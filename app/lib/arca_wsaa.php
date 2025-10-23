<?php
// /app/lib/arca_wsaa.php
declare(strict_types=1);

class WSAAAuth {
  // Endpoints oficiales AFIP (manual)
  const LOGINCMS = [
    'sandbox'    => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
    'production' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
  ];

  private string $certDir;
  private string $env;
  private int $societyId;
  private string $service;

  public function __construct(int $societyId, string $env='sandbox', string $service='wsfe', ?string $certDir=null) {
    $this->societyId = $societyId;
    $this->env       = in_array($env, ['sandbox','production'], true) ? $env : 'sandbox';
    $this->service   = $service;
    $this->certDir   = $certDir ?: (dirname(__DIR__,2)."/secure/certs/{$societyId}");
    // Recomendado por manual: zona horaria GMT-3 y reloj sincronizado (NTP). :contentReference[oaicite:5]{index=5}
    if (!ini_get('date.timezone')) @date_default_timezone_set('America/Argentina/Buenos_Aires');
  }

  public function getTA(): array {
    $crt = $this->certDir.'/certificate.crt';
    $key = $this->certDir.'/private.key';
    if (!is_file($crt) || !is_file($key)) {
      throw new RuntimeException("Faltan certificados para society {$this->societyId}:\n$crt\n$key");
    }

    // 1) TRA con formato ISO8601 local y margen ±60s (como en el ejemplo PHP del manual). :contentReference[oaicite:6]{index=6}
    $tra = $this->buildTRA($this->service);
    $traFile = tempnam(sys_get_temp_dir(), 'TRA_').'.xml';
    file_put_contents($traFile, $tra);

    // 2) Firmar en PKCS7 **NO DETACHED** (igual al manual), generará un S/MIME.
    $tmpSmime = tempnam(sys_get_temp_dir(), 'SMIME_').'.tmp';
    $ok = openssl_pkcs7_sign(
      $traFile, $tmpSmime, "file://$crt", ["file://$key", ''],
      [], /* headers */
      PKCS7_BINARY /* NO PKCS7_DETACHED */
    );
    if (!$ok) throw new RuntimeException('No se pudo firmar el TRA (openssl_pkcs7_sign)');

    // 3) Extraer el bloque base64 del S/MIME (misma técnica que el ejemplo PHP oficial). :contentReference[oaicite:7]{index=7}
    $cmsB64 = $this->smimeToBase64($tmpSmime);
    if (!$cmsB64) throw new RuntimeException('No se pudo extraer el CMS base64.');

    // 4) SOAP loginCms al WSAA con SOAPAction=urn:LoginCms (como en el curl del manual). :contentReference[oaicite:8]{index=8}
    $url  = self::LOGINCMS[$this->env];
    $envelope = $this->soapEnvelopeLoginCms($cmsB64);
    $resp = $this->soapCall($url, $envelope, 'urn:LoginCms');

    // 5) Parsear TA: credentials/token y credentials/sign (tal cual muestra el manual). :contentReference[oaicite:9]{index=9}
    $taXml = $this->extractFirstTag($resp, 'loginCmsReturn');
    if (!$taXml) throw new RuntimeException('WSAA: respuesta sin loginCmsReturn.');
    $sx = @simplexml_load_string($taXml);
    if (!$sx) throw new RuntimeException('WSAA: TA inválido (no XML).');

    $token = (string)$sx->credentials->token;
    $sign  = (string)$sx->credentials->sign;
    $until = (string)$sx->header->expirationTime;

    if (!$token || !$sign) throw new RuntimeException('WSAA: faltan token/sign.');

    return [
      'ok'=>true,
      'token'=>$token,
      'sign'=>$sign,
      'expires_at'=>$until,
      'raw_ta_xml'=>$taXml
    ];
  }

  private function buildTRA(string $service): string {
    $gen = date('c', time()-60);
    $exp = date('c', time()+60);
    $uniq= (string)time();
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>{$uniq}</uniqueId>
    <generationTime>{$gen}</generationTime>
    <expirationTime>{$exp}</expirationTime>
  </header>
  <service>{$service}</service>
</loginTicketRequest>
XML;
  }

  private function smimeToBase64(string $smimePath): ?string {
    $lines = file($smimePath, FILE_IGNORE_NEW_LINES);
    if (!$lines) return null;
    // Quitar cabeceras S/MIME (primeras 4 líneas aprox.) como sugiere el ejemplo de AFIP. :contentReference[oaicite:10]{index=10}
    $start = 0; $out = '';
    foreach ($lines as $i=>$ln) { if ($i >= 4) $out .= $ln."\n"; }
    $out = trim($out);
    // Si vino con -----BEGIN PKCS7----, extraer contenido interno
    if (preg_match('/-----BEGIN PKCS7-----([\\s\\S]+?)-----END PKCS7-----/',$out,$m)) {
      return trim($m[1]);
    }
    return $out ?: null;
  }

  private function soapEnvelopeLoginCms(string $cmsB64): string {
    $cmsEsc = htmlspecialchars($cmsB64, ENT_XML1|ENT_COMPAT, 'UTF-8');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">
  <soapenv:Header/>
  <soapenv:Body>
    <wsaa:loginCms>
      <wsaa:in0>{$cmsEsc}</wsaa:in0>
    </wsaa:loginCms>
  </soapenv:Body>
</soapenv:Envelope>
XML;
  }

  private function soapCall(string $url, string $envelope, string $soapAction): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $envelope,
      CURLOPT_HTTPHEADER     => [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: '.$soapAction
      ],
      CURLOPT_TIMEOUT        => 60,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $e = curl_error($ch); curl_close($ch); throw new RuntimeException("WSAA cURL error: $e"); }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) throw new RuntimeException("WSAA HTTP $code\n".$resp);
    return $resp;
  }

  private function extractFirstTag(string $xml, string $tag): ?string {
    if (preg_match("/<{$tag}>([\\s\\S]*?)<\\/{$tag}>/",$xml,$m)) return $m[1];
    return null;
  }
}
