<?php
/**
 * includes/extra/configure/bx_github_repositories.php
 * BX GitHub Repositories - Security Configuration
 *
 * Definiert den modul-spezifischen Schlüssel für die Verschlüsselung
 * sensibler GitHub-Daten (Tokens).
 *
 * Hinweis:
 * - Den Schlüssel sicher aufbewahren und nicht veröffentlichen.
 * - Ein nachträglicher Schlüsselwechsel erfordert Neuverschlüsselung bestehender Werte.
 */

defined('BX_GITHUB_REPOSITORIES_CRYPTO_KEY') or define('BX_GITHUB_REPOSITORIES_CRYPTO_KEY', 'c91d7e4a8f2b6035d4ab1c9e76f03d82b7e91a4c2d5f60b8a1c3e7d4f9260ab5');

/**
 * GitHub App Credentials (für App Authentication per JWT)
 * 
 * WICHTIG: Private Key muss mit BX_GITHUB_REPOSITORIES_CRYPTO_KEY verschlüsselt werden!
 * Die Verschlüsselung wird durch bx_github_repositories_crypto.php vorgenommen.
 * 
 * Falls leer (null), wird nur PAT (Bearer Token) unterstützt.
 */

// GitHub App ID (vom App-Setup auf github.com/settings/apps)
defined('BX_GITHUB_REPOSITORIES_APP_ID') or define('BX_GITHUB_REPOSITORIES_APP_ID', 3456729);

// GitHub Installation ID (Installation auf dem eigenen Account/Org)
defined('BX_GITHUB_REPOSITORIES_INSTALLATION_ID') or define('BX_GITHUB_REPOSITORIES_INSTALLATION_ID', 126088667);

// RSA Private Key (PEM Format, VERSCHLÜSSELT mit BX_GITHUB_REPOSITORIES_CRYPTO_KEY)
// Siehe Phase 6 Admin-UI für Verschlüsselung vor dem Speichern
defined('BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED') or define('BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED', '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');

/**
 * Token-Caching (Runtime Memory)
 * 
 * Installation Access Tokens sind ~1h gültig. Sie werden zur Laufzeit gecacht.
 * Speicher: $GLOBALS['bx_github_token_cache']
 * Format: ['token' => 'ghu_xxx', 'expires_at' => unix_timestamp, 'generated_at' => unix_timestamp]
 */
