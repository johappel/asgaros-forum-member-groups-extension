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
		 * @var string
		 */
		private $forums_table;

		/**
		 * Konstruktor.
		 */
		public function __construct() {
			global $wpdb;
			$this->db = $wpdb;
			$prefix   = $wpdb ? $wpdb->prefix : 'wp_';
			$this->spaces_table   = $prefix . 'afspaces_spaces';
			$this->managers_table = $prefix . 'afspaces_space_managers';
			$this->forums_table   = $prefix . 'afspaces_space_forums';
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
				rejection_reason text NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY forum_id (forum_id),
				KEY owner_user_id (owner_user_id),
				KEY status (status)
			) {$charset};";

			$sql_managers = "CREATE TABLE {$this->managers_table} (
				space_id int unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				role varchar(20) NOT NULL DEFAULT 'manager',
				PRIMARY KEY (space_id, user_id),
				KEY user_id (user_id)
			) {$charset};";

			$sql_forums = "CREATE TABLE {$this->forums_table} (
				space_id int unsigned NOT NULL,
				forum_id int unsigned NOT NULL,
				is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY (space_id, forum_id),
				UNIQUE KEY unique_forum_id (forum_id),
				KEY space_id (space_id)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql_spaces );
			dbDelta( $sql_managers );
			dbDelta( $sql_forums );

			// Bestehende Duplikate bereinigen, bevor ein Unique-Index auf forum_id gesetzt wird.
			$this->normalize_duplicate_forums();
			$this->backfill_primary_forum_mappings();
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
				$this->add_forum_to_space( (int) $existing->id, $space->forum_id, true );
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
					'rejection_reason' => $space->rejection_reason,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$space_id = (int) $this->db->insert_id;
			$this->add_forum_to_space( $space_id, $space->forum_id, true );
			return $space_id;
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
			if ( ! $row ) {
				$row = $this->db->get_row(
					$this->db->prepare(
						"SELECT s.* FROM {$this->spaces_table} s INNER JOIN {$this->forums_table} f ON f.space_id = s.id WHERE f.forum_id = %d;",
						$forum_id
					),
					ARRAY_A
				);
			}
			return $row ? new Space( $row ) : null;
		}

		/**
		 * Gibt alle eindeutig dieser Arbeitsgruppe zugeordneten Foren zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @return int[]
		 */
		public function list_forum_ids( int $space_id ): array {
			$rows = $this->db->get_col(
				$this->db->prepare( "SELECT forum_id FROM {$this->forums_table} WHERE space_id = %d ORDER BY is_primary DESC, forum_id ASC;", $space_id )
			);
			return array_values( array_map( 'intval', (array) $rows ) );
		}

		/**
		 * Ordnet ein Forum einer Arbeitsgruppe zu.
		 *
		 * @param int  $space_id   Space-ID.
		 * @param int  $forum_id   Asgaros-Forum-ID.
		 * @param bool $is_primary Primärforum markieren.
		 * @return void
		 */
		public function add_forum_to_space( int $space_id, int $forum_id, bool $is_primary = false ): void {
			if ( $space_id < 1 || $forum_id < 1 ) {
				return;
			}
			$this->db->replace(
				$this->forums_table,
				array(
					'space_id'   => $space_id,
					'forum_id'   => $forum_id,
					'is_primary' => $is_primary ? 1 : 0,
				),
				array( '%d', '%d', '%d' )
			);
		}

		/**
		 * Prüft die Forum-Zuordnung ohne Vertrauen in vom Client gelieferte IDs.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $forum_id Forum-ID.
		 * @return bool
		 */
		public function is_forum_in_space( int $space_id, int $forum_id ): bool {
			$count = (int) $this->db->get_var(
				$this->db->prepare( "SELECT COUNT(*) FROM {$this->forums_table} WHERE space_id = %d AND forum_id = %d;", $space_id, $forum_id )
			);
			return $count > 0;
		}

		/**
		 * Entfernt eine zusätzliche Forum-Zuordnung aus einem Space.
		 * Das Primärforum wird absichtlich nicht über diese Methode entfernt.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $forum_id Forum-ID.
		 * @return void
		 */
		public function remove_forum_from_space( int $space_id, int $forum_id ): void {
			$this->db->query(
				$this->db->prepare(
					"DELETE FROM {$this->forums_table} WHERE space_id = %d AND forum_id = %d AND is_primary = 0;",
					$space_id,
					$forum_id
				)
			);
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
		 * Gibt alle Space-IDs zurück, in denen ein Benutzer Verantwortung trägt.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int[]
		 */
		public function list_manager_space_ids( int $user_id ): array {
			$rows = $this->db->get_col(
				$this->db->prepare(
					"SELECT DISTINCT space_id FROM {$this->managers_table} WHERE user_id = %d ORDER BY space_id ASC;",
					$user_id
				)
			);

			if ( empty( $rows ) ) {
				return array();
			}

			return array_values( array_map( 'intval', $rows ) );
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
		 * Setzt den Status eines Spaces.
		 *
		 * @param int    $space_id Space-ID.
		 * @param string $status   Neuer Status.
		 * @return void
		 */
		public function update_status( int $space_id, string $status ): void {
			$this->db->update(
				$this->spaces_table,
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $space_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Setzt die Sichtbarkeit eines Spaces.
		 *
		 * @param int    $space_id   Space-ID.
		 * @param string $visibility Neue Sichtbarkeit.
		 * @return void
		 */
		public function update_visibility( int $space_id, string $visibility ): void {
			$this->db->update(
				$this->spaces_table,
				array(
					'visibility' => $visibility,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $space_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Speichert die Ablehnungsbegründung eines Spaces.
		 *
		 * @param int    $space_id Space-ID.
		 * @param string $reason   Begründung.
		 * @return void
		 */
		public function set_rejection_reason( int $space_id, string $reason ): void {
			$this->db->update(
				$this->spaces_table,
				array( 'rejection_reason' => $reason ),
				array( 'id' => $space_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Löscht einen Space-Datensatz inklusive Manager-Zuordnungen.
		 *
		 * @param int $space_id Space-ID.
		 * @return void
		 */
		public function delete_space( int $space_id ): void {
			$this->db->delete( $this->managers_table, array( 'space_id' => $space_id ), array( '%d' ) );
			$this->db->delete( $this->spaces_table, array( 'id' => $space_id ), array( '%d' ) );
		}

		/**
		 * Listet Spaces nach Status.
		 *
		 * @param string $status Status.
		 * @return Space[]
		 */
		public function list_spaces_by_status( string $status ): array {
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$this->spaces_table} WHERE status = %s ORDER BY created_at ASC, id ASC;", $status ),
				ARRAY_A
			);
			if ( empty( $rows ) ) {
				return array();
			}
			return array_map( static fn( $r ) => new Space( $r ), $rows );
		}

		/**
		 * Zählt Spaces mit einem Status, ohne die vollständigen Datensätze zu laden.
		 *
		 * @param string $status Status.
		 * @return int
		 */
		public function count_spaces_by_status( string $status ): int {
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->spaces_table} WHERE status = %s;",
					$status
				)
			);
		}

		/**
		 * Zählt die noch bestehenden (nicht abgelehnten/gelöschten) Räume eines Eigentümers.
		 *
		 * Diese Zählung dient dem Raumlimit und wird atomar mit einer einzigen
		 * Abfrage ermittelt, damit parallele Requests das Limit nicht umgehen.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return int
		 */
		public function count_owner_live_spaces( int $user_id ): int {
			return (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT COUNT(*) FROM {$this->spaces_table} WHERE owner_user_id = %d AND status IN (%s, %s, %s);",
					$user_id,
					'pending',
					'active',
					'archived'
				)
			);
		}

		/**
		 * Gibt den Zeitstempel der jüngsten Raumgründung eines Benutzers zurück.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return string|null MySQL-Datetime oder null.
		 */
		public function latest_created_at_for_owner( int $user_id ): ?string {
			$value = $this->db->get_var(
				$this->db->prepare(
					"SELECT MAX(created_at) FROM {$this->spaces_table} WHERE owner_user_id = %d;",
					$user_id
				)
			);
			return ( null === $value || '' === (string) $value ) ? null : (string) $value;
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
		 * Übernimmt bestehende Primärforen in die neue Zuordnungstabelle.
		 *
		 * @return void
		 */
		private function backfill_primary_forum_mappings(): void {
			$this->db->query(
				"INSERT IGNORE INTO {$this->forums_table} (space_id, forum_id, is_primary)
				 SELECT id, forum_id, 1 FROM {$this->spaces_table};"
			);
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
