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

			flush_rewrite_rules();
		}
	}
}
