<?php
/* -----------------------------------------------------------------------------------------
	$Id: /lang/german/extra/admin/bx_github_repositories.php 1000 2026-04-23 12:00:00Z benax $
	
	modified eCommerce Shopsoftware
	http://www.modified-shop.org
	
	Copyright (c) 2009 - 2013 [www.modified-shop.org]
	-----------------------------------------------------------------------------------------
	Released under the GNU General Public License
	---------------------------------------------------------------------------------------*/

define('BX_GITHUB_REPOSITORIES_HEADING_TITLE', 'BX GitHub Repositories');
define('BX_GITHUB_REPOSITORIES_HEADING_SUB_TITLE', 'Repository- und Release-Verwaltung');
define('BX_GITHUB_REPOSITORIES_TEXT_INTRO', 'Dieses Modul synchronisiert GitHub-Releases mit Download-Produkten.');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_HEADING', 'Setup-Assistent');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_DESCRIPTION', 'Dieser Bereich bereitet die spätere Admin-UI für die kundenspezifische Einrichtung vor.');
define('BX_GITHUB_REPOSITORIES_TEXT_SETUP_GOAL', 'Ziel ist die Erfassung eigener GitHub-App-Daten pro Shop und die Auswahl der zu synchronisierenden Repositories.');
define('BX_GITHUB_REPOSITORIES_LABEL_APP_ID', 'GitHub App ID');
define('BX_GITHUB_REPOSITORIES_LABEL_INSTALLATION_ID', 'Installation ID');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_UPLOAD', 'PEM-Datei hochladen');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY', 'Private Key (PEM)');
define('BX_GITHUB_REPOSITORIES_LABEL_PRIVATE_KEY_STATUS', 'Status Private Key');
define('BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_UPLOAD_HELP', 'PEM-Datei direkt hochladen. Der Inhalt wird serverseitig validiert und verschlüsselt gespeichert.');
define('BX_GITHUB_REPOSITORIES_TEXT_PRIVATE_KEY_HINT', 'Hinweis: Der Key wird nach dem Speichern nicht im Klartext angezeigt. Sie sehen den verschlüsselten String.');
define('BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_STORED', 'In der Datenbank hinterlegt (verschlüsselt).');
define('BX_GITHUB_REPOSITORIES_STATUS_PRIVATE_KEY_MISSING', 'Noch nicht hinterlegt.');
define('BX_GITHUB_REPOSITORIES_BUTTON_TEST_CONNECTION', 'Verbindung testen');
define('BX_GITHUB_REPOSITORIES_BUTTON_SAVE_SETTINGS', 'Einstellungen speichern');
define('BX_GITHUB_REPOSITORIES_BUTTON_LOAD_REPOSITORIES', 'Repositories laden/aktualisieren');
define('BX_GITHUB_REPOSITORIES_BUTTON_SAVE_REPOSITORY_SELECTION', 'Repository-Auswahl speichern');
define('BX_GITHUB_REPOSITORIES_TAB_SETUP', 'Setup-Assistent');
define('BX_GITHUB_REPOSITORIES_TAB_REPOSITORIES', 'Repository-Auswahl');
define('BX_GITHUB_REPOSITORIES_LABEL_REPOSITORY', 'Repository');
define('BX_GITHUB_REPOSITORIES_LABEL_LOCAL_FILENAME', 'Lokaler Dateiname');
define('BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_HEADING', 'Repository-Auswahl');
define('BX_GITHUB_REPOSITORIES_TEXT_REPOSITORY_SELECTION_INTRO', 'Nach erfolgreichem Verbindungstest werden alle Repositories der Installation geladen und hier zur Auswahl angeboten.');
define('BX_GITHUB_REPOSITORIES_TEXT_NO_REPOSITORIES_LOADED', 'Noch keine Daten geladen. Bitte zuerst die Verbindung testen.');
define('BX_GITHUB_REPOSITORIES_TEXT_IMPORT_TARGET', 'Importziel: Das ausgewählte ZIP-Asset wird später im Shop-Ordner &quot;download&quot; unter stabilem Dateinamen gespeichert.');
define('BX_GITHUB_REPOSITORIES_TEXT_UNKNOWN_GITHUB_RESPONSE', 'Unbekannte Antwort von GitHub');
define('BX_GITHUB_REPOSITORIES_ERROR_REPOSITORY_LOAD_FAILED', 'Repositories konnten nicht geladen werden (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_JWT_CREATION_FAILED', 'JWT-Erstellung fehlgeschlagen: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_CONNECTION_TEST_FAILED', 'Verbindungstest fehlgeschlagen (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_APP_ID', 'GitHub App ID ist ungültig.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_INSTALLATION_ID', 'Installation ID ist ungültig.');
define('BX_GITHUB_REPOSITORIES_ERROR_UPLOAD_FAILED', 'PEM-Datei konnte nicht hochgeladen werden (Upload-Fehler %d).');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_FILE_SIZE', 'PEM-Datei ist leer oder größer als 32 KB.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_UPLOAD', 'PEM-Datei-Upload ist ungültig.');
define('BX_GITHUB_REPOSITORIES_ERROR_FILE_READ_FAILED', 'PEM-Datei konnte nicht gelesen werden.');
define('BX_GITHUB_REPOSITORIES_ERROR_INVALID_PRIVATE_KEY', 'Die hochgeladene PEM-Datei enthält keinen gültigen Private Key.');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_PRIVATE_KEY_UPLOAD', 'Bitte eine PEM-Datei hochladen.');
define('BX_GITHUB_REPOSITORIES_ERROR_ENCRYPTION_FAILED', 'Verschlüsselung fehlgeschlagen: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_CONNECTION_SETTINGS', 'App ID und Installation ID sind nicht konfiguriert. Bitte zuerst Einstellungen speichern.');
define('BX_GITHUB_REPOSITORIES_ERROR_DECRYPTION_FAILED', 'Private Key konnte nicht entschlüsselt werden: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_MISSING_STORED_PRIVATE_KEY', 'Kein Private Key gespeichert. Bitte zuerst Einstellungen speichern.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_CONNECTION_TEST', 'Verbindung erfolgreich. Installation Token wurde erzeugt.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SETTINGS_SAVED', 'Einstellungen wurden gespeichert. Der Private Key ist nur verschlüsselt in der Datenbank abgelegt.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SETTINGS_UPDATED', 'Folgende Einstellungen wurden gespeichert: %s');
define('BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORIES_LOADED', 'Repositories synchronisiert: %d neu, %d aktualisiert.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_REPOSITORY_SELECTION_SAVED', 'Repository-Auswahl gespeichert. Aktiv: %d.');
define('BX_GITHUB_REPOSITORIES_INFO_NO_SETTINGS_CHANGED', 'Keine Änderungen erkannt. Es wurden keine Einstellungen gespeichert.');
define('BX_GITHUB_REPOSITORIES_INFO_DEACTIVATED_FILES_REMOVED', 'ZIP-Dateien deaktivierter Repositories gelöscht: %d.');
define('BX_GITHUB_REPOSITORIES_INFO_SYNC_SKIPPED', 'Download übersprungen (kein neuer Tag): %d.');
define('BX_GITHUB_REPOSITORIES_WARNING_FILE_DELETE_FAILED', 'Datei konnte nicht gelöscht werden: %s');
define('BX_GITHUB_REPOSITORIES_BUTTON_SYNC_RELEASES', 'Releases herunterladen');
define('BX_GITHUB_REPOSITORIES_LABEL_VERSION', 'Version');
define('BX_GITHUB_REPOSITORIES_LABEL_LAST_CHECK', 'Letzter Check');
define('BX_GITHUB_REPOSITORIES_INFO_NO_ACTIVE_REPOSITORIES', 'Keine aktiven Repositories ausgewählt.');
define('BX_GITHUB_REPOSITORIES_SUCCESS_SYNC_COMPLETED', 'Synchronisation abgeschlossen: %d erfolgreich, %d Fehler.');
define('BX_GITHUB_REPOSITORIES_ERROR_NO_RELEASE_FOUND', 'Kein öffentlicher Release für dieses Repository gefunden.');
define('BX_GITHUB_REPOSITORIES_ERROR_NO_MATCHING_ASSET', 'Kein passendes Asset im Release gefunden.');
define('BX_GITHUB_REPOSITORIES_ERROR_TEMP_FILE_CREATE', 'Temporäre Datei konnte nicht erstellt werden: %s');
define('BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_FAILED', 'Download fehlgeschlagen (HTTP %d): %s');
define('BX_GITHUB_REPOSITORIES_ERROR_DOWNLOAD_EMPTY', 'Die heruntergeladene Datei ist leer.');
define('BX_GITHUB_REPOSITORIES_ERROR_FILE_RENAME', 'Datei konnte nicht ins Zielverzeichnis verschoben werden: %s');
