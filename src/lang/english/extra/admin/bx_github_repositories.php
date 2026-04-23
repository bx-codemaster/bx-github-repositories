<?php
/* -----------------------------------------------------------------------------------------
	$Id: /lang/english/extra/admin/bx_github_repositories.php 1000 2026-04-23 12:00:00Z benax $
	
	modified eCommerce Shopsoftware
	http://www.modified-shop.org
	
	Copyright (c) 2009 - 2013 [www.modified-shop.org]
	-----------------------------------------------------------------------------------------
	Released under the GNU General Public License
	---------------------------------------------------------------------------------------*/

define('BX_GITHUB_REPOSITORIES_HEADING_TITLE', 'BX GitHub Repositories');
define('BX_GITHUB_REPOSITORIES_HEADING_SUB_TITLE', 'Phase 1 base structure is ready');
define('BX_GITHUB_REPOSITORIES_TEXT_INTRO', 'The base files are in place. Business logic will follow in the next phases.');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_HEADING', 'Setup Assistant');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_DESCRIPTION', 'This area prepares the future admin UI for customer-specific setup.');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_GOAL', 'The goal is to collect shop-specific GitHub app data and select the repositories to sync.');
define('BX_GITHUB_REPOSITORIES_LABEL_APP_ID', 'GitHub App ID');
define('BX_GITHUB_REPOSITORIES_LABEL_INSTALLATION_ID', 'Installation ID');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_UPLOAD', 'Upload PEM file');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY', 'Private Key (PEM)');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_STATUS', 'Private key status');
define('BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_UPLOAD_HELP', 'Upload the PEM file directly. Its content is validated server-side and stored encrypted.');
define('BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_HINT', 'Note: After saving, the key is no longer shown in plain text. You will see the encrypted string.');
define('BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_STORED', 'Stored in the database (encrypted).');
define('BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_MISSING', 'Not stored yet.');
define('BX_GITHUB_REPOSITORIES_BUTTON_TEST_CONNECTION', 'Test connection');
define('BX_GITHUB_REPOSITORIES_BUTTON_SAVE_SETTINGS', 'Save settings');
define('BX_GITHUB_REPOSITORIES_BUTTON_LOAD_REPOSITORIES', 'Load/update repositories');
define('BX_GITHUB_REPOSITORIES_BUTTON_SAVE_REPOSITORY_SELECTION', 'Save repository selection');
define('BX_GITHUB_REPOSITORIES_TAB_SETUP', 'Setup assistant');
define('BX_GITHUB_REPOSITORIES_TAB_REPOSITORIES', 'Repository selection');
define('BX_GITHUB_REPOSITORIES_LABEL_REPOSITORY', 'Repository');
define('BX_GITHUB_REPOSITORIES_LABEL_LOCAL_FILENAME', 'Local filename');
define('BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_HEADING', 'Repository selection');
define('BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_INTRO', 'After a successful connection test, all repositories for the installation are loaded and offered for selection here.');
define('BX_GITHUB_REPOSITORIES_TEXT_NO_REPOSITORIES_LOADED', 'No data loaded yet. Please test the connection first.');
define('BX_GITHUB_REPOSITORIES_TEXT_IMPORT_TARGET', 'Import target: The selected ZIP asset will later be stored in the shop &quot;download&quot; folder under a stable file name.');
define('BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE', 'Unknown response from GitHub');
define('BX_GITHUB_REPOSITORIES_ERROR_REPOSITORY_LOAD_FAILED', 'Repositories could not be loaded (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_JWT_CREATION_FAILED', 'JWT creation failed: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED', 'Connection test failed (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_APP_ID', 'GitHub App ID is invalid.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_INSTALLATION_ID', 'Installation ID is invalid.');
define('BX_GITHUB_REPOSITORIES_ERROR_UPLOAD_FAILED', 'PEM file could not be uploaded (upload error %d).');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_FILE_SIZE', 'PEM file is empty or larger than 32 KB.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_UPLOAD', 'PEM file upload is invalid.');
define('BX_GITHUB_REPOSITORIES_ERROR_FILE_READ_FAILED', 'PEM file could not be read.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_PRIVATE_KEY', 'The uploaded PEM file does not contain a valid private key.');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_PRIVATE_KEY_UPLOAD', 'Please upload a PEM file.');
define('BX_GITHUB_REPOSITORIES_ERROR_ENCRYPTION_FAILED', 'Encryption failed: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS', 'App ID and installation ID are not configured. Please save the settings first.');
define('BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED', 'Private key could not be decrypted: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_STORED_PRIVATE_KEY', 'No private key stored. Please save the settings first.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_CONNECTION_TEST', 'Connection successful. Installation token was created.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SETTINGS_SAVED', 'Settings were saved. The private key is stored in the database only in encrypted form.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SETTINGS_UPDATED', 'The following settings were saved: %s');
define('BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORIES_LOADED', 'Repositories synchronized: %d new, %d updated.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORY_SELECTION_SAVED', 'Repository selection saved. Active: %d.');
define('BX_GITHUB_REPOSITORIES_INFO_NO_SETTINGS_CHANGED', 'No changes detected. No settings were saved.');
define('BX_GITHUB_REPOSITORIES_INFO_DEACTIVATED_FILES_REMOVED', 'ZIP files of deactivated repositories removed: %d.');
define('BX_GITHUB_REPOSITORIES_INFO_SYNC_SKIPPED', 'Download skipped (no newer tag): %d.');
define('BX_GITHUB_REPOSITORIES_WARNING_FILE_DELETE_FAILED', 'Could not delete file: %s');
define('BX_GITHUB_REPOSITORIES_BUTTON_SYNC_RELEASES', 'Download Releases');
define('BX_GITHUB_REPOSITORIES_LABEL_VERSION', 'Version');
define('BX_GITHUB_REPOSITORIES_LABEL_LAST_CHECK', 'Last Check');
define('BX_GITHUB_REPOSITORIES_INFO_NO_ACTIVE_REPOSITORIES', 'No active repositories selected.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SYNC_COMPLETED', 'Sync completed: %d successful, %d errors.');
define('BX_GITHUB_REPOSITORIES_ERROR_NO_RELEASE_FOUND', 'No public release found for this repository.');
define('BX_GITHUB_REPOSITORIES_ERROR_NO_MATCHING_ASSET', 'No matching asset found in the release.');
define('BX_GITHUB_REPOSITORIES_ERROR_TEMP_FILE_CREATE', 'Could not create temporary file: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED', 'Download failed (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_EMPTY', 'The downloaded file is empty.');
define('BX_GITHUB_REPOSITORIES_ERROR_FILE_RENAME', 'Could not move file to target directory: %s');
