<?php
/**
 * Verwaltungsansicht für die Toolbox-Links einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\ToolboxService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\SpaceLink;

if ( ! class_exists( 'AFSpaces\\Interface\\ToolboxLinksView' ) ) {

	/**
	 * Rendert die Verwaltung (Anlegen, Bearbeiten, Löschen, Sortieren) der Links.
	 */
	class ToolboxLinksView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private ToolboxService $toolbox;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, ToolboxService $toolbox ) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->toolbox = $toolbox;
		}

		/**
		 * Rendert die Verwaltungsansicht.
		 *
		 * @param int $space_id Space-ID.
		 * @return string
		 */
		public function render( int $space_id ): string {
			$actor = get_current_user_id();
			if ( 0 === $actor ) {
				return $this->notice( __( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return $this->notice( __( 'Arbeitsgruppe nicht gefunden.', 'afspaces' ) );
			}

			$can_manage = user_can( $actor, Capabilities::MANAGE_ALL_SPACES )
				|| $this->spaces->is_manager( $space_id, $actor );
			if ( ! $can_manage ) {
				return $this->notice( __( 'Du darfst den Werkzeugkasten dieser Arbeitsgruppe nicht verwalten.', 'afspaces' ) );
			}

			$links       = $this->toolbox->list_links( $space_id );
			$icon_options = SpaceLink::icon_options();

			ob_start();
			?>
			<section class="afspaces-toolbox-admin" aria-labelledby="afspaces-toolbox-admin-heading">
				<?php echo $this->render_message(); ?>
				<h2 id="afspaces-toolbox-admin-heading"><?php echo esc_html__( 'Toolbox / Links', 'afspaces' ); ?></h2>
				<p><?php echo esc_html__( 'Verwalte hier die Links, die den Mitgliedern im Werkzeugkasten dieser Arbeitsgruppe angezeigt werden.', 'afspaces' ); ?></p>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-toolbox-list-heading">
					<div id="afspaces-toolbox-list-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Vorhandene Links', 'afspaces' ); ?></div>
					<?php if ( empty( $links ) ) : ?>
						<p><?php echo esc_html__( 'Es wurden noch keine Links eingerichtet.', 'afspaces' ); ?></p>
					<?php else : ?>
						<div class="afspaces-table-wrap">
						<table class="afspaces-table afspaces-table--responsive afspaces-toolbox-links-table">
							<thead>
								<tr>
									<th scope="col"><?php echo esc_html__( 'Titel', 'afspaces' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Ziel', 'afspaces' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Reihenfolge', 'afspaces' ); ?></th>
									<th scope="col"><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$last_index = count( $links ) - 1;
								foreach ( $links as $index => $link ) :
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $link->title ); ?></strong>
											<?php if ( '' !== $link->description ) : ?>
												<br /><span class="description"><?php echo esc_html( $link->description ); ?></span>
											<?php endif; ?>
										</td>
										<td><a href="<?php echo esc_url( $link->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link->url ); ?></a></td>
										<td>
											<div class="afspaces-table__actions">
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="move_toolbox_link" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="toolbox_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
													<input type="hidden" name="direction" value="up" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"<?php echo 0 === $index ? ' disabled aria-disabled="true"' : ''; ?> aria-label="<?php echo esc_attr__( 'Nach oben verschieben', 'afspaces' ); ?>">
														<span class="fas fa-arrow-up" aria-hidden="true"></span>
													</button>
												</form>
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="move_toolbox_link" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="toolbox_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
													<input type="hidden" name="direction" value="down" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"<?php echo $index === $last_index ? ' disabled aria-disabled="true"' : ''; ?> aria-label="<?php echo esc_attr__( 'Nach unten verschieben', 'afspaces' ); ?>">
														<span class="fas fa-arrow-down" aria-hidden="true"></span>
													</button>
												</form>
											</div>
										</td>
										<td>
											<details class="afspaces-toolbox-edit">
												<summary class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Bearbeiten', 'afspaces' ); ?></summary>
												<?php echo $this->render_edit_form( $space_id, $link, $icon_options ); ?>
											</details>
											<form method="post" class="afspaces-inline-form" data-afspaces-confirm="<?php echo esc_attr__( 'Diesen Link wirklich löschen?', 'afspaces' ); ?>">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="delete_toolbox_link" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="toolbox_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
												<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Löschen', 'afspaces' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
					<?php endif; ?>
				</section>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-toolbox-add-heading">
					<div id="afspaces-toolbox-add-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Neuen Link hinzufügen', 'afspaces' ); ?></div>
					<?php echo $this->render_add_form( $space_id, $icon_options ); ?>
				</section>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert das Formular zum Anlegen eines Links.
		 *
		 * @param int                   $space_id     Space-ID.
		 * @param array<string,string>  $icon_options Icon-Auswahl.
		 * @return string
		 */
		private function render_add_form( int $space_id, array $icon_options ): string {
			ob_start();
			?>
			<form method="post" class="afspaces-form afspaces-toolbox-form">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="add_toolbox_link" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<?php echo $this->render_fields( 'add', null, $icon_options ); ?>
				<p><button type="submit" class="afspaces-button"><?php echo esc_html__( 'Link hinzufügen', 'afspaces' ); ?></button></p>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert das Formular zum Bearbeiten eines Links.
		 *
		 * @param int                  $space_id     Space-ID.
		 * @param SpaceLink            $link         Zu bearbeitender Link.
		 * @param array<string,string> $icon_options Icon-Auswahl.
		 * @return string
		 */
		private function render_edit_form( int $space_id, SpaceLink $link, array $icon_options ): string {
			ob_start();
			?>
			<form method="post" class="afspaces-form afspaces-toolbox-form">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="update_toolbox_link" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="toolbox_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
				<?php echo $this->render_fields( 'edit-' . $link->id, $link, $icon_options ); ?>
				<p><button type="submit" class="afspaces-button"><?php echo esc_html__( 'Änderungen speichern', 'afspaces' ); ?></button></p>
			</form>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert die gemeinsamen Formularfelder.
		 *
		 * @param string               $id_prefix    Eindeutiger Präfix für Feld-IDs.
		 * @param SpaceLink|null        $link         Vorbelegung (null = neu).
		 * @param array<string,string> $icon_options Icon-Auswahl.
		 * @return string
		 */
		private function render_fields( string $id_prefix, ?SpaceLink $link, array $icon_options ): string {
			$title        = $link ? $link->title : '';
			$url          = $link ? $link->url : '';
			$description  = $link ? $link->description : '';
			$icon         = $link ? $link->icon : '';
			$open_new_tab = $link ? $link->open_new_tab : false;

			ob_start();
			?>
			<p class="afspaces-field">
				<label for="afspaces-toolbox-title-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Titel', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-toolbox-title-<?php echo esc_attr( $id_prefix ); ?>" name="title" maxlength="200" required value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p class="afspaces-field">
				<label for="afspaces-toolbox-url-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Ziel (URL)', 'afspaces' ); ?></label>
				<input type="url" id="afspaces-toolbox-url-<?php echo esc_attr( $id_prefix ); ?>" name="url" inputmode="url" placeholder="https://" required value="<?php echo esc_attr( $url ); ?>" />
			</p>
			<p class="afspaces-field">
				<label for="afspaces-toolbox-desc-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Beschreibung (optional)', 'afspaces' ); ?></label>
				<input type="text" id="afspaces-toolbox-desc-<?php echo esc_attr( $id_prefix ); ?>" name="description" maxlength="500" value="<?php echo esc_attr( $description ); ?>" />
			</p>
			<p class="afspaces-field">
				<label for="afspaces-toolbox-icon-<?php echo esc_attr( $id_prefix ); ?>"><?php echo esc_html__( 'Symbol (optional)', 'afspaces' ); ?></label>
				<select id="afspaces-toolbox-icon-<?php echo esc_attr( $id_prefix ); ?>" name="icon">
					<?php foreach ( $icon_options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $icon, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="afspaces-field afspaces-field-checkbox">
				<label>
					<input type="checkbox" name="open_new_tab" value="1"<?php checked( $open_new_tab ); ?> />
					<?php echo esc_html__( 'In neuem Tab öffnen', 'afspaces' ); ?>
				</label>
			</p>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * @param string $text Meldung.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

		/**
		 * Gibt eine gespeicherte Statusmeldung aus der Session aus.
		 *
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$msg = $_SESSION['afspaces_message'];
			unset( $_SESSION['afspaces_message'] );

			$role = ( 'error' === $msg['type'] ) ? 'alert' : 'status';
			return sprintf(
				'<div class="afspaces-message afspaces-message-%1$s" role="%2$s" aria-live="polite">%3$s</div>',
				esc_attr( $msg['type'] ),
				esc_attr( $role ),
				esc_html( $msg['message'] )
			);
		}
	}
}
