<?php
/**
 * Suche in WordPress-Beiträgen und -Seiten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\WpPostSearch' ) ) {

	/**
	 * Durchsucht öffentliche WordPress-Inhalte via WP_Query.
	 *
	 * Ist SearchWP im „Native"-Modus aktiv, verbessert es die WP_Query-Suche
	 * transparent; es besteht keine harte Abhängigkeit. Es werden nur
	 * veröffentlichte, öffentlich lesbare Inhalte berücksichtigt.
	 */
	final class WpPostSearch {

		/**
		 * Durchsuchbare Beitragstypen.
		 *
		 * @var string[]
		 */
		private array $post_types;

		/**
		 * Konstruktor.
		 *
		 * @param string[] $post_types Beitragstypen (Default: post, page).
		 */
		public function __construct( array $post_types = array( 'post', 'page' ) ) {
			$this->post_types = array_values( array_filter( array_map( 'strval', $post_types ) ) );
			if ( empty( $this->post_types ) ) {
				$this->post_types = array( 'post', 'page' );
			}
		}

		/**
		 * Führt eine Beitragssuche aus.
		 *
		 * @param string $keywords Suchbegriff.
		 * @param string $sort     'relevance'|'date'.
		 * @param int    $page     Seite (1-basiert).
		 * @param int    $per_page Treffer pro Seite.
		 * @return array{hits: SearchHit[], total: int}
		 */
		public function search( string $keywords, string $sort = 'relevance', int $page = 1, int $per_page = 10 ): array {
			$keywords = trim( $keywords );
			if ( '' === $keywords || ! class_exists( '\\WP_Query' ) ) {
				return array( 'hits' => array(), 'total' => 0 );
			}

			$args = array(
				's'                   => $keywords,
				'post_type'           => $this->post_types,
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, min( 50, $per_page ) ),
				'paged'               => max( 1, $page ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => false,
			);

			if ( 'date' === $sort ) {
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
			} else {
				$args['orderby'] = 'relevance';
			}

			/**
			 * Erlaubt das Anpassen der WP-Suchparameter (z. B. weitere Post-Types).
			 *
			 * @param array<string,mixed> $args     WP_Query-Argumente.
			 * @param string              $keywords Suchbegriff.
			 */
			$args = (array) apply_filters( 'afspaces_wp_search_args', $args, $keywords );

			$query = new \WP_Query( $args );

			$hits = array();
			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}
				// Nur öffentlich lesbare Inhalte ausliefern.
				if ( ! $this->is_public( $post ) ) {
					continue;
				}
				$hits[] = $this->to_hit( $post, $keywords );
			}

			$total = (int) $query->found_posts;

			if ( function_exists( 'wp_reset_postdata' ) ) {
				wp_reset_postdata();
			}

			return array(
				'hits'  => $hits,
				'total' => $total,
			);
		}

		/**
		 * Prüft, ob ein Beitrag öffentlich lesbar ist.
		 *
		 * @param \WP_Post $post Beitrag.
		 * @return bool
		 */
		private function is_public( \WP_Post $post ): bool {
			if ( 'publish' !== $post->post_status ) {
				return false;
			}
			if ( '' !== (string) $post->post_password ) {
				return false;
			}
			$type = get_post_type_object( $post->post_type );
			return ! $type || ! empty( $type->public );
		}

		/**
		 * Wandelt einen Beitrag in einen Suchtreffer um.
		 *
		 * @param \WP_Post $post     Beitrag.
		 * @param string   $keywords Suchbegriff (für Highlight).
		 * @return SearchHit
		 */
		private function to_hit( \WP_Post $post, string $keywords ): SearchHit {
			$title = (string) get_the_title( $post );
			if ( '' === $title ) {
				$title = __( '(ohne Titel)', 'afspaces' );
			}

			$source_text = (string) $post->post_excerpt;
			if ( '' === trim( $source_text ) ) {
				$source_text = (string) $post->post_content;
			}
			$snippet = SnippetBuilder::build( $source_text, $keywords );

			$author_name = (string) get_the_author_meta( 'display_name', (int) $post->post_author );
			if ( '' === $author_name ) {
				$author_name = __( 'Unbekannt', 'afspaces' );
			}

			$date = (string) get_the_date( '', $post );

			$type_obj      = get_post_type_object( $post->post_type );
			$context_label = $type_obj && isset( $type_obj->labels->singular_name )
				? (string) $type_obj->labels->singular_name
				: (string) $post->post_type;

			return new SearchHit(
				SearchHit::SOURCE_WP,
				$title,
				(string) get_permalink( $post ),
				$snippet,
				$author_name,
				$date,
				$context_label,
				0.0,
				'wp:' . (int) $post->ID
			);
		}
	}
}
