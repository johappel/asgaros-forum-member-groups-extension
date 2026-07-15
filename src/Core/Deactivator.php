<?php
/**
 * Deaktivierungslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\Deactivator' ) ) {

	/**
	 * Wird bei der Plugin-Deaktivierung ausgeführt.
	 */
	class Deactivator {

		/**
		 * Deaktivierungs-Hook-Callback.
		 *
		 * @return void
		 */
		public static function deactivate(): void {
			flush_rewrite_rules();
		}
	}
}
