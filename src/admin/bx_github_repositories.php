<?php
/**
 * -----------------------------------------------------------------------------
 * BX GitHub Repositories - Admin Controller
 * -----------------------------------------------------------------------------
 * Datei:
 * - src/admin/bx_github_repositories.php
 *
 * Zweck:
 * - Stellt die Admin-Oberfläche für das Modul bereit.
 * - Verarbeitet Setup, Verbindungstest, Repository-Ladevorgang,
 *   Aktivierung/Deaktivierung und manuelle Release-Synchronisation.
 *
 * Verantwortung:
 * - Entgegennahme und Validierung von Admin-Formulardaten.
 * - Persistenz von Modul-Konfigurationen (App ID, Installation ID, PEM-Secret).
 * - Triggern der GitHub-Kommunikation über ausgelagerte Hilfsfunktionen.
 * - Schreiben von Status- und Ergebnisdaten in Modultabellen.
 *
 * Nicht-Ziele:
 * - Keine dauerhafte Speicherung von Klartext-PEM.
 *
 * Sicherheitsprinzipien:
 * - PEM-Inhalte werden serverseitig geprüft.
 * - Private Keys werden nur verschlüsselt in TABLE_CONFIGURATION gehalten.
 * - Uploads werden auf Gültigkeit und Größe geprüft.
 * - Fehlerausgaben für Admins enthalten keine sensiblen Schlüsselinhalte.
 *
 * Grober Ablauf:
 * 1) Initialisierung von Abhängigkeiten und aktuellen Konfigurationswerten.
 * 2) Laden der Repository-Übersicht aus bx_github_repositories.
 * 3) Dispatch nach POST-Action:
 *    - save_settings
 *    - test_connection
 *    - load_repositories
 *    - save_repository_selection
 *    - sync_releases
 * 4) Rücksprung auf die Modulseite mit messageStack-Feedback.
 * 5) Rendern der Tabs (Setup, Repository-Auswahl, Handbuch).
 *
 * Hinweis zur Wartung:
 * - Fachliche Kernlogik ist teilweise in Helper-Funktionen ausgelagert.
 * - Bei größeren Erweiterungen ist eine weitere Trennung in Service-/
 *   Worker-Schichten empfohlen.
 * -----------------------------------------------------------------------------
 */

require('includes/application_top.php');

require_once (DIR_FS_CATALOG . 'includes/classes/bx_dependency_resolver.php');
bx_dependency_resolver::require('modified_github');

require_once(__DIR__ . '/includes/classes/bx_github_repositories_crypto.php');
require_once(__DIR__ . '/includes/classes/bx_github_repositories_jwt_provider.php');
require_once(__DIR__ . '/includes/classes/bx_github_repositories_product_service.php');

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
  "SELECT repo.repositories_id, repo.status, repo.owner_name, repo.repo_name, repo.local_filename_stable,
          repo.current_tag_name, repo.last_check_at, repo.last_error_message,
          repo.product_id, repo.products_attributes_id,
          (
            SELECT log.tag_name
              FROM " . TABLE_BX_GITHUB_RELEASE_LOG . " log
             WHERE log.repositories_id = repo.repositories_id
               AND log.import_status = 'success'
             ORDER BY log.downloaded_at DESC, log.release_log_id DESC
             LIMIT 1
          ) AS downloaded_tag_name
     FROM " . TABLE_BX_GITHUB_REPOSITORIES . " repo
 ORDER BY repo.owner_name ASC, repo.repo_name ASC"
);
while ($repository_row = xtc_db_fetch_array($repository_query)) {
  $repository_rows[] = $repository_row;
}

$current_action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

if ($current_action === 'save_settings') {
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

if ($current_action === 'load_repositories') {
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
      $load_token = (string)$token_data['token'];
      $repositories = bx_github_repositories_fetch_installation_repositories($load_token);

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
        } else {
          $local_filename = bx_github_repositories_build_stable_filename($owner_name, $repo_name);

          xtc_db_query(
            "INSERT INTO " . TABLE_BX_GITHUB_REPOSITORIES . "
                (status, owner_name, repo_name, local_filename_stable, created_at, updated_at)
             VALUES
                (0, '" . xtc_db_input($owner_name) . "', '" . xtc_db_input($repo_name) . "', '" . xtc_db_input($local_filename) . "', now(), now())"
          );
          $inserted_count++;
        }

        try {
          $latest_tag_info = bx_github_repositories_fetch_latest_tag($owner_name, $repo_name, $load_token);
          $latest_tag_name = (string)$latest_tag_info['tag_name'];

          xtc_db_query(
            "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
                SET current_tag_name = '" . xtc_db_input($latest_tag_name) . "',
                    last_check_at = now(),
                    last_error_message = NULL,
                    updated_at = now()
              WHERE owner_name = '" . xtc_db_input($owner_name) . "'
                AND repo_name = '" . xtc_db_input($repo_name) . "'"
          );
        } catch (Exception $e) {
          xtc_db_query(
            "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
                SET last_check_at = now(),
                    last_error_message = '" . xtc_db_input(mb_substr($e->getMessage(), 0, 500)) . "',
                    updated_at = now()
              WHERE owner_name = '" . xtc_db_input($owner_name) . "'
                AND repo_name = '" . xtc_db_input($repo_name) . "'"
          );
        }
      }

      $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORIES_LOADED, $inserted_count, $updated_count), 'success');
    } catch (Exception $e) {
      $messageStack->add_session($e->getMessage(), 'error');
    }
  }

  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($current_action === 'save_repository_selection') {
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

if ($current_action === 'create_product') {
  $product_repository_id = (int)($_POST['product_repository_id'] ?? 0);

  if ($product_repository_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_REPOSITORY_SELECTION, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $repo_query = xtc_db_query(
    "SELECT repositories_id, 
                  owner_name, 
                  repo_name, 
                  local_filename_stable, 
                  current_tag_name, 
                  product_id, 
                  products_attributes_id
       FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
      WHERE repositories_id = " . $product_repository_id . "
      LIMIT 1"
  );

  if (xtc_db_num_rows($repo_query) === 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_REPOSITORY_SELECTION, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $repo_data = xtc_db_fetch_array($repo_query);
  $owner                 = (string)$repo_data['owner_name'];
  $repo_name             = (string)$repo_data['repo_name'];
  $local_file            = (string)$repo_data['local_filename_stable'];
  $current_product_id    = (int)($repo_data['product_id'] ?? 0);
  $current_attributes_id = (int)($repo_data['products_attributes_id'] ?? 0);

  $template_product_id = defined('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID')
    ? (int)constant('MODULE_BX_GITHUB_REPOSITORIES_TEMPLATE_PRODUCT_ID')
    : 0;

  if ($template_product_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_NO_TEMPLATE_PRODUCT, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  if ((int)$current_app_id_value <= 0 || (int)$current_installation_id_value <= 0 || $current_private_key_encrypted_value === '') {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  try {
    $crypto      = new bx_github_repositories_crypto();
    $private_key = $crypto->decryptToken($current_private_key_encrypted_value);
    $token_data  = bx_github_repositories_create_installation_token((int)$current_app_id_value, (int)$current_installation_id_value, $private_key);
    $sync_token  = (string)$token_data['token'];

    $tag_name = trim((string)($repo_data['current_tag_name'] ?? ''));
    if ($tag_name === '') {
      $tag_info = bx_github_repositories_fetch_latest_tag($owner, $repo_name, $sync_token);
      $tag_name = (string)$tag_info['tag_name'];
    }

    $moduleinfo = bx_github_repositories_fetch_moduleinfo_json($owner, $repo_name, $sync_token);

/*
DEBUG moduleinfo.json [bx-codemaster/bx_advent_calendar]:
 {"name":"BX Advent Calendar",
  "archiveName":"bx-codemaster/bx_advent_calendar",
  "category":"productivity",
  "developer":"Axel Benkert",
  "developerWebsite":"https://www.bx-coding.de",
  "website":"https://www.der-eisenhans.de",
  "srcDir":"src",
  "version":"auto",
  "shortDescription":
  "Hoch konfigurierbarer Adventskalender für Ihren Modified Shop",
  "description":"BX Advent Calendar ist ein hoch konfigurierbarer Adventskalender für Ihren Modified Shop. Er ermöglicht es Ihnen, Ihren Kunden täglich neue Angebote, Rabatte oder Überraschungen zu präsentieren und so die Vorweihnachtszeit zu einem besonderen Erlebnis zu machen. Mit einer benutzerfreundlichen Oberfläche können Sie einfach Inhalte hinzufügen, bearbeiten und planen, um Ihren Kunden jeden Tag eine Freude zu bereiten.",
  "price":"99.00",
  "date":"2026-03-08 00:00:00",
  "php":{"version":"^7.4 || ^8.0"},
  "modifiedCompatibility":["3.0.1","3.0.2","3.1.0","3.1.1","3.1.2","3.1.3","3.1.4","3.1.5","3.1.6","3.2.0","3.2.1","3.3.0"]}
*/

if ((string)($_REQUEST['debug_moduleinfo'] ?? '') === '1') {
      $moduleinfo_debug_payload = $moduleinfo !== null
        ? (string)json_encode($moduleinfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : 'NULL';
      if ($moduleinfo_debug_payload === '') {
        $moduleinfo_debug_payload = 'JSON_ENCODE_FAILED';
      }
      $messageStack->add_session(
        'DEBUG moduleinfo.json [' . $owner . '/' . $repo_name . ']: ' . mb_substr($moduleinfo_debug_payload, 0, 4000),
        'info'
      );
    }

    $product_service = new bx_github_repositories_product_service();
    $mapping = $product_service->ensureProduct(
      (int)$repo_data['repositories_id'],
      $current_product_id,
      $current_attributes_id,
      $template_product_id,
      $local_file,
      $owner,
      $repo_name,
      $sync_token,
      $tag_name,
      $moduleinfo
    );

    $moduleinfo_hash_sql = $moduleinfo !== null
      ? "'" . xtc_db_input(hash('sha256', (string)json_encode($moduleinfo))) . "'"
      : 'NULL';

    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
          SET moduleinfo_ref_tag       = '" . xtc_db_input($tag_name) . "',
              moduleinfo_hash          = " . $moduleinfo_hash_sql . ",
              moduleinfo_last_fetch_at = now(),
              product_sync_error       = NULL
        WHERE repositories_id = " . (int)$repo_data['repositories_id']
    );

    $is_new_product = ((int)$repo_data['product_id'] === 0 || (int)$mapping['product_id'] !== (int)$repo_data['product_id']);
    $msg_key = $is_new_product
      ? BX_GITHUB_REPOSITORIES_INFO_PRODUCT_CREATED
      : BX_GITHUB_REPOSITORIES_INFO_PRODUCT_UPDATED;

    $messageStack->add_session(
      sprintf($msg_key, $owner . '/' . $repo_name, (int)$mapping['product_id']),
      'success'
    );
  } catch (Exception $e) {
    $error_msg = mb_substr($e->getMessage(), 0, 500);
    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
          SET product_sync_error = '" . xtc_db_input($error_msg) . "'
        WHERE repositories_id = " . (int)$repo_data['repositories_id']
    );
    $messageStack->add_session(
      sprintf(BX_GITHUB_REPOSITORIES_WARNING_PRODUCT_SYNC_FAILED, (string)$repo_data['owner_name'] . '/' . (string)$repo_data['repo_name'], $error_msg),
      'warning'
    );
  }

  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($current_action === 'delete_product') {
  $product_repository_id = (int)($_POST['product_repository_id'] ?? 0);

  if ($product_repository_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_REPOSITORY_SELECTION, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $repo_query = xtc_db_query(
    "SELECT repositories_id, owner_name, repo_name, product_id
       FROM " . TABLE_BX_GITHUB_REPOSITORIES . "
      WHERE repositories_id = " . $product_repository_id . "
      LIMIT 1"
  );

  if (xtc_db_num_rows($repo_query) === 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_REPOSITORY_SELECTION, 'error');
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $repo_data = xtc_db_fetch_array($repo_query);
  $product_id = (int)($repo_data['product_id'] ?? 0);

  if ($product_id <= 0) {
    $messageStack->add_session(
      sprintf(BX_GITHUB_REPOSITORIES_INFO_NO_PRODUCT_TO_DELETE, (string)$repo_data['owner_name'] . '/' . (string)$repo_data['repo_name']),
      'info'
    );
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  try {
    $product_exists_query = xtc_db_query(
      "SELECT products_id
         FROM " . TABLE_PRODUCTS . "
        WHERE products_id = " . $product_id . "
        LIMIT 1"
    );

    if (xtc_db_num_rows($product_exists_query) > 0) {
      require_once(DIR_WS_CLASSES . 'categories.php');
      $categories = new categories();
      $categories->remove_product($product_id);
    }

    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES . "
          SET product_id              = 0,
              products_attributes_id  = 0,
              product_sync_error      = NULL,
              updated_at              = now()
        WHERE repositories_id = " . (int)$repo_data['repositories_id']
    );

    $messageStack->add_session(
      sprintf(BX_GITHUB_REPOSITORIES_SUCCESS_PRODUCT_DELETED, (string)$repo_data['owner_name'] . '/' . (string)$repo_data['repo_name'], $product_id),
      'success'
    );
  } catch (Exception $e) {
    $messageStack->add_session(
      sprintf(BX_GITHUB_REPOSITORIES_WARNING_PRODUCT_DELETE_FAILED, (string)$repo_data['owner_name'] . '/' . (string)$repo_data['repo_name'], mb_substr($e->getMessage(), 0, 500)),
      'warning'
    );
  }

  xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
  exit();
}

if ($current_action === 'sync_releases') {
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

  $single_sync_repository_id = (int)($_POST['sync_repository_id'] ?? 0);

  $active_query = xtc_db_query(
    "SELECT repo.repositories_id, 
                   repo.owner_name, 
                   repo.repo_name, 
                   repo.local_filename_stable, 
                   repo.current_tag_name,
                   repo.product_id, 
                   repo.products_attributes_id,
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
        " . ($single_sync_repository_id > 0 ? " AND repo.repositories_id = " . $single_sync_repository_id : "") . "
   ORDER BY repo.owner_name ASC, repo.repo_name ASC"
  );
  $active_repos = [];
  while ($row = xtc_db_fetch_array($active_query)) {
    $active_repos[] = $row;
  }

  if (count($active_repos) === 0) {
    if ($single_sync_repository_id > 0) {
      $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_REPOSITORY_SELECTION, 'error');
    } else {
      $messageStack->add_session(BX_GITHUB_REPOSITORIES_INFO_NO_ACTIVE_REPOSITORIES, 'warning');
    }
    xtc_redirect(xtc_href_link(BX_FILENAME_GITHUB_REPOSITORIES));
    exit();
  }

  $download_dir = rtrim((string)DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR;
  $sync_success = 0;
  $sync_errors  = 0;
  $sync_skipped = 0;

  foreach ($active_repos as $repo) {
    $repo_id            = (int)$repo['repositories_id'];
    $owner              = (string)$repo['owner_name'];
    $repo_name_str      = (string)$repo['repo_name'];
    $local_file         = (string)$repo['local_filename_stable'];
    $current_tag        = isset($repo['current_tag_name']) ? trim((string)$repo['current_tag_name']) : '';
    $downloaded_tag     = isset($repo['downloaded_tag_name']) ? trim((string)$repo['downloaded_tag_name']) : '';
    $repo_product_id    = (int)($repo['product_id'] ?? 0);
    $repo_attributes_id = (int)($repo['products_attributes_id'] ?? 0);

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

      if ($downloaded_tag !== '' && $downloaded_tag === $tag_name && is_file($target_path)) {
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

if ($current_action === 'test_connection') {
  $test_app_id          = (int)$current_app_id_value;
  $test_installation_id = (int)$current_installation_id_value;
  $test_has_errors      = false;

  if ($test_app_id <= 0 || $test_installation_id <= 0) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS, 'error');
    $test_has_errors = true;
  }

  $uploaded_pem = '';
  if (isset($_FILES['github_private_key_file']) && is_array($_FILES['github_private_key_file'])) {
    $upload_error = (int)($_FILES['github_private_key_file']['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($upload_error !== UPLOAD_ERR_NO_FILE) {
      if ($upload_error !== UPLOAD_ERR_OK) {
        $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_UPLOAD_FAILED, $upload_error), 'error');
        $test_has_errors = true;
      } else {
        $tmp_name = (string)($_FILES['github_private_key_file']['tmp_name'] ?? '');
        $size = (int)($_FILES['github_private_key_file']['size'] ?? 0);

        if ($size <= 0 || $size > 32768) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_FILE_SIZE, 'error');
          $test_has_errors = true;
        } elseif ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
          $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_UPLOAD, 'error');
          $test_has_errors = true;
        } else {
          $upload_content = file_get_contents($tmp_name);
          if ($upload_content === false) {
            $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_FILE_READ_FAILED, 'error');
            $test_has_errors = true;
          } else {
            $uploaded_pem = trim((string)$upload_content);
          }
        }
      }
    }
  }

  if ($uploaded_pem !== '' && !bx_github_repositories_is_valid_pem_private_key($uploaded_pem)) {
    $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_INVALID_PRIVATE_KEY, 'error');
    $test_has_errors = true;
  }

  if ($test_has_errors === false) {
    $private_key_for_test = $uploaded_pem;

    if ($private_key_for_test === '') {
      if ($private_key_encrypted_value !== '') {
        try {
          $crypto = new bx_github_repositories_crypto();
          $private_key_for_test = $crypto->decryptToken($private_key_encrypted_value);
        } catch (Exception $e) {
          $messageStack->add_session(sprintf(BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED, $e->getMessage()), 'error');
          $test_has_errors = true;
        }
      } else {
        $messageStack->add_session(BX_GITHUB_REPOSITORIES_ERROR_MISSING_STORED_PRIVATE_KEY, 'error');
        $test_has_errors = true;
      }
    }

    if ($test_has_errors === false && $private_key_for_test !== '') {
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

  <div id="bx-github-loading-overlay" aria-live="assertive" aria-busy="true">
    <div class="bx-github-loading-box main" role="status">
      <div class="bx-github-loading-spinner" aria-hidden="true"></div>
      <p class="bx-github-loading-title"><?php echo BX_GITHUB_REPOSITORIES_TEXT_LOADING_TITLE; ?></p>
      <p class="bx-github-loading-note"><?php echo BX_GITHUB_REPOSITORIES_TEXT_LOADING_NOTE; ?></p>
    </div>
  </div>

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
        <div class="pageHeadingImage bx-gh-page-heading-image">
          <?php echo xtc_image(DIR_WS_ICONS . 'heading/bx_github_repositories.png', BX_GITHUB_REPOSITORIES_HEADING_TITLE, '', '', 'class="bx-gh-heading-icon"'); ?>
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
                  <strong><?php echo BX_GITHUB_REPOSITORIES_HEADING_TITLE; ?></strong> 
                  <?php echo BX_GITHUB_REPOSITORIES_TEXT_INTRO; ?>
                </div>
              </div>

              <div class="tabs">
                <ul class="tab-nav" role="tablist" aria-label="BX GitHub Repositories Tabs">
                  <li><a id="tab-link-setup" href="#tab-setup" role="tab" aria-controls="tab-setup" aria-selected="false" tabindex="-1"><?php echo BX_GITHUB_REPOSITORIES_TAB_SETUP; ?></a></li>
                  <li><a id="tab-link-repos" href="#tab-repos" role="tab" aria-controls="tab-repos" aria-selected="false" tabindex="-1"><?php echo BX_GITHUB_REPOSITORIES_TAB_REPOSITORIES; ?></a></li>
                  <li><a id="tab-link-manual" href="#tab-manual" role="tab" aria-controls="tab-manual" aria-selected="false" tabindex="-1"><?php echo BX_GITHUB_REPOSITORIES_TAB_MANUAL; ?></a></li>
                </ul>

                <div class="tab-content">
                  <div id="tab-setup" role="tabpanel" aria-labelledby="tab-link-setup" tabindex="0" hidden="hidden">
                    <div class="main bx-gh-section-heading"><strong><?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_HEADING; ?></strong></div>
                    <div class="main bx-gh-section-text">
                      <?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_DESCRIPTION; ?>
                      <?php echo BX_GITHUB_REPOSITORIES_TEXT_SETUP_GOAL; ?>
                    </div>
                    <?php echo xtc_draw_form('github_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post', 'enctype="multipart/form-data"'); ?>
                    <table class="tableInput" border="0" cellpadding="6" cellspacing="0" width="100%">
                      <tr>
                        <td class="main bx-gh-form-label-col"><label for="github_app_id"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_APP_ID; ?></strong></label></td>
                        <td class="main"><input type="text" id="github_app_id" name="github_app_id" value="<?php echo htmlspecialchars($app_id_value, ENT_QUOTES, 'UTF-8'); ?>" class="bx-gh-input-regular" /></td>
                      </tr>
                      <tr>
                        <td class="main"><label for="github_installation_id"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_INSTALLATION_ID; ?></strong></label></td>
                        <td class="main"><input type="text" id="github_installation_id" name="github_installation_id" value="<?php echo htmlspecialchars($installation_id_value, ENT_QUOTES, 'UTF-8'); ?>" class="bx-gh-input-regular" /></td>
                      </tr>
                      <tr>
                        <td class="main bx-gh-vertical-top"><label for="github_private_key_file"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_UPLOAD; ?></strong></label></td>
                        <td class="main">
                          <?php echo xtc_draw_file_field('github_private_key_file', false, ' id="github_private_key_file" accept=".pem"'); ?>
                          <div class="bx-gh-help-text">
                            <?php echo BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_UPLOAD_HELP; ?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td class="main bx-gh-vertical-top"><label for="github_private_key"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY; ?></strong></label></td>
                        <td class="main">
                          <textarea id="github_private_key" rows="8" readonly="readonly"><?php echo htmlspecialchars($private_key_encrypted_value, ENT_QUOTES, 'UTF-8'); ?></textarea>
                          <div class="bx-gh-help-text">
                            <?php echo BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_HINT; ?>
                          </div>
                        </td>
                      </tr>
                    </table>
                    <div class="main bx-gh-status-text">
                      <?php echo BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_STATUS; ?>: <?php echo $private_key_encrypted_value !== '' ? BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_STORED : BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_MISSING; ?>
                    </div>
                    <div class="bx-gh-actions-row">
                      <button type="submit" class="button" name="action" value="test_connection"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_TEST_CONNECTION; ?></button>
                      <button type="submit" class="button bx-gh-save-settings-btn" name="action" value="save_settings"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_SAVE_SETTINGS; ?></button>
                    </div>
                    </form>
                  </div> <!-- #tab-setup -->

                  <div id="tab-repos" role="tabpanel" aria-labelledby="tab-link-repos" tabindex="0" hidden="hidden">
                    <div class="main bx-gh-section-heading bx-gh-repo-heading">
                      <strong><?php echo BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_HEADING; ?></strong>
                    </div>
                    <div class="main bx-gh-repo-intro">
                      <?php echo BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_INTRO; ?>
                    </div>

                    <?php echo xtc_draw_form('github_repositories_selection_form', BX_FILENAME_GITHUB_REPOSITORIES, '', 'post'); ?>
                    <input type="hidden" name="sync_repository_id" id="sync_repository_id" value="" />
                    <input type="hidden" name="product_repository_id" id="product_repository_id" value="" />
                    <table class="tableInput" border="0" cellpadding="6" cellspacing="0" width="100%">
                      <tr>
                        <td class="main">
                          <label for="select_all_repositories">
                            <input type="checkbox" id="select_all_repositories" aria-label="<?php echo BX_GITHUB_REPOSITORIES_LABEL_SELECT_ALL_REPOSITORIES; ?>" />
                            <strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_REPOSITORY; ?></strong>
                          </label>
                        </td>
                        <td class="main bx-gh-col-local-filename"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_LOCAL_FILENAME; ?></strong></td>
                        <td class="main bx-gh-col-zip-status"><strong>ZIP</strong></td>
                        <td class="main bx-gh-col-version"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_VERSION; ?></strong></td>
                        <td class="main bx-gh-col-product"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_PRODUCT; ?></strong></td>
                        <td class="main bx-gh-col-last-check"><strong><?php echo BX_GITHUB_REPOSITORIES_LABEL_LAST_CHECK; ?></strong></td>
                      </tr>
                      <?php if (count($repository_rows) === 0) { ?>
                        <tr>
                          <td class="main bx-gh-empty-row" colspan="5">
                            <?php echo BX_GITHUB_REPOSITORIES_TEXT_NO_REPOSITORIES_LOADED; ?>
                          </td>
                        </tr>
                      <?php } else { ?>
                        <?php foreach ($repository_rows as $repository_row) { ?>
                          <?php
                            $local_filename_display = (string)$repository_row['local_filename_stable'];
                            $local_filename_safe    = basename($local_filename_display);
                            $local_file_path        = rtrim((string)DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . $local_filename_safe;
                            $local_file_exists      = ($local_filename_safe !== '') && is_file($local_file_path);
                            $download_endpoint      = (string)constant('BX_FILENAME_GITHUB_DOWNLOAD_REPO');
                            $local_file_url         = rtrim((string)HTTP_CATALOG_SERVER, '/') . (string)DIR_WS_CATALOG . 'admin/' . $download_endpoint . '?file=' . rawurlencode($local_filename_safe);

                            $tag_update_required = $repository_row['current_tag_name'] !== null
                              && trim((string)$repository_row['current_tag_name']) !== ''
                              && trim((string)($repository_row['downloaded_tag_name'] ?? '')) !== trim((string)$repository_row['current_tag_name']);
                            $zip_download_required = $tag_update_required || ((int)$repository_row['status'] === 1 && !$local_file_exists);
                            $mapped_product_id = (int)($repository_row['product_id'] ?? 0);
                            $has_mapped_product = false;
                            if ($mapped_product_id > 0) {
                              $product_check_query = xtc_db_query(
                                "SELECT products_id
                                   FROM " . TABLE_PRODUCTS . "
                                  WHERE products_id = " . $mapped_product_id . "
                                  LIMIT 1"
                              );
                              $has_mapped_product = xtc_db_num_rows($product_check_query) > 0;
                            }
                          ?>
                          <tr class="<?php echo $repository_row['last_error_message'] !== null ? 'bx-gh-row-error' : ($zip_download_required ? 'bx-gh-row-update' : ''); ?>">
                            <td class="main">
                              <label>
                                <input type="checkbox" class="js-repo-select" name="selected_repositories[]" value="<?php echo (int)$repository_row['repositories_id']; ?>"<?php echo (int)$repository_row['status'] === 1 ? ' checked="checked"' : ''; ?> />
                                <?php echo htmlspecialchars((string)$repository_row['owner_name'] . '/' . (string)$repository_row['repo_name'], ENT_QUOTES, 'UTF-8'); ?>
                              </label>
                              <?php if ($repository_row['last_error_message'] !== null) { ?>
                                <div class="bx-gh-repo-error-msg"><?php echo htmlspecialchars((string)$repository_row['last_error_message'], ENT_QUOTES, 'UTF-8'); ?></div>
                              <?php } ?>
                            </td>
                            <td class="main bx-gh-text-muted">
                              <?php
                                if ($local_file_exists) {
                                  if($_SESSION['customer_id'] === '1') {
                                    echo '<a class="bx-gh-local-file-link" href="' . htmlspecialchars($local_file_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" title="' . htmlspecialchars(BX_GITHUB_REPOSITORIES_TEXT_OPEN_DOWNLOAD_FILE, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($local_filename_display, ENT_QUOTES, 'UTF-8').'</a>';
                                  } else {
                                    echo '<span class="bx-gh-local-file">' . htmlspecialchars($local_filename_display, ENT_QUOTES, 'UTF-8') . '</span>';
                                  }
                                } else {
                                  echo '<span class="bx-gh-local-file-missing" title="' . htmlspecialchars(BX_GITHUB_REPOSITORIES_TEXT_DOWNLOAD_FILE_MISSING, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($local_filename_display, ENT_QUOTES, 'UTF-8') . '</span>';
                                }
                              ?>
                            </td>
                            <td class="main bx-gh-zip-status-cell">
                              <?php 
                              if ($zip_download_required) { 
                                if ((int)$repository_row['status'] === 1) { ?>
                                  <div class="bx-gh-inline-download">
                                    <button type="submit" class="button bx-gh-button-red" name="action" value="sync_releases" onclick="document.getElementById('sync_repository_id').value='<?php echo (int)$repository_row['repositories_id']; ?>';">
                                      <?php echo BX_GITHUB_REPOSITORIES_BUTTON_DOWNLOAD_REPOSITORY; ?>
                                    </button>
                                  </div>
                              <?php
                                 }
                               } else {
                                 echo '<span class="bx-gh-zip-dot bx-gh-zip-dot-present" title="' . htmlspecialchars(BX_GITHUB_REPOSITORIES_TEXT_ZIP_PRESENT, ENT_QUOTES, 'UTF-8') . '"></span>';
                               }
                              ?>
                            </td>
                            <td class="main bx-gh-version-cell">
                              <?php echo $repository_row['current_tag_name'] !== null ? htmlspecialchars((string)$repository_row['current_tag_name'], ENT_QUOTES, 'UTF-8') : '&ndash;'; ?>
                            </td>
                            <td class="main bx-gh-product-cell">

                              <div class="bx-gh-inline-download">
                                <?php if ($has_mapped_product) { ?>
                                  <button
                                    type="submit"
                                    class="button bx-gh-button-red"
                                    name="action"
                                    value="delete_product"
                                    onclick="document.getElementById('product_repository_id').value='<?php echo (int)$repository_row['repositories_id']; ?>'; return confirm('<?php echo addslashes(BX_GITHUB_REPOSITORIES_TEXT_CONFIRM_DELETE_PRODUCT); ?>');"
                                  >
                                    <?php echo BX_GITHUB_REPOSITORIES_BUTTON_DELETE_PRODUCT; ?>
                                  </button>
                                <?php } else { ?>
                                  <button
                                    type="submit"
                                    class="button bx-gh-button-create"
                                    name="action"
                                    value="create_product"
                                    onclick="document.getElementById('product_repository_id').value='<?php echo (int)$repository_row['repositories_id']; ?>';"
                                  >
                                    <?php echo BX_GITHUB_REPOSITORIES_BUTTON_CREATE_PRODUCT; ?>
                                  </button>
                                <?php } ?>
                              </div>

                            </td>
                            <td class="main bx-gh-lastcheck-cell">
                              <?php echo $repository_row['last_check_at'] !== null ? htmlspecialchars((string)$repository_row['last_check_at'], ENT_QUOTES, 'UTF-8') : '&ndash;'; ?>
                            </td>
                          </tr>
                        <?php } ?>
                      <?php } ?>
                    </table>

                    <?php if (count($repository_rows) > 0) { ?>
                      <div class="bx-gh-selection-actions">
                        <button type="submit" class="button" name="action" value="save_repository_selection" onclick="document.getElementById('sync_repository_id').value='';"><?php echo BX_GITHUB_REPOSITORIES_BUTTON_SAVE_REPOSITORY_SELECTION; ?></button>
                      </div>
                    <?php } ?>
                    </form>

                    <div class="main bx-gh-import-target">
                      <?php echo BX_GITHUB_REPOSITORIES_TEXT_IMPORT_TARGET; ?>
                    </div>
                  </div> <!-- #tab-repos -->

                  <div id="tab-manual" role="tabpanel" aria-labelledby="tab-link-manual" tabindex="0" hidden="hidden">

                  <div class="main bx-gh-section-heading bx-gh-manual-heading">
                        <strong><?php echo BX_GITHUB_REPOSITORIES_TEXT_MANUAL_HEADING; ?></strong>
                      </div>
                      <div class="main bx-gh-manual-text">
                        <?php echo BX_GITHUB_REPOSITORIES_TEXT_MANUAL_INTRO; ?>
                      </div>
                      <div class="main bx-gh-manual-text">
                        <?php echo BX_GITHUB_REPOSITORIES_TEXT_MANUAL_TAG_REQUIREMENT; ?>
                      </div>
                      <pre class="bx-gh-manual-code"><code>git tag -a v2.0.5 -m "BX Products Video v2.0.5"
  git push origin v2.0.5</code></pre>
                      <div class="main bx-gh-manual-text bx-gh-text-soft-dark">
                        <?php echo BX_GITHUB_REPOSITORIES_TEXT_MANUAL_LOCATION_HINT; ?>
                      </div>
                  </div> <!-- #tab-manual -->

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
  $contents[] = array('text' => BX_GITHUB_REPOSITORIES_TEXT_SETUP_RIGHT_HELP);

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

                <div id="tab-manual-right" role="tabpanel" aria-labelledby="tab-link-manual" tabindex="0" hidden="hidden">
<?php

  $manual_language_code = strtoupper($_SESSION['language_code'] ?? 'DE');
/** pub/INSTALLATION_SHOP_OPERATOR_DE.pdf */
  $manual_download_file = rawurlencode('c' . $manual_language_code . '.pdf');
  $manual_download_path = 'pub/';
  $manual_download_url  = xtc_href_link_admin($manual_download_path . $manual_download_file);

  if (is_file(DIR_FS_CATALOG . $manual_download_path . $manual_download_file)) {
    $manual_download_link_html = '<a href="' . htmlspecialchars($manual_download_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($manual_download_file, ENT_QUOTES, 'UTF-8') . '</a>';
  } else {
    $manual_download_link_html = '<p style="margin: 0;">' . htmlspecialchars(BX_GITHUB_REPOSITORIES_TEXT_FILE_MISSING, ENT_QUOTES, 'UTF-8') . '</p>';
  }

  $heading  = array();
  $contents = array();

  $heading[]  = array('text' => '<strong>'.BX_GITHUB_REPOSITORIES_TEXT_MANUAL_HEADING.'</strong>');
  $contents[] = array('text' => BX_GITHUB_REPOSITORIES_TEXT_MANUAL_RIGHT_HELP);
  $contents[] = array('text' => $manual_download_link_html);

  if ( (xtc_not_null($heading)) && (xtc_not_null($contents)) ) {
    $box = new box;
    echo $box->infoBox($heading, $contents);
  }
?>
              </div> <!-- #tab-manual-right -->
            </div> <!-- .tab-content -->

          </td> <!-- .boxRight -->
        </tr>
      </table> <!-- .tableCenter -->
    </td> <!-- .boxCenter -->
  </tr>
</table>

<?php require(DIR_WS_INCLUDES.'footer.php'); ?>
<script>
  (function () {
    var overlay = document.getElementById('bx-github-loading-overlay');
    if (!overlay) {
      return;
    }

    var longRunningActions = {
      load_repositories: true,
      sync_releases: true
    };

    var showOverlay = function () {
      overlay.style.display = 'flex';
    };

    var forms = document.getElementsByTagName('form');

    for (var i = 0; i < forms.length; i++) {
      forms[i].addEventListener('submit', function (event) {
        var form = event.target;
        var submitter = event.submitter || null;
        var actionValue = '';

        if (submitter && submitter.name === 'action') {
          actionValue = submitter.value;
        }

        if (!actionValue && document.activeElement && document.activeElement.name === 'action') {
          actionValue = document.activeElement.value;
        }

        if (!actionValue) {
          var hiddenActionInput = form.querySelector('input[name="action"]');
          if (hiddenActionInput) {
            actionValue = hiddenActionInput.value;
          }
        }

        if (longRunningActions[actionValue]) {
          showOverlay();
        }
      });
    }

    var selectionForm = document.querySelector('form[name="github_repositories_selection_form"]');
    var selectAllInput = document.getElementById('select_all_repositories');

    if (!selectionForm || !selectAllInput) {
      return;
    }

    var repoCheckboxes = selectionForm.querySelectorAll('input[name="selected_repositories[]"]');
    if (!repoCheckboxes.length) {
      selectAllInput.checked = false;
      selectAllInput.disabled = true;
      return;
    }

    var updateSelectAllState = function () {
      var checkedCount = 0;

      for (var j = 0; j < repoCheckboxes.length; j++) {
        if (repoCheckboxes[j].checked) {
          checkedCount++;
        }
      }

      selectAllInput.checked = checkedCount === repoCheckboxes.length;
      selectAllInput.indeterminate = checkedCount > 0 && checkedCount < repoCheckboxes.length;
    };

    selectAllInput.addEventListener('change', function () {
      for (var j = 0; j < repoCheckboxes.length; j++) {
        repoCheckboxes[j].checked = selectAllInput.checked;
      }
      selectAllInput.indeterminate = false;
    });

    for (var j = 0; j < repoCheckboxes.length; j++) {
      repoCheckboxes[j].addEventListener('change', updateSelectAllState);
    }

    updateSelectAllState();
  })();
</script>
</body>
</html>
<?php require(DIR_WS_INCLUDES.'application_bottom.php'); ?>