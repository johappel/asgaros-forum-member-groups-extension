<?php
/**
 * Frontend-Ansicht für die Einstellungen einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\UserIdentityService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\SpaceCreationSettings;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceLifecycle;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupSettingsView' ) ) {

	/** Rendert die bearbeitbaren Einstellungen einer Arbeitsgruppe. */
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

		/**
		 * Rendert die Einstellungsseite.
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
			$settings = SpaceCreationSettings::load();
			$is_privileged = user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) || user_can( $actor, Capabilities::MODERATE_SPACE );
			$visibility_options = array_values(
				array_filter(
					$settings->visibilities_for( $is_privileged ),
					static fn( string $visibility ): bool => SpaceCreationSettings::VISIBILITY_PUBLIC !== $visibility
				)
			);
			// Ein bereits gespeicherter Modus bleibt sichtbar, auch wenn die
			// aktuelle globale Auswahl ihn für neue Änderungen nicht mehr anbietet.
			// So kann ein Formular ohne Änderung sicher erneut gespeichert werden.
			if ( SpaceCreationSettings::VISIBILITY_PUBLIC !== $space->visibility
				&& ! in_array( $space->visibility, $visibility_options, true ) ) {
				array_unshift( $visibility_options, $space->visibility );
			}
			$join_mode = self::join_mode( $meta );

			ob_start();
			?>
			<section class="afspaces-working-group-settings" aria-label="<?php echo esc_attr__( 'Arbeitsgruppen-Details', 'afspaces' ); ?>">
				<?php echo $this->render_message(); ?>

				<form method="post" class="afspaces-working-group-form afspaces-settings-form">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="save_working_group_settings" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />

					<section class="afspaces-settings-section" aria-label="<?php echo esc_attr__( 'Allgemeine Angaben', 'afspaces' ); ?>">
						<div class="afspaces-settings-fields">
							<div class="afspaces-settings-field">
								<label for="afspaces-name"><?php echo esc_html__( 'Name der Arbeitsgruppe', 'afspaces' ); ?></label>
								<input type="text" id="afspaces-name" name="name" value="<?php echo esc_attr( $forum_name ); ?>" maxlength="<?php echo esc_attr( (string) $settings->name_max_length ); ?>" required />
							</div>
							<div class="afspaces-settings-field">
								<label for="afspaces-description"><?php echo esc_html__( 'Worum geht es?', 'afspaces' ); ?></label>
								<textarea id="afspaces-description" name="description" rows="5"><?php echo esc_textarea( $meta->description ); ?></textarea>
							</div>
							<?php if ( ! empty( $topics ) ) : ?>
								<div class="afspaces-settings-field">
									<label for="afspaces-topics"><?php echo esc_html__( 'Themen', 'afspaces' ); ?></label>
									<select id="afspaces-topics" name="topic_ids[]" multiple size="5">
										<?php foreach ( $topics as $topic ) : ?>
											<option value="<?php echo esc_attr( (string) $topic['id'] ); ?>" <?php selected( in_array( (int) $topic['id'], $meta->topic_ids, true ) ); ?>><?php echo esc_html( (string) $topic['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php echo esc_html__( 'Mehrfachauswahl ist möglich. Es werden nur gültige Begriffe der konfigurierten Themen-Taxonomie gespeichert.', 'afspaces' ); ?></p>
								</div>
							<?php endif; ?>
							<div class="afspaces-settings-field">
								<label for="afspaces-contact-text"><?php echo esc_html__( 'Wie können andere mit der Gruppe Kontakt aufnehmen?', 'afspaces' ); ?></label>
								<textarea id="afspaces-contact-text" name="contact_text" rows="4"><?php echo esc_textarea( $meta->contact_text ); ?></textarea>
							</div>
						</div>
					</section>

					<section class="afspaces-settings-section" aria-labelledby="afspaces-appearance-heading">
						<h3 id="afspaces-appearance-heading"><?php echo esc_html__( 'Darstellung', 'afspaces' ); ?></h3>
						<div class="afspaces-settings-fields">
							<div class="afspaces-settings-field">
								<label for="afspaces-icon"><?php echo esc_html__( 'Symbol', 'afspaces' ); ?></label>
								<select id="afspaces-icon" name="icon">
									<?php foreach ( WorkingGroupService::icon_options() as $icon => $label ) : ?>
										<option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $meta->icon, $icon ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<fieldset class="afspaces-accent-fieldset">
								<legend><?php echo esc_html__( 'Akzentfarbe', 'afspaces' ); ?></legend>
								<div class="afspaces-accent-options">
									<?php foreach ( WorkingGroupService::accent_color_options() as $color => $label ) : ?>
										<?php $is_selected = $meta->accent_color === $color; ?>
										<label class="afspaces-accent-option<?php echo $is_selected ? ' is-selected' : ''; ?>" style="--afspaces-accent-option-color: <?php echo esc_attr( $color ); ?>;">
											<input type="radio" name="accent_color" value="<?php echo esc_attr( $color ); ?>" <?php checked( $is_selected ); ?> />
											<span class="afspaces-accent-swatch" style="background-color: <?php echo esc_attr( $color ); ?>;" aria-hidden="true"></span>
											<span class="afspaces-accent-option-text"><strong><?php echo esc_html( $label ); ?></strong><code><?php echo esc_html( strtoupper( $color ) ); ?></code></span>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="description"><?php echo esc_html__( 'Diese Farbe wird zur Kennzeichnung der Arbeitsgruppe verwendet. Die markierte Kachel ist aktuell ausgewählt.', 'afspaces' ); ?></p>
							</fieldset>
						</div>
					</section>

					<section class="afspaces-settings-section" aria-labelledby="afspaces-access-heading">
						<h3 id="afspaces-access-heading"><?php echo esc_html__( 'Zugang und Mitgliedschaft', 'afspaces' ); ?></h3>
						<div class="afspaces-settings-fields">
							<h4 class="afspaces-access-subheading"><?php echo esc_html__( 'Leserschaft', 'afspaces' ); ?></h4>
							<fieldset class="afspaces-access-options afspaces-settings-field">
								<legend><?php echo esc_html__( 'Wer darf die Beiträge dieser Arbeitsgruppe lesen?', 'afspaces' ); ?></legend>
								<?php foreach ( $visibility_options as $visibility ) : ?>
									<?php $visibility_help_id = 'afspaces-visibility-help-' . sanitize_key( $visibility ); ?>
									<label class="afspaces-radio-option">
										<input type="radio" name="visibility" value="<?php echo esc_attr( $visibility ); ?>" aria-describedby="<?php echo esc_attr( $visibility_help_id ); ?>" <?php checked( $space->visibility, $visibility ); ?> />
										<span><?php echo esc_html( CreateSpaceView::visibility_label( $visibility ) ); ?></span>
									</label>
									<p id="<?php echo esc_attr( $visibility_help_id ); ?>" class="description"><?php echo esc_html( CreateSpaceView::visibility_description( $visibility ) ); ?></p>
								<?php endforeach; ?>
							</fieldset>
							<h4 class="afspaces-membership-subheading"><?php echo esc_html__( 'Mitgliedschaft', 'afspaces' ); ?></h4>
							<fieldset class="afspaces-membership-options afspaces-settings-field">
								<legend><?php echo esc_html__( 'Wer kann Mitglied werden und Beiträge verfassen?', 'afspaces' ); ?></legend>
								<label class="afspaces-radio-option">
									<input type="radio" name="join_policy" value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_REQUEST ); ?>" aria-describedby="afspaces-membership-help-request" <?php checked( $join_mode, WorkingGroupMeta::JOIN_POLICY_REQUEST ); ?> />
									<span><?php echo esc_html__( 'Beitritt auf Anfrage oder mit einem Einladungslink', 'afspaces' ); ?></span>
								</label>
								<p id="afspaces-membership-help-request" class="description"><?php echo esc_html( self::join_policy_description( WorkingGroupMeta::JOIN_POLICY_REQUEST ) ); ?></p>
								<label class="afspaces-radio-option">
									<input type="radio" name="join_policy" value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY ); ?>" aria-describedby="afspaces-membership-help-invite-only" <?php checked( $join_mode, WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY ); ?> />
									<span><?php echo esc_html__( 'Nur über Einladungslink', 'afspaces' ); ?></span>
								</label>
								<p id="afspaces-membership-help-invite-only" class="description"><?php echo esc_html( self::join_policy_description( WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY ) ); ?></p>
								<label class="afspaces-radio-option">
									<input type="radio" name="join_policy" value="<?php echo esc_attr( WorkingGroupMeta::JOIN_POLICY_CLOSED ); ?>" aria-describedby="afspaces-membership-help-closed" <?php checked( $join_mode, WorkingGroupMeta::JOIN_POLICY_CLOSED ); ?> />
									<span><?php echo esc_html__( 'Keine neuen Mitglieder', 'afspaces' ); ?></span>
								</label>
								<p id="afspaces-membership-help-closed" class="description"><?php echo esc_html( self::join_policy_description( WorkingGroupMeta::JOIN_POLICY_CLOSED ) ); ?></p>
							</fieldset>
							<p class="afspaces-inline-hint"><?php echo esc_html__( 'Moderationsrechte werden an anderer Stelle vergeben.', 'afspaces' ); ?></p>
						</div>
					</section>

					<section class="afspaces-settings-section afspaces-responsibles-section" aria-labelledby="afspaces-responsibles-heading">
						<h3 id="afspaces-responsibles-heading"><?php echo esc_html__( 'Verantwortliche', 'afspaces' ); ?></h3>
						<?php if ( empty( $responsibles ) ) : ?>
							<p><?php echo esc_html__( 'Für diese Arbeitsgruppe sind derzeit keine Verantwortlichen hinterlegt.', 'afspaces' ); ?></p>
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
						<p class="afspaces-inline-hint"><?php echo esc_html__( 'Verantwortliche verwalten Mitglieder, Einladungen und Beitrittsanfragen. Außerdem können sie ausschließlich das Forum dieser Arbeitsgruppe moderieren.', 'afspaces' ); ?></p>
						<p class="afspaces-inline-hint"><a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $space_id ) ) ); ?>"><?php echo esc_html__( 'Verantwortliche in der Mitgliederverwaltung bearbeiten', 'afspaces' ); ?></a></p>
					</section>

					<button type="submit" class="afspaces-button afspaces-settings-submit"><?php echo esc_html__( 'Änderungen speichern', 'afspaces' ); ?></button>
				</form>

				<?php echo $this->render_owner_transfer( $space, $actor, $responsibles ); ?>
				<?php echo $this->render_management( $space, $actor ); ?>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		/**
		 * Gibt die fachliche Beitrittsauswahl für bestehende Daten zurück.
		 *
		 * @param WorkingGroupMeta $meta Metadaten.
		 * @return string
		 */
		private static function join_mode( WorkingGroupMeta $meta ): string {
			if ( WorkingGroupMeta::JOIN_POLICY_REQUEST === $meta->join_policy && $meta->join_requests_enabled ) {
				return WorkingGroupMeta::JOIN_POLICY_REQUEST;
			}

			return WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY === $meta->join_policy
				? WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY
				: WorkingGroupMeta::JOIN_POLICY_CLOSED;
		}

		/**
		 * Gibt die fachliche Erläuterung eines Beitrittsmodus zurück.
		 *
		 * @param string $join_policy Interner Beitrittswert.
		 * @return string
		 */
		private static function join_policy_description( string $join_policy ): string {
			switch ( $join_policy ) {
				case WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY:
					return __( 'Neue Mitglieder können nur von berechtigten Personen eingeladen werden.', 'afspaces' );
				case WorkingGroupMeta::JOIN_POLICY_CLOSED:
					return __( 'Die Arbeitsgruppe nimmt derzeit keine weiteren Mitglieder auf.', 'afspaces' );
				case WorkingGroupMeta::JOIN_POLICY_REQUEST:
				default:
					return __( 'Angemeldete Personen können eine Mitgliedschaft anfragen. Zusätzlich können berechtigte Personen Einladungslinks erstellen.', 'afspaces' );
			}
		}

		/**
		 * Rendert die separate, berechtigungsgeschützte Owner-Übertragung.
		 *
		 * @param Space                          $space         Space.
		 * @param int                            $actor         Akteur.
		 * @param array<int,array<string,mixed>> $responsibles Verantwortliche.
		 * @return string
		 */
		private function render_owner_transfer( Space $space, int $actor, array $responsibles ): string {
			$is_owner = user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) || $space->owner_user_id === $actor;
			$candidates = array_values(
				array_filter(
					$responsibles,
					static fn( array $responsible ): bool => (int) $responsible['user_id'] !== $space->owner_user_id
				)
			);

			if ( ! $is_owner || empty( $candidates ) ) {
				return '';
			}

			ob_start();
			?>
			<section class="afspaces-settings-section afspaces-owner-transfer" aria-labelledby="afspaces-owner-transfer-heading">
				<h3 id="afspaces-owner-transfer-heading"><?php echo esc_html__( 'Hauptverantwortung', 'afspaces' ); ?></h3>
				<p><?php echo esc_html__( 'Aktuell verantwortlich:', 'afspaces' ); ?> <strong><?php echo esc_html( $this->identity->get_display_name( $space->owner_user_id ) ); ?></strong></p>
				<form method="post" class="afspaces-owner-transfer-form">
					<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
					<input type="hidden" name="afspaces_action" value="transfer_space_owner" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
					<label for="afspaces-transfer-owner"><?php echo esc_html__( 'Verantwortung übertragen an', 'afspaces' ); ?></label>
					<select id="afspaces-transfer-owner" name="new_owner_id">
						<?php foreach ( $candidates as $responsible ) : ?>
							<option value="<?php echo esc_attr( (string) $responsible['user_id'] ); ?>"><?php echo esc_html( $this->identity->get_display_name( (int) $responsible['user_id'] ) ?: (string) $responsible['display_name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="afspaces-button" data-afspaces-confirm="<?php echo esc_attr__( 'Möchtest du die Verantwortung wirklich übertragen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Verantwortung übertragen', 'afspaces' ); ?></button>
				</form>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert Status, Lifecycle-Aktionen und den Gefahrenbereich.
		 *
		 * @param Space $space Space.
		 * @param int   $actor Akteur.
		 * @return string
		 */
		private function render_management( Space $space, int $actor ): string {
			$is_owner = user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) || $space->owner_user_id === $actor;

			ob_start();
			?>
			<section class="afspaces-settings-section afspaces-space-management" aria-labelledby="afspaces-space-management-heading">
				<h3 id="afspaces-space-management-heading"><?php echo esc_html__( 'Verwaltung', 'afspaces' ); ?></h3>
				<p class="description"><strong><?php echo esc_html__( 'Status:', 'afspaces' ); ?></strong> <?php echo esc_html( self::status_label( $space->status ) ); ?></p>

				<?php if ( SpaceLifecycle::STATUS_REJECTED === $space->status && '' !== $space->rejection_reason ) : ?>
					<p class="afspaces-message afspaces-message-error" role="alert"><?php echo esc_html( sprintf( __( 'Ablehnungsgrund: %s', 'afspaces' ), $space->rejection_reason ) ); ?></p>
				<?php endif; ?>

				<div class="afspaces-management-lifecycle">
					<?php if ( SpaceLifecycle::STATUS_ACTIVE === $space->status ) : ?>
						<form method="post">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="archive_space" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
							<button type="submit" class="afspaces-button" data-afspaces-confirm="<?php echo esc_attr__( 'Arbeitsgruppe wirklich archivieren?', 'afspaces' ); ?>"><?php echo esc_html__( 'Arbeitsgruppe archivieren', 'afspaces' ); ?></button>
						</form>
					<?php elseif ( SpaceLifecycle::STATUS_ARCHIVED === $space->status ) : ?>
						<form method="post">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="reactivate_space" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
							<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Arbeitsgruppe reaktivieren', 'afspaces' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( $is_owner && SpaceLifecycle::STATUS_DELETED !== $space->status ) : ?>
				<section class="afspaces-settings-section afspaces-danger-zone" aria-labelledby="afspaces-danger-heading">
					<h3 id="afspaces-danger-heading"><?php echo esc_html__( 'Gefahrenbereich', 'afspaces' ); ?></h3>
					<p class="afspaces-danger-hint"><?php echo esc_html__( 'Das Löschen entfernt den Forenbereich der Arbeitsgruppe endgültig. Diese Aktion kann nicht rückgängig gemacht werden.', 'afspaces' ); ?></p>
					<form method="post" class="afspaces-delete-space">
						<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
						<input type="hidden" name="afspaces_action" value="delete_space" />
						<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
						<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Diese Arbeitsgruppe wirklich unwiderruflich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Arbeitsgruppe löschen', 'afspaces' ); ?></button>
					</form>
				</section>
			<?php endif; ?>
			<?php
			return (string) ob_get_clean();
		}

		/** @param string $status Status. @return string */
		private static function status_label( string $status ): string {
			return StatusLabels::space( $status );
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
