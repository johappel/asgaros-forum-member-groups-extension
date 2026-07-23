<?php
/**
 * Frontend-Ansicht für eingeladene Benutzer.
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
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\MyInvitationsView' ) ) {

	/**
	 * Rendert „Meine Forum-Einladungen“ inklusive Accept/Decline.
	 */
	class MyInvitationsView {

		private InvitationService $invitations;
		private JoinRequestService $join_requests;
		private InviteLinkService $invite_links;
		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Konstruktor.
		 */
		public function __construct( InvitationService $invitations, JoinRequestService $join_requests, InviteLinkService $invite_links, SpaceRepository $spaces, AsgarosAdapterInterface $asgaros ) {
			$this->invitations = $invitations;
			$this->join_requests = $join_requests;
			$this->invite_links = $invite_links;
			$this->spaces      = $spaces;
			$this->asgaros     = $asgaros;
		}

		/**
		 * Rendert die Ansicht.
		 *
		 * @return string
		 */
		public function render(): string {
			$invite_link_token = isset( $_GET['invite_link'] ) ? sanitize_text_field( (string) $_GET['invite_link'] ) : '';
			if ( '' !== $invite_link_token ) {
				return $this->render_invite_link( $invite_link_token );
			}

			if ( ! \is_user_logged_in() ) {
				return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html__( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = $this->current_user_id();
			$list  = $this->invitations->list_my_invitations( $actor );
			$join_requests = $this->join_requests->list_my_requests( $actor );
			$dashboard_url = SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD );

			ob_start();
			?>
			<section class="afspaces-my-invitations" aria-labelledby="afspaces-my-invitations-heading">
				<h2 id="afspaces-my-invitations-heading"><?php echo esc_html__( 'Meine Forum-Einladungen', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" class="afspaces-link-back">
						<?php echo esc_html__( '← Zurück zu Meine Räume', 'afspaces' ); ?>
					</a>
				</p>
				<?php if ( empty( $list ) ) : ?>
					<p><?php echo esc_html__( 'Du hast aktuell keine Einladungen.', 'afspaces' ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__( 'Mit der Annahme wirst du Mitglied der jeweiligen Raumgruppe.', 'afspaces' ); ?></p>
					<ul class="afspaces-space-list">
						<?php foreach ( $list as $inv ) : ?>
							<?php
							$space  = $this->spaces->get_space( $inv->space_id );
							$forum  = $space ? $this->asgaros->get_forum( $space->forum_id ) : null;
							$sender = \get_userdata( $inv->inviter_user_id );
							$token  = $this->invitations->build_token( $inv );
							?>
							<li class="afspaces-space-item content-container afspaces-content-container">
								<div class="content-element forum afspaces-forum-row">
									<div class="forum-status read" aria-hidden="true"><i class="fas fa-envelope"></i></div>
									<div class="forum-name">
										<span class="forum-title"><?php echo esc_html( $forum['name'] ?? sprintf( 'Space #%d', $inv->space_id ) ); ?></span>
										<small class="forum-description"><?php echo esc_html( sprintf( __( 'Absender: %s', 'afspaces' ), $sender ? $sender->display_name : '-' ) ); ?></small>
										<small class="forum-description"><?php echo esc_html( sprintf( __( 'Status: %s', 'afspaces' ), $inv->effective_status() ) ); ?></small>
										<small class="forum-stats"><?php echo esc_html( sprintf( __( 'Ablauf: %s', 'afspaces' ), $inv->expires_at ) ); ?></small>
										<?php if ( '' !== $inv->message ) : ?>
											<small class="forum-description"><?php echo esc_html( sprintf( __( 'Nachricht: %s', 'afspaces' ), $inv->message ) ); ?></small>
										<?php endif; ?>
									</div>
									<div class="forum-poster">
										<?php if ( 'pending' === $inv->effective_status() ) : ?>
											<div class="afspaces-inline-form" role="group" aria-label="<?php echo esc_attr__( 'Einladung beantworten', 'afspaces' ); ?>">
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="accept_invitation" />
													<input type="hidden" name="invitation_token" value="<?php echo esc_attr( $token ); ?>" />
													<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Annehmen', 'afspaces' ); ?></button>
												</form>
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="decline_invitation" />
													<input type="hidden" name="invitation_token" value="<?php echo esc_attr( $token ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Ablehnen', 'afspaces' ); ?></button>
												</form>
											</div>
										<?php else : ?>
											<p><?php echo esc_html__( 'Diese Einladung ist bereits abgeschlossen.', 'afspaces' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Meine Beitrittsanfragen', 'afspaces' ); ?></h3>
				<?php if ( empty( $join_requests ) ) : ?>
					<p><?php echo esc_html__( 'Du hast aktuell keine Beitrittsanfragen.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $join_requests as $request ) : ?>
							<?php
							$space  = $this->spaces->get_space( $request->space_id );
							$forum  = $space ? $this->asgaros->get_forum( $space->forum_id ) : null;
							?>
							<li class="afspaces-space-item content-container afspaces-content-container">
								<div class="content-element forum afspaces-forum-row">
									<div class="forum-status read" aria-hidden="true"><i class="fas fa-user-clock"></i></div>
									<div class="forum-name">
										<span class="forum-title"><?php echo esc_html( $forum['name'] ?? sprintf( 'Space #%d', $request->space_id ) ); ?></span>
										<small class="forum-stats"><?php echo esc_html( sprintf( __( 'Status: %s', 'afspaces' ), $request->status ) ); ?></small>
										<?php if ( '' !== $request->request_message ) : ?>
											<small class="forum-description"><?php echo esc_html( sprintf( __( 'Deine Nachricht: %s', 'afspaces' ), $request->request_message ) ); ?></small>
										<?php endif; ?>
										<?php if ( '' !== $request->decision_message ) : ?>
											<small class="forum-description"><?php echo esc_html( sprintf( __( 'Rueckmeldung: %s', 'afspaces' ), $request->decision_message ) ); ?></small>
										<?php endif; ?>
									</div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * @param string $token Klartext-Token.
		 * @return string
		 */
		private function render_invite_link( string $token ): string {
			$actor = $this->current_user_id();

			try {
				$preview = $this->invite_links->preview_link( $token, $actor );
			} catch ( DomainException $e ) {
				return sprintf( '<p class="afspaces-notice" role="alert">%s</p>', esc_html( $e->getMessage() ) );
			}

			ob_start();
			?>
			<section class="afspaces-my-invitations afspaces-invite-link-landing" aria-labelledby="afspaces-invite-link-heading">
					<h2 id="afspaces-invite-link-heading"><?php echo esc_html__( 'Raumeinladung', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
					<p><strong><?php echo esc_html__( 'Raum:', 'afspaces' ); ?></strong> <?php echo esc_html( $preview['forum_name'] ); ?></p>
					<?php $dashboard_url = SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ); ?>
					<p>
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="afspaces-link-back">
							<?php echo esc_html__( '← Zurück zu Meine Räume', 'afspaces' ); ?>
						</a>
					</p>
				<h3><?php echo esc_html( $preview['forum_name'] ); ?></h3>
				<p><?php echo esc_html( $preview['status_message'] ); ?></p>

				<?php if ( 'login_required' === $preview['state'] ) : ?>
					<p><a class="afspaces-button" href="<?php echo esc_url( $preview['login_url'] ); ?>"><?php echo esc_html__( 'Anmelden und fortfahren', 'afspaces' ); ?></a></p>
						<p class="description"><?php echo esc_html__( 'Der Einladungslink selbst ist davon unabhängig, ob du dich nur anmelden oder auf dieser Website zusätzlich registrieren musst.', 'afspaces' ); ?></p>
					<?php if ( $preview['can_register'] && '' !== $preview['registration_url'] ) : ?>
								<?php $privacy_url = function_exists( '\wp_privacy_policy_url' ) ? (string) \wp_privacy_policy_url() : ''; ?>
						<form method="post" class="afspaces-invite-registration-consent" aria-label="<?php echo esc_attr__( 'Registrierung mit Datenschutz-Zustimmung', 'afspaces' ); ?>">
							<?php echo \wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="request_invite_link_registration" />
							<input type="hidden" name="invite_link_token" value="<?php echo esc_attr( $token ); ?>" />
								<p class="description"><?php echo esc_html__( 'Wenn du neu registriert wirst, wird das nur nach zentraler Freigabe angeboten.', 'afspaces' ); ?></p>
							<label for="afspaces-privacy-consent" class="afspaces-checkbox">
								<input type="checkbox" id="afspaces-privacy-consent" name="privacy_consent" value="1" required />
								<span>
									<?php if ( '' !== $privacy_url ) : ?>
										<?php
										echo \wp_kses_post(
											sprintf(
												/* translators: %s: Link zur Datenschutzerklärung */
												__( 'Ich habe die <a href="%s" target="_blank" rel="noopener noreferrer">Datenschutzinformationen</a> gelesen und akzeptiere sie.', 'afspaces' ),
												esc_url( $privacy_url )
											)
										);
										?>
									<?php else : ?>
										<?php echo esc_html__( 'Ich habe die Datenschutzinformationen gelesen und akzeptiere sie.', 'afspaces' ); ?>
									<?php endif; ?>
								</span>
							</label>
							<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Neu registrieren', 'afspaces' ); ?></button>
						</form>
						<?php \do_action( 'afspaces_invite_link_registration_captcha', $preview['link'], $preview['space'] ); ?>
					<?php endif; ?>
				<?php elseif ( in_array( $preview['state'], array( 'ready', 'approval_required' ), true ) ) : ?>
					<form method="post" class="afspaces-inline-form">
							<?php echo \wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
						<input type="hidden" name="afspaces_action" value="use_invite_link" />
						<input type="hidden" name="invite_link_token" value="<?php echo esc_attr( $token ); ?>" />
						<button type="submit" class="afspaces-button"><?php echo esc_html( $preview['action_label'] ); ?></button>
					</form>
				<?php else : ?>
					<p><?php echo esc_html__( 'Für diesen Einladungslink ist aktuell keine weitere Aktion erforderlich.', 'afspaces' ); ?></p>
				<?php endif; ?>
			</section>
			<?php

			return (string) ob_get_clean();
		}

		/**
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
		 * Gibt die aktuelle Benutzer-ID zurück, ohne in Testumgebungen zu fatalen Fehlern zu führen.
		 *
		 * @return int
		 */
		private function current_user_id(): int {
			return function_exists( '\get_current_user_id' ) ? (int) \get_current_user_id() : 0;
		}
	}
}
