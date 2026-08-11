# TESTING.md

## Entwickler-Schnellstart

Ohne projektspezifische lokale Pfade:

```bash
composer install
composer test
vendor/bin/phpunit -c phpunit.xml.dist
vendor/bin/phpunit -c phpunit-integration.xml.dist
```

Die Integrationstests setzen eine vorbereitete WordPress-/Asgaros-Umgebung voraus. Ist sie nicht verfügbar, gilt der Unit-Testlauf als fokussierte technische Prüfung; die Integration muss für die Release-Abnahme nachgeholt werden.

## Production-Readiness-Prüfung (Issue #4)

Der Lifecycle-Test `tests/Integration/HubLifecycleTest.php` prüft die Wiederverwendung einer gespeicherten Hub-Seite nach Titel-/Slug-Änderung sowie die Wiederherstellung nach Löschung. Die Unit-Suite enthält zusätzlich die Default- und Sanitizing-Prüfung für `afspaces_cleanup_on_uninstall`.

Vor einem Release mindestens ausführen:

```powershell
vendor/bin/phpunit -c phpunit.xml.dist --no-coverage
vendor/bin/phpunit -c phpunit-integration.xml.dist --no-coverage --filter HubLifecycleTest
```

Die vollständige Integration gegen die festgelegte WordPress-/Asgaros-Testinstanz bleibt für die Release-Abnahme erforderlich.

## Testpyramide

1. Unit-Tests für Domain und Policies.
2. Integrationstests mit WordPress-Testumgebung und Asgaros.
3. REST- und Sicherheitstests.
4. End-to-End-Tests für Benutzerpfade.
5. Accessibility-Automation und manuelle Prüfungen.

## Werkzeuge

Empfohlen:

- PHPUnit,
- WordPress PHPUnit Test Suite,
- Brain Monkey nur für isolierte WordPress-Hooks, falls sinnvoll,
- PHPCS mit WordPress Coding Standards,
- PHPStan oder Psalm in pragmatischer Konfiguration,
- Playwright,
- axe-core,
- WP-CLI für Setup- und Migrationstests.

## Testmatrix

Mindestens:

- unterstützte minimale und aktuelle WordPress-Version,
- PHP 8.1 und aktuelle stabile PHP-Version,
- definierte minimale und aktuelle Asgaros-Version,
- Single Site; Multisite als explizite spätere Entscheidung,
- JavaScript an und aus,
- Administrator, Manager, Mitglied, eingeladener und anonymer Benutzer.

## CI

Jeder Pull Request prüft:

- Syntax,
- Coding Standards,
- statische Analyse,
- Unit- und Integrationstests,
- Build der Assets,
- E2E-Smoke-Test,
- Accessibility-Smoke-Test.
