<?php
/**
 * Werkzeugkasten-Integration (Toolbox) in das Asgaros-Forum-Menü.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\ToolboxService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceLink;

if ( ! class_exists( 'AFSpaces\\Interface\\ForumToolbox' ) ) {

	/**
	 * Blendet in Foren einer Arbeitsgruppe einen Menüpunkt „Toolbox“ ein und
	 * rendert den zugehörigen Dialog mit Links und dem Dokumente-Einstieg.
	 *
	 * Verwendeter, dokumentierter Asgaros-Hook:
	 * - Action `asgarosforum_custom_header_menu` (in allen Forumkontexten aktiv:
	 *   Forumübersicht, Topic, neues Thema, Antwort, Beitrag bearbeiten).
	 */
	class ForumToolbox {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private ToolboxService $toolbox;

		/**
		 * Aufgelöster Space des aktuellen Forumkontexts (false = noch nicht ermittelt).
		 *
		 * @var Space|null|false
		 */
		private $resolved_space = false;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, ToolboxService $toolbox ) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->toolbox = $toolbox;
		}

		/**
		 * Registriert die Asgaros- und WordPress-Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'asgarosforum_custom_header_menu', array( $this, 'render_menu_entry' ) );
			add_action( 'wp_footer', array( $this, 'render_dialog' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Ermittelt den zum aktuellen Forumkontext gehörenden aktiven Space.
		 *
		 * Deckt Forum-, Topic- und Beitrags-/Editieransichten ab, da Asgaros in
		 * all diesen Ansichten `current_forum` auf das zugehörige Forum setzt.
		 *
		 * @return Space|null
		 */
		private function current_space(): ?Space {
			if ( false !== $this->resolved_space ) {
				return $this->resolved_space;
			}

			$this->resolved_space = null;

			if ( ! is_user_logged_in() ) {
				return null;
			}

			$forum_id = $this->asgaros->get_current_forum_id();
			if ( $forum_id < 1 ) {
				return null;
			}

			$this->resolved_space = $this->toolbox->resolve_space_by_forum( $forum_id );
			return $this->resolved_space;
		}

		/**
		 * Name der Arbeitsgruppe für Titel/Beschriftung.
		 *
		 * @param Space $space Space.
		 * @return string
		 */
		private function space_name( Space $space ): string {
			$forum = $this->asgaros->get_forum( $space->forum_id );
			$name  = trim( (string) ( $forum['name'] ?? '' ) );
			if ( '' === $name ) {
				$name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space->id );
			}
			return $name;
		}

		/**
		 * Bindet die Assets nur auf Forumseiten mit zugeordneter Arbeitsgruppe ein.
		 *
		 * @return void
		 */
		public function enqueue_assets(): void {
			if ( ! $this->current_space() ) {
				return;
			}

			wp_enqueue_style(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.css',
				array(),
				AFSPACES_VERSION
			);
			AppearanceSettingsPage::enqueue_inline_style();

			wp_enqueue_script(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.js',
				array(),
				AFSPACES_VERSION,
				true
			);
		}

		/**
		 * Rendert den Menüpunkt „Toolbox“ im Asgaros-Forum-Menü.
		 *
		 * @return void
		 */
		public function render_menu_entry(): void {
			$space = $this->current_space();
			if ( ! $space ) {
				return;
			}

			printf(
				'<a class="afspaces-toolbox-link" href="%1$s" data-afspaces-toolbox-open aria-haspopup="dialog" aria-controls="afspaces-toolbox-dialog"><span class="fas fa-toolbox" aria-hidden="true"></span> %2$s</a>',
				'#afspaces-toolbox-dialog',
				esc_html__( 'Toolbox', 'afspaces' )
			);
		}

		/**
		 * Rendert den Toolbox-Dialog im Footer (server-seitig, ohne JS nutzbar).
		 *
		 * @return void
		 */
		public function render_dialog(): void {
			$space = $this->current_space();
			if ( ! $space ) {
				return;
			}

			$actor      = get_current_user_id();
			$can_manage = $actor > 0 && (
				user_can( $actor, Capabilities::MANAGE_ALL_SPACES )
				|| $this->spaces->is_manager( $space->id, $actor )
			);

			$links      = $this->toolbox->list_links( $space->id );
			$space_name = $this->space_name( $space );

			/**
			 * Ermöglicht einem späteren Ausbau, die Dokumentenansicht in den
			 * Toolbox-Dialog einzuhängen. Gibt der Filter einen nicht-leeren
			 * String zurück, wird dieser statt des Platzhalters ausgegeben.
			 *
			 * Erwartet bereits escapte, sichere HTML-Ausgabe.
			 *
			 * @param string $content  Bisheriger Inhalt (Standard: leer).
			 * @param Space  $space    Aktuelle Arbeitsgruppe.
			 * @param int    $actor    Aktuelle Benutzer-ID.
			 */
			$documents_content = (string) apply_filters( 'afspaces_toolbox_documents_content', '', $space, $actor );
			?>
			<div id="afspaces-toolbox-dialog" class="afspaces-toolbox-overlay" role="dialog" aria-modal="true" aria-labelledby="afspaces-toolbox-title" hidden>
				<div class="afspaces-toolbox-backdrop" data-afspaces-toolbox-close tabindex="-1"></div>
				<div class="afspaces-toolbox-panel" role="document">
					<header class="afspaces-toolbox-header">
						<h2 id="afspaces-toolbox-title" class="afspaces-toolbox-title">
							<span class="fas fa-toolbox" aria-hidden="true"></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: Name der Arbeitsgruppe */
									__( 'Toolbox – %s', 'afspaces' ),
									$space_name
								)
							);
							?>
						</h2>
						<a class="afspaces-toolbox-close" href="#" data-afspaces-toolbox-close aria-label="<?php echo esc_attr__( 'Toolbox schließen', 'afspaces' ); ?>">
							<span class="fas fa-times" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php echo esc_html__( 'Schließen', 'afspaces' ); ?></span>
						</a>
					</header>

					<div class="afspaces-toolbox-body">
						<section class="afspaces-toolbox-section" aria-labelledby="afspaces-toolbox-links-heading">
							<h3 id="afspaces-toolbox-links-heading" class="afspaces-toolbox-section-title">
								<span class="fas fa-link" aria-hidden="true"></span>
								<?php echo esc_html__( 'Links', 'afspaces' ); ?>
							</h3>
							<?php if ( empty( $links ) ) : ?>
								<p class="afspaces-toolbox-empty"><?php echo esc_html__( 'Für diese Arbeitsgruppe wurden noch keine Links eingerichtet.', 'afspaces' ); ?></p>
							<?php else : ?>
								<ul class="afspaces-toolbox-links">
									<?php foreach ( $links as $link ) : ?>
										<?php
										$icon_class = SpaceLink::icon_class( $link->icon );
										$target     = $link->open_new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
										?>
										<li class="afspaces-toolbox-link-item">
											<a class="afspaces-toolbox-link-anchor" href="<?php echo esc_url( $link->url ); ?>"<?php echo $target; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statisch, oben aufgebaut. ?>>
												<?php if ( '' !== $icon_class ) : ?>
													<span class="afspaces-toolbox-link-icon <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
												<?php endif; ?>
												<span class="afspaces-toolbox-link-text">
													<span class="afspaces-toolbox-link-title"><?php echo esc_html( $link->title ); ?></span>
													<?php if ( '' !== $link->description ) : ?>
														<span class="afspaces-toolbox-link-desc"><?php echo esc_html( $link->description ); ?></span>
													<?php endif; ?>
												</span>
												<?php if ( $link->open_new_tab ) : ?>
													<span class="screen-reader-text"><?php echo esc_html__( '(öffnet in neuem Tab)', 'afspaces' ); ?></span>
												<?php endif; ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>

							<?php if ( $can_manage ) : ?>
								<p class="afspaces-toolbox-manage">
									<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_TOOLBOX, array( 'space_id' => $space->id ) ) ); ?>">
										<?php echo esc_html__( 'Links verwalten', 'afspaces' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</section>

						<section class="afspaces-toolbox-section" aria-labelledby="afspaces-toolbox-documents-heading">
							<h3 id="afspaces-toolbox-documents-heading" class="afspaces-toolbox-section-title">
								<span class="fas fa-folder-open" aria-hidden="true"></span>
								<?php echo esc_html__( 'Dokumente', 'afspaces' ); ?>
							</h3>
							<?php if ( '' !== $documents_content ) : ?>
								<div class="afspaces-toolbox-documents"><?php echo $documents_content; // Vom Filter geliefert, muss bereits escaped sein. ?></div>
							<?php else : ?>
								<p class="afspaces-toolbox-empty afspaces-toolbox-documents-placeholder">
									<?php echo esc_html__( 'Die Dokumentenansicht wird in einem folgenden Schritt ergänzt. Dann werden alle in den Foren dieser Arbeitsgruppe hochgeladenen Dateien hier gesammelt.', 'afspaces' ); ?>
								</p>
								<p>
									<button type="button" class="afspaces-button" data-afspaces-toolbox-documents disabled aria-disabled="true">
										<span class="fas fa-folder-open" aria-hidden="true"></span>
										<?php echo esc_html__( 'Dokumente öffnen', 'afspaces' ); ?>
									</button>
								</p>
							<?php endif; ?>
						</section>
					</div>
				</div>
			</div>
			<?php
		}
	}
}
