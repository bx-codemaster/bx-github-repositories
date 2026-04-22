<?php
/**
 * GitHub App JWT Provider
 * 
 * Generiert RS256-signed JSON Web Tokens für GitHub App Authentication.
 * Basierend auf: https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/
 */
class bx_github_repositories_jwt_provider
{
    /**
     * Erstelle einen JWT für GitHub App Authentication (RS256)
     * 
     * @param int $app_id GitHub App ID (von GitHub Settings)
     * @param string $private_key_pem RSA Private Key im PEM-Format
     * @param int $iss_buffer Puffer für iat (in Sekunden) - Standard 60s (GitHub empfohlen)
     * @param int $exp_offset Expiry Offset von jetzt (in Sekunden) - Standard 540s = 9 Min (max 10 Min)
     * @return string JWT Token
     * @throws Exception Wenn Private Key ungültig
     */
    public static function buildJWT($app_id, $private_key_pem, $iss_buffer = 60, $exp_offset = 540)
    {
        if (!$app_id || !$private_key_pem) {
            throw new Exception('App ID und Private Key erforderlich für JWT Generation');
        }

        // JWT Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        $now = time();
        
        // JWT Payload (GitHub App)
        $payload = [
            'iat' => $now - $iss_buffer,    // Issued at (60s in past für Clock Skew)
            'exp' => $now + $exp_offset,    // Expires (540s = 9 Min, max 10 Min GitHub)
            'iss' => (int)$app_id           // Issuer (App ID)
        ];

        // Encodiere Header + Payload
        $header_encoded = self::base64UrlEncode(json_encode($header));
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        // Signatur-Input
        $signature_input = "{$header_encoded}.{$payload_encoded}";

        // RS256 Signature (OpenSSL RSA)
        $private_key_resource = openssl_pkey_get_private($private_key_pem);
        if (!$private_key_resource) {
            throw new Exception('Private Key ungültig oder nicht im PEM-Format');
        }

        $signature_binary = '';
        $sign_success = openssl_sign($signature_input, $signature_binary, $private_key_resource, OPENSSL_ALGO_SHA256);
        if (!$sign_success) {
            throw new Exception('JWT Signatur fehlgeschlagen (openssl_sign)');
        }

        // Signature zu Base64URL
        $signature_encoded = self::base64UrlEncode($signature_binary);

        // Finaler JWT
        return "{$signature_input}.{$signature_encoded}";
    }

    /**
     * Base64 URL-safe Encoding (JWT-Standard)
     * 
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        $b64 = base64_encode($data);
        // RFC 4648 - Remove padding, replace +/, with -_
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    /**
     * Normalisiere Private Key für Verwendung mit openssl_pkey_get_private
     * 
     * Handles:
     * - Escaped newlines (\n statt echte Newlines)
     * - Base64 encoded keys (dekodiert)
     * - Verifies PEM format
     * 
     * @param string $key
     * @return string Normalisierter PEM-Key
     */
    public static function normalizePrivateKey($key)
    {
        // Falls komplett Base64 encoded (z.B. nach DB-Speicherung)
        if (preg_match('/^[A-Za-z0-9+\/]+=*$/', trim($key))) {
            $decoded = base64_decode(trim($key), true);
            if ($decoded !== false && strpos($decoded, 'PRIVATE KEY') !== false) {
                $key = $decoded;
            }
        }

        // Escaped Newlines ersetzen
        $key = str_replace('\\n', "\n", $key);

        // Trim und PEM-Format-Check
        $key = trim($key);
        if (!preg_match('/^-----BEGIN.*PRIVATE KEY-----/', $key)) {
            throw new Exception('Private Key ist nicht im PEM-Format (BEGIN PRIVATE KEY erwartet)');
        }

        return $key;
    }
}
