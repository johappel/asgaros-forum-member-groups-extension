<?php
/**
 * Datenbank-Repository für Spaces und Manager.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceManager;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\SpaceRepository' ) ) {

	/**
	 * Verwaltet die eigenen Plugin-Tabellen.
	 */
	class SpaceRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		/**
		 * @var string
		 */
		private $spaces_table;

		/**
		 * @var string
		 */
		private $managers_table;

		/**
		 * Konstruktor.
		 */
		public function __construct() {
			global $wpdb;
			$this->db = $wpdb;
			$prefix   = $wpdb ? $wpdb->prefix : 'wp_';
			$this->spaces_table   = $prefix . 'afspaces_spaces';
			$this->managers_table = $prefix . 'afspaces_space_managers';
		}

		/**
		 * Erstellt die Tabellen (wird bei Aktivierung aufgerufen).
		 *
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();

			$sql_spaces = "CREATE TABLE {$this->spaces_table} (
				id int unsigned NOT NULL AUTO_INCREMENT,
				forum_id int unsigned NOT NULL,
				primary_group_id int unsigned NOT NULL,
				owner_user_id bigint(20) unsigned NOT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'private',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY forum_id (forum_id),
				KEY owner_user_id (owner_user_id)
			) {$charset};";

			$sql_managers = "CREATE TABLE {$this->managers_table} (
				space_id int unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				role varchar(20) NOT NULL DEFAULT 'manager',
				PRIMARY KEY (space_id, user_id),
				KEY user_id (user_id)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql_spaces );
			dbDelta( $sql_managers );
		}

		/**
		 * Legt einen Space an.
		 *
		 * @param Space $space Space-Modell (ohne id/zeitstempel).
		 * @return int Neue Space-ID.
		 */
		public function create_space( Space $space ): int {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->spaces_table,
				array(
					'forum_id'         => $space->forum_id,
					'primary_group_id' => $space->primary_group_id,
					'owner_user_id'    => $space->owner_user_id,
					'visibility'       => $space->visibility,
					'status'           => $space->status,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
			return (int) $this->db->insert_id;
		}

		/**
		 * Gibt einen Space anhand der ID zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @return Space|null
		 */
		public function get_space( int $space_id ): ?Space {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->spaces_table} WHERE id = %d;", $space_id ),
				ARRAY_A
			);
			return $row ? new Space( $row ) : null;
		}

		/**
		 * Gibt einen Space anhand der Forum-ID zurück.
		 *
		 * @param int $forum_id Forum-ID.
		 * @return Space|null
		 */
		public function get_space_by_forum( int $forum_id ): ?Space {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->spaces_table} WHERE forum_id = %d;", $forum_id ),
				ARRAY_A
			);
			return $row ? new Space( $row ) : null;
		}

		/**
		 * Listet alle Spaces.
		 *
		 * @return Space[]
		 */
		public function list_spaces(): array {
			$rows = $this->db->get_results( "SELECT * FROM {$this->spaces_table} ORDER BY id ASC;", ARRAY_A );
			if ( empty( $rows ) ) {
				return array();
			}
			return array_map( static fn( $r ) => new Space( $r ), $rows );
		}

		/**
		 * Fügt einen Manager hinzu.
		 *
		 * @param SpaceManager $manager Manager-Modell.
		 * @return void
		 */
		public function add_manager( SpaceManager $manager ): void {
			$this->db->replace(
				$this->managers_table,
				array(
					'space_id' => $manager->space_id,
					'user_id'  => $manager->user_id,
					'role'     => $manager->role,
				),
				array( '%d', '%d', '%s' )
			);
		}

		/**
		 * Gibt die Manager eines Spaces zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @return SpaceManager[]
		 */
		public function get_managers( int $space_id ): array {
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$this->managers_table} WHERE space_id = %d;", $space_id ),
				ARRAY_A
			);
			if ( empty( $rows ) ) {
				return array();
			}
			return array_map( static fn( $r ) => new SpaceManager( $r ), $rows );
		}

		/**
		 * Prüft, ob ein Benutzer Manager (oder Owner) eines Spaces ist.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $user_id  Benutzer-ID.
		 * @return bool
		 */
		public function is_manager( int $space_id, int $user_id ): bool {
			$count = (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->managers_table} WHERE space_id = %d AND user_id = %d;",
					$space_id,
					$user_id
				)
			);
			return $count > 0;
		}

		/**
		 * Zählt die Owner eines Spaces.
		 *
		 * @param int $space_id Space-ID.
		 * @return int
		 */
		public function count_owners( int $space_id ): int {
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->managers_table} WHERE space_id = %d AND role = %s;",
					$space_id,
					SpaceManager::ROLE_OWNER
				)
			);
		}
	}
}
