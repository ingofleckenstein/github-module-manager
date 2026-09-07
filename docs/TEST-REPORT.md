# Abnahmebericht – 7. September 2026

Umgebung: HumHub CE 1.18.5 aus dem offiziellen Installationspaket, PHP 8.3.6, MariaDB 10.11.14, WSL/Linux. Dauerhafte lokale Umgebung: `humhub-test-environment`; Daten unter `~/.local/share/humhub-test`, Browser http://localhost:8765. Keine produktive Installation verändert.

## Automatisierte Prüfungen

- 86 isolierte Serviceprüfungen: gültige und ungültige Repository-/Branch-Adressen; SSRF-Hostgrenzen; ZIP-Traversal einschließlich Windows-Pfaden; Symlinks; defekte, zu große und unvollständige Archive; eindeutige Modul-ID; PHP-freie Inspektion; Composer-Abweisung; Neuinstallation; Update; Backup; Schreibrechte; lokaler Fingerabdruck; parallele Updates; Austausch- und Migrationsfehler; Wiederherstellung und persistenter Wiederherstellungsstatus.
- 21 HumHub-/MariaDB-Integrationsprüfungen: temporäres Fixture-Modul innerhalb der dauerhaften Testcommunity, danach gezielte Entfernung der Testartefakte. Neuinstallation, HumHub-Erkennung, deaktivierter Zustand, echte Initial-/Update-Migrationen, SHA-basierte Erkennung auch bei gleicher Modulversion, Backup, Fehlerpfade und Auditdaten geprüft.
- PHP-Syntax aller Modul- und Testdateien geprüft.

## HTTP-Prüfungen auf der lokalen Community

- Anmeldung und Verwaltungsseite: HTTP 200.
- Gastzugriff auf Administration wird zur Anmeldung umgeleitet.
- Systemadmin-GET auf Installation: 405; POST ohne gültigen CSRF-Token: 400.
- Öffentliches Repository `https://github.com/humhub-contrib/popover-vcard`: tatsächlicher Standardbranch `master`, Metadaten-/Branchabfrage und sicherer Archivdownload bis zur Modul-Bestätigungsseite erfolgreich. Dieses externe Modul wurde dabei nicht installiert.
- Konfigurations- und Runtime-Logpfade sind über den lokalen Webserver nicht abrufbar (404).
- Windows-Zugriff über localhost:8765 mit HTTP 200 bestätigt.

Die vollständige Installation-/Updatefolge verwendet deterministische GitHub-Provider-Fixtures, echte HumHub-APIs und MariaDB. Damit sind Netzwerkfehler reproduzierbar, ohne ein öffentliches GitHub-Repository für den Test zu verändern. Eine spätere Abnahme auf dem tatsächlichen Hosting einschließlich PHP-Dateirechten, OPCache und Wartungsfenster bleibt vor einem produktiven Einsatz erforderlich. Die eingebaute ZIP-Prüfung ersetzt keine Prüfung der Vertrauenswürdigkeit eines Modulautors.
