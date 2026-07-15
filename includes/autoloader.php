<?php
/**
 * PSR-4 Autoloader für den Namespace AFSpaces.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix   = 'AFSpaces\\';
		$base_dir = AFSPACES_PATH . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
