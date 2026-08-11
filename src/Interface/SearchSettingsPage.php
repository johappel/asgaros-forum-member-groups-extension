<?php
/**
 * Admin-Seite für die Sucheinstellungen (Embeddings / semantische Suche).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Database\SearchIndexRepository;
use AFSpaces\Application\SearchIndexer;
use AFSpaces\Search\SearchSettings;

if ( ! class_exists( 'AFSpaces\\Interface\\SearchSettingsPage' ) ) {

	/**
	 * Konfiguriert die semantische Suche und stößt die Reindexierung an.
	 */
	class SearchSettingsPage {

		private const REINDEX_ACTION = 'afspaces_search_reindex';

		private SearchIndexer $indexer;
		private SearchIndexRepository $index;

		/**
		 * Konstruktor.
		 *
		 * @param SearchIndexer         $indexer Indexer.
		 * @param SearchIndexRepository $index   Index-Repository.
		 */
		public function __construct( SearchIndexer $indexer, SearchIndexRepository $index ) {
			$this->indexer = $indexer;
			$this->index   = $index;
		}

		/**
		 * Registriert Admin-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_post_' . self::REINDEX_ACTION, array( $this, 'handle_reindex' ) );
		}

		/**
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				'afspaces_search_group',
				SearchSettings::OPTION_KEY,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( SearchSettings::class, 'sanitize' ),
					'default'           => SearchSettings::defaults(),
				)
			);
		}

		/**
		 * Verarbeitet die manuelle Reindexierung.
		 *
		 * @return void
		 */
		public function handle_reindex(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Keine Berechtigung.', 'afspaces' ) );
			}
			check_admin_referer( self::REINDEX_ACTION );

			$stats = $this->indexer->reindex_all();

			$redirect = add_query_arg(
				array(
					'page'      => AFSpacesSettingsPage::PAGE_SLUG,
					'tab'       => 'search',
					'reindexed' => 1,
					'indexed'   => (int) $stats['indexed'],
					'skipped'   => (int) $stats['skipped'],
					'errors'    => (int) $stats['errors'],
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect );
			exit;
		}

		/**
		 * Liefert die auswählbaren öffentlichen Beitragstypen (slug => Label).
		 *
		 * @return array<string,string>
		 */
		private function available_post_types(): array {
			if ( ! function_exists( 'get_post_types' ) ) {
				return array( 'post' => __( 'Beiträge', 'afspaces' ), 'page' => __( 'Seiten', 'afspaces' ) );
			}
			$objects = get_post_types(
				array(
					'public'              => true,
					'exclude_from_search' => false,
				),
				'objects'
			);
			$result = array();
			foreach ( $objects as $slug => $obj ) {
				if ( 'attachment' === $slug ) {
					continue;
				}
				$result[ (string) $slug ] = isset( $obj->labels->name ) ? (string) $obj->labels->name : (string) $slug;
			}
			return $result;
		}

		/**
		 * Rendert die Einstellungsseite.
		 *
		 * @return void
		 */
		public function render_page( bool $embedded = false ): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$o          = SearchSettings::all();
			$has_key    = '' !== trim( (string) $o['embedding_api_key'] );
			$forum_count = $this->index->count( SearchIndexRepository::SOURCE_FORUM );
			$wp_count    = $this->index->count( SearchIndexRepository::SOURCE_WP );
			?>
			<?php if ( ! $embedded ) : ?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Arbeitsgruppen-Suche', 'afspaces' ); ?></h1>
			<?php endif; ?>

				<?php if ( isset( $_GET['reindexed'] ) ) : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: indexiert, 2: übersprungen, 3: Fehler */
									__( 'Reindexierung abgeschlossen: %1$d indexiert, %2$d unverändert, %3$d Fehler.', 'afspaces' ),
									(int) ( $_GET['indexed'] ?? 0 ),
									(int) ( $_GET['skipped'] ?? 0 ),
									(int) ( $_GET['errors'] ?? 0 )
								)
							);
							?>
						</p>
					</div>
				<?php endif; ?>

				<p class="description">
					<?php echo esc_html__( 'Die semantische Suche sendet Inhalte zur Vektor-Erzeugung an die konfigurierte Embedding-API. Private Arbeitsgruppen-Inhalte werden nur übertragen, wenn dies unten ausdrücklich aktiviert wird.', 'afspaces' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'afspaces_search_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Semantische Suche aktivieren', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[embedding_enabled]" value="1" <?php checked( ! empty( $o['embedding_enabled'] ) ); ?> />
									<?php echo esc_html__( 'Aktiv', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-emb-url"><?php echo esc_html__( 'API-Endpunkt', 'afspaces' ); ?></label></th>
							<td><input type="url" id="afspaces-emb-url" class="regular-text" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[embedding_api_url]" value="<?php echo esc_attr( (string) $o['embedding_api_url'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-emb-key"><?php echo esc_html__( 'API-Schlüssel', 'afspaces' ); ?></label></th>
							<td>
								<input type="password" id="afspaces-emb-key" class="regular-text" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[embedding_api_key]" value="" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( '•••••• (gespeichert – leer lassen, um beizubehalten)', 'afspaces' ) : ''; ?>" />
								<p class="description"><?php echo esc_html__( 'Der Schlüssel wird verschlüsselt gespeichert und nie im Frontend ausgegeben. Feld leer lassen, um den vorhandenen Schlüssel zu behalten.', 'afspaces' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-emb-model"><?php echo esc_html__( 'Modell', 'afspaces' ); ?></label></th>
							<td><input type="text" id="afspaces-emb-model" class="regular-text" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[embedding_model]" value="<?php echo esc_attr( (string) $o['embedding_model'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WordPress-Beiträge indexieren', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[index_wp]" value="1" <?php checked( ! empty( $o['index_wp'] ) ); ?> />
									<?php echo esc_html__( 'Beiträge und Seiten in die Suche einbeziehen', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Durchsuchte Inhaltstypen', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="afspaces-all-types" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[wp_all_public_types]" value="1" <?php checked( ! empty( $o['wp_all_public_types'] ) ); ?> />
									<?php echo esc_html__( 'Alle öffentlichen Beitragstypen einbeziehen', 'afspaces' ); ?>
								</label>
								<fieldset style="margin-top:0.75rem;">
									<legend class="screen-reader-text"><?php echo esc_html__( 'Einzelne Beitragstypen', 'afspaces' ); ?></legend>
									<?php
									$selected_types = (array) ( $o['wp_post_types'] ?? array( 'post', 'page' ) );
									foreach ( $this->available_post_types() as $slug => $label ) :
										?>
										<label style="display:inline-block; margin-right:1rem;">
											<input type="checkbox" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[wp_post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_types, true ) ); ?> />
											<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
										</label>
									<?php endforeach; ?>
									<p class="description"><?php echo esc_html__( 'Diese Auswahl greift, wenn „Alle öffentlichen Beitragstypen“ deaktiviert ist. Nach Änderungen bitte neu indexieren.', 'afspaces' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Private Inhalte einbetten', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[index_private]" value="1" <?php checked( ! empty( $o['index_private'] ) ); ?> />
									<?php echo esc_html__( 'Inhalte privater Arbeitsgruppen an die externe API senden (Datenschutz beachten)', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'WordPress-Suche ersetzen', 'afspaces' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[replace_wp_search]" value="1" <?php checked( ! empty( $o['replace_wp_search'] ) ); ?> />
									<?php echo esc_html__( 'Normale WordPress-Suchformulare öffnen das AFSpaces-Such-Overlay', 'afspaces' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-sem-weight"><?php echo esc_html__( 'Gewichtung semantisch', 'afspaces' ); ?></label></th>
							<td><input type="number" step="0.1" min="0" id="afspaces-sem-weight" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[semantic_weight]" value="<?php echo esc_attr( (string) $o['semantic_weight'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-kw-weight"><?php echo esc_html__( 'Gewichtung Keyword', 'afspaces' ); ?></label></th>
							<td><input type="number" step="0.1" min="0" id="afspaces-kw-weight" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[keyword_weight]" value="<?php echo esc_attr( (string) $o['keyword_weight'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="afspaces-min-score"><?php echo esc_html__( 'Mindest-Ähnlichkeit (semantisch)', 'afspaces' ); ?></label></th>
							<td>
								<input type="number" step="0.05" min="0" max="1" id="afspaces-min-score" name="<?php echo esc_attr( SearchSettings::OPTION_KEY ); ?>[semantic_min_score]" value="<?php echo esc_attr( (string) $o['semantic_min_score'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'Semantische Treffer mit geringerer Cosine-Ähnlichkeit werden ausgeblendet (0–1). Höhere Werte = strengere, nachvollziehbarere Ergebnisse. Empfehlung: 0.30.', 'afspaces' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>

				<hr />
				<h2><?php echo esc_html__( 'Index', 'afspaces' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Foren-Einträge, 2: WP-Einträge */
							__( 'Indexierte Einträge: %1$d Forenbeiträge, %2$d WordPress-Inhalte.', 'afspaces' ),
							$forum_count,
							$wp_count
						)
					);
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::REINDEX_ACTION ); ?>" />
					<?php wp_nonce_field( self::REINDEX_ACTION ); ?>
					<?php submit_button( __( 'Jetzt neu indexieren', 'afspaces' ), 'secondary', 'submit', false ); ?>
					<?php if ( ! SearchSettings::is_semantic_enabled() ) : ?>
						<p class="description"><?php echo esc_html__( 'Die semantische Suche ist noch nicht vollständig konfiguriert; die Reindexierung bleibt wirkungslos.', 'afspaces' ); ?></p>
					<?php endif; ?>
				</form>
			<?php if ( ! $embedded ) : ?>
			</div>
			<?php endif; ?>
			<?php
		}
	}
}
