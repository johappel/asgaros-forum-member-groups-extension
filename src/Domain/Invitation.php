<?php
/**
 * Domain-Modell Invitation.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Domain\\Invitation' ) ) {

	/**
	 * Persönliche Einladung zu einem Space.
	 */
	class Invitation {

		public const STATUS_PENDING  = 'pending';
		public const STATUS_ACCEPTED = 'accepted';
		public const STATUS_DECLINED = 'declined';
		public const STATUS_REVOKED  = 'revoked';
		public const STATUS_EXPIRED  = 'expired';

		public int $id;
		public int $space_id;
		public int $inviter_user_id;
		public int $invitee_user_id;
		public string $message;
		public string $status;
		public string $expires_at;
		public string $created_at;
		public string $updated_at;
		public string $accepted_at;
		public string $declined_at;
		public string $revoked_at;
		public string $last_sent_at;
		public int $send_count;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data ) {
			$this->id              = (int) ( $data['id'] ?? 0 );
			$this->space_id        = (int) ( $data['space_id'] ?? 0 );
			$this->inviter_user_id = (int) ( $data['inviter_user_id'] ?? 0 );
			$this->invitee_user_id = (int) ( $data['invitee_user_id'] ?? 0 );
			$this->message         = (string) ( $data['message'] ?? '' );
			$this->status          = (string) ( $data['status'] ?? self::STATUS_PENDING );
			$this->expires_at      = (string) ( $data['expires_at'] ?? '' );
			$this->created_at      = (string) ( $data['created_at'] ?? '' );
			$this->updated_at      = (string) ( $data['updated_at'] ?? '' );
			$this->accepted_at     = (string) ( $data['accepted_at'] ?? '' );
			$this->declined_at     = (string) ( $data['declined_at'] ?? '' );
			$this->revoked_at      = (string) ( $data['revoked_at'] ?? '' );
			$this->last_sent_at    = (string) ( $data['last_sent_at'] ?? '' );
			$this->send_count      = (int) ( $data['send_count'] ?? 0 );
		}

		/**
		 * Gibt den effektiven Status inklusive Ablauf zurück.
		 *
		 * @param string|null $now Zeitpunkt im mysql-Format.
		 * @return string
		 */
		public function effective_status( ?string $now = null ): string {
			$check_now = $now ?: current_time( 'mysql' );
			if ( self::STATUS_PENDING === $this->status && '' !== $this->expires_at && $this->expires_at < $check_now ) {
				return self::STATUS_EXPIRED;
			}
			return $this->status;
		}

		/**
		 * Markiert die Einladung als angenommen.
		 *
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 * @throws DomainException Bei ungültigem Übergang.
		 */
		public function accept( ?string $at = null ): void {
			if ( self::STATUS_ACCEPTED === $this->status ) {
				return;
			}
			if ( self::STATUS_PENDING !== $this->effective_status() ) {
				throw new DomainException( __( 'Einladung kann nicht angenommen werden.', 'afspaces' ) );
			}

			$now = $at ?: current_time( 'mysql' );
			$this->status      = self::STATUS_ACCEPTED;
			$this->accepted_at = $now;
			$this->updated_at  = $now;
		}

		/**
		 * Markiert die Einladung als abgelehnt.
		 *
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 * @throws DomainException Bei ungültigem Übergang.
		 */
		public function decline( ?string $at = null ): void {
			if ( self::STATUS_DECLINED === $this->status ) {
				return;
			}
			if ( self::STATUS_PENDING !== $this->effective_status() ) {
				throw new DomainException( __( 'Einladung kann nicht abgelehnt werden.', 'afspaces' ) );
			}

			$now = $at ?: current_time( 'mysql' );
			$this->status      = self::STATUS_DECLINED;
			$this->declined_at = $now;
			$this->updated_at  = $now;
		}

		/**
		 * Markiert die Einladung als widerrufen.
		 *
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 * @throws DomainException Bei ungültigem Übergang.
		 */
		public function revoke( ?string $at = null ): void {
			if ( self::STATUS_REVOKED === $this->status ) {
				return;
			}
			if ( self::STATUS_PENDING !== $this->effective_status() ) {
				throw new DomainException( __( 'Einladung kann nicht widerrufen werden.', 'afspaces' ) );
			}

			$now = $at ?: current_time( 'mysql' );
			$this->status      = self::STATUS_REVOKED;
			$this->revoked_at  = $now;
			$this->updated_at  = $now;
		}

		/**
		 * Persistiert den Ablauf als expliziten Status.
		 *
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 */
		public function expire( ?string $at = null ): void {
			if ( self::STATUS_PENDING !== $this->status ) {
				return;
			}

			$now = $at ?: current_time( 'mysql' );
			if ( '' !== $this->expires_at && $this->expires_at < $now ) {
				$this->status     = self::STATUS_EXPIRED;
				$this->updated_at = $now;
			}
		}
	}
}
