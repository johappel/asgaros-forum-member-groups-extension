<?php
/**
 * Zentrale URL- und View-Verwaltung für die Spaces-Hub-Seite.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\SpacesUrls' ) ) {

	/**
	 * Baut Links zur Hub-Seite und kennt die erlaubten Unteransichten.
	 *
	 * Die Hub-Seite bündelt Dashboard, Mitglieder, Einladungen und die
	 * persönlichen Einladungen unter einer einzigen WordPress-Seite mit dem
	 * Shortcode `[afspaces]`. Die konkrete Unteransicht wird über den
	 * Query-Parameter `afspaces_view` gesteuert.
	 */
	final class SpacesUrls {

		/**
		 * Slug der Hub-Seite.
		 */
		public const HUB_SLUG = 'afspaces';

		/**
		 * Option, in der die Hub-Seiten-ID gespeichert wird.
		 */
		public const HUB_PAGE_OPTION = 'afspaces_hub_page_id';

		/**
		 * Name des Query-Parameters für die Unteransicht.
		 */
		public const VIEW_PARAM = 'afspaces_view';

		public const VIEW_DASHBOARD      = 'dashboard';
		public const VIEW_MEMBERS        = 'members';
		public const VIEW_INVITATIONS    = 'invitations';
		public const VIEW_MY_INVITATIONS = 'my-invitations';
		public const VIEW_CREATE         = 'create';

		/**
		 * Zuordnung alter Einzelseiten-Slugs auf die neuen Unteransichten.
		 *
		 * @return array<string,string>
		 */
		public static function legacy_slug_map(): array {
			return array(
				'afspaces-dashboard'       => self::VIEW_DASHBOARD,
				'afspaces-members'         => self::VIEW_MEMBERS,
				'afspaces-invitations'     => self::VIEW_INVITATIONS,
				'afspaces-my-invitations'  => self::VIEW_MY_INVITATIONS,
			);
		}

		/**
		 * Gibt alle gültigen Unteransichten zurück.
		 *
		 * @return string[]
		 */
		public static function views(): array {
			return array(
				self::VIEW_DASHBOARD,
				self::VIEW_MEMBERS,
				self::VIEW_INVITATIONS,
				self::VIEW_MY_INVITATIONS,
				self::VIEW_CREATE,
			);
		}

		/**
		 * Normalisiert einen View-Wert auf eine erlaubte Unteransicht.
		 *
		 * @param mixed $view Roher View-Wert.
		 * @return string
		 */
		public static function normalize_view( $view ): string {
			$view = is_string( $view ) ? sanitize_key( $view ) : '';
			return in_array( $view, self::views(), true ) ? $view : self::VIEW_DASHBOARD;
		}

		/**
		 * Gibt die ID der Hub-Seite zurück (0, falls nicht vorhanden).
		 *
		 * @return int
		 */
		public static function hub_page_id(): int {
			$stored = (int) get_option( self::HUB_PAGE_OPTION, 0 );
			if ( $stored > 0 && 'page' === get_post_type( $stored ) && 'publish' === get_post_status( $stored ) ) {
				return $stored;
			}

			$page = get_page_by_path( self::HUB_SLUG );
			if ( $page ) {
				update_option( self::HUB_PAGE_OPTION, (int) $page->ID );
				return (int) $page->ID;
			}

			return 0;
		}

		/**
		 * Basis-Permalink der Hub-Seite (Fallback: Startseite).
		 *
		 * @return string
		 */
		public static function hub_base_url(): string {
			$page_id = self::hub_page_id();
			if ( $page_id > 0 ) {
				$permalink = get_permalink( $page_id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					return $permalink;
				}
			}

			return home_url( '/' . self::HUB_SLUG . '/' );
		}

		/**
		 * Baut eine URL zu einer Unteransicht der Hub-Seite.
		 *
		 * @param string              $view Unteransicht.
		 * @param array<string,mixed> $args Zusätzliche Query-Parameter.
		 * @return string
		 */
		public static function hub_url( string $view = self::VIEW_DASHBOARD, array $args = array() ): string {
			$view = self::normalize_view( $view );
			$base = self::hub_base_url();

			$query = array();
			if ( self::VIEW_DASHBOARD !== $view ) {
				$query[ self::VIEW_PARAM ] = $view;
			}
			foreach ( $args as $key => $value ) {
				if ( null === $value || '' === $value ) {
					continue;
				}
				$query[ $key ] = $value;
			}

			if ( empty( $query ) ) {
				return $base;
			}

			return add_query_arg( $query, $base );
		}
	}
}
