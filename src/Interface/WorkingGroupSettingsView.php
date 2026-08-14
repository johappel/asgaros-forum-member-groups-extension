<?php
/**
 * Frontend-Ansicht fuer Arbeitsgruppen-Metadaten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Application\UserIdentityService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\SpaceCreationSettings;
use AFSpaces\Domain\SpaceLifecycle;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupSettingsView' ) ) {

	/**
	 * Rendert die bearbeitbaren Metadaten einer Arbeitsgruppe.
	 */
	class WorkingGroupSettingsView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private WorkingGroupService $working_groups;
		private UserIdentityService $identity;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, WorkingGroupService $working_groups, ?UserIdentityService $identity = null ) {
			$this->spaces = $spaces;
			$this->asgaros = $asgaros;
			$this->working_groups = $working_groups;
			$this->identity = $identity ?: new UserIdentityService();
		}

		public function render( int $space_id ): string {
			$actor = get_current_user_id();
			if ( 0 === $actor ) {
				return $this->notice( __( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return $this->notice( __( 'Diese Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->spaces->is_manager( $space_id, $actor ) && ! user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) ) {
				return $this->notice( __( 'Du darfst diese Arbeitsgruppe nicht bearbeiten.', 'afspaces' ) );
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			$forum_name = trim( (string) ( $forum['name'] ?? '' ) );
			if ( '' === $forum_name ) {
				$forum_name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space_id );
			}

			$meta = $this->working_groups->get_metadata( $space_id );
			$topics = $this->working_groups->list_topics();
			$responsibles = $this->working_groups->list_responsibles( $space_id );

			ob_start();
			?>
			<section class="afspaces-working-group-settings" aria-labelledby="afspaces-working-group-settings-heading">
				<h2 id="afspaces-working-group-settings-heading"><?php echo esc_html( sprintf( __( 'Arbeitsgruppen-Details - %s', 'afspaces' ), $forum_name ) ); ?></h2>
				<div class="afspaces-detail-toggle" role="group" aria-label="<?php echo esc_attr__( 'Zwischen Ansicht und Bearbeiten wechseln', 'afspaces' ); ?>">
					<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_GROUP, array( 'space_id' => $space_id ) ) ); ?>"><?php echo esc_html__( 'Ansicht', 'afspaces' ); ?></a>
					<a class="afspaces-button is-active" aria-current="page" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_SETTINGS, array( 'space_id' => $space_id ) ) ); ?>"><?php echo esc_html__( 'Bearbeiten', 'afspaces' ); ?></a>
				</div>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier pflegst du Beschreibung, Sichtbarkeit und Kontaktinformationen dieser Arbeitsgruppe.', 'afspaces' ); ?></p>
				<p class="description"><?php echo esc_html__( '„Sichtbarkeit in Übersichten" steuert nur, ob die Arbeitsgruppe unter „Entdecken" auffindbar ist – nicht, wer das Forum lesen darf. Den Zugriff auf die Forumsinhalte legst du weiter unten über „Arbeitsgruppe verwalten → Sichtbarkeit" (privat, geschützt, öffentlich) fest.', 'afspaces' ); ?></p>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-working-group-contact-heading">
					<div id="afspaces-working-group-contact-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Arbeitsgruppenverantwortliche', 'afspaces' ); ?></div>
					<?php if ( empty( $responsibles ) ) : ?>
						<p><?php echo esc_html__( 'Für diese Arbeitsgruppe sind derzeit keine Verantwortlichen sichtbar hinterlegt.', 'afspaces' ); ?></p>
					<?php else : ?>
						<ul class="afspaces-responsibles-list">
							<?php foreach ( $responsibles as $responsible ) : ?>
								<li>
									<a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => $responsible['user_id'] ) ) ); ?>"><?php echo esc_html( $this->identity->get_display_name( (int) $responsible['user_id'] ) ?: (string) $responsible['display_name'] ); ?></a>
									<span class="afspaces-tag"><?php echo esc_html( $responsible['role_label'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>

				<form method="post" class="afspaces-working-group-form afspaces-form-grid">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="save_working_group_meta" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />

					<label for="afspaces-description"><?php echo esc_html__( 'Beschreibung', 'afspaces' ); ?></label>
					<textarea id="afspaces-description" name="description" rows="5"><?php echo esc_textarea( $meta->description ); ?></textarea>

					<label for="afspaces-contact-text"><?php echo esc_html__( 'Kontakttext für Arbeitsgruppenverantwortliche', 'afspaces' ); ?></label>
					<textarea id="afspaces-contact-text" name="contact_text" rows="4"><?php echo esc_textarea( $meta->contact_text ); ?></textarea>

					<label for="afspaces-accent-color"><?php echo esc_html__( 'Akzentfarbe (Corporate Design)', 'afspaces' ); ?></label>
					<select id="afspaces-accent-color" name="accent_color">
						<?php foreach ( WorkingGroupService::accent_color_options() as $color => $label ) : ?>
							<?php $option_text_color = '#f5ae35' === $color ? '#1d2327' : '#ffffff'; ?>
							<option value="<?php echo esc_attr( $color ); ?>" style="background-color: <?php echo esc_attr( $color ); ?>; color: <?php echo esc_attr( $option_text_color ); ?>;" <?php selected( $meta->accent_color, $color ); ?>><?php echo esc_html( $label . ' (' . $color . ')' ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="afspaces-icon"><?php echo esc_html__( 'Symbol', 'afspaces' ); ?></label>
					<select id="afspaces-icon" name="icon">
						<?php foreach ( WorkingGroupService::icon_options() as $icon => $label ) : ?>
							<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $meta->icon, $icon ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>

					<label for="afspaces-directory-visibility"><?php echo esc_html__( 'Sichtbarkeit in Übersichten', 'afspaces' ); ?></label>
					<select id="afspaces-directory-visibility" name="directory_visibility">
						<option value="<?php echo esc_attr( WorkingGroupMeta::DIRECTORY_LISTED ); ?>" <?php selected( $meta->directory_visibility, WorkingGroupMeta::DIRECTORY_LISTED ); ?>><?php echo esc_html__( 'Für angemeldete Personen sichtbar', 'afspaces' ); ?></option>
						<option value="<?php echo esc_attr( WorkingGroupMeta::DIRECTORY_MEMBERS ); ?>" <?php selected( $meta->directory_visibility, WorkingGroupMeta::DIRECTORY_MEMBERS ); ?>><?php echo esc_html__( 'Nur für Mitglieder sichtbar', 'afspaces' ); ?></option>
						<option value="<?php echo esc_attr( WorkingGroupMeta::DIRECTORY_HIDDEN ); ?>" <?php selected( $meta->directory_visibility, WorkingGroupMeta::DIRECTORY_HIDDEN ); ?>><?php echo esc_html__( 'Nur im eigenen Profil und Management sichtbar', 'afspaces' ); ?></option>
					</select>

					<label for="afspaces-join-policy"><?php echo esc_html__( 'Beitrittslogik', 'afspaces' ); ?></label>
					<select id="afspaces-join-policy" name="join_policy">
						<option value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_REQUEST ); ?>" <?php selected( $meta->join_policy, WorkingGroupMeta::JOIN_POLICY_REQUEST ); ?>><?php echo esc_html__( 'Beitritt per Anfrage', 'afspaces' ); ?></option>
						<option value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY ); ?>" <?php selected( $meta->join_policy, WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY ); ?>><?php echo esc_html__( 'Nur per Einladung', 'afspaces' ); ?></option>
						<option value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_CLOSED ); ?>" <?php selected( $meta->join_policy, WorkingGroupMeta::JOIN_POLICY_CLOSED ); ?>><?php echo esc_html__( 'Geschlossen ohne Beitritt', 'afspaces' ); ?></option>
					</select>

					<label for="afspaces-join-requests-enabled" class="afspaces-checkbox">
						<input type="checkbox" id="afspaces-join-requests-enabled" name="join_requests_enabled" value="1" <?php checked( $meta->join_requests_enabled ); ?> />
						<span><?php echo esc_html__( 'Beitrittsanfragen grundsätzlich erlauben', 'afspaces' ); ?></span>
					</label>
					<p class="description"><?php echo esc_html__( 'Wirkt nur zusammen mit der Beitrittslogik „Beitritt per Anfrage". Ist die Beitrittslogik „Nur per Einladung" oder „Geschlossen", hat dieses Häkchen keine Wirkung.', 'afspaces' ); ?></p>

					<?php if ( ! empty( $topics ) ) : ?>
						<label for="afspaces-topics"><?php echo esc_html__( 'Themen', 'afspaces' ); ?></label>
						<select id="afspaces-topics" name="topic_ids[]" multiple size="5">
							<?php foreach ( $topics as $topic ) : ?>
								<option value="<?php echo esc_attr( (string) $topic['id'] ); ?>" <?php selected( in_array( (int) $topic['id'], $meta->topic_ids, true ) ); ?>><?php echo esc_html( (string) $topic['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'Mehrfachauswahl ist möglich. Es werden nur gültige Begriffe der konfigurierten Themen-Taxonomie gespeichert.', 'afspaces' ); ?></p>
					<?php endif; ?>

					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Arbeitsgruppen-Details speichern', 'afspaces' ); ?></button>
				</form>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-working-group-scope-heading">
					<div id="afspaces-working-group-scope-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Verantwortung und Moderation', 'afspaces' ); ?></div>
					<p><?php echo esc_html__( 'Arbeitsgruppenverantwortliche verwalten Mitglieder, Einladungen und Beitrittsanfragen innerhalb von AFSpaces.', 'afspaces' ); ?></p>
					<p><?php echo esc_html__( 'Als Verantwortliche moderierst du außerdem die Themen deines eigenen Forums (Themen schließen, wieder öffnen oder löschen) über den Reiter „Moderation". Diese Rechte gelten ausschließlich für dein Forum und geben keine Moderationsrechte in anderen Foren.', 'afspaces' ); ?></p>
				</section>

				<?php echo $this->render_management( $space, $actor, $forum_name, $responsibles ); ?>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		/**
		 * Rendert die Lebenszyklus- und Verwaltungssteuerung eines Raums (MVP 4).
		 *
		 * @param \AFSpaces\Domain\Space          $space        Space.
		 * @param int                             $actor        Akteur.
		 * @param string                          $forum_name   Anzeigename.
		 * @param array<int,array<string,mixed>>  $responsibles Verantwortliche.
		 * @return string
		 */
		private function render_management( $space, int $actor, string $forum_name, array $responsibles ): string {
			$is_admin = user_can( $actor, Capabilities::MANAGE_ALL_SPACES );
			$is_owner = $is_admin || ( $space->owner_user_id === $actor );
			$settings = SpaceCreationSettings::load();

			ob_start();
			?>
			<section class="afspaces-section-card content-container afspaces-space-management" aria-labelledby="afspaces-space-management-heading">
				<div id="afspaces-space-management-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Arbeitsgruppe verwalten', 'afspaces' ); ?></div>

				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Status */
							__( 'Aktueller Status: %s', 'afspaces' ),
							self::status_label( $space->status )
						)
					);
					?>
				</p>

				<?php if ( SpaceLifecycle::STATUS_REJECTED === $space->status && '' !== $space->rejection_reason ) : ?>
					<p class="afspaces-message afspaces-message-error" role="alert">
						<?php echo esc_html( sprintf( __( 'Ablehnungsgrund: %s', 'afspaces' ), $space->rejection_reason ) ); ?>
					</p>
				<?php endif; ?>

				<form method="post" class="afspaces-form-grid">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="rename_space" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
					<label for="afspaces-rename"><?php echo esc_html__( 'Name der Arbeitsgruppe', 'afspaces' ); ?></label>
					<input type="text" id="afspaces-rename" name="name" value="<?php echo esc_attr( $forum_name ); ?>" maxlength="<?php echo esc_attr( (string) $settings->name_max_length ); ?>" />
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Namen speichern', 'afspaces' ); ?></button>
				</form>

				<form method="post" class="afspaces-form-grid">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="change_space_visibility" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
					<label for="afspaces-visibility"><?php echo esc_html__( 'Sichtbarkeit', 'afspaces' ); ?></label>
					<select id="afspaces-visibility" name="visibility">
						<?php foreach ( $settings->allowed_visibilities as $visibility ) : ?>
							<option value="<?php echo esc_attr( $visibility ); ?>" <?php selected( $space->visibility, $visibility ); ?>><?php echo esc_html( CreateSpaceView::visibility_label( $visibility ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Sichtbarkeit speichern', 'afspaces' ); ?></button>
				</form>

				<?php if ( $is_owner && count( $responsibles ) > 0 ) : ?>
					<form method="post" class="afspaces-form-grid">
						<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
						<input type="hidden" name="afspaces_action" value="transfer_space_owner" />
						<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
						<label for="afspaces-transfer-owner"><?php echo esc_html__( 'Verantwortung übertragen an', 'afspaces' ); ?></label>
						<select id="afspaces-transfer-owner" name="new_owner_id">
							<?php foreach ( $responsibles as $responsible ) : ?>
								<?php if ( (int) $responsible['user_id'] !== $space->owner_user_id ) : ?>
									<option value="<?php echo esc_attr( (string) $responsible['user_id'] ); ?>"><?php echo esc_html( $this->identity->get_display_name( (int) $responsible['user_id'] ) ?: (string) $responsible['display_name'] ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="afspaces-button" data-afspaces-confirm="<?php echo esc_attr__( 'Möchtest du die Verantwortung wirklich übertragen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Verantwortung übertragen', 'afspaces' ); ?></button>
					</form>
				<?php endif; ?>

				<div class="afspaces-management-lifecycle">
					<?php if ( SpaceLifecycle::STATUS_ACTIVE === $space->status ) : ?>
						<form method="post">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="archive_space" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
							<button type="submit" class="afspaces-button" data-afspaces-confirm="<?php echo esc_attr__( 'Arbeitsgruppe wirklich archivieren?', 'afspaces' ); ?>"><?php echo esc_html__( 'Archivieren', 'afspaces' ); ?></button>
						</form>
					<?php elseif ( SpaceLifecycle::STATUS_ARCHIVED === $space->status ) : ?>
						<form method="post">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="reactivate_space" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
							<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Reaktivieren', 'afspaces' ); ?></button>
						</form>
					<?php endif; ?>

					<?php if ( $is_owner && SpaceLifecycle::STATUS_DELETED !== $space->status ) : ?>
						<form method="post" class="afspaces-delete-space">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="delete_space" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
							<p class="description afspaces-danger-hint"><?php echo esc_html__( 'Das Löschen entfernt den Forenbereich der Arbeitsgruppe endgültig. Diese Aktion kann nicht rückgängig gemacht werden.', 'afspaces' ); ?></p>
							<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Diese Arbeitsgruppe wirklich unwiderruflich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Arbeitsgruppe löschen', 'afspaces' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Menschlich lesbare Statusbezeichnung.
		 *
		 * @param string $status Status.
		 * @return string
		 */
		private static function status_label( string $status ): string {
			switch ( $status ) {
				case SpaceLifecycle::STATUS_PENDING:
					return __( 'Wartet auf Freigabe', 'afspaces' );
				case SpaceLifecycle::STATUS_ARCHIVED:
					return __( 'Archiviert', 'afspaces' );
				case SpaceLifecycle::STATUS_REJECTED:
					return __( 'Abgelehnt', 'afspaces' );
				case SpaceLifecycle::STATUS_DELETED:
					return __( 'Gelöscht', 'afspaces' );
				case SpaceLifecycle::STATUS_ACTIVE:
				default:
					return __( 'Aktiv', 'afspaces' );
			}
		}

		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

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
