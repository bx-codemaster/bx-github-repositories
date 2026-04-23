# BX GitHub Repositories

## Handbuch für Shopbetreiber

## Ziel dieser Anleitung
Dieses Handbuch beschreibt die Einrichtung des Moduls BX GitHub Repositories für den Produktivbetrieb.

Nach Abschluss der Schritte kann der Shop:
- auf die eigenen GitHub-Repositories zugreifen,
- Release-ZIP-Dateien abrufen,
- ZIP-Dateien im Shop-Ordner `download` speichern,
- und nur die für den jeweiligen Shop freigegebenen Repositories verwenden.

---

## Ergebnis am Ende der Einrichtung
Folgende drei Werte müssen erfolgreich eingerichtet sein:
1. GitHub App ID
2. Installation ID
3. Private Key (PEM)

Zusatz:
- Verbindungstest im Modul ist erfolgreich.
- Repositories der Installation können geladen und ausgewählt werden.

---

## Voraussetzungen
1. GitHub-Account mit Rechten zum Anlegen und Installieren einer GitHub App.
2. Admin-Zugriff auf den Shop.
3. Schreibrechte auf den Shop-Ordner `download`.
4. Zugriff auf die Repositories, die später synchronisiert werden sollen.

---

## Schritt 1: GitHub App anlegen
1. In GitHub oben rechts auf das Profil klicken.
2. `Settings` öffnen.
3. `Developer settings` öffnen.
4. `GitHub Apps` auswählen.
5. `New GitHub App` klicken.
6. Einen eindeutigen Namen vergeben, z. B. `shopname-release-sync`.
7. `Homepage URL` eintragen (z. B. Shop-URL).
8. `Callback URL` nur setzen, wenn Benutzer-Login über GitHub benötigt wird.
9. Wenn keine Webhooks benötigt werden: Webhooks deaktivieren.
10. Berechtigungen minimal setzen:
   - Repository permission `Metadata`: `Read-only`
   - Repository permission `Contents`: `Read-only`
11. Unter Installation festlegen, wo die App installierbar ist.
12. App speichern.

Hinweis:
So wenig Rechte wie möglich vergeben (Least Privilege).

---

## Schritt 2: GitHub App installieren
1. In der GitHub App auf `Install App` gehen.
2. Ziel-Account oder Ziel-Organisation auswählen.
3. Repository-Zugriff auf `Selected repositories` setzen.
4. Nur die Repositories auswählen, die der Shop verwenden soll.
5. Installation bestätigen.

Wichtig:
Diese Auswahl bestimmt, welche Repositories der Shop technisch überhaupt sehen darf.

---

## Schritt 3: App ID ermitteln
1. In den GitHub-App-Einstellungen die `App ID` ablesen.
2. Wert notieren.

---

## Schritt 4: Installation ID ermitteln
1. In die Installationsansicht der App wechseln.
2. Die Installations-ID aus der URL oder Installationsansicht entnehmen.
3. Wert notieren.

Beispiel:
Bei einer URL mit `.../settings/installations/12345678` ist `12345678` die Installation ID.

---

## Schritt 5: Private Key (PEM) erzeugen
1. In der GitHub-App-Konfiguration den Bereich `Private keys` öffnen.
2. `Generate a private key` klicken.
3. Die heruntergeladene PEM-Datei sicher speichern.
4. Inhalt der PEM-Datei für die Modulkonfiguration bereithalten.

Wichtig:
- Private Keys nicht per E-Mail versenden.
- Private Key niemals in Tickets oder Chats posten.
- Bei Verdacht auf Leak sofort neuen Key erzeugen und alten widerrufen.

---

## Schritt 6: Daten im Shop-Modul eintragen
1. Im Shop-Admin das Modul BX GitHub Repositories öffnen.
2. Folgende Felder befüllen:
   - `GitHub App ID`
   - `Installation ID`
   - `Private Key (PEM)`
3. `Verbindung testen` ausführen.
4. Bei Erfolg `Einstellungen speichern`.

Empfehlung:
Private Key nach dem Speichern nur verschlüsselt ablegen und nicht erneut im Klartext anzeigen.

---

## Schritt 7: Repository-Liste laden und Auswahl festlegen
1. Nach erfolgreichem Verbindungstest die Repository-Liste laden.
2. Die gewünschten Repositories aktivieren.
3. Pro Repository Produkt-/Attribut-Zuordnung festlegen.
4. Release-Asset-Regel (ZIP) definieren.

Importziel:
Das gewählte ZIP-Asset wird im Shop-Ordner `download` gespeichert.

---

## Schritt 8: Funktionstest
1. Für ein Repository einen manuellen Testlauf ausführen.
2. Prüfen, ob die ZIP-Datei in `download` vorhanden ist.
3. Prüfen, ob Dateiname und Zuordnung korrekt sind.
4. Danach den automatischen Task aktivieren.

---

## Betrieb in mehreren Shops (Mandantentrennung)
Damit Shop A nicht auf Repositories von Shop B zugreift:
1. Pro Shop eine eigene GitHub-App-Installation verwenden.
2. Pro Installation nur `Selected repositories` freigeben.
3. Keine gemeinsamen PATs oder gemeinsamen Private Keys über mehrere Kunden nutzen.
4. Zugangsdaten pro Shop getrennt speichern.

Kurzregel:
Jeder Shop hat seine eigene Installation und eigene Repository-Freigabe.

---

## Typische Fehlerbilder und Lösungen

### Fehler 401 / 403 beim Verbindungstest
ögliche Ursache:
- App ID, Installation ID und PEM gehören nicht zusammen.
- App ist nicht für das Ziel-Repository installiert.
- Berechtigungen sind zu knapp.

Lösung:
1. Werte erneut prüfen.
2. Installation prüfen.
3. Permissions für den Anwendungsfall prüfen.

### Repository-Liste ist leer
ögliche Ursache:
- Installation hat keine Repositories freigegeben.
- Falscher Account oder falsche Organisation.

Lösung:
1. In GitHub die Installation aufrufen.
2. `Selected repositories` kontrollieren.
3. Gewuenschte Repositories freigeben.

### ZIP-Import funktioniert nicht
Mögliche Ursache:
- Release-Asset passt nicht zum Muster.
- Dateirechte im Ordner `download` fehlen.

Lösung:
1. Asset-Name und Pattern prüfen.
2. Schreibrechte auf `download` prüfen.
3. Testlauf erneut starten.

---

## Sicherheits-Checkliste vor Go-Live
1. Nur minimale Permissions aktiv.
2. Installation auf `Selected repositories` gesetzt.
3. Keine sensiblen Daten im Klartext in Logs.
4. Private Key geschützt gespeichert.
5. Testlauf erfolgreich.
6. Download-Datei korrekt im Ordner `download`.
7. Geplante Task-Ausfuehrung aktiviert.

---

## Wartung und Rotation
1. Private Key regelmäßig rotieren.
2. Nicht mehr benötigte Repositories aus der Installation entfernen.
3. Nach Rechteänderungen Verbindungstest erneut ausführen.
4. Fehlerprotokolle regelmäßig kontrollieren.
