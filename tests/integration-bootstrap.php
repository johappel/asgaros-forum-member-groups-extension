<?php
/**
 * Bootstrap für Integrationstests gegen die echte WP Local + Asgaros-Instanz.
 *
 * Lädt den WordPress-Kern, damit WP-Funktionen, die DB und Asgaros
 * im Testkontext verfügbar sind.
 *
 * Aufruf:
 *   php -c tests/php-cli.ini vendor/bin/phpunit --bootstrap tests/integration-bootstrap.php tests/Integration
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

// Pfad zur WP Local-Instanz.
$wp_load = 'C:\\Users\\Joachim\\Local Sites\\forums\\app\\public\\wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php nicht gefunden: {$wp_load}\n" );
	exit( 1 );
}

// Local nutzt MariaDB auf Port 10016; wp-config.php definiert 'localhost'
// (Socket), was in der CLI-PHP fehlschlägt. Wir erzwingen TCP vor dem Laden.
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', '127.0.0.1:10016' );
}

// Manche Fremd-Plugins erwarten einen HTTP-Kontext auch in CLI-Tests.
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
	$_SERVER['REQUEST_METHOD'] = 'GET';
}

// Unterdrückt gezielt die bekannte DB_HOST-Redefinitions-Warnung aus wp-config.php.
set_error_handler(
	static function ( int $errno, string $errstr ): bool {
		if ( E_WARNING === $errno && false !== strpos( $errstr, 'Constant DB_HOST already defined' ) ) {
			return true;
		}

		return false;
	}
);

// WordPress laden (definiert WP-Funktionen, $wpdb, etc.).
require_once $wp_load;

restore_error_handler();

// Asgaros-Instanz global verfügbar machen (falls nicht automatisch geschehen).
global $asgarosforum;
if ( ! isset( $asgarosforum ) && class_exists( 'AsgarosForum' ) ) {
	$asgarosforum = new \AsgarosForum();
}

// Plugin laden (stellt Autoloading + Hooks bereit).
require_once dirname( __DIR__ ) . '/afspaces.php';

// Asgaros muss aktiv sein.
if ( ! class_exists( 'AsgarosForum' ) ) {
	fwrite( STDERR, "Asgaros Forum ist nicht aktiv in der Testinstanz.\n" );
	exit( 1 );
}
