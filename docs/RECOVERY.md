# Wiederherstellung nach fehlgeschlagenem Update

Bei einem Fehler zeigt die Oberfläche einen Fehlerstatus. Im Modulverlauf stehen Ergebnis und Phase. Das Journal `@runtime/github-module-manager/operations/<module-id>.json` enthält Zielpfad, Backuppfad, Stagingpfad und zuletzt gespeicherte Phase. Es enthält keine Zugangsdaten.

1. Weitere Updates und betroffene Community-Zugriffe unterbrechen. Bei abgebrochenen PHP-Prozessen zunächst prüfen, ob der Vorgang wirklich beendet ist. Eine Dateisperre allein ersetzt diese Prüfung nicht.
2. Journal, installierte Dateien, vorhandenes Backup und Datenbankzustand prüfen. Ein Prozess kann zwischen einem Rename und dem nächsten Journaleintrag abgebrochen sein; Dateisystemzustand ist maßgeblich.
3. Bei `filesRestored: true` wurden die vorherigen Dateien bereits zurückgestellt. Bei `false` oder fehlendem Abschluss anhand der konkret im Journal genannten Pfade wiederherstellen. Keine geratenen Verzeichnisse löschen.
4. Nach Beginn der Migrationsphase die Datenbank unabhängig prüfen. MySQL/MariaDB-DDL ist nicht vollständig transaktional. Bei Bedarf die vor dem Update angelegte Datenbanksicherung zurückspielen oder eine fachlich geprüfte Korrekturmigration ausführen. Der Manager führt kein automatisches `down()` aus.
5. Modul-, Anwendungs- und Asset-Caches sowie OPCache nach der Wiederherstellung leeren. Modulverwaltung und betroffene Modulansichten kontrollieren.
6. Erst nach bestätigter Wiederherstellung das betreffende Journal archivieren und aus dem Operationsverzeichnis entfernen. Danach im Manager erneut prüfen. Andere Journale oder Backups nicht entfernen.

Die letzte erfolgreiche Version und SHA bleiben bei einem Migrationsfehler erhalten. Ein Fehlerstatus ist keine Zusage, dass alle Datenbankänderungen zurückgenommen wurden. File-Backups enthalten nur Moduldateien, weder Datenbank noch Uploads außerhalb des Modulordners.
