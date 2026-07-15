<?php
/**
 * Aktivierungslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\Activator' ) ) {

	/**
	 * Wird bei der Plugin-Aktivierung ausgeführt.
	 */
	class Activator {

		/**
		 * Aktivierungs-Hook-Callback.
		 *
		 * @return void
		 */
		public static function activate(): void {
			if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
				deactivate_plugins( plugin_basename( AFSPACES_FILE ) );
				wp_die(
					esc_html__( 'Asgaros Forum Spaces benötigt mindestens PHP 8.1.', 'afspaces' )
				);
			}

			// Capabilities werden in M1.4 registriert.
			// Datenbankschema-Migration folgt in einem späteren MVP-Schritt.
			flush_rewrite_rules();
		}
	}
}
