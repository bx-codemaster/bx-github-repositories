# BX Github Repositories - Roadmap

## Ziel
Käufer erhalten immer die neueste Version eines zugewiesenen Download-Produkts, für 2 Jahre ab Kaufdatum. Bei neuer Version werden berechtigte Käufer per E-Mail informiert.

## Fachregeln
- Immer neueste Version ausliefern.
- Berechtigung endet exakt 2 Jahre nach Kaufdatum.
- Nur berechtigte Käufer erhalten Update-Informationen.
- Download-Datei im Shop bleibt unter einem stabilen lokalen Dateinamen erreichbar.

## Architekturüberblick
- Quelle: GitHub Releases API via Latest-Release-Endpunkt.
- Synchronisation: Scheduled Task im modified-Mechanismus.
- Datenhaltung: Eigene Modultabellen, keine invasive Core-Erweiterung.
- Auslieferung: Stabiler Dateiname im Download-Verzeichnis, atomar ersetzt.
- Benachrichtigung: Queue-basierter Mailversand in Batches.

## Datenmodell

### Tabelle bx_github_repositories
Zweck: Konfiguration und Synchronisationsstatus pro Repository.

Felder:
- repositories_id (PK)
- status (0/1)
- owner_name
- repo_name
- product_id
- products_attributes_id
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
7. Notification-Queue für berechtigte Käufer füllen.

### Task 2: github_repositories_notify
Intervall: Regelmäßig, in Batches.

Ablauf:
1. Offene Queue-Einträge laden.
2. Mailversand pro Batch.
3. Status auf sent/error setzen.
4. Fehler retry-fähig protokollieren.

## Download- und Berechtigungslogik
1. Produktattribut verweist auf stabilen lokalen Dateinamen.
2. Beim Download wird zusätzlich geprüft:
   - Mapping Bestellung zu Repository vorhanden.
   - Kaufdatum + 2 Jahre noch nicht erreicht.
3. Nur dann wird Download ausgeliefert.
4. Nach Fristablauf wird Download gesperrt.

Hinweis:
- Frist kalenderbasiert als Kaufdatum plus 2 Jahre behandeln, nicht nur als 730 Tage in Sekunden.

## Admin-Maske

### Bereich 1: Übersicht
- Repository
- Produkt/Attribut
- Aktueller Tag
- Letzter Check
- Letzter Erfolg
- Letzter Fehler
- Auto-Update
- Auto-Notify

### Bereich 2: Repository bearbeiten
- owner_name, repo_name
- Produkt und Download-Attribut
- Asset-Pattern
- Stabiler Dateiname
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

## Implementierungs-Roadmap

### Phase 1 - Modulgrundlage
- Systemmodulstruktur und Konfigurationskeys anlegen.
- Datenbanktabellen erstellen.
- Admin-Rechte und Menüeintrag registrieren.
- Scheduled Tasks registrieren.

### Phase 2 - GitHub-Client
- GitHub App Authentifizierung (JWT -> Installation Access Token) implementieren.
- KnpLabs GitHub-Client anbinden und Auth-Hierarchie (App zuerst, PAT-Fallback) umsetzen.
- Robuste Asset-Auswahl, Rate-Limit-Handling und End-to-End-Smoke-Test bereitstellen.

### Phase 3 - Dateisynchronisation
- Stream-Download in temporäre Datei.
- Atomarer Austausch der stabilen Zieldatei.
- Logging und Rollback bei Fehlern.

### Phase 4 - Berechtigungsprüfung 2 Jahre
- Zusatzprüfung im Downloadfluss integrieren.
- Klare Fehlermeldung bei abgelaufener Berechtigung.

### Phase 5 - Kundenbenachrichtigung
- Queue füllen bei neuem Tag.
- Batchversand mit Retry.
- DE/EN Mailtexte.

### Phase 6 - Admin-UI
- Listenansicht, Detailmaske, Historie.
- Manuelle Aktionen: Check jetzt, Download jetzt, Testmail.

### Phase 7 - Hardening
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
- [x] Sprachdateien DE/EN anlegen: src/lang/german/extra/bx_github_repositories.php, src/lang/english/extra/bx_github_repositories.php

### Phase 2 - GitHub-Client
- [x] Auth-Serviceklassen anlegen: src/admin/includes/classes/bx_github_repositories_client_factory.php, src/admin/includes/classes/bx_github_repositories_app_manager.php, src/admin/includes/classes/bx_github_repositories_jwt_provider.php
- [x] Token-Exchange und Fehlerhandling für App-Authentifizierung implementieren: src/admin/includes/classes/bx_github_repositories_app_manager.php
- [x] Auth-Flow und Token-Lifecycle (JWT -> Installation Token, Cache, PAT-Fallback) implementieren: src/admin/includes/classes/bx_github_repositories_app_manager.php, src/admin/includes/classes/bx_github_repositories_client_factory.php
- [x] Live-Smoke-Test für Authentifizierung ergänzen: src/tests/bx_github_repositories_auth_smoke_test.php

### Phase 3 - Dateisynchronisation
- [ ] Download-Service anlegen (Temp-Datei, atomares Rename): src/admin/includes/classes/bx_github_repositories_downloader.php
- [ ] Dateisystem-Helfer anlegen (Locking/Pfadprüfung): src/admin/includes/classes/bx_github_repositories_fs.php
- [ ] Import-Logik in Worker integrieren: src/api/scheduled_tasks/modules/github_repositories_check.php

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
- [ ] Bearbeitungsformular für Repository-Mapping hinzufügen: src/admin/bx_github_repositories.php
- [ ] Historie und Queue-Status integrieren: src/admin/bx_github_repositories.php

### Phase 7 - Hardening und Betrieb
- [ ] Scheduled-Task-Registrierung in Install/Remove ergänzen: src/admin/includes/modules/system/bx_github_repositories.php
- [ ] Einheitliches Logging ergänzen: src/includes/classes/bx_github_repositories_logger.php
- [ ] Betriebsdokumentation ergänzen: docs/ROADMAP.md, docs/OPERATIONS.md

## Offene Entscheidungen
- Exakte Asset-Auswahlregel bei mehreren ZIP-Dateien.
- E-Mail-Inhalt und rechtliche Einordnung für Bestandskunden-Mails.
- Verhalten bei GitHub-Repositories ohne Releases.

## Abnahme-Checkliste
- Neues Release wird erkannt und importiert.
- Stabile Download-Datei wird atomar ersetzt.
- Berechtigte Käufer können neueste Version laden.
- Nach 2 Jahren ist Download gesperrt.
- E-Mail-Queue und Versand funktionieren inklusive Fehlerfällen.
- Scheduled Tasks laufen ohne Doppelverarbeitung.
