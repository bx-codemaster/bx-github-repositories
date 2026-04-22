<?php

define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_TITLE', 'BX GitHub Repositories');
define('MODULE_BX_GITHUB_REPOSITORIES_TEXT_DESCRIPTION', '<h3 style="margin-top:0; display:flex; align-items:center; gap:8px;">'.xtc_image(DIR_WS_ICONS.'heading/bx_github_repositories.png', 'BX GitHub Repositories', '', '', 'style="max-height: 32px;"').' BX GitHub Repositories</h3><p>Synchronizes GitHub Repositories with download files in the shop.</p>');
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

define('CFG_TXT_HOURLY', 'hourly');
define('CFG_TXT_DAILY', 'daily');
define('CFG_TXT_WEEKLY', 'weekly');
define('CFG_TXT_MONTHLY', 'monthly');