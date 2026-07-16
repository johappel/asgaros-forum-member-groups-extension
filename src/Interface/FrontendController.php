<?php
/**
 * Frontend-Controller für Dashboard und Mitgliederansicht.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Interface\\FrontendController' ) ) {

	/**
	 * Rendert das Dashboard und verarbeitet Formular-Requests.
	 */
	class FrontendController {

		/**
		 * @var SpaceRepository
		 */
		private SpaceRepository $spaces;

		/**
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * @var MemberService
		 */
		private MemberService $members;

		/**
		 * @var string
		 */
		private string $nonce_action = 'afspaces_member_action';

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository         $spaces  Space-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param MemberService           $members Mitglieder-Service.
		 */
		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			MemberService $members
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->members = $members;
		}

		/**
		 * Initialisiert Hooks.
		 *
		 * @return void
		 */
		public function init(): void {
			add_shortcode( 'afspaces_dashboard', array( $this, 'render_dashboard' ) );
			add_action( 'init', array( $this, 'handle_actions' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Bindet die Frontend-Assets ein.
		 *
		 * @return void
		 */
		public function enqueue_assets(): void {
			if ( ! has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'afspaces_dashboard' )
				&& ! has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'afspaces_members' ) ) {
				return;
			}
			wp_enqueue_style(
				'afspaces-frontend',
				AFSPACES_URL . 'assets/afspaces.css',
				array(),
				AFSPACES_VERSION
			);
		}

		/**
		 * Verarbeitet Formular-Requests (serverseitig, mit Nonce).
		 *
		 * @return void
		 */
		public function handle_actions(): void {
			if ( ! isset( $_POST['afspaces_action'] ) ) {
				return;
			}

			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), $this->nonce_action ) ) {
				wp_die( esc_html__( 'Ungültige Anfrage (Nonce).', 'afspaces' ) );
			}

			$space_id = isset( $_POST['space_id'] ) ? (int) $_POST['space_id'] : 0;
			$actor    = get_current_user_id();
			$action   = sanitize_text_field( wp_unslash( $_POST['afspaces_action'] ) );

			try {
				if ( 'add_member' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->add_member( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Person wurde hinzugefügt.', 'afspaces' ) );
				} elseif ( 'remove_member' === $action ) {
					$target = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
					$this->members->remove_member( $space_id, $actor, $target );
					$this->set_message( 'success', __( 'Die Person wurde entfernt.', 'afspaces' ) );
				}
			} catch ( DomainException $e ) {
				$this->set_message( 'error', $e->getMessage() );
			}

			// Redirect zurück zur sauberen URL (Post/Redirect/Get).
			$members_page = get_page_by_path( 'afspaces-members' );
			$redirect_url = $members_page ? get_permalink( $members_page ) : ( wp_get_referer() ?: home_url() );
			wp_safe_redirect( add_query_arg( 'space_id', $space_id, $redirect_url ) );
			exit;
		}

		/**
		 * Speichert eine Statusmeldung in der Session.
		 *
		 * @param string $type    'success' | 'error'.
		 * @param string $message Meldung.
		 * @return void
		 */
		private function set_message( string $type, string $message ): void {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}
			$_SESSION['afspaces_message'] = array(
				'type'    => $type,
				'message' => $message,
			);
		}

		/**
		 * Rendert das Dashboard (Shortcode).
		 *
		 * @return string
		 */
		public function render_dashboard(): string {
			if ( ! is_user_logged_in() ) {
				return $this->notice( __( 'Bitte melde dich an, um deine Räume zu verwalten.', 'afspaces' ) );
			}

			$actor = get_current_user_id();
			$spaces = $this->spaces->list_spaces();

			$manageable = array();
			foreach ( $spaces as $space ) {
				if ( $this->can_manage_space( $space->id, $actor ) ) {
					// Skip orphaned spaces (forum_id points to non-existent forum).
					$forum = $this->asgaros->get_forum( $space->forum_id );
					if ( ! empty( $forum ) ) {
						$manageable[] = $space;
					}
				}
			}

			ob_start();
			?>
			<section class="afspaces-dashboard" aria-labelledby="afspaces-dashboard-heading">
				<h2 id="afspaces-dashboard-heading"><?php echo esc_html__( 'Meine Räume', 'afspaces' ); ?></h2>
				<?php echo $this->render_message(); ?>

				<?php if ( empty( $manageable ) ) : ?>
					<p><?php echo esc_html__( 'Dir sind noch keine Räume zugeordnet. Ein Administrator kann bestehende Foren als Raum registrieren.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-space-list">
						<?php foreach ( $manageable as $space ) : ?>
							<?php
							$forum = $this->asgaros->get_forum( $space->forum_id );
							// $forum is guaranteed to be non-empty due to filtering in render_dashboard().
							$group_ids = $this->asgaros->get_forum_group_ids( $space->forum_id );
							$member_count = 0;
							if ( ! empty( $group_ids ) ) {
								$members = $this->asgaros->list_group_members( (int) $group_ids[0], array( 'per_page' => 1 ) );
								$member_count = (int) ( $members['total'] ?? 0 );
							}
							// Link zur Mitgliederseite (nicht zum Dashboard).
							$members_page = get_page_by_path( 'afspaces-members' );
							$members_url = $members_page ? get_permalink( $members_page ) : home_url();
							$manage_url = add_query_arg(
								array( 'space_id' => $space->id ),
								$members_url
							);
							?>
							<li class="afspaces-space-item">
								<h3><?php echo esc_html( $forum['name'] ); ?></h3>
								<p><?php echo esc_html( sprintf( __( '%d Mitglieder', 'afspaces' ), $member_count ) ); ?></p>
								<a class="afspaces-button" href="<?php echo esc_url( $manage_url ); ?>">
									<?php echo esc_html__( 'Mitglieder verwalten', 'afspaces' ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Prüft die Verwaltungsberechtigung für einen Space.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return bool
		 */
		private function can_manage_space( int $space_id, int $actor ): bool {
			if ( user_can( $actor, 'afspaces_manage_all_spaces' ) ) {
				return true;
			}
			return $this->spaces->is_manager( $space_id, $actor );
		}

		/**
		 * Rendert eine Statusmeldung aus der Session.
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

		/**
		 * Gibt eine einfache Hinweismeldung zurück.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private function notice( string $text ): string {
			return sprintf(
				'<p class="afspaces-notice" role="status">%s</p>',
				esc_html( $text )
			);
		}
	}
}
