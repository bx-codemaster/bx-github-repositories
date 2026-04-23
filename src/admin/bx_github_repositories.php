<?php

require('includes/application_top.php');

require_once (DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
bx_dependency_resolver::require('modified_github');

require_once(__DIR__ . '/includes/classes/bx_github_repositories_crypto.php');
require_once(__DIR__ . '/includes/classes/bx_github_repositories_jwt_provider.php');

/**
 * Validiere den PEM-Inhalt auf Private-Key-Block.
 */
function bx_github_repositories_is_valid_pem_private_key(string $pem): bool
{
  $pattern = '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----[\s\S]+-----END [A-Z0-9 ]*PRIVATE KEY-----/';
  return (bool)preg_match($pattern, trim($pem));
}

/**
 * Teste App-Authentifizierung direkt gegen GitHub.
 */
function bx_github_repositories_test_connection(int $app_id, int $installation_id, string $private_key_pem): array
{
  try {
    $normalized_key = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key_pem);
    $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $normalized_key);
  } catch (Exception $e) {
    return [
      'success' => false,
      'message' => sprintf(BX_GITHUB_REPOSITORIES_ERROR_JWT_CREATION_FAILED, $e->getMessage()),
    ];
  }

  $endpoint = 'https://api.github.com/app/installations/' . $installation_id . '/access_tokens';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $jwt,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'Content-Type: application/json',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 201 || !is_array($response) || empty($response['token'])) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    return [
      'success' => false,
      'message' => sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details),
    ];
  }

  return [
    'success' => true,
    'message' => BX_GITHUB_REPOSITORIES_SUCCESS_CONNECTION_TEST,
  ];
}

/**
 * Erzeuge Installation-Token per App-JWT.
 */
function bx_github_repositories_create_installation_token(int $app_id, int $installation_id, string $private_key_pem): array
{
  $normalized_key = bx_github_repositories_jwt_provider::normalizePrivateKey($private_key_pem);
  $jwt = bx_github_repositories_jwt_provider::buildJWT($app_id, $normalized_key);

  $endpoint = 'https://api.github.com/app/installations/' . $installation_id . '/access_tokens';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $jwt,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'Content-Type: application/json',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 201 || !is_array($response) || empty($response['token'])) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details));
  }

  return $response;
}

/**
 * Lade alle Repositories der Installation.
 */
function bx_github_repositories_fetch_installation_repositories(string $installation_token): array
{
  $endpoint = 'https://api.github.com/installation/repositories?per_page=100';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 200 || !is_array($response)) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_REPOSITORY_LOAD_FAILED, $http_code, $details));
  }

  return isset($response['repositories']) && is_array($response['repositories'])
    ? $response['repositories']
    : [];
}

/**
 * Erzeuge stabilen Dateinamen fuer ein Repository.
 */
function bx_github_repositories_build_stable_filename(string $owner, string $repo): string
{
  $base = strtolower(trim($owner . '-' . $repo));
  $base = preg_replace('/[^a-z0-9._-]+/', '-', $base);
  $base = trim((string)$base, '-._');
  if ($base === '') {
    $base = 'github-release';
  }
  return $base . '.zip';
}

/**
 * Lade neuesten Tag von GitHub (per Tags-API, neuester zuerst).
 * Gibt ['tag_name' => string, 'zipball_url' => string] zurück.
 */
function bx_github_repositories_fetch_latest_tag(string $owner, string $repo, string $installation_token): array
{
  $endpoint = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/tags?per_page=1';
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPGET        => true,
    CURLOPT_HTTPHEADER     => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);

  $response_raw = curl_exec($ch);
  $http_code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $response     = is_string($response_raw) ? json_decode($response_raw, true) : null;

  if ($http_code !== 200 || !is_array($response)) {
    $details = is_array($response) && isset($response['message']) ? (string)$response['message'] : BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE;
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED, $http_code, $details));
  }

  if (empty($response) || !isset($response[0]['name'])) {
    throw new Exception(BX_GITHUB_REPOSITORIES_ERROR_NO_RELEASE_FOUND);
  }

  $tag        = $response[0];
  $tag_name   = (string)$tag['name'];
  $zipball_url = isset($tag['zipball_url']) ? (string)$tag['zipball_url'] : '';

  if ($zipball_url === '') {
    $zipball_url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/zipball/' . rawurlencode($tag_name);
  }

  return [
    'tag_name'    => $tag_name,
    'zipball_url' => $zipball_url,
  ];
}

/**
 * Lade Asset-Datei atomar in das Zielverzeichnis herunter.
 */
function bx_github_repositories_download_asset(string $asset_url, string $installation_token, string $target_path): void
{
  $tmp_path = $target_path . '.tmp.' . bin2hex(random_bytes(8));
  $fp = fopen($tmp_path, 'wb');
  if ($fp === false) {
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_TEMP_FILE_CREATE, $tmp_path));
  }

  $ch = curl_init($asset_url);
  curl_setopt_array($ch, [
    CURLOPT_FILE            => $fp,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_MAXREDIRS       => 5,
    CURLOPT_TIMEOUT         => 120,
    CURLOPT_HTTPHEADER      => [
      'Authorization: Bearer ' . $installation_token,
      'Accept: application/vnd.github+json',
      'X-GitHub-Api-Version: 2022-11-28',
      'User-Agent: bx-github-repositories-admin',
    ],
    CURLOPT_SSL_VERIFYPEER  => true,
    CURLOPT_SSL_VERIFYHOST  => 2,
  ]);

  curl_exec($ch);
  $http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  fclose($fp);

  if ($curl_error !== '' || ($http_code !== 200 && $http_code !== 0)) {
    @unlink($tmp_path);
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED, $http_code, $curl_error !== '' ? $curl_error : '-'));
  }

  if (!file_exists($tmp_path) || filesize($tmp_path) === 0) {
    @unlink($tmp_path);
    throw new Exception(BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_EMPTY);
  }

  if (!rename($tmp_path, $target_path)) {
    @unlink($tmp_path);
    throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_FILE_RENAME, $target_path));
  }
}

$messages = [];
$errors   = [];

$current_app_id_value = defined('MODULE_BX_GITHUB_REPOSITORIES_APP_ID')
  ? (string)constant('MODULE_BX_GITHUB_REPOSITORIES_APP_ID')
  : '';
$current_installation_id_value = defined('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID')
  ? (string)constant('MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID')
  : '';
$current_private_key_encrypted_value = defined('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED')
  ? (string)constant('MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED')
  : '';

$app_id_value                = $current_app_id_value;
$installation_id_value       = $current_installation_id_value;
$private_key_encrypted_value = $current_private_key_encrypted_value;

$repository_rows = [];
$repository_query = xtc_db_query(
  "SELECT repositories_id, status, owner_name, repo_name, local_filename_stable,
          current_tag_name, last_check_at, last_error_message
     FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
 ORDER BY owner_name ASC, repo_name ASC"
);
while ($repository_row = xtc_db_fetch_array($repository_query)) {
  $repository_rows[] = $repository_row;
}

$is_post        = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$current_action = $is_post ? trim((string)($_POST['action'] ?? '')) : '';

if ($is_post && $current_action === 'save_settings') {
  $posted_app_id          = trim((string)($_POST['github_app_id'] ?? ''));
  $posted_installation_id = trim((string)($_POST['github_installation_id'] ?? ''));
  $save_has_errors        = false;

  $app_id_value          = $posted_app_id;
  $installation_id_value = $posted_installation_id;

  if ($posted_app_id === '' || !ctype_digit($posted_app_id) || (int)$posted_app_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_APP_ID, 'error');
    $save_has_errors = true;
  }

  if ($posted_installation_id === '' || !ctype_digit($posted_installation_id) || (int)$posted_installation_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_INSTALLATION_ID, 'error');
    $save_has_errors = true;
  }

  $uploaded_pem = '';
  if (isset($_FILES['github_private_key_file']) && is_array($_FILES['github_private_key_file'])) {
    $upload_error = (int)($_FILES['github_private_key_file']['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($upload_error !== UPLOAD_ERR_NO_FILE) {
      if ($upload_error !== UPLOAD_ERR_OK) {
        $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_UPLOAD_FAILED, $upload_error), 'error');
        $save_has_errors = true;
      } else {
        $tmp_name = (string)($_FILES['github_private_key_file']['tmp_name'] ?? '');
        $size = (int)($_FILES['github_private_key_file']['size'] ?? 0);

        if ($size <= 0 || $size > 32768) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_FILE_SIZE, 'error');
          $save_has_errors = true;
        } elseif ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_UPLOAD, 'error');
          $save_has_errors = true;
        } else {
          $upload_content = file_get_contents($tmp_name);
          if ($upload_content === false) {
            $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_FILE_READ_FAILED, 'error');
            $save_has_errors = true;
          } else {
            $uploaded_pem = trim((string)$upload_content);
          }
        }
      }
    }
  }

  $has_existing_key = $private_key_encrypted_value !== '';

  if ($uploaded_pem !== '' && !bx_github_repositories_is_valid_pem_private_key($uploaded_pem)) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_PRIVATE_KEY, 'error');
    $save_has_errors = true;
  }

  if ($uploaded_pem === '' && !$has_existing_key) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_PRIVATE_KEY_UPLOAD, 'error');
    $save_has_errors = true;
  }

  $new_private_key_encrypted = '';
  $private_key_changed = false;

  if ($save_has_errors === false) {
    try {
      $crypto = new bx_github_repositories_crypto();
      if ($uploaded_pem !== '') {
        $existing_private_key = $private_key_encrypted_value !== ''
          ? $crypto->decryptToken($private_key_encrypted_value)
          : '';

        if ($uploaded_pem !== $existing_private_key) {
          $new_private_key_encrypted = $crypto->encryptToken($uploaded_pem);
          $private_key_changed = true;
        }
      }
    } catch (Exception $e) {
      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_ENCRYPTION_FAILED, $e->getMessage()), 'error');
      $save_has_errors = true;
    }
  }

  if ($save_has_errors === false) {
    $updated_fields = [];

    if ($posted_app_id !== $current_app_id_value) {
      xtc_db_query(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . xtc_db_input($posted_app_id) . "',
                last_modified = now()
          WHERE configuration_key = 'MODULE_BX_GITHUB_REPOSITORIES_APP_ID'"
      );
      $updated_fields[] = BX_GITHUB_REPOSITORIES_LABEL_APP_ID;
      $current_app_id_value = $posted_app_id;
    }

    if ($posted_installation_id !== $current_installation_id_value) {
      xtc_db_query(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . xtc_db_input($posted_installation_id) . "',
                last_modified = now()
          WHERE configuration_key = 'MODULE_BX_GITHUB_REPOSITORIES_INSTALLATION_ID'"
      );
      $updated_fields[] = BX_GITHUB_REPOSITORIES_LABEL_INSTALLATION_ID;
      $current_installation_id_value = $posted_installation_id;
    }

    if ($private_key_changed === true) {
      xtc_db_query(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . xtc_db_input($new_private_key_encrypted) . "',
                last_modified = now()
          WHERE configuration_key = 'MODULE_BX_GITHUB_REPOSITORIES_PRIVATE_KEY_ENCRYPTED'"
      );
      $private_key_encrypted_value = $new_private_key_encrypted;
      $current_private_key_encrypted_value = $new_private_key_encrypted;
      $updated_fields[] = BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY;
    }

    if (count($updated_fields) > 0) {
      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_SETTINGS_UPDATED, implode(', ', $updated_fields)), 'success');
    } else {
      $messageStack->add_session(BX_GITHUB_REPOSITORIES_INFO_NO_SETTINGS_CHANGED, 'info');
    }
  }
  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($is_post && $current_action === 'load_repositories') {
  $load_has_errors = false;

  $load_app_id = (int)$current_app_id_value;
  $load_installation_id = (int)$current_installation_id_value;

  if ($load_app_id <= 0 || $load_installation_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS, 'error');
    $load_has_errors = true;
  }

  $private_key_for_load = '';
  if ($current_private_key_encrypted_value !== '') {
    try {
      $crypto = new bx_github_repositories_crypto();
      $private_key_for_load = $crypto->decryptToken($current_private_key_encrypted_value);
    } catch (Exception $e) {
      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED, $e->getMessage()), 'error');
      $load_has_errors = true;
    }
  } else {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_STORED_PRIVATE_KEY, 'error');
    $load_has_errors = true;
  }

  if ($load_has_errors === false) {
    try {
      $token_data = bx_github_repositories_create_installation_token($load_app_id, $load_installation_id, $private_key_for_load);
      $repositories = bx_github_repositories_fetch_installation_repositories((string)$token_data['token']);

      $inserted_count = 0;
      $updated_count = 0;

      foreach ($repositories as $repository) {
        $owner_name = isset($repository['owner']['login']) ? trim((string)$repository['owner']['login']) : '';
        $repo_name = isset($repository['name']) ? trim((string)$repository['name']) : '';

        if ($owner_name === '' || $repo_name === '') {
          continue;
        }

        $check_query = xtc_db_query(
          "SELECT repositories_id
             FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
            WHERE owner_name = '" . xtc_db_input($owner_name) . "'
              AND repo_name = '" . xtc_db_input($repo_name) . "'
            LIMIT 1"
        );

        if (xtc_db_num_rows($check_query) > 0) {
          xtc_db_query(
            "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
                SET updated_at = now()
              WHERE owner_name = '" . xtc_db_input($owner_name) . "'
                AND repo_name = '" . xtc_db_input($repo_name) . "'"
          );
          $updated_count++;
          continue;
        }

        $local_filename = bx_github_repositories_build_stable_filename($owner_name, $repo_name);

        xtc_db_query(
          "INSERT INTO " . TABLE_BX_GITHUB_REPOSITORIES . "
              (status, owner_name, repo_name, local_filename_stable, created_at, updated_at)
           VALUES
              (0, '" . xtc_db_input($owner_name) . "', '" . xtc_db_input($repo_name) . "', '" . xtc_db_input($local_filename) . "', now(), now())"
        );
        $inserted_count++;
      }

      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORIES_LOADED, $inserted_count, $updated_count), 'success');
    } catch (Exception $e) {
      $messageStack->add_session($e->getMessage(), 'error');
    }
  }

  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($is_post && $current_action === 'save_repository_selection') {
  $previous_active_repositories = [];
  $previous_active_query = xtc_db_query(
    "SELECT repositories_id, local_filename_stable
       FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
      WHERE status = 1"
  );
  while ($previous_active_row = xtc_db_fetch_array($previous_active_query)) {
    $previous_active_repositories[(int)$previous_active_row['repositories_id']] = (string)$previous_active_row['local_filename_stable'];
  }

  $selected_repository_ids = $_POST['selected_repositories'] ?? [];
  $selected_repository_ids = is_array($selected_repository_ids) ? $selected_repository_ids : [];
  $selected_repository_ids = array_values(array_filter(array_map('intval', $selected_repository_ids), static function ($id) {
    return $id > 0;
  }));

  xtc_db_query(
    "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
        SET status = 0,
            updated_at = now()"
  );

  if (count($selected_repository_ids) > 0) {
    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
          SET status = 1,
              updated_at = now()
        WHERE repositories_id IN (" . implode(',', $selected_repository_ids) . ")"
    );
  }

  $selected_lookup = array_flip($selected_repository_ids);
  $download_dir = rtrim((string)DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR;
  $removed_file_count = 0;

  foreach ($previous_active_repositories as $repositories_id => $local_filename_stable) {
    if (isset($selected_lookup[$repositories_id])) {
      continue;
    }

    if ($local_filename_stable === '') {
      continue;
    }

    $download_file_path = $download_dir . $local_filename_stable;
    if (!is_file($download_file_path)) {
      continue;
    }

    if (!@unlink($download_file_path)) {
      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_WARNING_FILE_DELETE_FAILED, $local_filename_stable), 'warning');
      continue;
    }

    $removed_file_count++;
  }

  $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORY_SELECTION_SAVED, count($selected_repository_ids)), 'success');
  if ($removed_file_count > 0) {
    $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_INFO_DEACTIVATED_FILES_REMOVED, $removed_file_count), 'info');
  }
  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($is_post && $current_action === 'sync_releases') {
  $sync_app_id          = (int)$current_app_id_value;
  $sync_installation_id = (int)$current_installation_id_value;

  if ($sync_app_id <= 0 || $sync_installation_id <= 0 || $current_private_key_encrypted_value === '') {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  try {
    $crypto              = new bx_github_repositories_crypto();
    $private_key_sync    = $crypto->decryptToken($current_private_key_encrypted_value);
    $token_data          = bx_github_repositories_create_installation_token($sync_app_id, $sync_installation_id, $private_key_sync);
    $sync_token          = (string)$token_data['token'];
  } catch (Exception $e) {
    $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED, $e->getMessage()), 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $active_query = xtc_db_query(
    "SELECT repositories_id, owner_name, repo_name, local_filename_stable, current_tag_name
       FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
      WHERE status = 1
   ORDER BY owner_name ASC, repo_name ASC"
  );
  $active_repos = [];
  while ($row = xtc_db_fetch_array($active_query)) {
    $active_repos[] = $row;
  }

  if (count($active_repos) === 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_INFO_NO_ACTIVE_REPOSITORIES, 'warning');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $download_dir = rtrim((string)DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR;
  $sync_success = 0;
  $sync_errors  = 0;
  $sync_skipped = 0;

  foreach ($active_repos as $repo) {
    $repo_id       = (int)$repo['repositories_id'];
    $owner         = (string)$repo['owner_name'];
    $repo_name_str = (string)$repo['repo_name'];
    $local_file    = (string)$repo['local_filename_stable'];
    $current_tag   = isset($repo['current_tag_name']) ? trim((string)$repo['current_tag_name']) : '';

    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
          SET last_check_at = now()
        WHERE repositories_id = " . $repo_id
    );

    try {
      $tag_info    = bx_github_repositories_fetch_latest_tag($owner, $repo_name_str, $sync_token);
      $tag_name    = $tag_info['tag_name'];
      $zipball_url = $tag_info['zipball_url'];
      $asset_name  = $local_file;
      $target_path = $download_dir . $local_file;

      if ($current_tag !== '' && $current_tag === $tag_name && is_file($target_path)) {
        xtc_db_query(
          "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
              SET last_error_message = NULL,
                  updated_at = now()
            WHERE repositories_id = " . $repo_id
        );
        xtc_db_query(
          "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
              (repositories_id, tag_name, asset_name, asset_url, import_status)
           VALUES (" . $repo_id . ", '" . xtc_db_input($tag_name) . "', '" . xtc_db_input($asset_name) . "',
                    '" . xtc_db_input($zipball_url) . "', 'skipped')"
        );
        $sync_skipped++;
        continue;
      }

      bx_github_repositories_download_asset($zipball_url, $sync_token, $target_path);

      $file_size = file_exists($target_path) ? (int)filesize($target_path) : 0;
      $checksum  = file_exists($target_path) ? (string)hash_file('sha256', $target_path) : '';

      xtc_db_query(
        "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
            SET current_tag_name     = '" . xtc_db_input($tag_name) . "',
                current_release_name = '" . xtc_db_input($tag_name) . "',
                current_asset_name   = '" . xtc_db_input($asset_name) . "',
                current_asset_url    = '" . xtc_db_input($zipball_url) . "',
                current_published_at = NULL,
                last_success_at      = now(),
                last_error_message   = NULL
          WHERE repositories_id = " . $repo_id
      );

      xtc_db_query(
        "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
            (repositories_id, tag_name, asset_name, asset_url, downloaded_at, file_size, checksum_sha256, import_status)
         VALUES (" . $repo_id . ", '" . xtc_db_input($tag_name) . "', '" . xtc_db_input($asset_name) . "',
                  '" . xtc_db_input($zipball_url) . "', now(),
                  " . $file_size . ", '" . xtc_db_input($checksum) . "', 'success')"
      );

      $sync_success++;
    } catch (Exception $e) {
      $error_msg = $e->getMessage();
      xtc_db_query(
        "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
            SET last_error_message = '" . xtc_db_input(mb_substr($error_msg, 0, 500)) . "'
          WHERE repositories_id = " . $repo_id
      );
      xtc_db_query(
        "INSERT INTO " . TABLE_BX_GITHUB_RELEASE_LOG . "
            (repositories_id, tag_name, asset_name, import_status, error_message)
         VALUES (" . $repo_id . ", '', NULL, 'error', '" . xtc_db_input(mb_substr($error_msg, 0, 500)) . "')"
      );
      $sync_errors++;
    }
  }

  $msg_type = $sync_errors === 0 ? 'success' : 'warning';
  $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_SYNC_COMPLETED, $sync_success, $sync_errors), $msg_type);
  if ($sync_skipped > 0) {
    $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_INFO_SYNC_SKIPPED, $sync_skipped), 'info');
  }
  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($is_post && $current_action === 'test_connection') {
  $test_app_id          = (int)$current_app_id_value;
  $test_installation_id = (int)$current_installation_id_value;

  if ($test_app_id <= 0 || $test_installation_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS, 'error');
  }

  $uploaded_pem = '';
  if (isset($_FILES['github_private_key_file']) && is_array($_FILES['github_private_key_file'])) {
    $upload_error = (int)($_FILES['github_private_key_file']['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($upload_error !== UPLOAD_ERR_NO_FILE) {
      if ($upload_error !== UPLOAD_ERR_OK) {
        $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_UPLOAD_FAILED, $upload_error), 'error');
      } else {
        $tmp_name = (string)($_FILES['github_private_key_file']['tmp_name'] ?? '');
        $size = (int)($_FILES['github_private_key_file']['size'] ?? 0);

        if ($size <= 0 || $size > 32768) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_FILE_SIZE, 'error');
        } elseif ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_UPLOAD, 'error');
        } else {
          $upload_content = file_get_contents($tmp_name);
          if ($upload_content === false) {
            $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_FILE_READ_FAILED, 'error');
          } else {
            $uploaded_pem = trim((string)$upload_content);
          }
        }
      }
    }
  }

  if ($uploaded_pem !== '' && !bx_github_repositories_is_valid_pem_private_key($uploaded_pem)) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_PRIVATE_KEY, 'error');
  }

  if (count($errors) === 0) {
    $private_key_for_test = $uploaded_pem;

    if ($private_key_for_test === '') {
      if ($private_key_encrypted_value !== '') {
        try {
          $crypto = new bx_github_repositories_crypto();
          $private_key_for_test = $crypto->decryptToken($private_key_encrypted_value);
        } catch (Exception $e) {
          $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED, $e->getMessage()), 'error');
        }
      } else {
        $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_STORED_PRIVATE_KEY, 'error');
      }
    }

    if (count($errors) === 0 && $private_key_for_test !== '') {
      $test_result = bx_github_repositories_test_connection($test_app_id, $test_installation_id, $private_key_for_test);
      if ($test_result['success'] === true) {
        $messageStack->add_session($test_result['message'], 'success');
      } else {
        $messageStack->add_session($test_result['message'], 'error');
      }
    }
  }
  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

require_once(DIR_WS_INCLUDES . 'head.php');
?>
</head>
<body>
	<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

  <table class="tableBody">
    <tr>
      <?php
      if (USE_ADMIN_TOP_MENU == 'false') {
        echo '<td class="columnLeft2">'.PHP_EOL;
        require_once(DIR_WS_INCLUDES.'column_left.php');
        echo '</td> <!-- .columnLeft2 -->'.PHP_EOL;
      }
      ?>
      <td class="boxCenter">
        <div class="pageHeadingImage" style="min-width: 45px;">
          <?php echo xtc_image(DIR_WS_ICONS . 'heading/bx_github_repositories.png', BX_GITHUB_REPOSITORIES_HEADING_TITLE, '', '', 'style="max-height: 32px;"'); ?>
        </div>
        <div class="pageHeading flt-l">
          <div class="pageHeading"><?php echo BX_GITHUB_REPOSITORIES_HEADING_TITLE; ?></div>
          <div class="main pdg2 flt-l"><?php echo BX_GITHUB_REPOSITORIES_HEADING_SUB_TITLE; ?></div>
        </div>
        <div class="clear"></div>

        <table class="tableCenter">
          <tr>
            <td class="boxCenterLeft">
              <div id="headboard">
                <div class="main">
                  <strong><?php echo BX_GITHUB_REPOSITORIES_HEADING_TITLE; ?></strong> <?php echo BX_GITHUB_REPOSITORIES_TEXT_INTRO; ?>
                </div>
              </div>

            <div class="tabs">
              <ul class="tab-nav" role="tablist" aria-label="BX GitHub Repositories Tabs">
                <li><a id="tab-link-setup" href="#tab-setup" role="tab" aria-controls="tab-setup" aria-selected="false" tabindex="-1"><?php echo BX_GITHUB_REPOSITORIES_TAB_SETUP; ?></a></li>
                <li><a id="tab-link-repos" href="#tab-repos" role="tab" aria-controls="tab-repos" aria-selected="false" tabindex="-1"><?php echo BX_GITHUB_REPOSITORIES_TAB_REPOSITORIES; ?></a></li>
              </ul>

              <div class="tab-content">
                <div id="tab-setup" role="tabpanel" aria-labelledby="tab-link-setup" tabindex="0" hidden="hidden">
                  <div class="main" style="margin: 14px 0 8px;"><strong><?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_HEADING; ?></strong></div>
                  <div class="main" style="margin-bottom: 14px;">
                    <?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_DESCRIPTION; ?>
                    <?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_GOAL; ?>
                  </div>
                  <?php echo xtc_draw_form('github_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post', 'enctype="multipart/form-data"'); ?>
                  <table class="tableInput" border="0" cellpadding="6" cellspacing="0" width="100%">
                    <tr>
                      <td class="main" style="width: 220px;"><label for="github_app_id"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_APP_ID; ?></strong></label></td>
                      <td class="main"><input type="text" id="github_app_id" name="github_app_id" value="<?php echo htmlspecialchars($app_id_value, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; max-width: 420px;" /></td>
                    </tr>
                    <tr>
                      <td class="main"><label for="github_installation_id"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_INSTALLATION_ID; ?></strong></label></td>
                      <td class="main"><input type="text" id="github_installation_id" name="github_installation_id" value="<?php echo htmlspecialchars($installation_id_value, ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; max-width: 420px;" /></td>
                    </tr>
                    <tr>
                      <td class="main" style="vertical-align: top;"><label for="github_private_key_file"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_UPLOAD; ?></strong></label></td>
                      <td class="main">
                        <?php echo xtc_draw_file_field('github_private_key_file', false, ' id="github_private_key_file" accept=".pem"'); ?>
                        <div style="margin-top: 6px; color: #666;">
                          <?php echo BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_UPLOAD_HELP; ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td class="main" style="vertical-align: top;"><label for="github_private_key"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY; ?></strong></label></td>
                      <td class="main">
                        <textarea id="github_private_key" rows="8" readonly="readonly"><?php echo htmlspecialchars($private_key_encrypted_value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div style="margin-top: 6px; color: #666;">
                          <?php echo BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_HINT; ?>
                        </div>
                      </td>
                    </tr>
                  </table>
                  <div class="main" style="margin-top: 8px; color: #555;">
                    <?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_STATUS; ?>: <?php echo $private_key_encrypted_value !== '' ? BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_STORED : BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_MISSING; ?>
                  </div>
                  <div style="margin: 12px 0 16px;">
                    <button type="submit" class="button" name="action" value="test_connection"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_TEST_CONNECTION; ?></button>
                    <button type="submit" class="button" name="action" value="save_settings" style="margin-left: 8px;"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_SAVE_SETTINGS; ?></button>
                  </div>
                  </form>
                </div> <!-- #tab-setup -->

                <div id="tab-repos" role="tabpanel" aria-labelledby="tab-link-repos" tabindex="0" hidden="hidden">
                  <div class="main" style="margin: 16px 0 8px;">
                    <strong><?php echo BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_HEADING; ?></strong>
                  </div>
                  <div class="main" style="margin-bottom: 8px;">
                    <?php echo BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_INTRO; ?>
                  </div>
                  <?php echo xtc_draw_form('github_repositories_selection_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post'); ?>
                  <table class="tableInput" border="0" cellpadding="6" cellspacing="0" width="100%">
                    <tr>
                      <td class="main"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_REPOSITORY; ?></strong></td>
                      <td class="main" style="width: 30%;"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_LOCAL_FILENAME; ?></strong></td>
                      <td class="main" style="width: 100px;"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_VERSION; ?></strong></td>
                      <td class="main" style="width: 130px;"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_LAST_CHECK; ?></strong></td>
                    </tr>
                    <?php if (count($repository_rows) === 0) { ?>
                      <tr>
                        <td class="main" colspan="2" style="color: #777;">
                          <?php echo BX_GITHUB_REPOSITORIES_TEXT_NO_REPOSITORIES_LOADED; ?>
                        </td>
                      </tr>
                    <?php } else { ?>
                      <?php foreach ($repository_rows as $repository_row) { ?>
                        <tr<?php echo $repository_row['last_error_message'] !== null ? ' style="background:#fff3f3;"' : ''; ?>>
                          <td class="main">
                            <label>
                              <input type="checkbox" name="selected_repositories[]" value="<?php echo (int)$repository_row['repositories_id']; ?>"<?php echo (int)$repository_row['status'] === 1 ? ' checked="checked"' : ''; ?> />
                              <?php echo htmlspecialchars((string)$repository_row['owner_name'] . '/' . (string)$repository_row['repo_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                            <?php if ($repository_row['last_error_message'] !== null) { ?>
                              <div style="color:#c00; font-size:11px; margin-top:2px;"><?php echo htmlspecialchars((string)$repository_row['last_error_message'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php } ?>
                          </td>
                          <td class="main" style="color: #666;">
                            <?php echo htmlspecialchars((string)$repository_row['local_filename_stable'], ENT_QUOTES, 'UTF-8'); ?>
                          </td>
                          <td class="main" style="color: #444; font-size: 12px;">
                            <?php echo $repository_row['current_tag_name'] !== null ? htmlspecialchars((string)$repository_row['current_tag_name'], ENT_QUOTES, 'UTF-8') : '&ndash;'; ?>
                          </td>
                          <td class="main" style="color: #888; font-size: 12px;">
                            <?php echo $repository_row['last_check_at'] !== null ? htmlspecialchars((string)$repository_row['last_check_at'], ENT_QUOTES, 'UTF-8') : '&ndash;'; ?>
                          </td>
                        </tr>
                      <?php } ?>
                    <?php } ?>
                  </table>

                  <?php if (count($repository_rows) > 0) { ?>
                    <div style="margin-top: 10px;">
                      <button type="submit" class="button" name="action" value="save_repository_selection"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_SAVE_REPOSITORY_SELECTION; ?></button>
                    </div>
                  <?php } ?>
                  </form>

                  <div style="margin-top: 12px; color: #555;" class="main">
                    <?php echo BX_GITHUB_REPOSITORIES_TEXT_IMPORT_TARGET; ?>
                  </div>
                </div> <!-- #tab-repos -->

              </div> <!-- .tab-content -->
            </div>
          </td>

          <td class="boxRight">
            <div class="tab-content">
              <div id="tab-setup-right" role="tabpanel" aria-labelledby="tab-link-setup" tabindex="0" hidden="hidden">
<?php
  $heading  = array();
  $contents = array();

  $heading[]  = array('text' => '<strong>'.BX_GITHUB_REPOSITORIES_TEXT_SETUP_HEADING.'</strong>');
  $contents[] = array('text' => 'Text');

  if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
    $box = new box;
    echo $box->infoBox($heading, $contents);
  }
?>
              </div> <!-- #tab-setup-right -->

              <div id="tab-repos-right" role="tabpanel" aria-labelledby="tab-link-repos" tabindex="0" hidden="hidden">
<?php
  $heading  = array();
  $contents = array();

  $heading[]  = array('text' => '<strong>'.BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_HEADING.'</strong>');
  $contents[] = array('text' =>  xtc_draw_form('github_repositories_load_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post')
                      .'<button type="submit" class="button" name="action" value="load_repositories">'
                      .BX_GITHUB_REPOSITORIES_BUTTON_LOAD_REPOSITORIES.'</button></form>');

    $contents[] = array('text' => xtc_draw_form('github_repositories_sync_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post')
                      .'<button type="submit" class="button" name="action" value="sync_releases">'
                      .BX_GITHUB_REPOSITORIES_BUTTON_SYNC_RELEASES.'</button></form>');
  
  if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
    $box = new box;
    echo $box->infoBox($heading, $contents);
  }
?>
              </div> <!-- #tab-repos-right -->
            </div> <!-- .tab-content -->

          </td> <!-- .boxRight -->
        </tr>
      </table> <!-- .tableCenter -->
    </td> <!-- .boxCenter -->
  </tr>
</table>

<?php require(DIR_WS_INCLUDES.'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>