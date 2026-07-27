<?php
/**
 * Anwendungsdienst für die post-genaue Forensuche.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Search\SearchHit;
use AFSpaces\Search\SnippetBuilder;

if ( ! class_exists( 'AFSpaces\\Application\\ForumSearchService' ) ) {

	/**
	 * Durchsucht Forenbeiträge post-genau und liefert darstellbare Treffer.
	 *
	 * Die Zugriffsprüfung erfolgt im Adapter (nur zugängliche Kategorien,
	 * nur freigegebene Themen). Dieser Dienst reichert die Rohtreffer um
	 * Ausschnitt, Autor, Datum und Deep-Link an.
	 */
	class ForumSearchService {

		/**
		 * Erlaubte Sortierungen.
		 */
		private const SORTS = array( 'relevance', 'date' );

		/**
		 * Asgaros-Adapter.
		 *
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Konstruktor.
		 *
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 */
		public function __construct( AsgarosAdapterInterface $asgaros ) {
			$this->asgaros = $asgaros;
		}

		/**
		 * Führt eine Forensuche aus.
		 *
		 * @param string $keywords Suchbegriff.
		 * @param string $sort     Sortierung ('relevance'|'date').
		 * @param int    $page     Seite (1-basiert).
		 * @param int    $per_page Treffer pro Seite.
		 * @return array{hits: SearchHit[], total: int, page: int, per_page: int, total_pages: int, query: string}
		 */
		public function search( string $keywords, string $sort = 'relevance', int $page = 1, int $per_page = 10 ): array {
			$keywords = trim( $keywords );
			$sort     = in_array( $sort, self::SORTS, true ) ? $sort : 'relevance';
			$page     = max( 1, $page );
			$per_page = max( 1, min( 50, $per_page ) );

			$empty = array(
				'hits'        => array(),
				'total'       => 0,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => 0,
				'query'       => $keywords,
			);

			if ( '' === $keywords ) {
				return $empty;
			}

			$response = $this->asgaros->search_posts(
				$keywords,
				array(
					'sort'     => $sort,
					'page'     => $page,
					'per_page' => $per_page,
				)
			);

			$total = (int) ( $response['total'] ?? 0 );
			if ( $total < 1 || empty( $response['results'] ) ) {
				return $empty;
			}

			$hits = array();
			foreach ( (array) $response['results'] as $row ) {
				$hits[] = $this->to_hit( $row, $keywords );
			}

			return array(
				'hits'        => $hits,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
				'query'       => $keywords,
			);
		}

		/**
		 * Wandelt eine Adapterzeile in einen darstellbaren Treffer um.
		 *
		 * @param array<string,mixed> $row      Rohtreffer.
		 * @param string              $keywords Suchbegriff (für Highlight).
		 * @return SearchHit
		 */
		private function to_hit( array $row, string $keywords ): SearchHit {
			$title = (string) ( $row['topic_name'] ?? '' );
			if ( '' === $title ) {
				$title = __( 'Beitrag', 'afspaces' );
			}

			$author_name = $this->author_name( (int) ( $row['author_id'] ?? 0 ) );
			$date        = $this->format_date( (string) ( $row['post_date'] ?? '' ) );
			$snippet     = SnippetBuilder::build( (string) ( $row['post_text'] ?? '' ), $keywords );

			$context = (string) ( $row['forum_name'] ?? '' );

			return new SearchHit(
				SearchHit::SOURCE_FORUM,
				$title,
				(string) ( $row['url'] ?? '' ),
				$snippet,
				$author_name,
				$date,
				$context,
				(float) ( $row['score'] ?? 0 )
			);
		}

		/**
		 * Ermittelt den Anzeigenamen einer Autorin/eines Autors.
		 *
		 * @param int $author_id Benutzer-ID.
		 * @return string
		 */
		private function author_name( int $author_id ): string {
			if ( $author_id < 1 ) {
				return __( 'Unbekannt', 'afspaces' );
			}

			$name = '';
			if ( function_exists( 'get_the_author_meta' ) ) {
				$name = (string) get_the_author_meta( 'display_name', $author_id );
			}
			if ( '' === $name && function_exists( 'get_userdata' ) ) {
				$user = get_userdata( $author_id );
				$name = $user ? (string) $user->display_name : '';
			}

			return '' !== $name ? $name : __( 'Unbekannt', 'afspaces' );
		}

		/**
		 * Formatiert ein Beitragsdatum lokalisiert.
		 *
		 * @param string $mysql_date Datum im MySQL-Format.
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
				$format = (string) get_option( 'date_format', 'd.m.Y' );
				return (string) date_i18n( $format, $timestamp );
			}

			return gmdate( 'd.m.Y', $timestamp );
		}
	}
}
