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

