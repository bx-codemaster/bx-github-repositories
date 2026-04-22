<?php
/**
 * Hilfsfunktionen für GitHub Repository Synchronisation
 * 
 * Enthält Utilities für:
 * - Asset Pattern Validierung
 * - Datumverarbeitung
 * - URL Normalisierung
 * - Checksum Berechnung
 */

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

class bx_github_repositories_helpers {

  /**
   * Validiere GitHub Repository Format
   * 
   * @param string $owner Owner Name (GitHub User/Organization)
   * @param string $repo Repository Name
   * @return bool True wenn gültig
   */
  public static function isValidRepository(string $owner, string $repo): bool {
    // GitHub erlaubt alphanumeric, Bindestrich, Unterstrich
    $pattern = '^[a-zA-Z0-9._-]+$';

    return !empty($owner) && !empty($repo) 
      && preg_match('~' . $pattern . '~', $owner) 
      && preg_match('~' . $pattern . '~', $repo);
  }

  /**
   * Validiere Asset Pattern (Wildcard oder Regex)
   * 
   * @param string $pattern Pattern String
   * @return bool True wenn gültig
   */
  public static function isValidAssetPattern(string $pattern): bool {
    if (empty(trim($pattern))) {
      return false;
    }

    // Wenn Regex-artig (enthält ~): Prüfe Syntax
    if (preg_match('~^[^a-zA-Z0-9._*?-]~', trim($pattern))) {
      // Versuche Regex zu kompilieren
      return @preg_match('~' . $pattern . '~', '') !== false;
    }

    return true;
  }

  /**
   * Validiere GitHub Token Format
   * 
   * @param string $token Token String
   * @return bool True wenn plausibel
   */
  public static function isValidTokenFormat(string $token): bool {
    // Moderne GitHub Tokens beginnen mit:
    // - ghp_* (Personal Access Token)
    // - gho_* (OAuth Token)
    // - ghu_* (User to Server)
    // - ghs_* (Server to Server)
    // - ghu_* (User to Server)

    return (bool)preg_match('~^(ghp_|gho_|ghu_|ghs_|ghu_)[a-zA-Z0-9_]{36,}$~', $token);
  }

  /**
   * Normalisiere GitHub URL zu Repository Format
   * 
   * Beispiele:
   *  https://github.com/user/repo -> user/repo
   *  user/repo -> user/repo
   *  /user/repo -> user/repo
   */
  public static function normalizeRepositoryPath(string $path): ?array {
    $path = trim($path);

    // Entferne GitHub URLs
    if (strpos($path, 'https://') === 0 || strpos($path, 'http://') === 0) {
      $path = preg_replace('~^https?://github\.com/~', '', $path);
    }

    // Entferne führende/nachfolgende Slashes
    $path = trim($path, '/');

    // Teile nach /
    $parts = explode('/', $path);

    if (count($parts) === 2) {
      [$owner, $repo] = $parts;

      if (self::isValidRepository($owner, $repo)) {
        return [
          'owner' => $owner,
          'repo' => $repo
        ];
      }
    }

    return null;
  }

  /**
   * Konvertiere GitHub ISO DateTime zu Unix Timestamp
   * 
   * GitHub gibt Datetimes im Format "2023-04-21T10:30:45Z" zurück
   */
  public static function parseGitHubDateTime(?string $datetime): ?int {
    if (empty($datetime)) {
      return null;
    }

    try {
      $timestamp = strtotime($datetime);
      return $timestamp ?: null;
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * Prüfe ob ein Tag neuer ist als das letzte bekannte Tag
   * 
   * @param int|null $current_timestamp Timestamp des aktuellen Tags
   * @param int|null $last_timestamp Timestamp des letzten Checks
   */
  public static function isNewRelease(?int $current_timestamp, ?int $last_timestamp): bool {
    if (!$current_timestamp) {
      return false;
    }

    if (!$last_timestamp) {
      return true; // Erstes Mal
    }

    return $current_timestamp > $last_timestamp;
  }

  /**
   * Generiere stabilen lokalen Dateinamen
   * 
   * Basierend auf: bx-modulname-latest-HASH.ext
   * Beispiel: bx-github-repositories-latest-a1b2c3d4.zip
   */
  public static function generateStableFilename(string $owner, string $repo, string $asset_name): string {
    // Extrahiere Extension
    $parts = explode('.', $asset_name);
    $ext = array_pop($parts);

    // Generiere deterministic Hash
    $hash_input = strtolower($owner . '/' . $repo);
    $hash = substr(md5($hash_input), 0, 8);

    return 'bx-' . strtolower($repo) . '-latest-' . $hash . '.' . strtolower($ext);
  }

  /**
   * Validiere Datei für Download
   * 
   * @param string $filename Lokaler Dateiname
   * @param int $max_size Max Dateigröße in Bytes
   */
  public static function validateDownloadFile(string $filename, int $max_size = 0): bool {
    $filepath = DIR_FS_DOWNLOAD . $filename;

    // Prüfe ob Datei existiert
    if (!file_exists($filepath)) {
      return false;
    }

    // Prüfe ob Datei lesbar ist
    if (!is_readable($filepath)) {
      return false;
    }

    // Prüfe ob Datei reguläre Datei ist (nicht Directory)
    if (!is_file($filepath)) {
      return false;
    }

    // Prüfe Pfad Traversal (Datei muss in Download Dir sein)
    if (realpath($filepath) === false || strpos(realpath($filepath), realpath(DIR_FS_DOWNLOAD)) !== 0) {
      return false;
    }

    // Prüfe Größe wenn limite gesetzt
    if ($max_size > 0 && filesize($filepath) > $max_size) {
      return false;
    }

    return true;
  }

  /**
   * Berechne SHA256 Checksum einer Datei
   * 
   * @param string $filepath Absoluter Dateipfad
   * @return string|null SHA256 Hash oder null bei Fehler
   */
  public static function calculateFileChecksum(string $filepath): ?string {
    if (!file_exists($filepath) || !is_readable($filepath)) {
      return null;
    }

    try {
      return hash_file('sha256', $filepath);
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * Vergleiche zwei Checksums
   */
  public static function verifyChecksum(string $file_hash, string $expected_hash): bool {
    return hash_equals($file_hash, $expected_hash);
  }

  /**
   * Formatiere Bytes als lesbare Größe
   * 
   * Beispiele: 1024 -> "1 KB", 1048576 -> "1 MB"
   */
  public static function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
  }

  /**
   * Extrahiere Versionsnummer aus Tag Name
   * 
   * Beispiele:
   *  v1.2.3 -> 1.2.3
   *  release-2.0 -> 2.0
   *  1.0.0-beta -> 1.0.0
   */
  public static function extractVersion(string $tag): string {
    // Entferne führende v
    $version = preg_replace('~^v~i', '', $tag);

    // Entferne alles nach Bindestrich (pre-release Marker)
    $version = preg_replace('~-.*$~', '', $version);

    return $version;
  }

  /**
   * Prüfe ob String JSON ist
   */
  public static function isJson(string $string): bool {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

  /**
   * Sanitize Dateiname (entferne gefährliche Zeichen)
   */
  public static function sanitizeFilename(string $filename): string {
    // Entferne Pfad-Traversal Versuche
    $filename = str_replace(['..', '/', '\\'], '', $filename);

    // Nur alphanumeric, Punkt, Bindestrich, Unterstrich erlauben
    $filename = preg_replace('~[^a-zA-Z0-9._-]~', '', $filename);

    return $filename ?: 'file';
  }

  /**
   * Generiere Log Eintrag
   */
  public static function logAction(string $action, string $message, ?int $repo_id = null, string $level = 'info'): void {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] [{$action}]";

    if ($repo_id) {
      $log_entry .= " [Repo #{$repo_id}]";
    }

    $log_entry .= " {$message}\n";

    if (defined('DIR_FS_LOG')) {
      $log_file = DIR_FS_LOG . 'bx_github_repositories.log';
      error_log($log_entry, 3, $log_file);
    }
  }
}
