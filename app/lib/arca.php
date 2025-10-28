<?php
// /app/lib/arca.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/arca_wsaa.php';

class ArcaClient {
    private string $env;
    private ?string $api_key;
    private ?string $api_secret;
    private int $pos_number;
    private ?int $society_id;
    private ?int $branch_id;

    private const URLS_REST = [
        'sandbox'    => 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
        'production' => 'https://wsfe.afip.gov.ar/wsfev1/service.asmx'
    ];

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

    public static function fromSaleId(int $sale_id): self {
        $sale = DB::one("SELECT s.*, b.society_id, b.default_pos_number
                         FROM sales s
                         JOIN branches b ON b.id = s.branch_id
                         WHERE s.id = ?", [$sale_id]);
        if (!$sale) throw new RuntimeException('Venta no encontrada');

        $society_id = (int)$sale['society_id'];
        $branch_id  = (int)$sale['branch_id'];

        // ==================================================================
        // *** ESTA ES LA LÍNEA CORREGIDA ***
        // Se eliminó "ORDER BY id DESC" porque la tabla `arca_accounts` no tiene esa columna.
        $acc = DB::one("SELECT * FROM arca_accounts WHERE society_id=? AND COALESCE(enabled,1)=1 LIMIT 1", [$society_id]);
        // ==================================================================

        if ($acc) {
          $env   = (string)($acc['env'] ?? 'mock');
          $key   = $acc['api_key'] ?: null;
          $sec   = $acc['api_secret'] ?: null;
          $pos   = (int)($acc['pos_number'] ?? ($sale['default_pos_number'] ?? 1));
          return new self($env, $key, $sec, $pos, $society_id, $branch_id);
        }

        $soc = DB::one("SELECT * FROM societies WHERE id=?", [$society_id]);
        if (!$soc) throw new RuntimeException('Society no encontrada');
        if (!($soc['arca_enabled'] ?? 0)) throw new RuntimeException('ARCA no habilitado en societies');

        $env = (string)($soc['arca_env'] ?? 'mock');
        $key = $soc['arca_api_key'] ?? null;
        $sec = $soc['arca_api_secret'] ?? null;
        $pos = (int)($soc['arca_pos_number'] ?? ($sale['default_pos_number'] ?? 1));

        return new self($env, $key ?: null, $sec ?: null, $pos, $society_id, $branch_id);
    }

    public function emitInvoiceForSale(int $sale_id): array {
        $sale = DB::one("SELECT s.*, b.society_id, so.name AS soc_legal_name, so.tax_id AS soc_tax_id
                        FROM sales s
                        JOIN branches b ON b.id = s.branch_id
                        JOIN societies so ON so.id = b.society_id
                        WHERE s.id = ?", [$sale_id]);
        if (!$sale) throw new RuntimeException('Venta no encontrada');

        if (strtoupper((string)($sale['doc_type'] ?? '')) !== 'INVOICE') {
             throw new RuntimeException('La venta no es de tipo Factura');
        }

        $letter = in_array($sale['cbte_letter'], ['A','B','C']) ? $sale['cbte_letter'] : 'B';
        $items = DB::all("SELECT si.*, p.name AS product_name, p.barcode FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?", [$sale_id]);
        if (!$items) throw new RuntimeException('La venta no tiene ítems');
        $customer = !empty($sale['customer_id']) ? DB::one("SELECT * FROM customers WHERE id=?", [(int)$sale['customer_id']]) : null;
        $totals = $this->computeTotals($sale, $items);

        $payload = [
          'point_of_sale' => $this->pos_number,
          'letter'        => $letter,
          'date'          => date('Y-m-d'),
          'receiver'      => $this->mapCustomer($customer, $letter),
          'totals'        => $totals
        ];

        if ($this->env === 'mock') {
            $out = $this->mockResponse();
            $this->updateSaleArca($sale_id, 'sent', $out);
            return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'mock'];
        }

        if (!empty($this->api_key) && !empty($this->api_secret)) {
            $err = 'El modo REST (api_key) no está implementado para la facturación SOAP.';
            $this->updateSaleArca($sale_id, 'error', ['arca_error'=>$err]);
            throw new RuntimeException($err);
        }

        $socCUIT = preg_replace('/\\D+/', '', (string)($sale['soc_tax_id'] ?? ''));
        if (!$socCUIT) throw new RuntimeException('CUIT de la sociedad vacío');
        $ta = (new WSAAAuth((int)$sale['society_id'], $this->env, 'wsfe'))->getTA();
        $wsfeUrl = self::WSFE_URLS[$this->env] ?? null;
        if (!$wsfeUrl) {
          $out = $this->mockResponse($ta['token'], $ta['sign']);
          $this->updateSaleArca($sale_id, 'sent', $out);
          return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'wsaa-mock'];
        }

        $cbteTipo = $this->mapCbteTipo($letter);
        $lastVoucherSoap = $this->buildFECompUltimoAutorizadoEnvelope($ta, $socCUIT, $this->pos_number, $cbteTipo);
        $lastVoucherResp = $this->soapCall($wsfeUrl, $lastVoucherSoap, 'http://ar.gov.afip.dif.wsfev1/FECompUltimoAutorizado');
        $lastVoucherNum = (int)$this->findTag($lastVoucherResp, 'CbteNro');
        $nextVoucherNum = $lastVoucherNum + 1;
        $soapEnv = $this->buildFECAESolicitarEnvelope($ta, $socCUIT, $this->pos_number, $letter, $payload, $nextVoucherNum);
        $soapResp = $this->soapCall($wsfeUrl, $soapEnv, 'http://ar.gov.afip.dif.wsfev1/FECAESolicitar');
        $result = $this->findTag($soapResp, 'Resultado');
        if ($result !== 'A') {
             $errors = '';
             preg_match_all('/<Msg>([\s\S]*?)<\/Msg>/i', $soapResp, $matches);
             if (!empty($matches[1])) $errors = implode('; ', array_map('htmlspecialchars', $matches[1]));
             $errorMsg = "ARCA rechazó (Resultado: {$result}). Errores: {$errors}";
             $this->updateSaleArca($sale_id, 'error', ['arca_error' => mb_substr($errorMsg, 0, 255)]);
             throw new RuntimeException($errorMsg);
        }
        $cae = $this->findTag($soapResp, 'CAE');
        $caeVto = $this->findTag($soapResp, 'CAEFchVto');
        if (!$cae) {
            $this->updateSaleArca($sale_id, 'error', ['arca_error'=>mb_substr('WSFE: Aprobado pero sin CAE. Resp: '.substr($soapResp,0,300),0,255)]);
            throw new RuntimeException('WSFE: no se encontró CAE/Número en la respuesta');
        }
        $out = ['cbte_number' => $nextVoucherNum, 'cae' => $cae, 'cae_due' => $caeVto ? date('Y-m-d', strtotime($caeVto)) : null, 'pdf_url' => null, 'qr_url' => null];
        $this->updateSaleArca($sale_id, 'sent', $out, ['pos_number' => $this->pos_number]);
        return ['ok'=>true, 'sale_id'=>$sale_id, 'env'=>$this->env, 'data'=>$out, '_mode'=>'wsaa-wsfe'];
    }
    // ===== Helpers (Mantenemos tus helpers originales sin cambios) =====

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
        if (!$cust) return ['name'=>'Consumidor Final', 'tax_id'=>'0', 'address'=>'', 'city'=>'', 'state'=>'', 'postal_code'=>''];
        return [
          'name'       => (string)($cust['name'] ?? 'Consumidor Final'),
          'tax_id'     => (string)($cust['tax_id'] ?? $cust['dni'] ?? '0'),
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

    private function updateSaleArca(int $sale_id, string $status, array $fields = [], array $extra_fields = []): void {
        $cols = DB::all("SHOW COLUMNS FROM sales");
        $existing = array_column($cols, 'Field');
        
        $data = ['arca_status' => $status] + $fields + $extra_fields;

        $set = []; $vals= [];
        foreach ($data as $k=>$v) {
            if (!in_array($k, $existing, true)) continue;
            $set[] = "`{$k}` = ?"; $vals[]= $v;
        }
        if (!count($set)) return;
    
        $vals[] = $sale_id;
        DB::run("UPDATE sales SET ".implode(',', $set)." WHERE id = ?", $vals);
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

    // ===== WSFE (SOAP) - SECCIÓN ACTUALIZADA =====

    private function buildFECompUltimoAutorizadoEnvelope(array $ta, string $cuit, int $ptoVta, int $cbteTipo): string {
        return <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ar="http://ar.gov.afip.dif.wsfev1/">
   <soap:Header/>
   <soap:Body>
      <ar:FECompUltimoAutorizado>
         <ar:Auth>
            <ar:Token>{$ta['token']}</ar:Token>
            <ar:Sign>{$ta['sign']}</ar:Sign>
            <ar:Cuit>{$cuit}</ar:Cuit>
         </ar:Auth>
         <ar:PtoVta>{$ptoVta}</ar:PtoVta>
         <ar:CbteTipo>{$cbteTipo}</ar:CbteTipo>
      </ar:FECompUltimoAutorizado>
   </soap:Body>
</soap:Envelope>
XML;
    }

    private function buildFECAESolicitarEnvelope(array $ta, string $cuit, int $ptoVta, string $letter, array $payload, int $nextVoucherNum): string {
        $cbteTipo = $this->mapCbteTipo($letter);
        $total    = number_format($payload['totals']['total'] ?? 0, 2, '.', '');
        $neto     = $total;
        $iva      = '0.00';
        
        $docNro   = preg_replace('/\\D+/', '', $payload['receiver']['tax_id'] ?? '0');
        $docTipo  = ($docNro === '0' || strlen($docNro) === 8) ? 96 : 80; // 96 DNI, 80 CUIT

        return <<<XML
<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ar="http://ar.gov.afip.dif.wsfev1/">
  <soap:Header/>
  <soap:Body>
    <ar:FECAESolicitar>
      <ar:Auth>
        <ar:Token>{$ta['token']}</ar:Token>
        <ar:Sign>{$ta['sign']}</ar:Sign>
        <ar:Cuit>{$cuit}</ar:Cuit>
      </ar:Auth>
      <ar:FeCAEReq>
        <ar:FeCabReq>
          <ar:CantReg>1</ar:CantReg>
          <ar:PtoVta>{$ptoVta}</ar:PtoVta>
          <ar:CbteTipo>{$cbteTipo}</ar:CbteTipo>
        </ar:FeCabReq>
        <ar:FeDetReq>
          <ar:FECAEDetRequest>
            <ar:Concepto>1</ar:Concepto>
            <ar:DocTipo>{$docTipo}</ar:DocTipo>
            <ar:DocNro>{$docNro}</ar:DocNro>
            <ar:CbteDesde>{$nextVoucherNum}</ar:CbteDesde>
            <ar:CbteHasta>{$nextVoucherNum}</ar:CbteHasta>
            <ar:CbteFch>{date('Ymd')}</ar:CbteFch>
            <ar:ImpTotal>{$total}</ar:ImpTotal>
            <ar:ImpTotConc>0</ar:ImpTotConc>
            <ar:ImpNeto>{$neto}</ar:ImpNeto>
            <ar:ImpOpEx>0</ar:ImpOpEx>
            <ar:ImpTrib>0</ar:ImpTrib>
            <ar:ImpIVA>{$iva}</ar:ImpIVA>
            <ar:MonId>PES</ar:MonId>
            <ar:MonCotiz>1</ar:MonCotiz>
          </ar:FECAEDetRequest>
        </ar:FeDetReq>
      </ar:FeCAEReq>
    </ar:FECAESolicitar>
  </soap:Body>
</soap:Envelope>
XML;
    }

    private function mapCbteTipo(string $letter): int {
        switch ($letter) {
          case 'A': return 1;
          case 'B': return 6;
          case 'C': return 11;
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
                'Content-Type: application/soap+xml; charset=utf-8',
                "Action: {$action}" 
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
            throw new RuntimeException("WSFE HTTP $code\n".htmlspecialchars(substr($resp, 0, 500)));
        }
        return $resp;
    }

    private function findTag(string $xml, string $tag): ?string {
        if (preg_match("/<{$tag}>([\\s\\S]*?)<\\/{$tag}>/i", $xml, $m)) return trim($m[1]);
        return null;
    }
}