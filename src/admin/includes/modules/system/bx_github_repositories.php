<?php

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

class bx_github_repositories {
  /** @var string Modulcode */
  public string $code;
  
  /** @var string Modul-Titel */
  public string $title;
  
  /** @var string Modul-Beschreibung */
  public string $description;
  
  /** @var int Sortierreihenfolge */
  public int $sort_order;
  
  /** @var bool Modul aktiviert/deaktiviert */
  public bool $enabled;
  
  /** @var string Modul-Version */
  public string $version; 
  
  /** @var int|null Check-Status Cache */
  private ?int $_check = null;

  /** @var string Entwicklungsstatus */
  public string $development_status; // 'p' = production ready, 'd' = in development

  public function __construct() {
    $this->code        = 'bx_github_repositories';
    $this->title       = MODULE_BX_GITHUB_REPOSITORIES_TEXT_TITLE;
    $this->description = MODULE_BX_GITHUB_REPOSITORIES_TEXT_DESCRIPTION;
    $this->sort_order  = defined('MODULE_BX_GITHUB_REPOSITORIES_SORT_ORDER') ? (int)constant('MODULE_BX_GITHUB_REPOSITORIES_SORT_ORDER') : 0;
    $this->enabled     = (defined('MODULE_BX_GITHUB_REPOSITORIES_STATUS') && constant('MODULE_BX_GITHUB_REPOSITORIES_STATUS') == 'True');
    $this->version     = '1.0.0';
    $this->development_status = 'd';
  }

  public function process($file): bool {
    return true;
  }

  public function display(): array {
    return array('text' => '<br /><div align="center">' . xtc_button(BUTTON_SAVE) .
      xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=' . $this->code)) .
      '</div>');
  }

  public function check(): int {
    if (!isset($this->_check)) {
      $check_query = xtc_db_query("SELECT configuration_value
                                     FROM " . TABLE_CONFIGURATION . "
                                    WHERE configuration_key = 'MODULE_BX_GITHUB_REPOSITORIES_STATUS'");
      $this->_check = xtc_db_num_rows($check_query);
    }

    return $this->_check;
  }

  public function install(): void {
    if (!$this->columnExists(TABLE_ADMIN_ACCESS, 'bx_github_repositories')) {
      xtc_db_query("ALTER TABLE " . TABLE_ADMIN_ACCESS . " ADD bx_github_repositories INT(1) NOT NULL DEFAULT 0");
    }
    xtc_db_query("UPDATE " . TABLE_ADMIN_ACCESS . " SET bx_github_repositories = 1");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             set_function, 
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_STATUS', 
                                                'True', 
                                                '6', 
                                                '1', 'xtc_cfg_select_option(array(\'True\', \'False\'), ',
                                                now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             set_function, 
                                             date_added)
                                     VALUES ('MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS', 
                                             'True', 
                                             '6', 
                                             '2', 
                                             'xtc_cfg_select_option(array(\'True\', \'False\'), ', 
                                             now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL', 
                                            '1', 
                                            '6', 
                                            '3', 
                                            now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             set_function, 
                                             date_added)
                                     VALUES ('MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT', 
                                             'daily', 
                                             '6', 
                                             '4', 
                                             'xtc_cfg_select_option(array(\'hourly\', \'daily\', \'weekly\', \'monthly\'), ', 
                                             now())");
    
                                             xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT', 
                                            '30', 
                                            '6', 
                                            '5', 
                                            now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY', 
                                            '60', 
                                            '6', 
                                            '6', 
                                            now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order,  
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT', 
                                            '3', 
                                            '6', 
                                            '7',
                                            now())");

    xtc_db_query("
    INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, 
                                             configuration_value, 
                                             configuration_group_id, 
                                             sort_order, 
                                             set_function,  
                                             date_added)
                                    VALUES ('MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG', 
                                            'False', 
                                            '6', 
                                            '8', 
                                            'xtc_cfg_select_option(array(\'True\', \'False\'), ',  
                                            now())");


    xtc_db_query("CREATE TABLE IF NOT EXISTS bx_github_repositories (
      repositories_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      status TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
      owner_name VARCHAR(128) NOT NULL,
      repo_name VARCHAR(128) NOT NULL,
      product_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
      products_attributes_id INT(11) UNSIGNED NOT NULL DEFAULT 0,
      release_asset_pattern VARCHAR(255) DEFAULT NULL,
      github_token_encrypted TEXT,
      local_filename_stable VARCHAR(255) NOT NULL,
      current_tag_name VARCHAR(128) DEFAULT NULL,
      current_release_name VARCHAR(255) DEFAULT NULL,
      current_asset_name VARCHAR(255) DEFAULT NULL,
      current_asset_url TEXT,
      current_published_at DATETIME DEFAULT NULL,
      last_check_at DATETIME DEFAULT NULL,
      last_success_at DATETIME DEFAULT NULL,
      last_error_message TEXT,
      check_interval_value INT(11) UNSIGNED NOT NULL DEFAULT 1,
      check_interval_unit VARCHAR(16) NOT NULL DEFAULT 'daily',
      auto_update TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
      auto_notify TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
      updated_at DATETIME NOT NULL DEFAULT '1000-01-01 00:00:00',
      PRIMARY KEY (repositories_id),
      KEY idx_status (status),
      KEY idx_product (product_id, products_attributes_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    xtc_db_query("CREATE TABLE IF NOT EXISTS bx_github_release_log (
      release_log_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      repositories_id INT(11) UNSIGNED NOT NULL,
      tag_name VARCHAR(128) NOT NULL,
      asset_name VARCHAR(255) DEFAULT NULL,
      asset_url TEXT,
      published_at DATETIME DEFAULT NULL,
      downloaded_at DATETIME DEFAULT NULL,
      file_size BIGINT(20) UNSIGNED DEFAULT NULL,
      checksum_sha256 CHAR(64) DEFAULT NULL,
      import_status VARCHAR(32) NOT NULL DEFAULT 'success',
      error_message TEXT,
      PRIMARY KEY (release_log_id),
      KEY idx_repo (repositories_id),
      KEY idx_tag (tag_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    xtc_db_query("CREATE TABLE IF NOT EXISTS bx_github_notifications (
      notification_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
      repositories_id INT(11) UNSIGNED NOT NULL,
      orders_id INT(11) UNSIGNED NOT NULL,
      orders_products_id INT(11) UNSIGNED NOT NULL,
      customers_id INT(11) UNSIGNED NOT NULL,
      customers_email_address VARCHAR(255) NOT NULL,
      tag_name VARCHAR(128) NOT NULL,
      queued_at DATETIME DEFAULT NULL,
      sent_at DATETIME DEFAULT NULL,
      status VARCHAR(32) NOT NULL DEFAULT 'queued',
      error_message TEXT,
      PRIMARY KEY (notification_id),
      KEY idx_repo_status (repositories_id, status),
      KEY idx_customer (customers_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (TABLE_SCHEDULED_TASKS !== '') {
      if (!$this->taskExists('github_repositories_check')) {
        xtc_db_query("INSERT INTO " . TABLE_SCHEDULED_TASKS . "
          (time_regularity, time_unit, status, tasks)
          VALUES ('1', 'd', 0, 'github_repositories_check')");
      }

      if (!$this->taskExists('github_repositories_notify')) {
        xtc_db_query("INSERT INTO " . TABLE_SCHEDULED_TASKS . "
          (time_regularity, time_unit, status, tasks)
          VALUES ('1', 'h', 0, 'github_repositories_notify')");
      }
    }
  }

  public function remove(): void {
    xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')");

    if ($this->columnExists(TABLE_ADMIN_ACCESS, 'bx_github_repositories')) {
      xtc_db_query("ALTER TABLE " . TABLE_ADMIN_ACCESS . " DROP bx_github_repositories");
    }

    xtc_db_query("DROP TABLE IF EXISTS bx_github_notifications");
    xtc_db_query("DROP TABLE IF EXISTS bx_github_release_log");
    xtc_db_query("DROP TABLE IF EXISTS bx_github_repositories");

    if (TABLE_SCHEDULED_TASKS !== '') {
      xtc_db_query("DELETE FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks IN ('github_repositories_check', 'github_repositories_notify')");
    }
  }

  public function keys(): array {
    return array(
      'MODULE_BX_GITHUB_REPOSITORIES_STATUS',
      'MODULE_BX_GITHUB_REPOSITORIES_SCHEDULED_TASKS',
      'MODULE_BX_GITHUB_REPOSITORIES_CHECK_INTERVAL',
      'MODULE_BX_GITHUB_REPOSITORIES_CHECK_UNIT',
      'MODULE_BX_GITHUB_REPOSITORIES_API_TIMEOUT',
      'MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_DELAY',
      'MODULE_BX_GITHUB_REPOSITORIES_API_RETRY_COUNT',
      'MODULE_BX_GITHUB_REPOSITORIES_AUTH_DEBUG',
    );
  }

  protected function columnExists($table, $column): bool {
    $column = xtc_db_input($column);
    $check_query = xtc_db_query("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'");

    return xtc_db_num_rows($check_query) > 0;
  }

  protected function taskExists($task_name): bool {
    if (TABLE_SCHEDULED_TASKS === '') {
      return false;
    }

    $task_name   = xtc_db_input($task_name);
    $check_query = xtc_db_query("SELECT tasks_id FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks = '" . $task_name . "' LIMIT 1");

    return xtc_db_num_rows($check_query) > 0;
  }
}
