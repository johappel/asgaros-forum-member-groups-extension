<?php
/**
 * Frontend-Ansicht der Forensuche.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Application\ForumSearchService;
use AFSpaces\Search\SearchHit;

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
		 * Suchdienst.
		 *
		 * @var ForumSearchService
		 */
		private ForumSearchService $search;

		/**
		 * Treffer pro Seite.
		 *
		 * @var int
		 */
		private int $per_page = 10;

		/**
		 * Konstruktor.
		 *
		 * @param ForumSearchService $search Suchdienst.
		 */
		public function __construct( ForumSearchService $search ) {
			$this->search = $search;
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
			$sort  = in_array( $sort, array( 'relevance', 'date' ), true ) ? $sort : 'relevance';

			$results = ( '' !== $query )
				? $this->search->search( $query, $sort, $page, $this->per_page )
				: null;

			ob_start();
			?>
			<section class="afspaces-search-view" aria-labelledby="afspaces-search-heading">
				<h2 id="afspaces-search-heading"><?php echo esc_html__( 'Forensuche', 'afspaces' ); ?></h2>

				<div class="afspaces-section-card content-container">
					<form method="get" class="afspaces-search afspaces-search-form" role="search" aria-labelledby="afspaces-search-heading">
						<?php if ( SpacesUrls::hub_page_id() > 0 ) : ?>
							<input type="hidden" name="<?php echo esc_attr( SpacesUrls::VIEW_PARAM ); ?>" value="<?php echo esc_attr( SpacesUrls::VIEW_SEARCH ); ?>" />
						<?php endif; ?>

						<p class="afspaces-field">
							<label for="afspaces-search-input"><?php echo esc_html__( 'Suchbegriff', 'afspaces' ); ?></label>
							<input type="search" id="afspaces-search-input" name="<?php echo esc_attr( self::PARAM_QUERY ); ?>" value="<?php echo esc_attr( $query ); ?>" autocomplete="off" />
						</p>

						<p class="afspaces-field">
							<label for="afspaces-search-sort"><?php echo esc_html__( 'Sortierung', 'afspaces' ); ?></label>
							<select id="afspaces-search-sort" name="<?php echo esc_attr( self::PARAM_SORT ); ?>">
								<option value="relevance" <?php selected( 'relevance', $sort ); ?>><?php echo esc_html__( 'Relevanz', 'afspaces' ); ?></option>
								<option value="date" <?php selected( 'date', $sort ); ?>><?php echo esc_html__( 'Neueste zuerst', 'afspaces' ); ?></option>
							</select>
						</p>

						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Suchen', 'afspaces' ); ?></button>
					</form>
				</div>

				<?php echo $this->render_results( $results, $query, $sort ); ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert den Ergebnisbereich.
		 *
		 * @param array<string,mixed>|null $results Suchergebnis oder null.
		 * @param string                   $query   Suchbegriff.
		 * @param string                   $sort    Sortierung.
		 * @return string
		 */
		private function render_results( ?array $results, string $query, string $sort ): string {
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
				<?php echo $this->render_pagination( $results, $query, $sort ); ?>
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
		 * Rendert die von der Themenpagination unabhängige Trefferpagination.
		 *
		 * @param array<string,mixed> $results Suchergebnis.
		 * @param string              $query   Suchbegriff.
		 * @param string              $sort    Sortierung.
		 * @return string
		 */
		private function render_pagination( array $results, string $query, string $sort ): string {
			$total_pages = (int) ( $results['total_pages'] ?? 0 );
			$current     = (int) ( $results['page'] ?? 1 );
			if ( $total_pages < 2 ) {
				return '';
			}

			$link = function ( int $page ) use ( $query, $sort ): string {
				return SpacesUrls::hub_url(
					SpacesUrls::VIEW_SEARCH,
					array(
						self::PARAM_QUERY => $query,
						self::PARAM_SORT  => $sort,
						self::PARAM_PAGE  => $page,
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
