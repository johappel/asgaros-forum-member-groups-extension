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
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\MemberService;
use AFSpaces\Application\SpaceRegistrationService;
use AFSpaces\Application\WorkingGroupService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\Requirements;
use AFSpaces\Domain\SpacePolicy;
use AFSpaces\Interface\AppearanceSettingsPage;
use AFSpaces\Interface\ForumNavigation;
use AFSpaces\Interface\FrontendController;
use AFSpaces\Interface\InvitationsView;
use AFSpaces\Interface\MembersView;
use AFSpaces\Interface\MyInvitationsView;
use AFSpaces\Interface\ProfileView;
use AFSpaces\Interface\RestController;
use AFSpaces\Interface\SpacesHubController;
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

			$frontend = new FrontendController( $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups, $space_registration );
			$frontend->init();

			$appearance = new AppearanceSettingsPage();
			$appearance->init();

			// Zentrale Hub-Seite mit Router-Shortcode `[afspaces]`.
			$hub = new SpacesHubController( $frontend, $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups );
			$hub->init();

			// Integration in die Asgaros-Forum-Navigation.
			$navigation = new ForumNavigation( $spaces, $inv_repo, $join_repo );
			$navigation->init();

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

			// REST-API registrieren.
			$rest = new RestController( $spaces, $asgaros, $members, $invites, $join_requests, $invite_links, $working_groups );
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
	}
}
