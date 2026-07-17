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
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\MyInvitationsView' ) ) {

	/**
	 * Rendert „Meine Forum-Einladungen“ inklusive Accept/Decline.
	 */
	class MyInvitationsView {

		private InvitationService $invitations;
		private InviteLinkService $invite_links;
		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Konstruktor.
		 */
		public function __construct( InvitationService $invitations, InviteLinkService $invite_links, SpaceRepository $spaces, AsgarosAdapterInterface $asgaros ) {
			$this->invitations = $invitations;
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
			$invite_link_token = isset( $_GET['invite_link'] ) ? sanitize_text_field( wp_unslash( $_GET['invite_link'] ) ) : '';
			if ( '' !== $invite_link_token ) {
				return $this->render_invite_link( $invite_link_token );
			}

			if ( ! is_user_logged_in() ) {
				return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html__( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$list  = $this->invitations->list_my_invitations( $actor );

			ob_start();
			?>
			<section class="afspaces-my-invitations" aria-labelledby="afspaces-my-invitations-heading">
				<h2 id="afspaces-my-invitations-heading"><?php echo esc_html__( 'Meine Forum-Einladungen', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Mit der Annahme wirst du Mitglied der jeweiligen Raumgruppe.', 'afspaces' ); ?></p>
				<?php if ( empty( $list ) ) : ?>
					<p><?php echo esc_html__( 'Du hast aktuell keine Einladungen.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $list as $inv ) : ?>
							<?php
							$space  = $this->spaces->get_space( $inv->space_id );
							$forum  = $space ? $this->asgaros->get_forum( $space->forum_id ) : null;
							$sender = get_userdata( $inv->inviter_user_id );
							$token  = $this->invitations->build_token( $inv );
							?>
							<li class="afspaces-space-item">
								<h3><?php echo esc_html( $forum['name'] ?? sprintf( 'Space #%d', $inv->space_id ) ); ?></h3>
								<p><strong><?php echo esc_html__( 'Absender:', 'afspaces' ); ?></strong> <?php echo esc_html( $sender ? $sender->display_name : '-' ); ?></p>
								<p><strong><?php echo esc_html__( 'Status:', 'afspaces' ); ?></strong> <?php echo esc_html( $inv->effective_status() ); ?></p>
								<p><strong><?php echo esc_html__( 'Ablauf:', 'afspaces' ); ?></strong> <?php echo esc_html( $inv->expires_at ); ?></p>
								<?php if ( '' !== $inv->message ) : ?>
									<p><strong><?php echo esc_html__( 'Nachricht:', 'afspaces' ); ?></strong> <?php echo esc_html( $inv->message ); ?></p>
								<?php endif; ?>

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
			$actor = get_current_user_id();

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
				<h3><?php echo esc_html( $preview['forum_name'] ); ?></h3>
				<p><?php echo esc_html( $preview['status_message'] ); ?></p>

				<?php if ( 'login_required' === $preview['state'] ) : ?>
					<p><a class="afspaces-button" href="<?php echo esc_url( $preview['login_url'] ); ?>"><?php echo esc_html__( 'Anmelden und fortfahren', 'afspaces' ); ?></a></p>
					<?php if ( $preview['can_register'] && '' !== $preview['registration_url'] ) : ?>
						<?php $privacy_url = (string) wp_privacy_policy_url(); ?>
						<form method="post" class="afspaces-invite-registration-consent" aria-label="<?php echo esc_attr__( 'Registrierung mit Datenschutz-Zustimmung', 'afspaces' ); ?>">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="request_invite_link_registration" />
							<input type="hidden" name="invite_link_token" value="<?php echo esc_attr( $token ); ?>" />
							<label for="afspaces-privacy-consent" class="afspaces-checkbox">
								<input type="checkbox" id="afspaces-privacy-consent" name="privacy_consent" value="1" required />
								<span>
									<?php if ( '' !== $privacy_url ) : ?>
										<?php
										echo wp_kses_post(
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
						<?php do_action( 'afspaces_invite_link_registration_captcha', $preview['link'], $preview['space'] ); ?>
					<?php endif; ?>
				<?php elseif ( in_array( $preview['state'], array( 'ready', 'approval_required' ), true ) ) : ?>
					<form method="post" class="afspaces-inline-form">
						<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
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
	}
}
