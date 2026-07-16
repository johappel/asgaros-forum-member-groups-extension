<?php
/**
 * Registriert und verwaltet Plugin-Capabilities.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\Capabilities' ) ) {

	/**
	 * Zentrale Verwaltung der Capabilities.
	 */
	class Capabilities {

		public const MANAGE_ALL_SPACES   = 'afspaces_manage_all_spaces';
		public const CREATE_SPACE        = 'afspaces_create_space';
		public const MANAGE_OWN_SPACE     = 'afspaces_manage_own_space';
		public const INVITE_MEMBERS       = 'afspaces_invite_members';
		public const REMOVE_MEMBERS       = 'afspaces_remove_members';
		public const CREATE_INVITE_LINKS  = 'afspaces_create_invite_links';
		public const MODERATE_SPACE       = 'afspaces_moderate_space';

		/**
		 * Capabilities, die Administratoren bei Aktivierung zugewiesen werden.
		 *
		 * @return string[]
		 */
		public static function all(): array {
			return array(
				self::MANAGE_ALL_SPACES,
				self::CREATE_SPACE,
				self::MANAGE_OWN_SPACE,
				self::INVITE_MEMBERS,
				self::REMOVE_MEMBERS,
				self::CREATE_INVITE_LINKS,
				self::MODERATE_SPACE,
			);
		}

		/**
		 * Weist allen Administrator-Rollen die Capabilities zu.
		 *
		 * @return void
		 */
		public static function register(): void {
			$role = get_role( 'administrator' );
			if ( ! $role ) {
				return;
			}
			foreach ( self::all() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		/**
		 * Entfernt die Capabilities bei Deinstallation.
		 *
		 * @return void
		 */
		public static function remove(): void {
			$role = get_role( 'administrator' );
			if ( ! $role ) {
				return;
			}
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
