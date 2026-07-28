<?php
/**
 * Frontend-Ansicht der Forensuche.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Application\HybridSearchService;
use AFSpaces\Search\SearchHit;
use AFSpaces\Search\SearchSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\SearchView' ) ) {

	/**
	 * Rendert Suchformular und post-genaue Trefferliste.
	 */
	class SearchView {

		/**
		 * Query-Parameter für den Suchbegriff.
		 */
		public const PARAM_QUERY = 'afspaces_q';

		/**
		 * Query-Parameter für die Sortierung.
		 */
		public const PARAM_SORT = 'afspaces_sort';

		/**
		 * Query-Parameter für die Trefferseite.
		 */
		public const PARAM_PAGE = 'afspaces_spage';

		/**
		 * Query-Parameter für den Suchbereich.
		 */
		public const PARAM_SCOPE = 'afspaces_scope';

		/**
		 * Query-Parameter für die semantische Suche.
		 */
		public const PARAM_SEMANTIC = 'afspaces_semantic';

		/**
		 * Query-Parameter für den Arbeitsgruppen-Filter (Forum-ID).
		 */
		public const PARAM_GROUP = 'afspaces_ag';

		/**
		 * Query-Parameter für den Autor-Filter (Name).
		 */
		public const PARAM_AUTHOR = 'afspaces_author';

		/**
		 * Query-Parameter für den Zeitraum-Beginn.
		 */
		public const PARAM_FROM = 'afspaces_from';

		/**
		 * Query-Parameter für das Zeitraum-Ende.
		 */
		public const PARAM_TO = 'afspaces_to';

		/**
		 * Suchdienst.
		 *
		 * @var HybridSearchService
		 */
		private HybridSearchService $search;

		/**
		 * Asgaros-Adapter (für die Arbeitsgruppen-Liste).
		 *
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Treffer pro Seite.
		 *
		 * @var int
		 */
		private int $per_page = 10;

		/**
		 * Konstruktor.
		 *
		 * @param HybridSearchService     $search  Suchdienst.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 */
		public function __construct( HybridSearchService $search, AsgarosAdapterInterface $asgaros ) {
			$this->search  = $search;
			$this->asgaros = $asgaros;
		}

		/**
		 * Rendert die Suchansicht.
		 *
		 * @return string
		 */
		public function render(): string {
			$query = isset( $_GET[ self::PARAM_QUERY ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_QUERY ] ) ) : '';
			$sort  = isset( $_GET[ self::PARAM_SORT ] ) ? sanitize_key( wp_unslash( $_GET[ self::PARAM_SORT ] ) ) : 'relevance';
			$page  = isset( $_GET[ self::PARAM_PAGE ] ) ? max( 1, (int) $_GET[ self::PARAM_PAGE ] ) : 1;
			$scope = isset( $_GET[ self::PARAM_SCOPE ] ) ? sanitize_key( wp_unslash( $_GET[ self::PARAM_SCOPE ] ) ) : HybridSearchService::SCOPE_ALL;
			$sort  = in_array( $sort, array( 'relevance', 'date' ), true ) ? $sort : 'relevance';
			$scope = in_array( $scope, array( HybridSearchService::SCOPE_ALL, HybridSearchService::SCOPE_FORUM, HybridSearchService::SCOPE_WP ), true ) ? $scope : HybridSearchService::SCOPE_ALL;

			$semantic_available = SearchSettings::is_semantic_enabled();
			$semantic           = $semantic_available && ! empty( $_GET[ self::PARAM_SEMANTIC ] );

			$ag_id  = isset( $_GET[ self::PARAM_GROUP ] ) ? max( 0, (int) $_GET[ self::PARAM_GROUP ] ) : 0;
			$author = isset( $_GET[ self::PARAM_AUTHOR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_AUTHOR ] ) ) : '';
			$from   = isset( $_GET[ self::PARAM_FROM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_FROM ] ) ) : '';
			$to     = isset( $_GET[ self::PARAM_TO ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PARAM_TO ] ) ) : '';

			$filters = array(
				'forum_id'    => $ag_id,
				'author_name' => $author,
				'date_from'   => $from,
				'date_to'     => $to,
			);
			$filters_active = ( $ag_id > 0 || '' !== $author || '' !== $from || '' !== $to );

			$results = ( '' !== $query )
				? $this->search->search(
					$query,
					array(
						'scope'    => $scope,
						'sort'     => $sort,
						'semantic' => $semantic,
						'page'     => $page,
						'per_page' => $this->per_page,
						'filters'  => $filters,
					)
				)
				: null;

			$forums = $this->asgaros->list_accessible_forums();

			ob_start();
			?>
			<section class="afspaces-search-view" aria-labelledby="afspaces-search-heading">
				<h2 id="afspaces-search-heading"><?php echo esc_html__( 'Forensuche', 'afspaces' ); ?></h2>

				<div class="afspaces-section-card content-container">
					<form method="get" class="afspaces-search afspaces-search-form" role="search" aria-labelledby="afspaces-search-heading">
						<?php if ( SpacesUrls::hub_page_id() > 0 ) : ?>
							<input type="hidden" name="<?php echo esc_attr( SpacesUrls::VIEW_PARAM ); ?>" value="<?php echo esc_attr( SpacesUrls::VIEW_SEARCH ); ?>" />
						<?php endif; ?>

						<div class="afspaces-search-row">
							<p class="afspaces-field afspaces-field-grow">
								<label for="afspaces-search-input"><?php echo esc_html__( 'Suchbegriff', 'afspaces' ); ?></label>
								<input type="search" id="afspaces-search-input" name="<?php echo esc_attr( self::PARAM_QUERY ); ?>" value="<?php echo esc_attr( $query ); ?>" autocomplete="off" />
							</p>

							<p class="afspaces-field">
								<label for="afspaces-search-scope"><?php echo esc_html__( 'Bereich', 'afspaces' ); ?></label>
								<select id="afspaces-search-scope" name="<?php echo esc_attr( self::PARAM_SCOPE ); ?>">
									<option value="all" <?php selected( HybridSearchService::SCOPE_ALL, $scope ); ?>><?php echo esc_html__( 'Alles', 'afspaces' ); ?></option>
									<option value="forum" <?php selected( HybridSearchService::SCOPE_FORUM, $scope ); ?>><?php echo esc_html__( 'Foren', 'afspaces' ); ?></option>
									<option value="wp" <?php selected( HybridSearchService::SCOPE_WP, $scope ); ?>><?php echo esc_html__( 'Beiträge & Seiten', 'afspaces' ); ?></option>
								</select>
							</p>

							<p class="afspaces-field">
								<label for="afspaces-search-sort"><?php echo esc_html__( 'Sortierung', 'afspaces' ); ?></label>
								<select id="afspaces-search-sort" name="<?php echo esc_attr( self::PARAM_SORT ); ?>">
									<option value="relevance" <?php selected( 'relevance', $sort ); ?>><?php echo esc_html__( 'Relevanz', 'afspaces' ); ?></option>
									<option value="date" <?php selected( 'date', $sort ); ?>><?php echo esc_html__( 'Neueste zuerst', 'afspaces' ); ?></option>
								</select>
							</p>

							<p class="afspaces-field afspaces-field-submit">
								<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Suchen', 'afspaces' ); ?></button>
							</p>
						</div>

						<?php if ( $semantic_available ) : ?>
							<p class="afspaces-field afspaces-field-checkbox">
								<label for="afspaces-search-semantic">
									<input type="checkbox" id="afspaces-search-semantic" name="<?php echo esc_attr( self::PARAM_SEMANTIC ); ?>" value="1" <?php checked( $semantic ); ?> />
									<?php echo esc_html__( 'Semantische Suche einbeziehen', 'afspaces' ); ?>
								</label>
							</p>
						<?php endif; ?>

						<details class="afspaces-search-filters"<?php echo $filters_active ? ' open' : ''; ?>>
							<summary><?php echo esc_html__( 'Filter (Arbeitsgruppe, Autor:in, Zeitraum)', 'afspaces' ); ?></summary>
							<div class="afspaces-search-row afspaces-search-filter-row">
								<p class="afspaces-field">
									<label for="afspaces-filter-ag"><?php echo esc_html__( 'Arbeitsgruppe', 'afspaces' ); ?></label>
									<select id="afspaces-filter-ag" name="<?php echo esc_attr( self::PARAM_GROUP ); ?>">
										<option value="0"><?php echo esc_html__( 'Alle Arbeitsgruppen', 'afspaces' ); ?></option>
										<?php foreach ( $forums as $forum ) : ?>
											<option value="<?php echo esc_attr( (string) $forum['id'] ); ?>" <?php selected( $ag_id, (int) $forum['id'] ); ?>><?php echo esc_html( (string) $forum['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
								<p class="afspaces-field">
									<label for="afspaces-filter-author"><?php echo esc_html__( 'Autor:in', 'afspaces' ); ?></label>
									<input type="text" id="afspaces-filter-author" name="<?php echo esc_attr( self::PARAM_AUTHOR ); ?>" value="<?php echo esc_attr( $author ); ?>" autocomplete="off" />
								</p>
								<p class="afspaces-field">
									<label for="afspaces-filter-from"><?php echo esc_html__( 'Von', 'afspaces' ); ?></label>
									<input type="date" id="afspaces-filter-from" name="<?php echo esc_attr( self::PARAM_FROM ); ?>" value="<?php echo esc_attr( $from ); ?>" />
								</p>
								<p class="afspaces-field">
									<label for="afspaces-filter-to"><?php echo esc_html__( 'Bis', 'afspaces' ); ?></label>
									<input type="date" id="afspaces-filter-to" name="<?php echo esc_attr( self::PARAM_TO ); ?>" value="<?php echo esc_attr( $to ); ?>" />
								</p>
							</div>
						</details>
					</form>
				</div>

				<?php echo $this->render_results( $results, $query, $sort, $scope, $semantic, $filters ); ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert den Ergebnisbereich.
		 *
		 * @param array<string,mixed>|null $results  Suchergebnis oder null.
		 * @param string                   $query    Suchbegriff.
		 * @param string                   $sort     Sortierung.
		 * @param string                   $scope    Suchbereich.
		 * @param bool                     $semantic Semantik aktiv?
		 * @param array<string,mixed>      $filters  Aktive Filter.
		 * @return string
		 */
		private function render_results( ?array $results, string $query, string $sort, string $scope, bool $semantic, array $filters = array() ): string {
			if ( null === $results ) {
				return '';
			}

			$total = (int) ( $results['total'] ?? 0 );

			ob_start();
			?>
			<div class="afspaces-search-status" role="status" aria-live="polite">
				<?php
				if ( 0 === $total ) {
					echo esc_html(
						sprintf(
							/* translators: %s: Suchbegriff */
							__( 'Keine Treffer für „%s“.', 'afspaces' ),
							$query
						)
					);
				} else {
					echo esc_html(
						sprintf(
							/* translators: 1: Trefferanzahl, 2: Suchbegriff */
							_n( '%1$d Treffer für „%2$s“.', '%1$d Treffer für „%2$s“.', $total, 'afspaces' ),
							$total,
							$query
						)
					);
				}
				?>
			</div>

			<?php if ( $total > 0 ) : ?>
				<ol class="afspaces-search-results-list">
					<?php
					foreach ( (array) $results['hits'] as $hit ) {
						echo $this->render_hit( $hit ); // Bereits escaped.
					}
					?>
				</ol>
				<?php echo $this->render_pagination( $results, $query, $sort, $scope, $semantic, $filters ); ?>
			<?php endif; ?>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert einen einzelnen Treffer.
		 *
		 * @param SearchHit $hit Treffer.
		 * @return string
		 */
		private function render_hit( SearchHit $hit ): string {
			ob_start();
			?>
			<li class="afspaces-search-result">
				<h3 class="afspaces-search-result-title">
					<a href="<?php echo esc_url( $hit->url ); ?>"><?php echo esc_html( $hit->title ); ?></a>
				</h3>
				<p class="afspaces-search-result-meta">
					<span class="afspaces-tag afspaces-source-<?php echo esc_attr( $hit->source ); ?>"><?php echo esc_html( $this->source_label( $hit->source ) ); ?></span>
					<?php if ( '' !== $hit->context_label ) : ?>
						<span class="afspaces-tag"><?php echo esc_html( $hit->context_label ); ?></span>
					<?php endif; ?>
					<span class="afspaces-search-result-author"><?php echo esc_html( $hit->author_name ); ?></span>
					<?php if ( '' !== $hit->date ) : ?>
						<span aria-hidden="true"> · </span>
						<span class="afspaces-search-result-date"><?php echo esc_html( $hit->date ); ?></span>
					<?php endif; ?>
				</p>
				<p class="afspaces-search-result-snippet"><?php echo $this->render_snippet( $hit->snippet ); ?></p>
				<p class="afspaces-search-result-link">
					<a href="<?php echo esc_url( $hit->url ); ?>"><?php echo esc_html__( 'Zum gefundenen Beitrag', 'afspaces' ); ?></a>
				</p>
			</li>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert einen Ausschnitt mit hervorgehobenen Fundstellen.
		 *
		 * @param array<int,array{text:string,mark:bool}> $segments Segmente.
		 * @return string
		 */
		private function render_snippet( array $segments ): string {
			if ( empty( $segments ) ) {
				return '';
			}

			$html = '';
			foreach ( $segments as $segment ) {
				$text = esc_html( (string) ( $segment['text'] ?? '' ) );
				if ( ! empty( $segment['mark'] ) ) {
					$html .= '<mark>' . $text . '</mark>';
				} else {
					$html .= $text;
				}
			}

			return $html;
		}

		/**
		 * Liefert das Quellen-Label für ein Badge.
		 *
		 * @param string $source Quelle (forum|wp).
		 * @return string
		 */
		private function source_label( string $source ): string {
			if ( SearchHit::SOURCE_WP === $source ) {
				return __( 'Beitrag', 'afspaces' );
			}
			return __( 'Forum', 'afspaces' );
		}

		/**
		 * Rendert die von der Themenpagination unabhängige Trefferpagination.
		 *
		 * @param array<string,mixed> $results  Suchergebnis.
		 * @param string              $query    Suchbegriff.
		 * @param string              $sort     Sortierung.
		 * @param string              $scope    Suchbereich.
		 * @param bool                $semantic Semantik aktiv?
		 * @param array<string,mixed> $filters  Aktive Filter.
		 * @return string
		 */
		private function render_pagination( array $results, string $query, string $sort, string $scope, bool $semantic, array $filters = array() ): string {
			$total_pages = (int) ( $results['total_pages'] ?? 0 );
			$current     = (int) ( $results['page'] ?? 1 );
			if ( $total_pages < 2 ) {
				return '';
			}

			$link = function ( int $page ) use ( $query, $sort, $scope, $semantic, $filters ): string {
				return SpacesUrls::hub_url(
					SpacesUrls::VIEW_SEARCH,
					array(
						self::PARAM_QUERY    => $query,
						self::PARAM_SORT     => $sort,
						self::PARAM_SCOPE    => $scope,
						self::PARAM_SEMANTIC => $semantic ? '1' : '',
						self::PARAM_GROUP    => (int) ( $filters['forum_id'] ?? 0 ) > 0 ? (string) $filters['forum_id'] : '',
						self::PARAM_AUTHOR   => (string) ( $filters['author_name'] ?? '' ),
						self::PARAM_FROM     => (string) ( $filters['date_from'] ?? '' ),
						self::PARAM_TO       => (string) ( $filters['date_to'] ?? '' ),
						self::PARAM_PAGE     => $page,
					)
				);
			};

			ob_start();
			?>
			<nav class="afspaces-search-pagination" aria-label="<?php echo esc_attr__( 'Trefferseiten', 'afspaces' ); ?>">
				<ul class="afspaces-pagination-list">
					<?php if ( $current > 1 ) : ?>
						<li>
							<a rel="prev" href="<?php echo esc_url( $link( $current - 1 ) ); ?>"><?php echo esc_html__( 'Zurück', 'afspaces' ); ?></a>
						</li>
					<?php endif; ?>
					<li class="afspaces-pagination-status">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: aktuelle Seite, 2: Gesamtseiten */
								__( 'Seite %1$d von %2$d', 'afspaces' ),
								$current,
								$total_pages
							)
						);
						?>
					</li>
					<?php if ( $current < $total_pages ) : ?>
						<li>
							<a rel="next" href="<?php echo esc_url( $link( $current + 1 ) ); ?>"><?php echo esc_html__( 'Weiter', 'afspaces' ); ?></a>
						</li>
					<?php endif; ?>
				</ul>
			</nav>
			<?php
			return (string) ob_get_clean();
		}
	}
}
