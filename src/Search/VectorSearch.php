<?php
/**
 * Semantische Suche über den Embedding-Index.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SearchIndexRepository;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Search\\VectorSearch' ) ) {

	/**
	 * Findet semantisch ähnliche Foren- und WP-Inhalte.
	 *
	 * Die Zugriffsprüfung erfolgt bei jeder Abfrage live: Forentreffer werden
	 * nur zurückgegeben, wenn ihre Kategorie für den aktuellen Benutzer
	 * zugänglich ist. Private Inhalte erscheinen nur, wenn sie überhaupt
	 * indexiert wurden (Opt-in) und die Kategorie zugänglich ist.
	 */
	final class VectorSearch {

		private SearchIndexRepository $index;
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Konstruktor.
		 *
		 * @param SearchIndexRepository   $index   Index-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 */
		public function __construct( SearchIndexRepository $index, AsgarosAdapterInterface $asgaros ) {
			$this->index   = $index;
			$this->asgaros = $asgaros;
		}

		/**
		 * Führt eine semantische Suche aus.
		 *
		 * @param string   $query        Suchbegriff.
		 * @param string[] $source_types Zu durchsuchende Quellen.
		 * @param int      $limit        Maximale Trefferzahl.
		 * @return array<int,array{key:string,score:float,hit:SearchHit}>
		 */
		public function search( string $query, array $source_types, int $limit = 50 ): array {
			$query = trim( $query );
			if ( '' === $query || ! SearchSettings::is_semantic_enabled() ) {
				return array();
			}

			try {
				$vector = EmbeddingClient::from_settings()->embed_one( $query );
			} catch ( DomainException $e ) {
				return array();
			}
			if ( empty( $vector ) ) {
				return array();
			}
			$query_vec = VectorMath::normalize( $vector );

			$accessible_categories = array_flip( $this->asgaros->list_accessible_category_ids() );
			$candidates            = $this->index->get_candidates( $source_types );

			$scored = array();
			foreach ( $candidates as $row ) {
				$source_type = (string) $row['source_type'];
				$source_id   = (int) $row['source_id'];

				if ( SearchIndexRepository::SOURCE_FORUM === $source_type ) {
					$category_id = (int) $row['category_id'];
					if ( ! isset( $accessible_categories[ $category_id ] ) ) {
						continue;
					}
				}

				$candidate_vec = VectorMath::unpack_vector( (string) $row['embedding'] );
				if ( count( $candidate_vec ) !== count( $query_vec ) ) {
					continue;
				}

				$score = VectorMath::dot( $query_vec, $candidate_vec );

				$scored[] = array(
					'key'   => $source_type . ':' . $source_id,
					'score' => $score,
					'row'   => $row,
				);
			}

			usort(
				$scored,
				static function ( array $a, array $b ): int {
					if ( $a['score'] === $b['score'] ) {
						return 0;
					}
					return ( $a['score'] < $b['score'] ) ? 1 : -1;
				}
			);

			$scored = array_slice( $scored, 0, max( 1, $limit ) );

			$result = array();
			foreach ( $scored as $entry ) {
				$result[] = array(
					'key'   => $entry['key'],
					'score' => (float) $entry['score'],
					'hit'   => $this->to_hit( $entry['row'], $query ),
				);
			}

			return $result;
		}

		/**
		 * Baut aus einer Indexzeile einen darstellbaren Treffer.
		 *
		 * @param array<string,mixed> $row   Indexzeile.
		 * @param string              $query Suchbegriff (für Highlight).
		 * @return SearchHit
		 */
		private function to_hit( array $row, string $query ): SearchHit {
			$source_type = (string) $row['source_type'];
			$source_id   = (int) $row['source_id'];
			$title       = (string) ( $row['title'] ?? '' );
			$excerpt     = (string) ( $row['excerpt'] ?? '' );
			$snippet     = SnippetBuilder::build( $excerpt, $query );
			$date        = $this->format_date( (string) ( $row['item_date'] ?? '' ) );

			if ( SearchIndexRepository::SOURCE_FORUM === $source_type ) {
				$url    = $this->asgaros->get_post_link( $source_id, (int) $row['topic_id'] );
				$source = SearchHit::SOURCE_FORUM;
			} else {
				$url    = function_exists( 'get_permalink' ) ? (string) get_permalink( $source_id ) : '';
				$source = SearchHit::SOURCE_WP;
			}

			return new SearchHit(
				$source,
				'' !== $title ? $title : __( 'Treffer', 'afspaces' ),
				$url,
				$snippet,
				(string) ( $row['author_name'] ?? '' ),
				$date,
				(string) ( $row['context_label'] ?? '' ),
				0.0,
				$source_type . ':' . $source_id
			);
		}

		/**
		 * Formatiert ein Datum lokalisiert.
		 *
		 * @param string $mysql_date MySQL-Datum.
		 * @return string
		 */
		private function format_date( string $mysql_date ): string {
			if ( '' === $mysql_date ) {
				return '';
			}
			$timestamp = strtotime( $mysql_date );
			if ( false === $timestamp ) {
				return '';
			}
			if ( function_exists( 'date_i18n' ) && function_exists( 'get_option' ) ) {
				return (string) date_i18n( (string) get_option( 'date_format', 'd.m.Y' ), $timestamp );
			}
			return gmdate( 'd.m.Y', $timestamp );
		}
	}
}
