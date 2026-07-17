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
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\Requirements;
use AFSpaces\Domain\SpacePolicy;
use AFSpaces\Interface\FrontendController;
use AFSpaces\Interface\InvitationsView;
use AFSpaces\Interface\MembersView;
use AFSpaces\Interface\MyInvitationsView;
use AFSpaces\Interface\RestController;

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

			$spaces  = new SpaceRepository();
			$asgaros = new AsgarosAdapter( $plugin->requirements );
			$policy  = new SpacePolicy( $spaces );
			$audit   = new AuditRepository();
			$inv_repo = new InvitationRepository();
			$members = new MemberService( $spaces, $asgaros, $policy, $audit );
			$invites = new InvitationService( $spaces, $inv_repo, $asgaros, $policy, $audit );

			$frontend = new FrontendController( $spaces, $asgaros, $members, $invites );
			$frontend->init();

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
				static function () use ( $spaces, $invites, $members ): string {
					if ( ! isset( $_GET['space_id'] ) ) {
						return '';
					}
					$view = new InvitationsView( $spaces, $invites, $members );
					return $view->render( (int) $_GET['space_id'] );
				}
			);

			add_shortcode(
				'afspaces_my_invitations',
				static function () use ( $invites, $spaces, $asgaros ): string {
					$view = new MyInvitationsView( $invites, $spaces, $asgaros );
					return $view->render();
				}
			);

			// REST-API registrieren.
			$rest = new RestController( $spaces, $asgaros, $members, $invites );
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
		 * Gibt den Requirements-Prüfer zurück.
		 *
		 * @return Requirements
		 */
		public function get_requirements(): Requirements {
			return $this->requirements;
		}
	}
}
