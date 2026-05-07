<?php
/* -----------------------------------------------------------------------------------------
   BX GitHub Repositories - Scheduled Task: Check
   ---------------------------------------------------------------------------------------*/

function cron_github_repositories_check() {
  if (!defined('MODULE_BX_GITHUB_REPOSITORIES_STATUS')
    || (string)constant('MODULE_BX_GITHUB_REPOSITORIES_STATUS') !== 'True'
    || !defined('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS')
    || (string)constant('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS') !== 'True') {
    return true;
  }

  require_once(DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
  bx_dependency_resolver::require('modified_github');

  require_once(DIR_FS_CATALOG . DIR_ADMIN . 'includes/classes/bx_github_repositories_crypto.php');
  require_once(DIR_FS_CATALOG . DIR_ADMIN . 'includes/classes/bx_github_repositories_jwt_provider.php');
  require_once(DIR_FS_CATALOG . DIR_ADMIN . 'includes/extra/functions/bx_github_repositories.php');

  $app_id = defined('MODULE_BX_GITHUB_REPOSITORIES_APP_ID') ? (int)constant('MODULE_BX_GITHUB_REPOSITORIES_APP_ID') : 0;
  $installation_id = defined('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID') ? (int)constant('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID') : 0;
  $private_key_encrypted = defined('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED')
    ? (string)constant('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED')
    : '';

  if ($app_id <= 0 || $installation_id <= 0 || $private_key_encrypted === '') {
    return true;
  }

  try {
    $crypto             = new bx_github_repositories_crypto();
    $private_key        = $crypto->decryptToken($private_key_encrypted);
    $token_data         = bx_github_repositories_create_installation_token($app_id, $installation_id, $private_key);
    $installation_token = (string)$token_data['token'];
  } catch (Exception $e) {
    return true;
  }

  $repo_query = xtc_db_query(
    "SELECT repo.repositories_id,
            repo.owner_name,
            repo.repo_name,
            repo.local_filename_stable,
            repo.current_tag_name,
            (
              SELECT log.tag_name
                FROM " . TABLE_BX_GITHUB_RELEASE_LOG . " log
               WHERE log.repositories_id = repo.repositories_id
                 AND log.import_status = 'success'
               ORDER BY log.downloaded_at DESC, log.release_log_id DESC
               LIMIT 1
            ) AS downloaded_tag_name
       FROM " . TABLE_BX_GITHUB_REPOSITORIES . " repo
      WHERE repo.status = 1
   ORDER BY repo.repositories_id ASC"
  );

  $download_dir = rtrim((string)DIR_FS_DOWNLOAD, '/\\') . DIRECTORY_SEPARATOR;

  while ($repo = xtc_db_fetch_array($repo_query)) {
    $repo_id = (int)$repo['repositories_id'];

    try {
      $tag_info = bx_github_repositories_fetch_latest_tag(
        (string)$repo['owner_name'],
        (string)$repo['repo_name'],
        $installation_token
      );

      $tag_name       = (string)$tag_info['tag_name'];
      $zipball_url    = (string)$tag_info['zipball_url'];
      $asset_name     = (string)$repo['local_filename_stable'];
      $target_path    = $download_dir . $asset_name;
      $downloaded_tag = isset($repo['downloaded_tag_name']) ? trim((string)$repo['downloaded_tag_name']) : '';

      xtc_db_query(
        "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
            SET last_check_at = now()
          WHERE repositories_id = " . $repo_id
      );

      if ($downloaded_tag !== '' && $downloaded_tag === $tag_name && is_file($target_path)) {
        xtc_db_query(
          "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
              SET current_tag_name = '" . xtc_db_input($tag_name) . "',
                  last_error_message = NULL,
                  updated_at = now()
            WHERE repositories_id = " . $repo_id
        );

        xtc_db_query(
          "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
              (repositories_id, tag_name, asset_name, asset_url, import_status)
           VALUES (" . $repo_id . ", '" . xtc_db_input($tag_name) . "', '" . xtc_db_input($asset_name) . "',
                   '" . xtc_db_input($zipball_url) . "', 'skipped')"
        );

        continue;
      }

      bx_github_repositories_download_asset($zipball_url, $installation_token, $target_path);

      $file_size = is_file($target_path) ? (int)filesize($target_path) : 0;
      $checksum = is_file($target_path) ? (string)hash_file('sha256', $target_path) : '';

      xtc_db_query(
        "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
            SET current_tag_name     = '" . xtc_db_input($tag_name) . "',
                current_release_name = '" . xtc_db_input($tag_name) . "',
                current_asset_name   = '" . xtc_db_input($asset_name) . "',
                current_asset_url    = '" . xtc_db_input($zipball_url) . "',
                current_published_at = NULL,
                last_success_at      = now(),
                last_error_message   = NULL,
                updated_at           = now()
          WHERE repositories_id = " . $repo_id
      );

      xtc_db_query(
        "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
            (repositories_id, tag_name, asset_name, asset_url, downloaded_at, file_size, checksum_sha256, import_status)
         VALUES (" . $repo_id . ", '" . xtc_db_input($tag_name) . "', '" . xtc_db_input($asset_name) . "',
                 '" . xtc_db_input($zipball_url) . "', now(),
                 " . $file_size . ", '" . xtc_db_input($checksum) . "', 'success')"
      );
    } catch (Exception $e) {
      $error_msg = substr($e->getMessage(), 0, 500);

      xtc_db_query(
        "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
            SET last_check_at = now(),
                last_error_message = '" . xtc_db_input($error_msg) . "',
                updated_at = now()
          WHERE repositories_id = " . $repo_id
      );

      xtc_db_query(
        "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
            (repositories_id, tag_name, asset_name, import_status, error_message)
         VALUES (" . $repo_id . ", '', NULL, 'error', '" . xtc_db_input($error_msg) . "')"
      );
    }
  }

  return true;
}
