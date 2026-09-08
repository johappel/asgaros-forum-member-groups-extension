<?php
/**
 * Datenbank-Repository für Arbeitsgruppen-Dokumente.
 *
 * Speichert ausschließlich die AFSpaces-Metadaten zu einem bestehenden
 * Asgaros-Anhang. Die Datei selbst bleibt unverändert im Asgaros-Upload.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Database;

use AFSpaces\Domain\SpaceDocument;

if ( ! class_exists( 'AFSpaces\\Adapters\\Database\\SpaceDocumentRepository' ) ) {

	/**
	 * Persistiert die Dokument-Metadaten der Arbeitsgruppen.
	 */
	class SpaceDocumentRepository {

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
			$this->table = $prefix . 'afspaces_space_documents';
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
				asgaros_post_id int unsigned NOT NULL,
				filename varchar(255) NOT NULL DEFAULT '',
				title varchar(200) NOT NULL DEFAULT '',
				document_topic varchar(120) NOT NULL DEFAULT '',
				visibility varchar(20) NOT NULL DEFAULT 'members',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				UNIQUE KEY space_post_file (space_id, asgaros_post_id, filename),
				KEY space_id (space_id),
				KEY asgaros_post_id (asgaros_post_id),
				KEY space_topic (space_id, document_topic),
				KEY space_created (space_id, created_at)
			) {$charset};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		/**
		 * Gibt alle Dokumente einer Arbeitsgruppe zurück (unsortiert; Filterung
		 * und Sortierung übernimmt der Dienst nach Sichtbarkeitsprüfung).
		 *
		 * @param int $space_id Space-ID.
		 * @return SpaceDocument[]
		 */
		public function list_for_space( int $space_id ): array {
			if ( $space_id < 1 ) {
				return array();
			}

			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d ORDER BY created_at DESC, id DESC;",
					$space_id
				),
				ARRAY_A
			);

			return array_map(
				static fn ( array $row ): SpaceDocument => new SpaceDocument( $row ),
				(array) $rows
			);
		}

		/**
		 * Gibt ein einzelnes Dokument zurück.
		 *
		 * @param int $document_id Dokument-ID.
		 * @return SpaceDocument|null
		 */
		public function get( int $document_id ): ?SpaceDocument {
			if ( $document_id < 1 ) {
				return null;
			}

			$row = $this->db->get_row(
				$this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d;", $document_id ),
				ARRAY_A
			);

			return $row ? new SpaceDocument( $row ) : null;
		}

		/**
		 * Sucht ein Dokument über seinen natürlichen Schlüssel.
		 *
		 * @param int    $space_id Space-ID.
		 * @param int    $post_id  Asgaros-Beitrags-ID.
		 * @param string $filename Dateiname.
		 * @return SpaceDocument|null
		 */
		public function find_by_natural_key( int $space_id, int $post_id, string $filename ): ?SpaceDocument {
			if ( $space_id < 1 || $post_id < 1 || '' === $filename ) {
				return null;
			}

			$row = $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d AND asgaros_post_id = %d AND filename = %s;",
					$space_id,
					$post_id,
					$filename
				),
				ARRAY_A
			);

			return $row ? new SpaceDocument( $row ) : null;
		}

		/**
		 * Gibt die Dokumente eines Beitrags in einer Arbeitsgruppe zurück
		 * (indiziert nach Dateiname).
		 *
		 * @param int $space_id Space-ID.
		 * @param int $post_id  Asgaros-Beitrags-ID.
		 * @return array<string,SpaceDocument>
		 */
		public function map_for_post( int $space_id, int $post_id ): array {
			if ( $space_id < 1 || $post_id < 1 ) {
				return array();
			}

			$rows = $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->table} WHERE space_id = %d AND asgaros_post_id = %d;",
					$space_id,
					$post_id
				),
				ARRAY_A
			);

			$map = array();
			foreach ( (array) $rows as $row ) {
				$doc                   = new SpaceDocument( $row );
				$map[ $doc->filename ] = $doc;
			}

			return $map;
		}

		/**
		 * Legt ein Dokument an.
		 *
		 * @param SpaceDocument $document Dokument-Modell (ohne id/Zeitstempel).
		 * @return int Neue Dokument-ID.
		 */
		public function create( SpaceDocument $document ): int {
			$now = current_time( 'mysql' );
			$this->db->insert(
				$this->table,
				array(
					'space_id'        => $document->space_id,
					'asgaros_post_id' => $document->asgaros_post_id,
					'filename'        => $document->filename,
					'title'           => $document->title,
					'document_topic'  => $document->document_topic,
					'visibility'      => $document->visibility,
					'created_by'      => $document->created_by,
					'created_at'      => $now,
					'updated_at'      => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);

			return (int) $this->db->insert_id;
		}

		/**
		 * Aktualisiert Titel, Thema und Sichtbarkeit eines Dokuments.
		 *
		 * @param SpaceDocument $document Dokument-Modell mit gültiger id.
		 * @return void
		 */
		public function update( SpaceDocument $document ): void {
			if ( $document->id < 1 ) {
				return;
			}

			$this->db->update(
				$this->table,
				array(
					'title'          => $document->title,
					'document_topic' => $document->document_topic,
					'visibility'     => $document->visibility,
					'updated_at'     => current_time( 'mysql' ),
				),
				array( 'id' => $document->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		/**
		 * Löscht ein Dokument (nur die Metadaten; die Datei bleibt bestehen).
		 *
		 * @param int $document_id Dokument-ID.
		 * @return void
		 */
		public function delete( int $document_id ): void {
			if ( $document_id < 1 ) {
				return;
			}

			$this->db->delete( $this->table, array( 'id' => $document_id ), array( '%d' ) );
		}

		/**
		 * Entfernt alle Dokument-Metadaten eines gelöschten Beitrags.
		 *
		 * @param int $post_id Asgaros-Beitrags-ID.
		 * @return void
		 */
		public function delete_by_post( int $post_id ): void {
			if ( $post_id < 1 ) {
				return;
			}

			$this->db->delete( $this->table, array( 'asgaros_post_id' => $post_id ), array( '%d' ) );
		}

		/**
		 * Entfernt alle Dokument-Metadaten einer Arbeitsgruppe.
		 *
		 * @param int $space_id Space-ID.
		 * @return void
		 */
		public function delete_by_space( int $space_id ): void {
			if ( $space_id < 1 ) {
				return;
			}

			$this->db->delete( $this->table, array( 'space_id' => $space_id ), array( '%d' ) );
		}

		/**
		 * Zählt die Dokumente einer Arbeitsgruppe.
		 *
		 * @param int $space_id Space-ID.
		 * @return int
		 */
		public function count_for_space( int $space_id ): int {
			if ( $space_id < 1 ) {
				return 0;
			}

			return (int) $this->db->get_var(
				$this->db->prepare( "SELECT COUNT(*) FROM {$this->table} WHERE space_id = %d;", $space_id )
			);
		}
	}
}
