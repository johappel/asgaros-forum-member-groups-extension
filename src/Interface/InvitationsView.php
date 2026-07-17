<?php
/**
 * Verwaltungsansicht für Space-Einladungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\InvitationsView' ) ) {

	/**
	 * Rendert Einladungserstellung und Einladungsliste für Manager.
	 */
	class InvitationsView {

		private SpaceRepository $spaces;
		private InvitationService $invitations;
		private MemberService $members;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, InvitationService $invitations, MemberService $members ) {
			$this->spaces      = $spaces;
			$this->invitations = $invitations;
			$this->members     = $members;
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
				return $this->notice( __( 'Raum nicht gefunden.', 'afspaces' ) );
			}

			$status_filter = isset( $_GET['inv_status'] ) ? sanitize_text_field( wp_unslash( $_GET['inv_status'] ) ) : '';
			$search        = isset( $_GET['inv_search'] ) ? sanitize_text_field( wp_unslash( $_GET['inv_search'] ) ) : '';

			try {
				$list = $this->invitations->list_space_invitations( $space_id, $actor, '' !== $status_filter ? $status_filter : null );
			} catch ( DomainException $e ) {
				return $this->notice( $e->getMessage() );
			}

			$search_results = array();
			if ( '' !== $search ) {
				$search_results = $this->members->search_users( $search, 1, 20 )['members'] ?? array();
			}

			ob_start();
			?>
			<section class="afspaces-invitations" aria-labelledby="afspaces-invitations-heading">
				<h2 id="afspaces-invitations-heading"><?php echo esc_html__( 'Einladungen', 'afspaces' ); ?></h2>

				<form method="get" class="afspaces-search" role="search" aria-label="<?php echo esc_attr__( 'Benutzer für Einladung suchen', 'afspaces' ); ?>">
					<label for="inv_search"><?php echo esc_html__( 'Person suchen', 'afspaces' ); ?></label>
					<input type="search" id="inv_search" name="inv_search" value="<?php echo esc_attr( $search ); ?>" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Suchen', 'afspaces' ); ?></button>
				</form>

				<?php if ( ! empty( $search_results ) ) : ?>
					<h3><?php echo esc_html__( 'Benutzer einladen', 'afspaces' ); ?></h3>
					<ul class="afspaces-search-results">
						<?php foreach ( $search_results as $user ) : ?>
							<li>
								<div>
									<strong><?php echo esc_html( $user['display_name'] ); ?></strong>
									<span>(<?php echo esc_html( $user['user_login'] ); ?>)</span>
								</div>
								<form method="post" class="afspaces-inline-form">
									<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
									<input type="hidden" name="afspaces_action" value="create_invitation" />
									<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
									<input type="hidden" name="invitee_user_id" value="<?php echo esc_attr( (string) $user['user_id'] ); ?>" />
									<label>
										<span class="screen-reader-text"><?php echo esc_html__( 'Persönliche Nachricht', 'afspaces' ); ?></span>
										<input type="text" name="message" maxlength="500" placeholder="<?php echo esc_attr__( 'Optionale Nachricht', 'afspaces' ); ?>" />
									</label>
									<label>
										<span class="screen-reader-text"><?php echo esc_html__( 'Ablauf in Tagen', 'afspaces' ); ?></span>
										<input type="number" name="expires_in_days" min="1" max="30" value="7" />
									</label>
									<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Einladen', 'afspaces' ); ?></button>
								</form>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Einladungen dieses Raums', 'afspaces' ); ?></h3>
				<form method="get" class="afspaces-filter" aria-label="<?php echo esc_attr__( 'Einladungen filtern', 'afspaces' ); ?>">
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
					<label for="inv_status"><?php echo esc_html__( 'Status', 'afspaces' ); ?></label>
					<select id="inv_status" name="inv_status">
						<option value=""><?php echo esc_html__( 'Alle', 'afspaces' ); ?></option>
						<?php foreach ( array( 'pending', 'accepted', 'declined', 'revoked', 'expired' ) as $status ) : ?>
							<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status_filter, $status ); ?>><?php echo esc_html( $status ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Filtern', 'afspaces' ); ?></button>
				</form>

				<?php if ( empty( $list ) ) : ?>
					<p><?php echo esc_html__( 'Keine Einladungen vorhanden.', 'afspaces' ); ?></p>
				<?php else : ?>
					<table class="afspaces-member-table afspaces-invitations-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Person', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Ablauf', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Nachricht', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $list as $inv ) : ?>
								<?php $user = get_userdata( $inv->invitee_user_id ); ?>
								<tr>
									<td><?php echo esc_html( $user ? $user->display_name : (string) $inv->invitee_user_id ); ?></td>
									<td><span><?php echo esc_html( $inv->effective_status() ); ?></span></td>
									<td><?php echo esc_html( $inv->expires_at ); ?></td>
									<td><?php echo esc_html( $inv->message ); ?></td>
									<td>
										<?php if ( 'pending' === $inv->effective_status() ) : ?>
											<form method="post" class="afspaces-inline-form">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="revoke_invitation" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="invitation_id" value="<?php echo esc_attr( (string) $inv->id ); ?>" />
												<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Widerrufen', 'afspaces' ); ?></button>
											</form>
											<form method="post" class="afspaces-inline-form">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="resend_invitation" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="invitation_id" value="<?php echo esc_attr( (string) $inv->id ); ?>" />
												<button type="submit" class="afspaces-button"><?php echo esc_html__( 'E-Mail erneut senden', 'afspaces' ); ?></button>
											</form>
										<?php else : ?>
											<span><?php echo esc_html__( 'Keine Aktion', 'afspaces' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</section>
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
	}
}
