<?php
/**
 * Domain-Modell Space.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

if ( ! class_exists( 'AFSpaces\\Domain\\Space' ) ) {

	/**
	 * Verbindet ein Asgaros-Forum mit zugriffssteuernden Gruppen und Verantwortlichen.
	 */
	class Space {

		public int $id;
		public int $forum_id;
		public int $primary_group_id;
		public int $owner_user_id;
		public string $visibility;
		public string $status;
		public string $created_at;
		public string $updated_at;

		/**
		 * @param array<string,mixed> $data Rohdaten aus der Datenbank.
		 */
		public function __construct( array $data ) {
			$this->id               = (int) ( $data['id'] ?? 0 );
			$this->forum_id         = (int) ( $data['forum_id'] ?? 0 );
			$this->primary_group_id = (int) ( $data['primary_group_id'] ?? 0 );
			$this->owner_user_id    = (int) ( $data['owner_user_id'] ?? 0 );
			$this->visibility       = (string) ( $data['visibility'] ?? 'private' );
			$this->status           = (string) ( $data['status'] ?? 'active' );
			$this->created_at       = (string) ( $data['created_at'] ?? '' );
			$this->updated_at       = (string) ( $data['updated_at'] ?? '' );
		}

		/**
		 * Gibt das Modell als Array zurück.
		 *
		 * @return array<string,mixed>
		 */
		public function to_array(): array {
			return array(
				'id'               => $this->id,
				'forum_id'         => $this->forum_id,
				'primary_group_id' => $this->primary_group_id,
				'owner_user_id'    => $this->owner_user_id,
				'visibility'       => $this->visibility,
				'status'           => $this->status,
				'created_at'       => $this->created_at,
				'updated_at'       => $this->updated_at,
			);
		}
	}
}
