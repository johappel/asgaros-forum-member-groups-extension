<?php
/**
 * Datenbank-Repository für Toolbox-Links einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\SpaceLink;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\SpaceLinkRepository' ) ) {

	/**
	 * Persistiert die konfigurierten Links des Werkzeugkastens.
	 */
	class SpaceLinkRepository {

		/**
		 * @var \wpdb
		 */
		private $db;

		/**
		 * @var string
		 */
		private string $table;

		/**
		 * Konstruktor.
		 */
		public function __construct() {
			global $wpdb;
			$this->db    = $wpdb;
			$prefix      = $wpdb ? $wpdb->prefix : 'wp_';
			$this->table = $prefix . 'afspaces_space_links';
		}

		/**
		 * Legt die Tabelle an (Aktivierung/Upgrade).
		 *
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();

			$sql = "CREATE TABLE {$this->table} (
				id int unsigned NOT NULL AUTO_INCREMENT,
				space_id int unsigned NOT NULL,
				title varchar(200) NOT NULL DEFAULT '',
				url text NOT NULL,
				description text NOT NULL,
				icon varchar(40) NOT NULL DEFAULT '',
				sort_order int unsigned NOT NULL DEFAULT 0,
				open_new_tab tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY space_id (space_id),
				KEY space_sort (space_id, sort_order)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Gibt alle Links einer Arbeitsgruppe in Anzeigereihenfolge zurück.
		 *
		 * @param int $space_id Space-ID.
		 * @return SpaceLink[]
		 */
		public function list_for_space( int $space_id ): array {
			if ( $space_id < 1 ) {
				return array();
			}

			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d ORDER BY sort_order ASC, id ASC;",
					$space_id
				),
				ARRAY_A
			);

			return array_map(
				static fn ( array $row ): SpaceLink => new SpaceLink( $row ),
				(array) $rows
			);
		}

		/**
		 * Gibt einen einzelnen Link zurück.
		 *
		 * @param int $link_id Link-ID.
		 * @return SpaceLink|null
		 */
		public function get_link( int $link_id ): ?SpaceLink {
			if ( $link_id < 1 ) {
				return null;
			}

			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d;", $link_id ),
				ARRAY_A
			);

			return $row ? new SpaceLink( $row ) : null;
		}

		/**
		 * Legt einen Link an.
		 *
		 * @param SpaceLink $link Link-Modell (ohne id/Zeitstempel).
		 * @return int Neue Link-ID.
		 */
		public function create( SpaceLink $link ): int {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->table,
				array(
					'space_id'     => $link->space_id,
					'title'        => $link->title,
					'url'          => $link->url,
					'description'  => $link->description,
					'icon'         => $link->icon,
					'sort_order'   => $link->sort_order,
					'open_new_tab' => $link->open_new_tab ? 1 : 0,
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);

			return (int) $this->db->insert_id;
		}

		/**
		 * Aktualisiert einen bestehenden Link.
		 *
		 * @param SpaceLink $link Link-Modell mit gültiger id.
		 * @return void
		 */
		public function update( SpaceLink $link ): void {
			if ( $link->id < 1 ) {
				return;
			}

			$this->db->update(
				$this->table,
				array(
					'title'        => $link->title,
					'url'          => $link->url,
					'description'  => $link->description,
					'icon'         => $link->icon,
					'sort_order'   => $link->sort_order,
					'open_new_tab' => $link->open_new_tab ? 1 : 0,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $link->id ),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Setzt die Sortierreihenfolge eines Links.
		 *
		 * @param int $link_id    Link-ID.
		 * @param int $sort_order Neue Position.
		 * @return void
		 */
		public function set_sort_order( int $link_id, int $sort_order ): void {
			if ( $link_id < 1 ) {
				return;
			}

			$this->db->update(
				$this->table,
				array(
					'sort_order' => max( 0, $sort_order ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $link_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Löscht einen Link.
		 *
		 * @param int $link_id Link-ID.
		 * @return void
		 */
		public function delete( int $link_id ): void {
			if ( $link_id < 1 ) {
				return;
			}

			$this->db->delete( $this->table, array( 'id' => $link_id ), array( '%d' ) );
		}

		/**
		 * Löscht alle Links einer Arbeitsgruppe (z. B. bei Space-Löschung).
		 *
		 * @param int $space_id Space-ID.
		 * @return void
		 */
		public function delete_for_space( int $space_id ): void {
			if ( $space_id < 1 ) {
				return;
			}

			$this->db->delete( $this->table, array( 'space_id' => $space_id ), array( '%d' ) );
		}

		/**
		 * Höchste vergebene Sortierposition einer Arbeitsgruppe.
		 *
		 * @param int $space_id Space-ID.
		 * @return int
		 */
		public function max_sort_order( int $space_id ): int {
			if ( $space_id < 1 ) {
				return 0;
			}

			return (int) $this->db->get_var(
				$this->db->prepare( "SELECT MAX(sort_order) FROM {$this->table} WHERE space_id = %d;", $space_id )
			);
		}
	}
}
