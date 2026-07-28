<?php
/**
 * Ortsunabhängiges Such-Overlay (Dialog) mit Live-Suche über die REST-API.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Search\SearchSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\SearchModal' ) ) {

	/**
	 * Stellt site-weit ein barrierearmes Such-Overlay bereit.
	 *
	 * Das Overlay nutzt die REST-Route `/afspaces/v1/search` und funktioniert
	 * unabhängig von der aktuellen Seite. Als Fallback ohne JavaScript bleiben
	 * die serverseitige Suchseite und die Asgaros-Weiterleitung bestehen.
	 */
	class SearchModal {

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
		 * Registriert Hooks und den Trigger-Shortcode.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'wp_footer', array( $this, 'render_modal' ) );
			add_shortcode( 'afspaces_search_button', array( $this, 'render_button' ) );
			add_shortcode( 'afspaces_search_link', array( $this, 'render_link' ) );
		}

		/**
		 * Lädt Skript und Konfiguration.
		 *
		 * @return void
		 */
		public function enqueue(): void {
			wp_register_script(
				'afspaces-search-modal',
				AFSPACES_URL . 'assets/afspaces-search.js',
				array(),
				AFSPACES_VERSION,
				true
			);

			$forums = array();
			foreach ( $this->asgaros->list_accessible_forums() as $forum ) {
				$forums[] = array(
					'id'   => (int) $forum['id'],
					'name' => (string) $forum['name'],
				);
			}

			wp_localize_script(
				'afspaces-search-modal',
				'afspacesSearch',
				array(
					'restUrl'           => esc_url_raw( rest_url( 'afspaces/v1/search' ) ),
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'loggedIn'          => is_user_logged_in(),
					'semanticAvailable' => SearchSettings::is_semantic_enabled() && is_user_logged_in(),
					'replaceWpSearch'   => (bool) SearchSettings::replace_wp_search(),
					'forums'            => $forums,
					'i18n'              => array(
						'title'        => __( 'Suche', 'afspaces' ),
						'placeholder'  => __( 'Foren und Beiträge durchsuchen …', 'afspaces' ),
						'scope'        => __( 'Bereich', 'afspaces' ),
						'scopeAll'     => __( 'Alles', 'afspaces' ),
						'scopeForum'   => __( 'Foren', 'afspaces' ),
						'scopeWp'      => __( 'Beiträge & Seiten', 'afspaces' ),
						'sort'         => __( 'Sortierung', 'afspaces' ),
						'sortRel'      => __( 'Relevanz', 'afspaces' ),
						'sortDate'     => __( 'Neueste zuerst', 'afspaces' ),
						'semantic'     => __( 'Semantische Suche einbeziehen', 'afspaces' ),
						'wordMode'     => __( 'Wortmodus', 'afspaces' ),
						'wordAny'      => __( 'Eines der Wörter', 'afspaces' ),
						'wordAll'      => __( 'Alle Wörter', 'afspaces' ),
						'searchIn'     => __( 'Suchen in', 'afspaces' ),
						'inAll'        => __( 'Titel & Text', 'afspaces' ),
						'inTitle'      => __( 'Nur Titel', 'afspaces' ),
						'filters'      => __( 'Filter', 'afspaces' ),
						'group'        => __( 'Arbeitsgruppe', 'afspaces' ),
						'anyGroup'     => __( 'Alle Arbeitsgruppen', 'afspaces' ),
						'author'       => __( 'Autor:in', 'afspaces' ),
						'authorPh'     => __( 'Name der Autorin/des Autors', 'afspaces' ),
						'dateFrom'     => __( 'Von', 'afspaces' ),
						'dateTo'       => __( 'Bis', 'afspaces' ),
						'close'        => __( 'Suche schließen', 'afspaces' ),
						'searching'    => __( 'Suche läuft …', 'afspaces' ),
						'noResults'    => __( 'Keine Treffer.', 'afspaces' ),
						/* translators: %d: Trefferzahl */
						'resultCount'  => __( '%d Treffer', 'afspaces' ),
						'toResult'     => __( 'Zum gefundenen Beitrag', 'afspaces' ),
						'prev'         => __( 'Zurück', 'afspaces' ),
						'next'         => __( 'Weiter', 'afspaces' ),
						'sourceForum'  => __( 'Forum', 'afspaces' ),
						'sourceWp'     => __( 'Beitrag', 'afspaces' ),
						'error'        => __( 'Die Suche ist fehlgeschlagen. Bitte erneut versuchen.', 'afspaces' ),
						'open'         => __( 'Suche öffnen', 'afspaces' ),
					),
				)
			);

			wp_enqueue_script( 'afspaces-search-modal' );
		}

		/**
		 * Gibt das Overlay-Markup im Footer aus (versteckt bis geöffnet).
		 *
		 * @return void
		 */
		public function render_modal(): void {
			?>
			<div id="afspaces-search-overlay" class="afspaces-search-overlay" hidden>
				<div class="afspaces-search-overlay__backdrop" data-afspaces-search-close></div>
				<div class="afspaces-search-overlay__dialog" role="dialog" aria-modal="true" aria-labelledby="afspaces-search-overlay-title">
					<div class="afspaces-search-overlay__header">
						<h2 id="afspaces-search-overlay-title" class="afspaces-search-overlay__title"><?php echo esc_html__( 'Suche', 'afspaces' ); ?></h2>
						<button type="button" class="afspaces-search-overlay__close" data-afspaces-search-close aria-label="<?php echo esc_attr__( 'Suche schließen', 'afspaces' ); ?>">&times;</button>
					</div>
					<div class="afspaces-search-overlay__body">
						<!-- Inhalte werden per JavaScript erzeugt. -->
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Rendert einen Trigger-Button (Shortcode `[afspaces_search_button]`).
		 *
		 * @param array<string,mixed>|string $atts Attribute.
		 * @return string
		 */
		public function render_button( $atts = array() ): string {
			$atts  = shortcode_atts( array( 'label' => __( 'Suche', 'afspaces' ) ), is_array( $atts ) ? $atts : array(), 'afspaces_search_button' );
			$label = (string) $atts['label'];

			return sprintf(
				'<button type="button" class="afspaces-button afspaces-search-trigger" data-afspaces-search-open><span class="afspaces-search-trigger__icon" aria-hidden="true">🔍</span> %s</button>',
				esc_html( $label )
			);
		}

		/**
		 * Rendert einen Textlink, der das Overlay öffnet (Shortcode `[afspaces_search_link]`).
		 *
		 * Für Menüs kann alternativ ein individueller Link mit dem Ziel
		 * `#afspaces-search` angelegt werden; das Skript fängt solche Links ab.
		 *
		 * @param array<string,mixed>|string $atts Attribute.
		 * @return string
		 */
		public function render_link( $atts = array() ): string {
			$atts  = shortcode_atts( array( 'label' => __( 'Suche', 'afspaces' ) ), is_array( $atts ) ? $atts : array(), 'afspaces_search_link' );
			$label = (string) $atts['label'];

			return sprintf(
				'<a href="#afspaces-search" class="afspaces-search-link" data-afspaces-search-open>%s</a>',
				esc_html( $label )
			);
		}
	}
}
