<?php
/**
 * Aktivierungslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\InviteLinkRepository;
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;

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

			// Eigene Tabellen anlegen.
			$spaces = new SpaceRepository();
			$spaces->install();
			$audit = new AuditRepository();
			$audit->install();
			$invitations = new InvitationRepository();
			$invitations->install();
			$invite_links = new InviteLinkRepository();
			$invite_links->install();

			// Capabilities registrieren.
			Capabilities::register();

			// Hub-Seite mit Router-Shortcode sicherstellen.
			self::ensure_hub_page();

			flush_rewrite_rules();
		}

		/**
		 * Legt die zentrale Hub-Seite (Shortcode `[afspaces]`) idempotent an.
		 *
		 * @return int Seiten-ID der Hub-Seite (0 bei Fehler).
		 */
		public static function ensure_hub_page(): int {
			$existing = get_page_by_path( \AFSpaces\Interface\SpacesUrls::HUB_SLUG );
			if ( $existing ) {
				update_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, (int) $existing->ID );
				return (int) $existing->ID;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => __( 'Räume', 'afspaces' ),
					'post_name'    => \AFSpaces\Interface\SpacesUrls::HUB_SLUG,
					'post_content' => '[afspaces]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( is_wp_error( $page_id ) || 0 === (int) $page_id ) {
				return 0;
			}

			update_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, (int) $page_id );
			return (int) $page_id;
		}
	}
}
