# GitHub Module Manager für HumHub

Dieses **HumHub-Modul** ermöglicht Systemadministrator*innen, Module aus **öffentlichen GitHub-Repositories** zu prüfen, zu installieren und anhand neuer Branch-Commits zu aktualisieren. Es wird in HumHub installiert und ist kein PeerTube-Plugin.

Version **0.1.4**. Zielplattform: **HumHub Community Edition 1.18.5**, PHP **8.2+**, Erweiterungen **cURL** und **ZIP**. Entwickelt und integriert geprüft mit PHP 8.3.6 und MariaDB 10.11. Auf der lokalen Standard-Testcommunity ist es unter **Administration → GitHub-Modulmanager** erreichbar.

## Funktionsumfang

- GitHub-URL prüfen, Standardbranch und Branches lesen; Branches mit `/` werden unterstützt.
- Modul-ID, Name, Beschreibung und Version aus `module.json` lesen und mit `config.php` abgleichen. PHP wird während der Vorschau nicht ausgeführt.
- Unveränderlichen Commit-SHA für Download und Bestätigung verwenden; kein unbemerktes Nachrücken auf einen neueren Branchstand.
- Neuinstallation und Zuordnung bereits vorhandener Custom-Module; bei einer Zuordnung wird keine unbekannte lokale SHA als bestätigt ausgegeben.
- Manuelle Einzel- und Gesamtprüfung, Status, Details und Updatehistorie mit Admin-ID.
- Die Prüfung liest die lokale `module.json` erneut: Eine fehlende bestätigte SHA bedeutet bei gleicher Version nicht fälschlich ein Update. Gleiche Versionen mit abweichender bestätigter SHA werden als anderer Commit ausgewiesen; neue lokale Dateifingerabdrücke als lokale Änderungen.
- Lokale Moduldaten lassen sich einzeln oder gesammelt neu einlesen, ohne zuletzt gelesene Remote-Daten zu überschreiben.
- Datei-Backup, vollständiges Staging, Verzeichnistausch auf demselben Dateisystem und Wiederherstellung bei abgefangenen Fehlern.
- Migrationen über HumHubs `MigrationService`, Bereinigung von Modul-, Anwendungs- und Asset-Cache sowie OPCache. Ausstehende HumHub-Core-Migrationen blockieren einen Modulupdate vor dem Dateiaustausch mit einem erklärenden Hinweis.
- Bereits beim Start registrierte Module werden bei einem Update nicht ein zweites Mal im selben Request registriert. Dadurch bleiben zwischengespeicherte Modulkonfigurationen und Event-Handler beim Dateiaustausch stabil; der bereinigte Cache wird im folgenden Request neu geladen.
- Lokale Dateiänderungen per SHA-256-Fingerabdruck erkennen und vor dem Ersetzen ausdrücklich bestätigen lassen.
- Systemadmin-Prüfung, POST/CSRF-Schutz, begrenzte HTTPS-Downloads und sichere ZIP-Extraktion.
- Deutsche und englische Oberfläche; konfigurierbare Download-, Entpack-, Dateianzahl- und Backup-Limits.
- Optionaler Selbstupdate-Kanal: Das Manager-Repository und sein Branch werden unter den Einstellungen hinterlegt. Der anschließende Updateablauf nutzt dieselbe SHA-gebundene Vorschau, Sperre, das Staging, Verzeichnistausch, Backup und Wiederherstellungsprotokoll wie ein Modulupdate. Die neue Manager-Version wird erst mit dem nächsten Request geladen.

Neu installierte Module bleiben deaktiviert. Aktivierung erfolgt über HumHubs normale Modulverwaltung. Der Aktivierungszustand bestehender Module wird nicht verändert. Auch deaktivierte Module können aktualisiert und migriert werden.

## Installation

1. Den vollständigen Repository-Inhalt als Ordner `github-module-manager` in einen konfigurierten Custom-Modulpfad kopieren, normalerweise `protected/modules/github-module-manager`.
2. In **Administration → Module** den **GitHub Module Manager** aktivieren. Seine versionierte Migration legt Zuordnungs- und Historientabellen an.
3. **Administration → GitHub-Modulmanager** öffnen und ein öffentliches Repository hinzufügen.
4. Branch auswählen, Modul prüfen, angezeigte Metadaten kontrollieren und erst danach installieren. Bei vorhandenen Dateien zunächst die Repository-Zuordnung speichern und anschließend ein Update prüfen.

Der Webserver-/PHP-Benutzer benötigt Schreibrechte auf Runtime und dem gewählten Custom-Modulpfad. Beide müssen auf demselben Dateisystem liegen, damit der Austausch ohne dateiweises Überschreiben gelingt. Der Manager leitet die Pfade aus `moduleAutoloadPaths` ab. Core-Pfade, reservierte Anwendungsaliase und Symlink-Pfade werden abgewiesen. Der Manager selbst ist nur über seinen besonderen, in den Einstellungen hinterlegten Selbstupdate-Kanal aktualisierbar; freie Zuordnung oder Überschreibung über die normale Modulmaske bleibt gesperrt.

## Grenzen des MVP

- Nur öffentliche GitHub-Repositories und Branches. **Private Repositories funktionieren nicht**, auch dann nicht, wenn du im Browser bei GitHub angemeldet bist. Die bestehende Installation besitzt keine GitHub-Token- oder andere Repository-Authentifizierung; deshalb kann auch der Selbstupdate-Kanal keine privaten Repositories lesen.
- Releases, Tags, SemVer-Updatekanäle, automatische Prüfungen und zusätzliche Provider sind gemäß Arbeitsauftrag Phase 2. Es erfolgen keine unbeaufsichtigten Updates.
- Nur ein Modul im Repository-Wurzelverzeichnis unter dem GitHub-ZIP-Wrapper. Monorepos mit Modul-Unterordnern werden abgewiesen.
- `config.php` muss eine eindeutig statisch lesbare Modul-ID und einen Klasseneintrag enthalten. Dynamisch berechnete oder mehrdeutige IDs werden abgewiesen.
- Composer wird niemals ausgeführt. Module mit `composer.json.require` oder nicht unterstützten deklarativen Modul-/PHP-Abhängigkeiten werden zur manuellen Installation verwiesen. `requirements.php` wird nach der Vertrauensbestätigung nach HumHubs Konvention geprüft; Abhängigkeiten werden nicht automatisch installiert.
- HumHubs Marketplace-`ModuleService::install/update()` eignet sich nicht für GitHub-Quellen. Der Manager nutzt einen eigenen Dateitransfer und HumHubs MigrationService. Individuelle überschreibende `Module::update()`-Hooks werden nicht automatisch ausgeführt; Module, die solche zusätzlichen Update-Schritte benötigen, sind manuell zu aktualisieren.
- Keine Änderungen an HumHub-Core, Themes oder Core-Composer-Paketen.

## Sicherheit und Wiederherstellung

Ein HumHub-Modul enthält ausführbaren PHP-Code. Installiere ausschließlich vertrauenswürdige Quellen. Die ZIP-Prüfung ist keine Sicherheitsprüfung des enthaltenen PHP-Codes. Auch deaktivierte Module können beim HumHub-Start ausführbare Konfiguration laden.

Die öffentliche GitHub-API benötigt keinen Token und begrenzt anonyme Anfragen. Repository-Metadaten werden 15 Minuten gecacht; explizite Update-Prüfungen lesen neu. Ein normaler Aufruf der Übersicht startet keine GitHub-Abfragen. HTTP-Redirects werden nicht verfolgt; API- und Codeload-Adressen werden direkt konstruiert und auf erlaubte Hosts sowie öffentliche IPv4-Adressen begrenzt.

Backups und Vorgangsprotokolle liegen unter `@runtime/github-module-manager`. Standardlimits: Download 50 MB, entpackt 200 MB, 10.000 Einträge, drei Dateibackups. Vorschauen gelten 30 Minuten; abgebrochene Vorschauen werden beim nächsten Prüfvorgang nach zwei Stunden aufgeräumt. Backups werden nach erfolgreichen Updates begrenzt. Ein gemeinsam genutztes Runtime-Dateisystem ist bei mehreren HumHub-Webservern Voraussetzung für die Dateisperren. Selbstupdates sind ebenfalls mit `selfUpdate: true` im Vorgangsprotokoll markiert. Vor einem Selbstupdate ist ein Wartungsfenster sinnvoll: Der gerade verarbeitete Request läuft noch mit dem alten PHP-Code, die Dateiübernahme erfolgt atomar und die neue Version wird im folgenden Request verwendet.

**Ein Datei-Rollback ist kein Datenbank-Rollback.** Vor Updates eine Datenbanksicherung erstellen. Bei einer fehlgeschlagenen oder abgebrochenen Operation weitere Änderungen stoppen und [RECOVERY.md](docs/RECOVERY.md) verwenden. Ein persistierendes Vorgangsprotokoll blockiert weitere Installationen dieses Moduls. Deaktivieren des Managers erhält Zuordnungen und Historie.

## Tests und Entwicklungsstand

```sh
php tests/run.php
```

Die isolierte Suite prüft URL-/Branch-Validierung, ZIP-Grenzen, Traversal, Symlinks, Metadaten, Backups, Sperren und Fehlerrücksetzungen ohne Netzwerk oder HumHub-Installation.

Die vollständige Testfolge auf einer bewusst ausgewählten lokalen HumHub-/MariaDB-Instanz ist in [tests/README.md](tests/README.md) beschrieben. Sie installiert und aktualisiert ausschließlich ein Testmodul und räumt dessen Daten anschließend auf. Die aktuelle Abnahme steht in [TEST-REPORT.md](docs/TEST-REPORT.md); Architekturentscheidungen und geprüfte Core-APIs in [ARCHITECTURE.md](docs/ARCHITECTURE.md).

Quellcode und Fehlerberichte: [ingofleckenstein/github-module-manager](https://github.com/ingofleckenstein/github-module-manager). Das Repository ist öffentlich. Die lokale Testumgebung und ihre Zugangsdaten sind nicht Bestandteil dieses Repositories.

## Lizenz

Neue Modulquellen stehen unter **AGPL-3.0-only**, siehe [LICENSE](LICENSE). Die erwähnten HumHub- und GitHub-Projekte bleiben Eigentum ihrer jeweiligen Rechteinhaber; ihre Implementierungen wurden als API-Referenz gelesen und nicht in dieses Modul kopiert.
