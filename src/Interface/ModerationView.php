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

			// Beitragsebene: einzelnes Thema mit seinen Beiträgen moderieren.
			$mod_topic = isset( $_GET['mod_topic'] ) ? (int) $_GET['mod_topic'] : 0;
			if ( $mod_topic > 0 ) {
				return $this->render_posts_panel( $space_id, $actor, $mod_topic );
			}

			$page    = isset( $_GET['afp_page'] ) ? max( 1, (int) $_GET['afp_page'] ) : 1;
			$result  = $this->moderation->list_topics( $space_id, $actor, array( 'page' => $page, 'per_page' => 20 ) );
			$topics  = $result['topics'] ?? array();
			$total   = (int) ( $result['total'] ?? 0 );
			$targets = $this->moderation->list_move_targets( $actor, $space_id );

			ob_start();
			?>
			<section class="afspaces-moderation" aria-labelledby="afspaces-moderation-heading">
				<h2 id="afspaces-moderation-heading"><?php echo esc_html__( 'Moderation der Themen meiner Arbeitsgruppe', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Hier moderierst du ausschließlich die Themen deines eigenen Forums. Du kannst Themen oben halten, schließen, wieder öffnen oder löschen. Diese Rechte gelten nur für dieses Forum und geben keine globalen Asgaros-Moderatorrechte.', 'afspaces' ); ?></p>

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
											<?php if ( ! empty( $topic['sticky'] ) ) : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_unpin_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Nicht mehr oben halten', 'afspaces' ); ?></button>
												</form>
											<?php else : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_pin_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
													<button type="submit" class="afspaces-button afspaces-button-primary"><?php echo esc_html__( 'Oben halten', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>
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
													<button type="submit" class="afspaces-button afspaces-button-primary"><?php echo esc_html__( 'Schließen', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>
											<form method="post">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="moderate_delete_topic" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
												<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Thema mit allen Beiträgen wirklich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Löschen', 'afspaces' ); ?></button>
											</form>
											<a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id, 'mod_topic' => $topic_id ) ) ); ?>"><?php echo esc_html__( 'Beiträge', 'afspaces' ); ?></a>
											<?php if ( ! empty( $targets ) ) : ?>
												<form method="post" class="afspaces-moderation-move">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_move_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
													<label class="screen-reader-text" for="afspaces-move-<?php echo esc_attr( (string) $topic_id ); ?>"><?php echo esc_html__( 'Verschieben nach', 'afspaces' ); ?></label>
													<select id="afspaces-move-<?php echo esc_attr( (string) $topic_id ); ?>" name="target_space_id">
														<?php foreach ( $targets as $target ) : ?>
															<option value="<?php echo esc_attr( (string) $target['space_id'] ); ?>"><?php echo esc_html( (string) $target['name'] ); ?></option>
														<?php endforeach; ?>
													</select>
													<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Verschieben', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>
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
		 * Rendert die Beitragsebene eines Themas (Beiträge einzeln löschen).
		 *
		 * @param int    $space_id   Space-ID.
		 * @param int    $actor      Akteur.
		 * @param int    $topic_id   Themen-ID.
		 * @return string
		 */
		private function render_posts_panel( int $space_id, int $actor, int $topic_id ): string {
			try {
				$result = $this->moderation->list_posts( $space_id, $actor, $topic_id, array( 'per_page' => 50 ) );
			} catch ( \AFSpaces\Core\DomainException $e ) {
				return $this->notice( $e->getMessage() );
			}

			$posts        = $result['posts'] ?? array();
			$post_targets = $this->moderation->list_post_move_targets( $space_id, $actor, $topic_id );
			$back         = SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id ) );

			ob_start();
			?>
			<section class="afspaces-moderation" aria-labelledby="afspaces-moderation-posts-heading">
				<h2 id="afspaces-moderation-posts-heading"><?php echo esc_html__( 'Beiträge moderieren', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><a class="afspaces-button afspaces-button-secondary" href="<?php echo esc_url( $back ); ?>"><?php echo esc_html__( 'Zurück zu den Themen', 'afspaces' ); ?></a></p>

				<?php if ( empty( $posts ) ) : ?>
					<p role="status"><?php echo esc_html__( 'Dieses Thema hat keine Beiträge (mehr).', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-moderation-posts">
						<?php foreach ( $posts as $post ) : ?>
							<li class="afspaces-moderation-post content-container">
								<p class="afspaces-moderation-post-meta">
									<strong><?php echo esc_html( (string) ( $post['author_name'] ?? '' ) ); ?></strong>
									<?php if ( ! empty( $post['is_first'] ) ) : ?>
										<span class="afspaces-tag"><?php echo esc_html__( 'Eröffnungsbeitrag', 'afspaces' ); ?></span>
									<?php endif; ?>
								</p>
								<div class="afspaces-moderation-post-text"><?php echo wp_kses_post( (string) ( $post['text'] ?? '' ) ); ?></div>
								<div class="afspaces-moderation-actions">
									<form method="post">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="moderate_delete_post" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
										<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) (int) $post['id'] ); ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id, 'mod_topic' => $topic_id ) ) ); ?>" />
										<?php $confirm = ! empty( $post['is_first'] ) ? __( 'Der Eröffnungsbeitrag wird gelöscht – dadurch wird das gesamte Thema entfernt. Fortfahren?', 'afspaces' ) : __( 'Diesen Beitrag wirklich löschen?', 'afspaces' ); ?>
										<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr( $confirm ); ?>"><?php echo esc_html__( 'Beitrag löschen', 'afspaces' ); ?></button>
									</form>
									<?php if ( empty( $post['is_first'] ) && ! empty( $post_targets ) ) : ?>
										<form method="post" class="afspaces-moderation-move">
											<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
											<input type="hidden" name="afspaces_action" value="moderate_move_post" />
											<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
											<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) (int) $post['id'] ); ?>" />
											<input type="hidden" name="redirect_to" value="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id, 'mod_topic' => $topic_id ) ) ); ?>" />
											<label class="screen-reader-text" for="afspaces-movepost-<?php echo esc_attr( (string) (int) $post['id'] ); ?>"><?php echo esc_html__( 'In Thema verschieben', 'afspaces' ); ?></label>
											<select id="afspaces-movepost-<?php echo esc_attr( (string) (int) $post['id'] ); ?>" name="target_topic_id">
												<?php foreach ( $post_targets as $target ) : ?>
													<option value="<?php echo esc_attr( (string) $target['topic_id'] ); ?>"><?php echo esc_html( (string) $target['name'] ); ?></option>
												<?php endforeach; ?>
											</select>
											<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Verschieben', 'afspaces' ); ?></button>
										</form>
									<?php endif; ?>
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
