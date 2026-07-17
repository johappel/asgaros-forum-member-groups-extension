<?php
/**
 * Domain-Modell für widerrufbare Einladungslinks.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Domain\\InviteLink' ) ) {

	/**
	 * Repräsentiert einen wiederverwendbaren Einladungslink.
	 */
	class InviteLink {

		public const STATUS_ACTIVE    = 'active';
		public const STATUS_REVOKED   = 'revoked';
		public const STATUS_EXPIRED   = 'expired';
		public const STATUS_EXHAUSTED = 'exhausted';

		public const MODE_AUTO_JOIN          = 'auto_join';
		public const MODE_APPROVAL_REQUIRED  = 'approval_required';
		public const MODE_EXISTING_USERS_ONLY = 'existing_users_only';

		public int $id;
		public int $space_id;
		public int $creator_user_id;
		public string $token_hash;
		public string $status;
		public string $approval_mode;
		public int $max_uses;
		public int $use_count;
		public bool $allow_registration;
		public string $expires_at;
		public string $created_at;
		public string $updated_at;
		public string $revoked_at;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data ) {
			$this->id                 = (int) ( $data['id'] ?? 0 );
			$this->space_id           = (int) ( $data['space_id'] ?? 0 );
			$this->creator_user_id    = (int) ( $data['creator_user_id'] ?? 0 );
			$this->token_hash         = (string) ( $data['token_hash'] ?? '' );
			$this->status             = (string) ( $data['status'] ?? self::STATUS_ACTIVE );
			$this->approval_mode      = (string) ( $data['approval_mode'] ?? self::MODE_AUTO_JOIN );
			$this->max_uses           = max( 0, (int) ( $data['max_uses'] ?? 1 ) );
			$this->use_count          = max( 0, (int) ( $data['use_count'] ?? 0 ) );
			$this->allow_registration = (bool) ( $data['allow_registration'] ?? false );
			$this->expires_at         = (string) ( $data['expires_at'] ?? '' );
			$this->created_at         = (string) ( $data['created_at'] ?? '' );
			$this->updated_at         = (string) ( $data['updated_at'] ?? '' );
			$this->revoked_at         = (string) ( $data['revoked_at'] ?? '' );
		}

		/**
		 * Liefert den effektiven Status aus Persistenzzustand, Ablauf und Nutzungslimit.
		 *
		 * @param string|null $now Vergleichszeitpunkt im mysql-Format.
		 * @return string
		 */
		public function effective_status( ?string $now = null ): string {
			if ( self::STATUS_REVOKED === $this->status ) {
				return self::STATUS_REVOKED;
			}

			$check_now = $now ?: (string) current_time( 'mysql' );
			if ( '' !== $this->expires_at && $this->expires_at < $check_now ) {
				return self::STATUS_EXPIRED;
			}

			if ( $this->has_usage_limit() && $this->use_count >= $this->max_uses ) {
				return self::STATUS_EXHAUSTED;
			}

			return self::STATUS_ACTIVE;
		}

		/**
		 * @return bool
		 */
		public function has_usage_limit(): bool {
			return $this->max_uses > 0;
		}

		/**
		 * @return bool
		 */
		public function allows_registration(): bool {
			return $this->allow_registration;
		}

		/**
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 * @throws DomainException Bei ungültigem Zustand.
		 */
		public function revoke( ?string $at = null ): void {
			if ( self::STATUS_REVOKED === $this->effective_status( $at ) ) {
				return;
			}

			if ( self::STATUS_ACTIVE !== $this->effective_status( $at ) ) {
				throw new DomainException( __( 'Einladungslink kann nicht widerrufen werden.', 'afspaces' ) );
			}

			$now = $at ?: (string) current_time( 'mysql' );
			$this->status     = self::STATUS_REVOKED;
			$this->revoked_at = $now;
			$this->updated_at = $now;
		}

		/**
		 * @param string|null $at Zeitpunkt im mysql-Format.
		 * @return void
		 * @throws DomainException Bei ungültigem Zustand.
		 */
		public function increment_use( ?string $at = null ): void {
			if ( self::STATUS_ACTIVE !== $this->effective_status( $at ) ) {
				throw new DomainException( __( 'Einladungslink ist nicht mehr nutzbar.', 'afspaces' ) );
			}

			++$this->use_count;
			$this->updated_at = $at ?: (string) current_time( 'mysql' );
		}
	}
}