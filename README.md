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
7. Cronjob einrichten: `https://deine-domain.tld/cron/status.cron.php?token=DEIN_TOKEN`
8. Rechte pruefen: `app/`, `config.php` und `schema.sql` duerfen nicht direkt aus dem Web erreichbar sein.
9. Dashboard ueber `index.php` oeffnen.

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
- E-Mail-Benachrichtigungen mit Warnschwelle
- Mehrere Public Status Pages mit Dienstzuweisung

## Cronjob

Der Cronjob prueft alle aktivierten Dienste:

```text
https://deine-domain.tld/cron/status.cron.php?token=DEIN_TOKEN
```

Der Token steht in `config.php` unter `cron.token`.

## Cache

`dashboard.php` und `public.php` laden `assets/app.css` und `assets/app.js` mit einem zufaelligen Cache-Busting-Parameter. Dadurch werden CSS und JavaScript bei jedem Seitenaufruf frisch angefordert.

## Aenderungsnotizen

- Mehrere Public Status Pages mit zufaelligen Token-URLs ergaenzt.
- Dienstzuweisung pro Public Page in der Settings-UI ergaenzt.
- Graphen auf Public Status Pages ergaenzt.
- Zufallsbasiertes Cache-Busting fuer CSS und JavaScript auf Dashboard und Public Status Pages ergaenzt.
- Activity-Seite als detailliertes, suchbares, filterbares und sortierbares Protokoll ausgebaut.
