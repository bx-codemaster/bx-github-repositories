# BX Github Repositories - Roadmap

## Milestone-Status

### Milestone M1 - Installierbar und Credential-Setup produktiv
Status: Erreicht

Ergebnis:
- Modul ist installierbar und lauffähig im Shop.
- Shopbetreiber kann GitHub App ID, Installation ID und PEM-Key im Setup-Assistenten erfassen.
- PEM wird serverseitig validiert und nur verschlüsselt gespeichert.
- Verbindungstest (JWT -> Installation Token) ist vorhanden.
- Detaillierte Shopbetreiber-Dokumentation für die Erzeugung der benötigten GitHub-Credentials liegt vor.

### Milestone M2 - Produktiv einsetzbares Modul
Status: Erreicht

Ergebnis:
- Modul ist in der aktuellen Form produktiv einsetzbar.
- Repositories koennen im Admin geladen, aktiviert und gezielt synchronisiert werden.
- ZIP-Dateien werden unter stabilem lokalem Dateinamen im Shop bereitgestellt; fehlende oder veraltete ZIPs werden im Admin sichtbar gemacht.
- Scheduled Tasks fuer den automatischen Abgleich sind registriert und auf die Intervalle hourly, daily, weekly und monthly ausgerichtet.
- Admin-UX fuer Setup und Betrieb ist vorhanden, inklusive Spinner, ZIP-Statusanzeige und sicherem Admin-Downloadfluss.
- Shopbetreiber-Handbuch steht als HTML im Manual-Tab sowie als PDF in DE und EN bereit.

## Release-Notiz M1 (Changelog/Tag)

Kurztext:
- Milestone M1 erreicht: Modul ist installierbar und das Credential-Setup produktiv nutzbar.

Tag-Beschreibung (Vorschlag):
- M1: Installierbares Grundmodul mit produktivem GitHub Credential-Setup
- Setup-Assistent fuer App ID, Installation ID und PEM-Upload vorhanden
- PEM wird serverseitig validiert und nur verschluesselt gespeichert
- Verbindungstest (JWT -> Installation Token) integriert
- Shopbetreiber-Dokumentation fuer GitHub-App-Einrichtung und Betrieb liegt vor

## Release-Notiz M2 (Changelog/Tag)

Kurztext:
- Milestone M2 erreicht: Modul ist in der aktuellen Form produktiv einsetzbar.

Tag-Beschreibung (Vorschlag):
- M2: Produktiv einsetzbares Modul fuer GitHub-Release-Synchronisation im modified-Shop
- Repository-Auswahl, manueller Sync und ZIP-Statusanzeige im Admin verfuegbar
- Sicherer Admin-Download lokaler Repository-ZIPs umgesetzt
- Scheduled Tasks fuer regelmaessige Pruefung und Synchronisation integriert
- Shopbetreiber-Handbuch in DE/EN als PDF sowie im Admin-Manual-Tab verfuegbar

## Ziel
Käufer erhalten immer die neueste Version einer stabilen Download-Datei, fuer 2 Jahre ab Kaufdatum. Bei neuer Version werden berechtigte Käufer per E-Mail informiert.

## Fachregeln
- Immer neueste Version ausliefern.
- Berechtigung endet exakt 2 Jahre nach Kaufdatum.
- Nur berechtigte Käufer erhalten Update-Informationen.
- Download-Datei im Shop bleibt unter einem stabilen lokalen Dateinamen erreichbar.
- Berechtigungspruefung orientiert sich an den gekauften Download-Positionen (orders_products_download) und dem Kaufdatum der Bestellung.

## Architekturüberblick
- Quelle: GitHub Releases API via Latest-Release-Endpunkt.
- Synchronisation: Scheduled Task im modified-Mechanismus.
- Datenhaltung: Eigene Modultabellen, keine invasive Core-Erweiterung.
- Credential-Management: App ID, Installation ID und Private Key je Shop in der Datenbank; Private Key nur verschlüsselt.
- Auslieferung: Stabiler Dateiname im Download-Verzeichnis, atomar ersetzt.
- Benachrichtigung: Queue-basierter Mailversand in Batches.
- Kein hartes Primaer-Mapping Repository -> Produkt/Attribut fuer die Auslieferung; massgeblich ist die in Bestellungen persistierte Download-Datei.

## Datenmodell

### Tabelle bx_github_repositories
Zweck: Konfiguration und Synchronisationsstatus pro Repository.

Felder:
- repositories_id (PK)
- status (0/1)
- owner_name
- repo_name
- product_id (optional, nur fuer Reporting/Backoffice)
- products_attributes_id (optional, nur fuer Reporting/Backoffice)
- release_asset_pattern
- github_token_encrypted
- local_filename_stable
- current_tag_name
- current_release_name
- current_asset_name
- current_asset_url
- current_published_at
- last_check_at
- last_success_at
- last_error_message
- check_interval_value
- check_interval_unit (m/h/d/w)
- auto_update (0/1)
- auto_notify (0/1)
- created_at
- updated_at

### Tabelle bx_github_release_log
Zweck: Historie aller erkannten und importierten Releases.

Felder:
- release_log_id (PK)
- repositories_id (FK)
- tag_name
- asset_name
- asset_url
- published_at
- downloaded_at
- file_size
- checksum_sha256
- import_status (success/error/skipped)
- error_message

### Tabelle bx_github_notifications
Zweck: Versand-Queue und Nachweis der Kundenbenachrichtigungen.

Felder:
- notification_id (PK)
- repositories_id (FK)
- orders_id
- orders_products_id
- customers_id
- customers_email_address
- tag_name
- queued_at
- sent_at
- status (queued/sent/error)
- error_message

## Scheduled Tasks

### Task 1: github_repositories_check
Intervall: Default täglich, im Admin änderbar.

Ablauf:
1. Aktive Repositories laden.
2. Intervall prüfen (fällig oder nicht).
3. GitHub Latest Release abrufen.
4. Asset per Pattern wählen.
5. Bei neuer Version Datei herunterladen:
   - In temporäre Datei streamen.
   - Optional Hash prüfen.
   - Atomar auf stabilen Dateinamen umbenennen.
6. Status und Release-Log aktualisieren.
7. Notification-Queue für berechtigte Käufer anhand gekaufter Download-Dateien (orders_products_download) füllen.

### Task 2: github_repositories_notify
Intervall: Regelmäßig, in Batches.

Ablauf:
1. Offene Queue-Einträge laden.
2. Mailversand pro Batch.
3. Status auf sent/error setzen.
4. Fehler retry-fähig protokollieren.

## Download- und Berechtigungslogik
1. Beim Kauf wird in modified die konkrete Download-Datei je Bestellposition in orders_products_download gespeichert.
2. Das Modul haelt diese Datei unter einem stabilen lokalen Dateinamen aktuell (atomarer Austausch).
3. Beim Download wird zusaetzlich geprueft, ob Kaufdatum + 2 Jahre noch nicht erreicht ist.
4. Nach Fristablauf wird Download gesperrt.

Hinweis:
- Frist kalenderbasiert als Kaufdatum plus 2 Jahre behandeln, nicht nur als 730 Tage in Sekunden.

## Admin-Maske

### Bereich 1: Übersicht
- Repository
- Stabiler Dateiname
- Aktueller Tag
- Letzter Check
- Letzter Erfolg
- Letzter Fehler
- Auto-Update
- Auto-Notify

### Bereich 2: Repository bearbeiten
- Alle Repositories der GitHub-App-Installation erfassen und als Auswahlliste anzeigen
- Credentials im Setup-Flow erfassen: App ID, Installation ID, Private-Key-Upload (PEM)
- PEM serverseitig validieren, verschlüsseln und nur verschlüsselt speichern
- Private Key nach dem Speichern nie im Klartext erneut anzeigen
- owner_name, repo_name
- Stabiler Dateiname (Auslieferungsanker)
- Asset-Pattern
- Intervall
- Token
- Schalter für Auto-Update/Auto-Notify
- Test API
- Test Download

### Bereich 3: Historie und Benachrichtigungen
- Release-Historie
- Queue-Status
- Versandfehler
- Anzahl benachrichtigter Käufer

## Credential-Setup und Speicherung (Detail)

### Ziel
- Shopbetreiber trägt App ID und Installation ID im Admin ein.
- Shopbetreiber lädt die erzeugte PEM-Datei hoch.
- Das Modul liest den PEM-Inhalt aus, validiert ihn, verschlüsselt ihn und speichert ihn in der Datenbank.

### Ablauf
1. User lädt PEM-Datei im Admin-Formular hoch.
2. Server liest den Dateiinhalt ein (kein Persistieren im Webroot).
3. Formatprüfung auf gültigen Private-Key-Block (BEGIN/END ... PRIVATE KEY).
4. Verschlüsselung mit Modul-Crypto.
5. Speicherung in TABLE_CONFIGURATION als verschlüsselter Wert.
6. Temporäre Upload-Datei wird sofort gelöscht.
7. Verbindungstest (JWT -> Installation Token) validiert die gespeicherten Werte.

### Sicherheitsanforderungen
- Nur Datei-Upload mit zusätzlicher Inhaltsvalidierung (Dateiendung allein reicht nicht).
- Größenlimit für PEM-Datei setzen.
- Kein Logging sensibler Schlüsselinhalte.
- Klartext-PEM niemals zurück an UI ausgeben.
- Bestehende Dateikonstanten nur als Migrations-Fallback, danach entfernen.

## Implementierungs-Roadmap

### Phase 1 - Modulgrundlage
Status: Erreicht
- Systemmodulstruktur und Konfigurationskeys anlegen.
- Datenbanktabellen erstellen.
- Admin-Rechte und Menüeintrag registrieren.
- Scheduled Tasks registrieren.

### Phase 2 - GitHub-Client
Status: Erreicht
- GitHub App Authentifizierung (JWT -> Installation Access Token) implementieren.
- KnpLabs GitHub-Client anbinden und Auth-Hierarchie (App zuerst, PAT-Fallback) umsetzen.
- Robuste Asset-Auswahl, Rate-Limit-Handling und End-to-End-Smoke-Test bereitstellen.

### Phase 3A - Dateisynchronisation (fachlich erreicht)
Status: Erreicht
- Fachziel erreicht: ZIP-Datei wird pro Repository unter stabilem lokalem Dateinamen aktualisiert und atomar ersetzt.
- Aktuelle Umsetzung liegt derzeit im Admin-Controller (manueller Sync), noch nicht in separaten Worker-/Service-Schichten.

### Phase 3B - Dateisynchronisation (technische Konsolidierung)
Status: Teilweise erreicht
- Stream-Download in temporäre Datei.
- ZIP im Shop-Ordner download speichern (stabiler lokaler Dateiname).
- Atomarer Austausch der stabilen Zieldatei im Zielordner.
- Logging und Rollback bei Fehlern.

### Phase 4 - Berechtigungsprüfung 2 Jahre
- Zusatzpruefung im Downloadfluss fuer orders_products_download integrieren.
- Klare Fehlermeldung bei abgelaufener Berechtigung.

### Phase 5 - Kundenbenachrichtigung
- Queue füllen bei neuem Tag.
- Batchversand mit Retry.
- DE/EN Mailtexte.

### Phase 6 - Admin-UI
Status: Weitgehend erreicht
- Listenansicht, Detailmaske, Historie.
- Setup-Flow: Repositories der Installation einlesen und gezielt aktivieren/deaktivieren.
- Setup-Flow: App ID, Installation ID und PEM-Datei im Admin erfassen.
- PEM-Upload verarbeiten (Validierung -> Verschlüsselung -> DB-Speicherung).
- DB-first-Konfigurationslesung für Auth aktivieren (Dateikonstanten nur temporärer Fallback).
- Manuelle Aktionen: Check jetzt, Download jetzt, Testmail.

### Phase 7 - Hardening
Status: Teilweise erreicht
- Locking gegen parallele Task-Ausführung.
- Monitoring, Logrotation, Fehleralarme.
- Lasttests mit großen Dateien.
- Security-Review für Token und Pfadvalidierung.

## Technische Umsetzungs-Checkliste (Dateipfade je Phase)

### Phase 1 - Modulgrundlage
- [x] Systemmodul-Klasse anlegen: src/admin/includes/modules/system/bx_github_repositories.php
- [x] Modul-Adminseite anlegen: src/admin/bx_github_repositories.php
- [x] Datenbanktabellen-Definitionen anlegen: src/admin/includes/extra/database_tables/bx_github_repositories.php
- [x] Dateinamen-Konstanten anlegen: src/admin/includes/extra/filenames/bx_github_repositories.php
- [x] Menüeintrag anlegen: src/admin/includes/extra/menu/bx_github_repositories.php
- [x] Sprachdateien DE/EN anlegen: src/lang/german/extra/admin/bx_github_repositories.php, src/lang/english/extra/admin/bx_github_repositories.php

### Phase 2 - GitHub-Client
- [x] Auth-Serviceklassen anlegen: src/admin/includes/classes/bx_github_repositories_client_factory.php, src/admin/includes/classes/bx_github_repositories_app_manager.php, src/admin/includes/classes/bx_github_repositories_jwt_provider.php
- [x] Token-Exchange und Fehlerhandling für App-Authentifizierung implementieren: src/admin/includes/classes/bx_github_repositories_app_manager.php
- [x] Auth-Flow und Token-Lifecycle (JWT -> Installation Token, Cache, PAT-Fallback) implementieren: src/admin/includes/classes/bx_github_repositories_app_manager.php, src/admin/includes/classes/bx_github_repositories_client_factory.php
- [x] Live-Smoke-Test für Authentifizierung ergänzen: src/tests/bx_github_repositories_auth_smoke_test.php

### Phase 3A - Dateisynchronisation (fachlich erreicht)
- [x] ZIP-Import unter stabilem Dateinamen inkl. temporaerer Datei und atomarem Austausch implementiert (derzeit im Admin-Controller): src/admin/bx_github_repositories.php

### Phase 3B - Dateisynchronisation (technische Konsolidierung)
- [ ] Download-Service anlegen (Temp-Datei, atomares Rename in den Shop-Ordner download): src/admin/includes/classes/bx_github_repositories_downloader.php
- [ ] Dateisystem-Helfer anlegen (Locking/Pfadprüfung): src/admin/includes/classes/bx_github_repositories_fs.php
- [ ] Import-Logik in Worker integrieren (ZIP-Asset aus Release in download speichern): src/api/scheduled_tasks/modules/github_repositories_check.php

### Phase 4 - 2-Jahres-Berechtigung
- [ ] Download-Gate in den Downloadfluss einhängen: src/includes/extra/modules/download/download_before_send/bx_github_repositories.php
- [ ] Berechtigungsprüfung (Kaufdatum + 2 Jahre) als Helper kapseln: src/includes/classes/bx_github_repositories_entitlement.php
- [ ] Fehlermeldungstexte DE/EN ergänzen: src/lang/german/extra/bx_github_repositories.php, src/lang/english/extra/bx_github_repositories.php

### Phase 5 - Kundenbenachrichtigung
- [ ] Notification-Queue-Service anlegen: src/includes/classes/bx_github_repositories_notify.php
- [ ] Scheduled-Task für Mailversand anlegen: src/api/scheduled_tasks/modules/github_repositories_notify.php
- [ ] Mail-Templates DE/EN anlegen: src/templates/tpl_modified_nova/admin/mail/german/bx_github_repositories_update_mail.txt, src/templates/tpl_modified_nova/admin/mail/english/bx_github_repositories_update_mail.txt

### Phase 6 - Admin-UI
- [ ] Listenansicht/Filter in Adminseite implementieren: src/admin/bx_github_repositories.php
- [x] Setup-Flow fuer Repository-Erfassung und Aktivierung/Deaktivierung vorhanden: src/admin/bx_github_repositories.php
- [x] Upload-Feld für PEM-Datei ergänzen und Multipart-Handling implementieren: src/admin/bx_github_repositories.php
- [x] Serverseitige PEM-Validierung und Verschlüsselung beim Speichern ergänzen: src/admin/bx_github_repositories.php, src/admin/includes/classes/bx_github_repositories_crypto.php
- [x] DB-Konfigurationskeys für App ID, Installation ID und verschlüsselten Private Key ergänzen: src/admin/includes/modules/system/bx_github_repositories.php
- [x] Auth-Layer auf DB-first umstellen (Fallback nur für Migration): src/admin/includes/classes/bx_github_repositories_app_manager.php
- [x] Migrationsschritt von Dateikonstanten in DB dokumentieren und umsetzen: src/includes/extra/configure/bx_github_repositories.php, docs/INSTALLATION_SHOPBETREIBER.md
- [ ] Historie und Queue-Status integrieren: src/admin/bx_github_repositories.php

### Phase 7 - Hardening und Betrieb
- [x] Scheduled-Task-Registrierung in Install/Remove ergänzt: src/admin/includes/modules/system/bx_github_repositories.php
- [ ] Einheitliches Logging ergänzen: src/includes/classes/bx_github_repositories_logger.php
- [ ] Betriebsdokumentation ergänzen: docs/ROADMAP.md, docs/OPERATIONS.md

## Offene Entscheidungen
- Exakte Asset-Auswahlregel bei mehreren ZIP-Dateien.
- E-Mail-Inhalt und rechtliche Einordnung für Bestandskunden-Mails.
- Verhalten bei GitHub-Repositories ohne Releases.
- Ob optionale Felder product_id/products_attributes_id langfristig entfernt werden sollen.

## Abnahme-Checkliste
- Neues Release wird erkannt und importiert.
- Stabile Download-Datei wird atomar ersetzt.
- Berechtigte Käufer können neueste Version laden.
- Nach 2 Jahren ist Download gesperrt.
- E-Mail-Queue und Versand funktionieren inklusive Fehlerfällen.
- Scheduled Tasks laufen ohne Doppelverarbeitung.
