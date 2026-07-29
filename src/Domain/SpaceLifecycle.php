<?php
/**
 * Lebenszyklus und Statusübergänge eines Spaces.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\SpaceLifecycle' ) ) {

	/**
	 * Zentrale, reine Validierung der erlaubten Space-Statusübergänge.
	 *
	 * Diese Klasse enthält keine WordPress-Abhängigkeiten, damit sie ohne
	 * WP-Bootstrap unit-getestet werden kann.
	 */
	final class SpaceLifecycle {

		public const STATUS_PENDING  = 'pending';
		public const STATUS_ACTIVE   = 'active';
		public const STATUS_ARCHIVED = 'archived';
		public const STATUS_REJECTED = 'rejected';
		public const STATUS_DELETED  = 'deleted';

		/**
		 * Gibt alle bekannten Status zurück.
		 *
		 * @return string[]
		 */
		public static function all_statuses(): array {
			return array(
				self::STATUS_PENDING,
				self::STATUS_ACTIVE,
				self::STATUS_ARCHIVED,
				self::STATUS_REJECTED,
				self::STATUS_DELETED,
			);
		}

		/**
		 * Erlaubte Zielzustände je Ausgangszustand.
		 *
		 * @return array<string,string[]>
		 */
		public static function transitions(): array {
			return array(
				self::STATUS_PENDING  => array( self::STATUS_ACTIVE, self::STATUS_REJECTED, self::STATUS_DELETED ),
				self::STATUS_ACTIVE   => array( self::STATUS_ARCHIVED, self::STATUS_DELETED ),
				self::STATUS_ARCHIVED => array( self::STATUS_ACTIVE, self::STATUS_DELETED ),
				self::STATUS_REJECTED => array( self::STATUS_DELETED ),
				self::STATUS_DELETED  => array(),
			);
		}

		/**
		 * Prüft, ob ein Statuswert gültig ist.
		 *
		 * @param string $status Statuswert.
		 * @return bool
		 */
		public static function is_valid_status( string $status ): bool {
			return in_array( $status, self::all_statuses(), true );
		}

		/**
		 * Prüft, ob ein Übergang erlaubt ist.
		 *
		 * @param string $from Ausgangsstatus.
		 * @param string $to   Zielstatus.
		 * @return bool
		 */
		public static function can_transition( string $from, string $to ): bool {
			if ( ! self::is_valid_status( $from ) || ! self::is_valid_status( $to ) ) {
				return false;
			}

			if ( $from === $to ) {
				return false;
			}

			$map = self::transitions();
			return in_array( $to, $map[ $from ] ?? array(), true );
		}

		/**
		 * Gibt zurück, ob ein Space in diesem Status für Mitglieder nutzbar ist.
		 *
		 * @param string $status Statuswert.
		 * @return bool
		 */
		public static function is_accessible( string $status ): bool {
			return self::STATUS_ACTIVE === $status;
		}

		/**
		 * Gibt zurück, ob ein Space in diesem Status noch existiert (nicht endgültig entfernt).
		 *
		 * @param string $status Statuswert.
		 * @return bool
		 */
		public static function is_live( string $status ): bool {
			return self::STATUS_DELETED !== $status;
		}
	}
}
