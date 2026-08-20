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
use AFSpaces\Application\ModerationActionVisibility;
use AFSpaces\Application\SpaceModerationService;

if ( ! class_exists( 'AFSpaces\\Interface\\ModerationView' ) ) {

	/**
	 * Listet die Themen des eigenen Forums und bietet Moderationsaktionen.
	 */
	class ModerationView {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private SpaceModerationService $moderation;
		private ModerationActionVisibility $visibility;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, SpaceModerationService $moderation, ?ModerationActionVisibility $visibility = null ) {
			$this->spaces     = $spaces;
			$this->asgaros    = $asgaros;
			$this->moderation = $moderation;
			$this->visibility = $visibility ?: new ModerationActionVisibility( $asgaros );
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
			$can_create_forum = $this->moderation->can_create_forum( $space_id, $actor );

			ob_start();
			?>
			<section class="afspaces-moderation" aria-labelledby="afspaces-moderation-heading">
				<h2 id="afspaces-moderation-heading"><?php echo esc_html__( 'Moderation', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>
				<p><?php echo esc_html__( 'Moderiert wird direkt im Forum: Bei Themen und Beiträgen findest du dort die jeweiligen Moderationsfunktionen. Diese Seite bietet dir eine schnelle Übersicht über die Themen deiner Arbeitsgruppe und führt dich direkt zur Moderation im Forum.', 'afspaces' ); ?></p>
				<details class="afspaces-moderation-help">
					<summary><?php echo esc_html__( 'Deine Moderationsmöglichkeiten', 'afspaces' ); ?></summary>
					<ul>
						<li><strong><?php echo esc_html__( 'Anpinnen', 'afspaces' ); ?></strong> – <?php echo esc_html__( 'Wichtiges Thema oben halten.', 'afspaces' ); ?></li>
						<li><strong><?php echo esc_html__( 'Schließen', 'afspaces' ); ?></strong> – <?php echo esc_html__( 'Weitere Antworten verhindern.', 'afspaces' ); ?></li>
						<li><strong><?php echo esc_html__( 'Wieder öffnen', 'afspaces' ); ?></strong> – <?php echo esc_html__( 'Antworten wieder zulassen.', 'afspaces' ); ?></li>
						<li><strong><?php echo esc_html__( 'Löschen', 'afspaces' ); ?></strong> – <?php echo esc_html__( 'Thema samt Beiträgen entfernen.', 'afspaces' ); ?></li>
					</ul>
				</details>
				<?php echo $this->render_forum_management( $space_id, (int) $space->forum_id, $actor, $can_create_forum ); ?>

				<?php if ( empty( $topics ) ) : ?>
					<p role="status"><?php echo esc_html__( 'In diesem Forum gibt es noch keine Themen.', 'afspaces' ); ?></p>
				<?php else : ?>
					<p class="afspaces-moderation-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%d Thema', '%d Themen', $total, 'afspaces' ), $total ) ); ?></p>
					<div class="afspaces-table-wrap">
					<table class="afspaces-table afspaces-table--responsive afspaces-moderation-table">
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
				<?php $topic_url = $this->topic_url( $topic_id, $topic ); ?>
				<?php $pin_action = AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_PIN; ?>
				<tr>
									<th scope="row"><?php echo esc_html( (string) $topic['name'] ); ?></th>
									<td><?php echo esc_html( (string) ( $topic['author_name'] ?? '' ) ); ?></td>
									<td><?php echo esc_html( (string) (int) ( $topic['post_count'] ?? 0 ) ); ?></td>
										<td class="afspaces-table__status">
										<?php if ( ! empty( $topic['closed'] ) ) : ?>
											<span class="afspaces-badge afspaces-badge--warning"><?php echo esc_html__( 'Geschlossen', 'afspaces' ); ?></span>
										<?php else : ?>
											<span class="afspaces-badge afspaces-badge--success"><?php echo esc_html__( 'Offen', 'afspaces' ); ?></span>
										<?php endif; ?>
									</td>
					<td>
						<div class="afspaces-table__actions">
							<a class="afspaces-button afspaces-button-primary" href="<?php echo esc_url( $topic_url ); ?>"><?php echo esc_html__( 'Im Forum moderieren', 'afspaces' ); ?></a>
							<?php if ( $this->should_render_local_action( $pin_action, $actor, $topic_id ) ) : ?>
							<?php if ( ! empty( $topic['sticky'] ) ) : ?>
								<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_unpin_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
									<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Abpinnen', 'afspaces' ); ?></button>
												</form>
											<?php else : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_pin_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
									<button type="submit" class="afspaces-button afspaces-button-primary"><?php echo esc_html__( 'Anpinnen', 'afspaces' ); ?></button>
								</form>
							<?php endif; ?>
							<?php endif; ?>
							<?php $close_action = ! empty( $topic['closed'] ) ? AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_OPEN : AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_CLOSE; ?>
							<?php if ( $this->should_render_local_action( $close_action, $actor, $topic_id ) ) : ?>
							<?php if ( ! empty( $topic['closed'] ) ) : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_reopen_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
															<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Wieder öffnen', 'afspaces' ); ?></button>
												</form>
											<?php else : ?>
												<form method="post">
													<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
													<input type="hidden" name="afspaces_action" value="moderate_close_topic" />
													<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
													<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
									<button type="submit" class="afspaces-button afspaces-button-primary"><?php echo esc_html__( 'Thema schließen', 'afspaces' ); ?></button>
								</form>
							<?php endif; ?>
							<?php endif; ?>
							<?php if ( $this->should_render_local_action( AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE, $actor, $topic_id ) ) : ?>
							<form method="post">
												<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
												<input type="hidden" name="afspaces_action" value="moderate_delete_topic" />
												<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
												<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
								<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Thema mit allen Beiträgen wirklich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Thema löschen', 'afspaces' ); ?></button>
							</form>
							<?php endif; ?>
							<?php if ( ! empty( $targets ) && $this->should_render_local_action( AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_MOVE, $actor, $topic_id ) ) : ?>
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
															<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Thema verschieben', 'afspaces' ); ?></button>
												</form>
											<?php endif; ?>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>

					<?php echo $this->render_pagination( $space_id, $page, $total, 20 ); ?>
				<?php endif; ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Rendert die Verwaltung der diesem Space zugeordneten Foren.
		 *
		 * Das Primärforum bleibt als technische Basis des Spaces erhalten. Nur
		 * zusätzliche, tatsächlich gemappte Foren erhalten die Löschaktion.
		 *
		 * @param int $space_id   Space-ID.
		 * @param int $primary_id Primärforum-ID.
		 * @param int $actor      Akteur.
		 * @return string
		 */
		private function render_forum_management( int $space_id, int $primary_id, int $actor, bool $can_create_forum ): string {
			$forum_ids = $this->spaces->list_forum_ids( $space_id );
			if ( empty( $forum_ids ) && $primary_id > 0 ) {
				$forum_ids = array( $primary_id );
			}

			$forum_ids = array_values( array_unique( array_filter( array_map( 'intval', $forum_ids ) ) ) );
			$additional_forums = array_values( array_filter( $forum_ids, static fn ( int $forum_id ): bool => $forum_id !== $primary_id ) );
			if ( empty( $additional_forums ) && ! $can_create_forum ) {
				return '';
			}

			ob_start();
			?>
			<section class="afspaces-forum-management" aria-labelledby="afspaces-forum-management-heading">
				<h2 id="afspaces-forum-management-heading"><?php echo esc_html__( 'Gruppenforen verwalten', 'afspaces' ); ?></h2>
				<p><?php echo esc_html__( 'Hier siehst du die Foren dieser Arbeitsgruppe. Zusätzliche Foren können bei Bedarf samt ihren Themen und Beiträgen entfernt werden.', 'afspaces' ); ?></p>
				<?php if ( $can_create_forum ) : ?>
					<details class="afspaces-create-forum">
						<summary class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( '+ Forum erstellen', 'afspaces' ); ?></summary>
						<form method="post">
							<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
							<input type="hidden" name="afspaces_action" value="moderate_create_forum" />
							<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
							<p><label for="afspaces-new-forum-name-<?php echo esc_attr( (string) $space_id ); ?>"><?php echo esc_html__( 'Forumname', 'afspaces' ); ?></label>
							<input required maxlength="120" type="text" id="afspaces-new-forum-name-<?php echo esc_attr( (string) $space_id ); ?>" name="forum_name" /></p>
							<p><label for="afspaces-new-forum-description-<?php echo esc_attr( (string) $space_id ); ?>"><?php echo esc_html__( 'Beschreibung (optional)', 'afspaces' ); ?></label>
							<textarea id="afspaces-new-forum-description-<?php echo esc_attr( (string) $space_id ); ?>" name="forum_description" rows="3"></textarea></p>
							<button type="submit" class="afspaces-button afspaces-button-primary"><?php echo esc_html__( 'Forum erstellen', 'afspaces' ); ?></button>
						</form>
					</details>
				<?php endif; ?>
				<div class="afspaces-table-wrap">
				<table class="afspaces-table afspaces-table--responsive afspaces-forum-management-table">
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html__( 'Forum', 'afspaces' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Typ', 'afspaces' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Themen', 'afspaces' ); ?></th>
							<th scope="col"><?php echo esc_html__( 'Aktionen', 'afspaces' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $forum_ids as $forum_id ) : ?>
						<?php
						$forum      = $this->asgaros->get_forum( $forum_id );
						$forum_name = (string) ( $forum['name'] ?? sprintf( __( 'Forum #%d', 'afspaces' ), $forum_id ) );
						$is_primary = $forum_id === $primary_id;
						$can_delete = null !== $forum && ! $is_primary && $this->moderation->can_delete_forum( $space_id, $actor, $forum_id );
						$topic_count = (int) ( $this->asgaros->list_forum_topics( $forum_id, array( 'page' => 1, 'per_page' => 1 ) )['total'] ?? 0 );
						?>
						<tr>
							<th scope="row"><?php echo esc_html( $forum_name ); ?></th>
							<td><span class="afspaces-badge afspaces-badge--neutral"><?php echo esc_html( $is_primary ? __( 'Primärforum', 'afspaces' ) : __( 'Zusätzliches Forum', 'afspaces' ) ); ?></span></td>
							<td><?php echo esc_html( (string) $topic_count ); ?></td>
							<td>
								<div class="afspaces-table__actions">
									<a class="afspaces-button afspaces-button-primary" href="<?php echo esc_url( $this->forum_url( $forum ) ); ?>"><?php echo esc_html__( 'Im Forum moderieren', 'afspaces' ); ?></a>
							<?php if ( $can_delete ) : ?>
								<form method="post">
									<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
									<input type="hidden" name="afspaces_action" value="moderate_delete_forum" />
									<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
									<input type="hidden" name="forum_id" value="<?php echo esc_attr( (string) $forum_id ); ?>" />
									<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Forum wirklich vollständig löschen? Alle Themen und Beiträge darin werden unwiderruflich entfernt.', 'afspaces' ); ?>"><?php echo esc_html__( 'Forum löschen', 'afspaces' ); ?></button>
								</form>
							<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
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
							<?php $is_first_post = ! empty( $post['is_first'] ); ?>
							<?php $delete_action = $is_first_post ? AsgarosAdapterInterface::MODERATION_ACTION_TOPIC_DELETE : AsgarosAdapterInterface::MODERATION_ACTION_POST_DELETE; ?>
							<li class="afspaces-moderation-post content-container">
								<p class="afspaces-moderation-post-meta">
									<strong><?php echo esc_html( (string) ( $post['author_name'] ?? '' ) ); ?></strong>
									<?php if ( $is_first_post ) : ?>
										<span class="afspaces-tag"><?php echo esc_html__( 'Eröffnungsbeitrag', 'afspaces' ); ?></span>
									<?php endif; ?>
								</p>
								<div class="afspaces-moderation-post-text"><?php echo wp_kses_post( (string) ( $post['text'] ?? '' ) ); ?></div>
								<div class="afspaces-table__actions">
									<?php if ( $this->should_render_local_action( $delete_action, $actor, $topic_id, (int) $post['id'] ) ) : ?>
									<form method="post">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="moderate_delete_post" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
										<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) (int) $post['id'] ); ?>" />
										<input type="hidden" name="redirect_to" value="<?php echo esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_MODERATION, array( 'space_id' => $space_id, 'mod_topic' => $topic_id ) ) ); ?>" />
										<?php $confirm = $is_first_post ? __( 'Der Eröffnungsbeitrag wird gelöscht – dadurch wird das gesamte Thema entfernt. Fortfahren?', 'afspaces' ) : __( 'Diesen Beitrag wirklich löschen?', 'afspaces' ); ?>
										<?php $delete_label = $is_first_post ? __( 'Thema löschen', 'afspaces' ) : __( 'Beitrag löschen', 'afspaces' ); ?>
										<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr( $confirm ); ?>"><?php echo esc_html( $delete_label ); ?></button>
									</form>
									<?php endif; ?>
									<?php if ( ! $is_first_post && ! empty( $post_targets ) && $this->should_render_local_action( AsgarosAdapterInterface::MODERATION_ACTION_POST_MOVE, $actor, $topic_id, (int) $post['id'] ) ) : ?>
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
											<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Beitrag verschieben', 'afspaces' ); ?></button>
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
		 * Wendet die aktionsbezogene native Asgaros-Deduplizierung an.
		 *
		 * @param string $action   Native Vergleichsaktion.
		 * @param int    $actor    Aktuelle Benutzer-ID.
		 * @param int    $topic_id Themen-ID.
		 * @param int    $post_id  Beitrags-ID.
		 * @return bool
		 */
		private function should_render_local_action( string $action, int $actor, int $topic_id = 0, int $post_id = 0 ): bool {
			return $this->visibility->should_render_local_action( $action, true, $actor, $topic_id, $post_id );
		}

		/**
		 * Baut einen direkten Link zum Forum eines Spaces.
		 *
		 * @param array<string,mixed>|null $forum Forumdaten.
		 * @return string
		 */
		private function forum_url( ?array $forum ): string {
			$slug = sanitize_title( (string) ( $forum['slug'] ?? '' ) );
			return '' !== $slug ? home_url( '/forum/forum/' . $slug . '/' ) : home_url( '/forum/' );
		}

		/**
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf( '<p class="afspaces-notice" role="status">%s</p>', esc_html( $text ) );
		}

		/**
		 * Baut möglichst direkt den Asgaros-Link zum Thema.
		 *
		 * @param int                 $topic_id Thema.
		 * @param array<string,mixed> $topic    Topic-Daten.
		 * @return string
		 */
		private function topic_url( int $topic_id, array $topic ): string {
			$first_post_id = (int) ( $topic['first_post_id'] ?? 0 );
			if ( $first_post_id > 0 ) {
				$url = $this->asgaros->get_post_link( $first_post_id, $topic_id );
				if ( '' !== $url ) {
					return $url;
				}
			}

			$forum = $this->asgaros->get_forum( (int) ( $topic['forum_id'] ?? 0 ) );
			$slug = sanitize_title( (string) ( $forum['slug'] ?? '' ) );
			return '' !== $slug ? home_url( '/forum/forum/' . $slug . '/' ) : home_url( '/forum/' );
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
