<?php

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

/**
 * Service-Klasse für die automatische Anlage und Aktualisierung von
 * Download-Produkten aus GitHub-Repository-Metadaten.
 *
 * Workflow:
 *  1. ensureProduct() aufrufen mit aktuellen Repo-Daten.
 *  2. Wenn product_id == 0: Template-Produkt kopieren, inaktiv setzen.
 *  3. Produktfelder aus moduleinfo.json aktualisieren.
 *  4. Download-Attribut sicherstellen und stable_filename zuweisen.
 *  5. Mapping (product_id, attributes_id) in bx_github_repositories speichern.
 */
class bx_github_repositories_product_service
{
  /**
   * Stellt sicher, dass für ein Repository ein Produkt existiert und aktuell ist.
   *
   * @param int        $repo_id               ID in bx_github_repositories
   * @param int        $current_product_id    Gespeicherte product_id (0 = noch kein Produkt)
   * @param int        $current_attributes_id Gespeicherte products_attributes_id
   * @param int        $template_products_id  Template-Produkt als Kopiervorlage
   * @param string     $stable_filename        Stabiler Dateiname (z. B. "owner-repo.zip")
   * @param array|null $moduleinfo             Dekodierte moduleinfo.json oder null
   *
   * @return array ['product_id' => int, 'products_attributes_id' => int]
   * @throws Exception Bei kritischen Fehlern (Template fehlt etc.)
   */
  public function ensureProduct(
    int $repo_id,
    int $current_product_id,
    int $current_attributes_id,
    int $template_products_id,
    string $stable_filename,
    string $owner_name,
    string $repo_name,
    string $installation_token,
    string $tag_name,
    ?array $moduleinfo
  ): array {
    if ($template_products_id <= 0) {
      throw new Exception(BX_GITHUB_REPOSITORIES_ERROR_NO_TEMPLATE_PRODUCT);
    }

    // Template-Produkt validieren
    $template_check = xtc_db_query(
      "SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . (int)$template_products_id . " LIMIT 1"
    );
    if (xtc_db_num_rows($template_check) === 0) {
      throw new Exception(sprintf(BX_GITHUB_REPOSITORIES_ERROR_TEMPLATE_NOT_FOUND, $template_products_id));
    }

    // Bestehendes Produkt prüfen – könnte manuell gelöscht worden sein
    $products_id = $current_product_id;
    if ($products_id > 0) {
      if ($products_id === $template_products_id) {
        $products_id = 0; // Template nie direkt aktualisieren
      }

      $exists = xtc_db_query(
        "SELECT products_id FROM " . TABLE_PRODUCTS . " WHERE products_id = " . (int)$products_id . " LIMIT 1"
      );
      if (xtc_db_num_rows($exists) === 0) {
        $products_id = 0; // neu anlegen
      }
    }

    $created_new_product = false;
    if ($products_id === 0) {
      $products_id = $this->copyTemplateProduct($template_products_id);
      $created_new_product = true;
    }

    if ($created_new_product) {
      $this->assignProductImageFromGithub($products_id, $owner_name, $repo_name, $installation_token);
    }

    $this->updateProductModel($products_id, $this->buildProductsModel($repo_id, $tag_name));

    $this->updateProductFromModuleinfo($products_id, $moduleinfo);

    $attributes_id = $this->ensureDownloadAttribute(
      $products_id,
      $current_attributes_id,
      $template_products_id,
      $stable_filename
    );

    $this->saveRepoMapping($repo_id, $products_id, $attributes_id);

    return [
      'product_id'             => $products_id,
      'products_attributes_id' => $attributes_id,
    ];
  }

  /**
   * Setzt die SKU in products.products_model.
   */
  private function updateProductModel(int $products_id, string $products_model): void
  {
    if ($products_model === '') {
      return;
    }

    xtc_db_query(
      "UPDATE " . TABLE_PRODUCTS .
      " SET products_model = '" . xtc_db_input($products_model) . "'," .
      "     products_last_modified = now()" .
      " WHERE products_id = " . (int)$products_id
    );
  }

  /**
   * Baut SKU im Format GH-{repo_id}-{tag}. Ohne Tag: GH-{repo_id}.
   */
  private function buildProductsModel(int $repo_id, string $tag_name): string
  {
    $tag = trim($tag_name);
    $tag = preg_replace('/[^A-Za-z0-9._-]+/', '-', $tag) ?? '';
    $tag = trim($tag, '-._');

    $base = 'GH-' . max(0, $repo_id);
    if ($tag === '') {
      return $base;
    }

    return $base . '-' . $tag;
  }

  /**
   * Kopiert das Template-Produkt vollständig:
   * Stammdaten, Beschreibungen (alle Sprachen), Kategoriezuordnung,
   * sowie das erste Download-Attribut inkl. products_attributes_download.
   * Das kopierte Produkt ist immer inaktiv (products_status = 0).
   */
  private function copyTemplateProduct(int $template_id): int
  {
    // --- Stammdaten kopieren ---
    $row_query = xtc_db_query(
      "SELECT * FROM " . TABLE_PRODUCTS . " WHERE products_id = " . (int)$template_id . " LIMIT 1");
    $row = xtc_db_fetch_array($row_query);

    if (!is_array($row) || empty($row)) {
      throw new Exception('Produktkopie fehlgeschlagen: Template-Produktdaten konnten nicht geladen werden.');
    }

    unset($row['products_id']);
    $row['products_status'] = 0; // immer inaktiv
    $row['products_sort'] = $this->getLowestFreeProductsSort();
    if (isset($row['products_model'])) {
      // products_model kann in manchen Shops eindeutig indiziert sein.
      $row['products_model'] = '';
    }
    if (array_key_exists('products_image', $row)) {
      // Das neue Produkt darf nie auf den Dateinamen des Template-Bildes zeigen.
      $row['products_image'] = '';
    }
    if (array_key_exists('ebay_sku', $row)) {
      // Wenn ein Fremdmodul ebay_sku eindeutig indiziert, darf hier kein leerer String dupliziert werden.
      $row['ebay_sku'] = null;
    }

    $new_id = $this->insertProductRow($row);
    if ($new_id <= 0) {
      // Fallback: potenziell eindeutige Felder leeren und erneut versuchen.
      foreach (['products_model', 'products_ean'] as $unique_col) {
        if (array_key_exists($unique_col, $row)) {
          $row[$unique_col] = '';
        }
      }
      $new_id = $this->insertProductRow($row);
    }
    if ($new_id <= 0) {
      $db_error = $this->getLastDbErrorMessage();
      $error_suffix = $db_error !== '' ? ' DB-Fehler: ' . $db_error : '';
      throw new Exception('Produktkopie fehlgeschlagen: Keine neue products_id erhalten.' . $error_suffix);
    }

    // --- Beschreibungen kopieren (alle Sprachen) ---
    $desc_query = xtc_db_query(
      "SELECT * FROM " . TABLE_PRODUCTS_DESCRIPTION .
      " WHERE products_id = " . (int)$template_id
    );
    while ($desc = xtc_db_fetch_array($desc_query)) {
      unset($desc['products_id']);
      $desc_parts = ['`products_id` = ' . $new_id];
      foreach ($desc as $col => $val) {
        $desc_parts[] = '`' . $col . '` = \'' . xtc_db_input((string)$val) . '\'';
      }
      xtc_db_query(
        "INSERT INTO " . TABLE_PRODUCTS_DESCRIPTION . " SET " . implode(', ', $desc_parts)
      );
    }

    // --- Kategoriezuordnung: alle Kategorien des Templates übernehmen ---
    $cat_query = xtc_db_query(
      "SELECT categories_id FROM " . TABLE_PRODUCTS_TO_CATEGORIES .
      " WHERE products_id = " . (int)$template_id
    );
    while ($cat = xtc_db_fetch_array($cat_query)) {
      xtc_db_query(
        "INSERT INTO " . TABLE_PRODUCTS_TO_CATEGORIES .
        " (products_id, categories_id)" .
        " VALUES (" . $new_id . ", " . (int)$cat['categories_id'] . ")"
      );
    }

    return $new_id;
  }

  /**
   * Führt den Produkt-INSERT aus und liefert die neue products_id (oder 0).
   */
  private function insertProductRow(array $row): int
  {
    $set_parts = [];
    foreach ($row as $col => $val) {
      if (in_array($col, ['products_date_added', 'products_last_modified'], true)) {
        $set_parts[] = '`' . $col . '` = now()';
      } elseif ($val === null) {
        $set_parts[] = '`' . $col . '` = NULL';
      } else {
        $set_parts[] = '`' . $col . '` = \'' . xtc_db_input((string)$val) . '\'';
      }
    }
    $set_parts[] = '`products_date_added` = now()';
    $set_parts[] = '`products_last_modified` = now()';

    // Duplikate auf Spaltennamen vermeiden
    $seen = [];
    $unique_parts = [];
    foreach ($set_parts as $part) {
      $key = strtok($part, ' ');
      if (!isset($seen[$key])) {
        $seen[$key]     = true;
        $unique_parts[] = $part;
      }
    }

    $result = xtc_db_query("INSERT INTO " . TABLE_PRODUCTS . " SET " . implode(', ', $unique_parts));
    if ($result === false) {
      return 0;
    }

    return (int)xtc_db_insert_id();
  }

  /**
   * Liest die letzte DB-Fehlermeldung aus der aktiven Verbindung.
   */
  private function getLastDbErrorMessage(): string
  {
    if (!isset($GLOBALS['db_link'])) {
      return '';
    }

    $db_link = $GLOBALS['db_link'];

    if (is_object($db_link) && function_exists('mysqli_error')) {
      return (string)mysqli_error($db_link);
    }

    if (is_resource($db_link) && function_exists('mysql_error')) {
      return (string)mysql_error($db_link);
    }

    return '';
  }

  /**
   * Aktualisiert Name, Kurz- und Langbeschreibung sowie Preis aus moduleinfo.json.
   * Felder, die in moduleinfo fehlen oder leer sind, werden nicht überschrieben.
   */
  private function updateProductFromModuleinfo(int $products_id, ?array $moduleinfo): void
  {
    if ($moduleinfo === null) {
      return;
    }

    // Preis aktualisieren
    $gross_price = bx_github_repositories_parse_price($moduleinfo['price'] ?? null);
    if ($gross_price !== null) {
      $tax_rate = $this->getProductTaxRate($products_id);
      $net_price = $gross_price;
      if ($tax_rate > 0.0) {
        $net_price = $gross_price / (1.0 + ($tax_rate / 100.0));
      }

      xtc_db_query(
        "UPDATE " . TABLE_PRODUCTS .
        " SET products_price = '" . number_format($net_price, 4, '.', '') . "'," .
        "     products_last_modified = now()" .
        " WHERE products_id = " . (int)$products_id
      );
    }

    // products_model

    // Beschreibungsfelder aus moduleinfo lesen und sanitizen
    $name  = isset($moduleinfo['name'])             ? trim(strip_tags((string)$moduleinfo['name']))             : '';
    $short = isset($moduleinfo['shortDescription']) ? trim(strip_tags((string)$moduleinfo['shortDescription'])) : '';
    $desc  = isset($moduleinfo['description'])      ? trim(strip_tags((string)$moduleinfo['description']))      : '';

    if ($name === '' && $short === '' && $desc === '') {
      return;
    }

    // Ziel-Sprachen aus Konfiguration lesen (kommaseparierte language_ids)
    $configured_ids = defined('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS')
      ? (string)constant('MODULE_BX_GITHUB_REPOSITORIES_MODULEINFO_LANGUAGE_IDS')
      : '';
    $target_lang_ids = array_filter(
      array_map('intval', explode(',', $configured_ids)),
      static function (int $id): bool { return $id > 0; }
    );

    // Für jede vorhandene Sprachzeile aktualisieren (gefiltert oder alle)
    $lang_where = count($target_lang_ids) > 0
      ? " AND language_id IN (" . implode(',', $target_lang_ids) . ")"
      : '';
    $lang_query = xtc_db_query(
      "SELECT language_id FROM " . TABLE_PRODUCTS_DESCRIPTION .
      " WHERE products_id = " . (int)$products_id . $lang_where
    );
    while ($lang = xtc_db_fetch_array($lang_query)) {
      $parts = [];
      if ($name  !== '') {
        $parts[] = "`products_name`              = '" . xtc_db_input($name)  . "'";
      }
      if ($name  !== '') {
        $parts[] = "`products_heading_title`     = '" . xtc_db_input($name)  . BX_GITHUB_REPOSITORIES_PRODUCT_HEADING_SUFFIX . "'";
      }
      if ($short !== '') {
        $parts[] = "`products_short_description` = '" . xtc_db_input($short) . "'";
      }
      if ($desc  !== '') {
        $parts[] = "`products_description`       = '" . xtc_db_input($desc)  . "'";
      }
      if (!empty($parts)) {
        xtc_db_query(
          "UPDATE " . TABLE_PRODUCTS_DESCRIPTION .
          " SET " . implode(', ', $parts) .
          " WHERE products_id = " . (int)$products_id .
          "   AND language_id = " . (int)$lang['language_id']
        );
      }
    }
  }

  /**
   * Ermittelt den Steuersatz des Produkts über tax_rates.
   */
  private function getProductTaxRate(int $products_id): float
  {
    $tax_query = xtc_db_query(
      "SELECT tr.tax_rate FROM " . TABLE_PRODUCTS . " p
                LEFT JOIN " . TABLE_TAX_RATES . " tr
                  ON tr.tax_class_id = p.products_tax_class_id
                WHERE p.products_id = " . (int)$products_id . " 
                ORDER BY tr.tax_priority ASC, tr.tax_class_id ASC LIMIT 1;"
    );

    $tax_row = xtc_db_fetch_array($tax_query);
    if (!is_array($tax_row) || !isset($tax_row['tax_rate'])) {
      return 0.0;
    }

    return (float)$tax_row['tax_rate'];
  }

  /**
   * Setzt beim neu erzeugten Produkt ein Bild aus dem Repository.
   * Reihenfolge: products_image.png, danach icon.png.
   */
  private function assignProductImageFromGithub(int $products_id, string $owner_name, string $repo_name, string $installation_token): void
  {
    $image_payload = $this->fetchGithubProductImage($owner_name, $repo_name, $installation_token);
    if ($image_payload === null) {
      return;
    }

    $image_name = (int)$products_id . '_0.' . $image_payload['extension'];
    $image_data = $image_payload['content'];

    $paths = [
      defined('DIR_FS_CATALOG_ORIGINAL_IMAGES') ? (string)constant('DIR_FS_CATALOG_ORIGINAL_IMAGES') : '',
      defined('DIR_FS_CATALOG_POPUP_IMAGES') ? (string)constant('DIR_FS_CATALOG_POPUP_IMAGES') : '',
      defined('DIR_FS_CATALOG_INFO_IMAGES') ? (string)constant('DIR_FS_CATALOG_INFO_IMAGES') : '',
      defined('DIR_FS_CATALOG_MIDI_IMAGES') ? (string)constant('DIR_FS_CATALOG_MIDI_IMAGES') : '',
      defined('DIR_FS_CATALOG_THUMBNAIL_IMAGES') ? (string)constant('DIR_FS_CATALOG_THUMBNAIL_IMAGES') : '',
      defined('DIR_FS_CATALOG_MINI_IMAGES') ? (string)constant('DIR_FS_CATALOG_MINI_IMAGES') : '',
    ];

    $written = false;
    foreach ($paths as $path) {
      if ($path === '' || !is_dir($path) || !is_writable($path)) {
        continue;
      }
      $target_file = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $image_name;
      if (@file_put_contents($target_file, $image_data) !== false) {
        $written = true;
      }
    }

    if (!$written) {
      return;
    }

    xtc_db_query(
      "UPDATE " . TABLE_PRODUCTS .
      " SET products_image = '" . xtc_db_input($image_name) . "'," .
      "     products_last_modified = now()" .
      " WHERE products_id = " . (int)$products_id
    );
  }

  /**
   * Lädt das bevorzugte Produktbild aus GitHub (products_image.png, sonst icon.png).
   */
  private function fetchGithubProductImage(string $owner_name, string $repo_name, string $installation_token): ?array
  {
    $owner = trim($owner_name);
    $repo  = trim($repo_name);
    $token = trim($installation_token);
    if ($owner === '' || $repo === '' || $token === '') {
      return null;
    }

    $candidates = ['products_image.png', 'icon.png'];
    foreach ($candidates as $filename) {
      $response = $this->downloadGithubRepositoryFile($owner, $repo, $filename, $token);
      if ($response === null || $response['content'] === '') {
        continue;
      }

      if (function_exists('getimagesizefromstring')) {
        $image_info = @getimagesizefromstring($response['content']);
        if ($image_info === false) {
          continue;
        }
      }

      return [
        'content' => $response['content'],
        'extension' => $response['extension'],
      ];
    }

    return null;
  }

  /**
   * Lädt eine Datei aus einem GitHub-Repository über die Contents-API.
   */
  private function downloadGithubRepositoryFile(string $owner, string $repo, string $filename, string $installation_token): ?array
  {
    $endpoint = 'https://api.github.com/repos/'
      . rawurlencode($owner) . '/'
      . rawurlencode($repo)
      . '/contents/' . rawurlencode($filename);

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
    $curl_error   = curl_error($ch);

    if ($curl_error !== '' || $http_code === 404 || $http_code !== 200 || !is_string($response_raw)) {
      return null;
    }

    $response = json_decode($response_raw, true);
    if (!is_array($response) || !isset($response['content']) || ($response['encoding'] ?? '') !== 'base64') {
      return null;
    }

    $decoded = base64_decode(str_replace(["\n", "\r"], '', (string)$response['content']), true);
    if ($decoded === false || $decoded === '') {
      return null;
    }

    $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension === '') {
      $extension = 'png';
    }

    return [
      'content' => $decoded,
      'extension' => $extension,
    ];
  }

  /**
   * Stellt sicher, dass ein Download-Attribut für das Produkt existiert
   * und auf den stabilen Dateinamen zeigt.
   *
   * Strategie:
   *  1. Vorhandenes Attribut (current_attributes_id) prüfen und wiederverwenden.
   *  2. Sonst: erstes Download-Attribut des Template-Produkts kopieren.
   *  3. Dateiname in products_attributes_download anlegen oder aktualisieren.
   *
   * @return int products_attributes_id (0 wenn Template kein Download-Attribut hat)
   */
  private function ensureDownloadAttribute(
    int $products_id,
    int $current_attributes_id,
    int $template_products_id,
    string $stable_filename
  ): int {
    $attributes_id = $current_attributes_id;

    // Vorhandenes Attribut validieren
    if ($attributes_id > 0) {
      $check = xtc_db_query(
        "SELECT products_attributes_id FROM " . TABLE_PRODUCTS_ATTRIBUTES .
        " WHERE products_attributes_id = " . (int)$attributes_id .
        "   AND products_id            = " . (int)$products_id . " LIMIT 1"
      );
      if (xtc_db_num_rows($check) === 0) {
        $attributes_id = 0;
      }
    }

    if ($attributes_id === 0) {
      // Erstes Download-Attribut des Templates kopieren
      $tpl_attr_query = xtc_db_query(
        "SELECT pa.options_id, pa.options_values_id, pa.options_values_price, pa.price_prefix, pa.attributes_stock" .
        " FROM " . TABLE_PRODUCTS_ATTRIBUTES . " pa" .
        " INNER JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad" .
        "    ON pad.products_attributes_id = pa.products_attributes_id" .
        " WHERE pa.products_id = " . (int)$template_products_id . " LIMIT 1"
      );
      $tpl_attr = xtc_db_fetch_array($tpl_attr_query);

      if (!$tpl_attr) {
        // Template hat kein Download-Attribut – nichts zu tun
        return 0;
      }

      $insert_columns = [
        'products_id',
        'options_id',
        'options_values_id',
        'options_values_price',
        'price_prefix',
        'attributes_stock',
      ];
      $insert_values = [
        (string)(int)$products_id,
        (string)(int)$tpl_attr['options_id'],
        (string)(int)$tpl_attr['options_values_id'],
        "'" . xtc_db_input((string)$tpl_attr['options_values_price']) . "'",
        "'" . xtc_db_input((string)$tpl_attr['price_prefix']) . "'",
        isset($tpl_attr['attributes_stock'])
          ? (string)(int)$tpl_attr['attributes_stock']
          : '0',
      ];

      xtc_db_query(
        "INSERT INTO " . TABLE_PRODUCTS_ATTRIBUTES .
        " (" . implode(', ', $insert_columns) . ")" .
        " VALUES (" . implode(', ', $insert_values) . ")"
      );
      $attributes_id = (int)xtc_db_insert_id();
    }

    // products_attributes_download anlegen oder aktualisieren
    $dl_exists = xtc_db_query(
      "SELECT products_attributes_id FROM " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD .
      " WHERE products_attributes_id = " . (int)$attributes_id . " LIMIT 1"
    );
    if (xtc_db_num_rows($dl_exists) > 0) {
      xtc_db_query(
        "UPDATE " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD .
        " SET products_attributes_filename = '" . xtc_db_input($stable_filename) . "'" .
        " WHERE products_attributes_id = " . (int)$attributes_id
      );
    } else {
      $maxdays = defined('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS')
        ? max(0, (int)constant('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXDAYS'))
        : 0;
      $maxcount = defined('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT')
        ? max(0, (int)constant('MODULE_BX_GITHUB_REPOSITORIES_DOWNLOAD_MAXCOUNT'))
        : 0;

      xtc_db_query(
        "INSERT INTO " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD .
        " (products_attributes_id, products_attributes_filename," .
        "  products_attributes_maxdays, products_attributes_maxcount)" .
        " VALUES (" . (int)$attributes_id . "," .
        "         '" . xtc_db_input($stable_filename) . "'," .
        "         " . $maxdays . "," .
        "         " . $maxcount . ")"
      );
    }

    return $attributes_id;
  }

  /**
   * Schreibt product_id und products_attributes_id zurück in bx_github_repositories.
   */
  private function saveRepoMapping(int $repo_id, int $products_id, int $attributes_id): void
  {
    xtc_db_query(
      "UPDATE " . TABLE_BX_GITHUB_REPOSITORIES .
      " SET product_id             = " . (int)$products_id . "," .
      "     products_attributes_id = " . (int)$attributes_id . "," .
      "     updated_at             = now()" .
      " WHERE repositories_id = " . (int)$repo_id
    );
  }

  /**
   * Ermittelt die kleinste freie products_sort-Position (> 0).
   */
  private function getLowestFreeProductsSort(): int
  {
    $expected = 1;
    $sort_query = xtc_db_query(
      "SELECT products_sort FROM " . TABLE_PRODUCTS .
      " WHERE products_sort > 0" .
      " ORDER BY products_sort ASC"
    );

    while ($sort_row = xtc_db_fetch_array($sort_query)) {
      $current = (int)($sort_row['products_sort'] ?? 0);
      if ($current < $expected) {
        continue;
      }
      if ($current > $expected) {
        break;
      }
      $expected++;
    }

    return $expected;
  }
}
