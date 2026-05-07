<?php

define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_TITLE', 'BX GitHub Repositories');
define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_DESCRIPTION', '
<details class="bxac-card">
  <summary class="bxac-summary" style="list-style: none;">
    <span class="bxac-arrow">▸</span>
    <span class="bxac-title">' . xtc_image(DIR_WS_ICONS.'heading/bx_github_repositories.png', 'BX GitHub Repositories', '', '', 'style="max-height: 32px; vertical-align: middle; margin-right: 8px;"') . 'BX GitHub Repositories</span>
  </summary>
  <div class="bxac-body">
    <h3 style="margin-top: 0;">Module for synchronizing GitHub repositories</h3>
    <p>Synchronizes GitHub Repositories with download files in the shop.</p>
  </div>
</details>');
define('MODULE_BX_GITHUB_REPOSITORIES_STATUS_TITLE', 'Enable module');
define('MODULE_BX_GITHUB_REPOSITORIES_STATUS_DESC', 'Enable or disable the module.');

define('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS_TITLE', 'Enable scheduled tasks');
define('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS_DESC', 'Enables periodic checks for new releases.');

define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL_TITLE', 'Check interval value');
define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL_DESC', 'Interval value for release checks (for example 1).');

define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT_TITLE', 'Check interval unit');
define('MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT_DESC', 'Unit for check interval (hourly, daily, weekly, monthly).');

define('MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT_TITLE', 'API Timeout');
define('MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT_DESC', 'Timeout for API requests in seconds.');

define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY_TITLE', 'API Retry Delay');
define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY_DESC', 'Delay between API retry attempts in seconds.');

define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT_TITLE', 'API Retry Count');
define('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT_DESC', 'Number of API retry attempts.');

define('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG_TITLE', 'Auth Debug Logging');
define('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG_DESC', 'Enables optional debug logging when GitHub App authentication fails before PAT fallback.');

define('MODULE_BX_GITHUB_REPOSITORIES_APP_ID_TITLE', 'GitHub App ID');
define('MODULE_BX_GITHUB_REPOSITORIES_APP_ID_DESC', 'The ID of the GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID_TITLE', 'GitHub Installation ID');
define('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID_DESC', 'The installation ID of the GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED_TITLE', 'GitHub Private Key (encrypted)');
define('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED_DESC', 'The encrypted private key of the GitHub App.');

define('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID_TITLE', 'Template Product ID');
define('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID_DESC', 'The products_id of the inactive template product used as a blueprint for automatically created download products. This product defines tax class, category, and other default values.');

define('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS_TITLE', 'moduleinfo.json Language IDs');
define('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS_DESC', 'Comma-separated language_id values of the languages into which name, short description and description from moduleinfo.json will be written (e.g. "2" for German or "1,2" for English and German). Leave empty to update all languages.');

define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS_TITLE', 'Download: Max days');
define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS_DESC', 'Value for products_attributes_maxdays when creating new products_attributes_download records.');

define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT_TITLE', 'Download: Max downloads');
define('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT_DESC', 'Value for products_attributes_maxcount when creating new products_attributes_download records.');

define('CFG_TXT_HOURLY', 'hourly');
define('CFG_TXT_DAILY', 'daily');
define('CFG_TXT_WEEKLY', 'weekly');
define('CFG_TXT_MONTHLY', 'monthly');