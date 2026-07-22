<?php
/**
 * Frontend-Controller für Dashboard und Mitgliederansicht.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InviteLinkService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\MemberService;
use AFSpaces\Application\SpaceRegistrationService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\FrontendController' ) ) {

	/**
	 * Rendert das Dashboard und verarbeitet Formular-Requests.
	 */
	class FrontendController {

		/**
		 * @var SpaceRepository
		 */
		private SpaceRepository $spaces;

		/**
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * @var MemberService
		 */
		private MemberService $members;

		/**
		 * @var InvitationService
		 */
		private InvitationService $invitations;

		/**
		 * @var InviteLinkService
		 */
		private InviteLinkService $invite_links;

		/**
		 * @var JoinRequestService
		 */
		private JoinRequestService $join_requests;

		/**
		 * @var SpaceRegistrationService
		 */
		private SpaceRegistrationService $space_registration;

		/**
		 * @var string
		 */
		private string $nonce_action = 'afspaces_member_action';

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository         $spaces  Space-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param MemberService           $members Mitglieder-Service.
		 * @param InvitationService       $invitations Einladungs-Service.
		 */
		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			MemberService $members,
			InvitationService $invitations,
			JoinRequestService $join_requests,
			InviteLinkService $invite_links,
			SpaceRegistrationService $space_registration
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->members = $members;
			$this->invitations = $invitations;
			$this->join_requests = $join_requests;
			$this->invite_links = $invite_links;
			$this->space_registration = $space_registration;
		}

		/**
		 * Initialisiert Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_shortcode( 'afspaces_dashboard', array( $this, 'render_dashboard' ) );
			add_action( 'init', array( $this, 'handle_actions' ) );
			add_action( 'wp_ajax_afspaces_action', array( $this, 'handle_actions' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Bindet die Frontend-Assets ein.
		 *
		 * @return void
		 */
		public function enqueue_assets(): void {
			$content = get_post_field( 'post_content', get_the_ID() );
			if ( ! has_shortcode( $content, 'afspaces' )
				&& ! has_shortcode( $content, 'afspaces_dashboard' )
				&& ! has_shortcode( $content, 'afspaces_members' )
				&& ! has_shortcode( $content, 'afspaces_invitations' )
				&& ! has_shortcode( $content, 'afspaces_my_invitations' ) ) {
				return;
			}
			wp_enqueue_style(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.css',
				array(),
				AFSPACES_VERSION
			);

			wp_enqueue_script(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.js',
				array(),
				AFSPACES_VERSION,
				true
			);

			wp_localize_script(
				'afspaces-frontend',
				'afspacesFrontend',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		/**
		 * Verarbeitet Formular-Requests (serverseitig, mit Nonce).
		 *
		 * @return void
		 */
		public function handle_actions(): void {
			if ( ! isset( $_POST['afspaces_action'] ) ) {
				return;
			}

			$is_ajax = wp_doing_ajax() || ( isset( $_POST['afspaces_ajax'] ) && '1' === (string) $_POST['afspaces_ajax'] );

			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $this->nonce_action ) ) {
				wp_die( esc_html__( 'Ungültige Anfrage (Nonce).', 'afspaces' ) );
			}

			$space_id = isset( $_POST['space_id'] ) ? (int) $_POST['space_id'] : 0;
			$actor    = get_current_user_id();
			$action   = sanitize_text_field( wp_unslash( $_POST['afspaces_action'] ) );
			$invite_link_token = '';

			try {
				if ( 'add_member' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->add_member( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Person wurde hinzugefügt.', 'afspaces' ) );
				} elseif ( 'remove_member' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->remove_member( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Person wurde entfernt.', 'afspaces' ) );
				} elseif ( 'assign_manager' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->assign_manager( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Person ist jetzt Raumverantwortliche.', 'afspaces' ) );
				} elseif ( 'revoke_manager' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->revoke_manager( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Raumverantwortung wurde entzogen.', 'afspaces' ) );
				} elseif ( 'create_invitation' === $action ) {
					$target = isset( $_POST['invitee_user_id'] ) ? (int) $_POST['invitee_user_id'] : 0;
					$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
					$days = isset( $_POST['expires_in_days'] ) ? (int) $_POST['expires_in_days'] : 7;
					$this->invitations->create_invitation( $space_id, $actor, $target, $message, $days );
					$this->set_message( 'success', __( 'Einladung wurde erstellt und versendet.', 'afspaces' ) );
				} elseif ( 'revoke_invitation' === $action ) {
					$invitation_id = isset( $_POST['invitation_id'] ) ? (int) $_POST['invitation_id'] : 0;
					$this->invitations->revoke_invitation( $invitation_id, $actor );
					$this->set_message( 'success', __( 'Einladung wurde widerrufen.', 'afspaces' ) );
				} elseif ( 'resend_invitation' === $action ) {
					$invitation_id = isset( $_POST['invitation_id'] ) ? (int) $_POST['invitation_id'] : 0;
					$this->invitations->resend_invitation( $invitation_id, $actor );
					$this->set_message( 'success', __( 'Einladung wurde erneut versendet.', 'afspaces' ) );
				} elseif ( 'accept_invitation' === $action ) {
					$token = isset( $_POST['invitation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invitation_token'] ) ) : '';
					$accepted = $this->invitations->accept_invitation_by_token( $token, $actor );
					$this->set_message( 'success', __( 'Einladung angenommen. Mitgliedschaft wurde aktiviert.', 'afspaces' ) );

					$forum_url = home_url( '/forum/' );
					$space = $this->spaces->get_space( $accepted->space_id );
					if ( $space ) {
						$forum_url = (string) apply_filters( 'afspaces_forum_url_after_accept', $forum_url, $space, $accepted );
					}

					wp_safe_redirect( $forum_url );
					exit;
				} elseif ( 'decline_invitation' === $action ) {
					$token = isset( $_POST['invitation_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invitation_token'] ) ) : '';
					$this->invitations->decline_invitation_by_token( $token, $actor );
					$this->set_message( 'success', __( 'Einladung wurde abgelehnt.', 'afspaces' ) );
				} elseif ( 'create_invite_link' === $action ) {
					$result = $this->invite_links->create_link(
						$space_id,
						$actor,
						array(
							'approval_mode'      => isset( $_POST['approval_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_mode'] ) ) : '',
							'max_uses'           => isset( $_POST['max_uses'] ) ? (int) $_POST['max_uses'] : 1,
							'expires_in_days'    => isset( $_POST['expires_in_days'] ) ? (int) $_POST['expires_in_days'] : 7,
							'allow_registration' => ! empty( $_POST['allow_registration'] ),
						)
					);
					$this->set_created_invite_link( $result['url'] );
					$this->set_message( 'success', __( 'Einladungslink wurde erstellt.', 'afspaces' ) );
				} elseif ( 'revoke_invite_link' === $action ) {
					$link_id = isset( $_POST['invite_link_id'] ) ? (int) $_POST['invite_link_id'] : 0;
					$this->invite_links->revoke_link( $link_id, $actor );
					$this->set_message( 'success', __( 'Einladungslink wurde widerrufen.', 'afspaces' ) );
				} elseif ( 'shorten_invite_link' === $action ) {
					$link_id = isset( $_POST['invite_link_id'] ) ? (int) $_POST['invite_link_id'] : 0;
					$expires_at = isset( $_POST['shorten_expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['shorten_expires_at'] ) ) : '';
					$this->invite_links->shorten_expiry( $link_id, $actor, $expires_at );
					$this->set_message( 'success', __( 'Das Ablaufdatum des Einladungslinks wurde verkürzt.', 'afspaces' ) );
				} elseif ( 'use_invite_link' === $action ) {
					$invite_link_token = isset( $_POST['invite_link_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invite_link_token'] ) ) : '';
					$result = $this->invite_links->use_link( $invite_link_token, $actor );

					if ( 'joined' === $result['result'] ) {
						$this->set_message( 'success', __( 'Du bist dem Raum beigetreten.', 'afspaces' ) );
						wp_safe_redirect( $result['forum_url'] );
						exit;
					}

					if ( 'already_member' === $result['result'] ) {
						$this->set_message( 'success', __( 'Du bist bereits Mitglied dieses Raums.', 'afspaces' ) );
						wp_safe_redirect( $result['forum_url'] );
						exit;
					}

					$this->set_message( 'success', __( 'Deine Beitrittsanfrage wurde gespeichert.', 'afspaces' ) );
				} elseif ( 'request_invite_link_registration' === $action ) {
					$invite_link_token = isset( $_POST['invite_link_token'] ) ? sanitize_text_field( wp_unslash( $_POST['invite_link_token'] ) ) : '';
					$consent = ! empty( $_POST['privacy_consent'] );

					if ( ! $consent ) {
						throw new DomainException( __( 'Bitte bestätige die Datenschutzinformationen, bevor du fortfährst.', 'afspaces' ) );
					}

					$preview = $this->invite_links->preview_link( $invite_link_token, 0 );
					if ( ! $preview['can_register'] || '' === (string) $preview['registration_url'] ) {
						throw new DomainException( __( 'Die Registrierung über diesen Einladungslink ist derzeit nicht verfügbar.', 'afspaces' ) );
					}

					wp_safe_redirect( (string) $preview['registration_url'] );
					exit;
				} elseif ( 'create_join_request' === $action ) {
					$message = isset( $_POST['request_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['request_message'] ) ) : '';
					$this->join_requests->create_request( $space_id, $actor, $message );
					$this->set_message( 'success', __( 'Deine Beitrittsanfrage wurde gespeichert.', 'afspaces' ) );
				} elseif ( 'approve_join_request' === $action ) {
					$request_id = isset( $_POST['join_request_id'] ) ? (int) $_POST['join_request_id'] : 0;
					$decision_message = isset( $_POST['decision_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['decision_message'] ) ) : '';
					$this->join_requests->approve_request( $request_id, $actor, $decision_message );
					$this->set_message( 'success', __( 'Die Beitrittsanfrage wurde genehmigt.', 'afspaces' ) );
				} elseif ( 'reject_join_request' === $action ) {
					$request_id = isset( $_POST['join_request_id'] ) ? (int) $_POST['join_request_id'] : 0;
					$decision_message = isset( $_POST['decision_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['decision_message'] ) ) : '';
					$this->join_requests->reject_request( $request_id, $actor, $decision_message );
					$this->set_message( 'success', __( 'Die Beitrittsanfrage wurde abgelehnt.', 'afspaces' ) );
				} elseif ( 'register_space' === $action ) {
					$forum_id = isset( $_POST['forum_id'] ) ? (int) $_POST['forum_id'] : 0;
					$space = $this->space_registration->register_existing_forum( $forum_id, $actor );
					$forum = $this->asgaros->get_forum( $space->forum_id );
					$this->set_message(
						'success',
						sprintf(
							/* translators: %s: Forumsname */
							__( 'Das Forum "%s" wurde als Raum registriert.', 'afspaces' ),
							(string) ( $forum['name'] ?? (string) $space->forum_id )
						)
					);
				}
			} catch ( DomainException $e ) {
				$this->set_message( 'error', $e->getMessage() );
				if ( $is_ajax ) {
					wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );
				}
			}

			if ( $is_ajax ) {
				wp_send_json_success( $this->peek_message() );
			}

			// Redirect zurück zur sauberen URL (Post/Redirect/Get).
			$ref = wp_get_referer() ?: home_url();
			if ( in_array( $action, array( 'create_invitation', 'revoke_invitation', 'resend_invitation', 'create_invite_link', 'revoke_invite_link', 'shorten_invite_link' ), true ) ) {
				wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_INVITATIONS, array( 'space_id' => $space_id ) ) );
				exit;
			}

			if ( in_array( $action, array( 'approve_join_request', 'reject_join_request' ), true ) ) {
				wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_JOIN_REQUESTS, array( 'space_id' => $space_id ) ) );
				exit;
			}

			if ( 'register_space' === $action ) {
				wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ) );
				exit;
			}

			if ( in_array( $action, array( 'accept_invitation', 'decline_invitation', 'use_invite_link', 'request_invite_link_registration' ), true ) ) {
				$args = array();
				if ( in_array( $action, array( 'use_invite_link', 'request_invite_link_registration' ), true ) && '' !== $invite_link_token ) {
					$args['invite_link'] = $invite_link_token;
				}
				wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_MY_INVITATIONS, $args ) );
				exit;
			}

			if ( 'create_join_request' === $action ) {
				wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_DISCOVER ) );
				exit;
			}

			wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $space_id ) ) );
			exit;
		}

		/**
		 * Liefert die aktuelle Nachricht aus der Session (ohne zu löschen).
		 *
		 * @return array<string,string>
		 */
		private function peek_message(): array {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) || ! is_array( $_SESSION['afspaces_message'] ) ) {
				return array(
					'type'    => 'success',
					'message' => '',
				);
			}

			$type = isset( $_SESSION['afspaces_message']['type'] ) ? (string) $_SESSION['afspaces_message']['type'] : 'success';
			$message = isset( $_SESSION['afspaces_message']['message'] ) ? (string) $_SESSION['afspaces_message']['message'] : '';

			return array(
				'type'    => $type,
				'message' => $message,
			);
		}

		/**
		 * Speichert eine Statusmeldung in der Session.
		 *
		 * @param string $type    'success' | 'error'.
		 * @param string $message Meldung.
		 * @return void
		 */
		private function set_message( string $type, string $message ): void {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}
			$_SESSION['afspaces_message'] = array(
				'type'    => $type,
				'message' => $message,
			);
		}

		/**
		 * @param string $url Vollständiger Invite-Link.
		 * @return void
		 */
		private function set_created_invite_link( string $url ): void {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			$_SESSION['afspaces_created_invite_link'] = $url;
		}

		/**
		 * Rendert das Dashboard (Shortcode).
		 *
		 * @return string
		 */
		public function render_dashboard(): string {
			if ( ! is_user_logged_in() ) {
				return $this->notice( __( 'Bitte melde dich an, um deine Räume zu verwalten.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$spaces = $this->spaces->list_spaces();
			$registrable_forums = $this->space_registration->list_registrable_forums( $actor );

			$manageable = array();
			$member_spaces = array();
			foreach ( $spaces as $space ) {
				// Skip orphaned spaces (forum_id points to non-existent forum).
				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}

				if ( $this->can_manage_space( $space->id, $actor ) ) {
					$manageable[] = $space;
					continue;
				}

				if ( $this->is_member_of_space( $space, $actor ) ) {
					$member_spaces[] = $space;
				}
			}

			ob_start();
			?>
			<section class="afspaces-dashboard" aria-labelledby="afspaces-dashboard-heading">
				<h2 id="afspaces-dashboard-heading"><?php echo esc_html__( 'Meine Räume', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>

				<?php if ( empty( $manageable ) && empty( $member_spaces ) ) : ?>
					<p><?php echo esc_html__( 'Dir sind noch keine Räume zugeordnet.', 'afspaces' ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $manageable ) ) : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $manageable as $space ) : ?>
							<?php
							$forum = $this->asgaros->get_forum( $space->forum_id );
							// $forum is guaranteed to be non-empty due to filtering in render_dashboard().
							$group_ids = $this->asgaros->get_forum_group_ids( $space->forum_id );
							$member_count = 0;
							if ( ! empty( $group_ids ) ) {
								$members = $this->asgaros->list_group_members( (int) $group_ids[0], array( 'per_page' => 1 ) );
								$member_count = (int) ( $members['total'] ?? 0 );
							}
							// Link zur Mitgliederseite (nicht zum Dashboard).
							$manage_url = SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $space->id ) );
							$invite_url = SpacesUrls::hub_url( SpacesUrls::VIEW_INVITATIONS, array( 'space_id' => $space->id ) );
							?>
							<li class="afspaces-space-item">
								<h3><?php echo esc_html( $forum['name'] ); ?></h3>
								<p><?php echo esc_html( sprintf( __( '%d Mitglieder', 'afspaces' ), $member_count ) ); ?></p>
								<div class="afspaces-space-actions" role="group" aria-label="<?php echo esc_attr__( 'Raumaktionen', 'afspaces' ); ?>">
									<a class="afspaces-button" href="<?php echo esc_url( $manage_url ); ?>">
										<?php echo esc_html__( 'Mitglieder verwalten', 'afspaces' ); ?>
									</a>
									<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( $invite_url ); ?>">
										<?php echo esc_html__( 'Einladungen und Invite-Links', 'afspaces' ); ?>
									</a>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( ! empty( $member_spaces ) ) : ?>
					<section class="afspaces-member-spaces" aria-labelledby="afspaces-member-spaces-heading">
						<h3 id="afspaces-member-spaces-heading"><?php echo esc_html__( 'Meine Mitgliedschaften', 'afspaces' ); ?></h3>
						<p><?php echo esc_html__( 'Du bist in diesen Räumen als Mitglied eingetragen.', 'afspaces' ); ?></p>
						<ul class="afspaces-space-list">
							<?php foreach ( $member_spaces as $space ) : ?>
								<?php
								$forum = $this->asgaros->get_forum( $space->forum_id );
								$forum_url = (string) apply_filters( 'afspaces_space_forum_url', $this->space_forum_url( $forum ), $space, $forum, $actor );
								?>
								<li class="afspaces-space-item">
									<h3><?php echo esc_html( $forum['name'] ); ?></h3>
									<p><?php echo esc_html__( 'Du kannst diesen Raum direkt im Forum öffnen.', 'afspaces' ); ?></p>
									<div class="afspaces-space-actions" role="group" aria-label="<?php echo esc_attr__( 'Raumaktionen', 'afspaces' ); ?>">
										<a class="afspaces-button" href="<?php echo esc_url( $forum_url ); ?>">
											<?php echo esc_html__( 'Forum öffnen', 'afspaces' ); ?>
										</a>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( $this->can_register_spaces( $actor ) ) : ?>
					<section class="afspaces-space-registration" aria-labelledby="afspaces-space-registration-heading">
						<h3 id="afspaces-space-registration-heading"><?php echo esc_html__( 'Bestehendes Forum als Raum registrieren', 'afspaces' ); ?></h3>
						<p><?php echo esc_html__( 'Ein Raum wird aus einem bestehenden Asgaros-Forum plus seiner zugeordneten Asgaros-Benutzergruppe gebildet.', 'afspaces' ); ?></p>

						<?php if ( empty( $registrable_forums ) ) : ?>
							<p><?php echo esc_html__( 'Es wurden keine verwaltbaren Foren gefunden.', 'afspaces' ); ?></p>
						<?php else : ?>
							<table class="afspaces-member-table afspaces-space-registration-table">
								<thead>
									<tr>
										<th><?php echo esc_html__( 'Forum', 'afspaces' ); ?></th>
										<th><?php echo esc_html__( 'Zugriffsgruppe', 'afspaces' ); ?></th>
										<th><?php echo esc_html__( 'Status', 'afspaces' ); ?></th>
										<th><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $registrable_forums as $forum ) : ?>
										<tr>
											<td><?php echo esc_html( $forum['name'] ); ?></td>
											<td>
												<?php if ( empty( $forum['group_ids'] ) ) : ?>
													<?php echo esc_html__( 'Keine Gruppe an der Kategorie hinterlegt', 'afspaces' ); ?>
												<?php else : ?>
													<?php echo esc_html( implode( ', ', array_map( 'strval', $forum['group_ids'] ) ) ); ?>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $forum['is_registered'] ) : ?>
													<?php echo esc_html__( 'Bereits registriert', 'afspaces' ); ?>
												<?php elseif ( ! $forum['can_register'] ) : ?>
													<?php echo esc_html__( 'Nicht registrierbar', 'afspaces' ); ?>
												<?php else : ?>
													<?php echo esc_html__( 'Bereit', 'afspaces' ); ?>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $forum['is_registered'] ) : ?>
													<?php
													$manage_url = SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => (int) $forum['space_id'] ) );
													?>
													<a class="afspaces-button" href="<?php echo esc_url( $manage_url ); ?>"><?php echo esc_html__( 'Öffnen', 'afspaces' ); ?></a>
												<?php elseif ( ! $forum['can_register'] ) : ?>
													<span><?php echo esc_html__( 'Ordne zuerst in Asgaros eine Benutzergruppe zur Kategorie zu.', 'afspaces' ); ?></span>
												<?php else : ?>
													<form method="post" class="afspaces-inline-form">
														<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
														<input type="hidden" name="afspaces_action" value="register_space" />
														<input type="hidden" name="forum_id" value="<?php echo esc_attr( (string) $forum['forum_id'] ); ?>" />
														<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Als Raum registrieren', 'afspaces' ); ?></button>
													</form>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Prüft die Verwaltungsberechtigung für einen Space.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return bool
		 */
		private function can_manage_space( int $space_id, int $actor ): bool {
			if ( user_can( $actor, 'afspaces_manage_all_spaces' ) ) {
				return true;
			}
			return $this->spaces->is_manager( $space_id, $actor );
		}

		/**
		 * Prüft, ob ein Benutzer Mitglied der Zugriffsgruppe eines Spaces ist.
		 *
		 * @param \AFSpaces\Domain\Space $space Space.
		 * @param int                      $actor Benutzer-ID.
		 * @return bool
		 */
		private function is_member_of_space( \AFSpaces\Domain\Space $space, int $actor ): bool {
			$group_ids = $this->asgaros->get_forum_group_ids( $space->forum_id );
			if ( empty( $group_ids ) && $space->primary_group_id > 0 ) {
				$group_ids = array( (int) $space->primary_group_id );
			}

			foreach ( $group_ids as $group_id ) {
				if ( $this->asgaros->is_user_in_group( $actor, (int) $group_id ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Baut die direkte URL zur Asgaros-Forumseite.
		 *
		 * @param array<string,mixed>|null $forum Forumdaten.
		 * @return string
		 */
		private function space_forum_url( ?array $forum ): string {
			$fallback = home_url( '/forum/' );
			if ( empty( $forum ) ) {
				return $fallback;
			}

			$slug = isset( $forum['slug'] ) ? sanitize_title( (string) $forum['slug'] ) : '';
			if ( '' === $slug ) {
				return $fallback;
			}

			return home_url( '/forum/forum/' . $slug . '/' );
		}

		/**
		 * @param int $actor Benutzer-ID.
		 * @return bool
		 */
		private function can_register_spaces( int $actor ): bool {
			return user_can( $actor, Capabilities::CREATE_SPACE )
				|| user_can( $actor, Capabilities::MANAGE_ALL_SPACES );
		}

		/**
		 * Rendert eine Statusmeldung aus der Session.
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

		/**
		 * Gibt eine einfache Hinweismeldung zurück.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf(
				'<p class="afspaces-notice" role="status">%s</p>',
				esc_html( $text )
			);
		}
	}
}
