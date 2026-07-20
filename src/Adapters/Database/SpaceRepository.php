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

			// Bestehende Duplikate bereinigen, bevor ein Unique-Index auf forum_id gesetzt wird.
			$this->normalize_duplicate_forums();
			$this->ensure_forum_unique_index();
		}

		/**
		 * Legt einen Space an.
		 *
		 * @param Space $space Space-Modell (ohne id/zeitstempel).
		 * @return int Neue Space-ID.
		 */
		public function create_space( Space $space ): int {
			$existing = $this->get_space_by_forum( $space->forum_id );
			if ( $existing ) {
				$this->db->update(
					$this->spaces_table,
					array(
						'primary_group_id' => $space->primary_group_id,
						'owner_user_id'    => $space->owner_user_id,
						'visibility'       => $space->visibility,
						'status'           => $space->status,
						'updated_at'       => current_time( 'mysql' ),
					),
					array( 'id' => $existing->id ),
					array( '%d', '%d', '%s', '%s', '%s' ),
					array( '%d' )
				);
				return (int) $existing->id;
			}

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
		 * Entfernt einen Manager aus einem Space.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $user_id  Benutzer-ID.
		 * @return void
		 */
		public function remove_manager( int $space_id, int $user_id ): void {
			$this->db->delete(
				$this->managers_table,
				array(
					'space_id' => $space_id,
					'user_id'  => $user_id,
				),
				array( '%d', '%d' )
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
		 * Zählt die Spaces, in denen ein Benutzer eine Verantwortungsrolle hat.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		public function count_manager_spaces( int $user_id ): int {
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(DISTINCT space_id) FROM {$this->managers_table} WHERE user_id = %d;",
					$user_id
				)
			);
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

		/**
		 * Zählt alle Raumverantwortlichen (Owner + Manager) eines Spaces.
		 *
		 * @param int $space_id Space-ID.
		 * @return int
		 */
		public function count_responsibles( int $space_id ): int {
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->managers_table} WHERE space_id = %d AND role IN (%s, %s);",
					$space_id,
					SpaceManager::ROLE_OWNER,
					SpaceManager::ROLE_MANAGER
				)
			);
		}

		/**
		 * Setzt den Owner eines Spaces.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $user_id  Neue Owner-Benutzer-ID.
		 * @return void
		 */
		public function set_owner_user( int $space_id, int $user_id ): void {
			$this->db->update(
				$this->spaces_table,
				array(
					'owner_user_id' => $user_id,
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $space_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Führt vorhandene doppelte Spaces (pro forum_id) auf den ältesten Datensatz zusammen.
		 *
		 * @return void
		 */
		private function normalize_duplicate_forums(): void {
			$rows = $this->db->get_results(
				"SELECT forum_id, MIN(id) AS keep_id, GROUP_CONCAT(id ORDER BY id ASC) AS ids, COUNT(*) AS cnt
				 FROM {$this->spaces_table}
				 GROUP BY forum_id
				 HAVING COUNT(*) > 1;",
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				return;
			}

			$invite_table = $this->db->prefix . 'afspaces_invitations';
			$audit_table  = $this->db->prefix . 'afspaces_audit';

			foreach ( $rows as $row ) {
				$keep_id = (int) $row['keep_id'];
				$ids     = array_map( 'intval', explode( ',', (string) $row['ids'] ) );
				$dups    = array_values( array_filter( $ids, static fn( int $id ): bool => $id !== $keep_id ) );

				if ( empty( $dups ) ) {
					continue;
				}

				$in_placeholders = implode( ', ', array_fill( 0, count( $dups ), '%d' ) );

				// Manager-Mappings konfliktfrei auf den behaltenen Space umhängen.
				$insert_sql = $this->db->prepare(
					"INSERT IGNORE INTO {$this->managers_table} (space_id, user_id, role)
					 SELECT %d, user_id, role FROM {$this->managers_table} WHERE space_id IN ({$in_placeholders});",
					array_merge( array( $keep_id ), $dups )
				);
				$this->db->query( $insert_sql );

				$delete_manager_sql = $this->db->prepare(
					"DELETE FROM {$this->managers_table} WHERE space_id IN ({$in_placeholders});",
					$dups
				);
				$this->db->query( $delete_manager_sql );

				if ( $this->table_exists( $invite_table ) ) {
					$invite_sql = $this->db->prepare(
						"UPDATE {$invite_table} SET space_id = %d WHERE space_id IN ({$in_placeholders});",
						array_merge( array( $keep_id ), $dups )
					);
					$this->db->query( $invite_sql );
				}

				if ( $this->table_exists( $audit_table ) ) {
					$audit_sql = $this->db->prepare(
						"UPDATE {$audit_table} SET space_id = %d WHERE space_id IN ({$in_placeholders});",
						array_merge( array( $keep_id ), $dups )
					);
					$this->db->query( $audit_sql );
				}

				$delete_space_sql = $this->db->prepare(
					"DELETE FROM {$this->spaces_table} WHERE id IN ({$in_placeholders});",
					$dups
				);
				$this->db->query( $delete_space_sql );
			}
		}

		/**
		 * Stellt einen Unique-Index auf forum_id sicher.
		 *
		 * @return void
		 */
		private function ensure_forum_unique_index(): void {
			$table_name = $this->spaces_table;
			$has_index = (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s;",
					$table_name,
					'unique_forum_id'
				)
			);

			if ( $has_index > 0 ) {
				return;
			}

			$this->db->query( "ALTER TABLE {$this->spaces_table} ADD UNIQUE KEY unique_forum_id (forum_id);" );
		}

		/**
		 * Prüft, ob eine Tabelle existiert.
		 *
		 * @param string $table Tabellenname.
		 * @return bool
		 */
		private function table_exists( string $table ): bool {
			$like = $this->db->esc_like( $table );
			$found = $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $like ) );
			return (string) $found === $table;
		}
	}
}
