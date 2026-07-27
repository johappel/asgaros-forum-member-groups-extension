<?php
/**
 * Repository für den semantischen Suchindex.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\SearchIndexRepository' ) ) {

	/**
	 * Verwaltet die Tabelle mit Embeddings für die semantische Suche.
	 */
	class SearchIndexRepository {

		public const SOURCE_FORUM = 'forum';
		public const SOURCE_WP    = 'wp';

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
			$this->table = $prefix . 'afspaces_search_index';
		}

		/**
		 * Legt die Indextabelle an.
		 *
		 * @return void
		 */
		public function install(): void {
			$charset = $this->db->get_charset_collate();

			$sql = "CREATE TABLE {$this->table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source_type varchar(10) NOT NULL DEFAULT 'forum',
				source_id bigint(20) unsigned NOT NULL,
				topic_id bigint(20) unsigned NOT NULL DEFAULT 0,
				category_id bigint(20) unsigned NOT NULL DEFAULT 0,
				is_private tinyint(1) NOT NULL DEFAULT 0,
				title text NULL,
				context_label varchar(255) NOT NULL DEFAULT '',
				excerpt longtext NULL,
				author_name varchar(255) NOT NULL DEFAULT '',
				item_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				content_hash char(40) NOT NULL DEFAULT '',
				embedding longblob NULL,
				dims int unsigned NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				UNIQUE KEY source (source_type, source_id),
				KEY category_id (category_id),
				KEY source_type (source_type)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Entfernt die Indextabelle.
		 *
		 * @return void
		 */
		public function uninstall(): void {
			$this->db->query( "DROP TABLE IF EXISTS {$this->table}" );
		}

		/**
		 * Gibt den gespeicherten Inhalts-Hash eines Elements zurück.
		 *
		 * @param string $source_type Quelle.
		 * @param int    $source_id   Quell-ID.
		 * @return string Leerstring, wenn nicht vorhanden.
		 */
		public function get_hash( string $source_type, int $source_id ): string {
			return (string) $this->db->get_var(
				$this->db->prepare(
					"SELECT content_hash FROM {$this->table} WHERE source_type = %s AND source_id = %d",
					$source_type,
					$source_id
				)
			);
		}

		/**
		 * Legt einen Indexeintrag an oder aktualisiert ihn.
		 *
		 * @param array<string,mixed> $row Datensatz.
		 * @return void
		 */
		public function upsert( array $row ): void {
			$data = array(
				'source_type' => (string) ( $row['source_type'] ?? self::SOURCE_FORUM ),
				'source_id'   => (int) ( $row['source_id'] ?? 0 ),
				'topic_id'    => (int) ( $row['topic_id'] ?? 0 ),
				'category_id' => (int) ( $row['category_id'] ?? 0 ),
				'is_private'  => ! empty( $row['is_private'] ) ? 1 : 0,
				'title'       => (string) ( $row['title'] ?? '' ),
				'context_label' => (string) ( $row['context_label'] ?? '' ),
				'excerpt'     => (string) ( $row['excerpt'] ?? '' ),
				'author_name' => (string) ( $row['author_name'] ?? '' ),
				'item_date'   => (string) ( $row['item_date'] ?? current_time( 'mysql' ) ),
				'content_hash' => (string) ( $row['content_hash'] ?? '' ),
				'embedding'   => (string) ( $row['embedding'] ?? '' ),
				'dims'        => (int) ( $row['dims'] ?? 0 ),
				'updated_at'  => current_time( 'mysql' ),
			);
			$formats = array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

			$existing_id = (int) $this->db->get_var(
				$this->db->prepare(
					"SELECT id FROM {$this->table} WHERE source_type = %s AND source_id = %d",
					$data['source_type'],
					$data['source_id']
				)
			);

			if ( $existing_id > 0 ) {
				$this->db->update( $this->table, $data, array( 'id' => $existing_id ), $formats, array( '%d' ) );
			} else {
				$this->db->insert( $this->table, $data, $formats );
			}
		}

		/**
		 * Löscht einen Indexeintrag.
		 *
		 * @param string $source_type Quelle.
		 * @param int    $source_id   Quell-ID.
		 * @return void
		 */
		public function delete( string $source_type, int $source_id ): void {
			$this->db->delete(
				$this->table,
				array( 'source_type' => $source_type, 'source_id' => $source_id ),
				array( '%s', '%d' )
			);
		}

		/**
		 * Lädt Indexkandidaten (inklusive Embedding-Blobs) für die Vektorsuche.
		 *
		 * @param string[] $source_types Zu berücksichtigende Quellen.
		 * @return array<int,array<string,mixed>>
		 */
		public function get_candidates( array $source_types = array( self::SOURCE_FORUM, self::SOURCE_WP ) ): array {
			$source_types = array_values( array_filter( array_map( 'strval', $source_types ) ) );
			if ( empty( $source_types ) ) {
				return array();
			}

			$placeholders = implode( ',', array_fill( 0, count( $source_types ), '%s' ) );
			$sql          = "SELECT source_type, source_id, topic_id, category_id, is_private, title, context_label, excerpt, author_name, item_date, embedding, dims FROM {$this->table} WHERE embedding IS NOT NULL AND dims > 0 AND source_type IN ({$placeholders})";

			$rows = $this->db->get_results(
				$this->db->prepare( $sql, ...$source_types ),
				ARRAY_A
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * Zählt die indexierten Einträge (optional je Quelle).
		 *
		 * @param string|null $source_type Optionaler Quellfilter.
		 * @return int
		 */
		public function count( ?string $source_type = null ): int {
			if ( null === $source_type ) {
				return (int) $this->db->get_var( "SELECT COUNT(*) FROM {$this->table}" );
			}
			return (int) $this->db->get_var(
				$this->db->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE source_type = %s", $source_type )
			);
		}

		/**
		 * Leert den gesamten Index.
		 *
		 * @return void
		 */
		public function truncate(): void {
			$this->db->query( "TRUNCATE TABLE {$this->table}" );
		}

		/**
		 * Gibt den Tabellennamen zurück.
		 *
		 * @return string
		 */
		public function table_name(): string {
			return $this->table;
		}
	}
}
