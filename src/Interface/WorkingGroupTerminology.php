<?php
/**
 * Sichtbare Begriffe fuer das Arbeitsgruppenmodell.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupTerminology' ) ) {

	/**
	 * Liefert zentral gepflegte UI-Begriffe fuer efabiNet.
	 */
	final class WorkingGroupTerminology {

		public const SINGULAR = 'working_group_singular';
		public const PLURAL = 'working_group_plural';
		public const MY_PLURAL = 'my_working_groups';
		public const DISCOVER = 'discover_working_groups';
		public const MANAGER_PLURAL = 'manager_plural';
		public const MANAGE = 'manage_working_group';

		/**
		 * @param string $key Begriffsschluessel.
		 * @return string
		 */
		public static function label( string $key ): string {
			switch ( $key ) {
				case self::SINGULAR:
					return __( 'Arbeitsgruppe', 'afspaces' );
				case self::PLURAL:
					return __( 'Arbeitsgruppen', 'afspaces' );
				case self::MY_PLURAL:
					return __( 'Meine Arbeitsgruppen', 'afspaces' );
				case self::DISCOVER:
					return __( 'Arbeitsgruppen entdecken', 'afspaces' );
				case self::MANAGER_PLURAL:
					return __( 'Arbeitsgruppenverantwortliche', 'afspaces' );
				case self::MANAGE:
					return __( 'Arbeitsgruppe verwalten', 'afspaces' );
				default:
					return __( 'Arbeitsgruppe', 'afspaces' );
			}
		}

		/**
		 * @param int $count Anzahl.
		 * @return string
		 */
		public static function membership_count( int $count ): string {
			$template = function_exists( '\_n' )
				? \_n( '%d Mitglied', '%d Mitglieder', $count, 'afspaces' )
				: ( 1 === $count ? '%d Mitglied' : '%d Mitglieder' );

			return sprintf(
				/* translators: %d: Anzahl der Mitglieder */
				$template,
				$count
			);
		}

		/**
		 * @param string $name Name der Arbeitsgruppe.
		 * @return string
		 */
		public static function manage_context( string $name ): string {
			return sprintf(
				/* translators: %s: Name der Arbeitsgruppe */
				__( 'Arbeitsgruppe verwalten: %s', 'afspaces' ),
				$name
			);
		}
	}
}