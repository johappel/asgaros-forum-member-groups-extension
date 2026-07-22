<?php
/**
 * Verwaltungsansicht für Beitrittsanfragen pro Space.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\JoinRequestsView' ) ) {

	/**
	 * Rendert die Entscheidungsliste für offene und historische Beitrittsanfragen.
	 */
	class JoinRequestsView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private JoinRequestService $join_requests;

		/**
		 * Konstruktor.
		 */
		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, JoinRequestService $join_requests ) {
			$this->spaces        = $spaces;
			$this->asgaros       = $asgaros;
			$this->join_requests = $join_requests;
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

			try {
				$requests = $this->join_requests->list_space_requests( $space_id, $actor, null );
			} catch ( DomainException $e ) {
				return $this->notice( $e->getMessage() );
			}

			$forum_data = $this->asgaros->get_forum( $space->forum_id );
			$forum_name = trim( (string) ( $forum_data['name'] ?? '' ) );
			if ( '' === $forum_name ) {
				$forum_name = sprintf( 'Space #%d', $space_id );
			}

			ob_start();
			?>
			<section class="afspaces-join-requests" id="afspaces-join-requests-view" aria-labelledby="afspaces-join-requests-heading">
				<h2 id="afspaces-join-requests-heading"><?php echo esc_html( sprintf( __( 'Beitrittsanfragen - %s', 'afspaces' ), $forum_name ) ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier entscheidest du über offene Beitrittsanfragen für diesen Raum.', 'afspaces' ); ?></p>

				<?php if ( empty( $requests ) ) : ?>
					<p><?php echo esc_html__( 'Es sind derzeit keine Beitrittsanfragen vorhanden.', 'afspaces' ); ?></p>
				<?php else : ?>
					<table class="afspaces-member-table afspaces-invitations-table">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Person', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Status', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Anfrage', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Entscheidung', 'afspaces' ); ?></th>
								<th><?php echo esc_html__( 'Aktion', 'afspaces' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $requests as $request ) : ?>
								<?php $requester = get_userdata( $request->requester_user_id ); ?>
								<tr>
									<td><?php echo esc_html( $requester ? $requester->display_name : (string) $request->requester_user_id ); ?></td>
									<td><?php echo esc_html( $request->status ); ?></td>
									<td><?php echo esc_html( $request->request_message ); ?></td>
									<td><?php echo esc_html( $request->decision_message ); ?></td>
									<td>
										<?php if ( 'pending' === $request->status ) : ?>
											<form method="post" class="afspaces-inline-form">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="approve_join_request" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="join_request_id" value="<?php echo esc_attr( (string) $request->id ); ?>" />
												<label>
													<span class="screen-reader-text"><?php echo esc_html__( 'Hinweis zur Entscheidung', 'afspaces' ); ?></span>
													<input type="text" name="decision_message" maxlength="500" placeholder="<?php echo esc_attr__( 'Optionale Notiz', 'afspaces' ); ?>" />
												</label>
												<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Genehmigen', 'afspaces' ); ?></button>
											</form>
											<form method="post" class="afspaces-inline-form">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="reject_join_request" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="join_request_id" value="<?php echo esc_attr( (string) $request->id ); ?>" />
												<label>
													<span class="screen-reader-text"><?php echo esc_html__( 'Hinweis zur Entscheidung', 'afspaces' ); ?></span>
													<input type="text" name="decision_message" maxlength="500" placeholder="<?php echo esc_attr__( 'Optionale Notiz', 'afspaces' ); ?>" />
												</label>
												<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Ablehnen', 'afspaces' ); ?></button>
											</form>
										<?php else : ?>
											<span><?php echo esc_html__( 'Bereits entschieden', 'afspaces' ); ?></span>
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
	}
}
