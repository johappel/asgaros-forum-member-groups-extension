<?php
/**
 * Oeffentliche Detailansicht einer Arbeitsgruppe.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\WorkingGroupMeta;

if ( ! class_exists( 'AFSpaces\\Interface\\WorkingGroupView' ) ) {

	/**
	 * Zeigt eine einzelne Arbeitsgruppe mit Status und Beitrittsoptionen.
	 */
	class WorkingGroupView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private InvitationService $invitations;
		private JoinRequestService $join_requests;
		private WorkingGroupService $working_groups;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, InvitationService $invitations, JoinRequestService $join_requests, WorkingGroupService $working_groups ) {
			$this->spaces = $spaces;
			$this->asgaros = $asgaros;
			$this->invitations = $invitations;
			$this->join_requests = $join_requests;
			$this->working_groups = $working_groups;
		}

		public function render( int $space_id ): string {
			if ( ! is_user_logged_in() ) {
				return $this->notice( __( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return $this->notice( __( 'Diese Arbeitsgruppe wurde nicht gefunden.', 'afspaces' ) );
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			if ( empty( $forum ) ) {
				return $this->notice( __( 'Das zugehörige Forum ist nicht mehr verfügbar.', 'afspaces' ) );
			}

			$meta = $this->working_groups->get_metadata( $space_id );
			$is_manager = $this->spaces->is_manager( $space_id, $actor ) || user_can( $actor, Capabilities::MANAGE_ALL_SPACES );
			$is_member = $this->asgaros->is_user_in_group( $actor, $space->primary_group_id );
			if ( ! $this->working_groups->can_view_group( $meta, $is_member, $is_manager ) ) {
				return $this->notice( __( 'Diese Arbeitsgruppe ist für dich nicht sichtbar.', 'afspaces' ) );
			}

			$requests = $this->join_requests->list_my_requests( $actor );
			$invitations = $this->invitations->list_my_invitations( $actor );
			$request = $this->latest_request_for_space( $requests, $space_id );
			$invitation = $this->latest_invitation_for_space( $invitations, $space_id );
			$has_pending_request = null !== $request && 'pending' === $request->status;
			$has_open_invitation = null !== $invitation && 'pending' === $invitation->effective_status();
			$can_request = $this->working_groups->can_request_join( $meta, $is_member, $is_manager, $has_pending_request, $has_open_invitation );
			$responsibles = $this->working_groups->list_responsibles( $space_id );
			$topics = $this->working_groups->topic_names( $meta );

			$members_result = $this->asgaros->list_group_members( $space->primary_group_id, array( 'per_page' => 100 ) );
			$members = is_array( $members_result['members'] ?? null ) ? $members_result['members'] : array();
			$member_count = (int) ( $members_result['total'] ?? count( $members ) );

			ob_start();
			?>
			<section class="afspaces-working-group-view" aria-labelledby="afspaces-working-group-view-heading">
				<h2 id="afspaces-working-group-view-heading"><?php echo esc_html( (string) $forum['name'] ); ?></h2>
				<?php echo $this->render_message(); ?>
				<div class="afspaces-working-group-card" style="--afspaces-accent: <?php echo esc_attr( $meta->accent_color ); ?>;">
					<div class="afspaces-working-group-card-header">
						<span class="afspaces-working-group-icon" aria-hidden="true"><i class="<?php echo esc_attr( WorkingGroupService::icon_class( $meta->icon ) ); ?>"></i></span>
						<div>
							<p><strong><?php echo esc_html__( 'Dein Status:', 'afspaces' ); ?></strong> <span class="afspaces-tag"><?php echo esc_html( $this->status_label( $is_manager, $is_member, $request, $invitation ) ); ?></span></p>
							<p><strong><?php echo esc_html__( 'Beitritt:', 'afspaces' ); ?></strong> <?php echo esc_html( $this->join_policy_label( $meta ) ); ?></p>
						</div>
					</div>

					<?php if ( '' !== $meta->description ) : ?>
						<p><?php echo esc_html( $meta->description ); ?></p>
					<?php elseif ( ! empty( $forum['description'] ) ) : ?>
						<p><?php echo esc_html( (string) $forum['description'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $topics ) ) : ?>
						<p><strong><?php echo esc_html__( 'Themen:', 'afspaces' ); ?></strong> <?php echo esc_html( implode( ', ', $topics ) ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $responsibles ) ) : ?>
						<div>
							<strong><?php echo esc_html__( 'Arbeitsgruppenverantwortliche:', 'afspaces' ); ?></strong>
							<ul class="afspaces-responsibles-list">
								<?php foreach ( $responsibles as $responsible ) : ?>
									<li><a href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => $responsible['user_id'] ) ) ); ?>"><?php echo esc_html( $responsible['display_name'] ); ?></a> <span class="afspaces-tag"><?php echo esc_html( $responsible['role_label'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $meta->contact_text ) : ?>
						<p><strong><?php echo esc_html__( 'Kontakt:', 'afspaces' ); ?></strong> <?php echo esc_html( $meta->contact_text ); ?></p>
					<?php endif; ?>

					<div class="afspaces-working-group-members">
						<strong><?php echo esc_html( sprintf( __( 'Mitglieder (%s):', 'afspaces' ), WorkingGroupTerminology::membership_count( $member_count ) ) ); ?></strong>
						<?php if ( empty( $members ) ) : ?>
							<p><?php echo esc_html__( 'Für diese Arbeitsgruppe sind derzeit keine Mitglieder sichtbar.', 'afspaces' ); ?></p>
						<?php else : ?>
							<ul class="afspaces-members-list afspaces-members-grid">
								<?php foreach ( $members as $member ) : ?>
									<?php $member_profile_url = SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE, array( 'user_id' => (int) $member['user_id'] ) ); ?>
									<li class="afspaces-member-card">
										<a class="afspaces-member-link" href="<?php echo esc_url( $member_profile_url ); ?>">
											<span class="afspaces-member-avatar" aria-hidden="true"><?php echo get_avatar( (int) $member['user_id'], 40 ); ?></span>
											<span class="afspaces-member-name"><?php echo esc_html( (string) $member['display_name'] ); ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<?php if ( $can_request ) : ?>
						<div class="afspaces-join-panel">
							<h3 class="afspaces-join-heading"><?php echo esc_html__( 'Dieser Arbeitsgruppe beitreten', 'afspaces' ); ?></h3>
							<p class="afspaces-join-intro"><?php echo esc_html__( 'Sende eine Beitrittsanfrage an die Arbeitsgruppenverantwortlichen. Du wirst benachrichtigt, sobald über deine Anfrage entschieden wurde.', 'afspaces' ); ?></p>
							<form method="post" class="afspaces-join-form">
								<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
								<input type="hidden" name="afspaces_action" value="create_join_request" />
								<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
								<label class="afspaces-join-label" for="afspaces-join-message-<?php echo esc_attr( (string) $space_id ); ?>"><?php echo esc_html__( 'Nachricht an die Verantwortlichen (optional)', 'afspaces' ); ?></label>
								<textarea id="afspaces-join-message-<?php echo esc_attr( (string) $space_id ); ?>" name="request_message" maxlength="500" rows="2" class="afspaces-join-textarea" placeholder="<?php echo esc_attr__( 'z. B. warum du beitreten möchtest', 'afspaces' ); ?>"></textarea>
								<button type="submit" class="afspaces-button afspaces-join-submit"><?php echo esc_html__( 'Beitritt anfragen', 'afspaces' ); ?></button>
							</form>
						</div>
					<?php endif; ?>

					<div class="afspaces-space-actions" role="group" aria-label="<?php echo esc_attr__( 'Arbeitsgruppenaktionen', 'afspaces' ); ?>">
						<?php if ( ! $can_request ) : ?>
							<p class="afspaces-inline-hint"><?php echo esc_html( $this->availability_hint( $is_manager, $is_member, $request, $invitation, $meta ) ); ?></p>
						<?php endif; ?>

						<?php if ( $is_manager ) : ?>
							<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_SETTINGS, array( 'space_id' => $space_id ) ) ); ?>"><?php echo esc_html__( 'Details bearbeiten', 'afspaces' ); ?></a>
							<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $space_id ) ) ); ?>"><?php echo esc_html__( 'Mitglieder verwalten', 'afspaces' ); ?></a>
						<?php endif; ?>
					</div>

					<p class="description"><?php echo esc_html__( 'Arbeitsgruppenverantwortung betrifft Mitgliedschaften, Einladungen und Beitrittsanfragen. Die Moderation von Forenbeiträgen bleibt in Asgaros getrennt.', 'afspaces' ); ?></p>
				</div>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		private function status_label( bool $is_manager, bool $is_member, $request, $invitation ): string {
			if ( $is_manager ) {
				return __( 'Arbeitsgruppenverantwortlich', 'afspaces' );
			}

			if ( $is_member ) {
				return __( 'Mitglied', 'afspaces' );
			}

			if ( null !== $invitation && 'pending' === $invitation->effective_status() ) {
				return __( 'Eingeladen', 'afspaces' );
			}

			if ( null !== $request && 'pending' === $request->status ) {
				return __( 'Anfrage offen', 'afspaces' );
			}

			if ( null !== $request && 'rejected' === $request->status ) {
				return __( 'Anfrage abgelehnt', 'afspaces' );
			}

			return __( 'Keine Zugehörigkeit', 'afspaces' );
		}

		private function join_policy_label( WorkingGroupMeta $meta ): string {
			if ( ! $meta->join_requests_enabled ) {
				return __( 'Derzeit ohne Beitrittsanfragen', 'afspaces' );
			}

			switch ( $meta->join_policy ) {
				case WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY:
					return __( 'Nur auf Einladung', 'afspaces' );
				case WorkingGroupMeta::JOIN_POLICY_CLOSED:
					return __( 'Geschlossen', 'afspaces' );
				default:
					return __( 'Beitritt per Anfrage möglich', 'afspaces' );
			}
		}

		private function availability_hint( bool $is_manager, bool $is_member, $request, $invitation, WorkingGroupMeta $meta ): string {
			if ( $is_manager ) {
				return __( 'Du verwaltest diese Arbeitsgruppe bereits.', 'afspaces' );
			}

			if ( $is_member ) {
				return __( 'Du bist bereits Mitglied dieser Arbeitsgruppe.', 'afspaces' );
			}

			if ( null !== $invitation && 'pending' === $invitation->effective_status() ) {
				return __( 'Für dich liegt bereits eine Einladung vor.', 'afspaces' );
			}

			if ( null !== $request && 'pending' === $request->status ) {
				return __( 'Deine Beitrittsanfrage ist bereits offen.', 'afspaces' );
			}

			if ( WorkingGroupMeta::JOIN_POLICY_INVITE_ONLY === $meta->join_policy ) {
				return __( 'Diese Arbeitsgruppe ist sichtbar, aber nur per Einladung beitretbar.', 'afspaces' );
			}

			if ( WorkingGroupMeta::JOIN_POLICY_CLOSED === $meta->join_policy || ! $meta->join_requests_enabled ) {
				return __( 'Diese Arbeitsgruppe ist sichtbar, aber aktuell nicht beitretbar.', 'afspaces' );
			}

			return __( 'Für diese Arbeitsgruppe ist aktuell keine weitere Aktion verfügbar.', 'afspaces' );
		}

		private function latest_request_for_space( array $requests, int $space_id ) {
			foreach ( $requests as $request ) {
				if ( $space_id === (int) $request->space_id ) {
					return $request;
				}
			}

			return null;
		}

		private function latest_invitation_for_space( array $invitations, int $space_id ) {
			foreach ( $invitations as $invitation ) {
				if ( $space_id === (int) $invitation->space_id ) {
					return $invitation;
				}
			}

			return null;
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