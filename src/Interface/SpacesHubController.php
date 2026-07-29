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
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Application\InviteLinkService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\MemberService;
use AFSpaces\Application\HybridSearchService;
use AFSpaces\Application\SpaceCreationService;
use AFSpaces\Application\SpaceLifecycleService;
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
		private WorkingGroupService $working_groups;
		private HybridSearchService $forum_search;
		private SpaceCreationService $space_creation;
		private SpaceLifecycleService $space_lifecycle;

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
			InviteLinkService $invite_links,
			WorkingGroupService $working_groups,
			HybridSearchService $forum_search,
			SpaceCreationService $space_creation,
			SpaceLifecycleService $space_lifecycle
		) {
			$this->frontend     = $frontend;
			$this->spaces       = $spaces;
			$this->asgaros      = $asgaros;
			$this->members      = $members;
			$this->invitations  = $invitations;
			$this->join_requests = $join_requests;
			$this->invite_links = $invite_links;
			$this->working_groups = $working_groups;
			$this->forum_search = $forum_search;
			$this->space_creation = $space_creation;
			$this->space_lifecycle = $space_lifecycle;
		}

		/**
		 * Registriert Shortcode und Legacy-Weiterleitungen.
		 *
		 * @return void
		 */
		public function init(): void {
			add_shortcode( 'afspaces', array( $this, 'render' ) );
			add_action( 'template_redirect', array( $this, 'redirect_legacy_pages' ) );
			add_action( 'template_redirect', array( $this, 'redirect_forum_search' ) );
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
		 * Ersetzt die eingebaute Asgaros-Suche durch die AFSpaces-Suche.
		 *
		 * Wird die Asgaros-Suchansicht aufgerufen (z. B. über das Suchfeld im
		 * Forum), erfolgt eine Weiterleitung auf die eigene, post-genaue Suche.
		 *
		 * @return void
		 */
		public function redirect_forum_search(): void {
			if ( is_admin() ) {
				return;
			}

			if ( ! $this->asgaros->is_search_request() ) {
				return;
			}

			// Nicht umleiten, wenn die Hub-Seite selbst angezeigt wird (Schleifenschutz).
			$hub_page_id = SpacesUrls::hub_page_id();
			if ( 0 === $hub_page_id || (int) get_queried_object_id() === $hub_page_id ) {
				return;
			}

			$args     = array();
			$keywords = isset( $_GET['keywords'] ) ? sanitize_text_field( wp_unslash( $_GET['keywords'] ) ) : '';
			if ( '' !== $keywords ) {
				$args[ SearchView::PARAM_QUERY ] = $keywords;
			}

			wp_safe_redirect( SpacesUrls::hub_url( SpacesUrls::VIEW_SEARCH, $args ) );
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
			<?php echo $this->render_navigation( $view, $space_id, $actor ); ?>
			<?php echo $this->render_breadcrumb( $view, $space_id ); ?>
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

				case SpacesUrls::VIEW_GROUP:
					$group_view = new WorkingGroupView( $this->spaces, $this->asgaros, $this->invitations, $this->join_requests, $this->working_groups );
					return $group_view->render( $space_id );

				case SpacesUrls::VIEW_PROFILE:
					$profile_user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
					$profile_view = new ProfileView( $this->spaces, $this->asgaros, $this->working_groups );
					return $profile_view->render( $profile_user_id );

				case SpacesUrls::VIEW_SETTINGS:
					$settings_view = new WorkingGroupSettingsView( $this->spaces, $this->asgaros, $this->working_groups );
					return $settings_view->render( $space_id );

				case SpacesUrls::VIEW_MY_INVITATIONS:
					$mine_view = new MyInvitationsView( $this->invitations, $this->join_requests, $this->invite_links, $this->spaces, $this->asgaros );
					return $mine_view->render();

				case SpacesUrls::VIEW_DISCOVER:
					$discover_view = new DiscoverView( $this->spaces, $this->asgaros, $this->invitations, $this->join_requests, $this->working_groups );
					return $discover_view->render();

				case SpacesUrls::VIEW_SEARCH:
					$search_view = new SearchView( $this->forum_search, $this->asgaros );
					return $search_view->render();

				case SpacesUrls::VIEW_CREATE:
					$create_view = new CreateSpaceView( $this->space_creation );
					return $create_view->render();

				case SpacesUrls::VIEW_APPROVALS:
					return $this->render_approvals();

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
				$items[] = sprintf( '<span aria-current="page">%s</span>', esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::PLURAL ) ) );
			} else {
				$items[] = sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ) ),
					esc_html( WorkingGroupTerminology::label( WorkingGroupTerminology::PLURAL ) )
				);
				$items[] = sprintf( '<span aria-current="page">%s</span>', esc_html( $this->view_label( $view, $space_id ) ) );
			}

			return sprintf(
				'<nav id="forum-breadcrumbs" class="afspaces-breadcrumb" aria-label="%1$s">%2$s</nav>',
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

			$room_context_active = $space_id > 0
				&& $this->can_manage_space( $space_id, $actor )
				&& in_array( $view, array( SpacesUrls::VIEW_MEMBERS, SpacesUrls::VIEW_INVITATIONS, SpacesUrls::VIEW_JOIN_REQUESTS, SpacesUrls::VIEW_SETTINGS ), true );

			$tabs = array();

			$forum_home = home_url( '/forum/' );
			$forum_home = (string) apply_filters( 'afspaces_forum_home_url', $forum_home );
			$tabs[] = array(
				'view'   => 'forum',
				'label'  => __( 'Forum', 'afspaces' ),
				'url'    => $forum_home,
				'active' => false,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_DASHBOARD,
				'label'  => WorkingGroupTerminology::label( WorkingGroupTerminology::MY_PLURAL ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_DASHBOARD ),
				'active' => in_array( $view, array( SpacesUrls::VIEW_DASHBOARD, SpacesUrls::VIEW_CREATE ), true ) || $room_context_active,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_MY_INVITATIONS,
				'label'  => __( 'Meine Einladungen', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_MY_INVITATIONS ),
				'active' => SpacesUrls::VIEW_MY_INVITATIONS === $view,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_PROFILE,
				'label'  => __( 'Mein Profil', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_PROFILE ),
				'active' => SpacesUrls::VIEW_PROFILE === $view,
			);

			$tabs[] = array(
				'view'   => SpacesUrls::VIEW_DISCOVER,
				'label'  => __( 'Entdecken', 'afspaces' ),
				'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_DISCOVER ),
				'active' => in_array( $view, array( SpacesUrls::VIEW_DISCOVER, SpacesUrls::VIEW_GROUP ), true ),
			);

			// „Freigaben" ist eine administrative Aufgabe und erscheint daher nur
			// für berechtigte Personen (Administratoren/Moderatoren) im Topmenü.
			// „Arbeitsgruppe gründen" ist hingegen ein Aktionsbutton im Dashboard.
			if ( $this->can_moderate_spaces( $actor ) ) {
				$tabs[] = array(
					'view'   => SpacesUrls::VIEW_APPROVALS,
					'label'  => __( 'Freigaben', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_APPROVALS ),
					'active' => SpacesUrls::VIEW_APPROVALS === $view,
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

// Such-Button öffnet jetzt das Modal mit Forum-Scope statt direkter Seite.
		$search_button = sprintf(
			'<button type="button" class="afspaces-header-search-btn" data-afspaces-search-open data-afspaces-search-scope="forum" aria-label="%s"><span class="search-icon fas fa-search" aria-hidden="true"></span></button>',
			esc_attr__( 'Forum durchsuchen', 'afspaces' )
		);

			return sprintf(
				'<div id="forum-header" class="afspaces-forum-header"><nav id="forum-navigation" class="afspaces-hub-nav" aria-label="%1$s"><ul>%2$s</ul></nav><div id="forum-search" class="afspaces-forum-search">%3$s</div><div class="clear"></div></div>',
				esc_attr__( 'Arbeitsgruppenverwaltung', 'afspaces' ),
				$items,
				$search_button
			);
		}

		/**
		 * Rendert arbeitsgruppenbezogene Verwaltungstabs unter dem Gruppentitel.
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
				$room_name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space_id );
			}

			$tabs = array(
				array(
					'view'   => SpacesUrls::VIEW_SETTINGS,
					'label'  => __( 'Details', 'afspaces' ),
					'url'    => SpacesUrls::hub_url( SpacesUrls::VIEW_SETTINGS, array( 'space_id' => $space_id ) ),
					'active' => SpacesUrls::VIEW_SETTINGS === $view,
				),
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
				esc_html( WorkingGroupTerminology::manage_context( $room_name ) ),
				esc_attr__( 'Arbeitsgruppenbezogene Verwaltung', 'afspaces' ),
				$items
			);
		}

		/**
		 * Rendert die Freigabeliste anhängiger Arbeitsgruppen (M4.4).
		 *
		 * @return string
		 */
		private function render_approvals(): string {
			$actor = get_current_user_id();
			if ( ! $this->can_moderate_spaces( $actor ) ) {
				return sprintf(
					'<p class="afspaces-notice" role="status">%s</p>',
					esc_html__( 'Du darfst keine Arbeitsgruppen freigeben.', 'afspaces' )
				);
			}

			try {
				$pending = $this->space_lifecycle->list_pending( $actor );
			} catch ( \AFSpaces\Core\DomainException $e ) {
				return sprintf( '<p class="afspaces-notice" role="alert">%s</p>', esc_html( $e->getMessage() ) );
			}

			ob_start();
			?>
			<section class="afspaces-approvals" aria-labelledby="afspaces-approvals-heading">
				<h2 id="afspaces-approvals-heading"><?php echo esc_html__( 'Arbeitsgruppen freigeben', 'afspaces' ); ?></h2>
				<?php if ( empty( $pending ) ) : ?>
					<p role="status"><?php echo esc_html__( 'Derzeit warten keine Arbeitsgruppen auf Freigabe.', 'afspaces' ); ?></p>
				<?php else : ?>
					<ul class="afspaces-approvals-list">
						<?php foreach ( $pending as $space ) : ?>
							<?php
							$forum      = $this->asgaros->get_forum( $space->forum_id );
							$forum_name = trim( (string) ( $forum['name'] ?? '' ) );
							if ( '' === $forum_name ) {
								$forum_name = sprintf( __( 'Arbeitsgruppe #%d', 'afspaces' ), $space->id );
							}
							$owner = get_userdata( $space->owner_user_id );
							?>
							<li class="afspaces-approval-item content-container">
								<h3><?php echo esc_html( $forum_name ); ?></h3>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: Name, 2: Sichtbarkeit */
											__( 'Angefragt von %1$s · Sichtbarkeit: %2$s', 'afspaces' ),
											$owner ? $owner->display_name : (string) $space->owner_user_id,
											CreateSpaceView::visibility_label( $space->visibility )
										)
									);
									?>
								</p>
								<div class="afspaces-approval-actions">
									<form method="post">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="approve_space" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
										<button type="submit" class="afspaces-button"><?php echo esc_html__( 'Freigeben', 'afspaces' ); ?></button>
									</form>
									<form method="post" class="afspaces-reject-form">
										<?php echo wp_nonce_field( 'afspaces_member_action', '_wpnonce', true, false ); ?>
										<input type="hidden" name="afspaces_action" value="reject_space" />
										<input type="hidden" name="space_id" value="<?php echo esc_attr( (string) $space->id ); ?>" />
										<label for="afspaces-reject-<?php echo esc_attr( (string) $space->id ); ?>"><?php echo esc_html__( 'Begründung der Ablehnung', 'afspaces' ); ?></label>
										<textarea id="afspaces-reject-<?php echo esc_attr( (string) $space->id ); ?>" name="rejection_reason" rows="2"></textarea>
										<button type="submit" class="afspaces-button afspaces-button-danger"><?php echo esc_html__( 'Ablehnen', 'afspaces' ); ?></button>
									</form>
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
					case SpacesUrls::VIEW_GROUP:
						return __( 'Arbeitsgruppe', 'afspaces' );
					case SpacesUrls::VIEW_PROFILE:
						return __( 'Arbeitsgruppenprofil', 'afspaces' );
					case SpacesUrls::VIEW_SETTINGS:
						return __( 'Arbeitsgruppen-Details', 'afspaces' );
				case SpacesUrls::VIEW_MY_INVITATIONS:
					return __( 'Meine Einladungen', 'afspaces' );
				case SpacesUrls::VIEW_JOIN_REQUESTS:
					return __( 'Beitrittsanfragen', 'afspaces' );
				case SpacesUrls::VIEW_DISCOVER:
					return WorkingGroupTerminology::label( WorkingGroupTerminology::DISCOVER );
				case SpacesUrls::VIEW_CREATE:
					return __( 'Arbeitsgruppe gründen', 'afspaces' );
				case SpacesUrls::VIEW_APPROVALS:
					return __( 'Freigaben', 'afspaces' );
				default:
					return WorkingGroupTerminology::label( WorkingGroupTerminology::MY_PLURAL );
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
		 * Prüft, ob der Benutzer Arbeitsgruppen freigeben darf.
		 *
		 * @param int $actor Benutzer-ID.
		 * @return bool
		 */
		private function can_moderate_spaces( int $actor ): bool {
			if ( 0 === $actor ) {
				return false;
			}
			return user_can( $actor, Capabilities::MANAGE_ALL_SPACES )
				|| user_can( $actor, Capabilities::MODERATE_SPACE );
		}
	}
}
