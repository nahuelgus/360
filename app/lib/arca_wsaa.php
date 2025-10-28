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
     * Obtiene el Ticket de Acceso (TA).
     */
    public function getTA(): array {
        $cachePath = __DIR__ . '/../../secure/cache/ta_' . $this->society_id . '_' . $this->env . '.json';
        
        if (file_exists($cachePath)) {
            $ta = json_decode(file_get_contents($cachePath), true);
            if (is_array($ta) && time() < (strtotime($ta['expirationTime']) - 300)) {
                return $ta;
            }
        }

        $tra_xml = $this->create_TRA();
        $signed_tra = $this->sign_TRA($tra_xml);
        $ta_response = $this->call_WSAA($signed_tra);

        // Limpiamos la respuesta SOAP para quedarnos solo con el XML del Body
        if (preg_match('/<loginCmsReturn>([\s\S]*)<\/loginCmsReturn>/', $ta_response, $matches)) {
            $loginCmsReturn = $matches[1];
        } else {
            throw new RuntimeException("WSAA: No se pudo encontrar 'loginCmsReturn' en la respuesta. Respuesta del servidor: " . htmlspecialchars(substr($ta_response, 0, 1000)));
        }

        $ta_xml = @simplexml_load_string($loginCmsReturn);
        if (!$ta_xml) {
            throw new RuntimeException("WSAA: TA inválido (no XML). Contenido de loginCmsReturn: " . htmlspecialchars($loginCmsReturn));
        }
        
        $new_ta = [
            'token'           => (string)$ta_xml->credentials->token,
            'sign'            => (string)$ta_xml->credentials->sign,
            'generationTime'  => (string)$ta_xml->header->generationTime,
            'expirationTime'  => (string)$ta_xml->header->expirationTime,
        ];

        if (!is_dir(dirname($cachePath))) mkdir(dirname($cachePath), 0775, true);
        file_put_contents($cachePath, json_encode($new_ta));

        return $new_ta;
    }

    private function create_TRA(): string {
        $now = new DateTime();
        $uniqueId = time();
        $generationTime = $now->format('c');
        $expirationTime = $now->add(new DateInterval('PT1H'))->format('c');

        return '<?xml version="1.0" encoding="UTF-8" ?>' .
               '<loginTicketRequest version="1.0">' .
                 '<header>' .
                   '<uniqueId>' . $uniqueId . '</uniqueId>' .
                   '<generationTime>' . $generationTime . '</generationTime>' .
                   '<expirationTime>' . $expirationTime . '</expirationTime>' .
                 '</header>' .
                 '<service>' . $this->service . '</service>' .
               '</loginTicketRequest>';
    }

    private function sign_TRA(string $tra_xml): string {
        $certPath = "file://" . realpath($this->certPaths['cert']);
        $keyPath = "file://" . realpath($this->certPaths['key']);
        
        $in_file_path = tempnam(sys_get_temp_dir(), 'TRA_IN_');
        $out_file_path = tempnam(sys_get_temp_dir(), 'TRA_OUT_');
        file_put_contents($in_file_path, $tra_xml);

        $status = openssl_pkcs7_sign($in_file_path, $out_file_path, $certPath, $keyPath, [], PKCS7_BINARY | PKCS7_DETACHED);

        unlink($in_file_path);

        if (!$status) {
            unlink($out_file_path);
            $error = "Error al firmar el TRA con OpenSSL.";
            while ($msg = openssl_error_string()) { $error .= " | " . $msg; }
            throw new RuntimeException($error);
        }

        $signed_tra_with_headers = file_get_contents($out_file_path);
        unlink($out_file_path);

        $parts = explode("\n\n", $signed_tra_with_headers, 2);
        return base64_encode($parts[1] ?? ''); // Se envía codificado en Base64
    }

    /**
     * Llama al Web Service de Autenticación (WSAA) de AFIP.
     * **ESTA FUNCIÓN ESTÁ CORREGIDA**
     */
    private function call_WSAA(string $cms_body_base64): string {
        $url = self::WSAA_URLS[$this->env];
        
        $soapReq = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">' .
                     '<soapenv:Header/>' .
                     '<soapenv:Body>' .
                       '<wsaa:loginCms>' .
                         '<wsaa:in0>' . $cms_body_base64 . '</wsaa:in0>' .
                       '</wsaa:loginCms>' .
                     '</soapenv:Body>' .
                   '</soapenv:Envelope>';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soapReq,
            // --- ESTA ES LA CORRECCIÓN CLAVE ---
            // Se añade la cabecera SOAPAction requerida por el protocolo SOAP 1.1
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "loginCms"'
            ],
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
            throw new RuntimeException("WSAA HTTP {$http_code} " . htmlspecialchars($response));
        }

        return $response ?: '';
    }
}