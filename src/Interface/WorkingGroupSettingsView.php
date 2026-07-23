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
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupSettingsView' ) ) {

	/**
	 * Rendert die bearbeitbaren Metadaten einer Arbeitsgruppe.
	 */
	class WorkingGroupSettingsView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private WorkingGroupService $working_groups;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, WorkingGroupService $working_groups ) {
			$this->spaces = $spaces;
			$this->asgaros = $asgaros;
			$this->working_groups = $working_groups;
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
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier pflegst du Beschreibung, Sichtbarkeit und Kontaktinformationen dieser Arbeitsgruppe.', 'afspaces' ); ?></p>

				<section class="afspaces-section-card content-container" aria-labelledby="afspaces-working-group-contact-heading">
					<div id="afspaces-working-group-contact-heading" class="title-element afspaces-section-title"><?php echo esc_html__( 'Arbeitsgruppenverantwortliche', 'afspaces' ); ?></div>
					<?php if ( empty( $responsibles ) ) : ?>
						<p><?php echo esc_html__( 'Für diese Arbeitsgruppe sind derzeit keine Verantwortlichen sichtbar hinterlegt.', 'afspaces' ); ?></p>
					<?php else : ?>
						<ul class="afspaces-responsibles-list">
							<?php foreach ( $responsibles as $responsible ) : ?>
								<li>
									<a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => $responsible['user_id'] ) ) ); ?>"><?php echo esc_html( $responsible['display_name'] ); ?></a>
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

					<label for="afspaces-accent-color"><?php echo esc_html__( 'Farbe', 'afspaces' ); ?></label>
					<input type="color" id="afspaces-accent-color" name="accent_color" value="<?php echo esc_attr( $meta->accent_color ); ?>" />

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
					<p><?php echo esc_html__( 'Forenmoderation in Asgaros, etwa das Moderieren von Beiträgen und Themen, bleibt davon getrennt und wird nicht automatisch aus dieser Rolle vergeben.', 'afspaces' ); ?></p>
				</section>
			</section>
			<?php

			return (string) ob_get_clean();
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