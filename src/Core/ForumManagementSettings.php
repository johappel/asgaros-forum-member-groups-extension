<?php
/**
 * Globale Richtlinie für zusätzliche Foren in Arbeitsgruppen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

if ( ! class_exists( 'AFSpaces\\Core\\ForumManagementSettings' ) ) {

	/**
	 * Kapselt den Opt-in-Schalter für Arbeitsgruppenverantwortliche.
	 */
	final class ForumManagementSettings {

		public const OPTION = 'afspaces_group_managers_can_create_forums';

		public const DEFAULT = false;

		/**
		 * @return bool
		 */
		public static function group_managers_can_create_forums(): bool {
			return (bool) get_option( self::OPTION, self::DEFAULT );
		}
	}
}
