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

    public function getTA(): array {
        $cachePath = __DIR__ . '/../../secure/cache/ta_' . $this->society_id . '_' . $this->env . '.json';
        
        if (file_exists($cachePath)) {
            $ta = json_decode(file_get_contents($cachePath), true);
            if (is_array($ta) && isset($ta['expirationTime']) && time() < (strtotime($ta['expirationTime']) - 300)) {
                return $ta;
            }
        }

        $tra_xml = $this->create_TRA();
        $signed_tra_cms = $this->sign_TRA($tra_xml);
        $ta_response_xml_str = $this->call_WSAA($signed_tra_cms);
        
        $ta_xml = @simplexml_load_string($ta_response_xml_str);
        if (!$ta_xml) {
            throw new RuntimeException("WSAA: TA inválido (no XML). Contenido de loginCmsReturn: " . htmlspecialchars($ta_response_xml_str));
        }

        $new_ta = [
            'token'           => (string)$ta_xml->credentials->token,
            'sign'            => (string)$ta_xml->credentials->sign,
            'generationTime'  => (string)$ta_xml->header->generationTime,
            'expirationTime'  => (string)$ta_xml->header->expirationTime,
        ];

        if (!is_dir(dirname($cachePath))) @mkdir(dirname($cachePath), 0775, true);
        file_put_contents($cachePath, json_encode($new_ta));

        return $new_ta;
    }

    private function create_TRA(): string {
        $now = new DateTime();
        $uniqueId = time();
        $generationTime = $now->format('c');
        $expirationTime = $now->add(new DateInterval('PT2H'))->format('c'); // Validez de 2 horas

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

    /**
     * Firma el TRA replicando el método del cliente oficial de AFIP.
     * **ESTA ES LA VERSIÓN CORREGIDA FINAL**
     */
    private function sign_TRA(string $tra_xml): string {
        $certPath = "file://" . realpath($this->certPaths['cert']);
        $keyPath = "file://" . realpath($this->certPaths['key']);
        
        $in_file_path = tempnam(sys_get_temp_dir(), 'TRA_IN_');
        $out_file_path = tempnam(sys_get_temp_dir(), 'TRA_OUT_');
        file_put_contents($in_file_path, $tra_xml);

        // Se firma con la clave privada (asumiendo que no tiene contraseña) y sin flags extra
        $status = openssl_pkcs7_sign($in_file_path, $out_file_path, $certPath, [$keyPath, ''], [], 0);

        unlink($in_file_path);

        if (!$status) {
            unlink($out_file_path);
            $error = "Error al firmar el TRA con OpenSSL.";
            while ($msg = openssl_error_string()) { $error .= " | " . $msg; }
            throw new RuntimeException($error);
        }

        $signed_tra_pem = file_get_contents($out_file_path);
        unlink($out_file_path);
        
        // Se extrae el contenido Base64 entre los marcadores BEGIN/END, como en el cliente oficial
        if (preg_match('/-----BEGIN PKCS7-----(.*)-----END PKCS7-----/s', $signed_tra_pem, $matches)) {
            // Se eliminan todos los espacios en blanco (saltos de línea, etc.) del Base64
            $cms = preg_replace('/\s+/', '', $matches[1]);
            return $cms;
        } else {
            throw new RuntimeException("No se pudo extraer la firma PKCS7 del archivo firmado.");
        }
    }
    
    private function call_WSAA(string $cms_base64): string {
        $url = self::WSAA_URLS[$this->env];
        
        $soapReq = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsaa="http://wsaa.view.sua.dvadac.desein.afip.gov">' .
                     '<soapenv:Header/>' .
                     '<soapenv:Body>' .
                       '<wsaa:loginCms>' .
                         '<wsaa:in0>' . $cms_base64 . '</wsaa:in0>' .
                       '</wsaa:loginCms>' .
                     '</soapenv:Body>' .
                   '</soapenv:Envelope>';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $soapReq,
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
            throw new RuntimeException("Error en la llamada cURL a WSAA: {$error}");
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code != 200) {
            throw new RuntimeException("WSAA HTTP {$http_code} " . htmlspecialchars($response));
        }

        // Se extrae el contenido de la respuesta para devolver el XML limpio
        if (preg_match('/<loginCmsReturn>([\s\S]*)<\/loginCmsReturn>/', $response, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException("WSAA: No se pudo encontrar 'loginCmsReturn' en la respuesta. Respuesta: " . htmlspecialchars($response));
    }
}