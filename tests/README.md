# Tests

## Isolierte Serviceprüfungen

PHP 8.2+ mit ZIP und cURL:

```sh
php tests/run.php
```

Keine Serverzugangsdaten und keine Netzwerkverbindungen erforderlich. Temporäre Dateien werden nach Testende entfernt.

## HumHub-/MariaDB-Integration

Nur in einer ausdrücklich für Entwicklung eingerichteten HumHub-CE-1.18.5-Testinstallation mit aktiviertem GitHub-Modulmanager und lokalem Admin-Konto `admin` ausführen. Die Datenbank-DSN muss zur zusätzlichen Absicherung `test` enthalten. Die Umgebungsvariablen dienen als explizite Freigabe, nicht als Erkennung einer Produktionsumgebung.

```sh
GMM_INTEGRATION=1 HUMHUB_ROOT=/pfad/zur/lokalen/testinstallation php tests/integration.php
```

Die Suite erzeugt das Modul `gmm-fixture` und die Tabelle `gmm_fixture_probe`. Sie prüft Neuinstallation, Erkennung durch HumHub, deaktivierten Zustand, Initial- und Update-Migration, SHA-basierte Erkennung trotz gleicher Modulversion, Backup, Downloadfehler, Migrationsfehler, Dateiwiederherstellung und Historie. Sie löscht am Ende nur ihre Testartefakte. Ein zufällig bereits vorhandenes Modul dieses Namens führt vor Änderungen zum Abbruch. Nicht gegen fremde oder produktive Instanzen ausführen.

## HTTP-Abnahme

- Als Gast und als angemeldete Person ohne Systemadminrechte alle Verwaltungsendpunkte ablehnen.
- Als Systemadmin GET auf `install` mit 405 ablehnen; POST ohne CSRF mit 400 ablehnen.
- Öffentliches Repository lesen, Branch auswählen und Modulvorschau sehen; noch keine Installation.
- Bestätigungsseite zeigt exakte SHA, lokale/remote Version und die Vertrauens-/Datenbanksicherungsabfrage.
- Neue Module bleiben deaktiviert; bei Zuordnung bestehender Module bleibt die installierte SHA zunächst unbekannt.
- Deutsch und Englisch sowie Formularbeschriftungen und Tastaturbedienung prüfen.

Die lokale Entwicklungsumgebung wird separat im Geschwisterprojekt `humhub-test-environment` dokumentiert. Ihre Zugangsdaten und Datenbank gehören niemals in dieses Repository.
