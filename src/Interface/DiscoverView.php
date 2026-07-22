<?php
/**
 * Frontend-Ansicht zum Entdecken von Räumen und Senden von Beitrittsanfragen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\JoinRequestService;

if ( ! class_exists( 'AFSpaces\\Interface\\DiscoverView' ) ) {

	/**
	 * Rendert die Discovery-Liste fuer nicht zugeordnete Nutzer.
	 */
	class DiscoverView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private JoinRequestService $requests;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, JoinRequestService $requests ) {
			$this->spaces   = $spaces;
			$this->asgaros  = $asgaros;
			$this->requests = $requests;
		}

		/**
		 * @return string
		 */
		public function render(): string {
			if ( ! is_user_logged_in() ) {
				return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html__( 'Bitte melde dich an.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$all_spaces = $this->spaces->list_spaces();
			$my_requests = $this->requests->list_my_requests( $actor );
			$request_by_space = $this->latest_request_by_space( $my_requests );

			$discoverable = array();
			foreach ( $all_spaces as $space ) {
				if ( 'active' !== $space->status ) {
					continue;
				}
				if ( $this->spaces->is_manager( $space->id, $actor ) ) {
					continue;
				}
				if ( $this->asgaros->is_user_in_group( $actor, $space->primary_group_id ) ) {
					continue;
				}

				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}

				$discoverable[] = array(
					'space'   => $space,
					'forum'   => $forum,
					'request' => $request_by_space[ $space->id ] ?? null,
				);
			}

			ob_start();
			?>
			<section class="afspaces-discover" aria-labelledby="afspaces-discover-heading">
				<h2 id="afspaces-discover-heading"><?php echo esc_html__( 'Räume entdecken', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier kannst du geschlossene Räume sehen und einen Beitritt anfragen.', 'afspaces' ); ?></p>

				<?php if ( empty( $discoverable ) ) : ?>
					<p><?php echo esc_html__( 'Derzeit sind keine weiteren Räume fuer dich verfuegbar.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $discoverable as $item ) : ?>
							<?php
							$space = $item['space'];
							$forum = $item['forum'];
							$request = $item['request'];
							?>
							<li class="afspaces-space-item">
								<h3><?php echo esc_html( (string) ( $forum['name'] ?? sprintf( 'Space #%d', $space->id ) ) ); ?></h3>
								<p><strong><?php echo esc_html__( 'Status:', 'afspaces' ); ?></strong> <?php echo esc_html__( 'geschlossen', 'afspaces' ); ?></p>
								<?php if ( ! empty( $forum['description'] ) ) : ?>
									<p><?php echo esc_html( (string) $forum['description'] ); ?></p>
								<?php endif; ?>

								<?php if ( null !== $request && 'pending' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine Beitrittsanfrage ist offen.', 'afspaces' ); ?></p>
								<?php elseif ( null !== $request && 'approved' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine letzte Anfrage wurde genehmigt.', 'afspaces' ); ?></p>
								<?php elseif ( null !== $request && 'rejected' === $request->status ) : ?>
									<p><?php echo esc_html__( 'Deine letzte Anfrage wurde abgelehnt. Du kannst erneut anfragen.', 'afspaces' ); ?></p>
								<?php endif; ?>

								<?php if ( null === $request || 'pending' !== $request->status ) : ?>
									<form method="post" class="afspaces-inline-form">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="create_join_request" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
										<label>
											<span class="screen-reader-text"><?php echo esc_html__( 'Nachricht fuer Raumverantwortliche', 'afspaces' ); ?></span>
											<input type="text" name="request_message" maxlength="500" placeholder="<?php echo esc_attr__( 'Optionale Nachricht', 'afspaces' ); ?>" />
										</label>
										<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Beitritt anfragen', 'afspaces' ); ?></button>
									</form>
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
		 * @param array<int,\AFSpaces\Domain\JoinRequest> $requests Anfragen.
		 * @return array<int,\AFSpaces\Domain\JoinRequest>
		 */
		private function latest_request_by_space( array $requests ): array {
			$map = array();
			foreach ( $requests as $request ) {
				if ( ! isset( $map[ $request->space_id ] ) ) {
					$map[ $request->space_id ] = $request;
				}
			}
			return $map;
		}

		/**
		 * @return string
		 */
		private function render_message(): string {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}

			if ( empty( $_SESSION['afspaces_message'] ) || ! is_array( $_SESSION['afspaces_message'] ) ) {
				return '';
			}

			$type = isset( $_SESSION['afspaces_message']['type'] ) ? (string) $_SESSION['afspaces_message']['type'] : 'success';
			$text = isset( $_SESSION['afspaces_message']['message'] ) ? (string) $_SESSION['afspaces_message']['message'] : '';
			unset( $_SESSION['afspaces_message'] );

			if ( '' === $text ) {
				return '';
			}

			$class = 'error' === $type ? 'afspaces-notice afspaces-notice-error' : 'afspaces-notice afspaces-notice-success';
			$role  = 'error' === $type ? 'alert' : 'status';

			return sprintf( '<div class="%1$s" role="%2$s">%3$s</div>', esc_attr( $class ), esc_attr( $role ), esc_html( $text ) );
		}
	}
}
