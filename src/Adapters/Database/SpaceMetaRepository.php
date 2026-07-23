<?php
/**
 * Persistenz fuer Arbeitsgruppen-Metadaten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\SpaceMetaRepository' ) ) {

	/**
	 * Speichert sichtbare Zusatzdaten fuer Spaces.
	 */
	class SpaceMetaRepository {

		/** @var \wpdb */
		private $db;

		private string $table;

		public function __construct() {
			global $wpdb;
			$this->db = $wpdb;
			$this->table = $wpdb->prefix . 'afspaces_space_meta';
		}

		/**
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();
			$sql = "CREATE TABLE {$this->table} (
				space_id int unsigned NOT NULL,
				description text NOT NULL,
				accent_color varchar(7) NOT NULL DEFAULT '#2d5d7f',
				icon varchar(40) NOT NULL DEFAULT 'users',
				contact_text text NOT NULL,
				directory_visibility varchar(20) NOT NULL DEFAULT 'listed',
				join_policy varchar(20) NOT NULL DEFAULT 'request',
				join_requests_enabled tinyint(1) NOT NULL DEFAULT 1,
				topic_ids longtext NOT NULL,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (space_id),
				KEY directory_visibility (directory_visibility),
				KEY join_policy (join_policy)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return WorkingGroupMeta
		 */
		public function get_for_space( int $space_id ): WorkingGroupMeta {
			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE space_id = %d;", $space_id ),
				ARRAY_A
			);

			if ( empty( $row ) ) {
				return WorkingGroupMeta::defaults_for_space( $space_id );
			}

			return new WorkingGroupMeta( $row );
		}

		/**
		 * @param int[] $space_ids Space-IDs.
		 * @return array<int,WorkingGroupMeta>
		 */
		public function list_for_spaces( array $space_ids ): array {
			$space_ids = array_values( array_filter( array_map( 'intval', $space_ids ), static fn( int $id ): bool => $id > 0 ) );
			if ( empty( $space_ids ) ) {
				return array();
			}

			$placeholders = implode( ', ', array_fill( 0, count( $space_ids ), '%d' ) );
			$rows = $this->db->get_results(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE space_id IN ({$placeholders});", $space_ids ),
				ARRAY_A
			);

			$meta_by_space = array();
			foreach ( $space_ids as $space_id ) {
				$meta_by_space[ $space_id ] = WorkingGroupMeta::defaults_for_space( $space_id );
			}

			foreach ( (array) $rows as $row ) {
				$meta = new WorkingGroupMeta( $row );
				$meta_by_space[ $meta->space_id ] = $meta;
			}

			return $meta_by_space;
		}

		/**
		 * @param WorkingGroupMeta $meta Modell.
		 * @return void
		 */
		public function save( WorkingGroupMeta $meta ): void {
			$this->db->replace(
				$this->table,
				array(
					'space_id'              => $meta->space_id,
					'description'           => $meta->description,
					'accent_color'          => $meta->accent_color,
					'icon'                  => $meta->icon,
					'contact_text'          => $meta->contact_text,
					'directory_visibility'  => $meta->directory_visibility,
					'join_policy'           => $meta->join_policy,
					'join_requests_enabled' => $meta->join_requests_enabled ? 1 : 0,
					'topic_ids'             => wp_json_encode( $meta->topic_ids ),
					'updated_at'            => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}
}