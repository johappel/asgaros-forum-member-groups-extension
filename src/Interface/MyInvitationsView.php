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
use AFSpaces\Application\InvitationService;

if ( ! class_exists( 'AFSpaces\\Interface\\MyInvitationsView' ) ) {

	/**
	 * Rendert „Meine Forum-Einladungen“ inklusive Accept/Decline.
	 */
	class MyInvitationsView {

		private InvitationService $invitations;
		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;

		/**
		 * Konstruktor.
		 */
		public function __construct( InvitationService $invitations, SpaceRepository $spaces, AsgarosAdapterInterface $asgaros ) {
			$this->invitations = $invitations;
			$this->spaces      = $spaces;
			$this->asgaros     = $asgaros;
		}

		/**
		 * Rendert die Ansicht.
		 *
		 * @return string
		 */
		public function render(): string {
			if ( ! is_user_logged_in() ) {
				return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html__( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$list  = $this->invitations->list_my_invitations( $actor );

			ob_start();
			?>
			<section class="afspaces-my-invitations" aria-labelledby="afspaces-my-invitations-heading">
				<h2 id="afspaces-my-invitations-heading"><?php echo esc_html__( 'Meine Forum-Einladungen', 'afspaces' ); ?></h2>
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
	}
}
