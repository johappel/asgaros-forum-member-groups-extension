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
			// Deinstallation bewahrt Daten standardmäßig. Der Cron-Hook ist kein
			// persistentes AFSpaces-Fachdatum und wird trotzdem immer entfernt.
			if ( ! (bool) get_option( 'afspaces_cleanup_on_uninstall', false ) ) {
				if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
					wp_clear_scheduled_hook( 'afspaces_reindex_search' );
				}
				return;
			}

			// Capabilities entfernen.
			Capabilities::remove();

			// Hub-Seite entfernen, falls vorhanden.
			$hub_page_id = (int) get_option( 'afspaces_hub_page_id', 0 );
			if ( self::is_managed_hub_page( $hub_page_id ) ) {
				wp_delete_post( $hub_page_id, true );
			}

			// Plugin-Optionen aufräumen.
			delete_option( 'afspaces_hub_page_id' );
			delete_option( 'afspaces_installed_version' );
			delete_option( 'afspaces_enable_space_creation' );
			delete_option( 'afspaces_search_options' );
			delete_option( 'afspaces_appearance_options' );
			delete_option( 'afspaces_creation_options' );
			delete_option( 'afspaces_cleanup_on_uninstall' );
			delete_option( ForumManagementSettings::OPTION );
			delete_option( 'afspaces_activation_notice' );

			// Geplanten Reindex-Lauf entfernen.
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( 'afspaces_reindex_search' );
			}

			// Eigene Tabellen aufräumen (Asgaros-Daten bleiben unangetastet, siehe ARCHITECTURE.md).
			global $wpdb;
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_spaces" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_space_managers" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_space_forums" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_audit" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_invitations" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_invite_links" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_join_requests" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_space_meta" );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}afspaces_search_index" );
		}

		/**
		 * Gibt frei, ob eine Seite als AFSpaces-eigene Hub-Seite gelöscht werden darf.
		 *
		 * @param int $page_id Seiten-ID.
		 * @return bool
		 */
		public static function is_managed_hub_page( int $page_id ): bool {
			return $page_id > 0 && '1' === (string) get_post_meta( $page_id, \AFSpaces\Interface\SpacesUrls::HUB_MANAGED_META, true );
		}
	}
}
