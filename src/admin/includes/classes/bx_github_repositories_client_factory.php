<?php

/**
 * GitHub Client Factory
 * 
 * Erstellt und konfiguriert KnpLabs GitHub Client mit korrekter Authentifizierung:
 * - App Authentication (JWT → Installation Token)
 * - PAT Authentication (Bearer Token)
 * - Token Resolution Hierarchy
 */
class bx_github_repositories_client_factory
{
    private $app_manager;
    private $crypto;

    /**
     * @param bx_github_repositories_app_manager $app_manager
     * @param bx_github_repositories_crypto $crypto
     */
    public function __construct($app_manager, $crypto)
    {
        $this->app_manager = $app_manager;
        $this->crypto = $crypto;
    }

    /**
     * Erstelle GitHub Client mit Auto-Auth
     * 
     * Authentifizierungshierarchie:
     * 1. GitHub App (Installation Access Token) - wenn konfiguriert
     * 2. PAT (Personal Access Token) - fallback
     * 3. Unauthenticated - Falls beide fehlen
     * 
     * @param string|null $fallback_pat Optional: PAT Token (fallback)
     * @return \Github\Client KnpLabs Client (ready to use)
     * @throws Exception Wenn beide Auth-Methoden fehlen
     */
    public function createClient($fallback_pat = null)
    {
        // Versuche KnpLabs Client zu laden
        if (!class_exists('\Github\Client')) {
            throw new Exception('KnpLabs Github\Client nicht verfügbar. Bitte modified_github composer-Paket laden.');
        }

        // Erstelle Client
        $client = new \Github\Client();

        // Versuche App Authentication
        try {
            $installation_token = $this->app_manager->getInstallationAccessToken();
            $client->authenticate($installation_token, null, \Github\AuthMethod::ACCESS_TOKEN);
            return $client; // Erfolgreich
        } catch (Exception $e) {
            $this->logAppAuthFallback($e);
            // App nicht konfiguriert, probiere PAT
        }

        // Fallback zu PAT
        $pat = $fallback_pat ?? $this->getPATFromConfig();
        if (!$pat) {
            throw new Exception('Keine GitHub Authentifizierung verfügbar (App und PAT beide leer)');
        }

        // PAT entschlüsseln falls nötig (optional)
        if ($this->crypto->isEncrypted($pat)) {
            $pat = $this->crypto->decryptToken($pat);
        }

        $client->authenticate($pat, null, \Github\AuthMethod::ACCESS_TOKEN);
        return $client;
    }

    /**
     * Hole PAT aus Config
     * 
     * @return string|null
     */
    private function getPATFromConfig()
    {
        // TODO: PAT Configuration (Phase 6 Admin-UI wird dies setzen)
        // Für jetzt: Lese aus optionalen Konstanten/DB-Konfiguration
        return null;
    }

    /**
     * Optionales Debug-Logging fuer App-Auth-Fehler vor PAT-Fallback.
     */
    private function logAppAuthFallback(Exception $exception): void
    {
        if (!$this->isAuthDebugEnabled()) {
            return;
        }

        $message = 'GitHub App authentication failed. Falling back to PAT.';
        $context = [
            'exception_class' => get_class($exception),
            'reason' => $this->sanitizeLogMessage($exception->getMessage()),
            'fallback' => 'pat',
        ];

        $logger = $this->getAuthLogger();
        if ($logger !== null) {
            $logger->warning($message, array_merge($context, ['exception' => $exception]));
            return;
        }

        // Fallback ohne Logger-Klasse: strukturierte Fehlerzeile ohne sensible Daten.
        error_log($message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Debug-Schalter fuer Auth-Logging (default: aus).
     */
    private function isAuthDebugEnabled(): bool
    {
        if (defined('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG')) {
            $value = constant('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG');
        } else {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * Lazy-Init des Shop-Loggers fuer Auth-Debug.
     */
    private function getAuthLogger(): ?LoggingManager
    {
        if (!class_exists('LoggingManager')) {
            if (defined('DIR_FS_CATALOG')) {
                $logger_file = DIR_FS_CATALOG . 'includes/classes/class.logger.php';
                if (is_file($logger_file)) {
                    require_once($logger_file);
                }
            }
        }

        if (!class_exists('LoggingManager')) {
            return null;
        }

        $log_dir = defined('DIR_FS_LOG') ? DIR_FS_LOG : (defined('DIR_FS_CATALOG') ? DIR_FS_CATALOG . 'log/' : '');
        if ($log_dir === '') {
            return null;
        }

        $logfile = rtrim($log_dir, '/\\') . '/bx_github_repositories_auth_%s_%s.log';

        try {
            return new LoggingManager($logfile, 'bx_github_repositories.auth', 'debug');
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Entfernt Zeilenumbrueche und kuerzt sehr lange Meldungen fuer Log-Ausgaben.
     */
    private function sanitizeLogMessage(string $message): string
    {
        $single_line = str_replace(["\r", "\n"], ' ', trim($message));
        return mb_substr($single_line, 0, 400);
    }

    /**
     * Test: Validiere Authentifizierung
     * 
     * @return bool
     */
    public function validateAuthentication()
    {
        try {
            $client = $this->createClient();
            $core_limit = $client->api('rateLimit')->getResource('core');
            return $core_limit !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Hole aktuelles Rate-Limit Status
     * 
     * @return array|null
     */
    public function getRateLimitStatus()
    {
        try {
            $client = $this->createClient();
            $core_limit = $client->api('rateLimit')->getResource('core');

            if ($core_limit === false) {
                return null;
            }

            return [
                'name' => $core_limit->getName(),
                'limit' => $core_limit->getLimit(),
                'remaining' => $core_limit->getRemaining(),
                'reset' => $core_limit->getReset(),
            ];
        } catch (Exception $e) {
            return null;
        }
    }
}
