# Server Monitor

Standalone Monitoring-Dashboard fuer Shared Hosting mit PHP, PDO und MySQL/MariaDB. Die Anwendung verwaltet Website-, TCP-Service- und Ping-Checks, protokolliert Abfragen, versendet optionale E-Mail-Benachrichtigungen und stellt konfigurierbare Public Status Pages bereit.

## Anforderungen

- PHP 8.5 kompatible Umgebung
- MySQL oder MariaDB
- PDO MySQL Extension
- cURL Extension fuer Website-Checks
- Keine Composer-Abhaengigkeiten
- Keine buildpflichtigen Frontend-Abhaengigkeiten

## Installation

1. Dateien auf den Webspace hochladen.
2. Datenbank anlegen.
3. `schema.sql` importieren oder `install.php` die Tabellen automatisch anlegen lassen.
4. `config.php.sample` nach `config.php` kopieren und Datenbankdaten, Zeitzone sowie Cron-Token eintragen.
5. `install.php` im Browser oeffnen und den Admin-User anlegen.
6. `install.php` danach vom Server loeschen.
7. Dashboard oeffnen, unter `Einstellungen` den Cron-Token pruefen oder rotieren und die angezeigte Cron-URL kopieren.
8. Rechte pruefen: `app/`, `config.php` und `schema.sql` duerfen nicht direkt aus dem Web erreichbar sein.
9. Cronjob beim Hoster einrichten, z. B. alle 1 bis 5 Minuten.

## Public Status Pages

Unter `Einstellungen` koennen mehrere Public Pages angelegt werden. Jede Public Page bekommt automatisch einen zufaelligen Token und ist unter folgender Adresse erreichbar:

```text
https://deine-domain.tld/status/<random-token>
```

Falls Apache-Rewrites nicht aktiv sind, funktioniert alternativ:

```text
https://deine-domain.tld/public.php?token=<random-token>
```

Pro Public Page koennen Titel, Beschreibung, Badge, Theme, Akzentfarbe, Footer-Hinweis, sichtbare Kennzahlen und die zugewiesenen Dienste gepflegt werden. Jede Public Page zeigt eigene Statuskarten, Service-Liste, letzte Ereignisse und Graphen fuer Latenz sowie Status-Mix.

## Admin-Funktionen

- Session-basierter Login und Logout
- Passwort-Hashing mit `password_hash()` und `password_verify()`
- CSRF-Schutz fuer schreibende Aktionen
- Prepared Statements via PDO
- Website-, TCP-Service- und Ping-Checks
- Filterbare, sortierbare und durchsuchbare Tabellen
- Dashboard-Graphen und Activity-Protokoll
- Latenzgraphen mit ms-/Zeit-Skala und Mouseover-Latenz
- E-Mail-Benachrichtigungen mit Warnschwelle
- Mehrere Public Status Pages mit Dienstzuweisung
- Cron-Control-Center mit Token-Rotation, Health-Status, Laufprotokoll, Retry-Regeln und Wartungsfenster

## Cronjob

Der Cronjob prueft alle aktivierten und faelligen Dienste. Jeder Dienst hat ein eigenes Check-Intervall in Minuten. Wenn ein Dienst noch nicht faellig ist, wird er in diesem Cronlauf uebersprungen.

```text
https://deine-domain.tld/cron/status.cron.php?token=DEIN_TOKEN
```

Der Token kann im Dashboard unter `Einstellungen` gepflegt und rotiert werden. Falls noch kein GUI-Token gesetzt wurde, wird als Fallback der Token aus `config.php` unter `cron.token` akzeptiert.

Optional kann JSON ausgegeben werden:

```text
https://deine-domain.tld/cron/status.cron.php?token=DEIN_TOKEN&format=json
```

Im Dashboard unter `Einstellungen` kann der Cronjob gepflegt werden:

- Cron aktivieren oder deaktivieren
- Cron-Token rotieren und Cron-URL ablesen
- Lock-Dauer, maximale Checks pro Lauf und Health-Warnschwelle einstellen
- Retry-Anzahl und Retry-Pause konfigurieren
- Default-Timeout fuer neue Checks einstellen
- Alert-Limit pro Lauf setzen, `0` bedeutet unbegrenzt
- Wartungsfenster pflegen, in dem Benachrichtigungen pausieren
- letzten Cronlauf mit Dauer, geprueften Zielen, Fehlern und Status sehen
- Check-Intervall pro Dienst im Check-Dialog setzen

### Cron-Optionen

- `Cron aktiv`: Schaltet geplante Pruefungen global an oder aus.
- `Cron-Token`: Geheimnis fuer den Cron-Endpunkt. Bei Rotation muss die Cronjob-URL beim Hoster aktualisiert werden.
- `Lock-Dauer`: Verhindert parallele Cronlaeufe, falls ein Lauf haengen bleibt oder zu lange dauert.
- `Max. Checks pro Lauf`: Begrenzt die Last pro Cronaufruf auf Shared Hosting.
- `Retries je Fehler`: Wiederholt fehlgeschlagene Checks, bevor der Ausfall gespeichert wird.
- `Retry-Pause`: Wartezeit zwischen Retry-Versuchen.
- `Default Timeout`: Vorgabewert fuer neue Dienste; bestehende Dienste behalten ihren eigenen Timeout.
- `Max. Alerts pro Lauf`: Begrenzt Mail-Benachrichtigungen pro Cronlauf.
- `Health Warnung nach Minuten`: Markiert den Cron im Dashboard als ueberfaellig, wenn er laenger nicht erfolgreich lief.
- `Wartungsfenster`: Pausiert Benachrichtigungen in einem Zeitfenster, Checks laufen aber weiter.

### Dienst-Intervalle

Im Dialog `Neuer Check` oder `Check bearbeiten` gibt es das Feld `Check-Intervall Minuten`. Der Cron prueft einen Dienst erst wieder, wenn seit `last_checked_at` mindestens dieses Intervall vergangen ist. So koennen kritische Dienste engmaschig und weniger wichtige Dienste seltener geprueft werden.

## Cache

`dashboard.php` und `public.php` laden `assets/app.css` und `assets/app.js` mit einem zufaelligen Cache-Busting-Parameter. Dadurch werden CSS und JavaScript bei jedem Seitenaufruf frisch angefordert.

## Aenderungsnotizen

- Mehrere Public Status Pages mit zufaelligen Token-URLs ergaenzt.
- Dienstzuweisung pro Public Page in der Settings-UI ergaenzt.
- Graphen auf Public Status Pages ergaenzt.
- Skalen und Mouseover-Latenz fuer Dashboard- und Public-Page-Graphen ergaenzt.
- Anwendungstitel auf `Server Monitor` vereinheitlicht und sichtbare Kurzlogos entfernt.
- Alte Kurznamen aus sichtbarer UI, Session-Default und Legacy-Konfiguration entfernt.
- Bestehende `config.php`-Installationen mit altem App-Namen werden automatisch auf `Server Monitor` normalisiert.
- Cron-Control-Center, Token-Rotation, Laufstatus, Retries, Alert-Limit, Wartungsfenster und Check-Intervall pro Dienst ergaenzt.
- README um Cron-Konfiguration, Token-Fallback, JSON-Ausgabe und Dienst-Intervalle erweitert.
- Zufallsbasiertes Cache-Busting fuer CSS und JavaScript auf Dashboard und Public Status Pages ergaenzt.
- Activity-Seite als detailliertes, suchbares, filterbares und sortierbares Protokoll ausgebaut.
