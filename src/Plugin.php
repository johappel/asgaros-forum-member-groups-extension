<?php
/**
 * Zentrale Plugin-Klasse.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\JoinRequestRepository;
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Adapters\Database\SearchIndexRepository;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\MemberService;
use AFSpaces\Application\ForumSearchService;
use AFSpaces\Application\HybridSearchService;
use AFSpaces\Application\SearchIndexer;
use AFSpaces\Application\SpaceRegistrationService;
use AFSpaces\Application\SpaceCreationService;
use AFSpaces\Application\SpaceLifecycleService;
use AFSpaces\Application\SpaceModerationService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\Requirements;
use AFSpaces\Domain\SpacePolicy;
use AFSpaces\Interface\AFSpacesSettingsPage;
use AFSpaces\Interface\AppearanceSettingsPage;
use AFSpaces\Interface\ForumNavigation;
use AFSpaces\Interface\ForumModerationControls;
use AFSpaces\Interface\FrontendController;
use AFSpaces\Interface\InvitationsView;
use AFSpaces\Interface\InstallationSettingsPage;
use AFSpaces\Interface\MembersView;
use AFSpaces\Interface\MyInvitationsView;
use AFSpaces\Interface\ProfileView;
use AFSpaces\Interface\RestController;
use AFSpaces\Interface\SearchModal;
use AFSpaces\Interface\SearchSettingsPage;
use AFSpaces\Interface\SearchView;
use AFSpaces\Interface\SpacesHubController;
use AFSpaces\Interface\SpaceCreationSettingsPage;
use AFSpaces\Interface\WorkingGroupSettingsView;
use AFSpaces\Interface\WorkingGroupView;

if ( ! class_exists( 'AFSpaces\\Plugin' ) ) {

	/**
	 * Hauptklasse des Plugins.
	 */
	final class Plugin {

		/**
		 * Singleton-Instanz.
		 *
		 * @var self|null
		 */
		private static ?self $instance = null;

		/**
		 * Requirements-Prüfer.
		 *
		 * @var Requirements
		 */
		private Requirements $requirements;

		/**
		 * Gibt die Singleton-Instanz zurück.
		 *
		 * @return self
		 */
		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Konstruktor (private wegen Singleton).
		 */
		private function __construct() {
			$this->requirements = new Requirements();
		}

		/**
		 * Initialisiert das Plugin.
		 *
		 * @return void
		 */
		public static function init(): void {
			$plugin = self::instance();

			if ( ! $plugin->requirements->check() ) {
				$plugin->requirements->show_admin_notice();
				return;
			}

			$plugin->maybe_upgrade();

			$spaces  = new SpaceRepository();
			$asgaros = new AsgarosAdapter( $plugin->requirements );
			$policy  = new SpacePolicy( $spaces );
			$audit   = new AuditRepository();
			$inv_repo = new InvitationRepository();
			$join_repo = new JoinRequestRepository();
			$link_repo = new \AFSpaces\Adapters\Database\InviteLinkRepository();
			$space_meta = new SpaceMetaRepository();
			$members = new MemberService( $spaces, $asgaros, $policy, $audit );
			$invites = new InvitationService( $spaces, $inv_repo, $asgaros, $policy, $audit );
			$join_requests = new JoinRequestService( $spaces, $join_repo, $asgaros, $policy, $audit );
			$invite_links = new \AFSpaces\Application\InviteLinkService( $spaces, $link_repo, $asgaros, $policy, $audit, $join_requests );
			$working_groups = new WorkingGroupService( $spaces, $space_meta, $asgaros, $policy, $audit );
			$space_registration = new SpaceRegistrationService( $spaces, $asgaros );
			$space_creation = new SpaceCreationService( $spaces, $asgaros, $space_meta, $audit );
			$space_lifecycle = new SpaceLifecycleService( $spaces, $asgaros, $space_meta, $audit );
			$space_moderation = new SpaceModerationService( $spaces, $asgaros, $policy, $audit );
			$forum_search = new ForumSearchService( $asgaros );
			$search_index = new SearchIndexRepository();
			$wp_search = new \AFSpaces\Search\WpPostSearch( \AFSpaces\Search\SearchSettings::wp_post_types() );
			$vector_search = new \AFSpaces\Search\VectorSearch( $search_index, $asgaros );
			$hybrid_search = new HybridSearchService( $forum_search, $wp_search, $vector_search );
			$search_indexer = new SearchIndexer( $asgaros, $search_index );
			$search_indexer->init();

			$frontend = new FrontendController( $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups, $space_registration, $space_creation, $space_lifecycle, $space_moderation );
			$frontend->init();

			$appearance = new AppearanceSettingsPage();
			$appearance->init();

			$installation = new InstallationSettingsPage();
			$installation->init();

			$creation_settings = new SpaceCreationSettingsPage();
			$creation_settings->init();

			$search_settings = new SearchSettingsPage( $search_indexer, $search_index );
			$search_settings->init();

			$settings_page = new AFSpacesSettingsPage(
				$appearance,
				$creation_settings,
				$search_settings,
				$installation
			);
			$settings_page->init();

			$activation_notice = get_option( 'afspaces_activation_notice', array() );
			if ( is_array( $activation_notice ) && ! empty( $activation_notice ) ) {
				add_action(
					'admin_notices',
					static function () use ( $activation_notice ): void {
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}

						$page_id = (int) ( $activation_notice['page_id'] ?? 0 );
						$version = (string) ( $activation_notice['asgaros_version'] ?? '' );
						?>
						<div class="notice notice-success is-dismissible">
							<p><strong><?php echo esc_html__( 'AFSpaces ist eingerichtet.', 'afspaces' ); ?></strong></p>
							<ul>
								<li><?php echo esc_html( $page_id > 0 ? __( 'Arbeitsgruppen-Seite vorhanden.', 'afspaces' ) : __( 'Arbeitsgruppen-Seite konnte noch nicht angelegt werden.', 'afspaces' ) ); ?></li>
								<li><?php echo esc_html( sprintf( __( 'Kompatible Asgaros-Version erkannt: %s.', 'afspaces' ), '' !== $version ? $version : __( 'unbekannt', 'afspaces' ) ) ); ?></li>
								<li><?php echo esc_html__( 'Selbstgründung ist zunächst deaktiviert.', 'afspaces' ); ?></li>
							</ul>
							<p>
								<a class="button button-primary" href="<?php echo esc_url( \AFSpaces\Interface\SpacesUrls::hub_url() ); ?>"><?php echo esc_html__( 'Arbeitsgruppen öffnen', 'afspaces' ); ?></a>
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=afspaces-settings&tab=installation' ) ); ?>"><?php echo esc_html__( 'Einstellungen prüfen', 'afspaces' ); ?></a>
							</p>
						</div>
						<?php
						delete_option( 'afspaces_activation_notice' );
					}
				);
			}

			// Ortsunabhängiges Such-Overlay (Dialog) mit Live-Suche.
			$search_modal = new SearchModal( $asgaros );
			$search_modal->init();

			// Zentrale Hub-Seite mit Router-Shortcode `[afspaces]`.
			$hub = new SpacesHubController( $frontend, $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups, $hybrid_search, $space_creation, $space_lifecycle, $space_moderation );
			$hub->init();

			// Integration in die Asgaros-Forum-Navigation.
			$navigation = new ForumNavigation( $spaces, $inv_repo, $join_repo, $asgaros, $space_meta, $space_creation, $space_lifecycle );
			$navigation->init();

			// Raum-begrenzte Moderationsaktionen direkt im Forum.
			$forum_moderation = new ForumModerationControls( $spaces, $asgaros, $space_moderation );
			$forum_moderation->init();

			// Mitgliederansicht in denselben Shortcode integrieren.
			add_shortcode(
				'afspaces_members',
				static function () use ( $spaces, $asgaros, $members ): string {
					if ( ! isset( $_GET['space_id'] ) ) {
						return '';
					}
					$view = new MembersView( $spaces, $asgaros, $members );
					return $view->render( (int) $_GET['space_id'] );
				}
			);

			add_shortcode(
				'afspaces_invitations',
				static function () use ( $spaces, $asgaros, $invites, $members, $invite_links ): string {
					if ( ! isset( $_GET['space_id'] ) ) {
						return '';
					}
					$view = new InvitationsView( $spaces, $asgaros, $invites, $members, $invite_links );
					return $view->render( (int) $_GET['space_id'] );
				}
			);

			add_shortcode(
				'afspaces_my_invitations',
				static function () use ( $invites, $join_requests, $spaces, $asgaros, $invite_links ): string {
					$view = new MyInvitationsView( $invites, $join_requests, $invite_links, $spaces, $asgaros );
					return $view->render();
				}
			);

			add_shortcode(
				'afspaces_search',
				static function () use ( $hybrid_search, $asgaros ): string {
					$view = new SearchView( $hybrid_search, $asgaros );
					return $view->render();
				}
			);

			add_shortcode(
				'afspaces_profile',
				static function ( $atts = array() ) use ( $spaces, $asgaros, $working_groups ): string {
					$atts = shortcode_atts(
						array(
							'user_id' => 0,
						),
						is_array( $atts ) ? $atts : array(),
						'afspaces_profile'
					);

					$profile_user_id = self::resolve_profile_user_id( (int) $atts['user_id'] );

					$view = new ProfileView( $spaces, $asgaros, $working_groups );
					return $view->render( $profile_user_id, true );
				}
			);

			// REST-API registrieren.
			$rest = new RestController( $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups, $hybrid_search, $space_creation, $space_lifecycle );
			add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

			add_filter( 'wp_privacy_personal_data_exporters', static function ( array $exporters ) use ( $inv_repo ): array {
				$exporters['afspaces-invitations'] = array(
					'exporter_friendly_name' => __( 'AFSpaces Einladungen', 'afspaces' ),
					'callback'               => static function ( string $email ) use ( $inv_repo ): array {
						$user = get_user_by( 'email', $email );
						if ( ! $user ) {
							return array( 'data' => array(), 'done' => true );
						}

						$items = array();
						foreach ( $inv_repo->list_for_invitee( (int) $user->ID ) as $inv ) {
							$items[] = array(
								'group_id'    => 'afspaces_invitations',
								'group_label' => __( 'Forum-Einladungen', 'afspaces' ),
								'item_id'     => 'afspaces_invitation_' . $inv->id,
								'data'        => array(
									array( 'name' => 'space_id', 'value' => (string) $inv->space_id ),
									array( 'name' => 'status', 'value' => $inv->status ),
									array( 'name' => 'expires_at', 'value' => $inv->expires_at ),
									array( 'name' => 'message', 'value' => $inv->message ),
								),
							);
						}

						return array( 'data' => $items, 'done' => true );
					},
				);

				return $exporters;
			} );

			add_filter( 'wp_privacy_personal_data_erasers', static function ( array $erasers ) use ( $inv_repo ): array {
				$erasers['afspaces-invitations'] = array(
					'eraser_friendly_name' => __( 'AFSpaces Einladungen', 'afspaces' ),
					'callback'             => static function ( string $email ) use ( $inv_repo ): array {
						$user = get_user_by( 'email', $email );
						if ( ! $user ) {
							return array(
								'items_removed'  => false,
								'items_retained' => false,
								'messages'       => array(),
								'done'           => true,
							);
						}

						$changed = $inv_repo->erase_personal_messages_for_user( (int) $user->ID );
						return array(
							'items_removed'  => $changed > 0,
							'items_retained' => true,
							'messages'       => array(),
							'done'           => true,
						);
					},
				);

				return $erasers;
			} );

			add_filter( 'wp_privacy_personal_data_exporters', static function ( array $exporters ) use ( $join_repo ): array {
				$exporters['afspaces-join-requests'] = array(
					'exporter_friendly_name' => __( 'AFSpaces Beitrittsanfragen', 'afspaces' ),
					'callback'               => static function ( string $email ) use ( $join_repo ): array {
						$user = get_user_by( 'email', $email );
						if ( ! $user ) {
							return array( 'data' => array(), 'done' => true );
						}

						$requests = array();
						foreach ( array_merge( $join_repo->list_for_requester( (int) $user->ID ), $join_repo->list_for_decider( (int) $user->ID ) ) as $request ) {
							$requests[ $request->id ] = $request;
						}

						$data = array();
						foreach ( $requests as $request ) {
							$item = array(
								array( 'name' => 'request_id', 'value' => (string) $request->id ),
								array( 'name' => 'space_id', 'value' => (string) $request->space_id ),
								array( 'name' => 'status', 'value' => $request->status ),
								array( 'name' => 'created_at', 'value' => $request->created_at ),
							);
							if ( (int) $request->requester_user_id === (int) $user->ID ) {
								$item[] = array( 'name' => 'request_message', 'value' => $request->request_message );
							}
							if ( (int) $request->decider_user_id === (int) $user->ID ) {
								$item[] = array( 'name' => 'decision_message', 'value' => $request->decision_message );
							}
							$data[] = array(
								'group_id'    => 'afspaces_join_requests',
								'group_label' => __( 'AFSpaces Beitrittsanfragen', 'afspaces' ),
								'item_id'     => 'afspaces_join_request_' . $request->id,
								'data'        => $item,
							);
						}

						return array( 'data' => $data, 'done' => true );
					},
				);
				return $exporters;
			} );

			add_filter( 'wp_privacy_personal_data_erasers', static function ( array $erasers ) use ( $join_repo ): array {
				$erasers['afspaces-join-requests'] = array(
					'eraser_friendly_name' => __( 'AFSpaces Beitrittsanfragen', 'afspaces' ),
					'callback'             => static function ( string $email ) use ( $join_repo ): array {
						$user = get_user_by( 'email', $email );
						if ( ! $user ) {
							return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
						}

						$changed = $join_repo->erase_personal_messages_for_user( (int) $user->ID );
						return array(
							'items_removed'  => $changed > 0,
							'items_retained' => true,
							'messages'       => array( __( 'Status, Zeitstempel und IDs bleiben als notwendige Sicherheits- und Nachweisinformationen erhalten.', 'afspaces' ) ),
							'done'           => true,
						);
					},
				);
				return $erasers;
			} );
		}

		/**
		 * Stellt bei Versionswechseln fehlende Strukturen (z. B. Hub-Seite) her.
		 *
		 * @return void
		 */
		private function maybe_upgrade(): void {
			$installed = (string) get_option( 'afspaces_installed_version', '' );
			if ( AFSPACES_VERSION === $installed ) {
				return;
			}

			\AFSpaces\Core\Activator::ensure_hub_page();

			// Neue Strukturen für bestehende Installationen nachziehen.
			$spaces = new SpaceRepository();
			$spaces->install();
			$search_index = new SearchIndexRepository();
			$search_index->install();
			SearchIndexer::schedule();

			update_option( 'afspaces_installed_version', AFSPACES_VERSION );
		}

		/**
		 * Gibt den Requirements-Prüfer zurück.
		 *
		 * @return Requirements
		 */
		public function get_requirements(): Requirements {
			return $this->requirements;
		}

		/**
		 * Ermittelt die anzuzeigende Profil-Benutzer-ID aus dem aktuellen Kontext.
		 *
		 * Deckt gängige Profilsysteme ab (Ultimate Member, BuddyPress,
		 * Autoren-Archiv, Asgaros `?member=`) und fällt sonst auf den
		 * eingeloggten Benutzer zurück. Über den Filter
		 * `afspaces_profile_user_id` kann die Erkennung überschrieben werden.
		 *
		 * @param int $explicit_user_id Optional per Shortcode-Attribut gesetzte ID.
		 * @return int
		 */
		private static function resolve_profile_user_id( int $explicit_user_id = 0 ): int {
			$user_id = 0;

			// 1. Primär: aktuell angezeigtes Profil aus dem Query-Kontext.
			//    Deckt den efabi-`profil`-CPT (post_author = Profilinhaber) sowie
			//    Autoren-Archive und User-Query-Objekte ab. Dies hat Vorrang, damit
			//    der Shortcode auf einer Profilseite immer die richtige Person zeigt.
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Post ) {
				$profile_post_types = (array) apply_filters( 'afspaces_profile_post_types', array( 'profil' ) );
				if ( in_array( $queried->post_type, $profile_post_types, true ) && (int) $queried->post_author > 0 ) {
					$user_id = (int) $queried->post_author;
				}
			} elseif ( $queried instanceof \WP_User ) {
				$user_id = (int) $queried->ID;
			}

			// 2. Explizites Shortcode-Attribut (z. B. dynamischer Token außerhalb von Profilseiten).
			if ( 0 === $user_id && $explicit_user_id > 0 ) {
				$user_id = $explicit_user_id;
			}

			// 3. Query-Parameter (Asgaros: ?member=, generisch: ?user_id=).
			if ( 0 === $user_id && isset( $_GET['member'] ) ) {
				$user_id = (int) $_GET['member'];
			}
			if ( 0 === $user_id && isset( $_GET['user_id'] ) ) {
				$user_id = (int) $_GET['user_id'];
			}

			// 4. Ultimate Member.
			if ( 0 === $user_id && function_exists( 'um_get_requested_user' ) ) {
				$um_id = (int) um_get_requested_user();
				if ( $um_id > 0 ) {
					$user_id = $um_id;
				}
			}

			// 5. BuddyPress / BuddyBoss.
			if ( 0 === $user_id && function_exists( 'bp_displayed_user_id' ) ) {
				$bp_id = (int) bp_displayed_user_id();
				if ( $bp_id > 0 ) {
					$user_id = $bp_id;
				}
			}

			// 6. Autoren-Archiv.
			if ( 0 === $user_id && function_exists( 'is_author' ) && is_author() ) {
				$author_id = (int) get_query_var( 'author' );
				if ( $author_id > 0 ) {
					$user_id = $author_id;
				}
			}

			// 7. Slug aus dem letzten URL-Pfadsegment (z. B. /profil/anastasia-neumann-schneider/).
			if ( 0 === $user_id && isset( $_SERVER['REQUEST_URI'] ) ) {
				$path = wp_parse_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
				if ( is_string( $path ) && '' !== $path ) {
					$segments = array_values( array_filter( explode( '/', $path ) ) );
					$slug     = end( $segments );
					if ( is_string( $slug ) && '' !== $slug ) {
						$user = get_user_by( 'slug', sanitize_title( $slug ) );
						if ( $user instanceof \WP_User ) {
							$user_id = (int) $user->ID;
						}
					}
				}
			}

			// 8. Fallback: eingeloggter Benutzer.
			if ( 0 === $user_id ) {
				$user_id = get_current_user_id();
			}

			/**
			 * Erlaubt die Überschreibung der erkannten Profil-Benutzer-ID.
			 *
			 * @param int $user_id          Erkannte Benutzer-ID.
			 * @param int $explicit_user_id Per Shortcode gesetzte ID (0 = keine).
			 */
			return (int) apply_filters( 'afspaces_profile_user_id', $user_id, $explicit_user_id );
		}
	}
}
