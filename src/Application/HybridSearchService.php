<?php
/**
 * Hybride Suche: Fusion aus Keyword- (Foren + WP) und semantischer Suche.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Search\ResultFusion;
use AFSpaces\Search\SearchHit;
use AFSpaces\Search\SearchSettings;
use AFSpaces\Search\VectorSearch;
use AFSpaces\Search\WpPostSearch;
use AFSpaces\Adapters\Database\SearchIndexRepository;

if ( ! class_exists( 'AFSpaces\\Application\\HybridSearchService' ) ) {

	/**
	 * Führt Forensuche, WP-Beitragssuche und semantische Suche zusammen.
	 *
	 * Für eine einzelne Quelle ohne Semantik wird direkt der jeweilige Dienst
	 * mit echter SQL-Paginierung genutzt. Sobald mehrere Quellen oder die
	 * semantische Suche beteiligt sind, werden Kandidatenfenster je Quelle
	 * geladen und per Reciprocal Rank Fusion zusammengeführt.
	 */
	class HybridSearchService {

		public const SCOPE_ALL   = 'all';
		public const SCOPE_FORUM = 'forum';
		public const SCOPE_WP    = 'wp';

		/**
		 * Kandidatenfenster je Quelle bei der Fusion.
		 */
		private const WINDOW = 60;

		private ForumSearchService $forum;
		private WpPostSearch $wp;
		private VectorSearch $vector;

		/**
		 * Konstruktor.
		 *
		 * @param ForumSearchService $forum  Forensuche.
		 * @param WpPostSearch       $wp     WP-Beitragssuche.
		 * @param VectorSearch       $vector Semantische Suche.
		 */
		public function __construct( ForumSearchService $forum, WpPostSearch $wp, VectorSearch $vector ) {
			$this->forum  = $forum;
			$this->wp     = $wp;
			$this->vector = $vector;
		}

		/**
		 * Führt eine hybride Suche aus.
		 *
		 * @param string              $query Suchbegriff.
		 * @param array<string,mixed> $opts  Optionen: scope, sort, semantic, page, per_page.
		 * @return array{hits: SearchHit[], total: int, page: int, per_page: int, total_pages: int, query: string, scope: string, semantic_used: bool}
		 */
		public function search( string $query, array $opts = array() ): array {
			$query    = trim( $query );
			$scope    = $this->normalize_scope( (string) ( $opts['scope'] ?? self::SCOPE_ALL ) );
			$sort     = ( isset( $opts['sort'] ) && 'date' === $opts['sort'] ) ? 'date' : 'relevance';
			$page     = max( 1, (int) ( $opts['page'] ?? 1 ) );
			$per_page = max( 1, min( 50, (int) ( $opts['per_page'] ?? 10 ) ) );
			$semantic = ! empty( $opts['semantic'] ) && SearchSettings::is_semantic_enabled();

			$empty = array(
				'hits'          => array(),
				'total'         => 0,
				'page'          => $page,
				'per_page'      => $per_page,
				'total_pages'   => 0,
				'query'         => $query,
				'scope'         => $scope,
				'semantic_used' => $semantic,
			);

			if ( '' === $query ) {
				return $empty;
			}

			// Einzelquelle ohne Semantik: echte SQL-Paginierung nutzen.
			if ( ! $semantic && self::SCOPE_FORUM === $scope ) {
				return $this->wrap( $this->forum->search( $query, $sort, $page, $per_page ), $scope, false );
			}
			if ( ! $semantic && self::SCOPE_WP === $scope ) {
				$wp = $this->wp->search( $query, $sort, $page, $per_page );
				return array(
					'hits'          => $wp['hits'],
					'total'         => (int) $wp['total'],
					'page'          => $page,
					'per_page'      => $per_page,
					'total_pages'   => (int) ceil( max( 0, (int) $wp['total'] ) / $per_page ),
					'query'         => $query,
					'scope'         => $scope,
					'semantic_used' => false,
				);
			}

			// Fusionspfad.
			$lists   = array();
			$hit_map = array();

			$keyword_weight  = SearchSettings::keyword_weight();
			$semantic_weight = SearchSettings::semantic_weight();

			if ( self::SCOPE_WP !== $scope ) {
				$forum_result = $this->forum->search( $query, $sort, 1, self::WINDOW );
				$keys         = array();
				foreach ( $forum_result['hits'] as $hit ) {
					$hit_map[ $hit->key ] = $hit;
					$keys[]               = $hit->key;
				}
				$lists[] = array( 'keys' => $keys, 'weight' => $keyword_weight );
			}

			if ( self::SCOPE_FORUM !== $scope ) {
				$wp_result = $this->wp->search( $query, $sort, 1, self::WINDOW );
				$keys      = array();
				foreach ( $wp_result['hits'] as $hit ) {
					$hit_map[ $hit->key ] = $hit;
					$keys[]               = $hit->key;
				}
				$lists[] = array( 'keys' => $keys, 'weight' => $keyword_weight );
			}

			if ( $semantic ) {
				$source_types = $this->scope_source_types( $scope );
				$semantic_res = $this->vector->search( $query, $source_types, self::WINDOW );
				$keys         = array();
				foreach ( $semantic_res as $entry ) {
					$key = (string) $entry['key'];
					// Keyword-Treffer (frischer Deep-Link/Snippet) bevorzugen.
					if ( ! isset( $hit_map[ $key ] ) ) {
						$hit_map[ $key ] = $entry['hit'];
					}
					$keys[] = $key;
				}
				$lists[] = array( 'keys' => $keys, 'weight' => $semantic_weight );
			}

			$fused       = ResultFusion::fuse( $lists );
			$total       = count( $fused );
			$offset      = ( $page - 1 ) * $per_page;
			$page_slice  = array_slice( $fused, $offset, $per_page );

			$hits = array();
			foreach ( $page_slice as $entry ) {
				$key = (string) $entry['key'];
				if ( isset( $hit_map[ $key ] ) ) {
					$hit        = $hit_map[ $key ];
					$hit->score = (float) $entry['score'];
					$hits[]     = $hit;
				}
			}

			return array(
				'hits'          => $hits,
				'total'         => $total,
				'page'          => $page,
				'per_page'      => $per_page,
				'total_pages'   => (int) ceil( $total / $per_page ),
				'query'         => $query,
				'scope'         => $scope,
				'semantic_used' => $semantic,
			);
		}

		/**
		 * Normalisiert den Suchbereich.
		 *
		 * @param string $scope Rohwert.
		 * @return string
		 */
		private function normalize_scope( string $scope ): string {
			return in_array( $scope, array( self::SCOPE_ALL, self::SCOPE_FORUM, self::SCOPE_WP ), true )
				? $scope
				: self::SCOPE_ALL;
		}

		/**
		 * Quelltypen für die Vektorsuche je Suchbereich.
		 *
		 * @param string $scope Suchbereich.
		 * @return string[]
		 */
		private function scope_source_types( string $scope ): array {
			if ( self::SCOPE_FORUM === $scope ) {
				return array( SearchIndexRepository::SOURCE_FORUM );
			}
			if ( self::SCOPE_WP === $scope ) {
				return array( SearchIndexRepository::SOURCE_WP );
			}
			return array( SearchIndexRepository::SOURCE_FORUM, SearchIndexRepository::SOURCE_WP );
		}

		/**
		 * Ergänzt ein ForumSearchService-Ergebnis um die Hybrid-Metadaten.
		 *
		 * @param array<string,mixed> $result   Forensuchergebnis.
		 * @param string              $scope    Suchbereich.
		 * @param bool                $semantic Semantik genutzt?
		 * @return array<string,mixed>
		 */
		private function wrap( array $result, string $scope, bool $semantic ): array {
			$result['scope']         = $scope;
			$result['semantic_used'] = $semantic;
			return $result;
		}
	}
}
