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

			// Hub-Seite entfernen, falls vorhanden.
			$hub_page_id = (int) get_option( 'afspaces_hub_page_id', 0 );
			if ( $hub_page_id > 0 ) {
				wp_delete_post( $hub_page_id, true );
			}

			// Plugin-Optionen aufräumen.
			delete_option( 'afspaces_hub_page_id' );
			delete_option( 'afspaces_installed_version' );
			delete_option( 'afspaces_enable_space_creation' );

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
