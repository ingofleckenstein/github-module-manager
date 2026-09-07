# Architekturentscheidung vor Implementierung

Ziel: HumHub CE 1.18.5, PHP >= 8.2 (lokale CLI: 8.3.6). MVP verfolgt öffentliche GitHub-Branches. Tags, Releases, Token und private Repositories bleiben gemäß Abschnitt 63 der Arbeitsanweisung Phase 2.

## Geprüfte Core-APIs

- `humhub/components/bootstrap/ModuleAutoLoader.php`: `moduleAutoloadPaths`, `locateModules()` und ausführbare config.php. Ungeprüfte Downloads dürfen deshalb niemals in einen Autoload-Pfad gelangen.
- `humhub/modules/marketplace/services/ModuleService.php`: `install()` und `update()` verwenden den OnlineModuleManager/Marketplace. Für GitHub ungeeignet; nicht zweckentfremden.
- `humhub/services/MigrationService.php`: `new MigrationService($module)`, `hasMigrations()`, `migrateUp()`; nur Zielmodul, keine globale Core-Migration.
- `humhub/components/Module.php`: Aktivierung ist getrennt; Migrationen können auch ohne Aktivierung aufgerufen werden. Updates überschreiben keine aktivierten Zustände.
- `humhub/components/AssetManager.php`: `clear()`; dazu ModuleManager::flushCache(), cache::flush(). OPCache invalidieren.
- `humhub/modules/admin/components/Controller.php`: setzt adminOnly bei Custom-Modulen zurück! Eigene explizite Systemadmin-Zugriffskontrolle ist erforderlich.
- Offizieller Updater 2.4.2, UpdatePackage/AvailableUpdate als Referenz für Migrations-/Cache-/OPCache-Schritte. Keine Fremdimplementierung kopieren, insbesondere nicht dessen unbeschränktes extractTo(). Referenz: https://github.com/humhub/updater

## Ablauf

GitHub-URL strikt parsen → Branch auf unveränderlichen SHA auflösen → SHA-Archiv in Runtime herunterladen → ZIP vor jedem Schreiben vollständig validieren → statische Modulprüfung ohne PHP-Ausführung → serverseitig gespeicherte, adminbezogene Vorschau. Erst bestätigtes POST installiert exakt diese Version.

Repository-Zuordnungen, Installationszustand und Audit-Historie liegen in eigenen Tabellen. Vorschau und Operationen besitzen zufällige IDs. Pro Modul flock; Installationszustand zusätzlich persistent. Vorhandene Module können ohne Dateiänderung zugeordnet werden. SHA bleibt dabei unbekannt, solange er nicht durch eine kontrollierte Installation belegt ist.

Dateiaustausch unter Sperre: vollständiges Staging und Backup im Runtime-Verzeichnis auf demselben Dateisystem wie der Modulpfad; Vorprüfung von Rechten und Device-ID. Alt nach Backup umbenennen, Neu zum Modulpfad umbenennen. Kein Kopieren über das aktive Modul. Ein Journal bleibt bei Prozessabbruch für die manuelle Wiederherstellung erhalten. Bei abgefangenen Fehlern werden alte Dateien wiederhergestellt; Datenbankänderungen sind ausdrücklich nicht automatisch rückrollbar. Nach Migrationsfehler keine weiteren Updates bis zur administrativen Prüfung.

Nur konfigurierte Custom-Autoload-Pfade; Core und Manager selbst sind gesperrt. Pfade mit Symlinks werden abgewiesen. Module mit nicht statisch prüfbarer Konfiguration oder unbekannten Abhängigkeitsangaben werden im MVP konservativ abgewiesen, statt Anforderungen zu ignorieren. requirements.php wird erst nach expliziter Vertrauensbestätigung ausgeführt. Composer wird niemals gestartet.

Modul-PHP kann nach Zustimmung beliebigen Servercode ausführen. Der Manager ist kein PHP-Sandbox-System. Fehlende Transaktionsfähigkeit von Migrationen und laufende Requests während des kurzen Rename-Fensters bleiben Betriebsgrenzen; vor Updates Datenbanksicherung und geeignetes Wartungsfenster vorsehen.
