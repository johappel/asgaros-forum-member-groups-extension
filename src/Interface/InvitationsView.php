<?php
/**
 * Verwaltungsansicht für Space-Einladungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InviteLinkService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\InvitationsView' ) ) {

	/**
	 * Rendert Einladungserstellung und Einladungsliste für Manager.
	 */
	class InvitationsView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private InvitationService $invitations;
		private MemberService $members;
		private InviteLinkService $invite_links;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, InvitationService $invitations, MemberService $members, InviteLinkService $invite_links ) {
			$this->spaces      = $spaces;
			$this->asgaros     = $asgaros;
			$this->invitations = $invitations;
			$this->members     = $members;
			$this->invite_links = $invite_links;
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
				$link_list = $this->invite_links->list_links( $space_id, $actor );
			} catch ( DomainException $e ) {
				return $this->notice( $e->getMessage() );
			}

			$search_results = array();
			if ( '' !== $search ) {
				$search_results = $this->members->search_users( $search, 1, 20 )['members'] ?? array();
			}

			$forum_data = $this->asgaros->get_forum( $space->forum_id );
			$forum_name = trim( (string) ( $forum_data['name'] ?? '' ) );
			if ( '' === $forum_name ) {
				$forum_name = sprintf( 'Space #%d', $space_id );
			}

			ob_start();
			?>
			<section class="afspaces-invitations" aria-labelledby="afspaces-invitations-heading">
				<h2 id="afspaces-invitations-heading"><?php echo esc_html( sprintf( __( 'Einladungen - %s', 'afspaces' ), $forum_name ) ); ?></h2>
				<?php echo $this->render_message(); ?>
				<?php
				$dashboard_url = SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD );
				?>
				<p>
					<a href="<?php echo esc_url( $dashboard_url ); ?>" class="afspaces-link-back">
						<?php echo esc_html__( '← Zurück zu Meine Räume', 'afspaces' ); ?>
					</a>
				</p>
				<p><strong><?php echo esc_html__( 'Raum:', 'afspaces' ); ?></strong> <?php echo esc_html( $forum_name ); ?></p>
				<p><?php echo esc_html__( 'Hier kannst du Personen suchen, persönlich einladen und Einladungslinks verwalten. Einladungslinks funktionieren unabhängig davon, ob neue Benutzer sich anmelden oder registrieren müssen.', 'afspaces' ); ?></p>
				<?php echo $this->render_created_invite_link(); ?>

				<section class="afspaces-invite-links" aria-labelledby="afspaces-invite-links-heading">
					<h3 id="afspaces-invite-links-heading"><?php echo esc_html__( 'Einladungslinks', 'afspaces' ); ?></h3>
					<p><?php echo esc_html__( 'Ein Link wird nur einmal vollständig angezeigt. Später ist nur noch die Verwaltung des Links möglich.', 'afspaces' ); ?></p>
					<p class="description"><?php echo esc_html__( 'Die optionale Registrierung neuer Benutzer wird nur angeboten, wenn sie auf dieser Website zentral erlaubt ist.', 'afspaces' ); ?></p>

					<form method="post" class="afspaces-invite-link-form">
						<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
						<input type="hidden" name="afspaces_action" value="create_invite_link" />
						<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />

						<label for="approval_mode"><?php echo esc_html__( 'Freigabemodus', 'afspaces' ); ?></label>
						<select id="approval_mode" name="approval_mode">
							<option value="auto_join"><?php echo esc_html__( 'Automatische Aufnahme', 'afspaces' ); ?></option>
							<option value="approval_required"><?php echo esc_html__( 'Beitrittsanfrage mit Freigabe', 'afspaces' ); ?></option>
						</select>

						<label for="invite_link_max_uses"><?php echo esc_html__( 'Maximale Nutzungen', 'afspaces' ); ?></label>
						<input type="number" id="invite_link_max_uses" name="max_uses" min="0" max="1000" value="1" />
						<p class="description"><?php echo esc_html__( '0 steht für unbegrenzt und wird nur bei entsprechender Freigabe akzeptiert.', 'afspaces' ); ?></p>

						<label for="invite_link_expires_days"><?php echo esc_html__( 'Ablauf in Tagen', 'afspaces' ); ?></label>
						<input type="number" id="invite_link_expires_days" name="expires_in_days" min="1" max="30" value="7" />

						<?php if ( $this->invite_links->is_registration_available_for_space( $space ) ) : ?>
							<label for="invite_link_registration" class="afspaces-checkbox">
								<input type="checkbox" id="invite_link_registration" name="allow_registration" value="1" />
								<span><?php echo esc_html__( 'Registrierung für neue Benutzer anbieten', 'afspaces' ); ?></span>
							</label>
						<?php endif; ?>

						<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Einladungslink erstellen', 'afspaces' ); ?></button>
					</form>

					<?php if ( empty( $link_list ) ) : ?>
						<p><?php echo esc_html__( 'Es sind noch keine Einladungslinks vorhanden.', 'afspaces' ); ?></p>
					<?php else : ?>
						<table class="afspaces-member-table afspaces-invitations-table afspaces-invite-links-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Status', 'afspaces' ); ?></th>
									<th><?php echo esc_html__( 'Freigabe', 'afspaces' ); ?></th>
									<th><?php echo esc_html__( 'Nutzungen', 'afspaces' ); ?></th>
									<th><?php echo esc_html__( 'Ablauf', 'afspaces' ); ?></th>
									<th><?php echo esc_html__( 'Registrierung', 'afspaces' ); ?></th>
									<th><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $link_list as $link ) : ?>
									<tr>
										<td><?php echo esc_html( $link->effective_status() ); ?></td>
										<td><?php echo esc_html( 'approval_required' === $link->approval_mode ? __( 'Manuelle Freigabe', 'afspaces' ) : __( 'Automatisch', 'afspaces' ) ); ?></td>
										<td><?php echo esc_html( 0 === $link->max_uses ? __( 'unbegrenzt', 'afspaces' ) : sprintf( '%1$d / %2$d', $link->use_count, $link->max_uses ) ); ?></td>
										<td><?php echo esc_html( $link->expires_at ); ?></td>
										<td><?php echo esc_html( $link->allows_registration() ? __( 'Ja', 'afspaces' ) : __( 'Nein', 'afspaces' ) ); ?></td>
										<td>
											<?php if ( 'active' === $link->effective_status() ) : ?>
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="revoke_invite_link" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="invite_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Widerrufen', 'afspaces' ); ?></button>
												</form>
												<form method="post" class="afspaces-inline-form">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="shorten_invite_link" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="invite_link_id" value="<?php echo esc_attr( (string) $link->id ); ?>" />
													<label>
														<span class="screen-reader-text"><?php echo esc_html__( 'Neues Ablaufdatum', 'afspaces' ); ?></span>
														<input type="datetime-local" name="shorten_expires_at" value="<?php echo esc_attr( gmdate( 'Y-m-d\TH:i', strtotime( $link->expires_at ) ) ); ?>" />
													</label>
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Ablauf verkürzen', 'afspaces' ); ?></button>
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

				<form method="get" class="afspaces-search" role="search" aria-label="<?php echo esc_attr__( 'Benutzer für Einladung suchen', 'afspaces' ); ?>">
					<label for="inv_search"><?php echo esc_html__( 'Person suchen', 'afspaces' ); ?></label>
					<input type="search" id="inv_search" name="inv_search" value="<?php echo esc_attr( $search ); ?>" />
					<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
					<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Suchen', 'afspaces' ); ?></button>
				</form>

				<?php if ( ! empty( $search_results ) ) : ?>
					<p class="description"><?php echo esc_html__( 'Wähle eine Person aus der Liste und sende eine persönliche Einladung mit optionaler Nachricht und Laufzeit.', 'afspaces' ); ?></p>
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
		 * @return string
		 */
		private function render_created_invite_link(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_created_invite_link'] ) ) {
				return '';
			}

			$url = (string) $_SESSION['afspaces_created_invite_link'];

			$field_id = 'afspaces-created-link';
			$status_id = 'afspaces-created-link-status';

			return sprintf(
				'<div class="afspaces-created-invite-link" role="status" aria-live="polite"><p>%1$s</p><div class="afspaces-inline-form"><input type="text" readonly value="%2$s" id="%3$s" /><button type="button" class="afspaces-button" onclick="var field=document.getElementById(\'%3$s\'); if(field){ field.focus(); field.select(); try { navigator.clipboard.writeText(field.value); } catch(e) {} var status=document.getElementById(\'%4$s\'); if(status){ status.textContent=\'%5$s\'; } }">%6$s</button></div><p id="%4$s" class="afspaces-copy-feedback" role="status" aria-live="polite"></p></div>',
				esc_html__( 'Neuer Einladungslink:', 'afspaces' ),
				esc_attr( $url ),
				esc_attr( $field_id ),
				esc_attr( $status_id ),
				esc_js( __( 'Link kopiert.', 'afspaces' ) ),
				esc_html__( 'Link kopieren', 'afspaces' )
			);
		}
	}
}
