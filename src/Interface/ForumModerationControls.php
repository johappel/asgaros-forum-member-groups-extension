<?php
/**
 * Blendet raum-begrenzte Moderationsaktionen direkt im Asgaros-Forum ein.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceModerationService;

if ( ! class_exists( 'AFSpaces\\Interface\\ForumModerationControls' ) ) {

	/**
	 * Rendert unter Beiträgen der eigenen Foren Moderationsaktionen.
	 *
	 * Verwendet ausschließlich den dokumentierten Asgaros-Hook
	 * `asgarosforum_after_post_message`. Es werden KEINE globalen
	 * Asgaros-Moderatorrechte vergeben; die Aktionen laufen über den
	 * space-begrenzten {@see SpaceModerationService}, der die Zugehörigkeit
	 * jedes Themas/Beitrags zum eigenen Forum prüft.
	 */
	class ForumModerationControls {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private SpaceModerationService $moderation;

		/**
		 * Zwischenspeicher je Forum-ID: [space, can_moderate, targets].
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private array $forum_cache = array();

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros, SpaceModerationService $moderation ) {
			$this->spaces     = $spaces;
			$this->asgaros    = $asgaros;
			$this->moderation = $moderation;
		}

		/**
		 * Registriert den Hook.
		 *
		 * @return void
		 */
		public function init(): void {
			add_action( 'asgarosforum_after_post_message', array( $this, 'render_controls' ), 20, 2 );
		}

		/**
		 * Rendert die Moderationsaktionen unter einem Beitrag.
		 *
		 * @param int $author_id Autor-ID (vom Hook geliefert, ungenutzt).
		 * @param int $post_id   Beitrags-ID.
		 * @return void
		 */
		public function render_controls( $author_id, $post_id ): void {
			unset( $author_id );

			$post_id = (int) $post_id;
			$user_id = get_current_user_id();
			if ( $post_id < 1 || 0 === $user_id ) {
				return;
			}

			// Nur in der Themenansicht anzeigen.
			global $asgarosforum;
			if ( ! is_object( $asgarosforum ) || ! isset( $asgarosforum->current_view ) || 'topic' !== $asgarosforum->current_view ) {
				return;
			}

			$location = $this->asgaros->get_post_location( $post_id );
			if ( null === $location ) {
				return;
			}

			$forum_id = (int) $location['forum_id'];
			$context  = $this->resolve_forum_context( $forum_id, $user_id );
			if ( null === $context ) {
				return;
			}

			$space    = $context['space'];
			$space_id = (int) $space->id;
			$is_first = ! empty( $location['is_first'] );
			$topic_id = (int) $location['topic_id'];

			$forum     = $this->asgaros->get_forum( $forum_id );
			$slug      = sanitize_title( (string) ( $forum['slug'] ?? '' ) );
			$forum_url = '' !== $slug ? home_url( '/forum/forum/' . $slug . '/' ) : home_url( '/forum/' );
			$forum_url = (string) apply_filters( 'afspaces_space_forum_url', $forum_url, $space, $forum, $user_id );
			$topic_url = $this->asgaros->get_post_link( $post_id, $topic_id );
			if ( '' === $topic_url ) {
				$topic_url = $forum_url;
			}

			echo '<div class="afspaces-forum-moderation" role="group" aria-label="' . esc_attr__( 'Moderation', 'afspaces' ) . '">';
			echo '<span class="afspaces-forum-moderation-label">' . esc_html__( 'Moderation:', 'afspaces' ) . '</span>';

			if ( $is_first ) {
				// Eröffnungsbeitrag: Themen-Aktionen (löschen, verschieben).
				$this->render_delete_topic_form( $space_id, $topic_id, $forum_url );
				$this->render_move_topic_form( $space_id, $topic_id, $context['targets'], $forum_url );
			} else {
				// Folgebeitrag: einzelnen Beitrag löschen.
				$this->render_delete_post_form( $space_id, $post_id, $topic_url );
			}

			echo '</div>';
		}

		/**
		 * Ermittelt (gecacht) den Space und die Moderationsberechtigung für ein Forum.
		 *
		 * @param int $forum_id Forum-ID.
		 * @param int $user_id  Benutzer-ID.
		 * @return array<string,mixed>|null
		 */
		private function resolve_forum_context( int $forum_id, int $user_id ): ?array {
			if ( array_key_exists( $forum_id, $this->forum_cache ) ) {
				$cached = $this->forum_cache[ $forum_id ];
				return false === $cached['ok'] ? null : $cached;
			}

			$space = $this->spaces->get_space_by_forum( $forum_id );
			if ( ! $space || ! $this->moderation->can_moderate( (int) $space->id, $user_id ) ) {
				$this->forum_cache[ $forum_id ] = array( 'ok' => false );
				return null;
			}

			$context = array(
				'ok'      => true,
				'space'   => $space,
				'targets' => $this->moderation->list_move_targets( $user_id, (int) $space->id ),
			);
			$this->forum_cache[ $forum_id ] = $context;

			return $context;
		}

		/**
		 * @param int    $space_id  Space-ID.
		 * @param int    $post_id   Beitrags-ID.
		 * @param string $return_to Ziel-URL nach der Aktion.
		 * @return void
		 */
		private function render_delete_post_form( int $space_id, int $post_id, string $return_to ): void {
			?>
			<form method="post" class="afspaces-forum-moderation-form">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="moderate_delete_post" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $return_to ); ?>" />
				<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Diesen Beitrag wirklich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Beitrag löschen', 'afspaces' ); ?></button>
			</form>
			<?php
		}

		/**
		 * @param int    $space_id  Space-ID.
		 * @param int    $topic_id  Themen-ID.
		 * @param string $return_to Ziel-URL nach der Aktion.
		 * @return void
		 */
		private function render_delete_topic_form( int $space_id, int $topic_id, string $return_to ): void {
			?>
			<form method="post" class="afspaces-forum-moderation-form">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="moderate_delete_topic" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $return_to ); ?>" />
				<button type="submit" class="afspaces-button afspaces-button-danger" data-afspaces-confirm="<?php echo esc_attr__( 'Dieses Thema mit allen Beiträgen wirklich löschen?', 'afspaces' ); ?>"><?php echo esc_html__( 'Thema löschen', 'afspaces' ); ?></button>
			</form>
			<?php
		}

		/**
		 * @param int                                                    $space_id  Space-ID.
		 * @param int                                                    $topic_id  Themen-ID.
		 * @param array<int,array{space_id:int, forum_id:int, name:string}> $targets Zielforen.
		 * @param string                                                 $return_to Ziel-URL nach der Aktion.
		 * @return void
		 */
		private function render_move_topic_form( int $space_id, int $topic_id, array $targets, string $return_to ): void {
			if ( empty( $targets ) ) {
				return;
			}
			$select_id = 'afspaces-move-target-' . $topic_id;
			?>
			<form method="post" class="afspaces-forum-moderation-form afspaces-forum-moderation-move">
				<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
				<input type="hidden" name="afspaces_action" value="moderate_move_topic" />
				<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space_id ); ?>" />
				<input type="hidden" name="topic_id" value="<?php echo esc_attr( (string) $topic_id ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $return_to ); ?>" />
				<label class="screen-reader-text" for="<?php echo esc_attr( $select_id ); ?>"><?php echo esc_html__( 'Thema verschieben nach', 'afspaces' ); ?></label>
				<select id="<?php echo esc_attr( $select_id ); ?>" name="target_space_id">
					<?php foreach ( $targets as $target ) : ?>
						<option value="<?php echo esc_attr( (string) $target['space_id'] ); ?>"><?php echo esc_html( (string) $target['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="afspaces-button afspaces-button-secondary"><?php echo esc_html__( 'Verschieben', 'afspaces' ); ?></button>
			</form>
			<?php
		}
	}
}
