<?php
/**
 * Audit-Log-Repository.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\AuditRepository' ) ) {

	/**
	 * Speichert und liest Audit-Ereignisse.
	 */
	class AuditRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		/**
		 * @var string
		 */
		private $table;

		/**
		 * Konstruktor.
		 */
		public function __construct() {
			global $wpdb;
			$this->db = $wpdb;
			$this->table = $wpdb->prefix . 'afspaces_audit';
		}

		/**
		 * Erstellt die Tabelle.
		 *
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();
			$sql = "CREATE TABLE {$this->table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				space_id int unsigned NOT NULL,
				actor_user_id bigint(20) unsigned NOT NULL,
				target_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(40) NOT NULL,
				object_type varchar(40) NOT NULL DEFAULT 'member',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY space_id (space_id),
				KEY created_at (created_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Protokolliert eine Aktion.
		 *
		 * @param int    $space_id       Space-ID.
		 * @param int    $actor_user_id  Akteur.
		 * @param int    $target_user_id Betroffener Benutzer.
		 * @param string $action         Aktion (z. B. 'member_added').
		 * @param string $object_type    Objekttyp (z. B. 'member').
		 * @return void
		 */
		public function log( int $space_id, int $actor_user_id, int $target_user_id, string $action, string $object_type = 'member' ): void {
			$this->db->insert(
				$this->table,
				array(
					'space_id'       => $space_id,
					'actor_user_id'  => $actor_user_id,
					'target_user_id' => $target_user_id,
					'action'         => $action,
					'object_type'    => $object_type,
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}

		/**
		 * Gibt die Einträge eines Spaces zurück (neueste zuerst).
		 *
		 * @param int $space_id Space-ID.
		 * @param int $limit    Maximale Anzahl.
		 * @return array<int,array<string,mixed>>
		 */
		public function list_for_space( int $space_id, int $limit = 50 ): array {
			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d ORDER BY created_at DESC LIMIT %d;",
					$space_id,
					$limit
				),
				ARRAY_A
			);
			return $rows ? (array) $rows : array();
		}
	}
}
