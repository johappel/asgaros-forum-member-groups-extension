<?php
/**
 * Aktivierungslogik des Plugins.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\JoinRequestRepository;
use AFSpaces\Adapters\Database\InviteLinkRepository;
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
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

			$requirements = new Requirements();
			if ( ! $requirements->check() ) {
				deactivate_plugins( plugin_basename( AFSPACES_FILE ) );
				wp_die( esc_html( implode( ' ', $requirements->get_messages() ) ) );
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
			$join_requests = new JoinRequestRepository();
			$join_requests->install();
			$space_meta = new SpaceMetaRepository();
			$space_meta->install();
			$search_index = new \AFSpaces\Adapters\Database\SearchIndexRepository();
			$search_index->install();

			// Capabilities registrieren.
			Capabilities::register();

			// Hub-Seite mit Router-Shortcode sicherstellen.
			$hub_page_id = self::ensure_hub_page();
			update_option(
				'afspaces_activation_notice',
				array(
					'page_id'         => $hub_page_id,
					'asgaros_version' => $requirements->get_asgaros_version() ?? '',
				)
			);

			// Wiederkehrenden Reindex-Lauf planen.
			\AFSpaces\Application\SearchIndexer::schedule();

			flush_rewrite_rules();
		}

		/**
		 * Legt die zentrale Hub-Seite (Shortcode `[afspaces]`) idempotent an.
		 *
		 * @return int Seiten-ID der Hub-Seite (0 bei Fehler).
		 */
		public static function ensure_hub_page(): int {
			$stored_id = (int) get_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, 0 );
			if ( $stored_id > 0 && \AFSpaces\Interface\SpacesUrls::is_valid_hub_page( $stored_id ) ) {
				$page = get_post( $stored_id );
				if ( '1' !== (string) get_post_meta( $stored_id, \AFSpaces\Interface\SpacesUrls::HUB_MANAGED_META, true )
					&& $page instanceof \WP_Post
					&& false !== strpos( (string) $page->post_content, '[afspaces' ) ) {
					update_post_meta( $stored_id, \AFSpaces\Interface\SpacesUrls::HUB_MANAGED_META, '1' );
				}
				return $stored_id;
			}

			$existing = get_page_by_path( \AFSpaces\Interface\SpacesUrls::HUB_SLUG );
			if ( $existing && '1' === (string) get_post_meta( (int) $existing->ID, \AFSpaces\Interface\SpacesUrls::HUB_MANAGED_META, true ) ) {
				update_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, (int) $existing->ID );
				return (int) $existing->ID;
			}
			// Ein fremder Konflikt mit dem Standard-Slug wird nicht übernommen.
			$existing = null;
			if ( $existing ) {
				if ( 'Räume' === (string) $existing->post_title ) {
					wp_update_post(
						array(
							'ID' => (int) $existing->ID,
							'post_title' => __( 'Arbeitsgruppen', 'afspaces' ),
						)
					);
				}
				update_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, (int) $existing->ID );
				return (int) $existing->ID;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => __( 'Arbeitsgruppen', 'afspaces' ),
					'post_name'    => \AFSpaces\Interface\SpacesUrls::HUB_SLUG,
					'post_content' => '[afspaces]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( is_wp_error( $page_id ) || 0 === (int) $page_id ) {
				return 0;
			}

			update_post_meta( (int) $page_id, \AFSpaces\Interface\SpacesUrls::HUB_MANAGED_META, '1' );
			update_option( \AFSpaces\Interface\SpacesUrls::HUB_PAGE_OPTION, (int) $page_id );
			return (int) $page_id;
		}
	}
}
