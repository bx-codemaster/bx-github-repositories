<?php

define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_TITLE', 'BX GitHub Repositories');
define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_DESCRIPTION', '
<details class="bxac-card">
  <summary class="bxac-summary" style="list-style: none;">
    <span class="bxac-arrow">▸</span>
    <span class="bxac-title">' . xtc_image(DIR_WS_ICONS.'heading/bx_github_repositories.png', 'BX GitHub Repositories', '', '', 'style="max-height: 32px; vertical-align: middle; margin-right: 8px;"') . 'BX GitHub Repositories</span>
  </summary>
  <div class="bxac-body">
    <h3 style="margin-top: 0;">Modul zur Synchronisation von GitHub-Repositories</h3>
    <p>Synchronisiert GitHub-Repositories mit Download-Dateien im Shop.</p>
  </div>
</details>');

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

define('MODULE_BX_GITHUB_REPOSITORIES_APP_ID_TITLE', 'GitHub App ID');
define('MODULE_BX_GITHUB_REPOSITORIES_APP_ID_DESC', 'Die ID der GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID_TITLE', 'GitHub Installation ID');
define('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID_DESC', 'Die Installation ID der GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED_TITLE', 'GitHub Private Key (verschlüsselt)');
define('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED_DESC', 'Der verschlüsselte private Schlüssel der GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID_TITLE', 'Template-Produkt ID');
define('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID_DESC', 'Die products_id des inaktiven Template-Produkts, das als Vorlage für automatisch angelegte Download-Produkte dient. Das Produkt legt Steuerklasse, Kategorie und weitere Standardwerte fest.');

define('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS_TITLE', 'moduleinfo.json Sprach-IDs');
define('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS_DESC', 'Kommagetrennte language_id-Werte der Sprachen, in die Name, Kurz- und Langbeschreibung aus der moduleinfo.json geschrieben werden (z. B. "2" für Deutsch oder "1,2" für Englisch und Deutsch). Leer lassen, um alle Sprachen zu aktualisieren.');

define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS_TITLE', 'Download: Max. Tage');
define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS_DESC', 'Wert für products_attributes_maxdays bei neu angelegten products_attributes_download-Einträgen.');

define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT_TITLE', 'Download: Max. Downloads');
define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT_DESC', 'Wert für products_attributes_maxcount bei neu angelegten products_attributes_download-Einträgen.');

define('CFG_TXT_HOURLY', 'Stündlich');
define('CFG_TXT_DAILY', 'Täglich');
define('CFG_TXT_WEEKLY', 'Wöchentlich');
define('CFG_TXT_MONTHLY', 'Monatlich');