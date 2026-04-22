<?php

define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_TITLE', 'BX GitHub Repositories');
define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_DESCRIPTION', '<h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">'.xtc_image(DIR_WS_ICONS.'heading/bx_github_repositories.png', 'BX GitHub Repositories', '', '', 'style="max-height: 32px;"').' BX GitHub Repositories</h3><p>Synchronisiert GitHub-Repositories mit Download-Dateien im Shop.</p>');

define('MODULE_BX_GITHUB_REPOSITORIES_STATUS_TITLE', 'Modul aktivieren');
define('MODULE_BX_GITHUB_REPOSITORIES_STATUS_DESC', 'Aktivieren oder deaktivieren Sie das Modul.');

define('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS_TITLE', 'Scheduled Tasks aktivieren');
define('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS_DESC', 'Aktiviert die periodische Prüfung auf neue Releases.');

define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL_TITLE', 'Prüfintervall Wert');
define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL_DESC', 'Intervallwert für die Release-Prüfung (z. B. 1).');

define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT_TITLE', 'Prüfintervall Einheit');
define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT_DESC', 'Einheit für das Prüfintervall (stündlich, täglich, wöchentlich, monatlich).');

define('MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT_TITLE', 'API Timeout');
define('MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT_DESC', 'Timeout für API-Anfragen in Sekunden.');

define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY_TITLE', 'API Retry Delay');
define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY_DESC', 'Verzögerung zwischen API-Wiederholungsversuchen in Sekunden.');

define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT_TITLE', 'API Retry Count');
define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT_DESC', 'Anzahl der API-Wiederholungsversuche.');

define('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG_TITLE', 'Auth Debug Logging');
define('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG_DESC', 'Aktiviert optionales Debug-Logging bei fehlgeschlagener GitHub App Authentifizierung vor PAT-Fallback.');

define('CFG_TXT_HOURLY', 'Stündlich');
define('CFG_TXT_DAILY', 'Täglich');
define('CFG_TXT_WEEKLY', 'Wöchentlich');
define('CFG_TXT_MONTHLY', 'Monatlich');