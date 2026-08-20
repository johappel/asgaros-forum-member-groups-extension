<?php
/**
 * Deutsche Bezeichnungen für technische Statuswerte in der Oberfläche.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

if ( ! class_exists( 'AFSpaces\\Interface\\StatusLabels' ) ) {

	/**
	 * Übersetzt gespeicherte Statuswerte nur für die Benutzeroberfläche.
	 *
	 * Die technischen Werte bleiben für Persistenz, Vergleiche und REST-APIs
	 * unverändert.
	 */
	final class StatusLabels {

		/**
		 * @param string $status Technischer Beitrittsanfragenstatus.
		 * @return string
		 */
		public static function join_request( string $status ): string {
			return self::label(
				$status,
				array(
					'pending'  => __( 'Offen', 'afspaces' ),
					'approved' => __( 'Genehmigt', 'afspaces' ),
					'rejected' => __( 'Abgelehnt', 'afspaces' ),
				)
			);
		}

		/**
		 * @param string $status Technischer Einladungsstatus.
		 * @return string
		 */
		public static function invitation( string $status ): string {
			return self::label(
				$status,
				array(
					'pending'  => __( 'Ausstehend', 'afspaces' ),
					'accepted' => __( 'Angenommen', 'afspaces' ),
					'declined' => __( 'Abgelehnt', 'afspaces' ),
					'revoked'  => __( 'Widerrufen', 'afspaces' ),
					'expired'  => __( 'Abgelaufen', 'afspaces' ),
				)
			);
		}

		/**
		 * @param string $status Technischer Einladungslinkstatus.
		 * @return string
		 */
		public static function invite_link( string $status ): string {
			return self::label(
				$status,
				array(
					'active'    => __( 'Aktiv', 'afspaces' ),
					'revoked'   => __( 'Widerrufen', 'afspaces' ),
					'expired'   => __( 'Abgelaufen', 'afspaces' ),
					'exhausted' => __( 'Aufgebraucht', 'afspaces' ),
				)
			);
		}

		/**
		 * @param string $status Technischer Space-Lebenszyklusstatus.
		 * @return string
		 */
		public static function space( string $status ): string {
			return self::label(
				$status,
				array(
					'pending'  => __( 'Wartet auf Freigabe', 'afspaces' ),
					'active'   => __( 'Aktiv', 'afspaces' ),
					'archived' => __( 'Archiviert', 'afspaces' ),
					'rejected' => __( 'Abgelehnt', 'afspaces' ),
					'deleted'  => __( 'Gelöscht', 'afspaces' ),
				)
			);
		}

		/**
		 * Unbekannte Werte bleiben sichtbar, damit ein unerwarteter Status nicht
		 * stillschweigend verschwindet.
		 *
		 * @param string          $status Technischer Status.
		 * @param array<string,string> $labels Bekannte Beschriftungen.
		 * @return string
		 */
		private static function label( string $status, array $labels ): string {
			return $labels[ $status ] ?? $status;
		}
	}
}
