<?php

/**
 * GitHub App Manager
 * 
 * Verwaltet die GitHub App Authentication:
 * 1. JWT aus Private Key generieren
 * 2. Installation Access Token austauschen (POST /app/installations/{id}/access_tokens)
 * 3. Token cachen (Runtime Memory, ~1h Gültigkeit)
 * 4. Auto-Refresh bei Ablauf
 */
class bx_github_repositories_app_manager
{
    private $crypto;
    private static $token_cache = [];

    /**
     * @param bx_github_repositories_crypto $crypto Crypto Helper
     */
    public function __construct($crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * Hole Installation Access Token (mit Auto-Refresh)
     * 
     * Logik:
     * 1. Prüfe Runtime Cache (selbstklasse static)
     * 2. Falls abgelaufen/leer: JWT generieren → Token austauschen
     * 3. Cachen für nächste 55 Minuten (60 Min Gültigkeit, 5 Min Sicherheitspuffer)
     * 
     * @return string Installation Access Token (Bearer Token für API-Calls)
     * @throws Exception Wenn App-Credentials nicht konfiguriert
     */
    public function getInstallationAccessToken()
    {
        // App-Credentials aus Config
        $app_id = BX_GITHUB_REPOSITORIES_APP_ID ?? null;
        $installation_id = BX_GITHUB_REPOSITORIES_INSTALLATION_ID ?? null;
        $private_key_encrypted = BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED ?? null;

        if (!$app_id || !$installation_id || !$private_key_encrypted) {
            throw new Exception('GitHub App ist nicht konfiguriert (App ID / Installation ID / Private Key fehlen)');
        }

        // Cache-Key
        $cache_key = "installation_{$installation_id}";

        // Prüfe Runtime Cache (selbstklasse static)
        if (isset(self::$token_cache[$cache_key])) {
            $cached = self::$token_cache[$cache_key];
            if (time() < $cached['expires_at'] - 60) { // 60s Sicherheitspuffer
                return $cached['token'];
            }
        }

        // Entschlüssele Private Key
        $private_key_decrypted = $this->crypto->decryptToken($private_key_encrypted);
        $private_key_normalized = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key_decrypted);

        // 1. Generiere JWT
        $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $private_key_normalized);

        // 2. Tausche gegen Installation Token aus
        $token_data = $this->exchangeJwtForToken($jwt, $installation_id);

        // 3. Cache
        self::$token_cache[$cache_key] = [
            'token' => $token_data['token'],
            'expires_at' => strtotime($token_data['expires_at']),
            'cached_at' => time()
        ];

        return $token_data['token'];
    }

    /**
     * POST /app/installations/{installation_id}/access_tokens
     * 
     * Tauscht JWT gegen Installation Access Token
     * 
     * @param string $jwt JWT Bearer Token
     * @param int $installation_id GitHub Installation ID
     * @return array Response mit 'token' und 'expires_at'
     * @throws Exception Bei HTTP-Fehler
     */
    private function exchangeJwtForToken($jwt, $installation_id)
    {
        $endpoint = 'https://api.github.com/app/installations/' . (int)$installation_id . '/access_tokens';

        $response = $this->curlPost($endpoint, [], $jwt);

        // Response validieren
        if (!isset($response['token']) || !isset($response['expires_at'])) {
            throw new Exception('GitHub App Token Exchange fehlgeschlagen: Ungültige Response');
        }

        return $response;
    }

    /**
     * Fallback: Direkter cURL POST (für Tests/Fallback)
     * 
     * @param string $url
     * @param array $data
     * @param string $jwt
     * @return array JSON Response
     */
    private function curlPost($url, $data, $jwt)
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $jwt,
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
                'User-Agent: bx-github-repositories'
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response_json = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 201) {
            throw new Exception("GitHub Token Exchange fehlgeschlagen (HTTP {$http_code}): {$response_json}");
        }

        return json_decode($response_json, true);
    }

    /**
     * Lösche Cache (z.B. bei Deinstallation)
     */
    public static function clearCache()
    {
        self::$token_cache = [];
    }

    /**
     * Debug: Gib Cache-Status aus
     */
    public static function getCacheStatus()
    {
        return self::$token_cache;
    }
}
