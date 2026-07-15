# TESTING.md

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
