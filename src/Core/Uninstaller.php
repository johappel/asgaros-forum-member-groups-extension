<?php
/**
 * Deinstallationslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

use AFSpaces\Core\Capabilities;

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
			// Capabilities entfernen.
			Capabilities::remove();

			// Eigene Tabellen aufräumen (Asgaros-Daten bleiben unangetastet, siehe ARCHITECTURE.md).
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_spaces" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_space_managers" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_audit" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_invitations" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_invite_links" );
		}
	}
}
