<?php
/* -----------------------------------------------------------------------------------------
   $Id: bx_download_github_repo.php 
   
   Admin-seitiger Download-Endpoint für GitHub-Repository-Zips
   Authentifizierung über Admin-Session mit Filename-Validierung
   -----------------------------------------------------------------------------------------
*/

// Nur Admin-Zugriff
$gzip_off = true;
require('includes/application_top.php');

// Filename aus GET-Parameter (muss ein gültiger Repository-Zip sein)
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header("HTTP/1.0 400 Bad Request");
    die('Keine Datei angegeben');
}

$filename_input = $_GET['file'];

// Sicherheit: Nur alphanumeric + Underscore + Bindestrich + Punkt erlauben
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.zip$/', $filename_input)) {
    header("HTTP/1.0 400 Bad Request");
    die('Ungültiger Dateiname');
}

// Sicherheit: Path-Traversal verhindern
$filename_safe = basename($filename_input);

// Volle Dateipfad konstruieren
$file_path = DIR_FS_DOWNLOAD . $filename_safe;

// Prüfen ob Datei existiert
if (!file_exists($file_path) || !is_file($file_path)) {
    header("HTTP/1.0 404 Not Found");
    die('Datei nicht gefunden: ' . htmlspecialchars($filename_safe));
}

// Sicherheit: Nur aus dem Download-Ordner erlauben
$real_path = realpath($file_path);
$real_download_dir = realpath(DIR_FS_DOWNLOAD);
if ($real_path === false || strpos($real_path, $real_download_dir) !== 0) {
    header("HTTP/1.0 403 Forbidden");
    die('Zugriff verweigert');
}

// Download-Header
header("Expires: Mon, 26 Nov 1962 00:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-Type: application/zip");
header("Content-Length: " . filesize($real_path));
header("Content-Disposition: attachment; filename=\"" . $filename_safe . "\"");

// Datei ausliefern
if (function_exists('readfile_chunked')) {
    $chunksize = 1 * (1024 * 1024);
    readfile_chunked($real_path, $chunksize);
} else {
    readfile($real_path);
}

exit();
