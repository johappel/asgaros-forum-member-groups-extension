<?php
/**
 * Deinstallationslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\Uninstaller' ) ) {

	/**
	 * Wird beim vollständigen Löschen des Plugins ausgeführt.
	 */
	class Uninstaller {

		/**
		 * Uninstall-Hook-Callback.
		 *
		 * @return void
		 */
		public static function uninstall(): void {
			// Eigene Tabellen und Optionen werden in einem späteren
			// MVP-Schritt definiert und hier aufgeräumt.
			// Asgaros-Daten bleiben unangetastet (siehe ARCHITECTURE.md).
		}
	}
}
