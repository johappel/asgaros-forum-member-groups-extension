<?php
/**
 * Baut und pflegt den semantischen Suchindex (Embeddings).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SearchIndexRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Search\EmbeddingClient;
use AFSpaces\Search\SearchSettings;
use AFSpaces\Search\SnippetBuilder;
use AFSpaces\Search\VectorMath;

if ( ! class_exists( 'AFSpaces\\Application\\SearchIndexer' ) ) {

	/**
	 * Erzeugt und aktualisiert Embeddings für Foren- und WP-Inhalte.
	 *
	 * Datenschutz: Private Arbeitsgruppen-Inhalte werden nur eingebettet, wenn
	 * dies in den Einstellungen ausdrücklich aktiviert wurde (`index_private`).
	 * Unveränderte Inhalte werden über einen Inhalts-Hash übersprungen, um
	 * unnötige API-Aufrufe zu vermeiden.
	 */
	class SearchIndexer {

		public const CRON_HOOK = 'afspaces_reindex_search';

		/**
		 * Maximale Zeichenzahl des einzubettenden Textes.
		 */
		private const MAX_CHARS = 2000;

		/**
		 * Batch-Größe für Embedding-API-Aufrufe.
		 */
		private const EMBED_BATCH = 16;

		private AsgarosAdapterInterface $asgaros;
		private SearchIndexRepository $index;

		/**
		 * Konstruktor.
		 *
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param SearchIndexRepository   $index   Index-Repository.
		 */
		public function __construct( AsgarosAdapterInterface $asgaros, SearchIndexRepository $index ) {
			$this->asgaros = $asgaros;
			$this->index   = $index;
		}

		/**
		 * Registriert Hooks (Cron + Inhaltsänderungen).
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( self::CRON_HOOK, array( $this, 'reindex_all' ) );
			add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
			add_action( 'trashed_post', array( $this, 'on_delete_post' ) );
			add_action( 'deleted_post', array( $this, 'on_delete_post' ) );
		}

		/**
		 * Plant den wiederkehrenden Reindex-Lauf.
		 *
		 * @return void
		 */
		public static function schedule(): void {
			if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + 300, 'daily', self::CRON_HOOK );
			}
		}

		/**
		 * Entfernt den geplanten Lauf.
		 *
		 * @return void
		 */
		public static function unschedule(): void {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		}

		/**
		 * Baut bzw. aktualisiert den gesamten Index.
		 *
		 * @return array{indexed:int, skipped:int, errors:int}
		 */
		public function reindex_all(): array {
			$stats = array( 'indexed' => 0, 'skipped' => 0, 'errors' => 0 );

			if ( ! SearchSettings::is_semantic_enabled() ) {
				return $stats;
			}

			$client = EmbeddingClient::from_settings();

			$this->reindex_forum_posts( $client, $stats );

			if ( SearchSettings::index_wp() ) {
				$this->reindex_wp_posts( $client, $stats );
			}

			return $stats;
		}

		/**
		 * Indexiert alle freigegebenen Forenbeiträge.
		 *
		 * @param EmbeddingClient      $client Embedding-Client.
		 * @param array<string,int>    $stats  Statistik (per Referenz).
		 * @return void
		 */
		private function reindex_forum_posts( EmbeddingClient $client, array &$stats ): void {
			$index_private = SearchSettings::index_private();
			$total         = $this->asgaros->count_all_posts();
			$batch         = 100;

			for ( $offset = 0; $offset < $total; $offset += $batch ) {
				$rows    = $this->asgaros->list_posts_for_index( $batch, $offset );
				$pending = array();

				foreach ( $rows as $row ) {
					if ( ! empty( $row['is_private'] ) && ! $index_private ) {
						continue;
					}

					$title = (string) $row['topic_name'];
					$text  = SnippetBuilder::plain( (string) $row['post_text'] );
					$hash  = sha1( $title . '|' . $text );

					if ( $this->index->get_hash( SearchIndexRepository::SOURCE_FORUM, (int) $row['post_id'] ) === $hash ) {
						$stats['skipped']++;
						continue;
					}

					$pending[] = array(
						'row'   => $row,
						'title' => $title,
						'text'  => $text,
						'hash'  => $hash,
					);
				}

				$this->flush_forum_batch( $client, $pending, $stats );
			}
		}

		/**
		 * Bettet einen Stapel Forenbeiträge ein und speichert sie.
		 *
		 * @param EmbeddingClient                  $client  Client.
		 * @param array<int,array<string,mixed>>   $pending Ausstehende Elemente.
		 * @param array<string,int>                $stats   Statistik (per Referenz).
		 * @return void
		 */
		private function flush_forum_batch( EmbeddingClient $client, array $pending, array &$stats ): void {
			foreach ( array_chunk( $pending, self::EMBED_BATCH ) as $chunk ) {
				$texts = array();
				foreach ( $chunk as $item ) {
					$texts[] = $this->embedding_text( $item['title'], $item['text'] );
				}

				try {
					$vectors = $client->embed( $texts );
				} catch ( DomainException $e ) {
					$stats['errors'] += count( $chunk );
					continue;
				}

				foreach ( $chunk as $i => $item ) {
					$vector = $vectors[ $i ] ?? array();
					if ( empty( $vector ) ) {
						$stats['errors']++;
						continue;
					}
					$row = $item['row'];
					$this->index->upsert(
						array(
							'source_type'  => SearchIndexRepository::SOURCE_FORUM,
							'source_id'    => (int) $row['post_id'],
							'topic_id'     => (int) $row['topic_id'],
							'category_id'  => (int) $row['category_id'],
							'is_private'   => ! empty( $row['is_private'] ),
							'title'        => $item['title'],
							'context_label' => (string) $row['forum_name'],
							'excerpt'      => $item['text'],
							'author_name'  => $this->author_name( (int) $row['author_id'] ),
							'item_date'    => (string) $row['post_date'],
							'content_hash' => $item['hash'],
							'embedding'    => VectorMath::pack_vector( VectorMath::normalize( $vector ) ),
							'dims'         => count( $vector ),
						)
					);
					$stats['indexed']++;
				}
			}
		}

		/**
		 * Indexiert alle konfigurierten WP-Beitragstypen.
		 *
		 * @param EmbeddingClient   $client Client.
		 * @param array<string,int> $stats  Statistik (per Referenz).
		 * @return void
		 */
		private function reindex_wp_posts( EmbeddingClient $client, array &$stats ): void {
			if ( ! class_exists( '\\WP_Query' ) ) {
				return;
			}

			$paged = 1;
			do {
				$query = new \WP_Query(
					array(
						'post_type'      => SearchSettings::wp_post_types(),
						'post_status'    => 'publish',
						'posts_per_page' => 50,
						'paged'          => $paged,
						'orderby'        => 'ID',
						'order'          => 'ASC',
						'no_found_rows'  => false,
					)
				);

				$pending = array();
				foreach ( $query->posts as $post ) {
					if ( ! $post instanceof \WP_Post || '' !== (string) $post->post_password ) {
						continue;
					}
					$title = (string) get_the_title( $post );
					$text  = SnippetBuilder::plain( (string) $post->post_content );
					$hash  = sha1( $title . '|' . $text );

					if ( $this->index->get_hash( SearchIndexRepository::SOURCE_WP, (int) $post->ID ) === $hash ) {
						$stats['skipped']++;
						continue;
					}

					$pending[] = array(
						'post'  => $post,
						'title' => $title,
						'text'  => $text,
						'hash'  => $hash,
					);
				}

				$this->flush_wp_batch( $client, $pending, $stats );

				$max_pages = (int) $query->max_num_pages;
				if ( function_exists( 'wp_reset_postdata' ) ) {
					wp_reset_postdata();
				}
				$paged++;
			} while ( $paged <= $max_pages );
		}

		/**
		 * Bettet einen Stapel WP-Beiträge ein und speichert sie.
		 *
		 * @param EmbeddingClient                $client  Client.
		 * @param array<int,array<string,mixed>> $pending Ausstehende Elemente.
		 * @param array<string,int>              $stats   Statistik (per Referenz).
		 * @return void
		 */
		private function flush_wp_batch( EmbeddingClient $client, array $pending, array &$stats ): void {
			foreach ( array_chunk( $pending, self::EMBED_BATCH ) as $chunk ) {
				$texts = array();
				foreach ( $chunk as $item ) {
					$texts[] = $this->embedding_text( $item['title'], $item['text'] );
				}

				try {
					$vectors = $client->embed( $texts );
				} catch ( DomainException $e ) {
					$stats['errors'] += count( $chunk );
					continue;
				}

				foreach ( $chunk as $i => $item ) {
					$vector = $vectors[ $i ] ?? array();
					if ( empty( $vector ) ) {
						$stats['errors']++;
						continue;
					}
					$post = $item['post'];
					$this->index->upsert(
						array(
							'source_type'  => SearchIndexRepository::SOURCE_WP,
							'source_id'    => (int) $post->ID,
							'topic_id'     => 0,
							'category_id'  => 0,
							'is_private'   => false,
							'title'        => $item['title'],
							'context_label' => $this->wp_context_label( $post ),
							'excerpt'      => $item['text'],
							'author_name'  => $this->author_name( (int) $post->post_author ),
							'item_date'    => (string) $post->post_date,
							'content_hash' => $item['hash'],
							'embedding'    => VectorMath::pack_vector( VectorMath::normalize( $vector ) ),
							'dims'         => count( $vector ),
						)
					);
					$stats['indexed']++;
				}
			}
		}

		/**
		 * Hook: (De-)Indexiert einen WP-Beitrag beim Speichern.
		 *
		 * @param int      $post_id Beitrags-ID.
		 * @param \WP_Post $post    Beitrag.
		 * @param bool     $update  Aktualisierung?
		 * @return void
		 */
		public function on_save_post( int $post_id, $post, bool $update = false ): void {
			if ( ! SearchSettings::is_semantic_enabled() || ! SearchSettings::index_wp() ) {
				return;
			}
			if ( ! $post instanceof \WP_Post ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}
			if ( ! in_array( $post->post_type, SearchSettings::wp_post_types(), true ) ) {
				return;
			}

			if ( 'publish' !== $post->post_status || '' !== (string) $post->post_password ) {
				$this->index->delete( SearchIndexRepository::SOURCE_WP, $post_id );
				return;
			}

			$title = (string) get_the_title( $post );
			$text  = SnippetBuilder::plain( (string) $post->post_content );
			$hash  = sha1( $title . '|' . $text );
			if ( $this->index->get_hash( SearchIndexRepository::SOURCE_WP, $post_id ) === $hash ) {
				return;
			}

			try {
				$vector = EmbeddingClient::from_settings()->embed_one( $this->embedding_text( $title, $text ) );
			} catch ( DomainException $e ) {
				return;
			}
			if ( empty( $vector ) ) {
				return;
			}

			$this->index->upsert(
				array(
					'source_type'  => SearchIndexRepository::SOURCE_WP,
					'source_id'    => $post_id,
					'title'        => $title,
					'context_label' => $this->wp_context_label( $post ),
					'excerpt'      => $text,
					'author_name'  => $this->author_name( (int) $post->post_author ),
					'item_date'    => (string) $post->post_date,
					'content_hash' => $hash,
					'embedding'    => VectorMath::pack_vector( VectorMath::normalize( $vector ) ),
					'dims'         => count( $vector ),
				)
			);
		}

		/**
		 * Hook: Entfernt einen gelöschten/verworfenen WP-Beitrag aus dem Index.
		 *
		 * @param int $post_id Beitrags-ID.
		 * @return void
		 */
		public function on_delete_post( int $post_id ): void {
			$this->index->delete( SearchIndexRepository::SOURCE_WP, $post_id );
		}

		/**
		 * Baut den einzubettenden Text (Titel + gekürzter Inhalt).
		 *
		 * @param string $title Titel.
		 * @param string $text  Inhalt (plain).
		 * @return string
		 */
		private function embedding_text( string $title, string $text ): string {
			$combined = trim( $title . "\n" . $text );
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $combined, 0, self::MAX_CHARS, 'UTF-8' );
			}
			return substr( $combined, 0, self::MAX_CHARS );
		}

		/**
		 * Ermittelt einen Anzeigenamen (Snapshot für die Anzeige).
		 *
		 * @param int $user_id Benutzer-ID.
		 * @return string
		 */
		private function author_name( int $user_id ): string {
			if ( $user_id < 1 || ! function_exists( 'get_the_author_meta' ) ) {
				return '';
			}
			return (string) get_the_author_meta( 'display_name', $user_id );
		}

		/**
		 * Kontext-Label eines WP-Beitrags (Beitragstyp).
		 *
		 * @param \WP_Post $post Beitrag.
		 * @return string
		 */
		private function wp_context_label( \WP_Post $post ): string {
			$type = get_post_type_object( $post->post_type );
			if ( $type && isset( $type->labels->singular_name ) ) {
				return (string) $type->labels->singular_name;
			}
			return (string) $post->post_type;
		}
	}
}
