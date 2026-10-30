# HumHub-Kompatibilität 0.1.11

Stand: 30.10.2026. Ziel: HumHub CE 1.18.6 und 1.19.3, PHP 8.2+.
Die bisherige Mindestversion bleibt erhalten; die obere Grenze ist bewusst auf 1.19.x gesetzt.

Admin-Menü über MenuLink; deklarierte Modulabhängigkeiten werden vor requirements.php geprüft. Tests berücksichtigen beide AssetManager-Namespaces.

Geprüft in getrennten frischen HumHub-/MariaDB-Installationen mit den offiziellen Paketen 1.18.6 und 1.19.3: Modulaktivierung/Migrationen, Laden der Modulklassen und Rendern der Verwaltungs-/Navigationsansichten. Die bestehenden Geschäftsregeln wurden nicht erweitert. Produktive Dateien und Datenbanken wurden nicht geändert.

Ein vollständiger Test der bestehenden produktiven Daten, des individuellen Themes und aller übrigen Community-Module ist damit nicht ersetzt. Insbesondere Keycloak und Share Content gehören nicht zu diesem Änderungssatz.
