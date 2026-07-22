<?php
/**
 * Domain-Modell für Beitrittsanfragen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Domain\\JoinRequest' ) ) {

	/**
	 * Repräsentiert eine Beitrittsanfrage zu einem Raum.
	 */
	class JoinRequest {

		public const STATUS_PENDING  = 'pending';
		public const STATUS_APPROVED = 'approved';
		public const STATUS_REJECTED = 'rejected';

		public int $id;
		public int $space_id;
		public int $requester_user_id;
		public string $request_message;
		public string $status;
		public int $decider_user_id;
		public string $decision_message;
		public string $approved_at;
		public string $rejected_at;
		public string $created_at;
		public string $updated_at;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data ) {
			$this->id               = (int) ( $data['id'] ?? 0 );
			$this->space_id         = (int) ( $data['space_id'] ?? 0 );
			$this->requester_user_id = (int) ( $data['requester_user_id'] ?? 0 );
			$this->request_message  = (string) ( $data['request_message'] ?? '' );
			$this->status           = (string) ( $data['status'] ?? self::STATUS_PENDING );
			$this->decider_user_id  = (int) ( $data['decider_user_id'] ?? 0 );
			$this->decision_message = (string) ( $data['decision_message'] ?? '' );
			$this->approved_at      = (string) ( $data['approved_at'] ?? '' );
			$this->rejected_at      = (string) ( $data['rejected_at'] ?? '' );
			$this->created_at       = (string) ( $data['created_at'] ?? '' );
			$this->updated_at       = (string) ( $data['updated_at'] ?? '' );
		}

		/**
		 * @param int $decider_user_id Entscheider.
		 * @param string $message Optionaler Entscheidungsgrund.
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 */
		public function approve( int $decider_user_id, string $message = '', ?string $at = null ): void {
			if ( self::STATUS_APPROVED === $this->status ) {
				return;
			}

			if ( self::STATUS_PENDING !== $this->status ) {
				throw new DomainException( __( 'Beitrittsanfrage kann nicht genehmigt werden.', 'afspaces' ) );
			}

			$now = $at ?: current_time( 'mysql' );
			$this->status           = self::STATUS_APPROVED;
			$this->decider_user_id  = $decider_user_id;
			$this->decision_message = $this->normalize_text( $message );
			$this->approved_at      = $now;
			$this->updated_at       = $now;
		}

		/**
		 * @param int $decider_user_id Entscheider.
		 * @param string $message Optionaler Entscheidungsgrund.
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 */
		public function reject( int $decider_user_id, string $message = '', ?string $at = null ): void {
			if ( self::STATUS_REJECTED === $this->status ) {
				return;
			}

			if ( self::STATUS_PENDING !== $this->status ) {
				throw new DomainException( __( 'Beitrittsanfrage kann nicht abgelehnt werden.', 'afspaces' ) );
			}

			$now = $at ?: current_time( 'mysql' );
			$this->status           = self::STATUS_REJECTED;
			$this->decider_user_id  = $decider_user_id;
			$this->decision_message = $this->normalize_text( $message );
			$this->rejected_at      = $now;
			$this->updated_at       = $now;
		}

		/**
		 * @param string $value Eingabetext.
		 * @return string
		 */
		private function normalize_text( string $value ): string {
			if ( function_exists( '\\sanitize_textarea_field' ) ) {
				return (string) \sanitize_textarea_field( $value );
			}

			return trim( $value );
		}
	}
}
