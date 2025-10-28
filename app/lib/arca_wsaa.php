<?php
// /app/lib/arca_wsaa.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

class WSAAAuth {
    private ?int $society_id;
    private string $env;
    private string $service;
    private array $certPaths;

    // URLs del Web Service de Autenticación y Autorización
    private const WSAA_URLS = [
        'sandbox'    => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'production' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
    ];

    public function __construct(?int $society_id, string $env, string $service = 'wsfe') {
        $this->society_id = $society_id;
        $this->env = in_array($env, ['sandbox', 'production']) ? $env : 'production';
        $this->service = $service;

        if (!$this->society_id) {
            throw new InvalidArgumentException('Se requiere un ID de sociedad para la autenticación WSAA.');
        }

        $basePath = __DIR__ . '/../../secure/certs/' . $this->society_id . '/';
        $this->certPaths = [
            'cert' => $basePath . 'certificate.crt',
            'key'  => $basePath . 'private.key'
        ];

        if (!file_exists($this->certPaths['cert']) || !file_exists($this->certPaths['key'])) {
            throw new RuntimeException("No se encontraron el certificado o la clave privada para la sociedad ID {$this->society_id}.");
        }
    }

    /**
     * Obtiene el Ticket de Acceso (TA). Primero intenta cachearlo y si no, lo solicita a AFIP.
     */
    public function getTA(): array {
        // La caché se guarda en un archivo temporal para no solicitar un TA en cada transacción.
        $cachePath = __DIR__ . '/../../secure/cache/ta_' . $this->society_id . '_' . $this->env . '.json';
        
        if (file_exists($cachePath)) {
            $ta = json_decode(file_get_contents($cachePath), true);
            // Verifica que el TA no esté expirado (con un margen de seguridad)
            if (is_array($ta) && time() < (strtotime($ta['expirationTime']) - 300)) {
                return $ta;
            }
        }

        // Si no hay caché válida, se solicita uno nuevo a AFIP.
        $tra_xml = $this->create_TRA();
        $signed_tra = $this->sign_TRA($tra_xml);
        $ta_response = $this->call_WSAA($signed_tra);

        $ta_xml = simplexml_load_string($ta_response);
        if (!$ta_xml) {
            // Este es el error que estabas viendo: la respuesta de AFIP no es un XML válido.
            throw new RuntimeException("WSAA: TA inválido (no XML). Respuesta del servidor: " . htmlspecialchars($ta_response));
        }

        $credentials = $ta_xml->children('soap', true)->Body->children()->loginCmsResponse->loginCmsReturn;
        
        $new_ta = [
            'token'           => (string)$credentials->credentials->token,
            'sign'            => (string)$credentials->credentials->sign,
            'generationTime'  => (string)$credentials->header->generationTime,
            'expirationTime'  => (string)$credentials->header->expirationTime,
        ];

        // Guardar el nuevo TA en la caché
        if (!is_dir(dirname($cachePath))) mkdir(dirname($cachePath), 0775, true);
        file_put_contents($cachePath, json_encode($new_ta));

        return $new_ta;
    }

    /**
     * Crea el Ticket de Requerimiento de Acceso (TRA) en formato XML.
     */
    private function create_TRA(): string {
        $now = new DateTime();
        $uniqueId = time();
        $generationTime = $now->format('c');
        $expirationTime = $now->add(new DateInterval('PT1H'))->format('c'); // 1 hora de validez

        return <<<XML
<?xml version="1.0" encoding="UTF-8" ?>
<loginTicketRequest version="1.0">
  <header>
    <uniqueId>{$uniqueId}</uniqueId>
    <generationTime>{$generationTime}</generationTime>
    <expirationTime>{$expirationTime}</expirationTime>
  </header>
  <service>{$this->service}</service>
</loginTicketRequest>
XML;
    }

    /**
     * Firma el TRA usando el certificado y la clave privada.
     * **ESTA FUNCIÓN ESTÁ CORREGIDA**
     */
    private function sign_TRA(string $tra_xml): string {
        $cert = "file://" . realpath($this->certPaths['cert']);
        $pkey = "file://" . realpath($this->certPaths['key']);

        $status = openssl_pkcs7_sign(
            "php://stdin", // Usamos streams para evitar archivos temporales
            "php://stdout",
            $cert,
            $pkey,
            [],      // Sin cabeceras adicionales
            PKCS7_BINARY | PKCS7_DETACHED
        );

        if (!$status) {
            throw new RuntimeException("Error al firmar el TRA con OpenSSL.");
        }
        
        // --- ESTA ES LA CORRECCIÓN CLAVE ---
        // La salida de openssl_pkcs7_sign incluye cabeceras que AFIP no acepta.
        // Este código las elimina, dejando solo el bloque de la firma digital (CMS).
        $signed_tra_with_headers = stream_get_contents(STDIN);
        $parts = explode("\n\n", $signed_tra_with_headers, 2);
        
        return $parts[1] ?? ''; // Devolvemos solo la segunda parte, que es la firma
    }


    /**
     * Llama al Web Service de Autenticación (WSAA) de AFIP.
     */
    private function call_WSAA(string $cms_body): string {
        $url = self::WSAA_URLS[$this->env];
        
        $soapReq = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">
  <soapenv:Header/>
  <soapenv:Body>
    <wsaa:loginCms>
      <wsaa:in0>{$cms_body}</wsaa:in0>
    </wsaa:loginCms>
  </soapenv:Body>
</soapenv:Envelope>
XML;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soapReq,
            CURLOPT_HTTPHEADER     => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Error en la llamada a WSAA: {$error}");
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200) {
            // Este es el error que viste: "WSAA HTTP 500"
            throw new RuntimeException("WSAA HTTP {$http_code} " . htmlspecialchars($response));
        }

        return $response ?: '';
    }
}