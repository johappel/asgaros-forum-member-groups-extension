<?php
/**
 * Frontend-Ansicht für die raum-begrenzte Forenmoderation (MVP 4, Option b).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceModerationService;

if ( ! class_exists( 'AFSpaces\\Interface\\ModerationView' ) ) {

	/**
	 * Listet die Themen des eigenen Forums und bietet Moderationsaktionen.
	 */
	class ModerationView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private SpaceModerationService $moderation;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, SpaceModerationService $moderation ) {
			$this->spaces     = $spaces;
			$this->asgaros    = $asgaros;
			$this->moderation = $moderation;
		}

		/**
		 * Rendert die Moderationsansicht für einen Space.
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
				return $this->notice( __( 'Diese Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->moderation->can_moderate( $space_id, $actor ) ) {
				return $this->notice( __( 'Du darfst dieses Forum nicht moderieren.', 'afspaces' ) );
			}

			$forum      = $this->asgaros->get_forum( $space->forum_id );
			$forum_name = trim( (string) ( $forum['name'] ?? '' ) );
			if ( '' === $forum_name ) {
				$forum_name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space_id );
			}

			$page   = isset( $_GET['afp_page'] ) ? max( 1, (int) $_GET['afp_page'] ) : 1;
			$result = $this->moderation->list_topics( $space_id, $actor, array( 'page' => $page, 'per_page' => 20 ) );
			$topics = $result['topics'] ?? array();
			$total  = (int) ( $result['total'] ?? 0 );

			ob_start();
			?>
			<section class="afspaces-moderation" aria-labelledby="afspaces-moderation-heading">
				<h2 id="afspaces-moderation-heading"><?php echo esc_html( sprintf( __( 'Moderation - %s', 'afspaces' ), $forum_name ) ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier moderierst du ausschließlich die Themen deines eigenen Forums. Du kannst Themen schließen, wieder öffnen oder löschen. Diese Rechte gelten nur für dieses Forum.', 'afspaces' ); ?></p>

				<?php if ( empty( $topics ) ) : ?>
					<p role="status"><?php echo esc_html__( 'In diesem Forum gibt es noch keine Themen.', 'afspaces' ); ?></p>
				<?php else : ?>
					<p class="afspaces-moderation-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d Thema', '%d Themen', $total, 'afspaces' ), $total ) ); ?></p>
					<table class="afspaces-moderation-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'Thema', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Autor', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Beiträge', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Status', 'afspaces' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'Aktionen', 'afspaces' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $topics as $topic ) : ?>
								<?php $topic_id = (int) $topic['id']; ?>
								<tr>
									<th scope="row"><?php echo esc_html( (string) $topic['name'] ); ?></th>
									<td><?php echo esc_html( (string) ( $topic['author_name'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) (int) ( $topic['post_count'] ?? 0 ) ); ?></td>
									<td>
										<?php if ( ! empty( $topic['closed'] ) ) : ?>
											<span class="afspaces-tag"><?php echo esc_html__( 'Geschlossen', 'afspaces' ); ?></span>
										<?php else : ?>
											<span class="afspaces-tag"><?php echo esc_html__( 'Offen', 'afspaces' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<div class="afspaces-moderation-actions">
											<?php if ( ! empty( $topic['closed'] ) ) : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_reopen_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Öffnen', 'afspaces' ); ?></button>
												</form>
											<?php else : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_close_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Schließen', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>
											<form method="post">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="moderate_delete_topic" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
												<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Thema mit allen Beiträgen wirklich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Löschen', 'afspaces' ); ?></button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo $this->render_pagination( $space_id, $page, $total, 20 ); ?>
				<?php endif; ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert eine einfache, semantische Seitennavigation.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $page     Aktuelle Seite.
		 * @param int $total    Gesamtzahl.
		 * @param int $per_page Einträge pro Seite.
		 * @return string
		 */
		private function render_pagination( int $space_id, int $page, int $total, int $per_page ): string {
			$pages = (int) ceil( $total / max( 1, $per_page ) );
			if ( $pages <= 1 ) {
				return '';
			}

			$items = '';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id, 'afp_page' => $i ) );
				if ( $i === $page ) {
					$items .= sprintf( '<li><span class="afspaces-button afspaces-button-secondary" aria-current="page">%d</span></li>', $i );
				} else {
					$items .= sprintf( '<li><a class="afspaces-button afspaces-button-secondary" href="%s">%d</a></li>', esc_url( $url ), $i );
				}
			}

			return sprintf(
				'<nav class="afspaces-pagination" aria-label="%1$s"><ul class="afspaces-pagination-list">%2$s</ul></nav>',
				esc_attr__( 'Seitennavigation der Themen', 'afspaces' ),
				$items
			);
		}

		/**
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

		/**
		 * Rendert eine gespeicherte Statusmeldung (Post/Redirect/Get).
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
	}
}
