# Integrationstests — Asgaros Forum Spaces

Integrationstests gegen die echte WP Local + Asgaros-Instanz unter
`C:\Users\Joachim\Local Sites\forums`.

## Voraussetzungen

- WP Local Site `forums` (MariaDB auf Port `10016`, DB `local`/`root`/`root`).
- Asgaros Forum aktiv (getestet mit 3.4.0).
- Local PHP 8.2.23 unter
  `C:\Users\Joachim\AppData\Roaming\Local\lightning-services\php-8.2.23+0\bin\win64\php.exe`
  (andere Versionen analog anpassen).

## Testdaten einrichten

Einmalig (idempotent) Test-Kategorie, -gruppe und -forum anlegen:

```powershell
$php = "C:\Users\Joachim\AppData\Roaming\Local\lightning-services\php-8.2.23+0\bin\win64\php.exe"
& $php -c "tests/php-cli.ini" tests/setup-integration-data.php
```

Die ausgegebenen IDs (Kategorie, Gruppe, Forum) müssen in
`tests/Integration/IntegrationTestCase.php` eingetragen sein
(`$category_id`, `$group_id`, `$forum_id`).

## Integrationstests ausführen

```powershell
$php = "C:\Users\Joachim\AppData\Roaming\Local\lightning-services\php-8.2.23+0\bin\win64\php.exe"
$ini = "tests/php-cli.ini"
$pu  = "vendor/bin/phpunit"
& $php -c $ini $pu -c phpunit-integration.xml.dist
```

## Wichtige Hinweise

- `wp-config.php` definiert `DB_HOST` als `localhost` (Socket). Die CLI-PHP
  erreicht die Local-MariaDB nur über TCP `127.0.0.1:10016`. Der Bootstrap
  (`tests/integration-bootstrap.php`) erzwingt daher `DB_HOST` vor dem Laden
  von `wp-load.php`.
- Asgaros setzt die globale Variable `$asgarosforum` im Plugin-Scope. Im
  PHPUnit-CLI-Kontext ist sie nicht automatisch verfügbar. Der Bootstrap
  stellt die Instanz explizit her (`new \AsgarosForum()`), damit der Adapter
  über `global $asgarosforum` darauf zugreifen kann.
- `tests/php-cli.ini` aktiviert die für PHPUnit und PDO-MySQL benötigten
  Extensions der Local-PHP-Installation. Bei einer anderen PHP-Version muss
  `extension_dir` angepasst werden.
