<?php
/**
 * Router-Shortcode `[afspaces]` für die Spaces-Hub-Seite.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InviteLinkService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\Capabilities;

if ( ! class_exists( 'AFSpaces\\Interface\\SpacesHubController' ) ) {

	/**
	 * Bündelt alle Frontend-Ansichten unter einer Seite mit gemeinsamer,
	 * forum-naher Navigation und leitet auf die bestehenden Views weiter.
	 */
	class SpacesHubController {

		private FrontendController $frontend;
		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private MemberService $members;
		private InvitationService $invitations;
		private JoinRequestService $join_requests;
		private InviteLinkService $invite_links;

		/**
		 * Konstruktor.
		 */
		public function __construct(
			FrontendController $frontend,
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			MemberService $members,
			InvitationService $invitations,
			JoinRequestService $join_requests,
			InviteLinkService $invite_links
		) {
			$this->frontend     = $frontend;
			$this->spaces       = $spaces;
			$this->asgaros      = $asgaros;
			$this->members      = $members;
			$this->invitations  = $invitations;
			$this->join_requests = $join_requests;
			$this->invite_links = $invite_links;
		}

		/**
		 * Registriert Shortcode und Legacy-Weiterleitungen.
		 *
		 * @return void
		 */
		public function init(): void {
			add_shortcode( 'afspaces', array( $this, 'render' ) );
			add_action( 'template_redirect', array( $this, 'redirect_legacy_pages' ) );
		}

		/**
		 * Leitet alte Einzelseiten auf die passende Hub-Unteransicht um (301).
		 *
		 * @return void
		 */
		public function redirect_legacy_pages(): void {
			if ( is_admin() || ! is_page() ) {
				return;
			}

			$post = get_post();
			if ( ! $post instanceof \WP_Post ) {
				return;
			}

			$map = SpacesUrls::legacy_slug_map();
			if ( ! isset( $map[ $post->post_name ] ) ) {
				return;
			}

			// Wenn die Hub-Seite selbst noch nicht existiert, nichts tun.
			if ( 0 === SpacesUrls::hub_page_id() ) {
				return;
			}

			$args = array();
			if ( isset( $_GET['space_id'] ) ) {
				$args['space_id'] = (int) $_GET['space_id'];
			}
			if ( isset( $_GET['invite_link'] ) ) {
				$args['invite_link'] = sanitize_text_field( wp_unslash( $_GET['invite_link'] ) );
			}
			if ( isset( $_GET['invitation_token'] ) ) {
				$args['invitation_token'] = sanitize_text_field( wp_unslash( $_GET['invitation_token'] ) );
			}

			wp_safe_redirect( SpacesUrls::hub_url( $map[ $post->post_name ], $args ), 301 );
			exit;
		}

		/**
		 * Rendert die Hub-Seite mit Navigation und der aktiven Unteransicht.
		 *
		 * @return string
		 */
		public function render(): string {
			$view     = SpacesUrls::normalize_view( isset( $_GET[ SpacesUrls::VIEW_PARAM ] ) ? wp_unslash( $_GET[ SpacesUrls::VIEW_PARAM ] ) : '' );
			$space_id = isset( $_GET['space_id'] ) ? (int) $_GET['space_id'] : 0;
			$actor    = get_current_user_id();

			$content = $this->render_view( $view, $space_id );

			ob_start();
			?>
		<div id="af-wrapper" class="afspaces-wrapper">
			<?php echo $this->render_breadcrumb( $view, $space_id ); ?>
			<?php echo $this->render_navigation( $view, $space_id, $actor ); ?>
			<?php echo $this->render_space_context_navigation( $view, $space_id, $actor ); ?>
			<div class="afspaces-hub-content">
				<?php echo $content; // Bereits escaped in den jeweiligen Views. ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Wählt die passende Unteransicht aus.
	 *
	 * @param string $view     Unteransicht.
	 * @param int    $space_id Space-ID.
	 * @return string
	 */
	private function render_view( string $view, int $space_id ): string {
			switch ( $view ) {
				case SpacesUrls::VIEW_MEMBERS:
					$members_view = new MembersView( $this->spaces, $this->asgaros, $this->members );
					return $members_view->render( $space_id );

				case SpacesUrls::VIEW_INVITATIONS:
					$inv_view = new InvitationsView( $this->spaces, $this->asgaros, $this->invitations, $this->members, $this->invite_links );
					return $inv_view->render( $space_id );

				case SpacesUrls::VIEW_JOIN_REQUESTS:
					$requests_view = new JoinRequestsView( $this->spaces, $this->asgaros, $this->join_requests );
					return $requests_view->render( $space_id );

				case SpacesUrls::VIEW_MY_INVITATIONS:
					$mine_view = new MyInvitationsView( $this->invitations, $this->join_requests, $this->invite_links, $this->spaces, $this->asgaros );
					return $mine_view->render();

				case SpacesUrls::VIEW_DISCOVER:
					$discover_view = new DiscoverView( $this->spaces, $this->asgaros, $this->join_requests );
					return $discover_view->render();

				case SpacesUrls::VIEW_CREATE:
					return $this->render_create_placeholder();

				case SpacesUrls::VIEW_DASHBOARD:
				default:
					return $this->frontend->render_dashboard();
			}
		}

		/**
		 * Rendert die Breadcrumb-Navigation (Forum › Räume › Unteransicht).
		 *
		 * @param string $view     Unteransicht.
		 * @param int    $space_id Space-ID.
		 * @return string
		 */
		private function render_breadcrumb( string $view, int $space_id ): string {
			$items = array();

			$forum_home = home_url( '/forum/' );
			$forum_home = (string) apply_filters( 'afspaces_forum_home_url', $forum_home );
			$items[]    = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $forum_home ),
				esc_html__( 'Forum', 'afspaces' )
			);

			if ( SpacesUrls::VIEW_DASHBOARD === $view ) {
				$items[] = sprintf( '<span aria-current="page">%s</span>', esc_html__( 'Räume', 'afspaces' ) );
			} else {
				$items[] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ) ),
					esc_html__( 'Räume', 'afspaces' )
				);
				$items[] = sprintf( '<span aria-current="page">%s</span>', esc_html( $this->view_label( $view, $space_id ) ) );
			}

			return sprintf(
				'<nav class="afspaces-breadcrumb" aria-label="%1$s">%2$s</nav>',
				esc_attr__( 'Brotkrümelnavigation', 'afspaces' ),
				implode( '<span class="afspaces-breadcrumb-sep" aria-hidden="true"> › </span>', $items )
			);
		}

		/**
		 * Rendert die Haupt-Unternavigation der Hub-Seite.
		 *
		 * @param string $view     Aktive Unteransicht.
		 * @param int    $space_id Space-ID.
		 * @param int    $actor    Benutzer-ID.
		 * @return string
		 */
		private function render_navigation( string $view, int $space_id, int $actor ): string {
			if ( 0 === $actor ) {
				return '';
			}

			$tabs = array();

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_DASHBOARD,
				'label'  => __( 'Meine Räume', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ),
				'active' => SpacesUrls::VIEW_DASHBOARD === $view,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_MY_INVITATIONS,
				'label'  => __( 'Meine Einladungen', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_MY_INVITATIONS ),
				'active' => SpacesUrls::VIEW_MY_INVITATIONS === $view,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_DISCOVER,
				'label'  => __( 'Räume entdecken', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_DISCOVER ),
				'active' => SpacesUrls::VIEW_DISCOVER === $view,
			);

			if ( $this->can_create_spaces( $actor ) ) {
				$tabs[] = array(
					'view'   => SpacesUrls::VIEW_CREATE,
					'label'  => __( 'Raum gründen', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_CREATE ),
					'active' => SpacesUrls::VIEW_CREATE === $view,
				);
			}

			/**
			 * Erlaubt MVP-4 und Erweiterungen, weitere Tabs einzuhängen.
			 *
			 * @param array<int,array<string,mixed>> $tabs     Tab-Definitionen.
			 * @param string                         $view     Aktive Ansicht.
			 * @param int                            $space_id Space-ID.
			 * @param int                            $actor    Benutzer-ID.
			 */
			$tabs = (array) apply_filters( 'afspaces_hub_navigation_tabs', $tabs, $view, $space_id, $actor );

			$items = '';
			foreach ( $tabs as $tab ) {
				$active = ! empty( $tab['active'] );
				$items .= sprintf(
					'<li><a href="%1$s" class="afspaces-hub-tab%2$s"%3$s>%4$s</a></li>',
					esc_url( (string) $tab['url'] ),
					$active ? ' is-active' : '',
					$active ? ' aria-current="page"' : '',
					esc_html( (string) $tab['label'] )
				);
			}

			return sprintf(
				'<nav class="afspaces-hub-nav" aria-label="%1$s"><ul>%2$s</ul></nav>',
				esc_attr__( 'Raumverwaltung', 'afspaces' ),
				$items
			);
		}

		/**
		 * Rendert raumbezogene Verwaltungstabs unter dem Raumtitel.
		 *
		 * @param string $view     Aktive Unteransicht.
		 * @param int    $space_id Space-ID.
		 * @param int    $actor    Benutzer-ID.
		 * @return string
		 */
		private function render_space_context_navigation( string $view, int $space_id, int $actor ): string {
			if ( 0 === $space_id || 0 === $actor || ! $this->can_manage_space( $space_id, $actor ) ) {
				return '';
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return '';
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			$room_name = trim( (string) ( $forum['name'] ?? '' ) );
			if ( '' === $room_name ) {
				$room_name = sprintf( __( 'Raum #%d', 'afspaces' ), $space_id );
			}

			$tabs = array(
				array(
					'view'   => SpacesUrls::VIEW_MEMBERS,
					'label'  => __( 'Mitglieder', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_MEMBERS, array( 'space_id' => $space_id ) ),
					'active' => SpacesUrls::VIEW_MEMBERS === $view,
				),
				array(
					'view'   => SpacesUrls::VIEW_INVITATIONS,
					'label'  => __( 'Einladungen', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_INVITATIONS, array( 'space_id' => $space_id ) ),
					'active' => SpacesUrls::VIEW_INVITATIONS === $view,
				),
				array(
					'view'   => SpacesUrls::VIEW_JOIN_REQUESTS,
					'label'  => __( 'Beitrittsanfragen', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_JOIN_REQUESTS, array( 'space_id' => $space_id ) ),
					'active' => SpacesUrls::VIEW_JOIN_REQUESTS === $view,
				),
			);

			/**
			 * Erlaubt Erweiterungen für raumbezogene Verwaltungstabs.
			 *
			 * @param array<int,array<string,mixed>> $tabs     Tab-Definitionen.
			 * @param string                         $view     Aktive Ansicht.
			 * @param int                            $space_id Space-ID.
			 * @param int                            $actor    Benutzer-ID.
			 */
			$tabs = (array) apply_filters( 'afspaces_hub_space_navigation_tabs', $tabs, $view, $space_id, $actor );

			$items = '';
			foreach ( $tabs as $tab ) {
				$active = ! empty( $tab['active'] );
				$items .= sprintf(
					'<li><a href="%1$s" class="afspaces-hub-tab%2$s"%3$s>%4$s</a></li>',
					esc_url( (string) $tab['url'] ),
					$active ? ' is-active' : '',
					$active ? ' aria-current="page"' : '',
					esc_html( (string) $tab['label'] )
				);
			}

			return sprintf(
				'<section class="afspaces-space-context" aria-labelledby="afspaces-space-context-heading"><h2 id="afspaces-space-context-heading" class="afspaces-space-context-title">%1$s</h2><nav class="afspaces-hub-nav afspaces-space-nav" aria-label="%2$s"><ul>%3$s</ul></nav></section>',
				esc_html( sprintf( __( 'Raum verwalten: %s', 'afspaces' ), $room_name ) ),
				esc_attr__( 'Raumbezogene Verwaltung', 'afspaces' ),
				$items
			);
		}

		/**
		 * Platzhalter für die spätere Raumgründung (MVP 4).
		 *
		 * @return string
		 */
		private function render_create_placeholder(): string {
			$actor = get_current_user_id();
			if ( ! $this->can_create_spaces( $actor ) ) {
				return sprintf(
					'<p class="afspaces-notice" role="status">%s</p>',
					esc_html__( 'Die Raumgründung ist derzeit nicht verfügbar.', 'afspaces' )
				);
			}

			ob_start();
			?>
			<section class="afspaces-create" aria-labelledby="afspaces-create-heading">
				<h2 id="afspaces-create-heading"><?php echo esc_html__( 'Raum gründen', 'afspaces' ); ?></h2>
				<p><?php echo esc_html__( 'Diese Funktion wird mit der nächsten Ausbaustufe verfügbar sein.', 'afspaces' ); ?></p>
				<?php
				/**
				 * Erweiterungspunkt für den MVP-4-Raumassistenten.
				 */
				do_action( 'afspaces_render_space_creation' );
				?>
			</section>
			<?php
			return (string) ob_get_clean();
		}

		/**
		 * Beschriftung einer Unteransicht (ggf. mit Forumsname).
		 *
		 * @param string $view     Unteransicht.
		 * @param int    $space_id Space-ID.
		 * @return string
		 */
		private function view_label( string $view, int $space_id ): string {
			switch ( $view ) {
				case SpacesUrls::VIEW_MEMBERS:
					return __( 'Mitglieder', 'afspaces' );
				case SpacesUrls::VIEW_INVITATIONS:
					return __( 'Einladungen', 'afspaces' );
				case SpacesUrls::VIEW_MY_INVITATIONS:
					return __( 'Meine Einladungen', 'afspaces' );
				case SpacesUrls::VIEW_JOIN_REQUESTS:
					return __( 'Beitrittsanfragen', 'afspaces' );
				case SpacesUrls::VIEW_DISCOVER:
					return __( 'Räume entdecken', 'afspaces' );
				case SpacesUrls::VIEW_CREATE:
					return __( 'Raum gründen', 'afspaces' );
				default:
					return __( 'Meine Räume', 'afspaces' );
			}
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return bool
		 */
		private function can_manage_space( int $space_id, int $actor ): bool {
			if ( user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) ) {
				return true;
			}
			return $this->spaces->is_manager( $space_id, $actor );
		}

		/**
		 * Prüft, ob die (optionale) Raumgründung für den Benutzer verfügbar ist.
		 *
		 * @param int $actor Benutzer-ID.
		 * @return bool
		 */
		private function can_create_spaces( int $actor ): bool {
			if ( 0 === $actor ) {
				return false;
			}
			$enabled = (bool) get_option( 'afspaces_enable_space_creation', false );
			$enabled = (bool) apply_filters( 'afspaces_enable_space_creation', $enabled, $actor );
			return $enabled && user_can( $actor, Capabilities::CREATE_SPACE );
		}
	}
}
