<?php
/**
 * Domain-Modell Space Manager (Raumverantwortliche).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\SpaceManager' ) ) {

	/**
	 * Rolle einer Person innerhalb eines einzelnen Spaces.
	 */
	class SpaceManager {

		public const ROLE_OWNER    = 'owner';
		public const ROLE_MANAGER  = 'manager';

		public int $space_id;
		public int $user_id;
		public string $role;

		/**
		 * @param array<string,mixed> $data Rohdaten.
		 */
		public function __construct( array $data ) {
			$this->space_id = (int) ( $data['space_id'] ?? 0 );
			$this->user_id  = (int) ( $data['user_id'] ?? 0 );
			$this->role     = (string) ( $data['role'] ?? self::ROLE_MANAGER );
		}

		/**
		 * @return array<string,mixed>
		 */
		public function to_array(): array {
			return array(
				'space_id' => $this->space_id,
				'user_id'  => $this->user_id,
				'role'     => $this->role,
			);
		}
	}
}
