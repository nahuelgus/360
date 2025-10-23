<?php
// /app/lib/arca.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Si no usás API key/secret, vamos por WSAA (token/sign)
require_once __DIR__ . '/arca_wsaa.php';

class ArcaClient {
  private string $env;        // 'mock' | 'sandbox' | 'production'
  private ?string $api_key;
  private ?string $api_secret;
  private int    $pos_number; // Punto de venta
  private ?int   $society_id;
  private ?int   $branch_id;

  // ==== CONFIGURA ACÁ LAS URLs reales si vas a usar REST (API key/secret) ====
  private const URLS_REST = [
      'sandbox'    => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
  'production' => 'https://wsfe.afip.gov.ar/wsfev1/service.asmx'
  ];

  // ==== CONFIGURA ACÁ LAS URLs reales de WSFE (WSAA/WSFE – SOAP) ====
  // Si dejás null, el flujo WSAA devolverá MOCK (CAE/nro “de prueba”)
// WSFE AFIP (si querés emitir CAE real). Si preferís seguir mockeando, poné null.
private const WSFE_URLS = [
  'sandbox'    => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
  'production' => 'https://wsfe.afip.gov.ar/wsfev1/service.asmx'
];

  public function __construct(string $env, ?string $api_key, ?string $api_secret, int $pos_number, ?int $society_id=null, ?int $branch_id=null) {
    $env = strtolower($env);
    if (!in_array($env, ['mock','sandbox','production'], true)) $env = 'mock';
    $this->env        = $env;
    $this->api_key    = $api_key ?: null;
    $this->api_secret = $api_secret ?: null;
    $this->pos_number = max(1, $pos_number);
    $this->society_id = $society_id;
    $this->branch_id  = $branch_id;
  }

  /** Construye ArcaClient leyendo DB a partir de sale_id */
  public static function fromSaleId(int $sale_id): self {
    $sale = DB::one("SELECT s.*, b.society_id, b.default_pos_number
                     FROM sales s
                     JOIN branches b ON b.id = s.branch_id
                     WHERE s.id = ?", [$sale_id]);
    if (!$sale) throw new RuntimeException('Venta no encontrada');

    $society_id = (int)$sale['society_id'];
    $branch_id  = (int)$sale['branch_id'];

    // 1) Preferimos arca_accounts (por sociedad/sucursal)
    $acc = DB::one("SELECT * FROM arca_accounts WHERE society_id=? AND COALESCE(enabled,1)=1 ORDER BY id DESC LIMIT 1", [$society_id]);
    if ($acc) {
      $env   = (string)($acc['env'] ?? 'mock');
      $key   = $acc['api_key'] ?: null;
      $sec   = $acc['api_secret'] ?: null;
      $pos   = (int)($acc['pos_number'] ?? ($sale['default_pos_number'] ?? 1));
      return new self($env, $key, $sec, $pos, $society_id, $branch_id);
    }

    // 2) Fallback a societies.*
    $soc = DB::one("SELECT * FROM societies WHERE id=?", [$society_id]);
    if (!$soc) throw new RuntimeException('Society no encontrada');

    $enabled = (int)($soc['arca_enabled'] ?? 0);
    if (!$enabled) throw new RuntimeException('ARCA no habilitado en societies');

    $env = (string)($soc['arca_env'] ?? 'mock');
    $key = $soc['arca_api_key'] ?? null;
    $sec = $soc['arca_api_secret'] ?? null;
    $pos = (int)($soc['arca_pos_number'] ?? ($sale['default_pos_number'] ?? 1));

    return new self($env, $key ?: null, $sec ?: null, $pos, $society_id, $branch_id);
  }

  /** Emite la factura de una venta. Si env='mock' simula. Si hay api_key usa REST. Si no, usa WSAA→WSFE. */
  public function emitInvoiceForSale(int $sale_id): array {
    $sale = DB::one("SELECT s.*, b.society_id, b.name AS branch_name, b.city AS branch_city, b.state AS branch_state,
                            so.legal_name AS soc_legal_name, so.tax_id AS soc_tax_id, so.gross_income AS soc_gi,
                            so.address AS soc_address, so.city AS soc_city, so.state AS soc_state, so.postal_code AS soc_zip
                     FROM sales s
                     JOIN branches b ON b.id = s.branch_id
                     JOIN societies so ON so.id = b.society_id
                     WHERE s.id = ?", [$sale_id]);
    if (!$sale) throw new RuntimeException('Venta no encontrada');

    $doc_mode = strtoupper((string)($sale['doc_mode'] ?? 'TICKET_X'));
    if ($doc_mode !== 'INVOICE') throw new RuntimeException('La venta no es de tipo Factura');

    $letter = strtoupper((string)($sale['cbte_letter'] ?? 'B'));
    if (!in_array($letter, ['A','B','C'], true)) $letter = 'B';

    // Ítems
    $items = DB::all("SELECT si.*, p.name AS product_name, p.barcode
                      FROM sale_items si
                      JOIN products p ON p.id = si.product_id
                      WHERE si.sale_id = ?", [$sale_id]);
    if (!$items) throw new RuntimeException('La venta no tiene ítems');

    // Cliente (opcional)
    $customer = null;
    if (!empty($sale['customer_id'])) {
      $customer = DB::one("SELECT * FROM customers WHERE id=?", [(int)$sale['customer_id']]);
    }

    // Totales
    $totals = $this->computeTotals($sale, $items);

    // Payload genérico
    $payload = [
      'point_of_sale' => $this->pos_number,
      'letter'        => $letter,
      'date'          => date('Y-m-d'),
      'currency'      => 'ARS',
      'issuer'   => [
        'legal_name' => (string)($sale['soc_legal_name'] ?? ''),
        'tax_id'     => preg_replace('/\\D+/', '', (string)($sale['soc_tax_id'] ?? '')), // CUIT
      ],
      'receiver' => $this->mapCustomer($customer, $letter),
      'items'    => array_map(function($it){
        return [
          'code'        => (string)($it['barcode'] ?? $it['product_id']),
          'description' => (string)($it['product_name'] ?? 'Item'),
          'quantity'    => (float)$it['qty'],
          'unit_price'  => (float)$it['unit_price'],
          'discount_pct'=> (float)($it['discount_pct'] ?? 0),
        ];
      }, $items),
      'totals'   => [
        'subtotal'       => (float)$totals['subtotal'],
        'discount_total' => (float)$totals['discount_total'],
        'total'          => (float)$totals['total'],
      ]
    ];

    // ==== MODO MOCK ====
    if ($this->env === 'mock') {
      $out = $this->mockResponse();
      $this->updateSaleArca($sale_id, 'sent', $out);
      return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'mock'];
    }

    // ==== REST por API KEY/SECRET (si están cargadas) ====
    if (!empty($this->api_key) && !empty($this->api_secret)) {
      $url = self::URLS_REST[$this->env] ?? null;
      if (!$url) throw new RuntimeException('URL REST no configurada para '.$this->env);

      // Si tu REST requiere auth previa, agregala aquí (Bearer / etc.)
      $resp = $this->httpJson('POST', $url, $payload);
      if (!is_array($resp) || empty($resp['ok'])) {
        $err = is_array($resp) ? ($resp['error'] ?? 'ARCA REST: respuesta inválida') : 'ARCA REST: sin respuesta';
        $this->updateSaleArca($sale_id, 'error', ['arca_error'=>mb_substr($err,0,255)]);
        throw new RuntimeException($err);
      }
      $out = [
        'cbte_number' => (int)($resp['number'] ?? 0),
        'cae'         => (string)($resp['cae'] ?? ''),
        'cae_due'     => (string)($resp['cae_due'] ?? null),
        'pdf_url'     => (string)($resp['pdf_url'] ?? null),
        'qr_url'      => (string)($resp['qr_url'] ?? null),
      ];
      $this->updateSaleArca($sale_id, 'sent', $out);
      return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'rest'];
    }

    // ==== WSAA → WSFE (token/sign) ====
    // 1) Obtener TA (token/sign) para el servicio 'wsfe'
    $socCUIT = preg_replace('/\\D+/', '', (string)($sale['soc_tax_id'] ?? ''));
    if (!$socCUIT) throw new RuntimeException('CUIT de la sociedad vacío');

    $ta = (new WSAAAuth((int)$sale['society_id'], $this->env, 'wsfe'))->getTA();
    $token = $ta['token'];
    $sign  = $ta['sign'];

    // 2) Invocar WSFE si hay URL definida; si no, devolver MOCK con TA (para pruebas)
    $wsfeUrl = self::WSFE_URLS[$this->env] ?? null;
    if (!$wsfeUrl) {
      $out = $this->mockResponse($token, $sign);
      $this->updateSaleArca($sale_id, 'sent', $out);
      return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'wsaa-mock'];
    }

    // 3) Armar FECAESolicitar (SOAP)
    $soapEnv = $this->buildFECAESolicitarEnvelope($token, $sign, $socCUIT, $this->pos_number, $letter, $payload, $sale_id);

    $soapResp = $this->soapCall($wsfeUrl, $soapEnv, 'FECAESolicitar');

    // Parsear (esto depende del WSDL real; lo dejamos genérico)
    // Buscamos tags típicos: <CAE>, <CAEFchVto>, <CbteDesde> o <CbteNro>
    $cae = $this->findTag($soapResp, 'CAE');
    $caeVto = $this->findTag($soapResp, 'CAEFchVto');
    $nro = (int)($this->findTag($soapResp, 'CbteDesde') ?? $this->findTag($soapResp, 'CbteNro') ?? 0);

    if (!$cae || !$nro) {
      $this->updateSaleArca($sale_id, 'error', ['arca_error'=>mb_substr('WSFE: sin CAE/Nro. Resp: '.substr($soapResp,0,300),0,255)]);
      throw new RuntimeException('WSFE: no se encontró CAE/Número en la respuesta');
    }

    $out = [
      'cbte_number' => $nro,
      'cae'         => $cae,
      'cae_due'     => $caeVto ?: null,
      'pdf_url'     => null,
      'qr_url'      => null,
    ];
    $this->updateSaleArca($sale_id, 'sent', $out);
    return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'wsaa-wsfe'];
  }

  // ===== Helpers =====

  private function computeTotals(array $sale, array $items): array {
    $cols = DB::all("SHOW COLUMNS FROM sales");
    $has = function($f) use ($cols){ foreach($cols as $c) if($c['Field']===$f) return true; return false; };
    if ($has('subtotal') && $has('discount_total') && $has('total')) {
      return [
        'subtotal'       => (float)($sale['subtotal'] ?? 0),
        'discount_total' => (float)($sale['discount_total'] ?? 0),
        'total'          => (float)($sale['total'] ?? 0),
      ];
    }
    $subtotal = 0.0;
    foreach ($items as $it) {
      $disc = (float)($it['discount_pct'] ?? 0);
      $line = (float)$it['qty'] * (float)$it['unit_price'] * (1 - max(0,min(100,$disc))/100.0);
      $subtotal += $line;
    }
    return [
      'subtotal' => round($subtotal,2),
      'discount_total' => 0.0,
      'total' => round($subtotal,2),
    ];
  }

  private function mapCustomer(?array $cust, string $letter): ?array {
    if (!$cust) return null;
    return [
      'name'       => (string)($cust['name'] ?? 'Consumidor Final'),
      'tax_id'     => (string)($cust['tax_id'] ?? $cust['dni'] ?? ''),
      'address'    => (string)($cust['address'] ?? ''),
      'city'       => (string)($cust['city'] ?? ''),
      'state'      => (string)($cust['state'] ?? ''),
      'postal_code'=> (string)($cust['postal_code'] ?? '')
    ];
  }

  private function httpJson(string $method, string $url, array $payload): array {
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json'];
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => $headers,
      CURLOPT_TIMEOUT        => 45,
      CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
      $err = curl_error($ch); curl_close($ch);
      return ['ok'=>false,'error'=>'cURL: '.$err];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($body, true);
    if (!is_array($json)) {
      return ['ok'=>false,'error'=>'HTTP '.$code.'; respuesta no JSON: '.mb_substr($body,0,500)];
    }
    if ($code < 200 || $code >= 300) {
      $msg = $json['error'] ?? ('HTTP '.$code);
      return ['ok'=>false,'error'=>$msg, 'raw'=>$json];
    }
    return $json;
  }

  private function updateSaleArca(int $sale_id, string $status, array $fields = []): void {
    $cols = DB::all("SHOW COLUMNS FROM sales");
    $existing = array_column($cols, 'Field');

    $data = ['arca_status' => $status] + $fields;

    $set = []; $vals= [];
    foreach ($data as $k=>$v) {
      if (!in_array($k, $existing, true)) continue;
      $set[] = "$k = ?"; $vals[]= $v;
    }
    if (!count($set)) return;

    $vals[] = $sale_id;
    DB::q("UPDATE sales SET ".implode(',', $set)." WHERE id = ?", $vals);
  }

  private function mockResponse(?string $token=null, ?string $sign=null): array {
    $nro = rand(10000,99999);
    $cae = substr(strtoupper(sha1(($token?:'t').($sign?:'s').microtime(true))),0,14);
    return [
      'cbte_number' => $nro,
      'cae'         => $cae,
      'cae_due'     => date('Y-m-d', strtotime('+10 days')),
      'pdf_url'     => null,
      'qr_url'      => null,
    ];
  }

  // ===== WSFE (SOAP) =====

  private function buildFECAESolicitarEnvelope(string $token, string $sign, string $cuit, int $ptoVta, string $letter, array $payload, int $sale_id): string {
    // NOTA: Este envelope es genérico; ajustalo al WSDL real de ARCA (nombres/tags).
    $cbteTipo = $this->mapCbteTipo($letter); // 1=A, 6=B, 11=C (ejemplo típico)
    $importe  = number_format($payload['totals']['total'] ?? 0, 2, '.', '');

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsfe="http://ar.gov.afip.dif.FEV1/">
  <soapenv:Header/>
  <soapenv:Body>
    <wsfe:FECAESolicitar>
      <wsfe:Auth>
        <wsfe:Token>{$token}</wsfe:Token>
        <wsfe:Sign>{$sign}</wsfe:Sign>
        <wsfe:Cuit>{$cuit}</wsfe:Cuit>
      </wsfe:Auth>
      <wsfe:FeCAEReq>
        <wsfe:FeCabReq>
          <wsfe:CantReg>1</wsfe:CantReg>
          <wsfe:PtoVta>{$ptoVta}</wsfe:PtoVta>
          <wsfe:CbteTipo>{$cbteTipo}</wsfe:CbteTipo>
        </wsfe:FeCabReq>
        <wsfe:FeDetReq>
          <wsfe:FECAEDetRequest>
            <wsfe:Concepto>1</wsfe:Concepto>
            <wsfe:DocTipo>99</wsfe:DocTipo>
            <wsfe:DocNro>0</wsfe:DocNro>
            <wsfe:CbteDesde>0</wsfe:CbteDesde>
            <wsfe:CbteHasta>0</wsfe:CbteHasta>
            <wsfe:CbteFch>{date('Ymd')}</wsfe:CbteFch>
            <wsfe:ImpTotal>{$importe}</wsfe:ImpTotal>
            <wsfe:ImpTotConc>0.00</wsfe:ImpTotConc>
            <wsfe:ImpNeto>{$importe}</wsfe:ImpNeto>
            <wsfe:ImpOpEx>0.00</wsfe:ImpOpEx>
            <wsfe:ImpIVA>0.00</wsfe:ImpIVA>
            <wsfe:ImpTrib>0.00</wsfe:ImpTrib>
            <wsfe:MonId>PES</wsfe:MonId>
            <wsfe:MonCotiz>1.000</wsfe:MonCotiz>
          </wsfe:FECAEDetRequest>
        </wsfe:FeDetReq>
      </wsfe:FeCAEReq>
    </wsfe:FECAESolicitar>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    return $xml;
  }

  private function mapCbteTipo(string $letter): int {
    // Mapeo típico AFIP/WSFE; ajustá si ARCA usa otros códigos
    switch ($letter) {
      case 'A': return 1;   // Factura A
      case 'B': return 6;   // Factura B
      case 'C': return 11;  // Factura C
      default:  return 6;
    }
  }

  private function soapCall(string $url, string $envelope, string $action): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $envelope,
      CURLOPT_HTTPHEADER     => [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "'.$action.'"'
      ],
      CURLOPT_TIMEOUT        => 60,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
      $err = curl_error($ch); curl_close($ch);
      throw new RuntimeException("WSFE cURL error: $err");
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
      throw new RuntimeException("WSFE HTTP $code\n".$resp);
    }
    return $resp;
  }

  private function findTag(string $xml, string $tag): ?string {
    if (preg_match("/<{$tag}>([\\s\\S]*?)<\\/{$tag}>/i", $xml, $m)) return trim($m[1]);
    return null;
  }
}
