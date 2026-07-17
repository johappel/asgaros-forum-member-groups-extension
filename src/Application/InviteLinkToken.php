<?php
/**
 * Token-Helfer für Einladungslinks.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

if ( ! class_exists( 'AFSpaces\\Application\\InviteLinkToken' ) ) {

	/**
	 * Erzeugt, hasht und verifiziert Link-Tokens.
	 */
	class InviteLinkToken {

		/**
		 * @return string
		 */
		public function generate(): string {
			return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		}

		/**
		 * @param string $token Klartext-Token.
		 * @return string
		 */
		public function hash( string $token ): string {
			return hash_hmac( 'sha256', $token, wp_salt( 'afspaces_invite_link' ) );
		}

		/**
		 * @param string $token Klartext-Token.
		 * @param string $expected_hash Gespeicherter Hash.
		 * @return bool
		 */
		public function matches( string $token, string $expected_hash ): bool {
			return hash_equals( $expected_hash, $this->hash( $token ) );
		}
	}
}