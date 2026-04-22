<?php

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

switch ($_SESSION['language_code']) {
  case 'de':
    define('MODULE_BX_GITHUB_REPOSITORIES_MENU_TITLE', 'BX GitHub Repositories');
    break;
  default:
    define('MODULE_BX_GITHUB_REPOSITORIES_MENU_TITLE', 'BX GitHub Repositories');
    break;
}

$add_contents[BOX_HEADING_TOOLS][] = array(
  'admin_access_name' => 'bx_github_repositories',
  'filename'          => 'bx_github_repositories.php',
  'boxname'           => MODULE_BX_GITHUB_REPOSITORIES_MENU_TITLE,
  'parameters'        => '',
  'ssl'               => ''
);
