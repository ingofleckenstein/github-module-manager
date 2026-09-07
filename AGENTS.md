# Entwicklung des GitHub Module Managers

- Ziel: HumHub CE 1.18.5, PHP >=8.2. Vor Änderungen zuerst `docs/ARCHITECTURE.md` lesen.
- Die dauerhafte lokale Testcommunity ist im Geschwisterprojekt `humhub-test-environment` dokumentiert. Sie liegt unter `~/.local/share/humhub-test/app` und ist über http://localhost:8765 erreichbar. Vor Tests Status prüfen; nie produktive Serverpfade verwenden.
- Quellcode und Testinstallation sind getrennte Kopien. Änderungen müssen für Integrationstests in den Modulordner der Testcommunity übertragen werden.
- Erst `php tests/run.php`, anschließend für Installations-/Migrations-/Rechteänderungen die Integrationstests gemäß `tests/README.md` ausführen.
- Ungeprüftes Repository-PHP nicht ausführen. Keine Shellbefehle, Git- oder Composer-Aufrufe im Modul. Alle Mutationen nur für Systemadmins, POST und CSRF.
- Core-Pfade, reservierte Aliase und Selbstupdates schützen; vorhandene Dateien nur mit Backup, Sperre und unveränderlicher Commit-Bestätigung ersetzen.
- Schemaänderungen ausschließlich durch neue Migrationen. Fehler- und Auditdaten dürfen keine Credentials oder rohen HTTP-Antworten enthalten.
- Neue PHP-Dateien: SPDX-License-Identifier: AGPL-3.0-only. UI-Texte über Yii::t und deutsche Übersetzungen.
- Repository ist lokal vorbereitet; Push erfolgt durch den Benutzer, solange kein ausdrücklicher anderer Auftrag vorliegt.
