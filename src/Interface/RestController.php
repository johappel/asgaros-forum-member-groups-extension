<?php
/**
 * REST-API-Controller (versioniert unter /wp-json/afspaces/v1).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Interface;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\MemberService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! class_exists( 'AFSpaces\\Interface\\RestController' ) ) {

	/**
	 * Stellt die REST-Endpunkte für die Mitglieder-Verwaltung bereit.
	 */
	class RestController {

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
		 * Registriert die REST-Routen.
		 *
		 * @return void
		 */
		public function register_routes(): void {
			$namespace = 'afspaces/v1';

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/members',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_members' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => $this->members_args(),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'add_member' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'user_id' => array(
								'type'     => 'integer',
								'required' => true,
								'minimum'  => 1,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/members/(?P<user_id>\d+)',
				array(
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'remove_member' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'user_id' => array(
								'type'     => 'integer',
								'required' => true,
								'minimum'  => 1,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/users/search',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'search_users' ),
						'permission_callback' => array( $this, 'can_search' ),
						'args'                => array(
							'search' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback'  => static fn( $v ) => is_string( $v ) && strlen( $v ) <= 60,
							),
							'page'   => array(
								'type'              => 'integer',
								'default'           => 1,
								'sanitize_callback' => 'absint',
							),
							'per_page' => array(
								'type'              => 'integer',
								'default'           => 20,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);
		}

		/**
		 * Argument-Schema für Mitglieder-Liste.
		 *
		 * @return array<string,mixed>
		 */
		private function members_args(): array {
			return array(
				'page'     => array(
					'type'              => 'integer',
					'default'           => 1,
					'sanitize_callback' => 'absint',
				),
				'per_page' => array(
					'type'              => 'integer',
					'default'           => 20,
					'sanitize_callback' => 'absint',
				),
				'search'   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				),
			);
		}

		/**
		 * Permission-Callback: darf der aktuelle Benutzer den Space verwalten?
		 *
		 * @param WP_REST_Request $request Request.
		 * @return bool|WP_Error
		 */
		public function can_manage( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();

			if ( 0 === $actor ) {
				return new WP_Error(
					'afspaces_rest_unauthorized',
					__( 'Anmeldung erforderlich.', 'afspaces' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return new WP_Error(
					'afspaces_rest_not_found',
					__( 'Raum nicht gefunden.', 'afspaces' ),
					array( 'status' => 404 )
				);
			}

			if ( user_can( $actor, Capabilities::MANAGE_ALL_SPACES ) ) {
				return true;
			}

			if ( ! $this->spaces->is_manager( $space_id, $actor ) ) {
				return new WP_Error(
					'afspaces_rest_forbidden',
					__( 'Keine Berechtigung für diesen Raum.', 'afspaces' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return true;
		}

		/**
		 * Permission-Callback: darf der Benutzer suchen?
		 *
		 * @param WP_REST_Request $request Request.
		 * @return bool|WP_Error
		 */
		public function can_search( WP_REST_Request $request ) {
			$actor = get_current_user_id();
			if ( 0 === $actor ) {
				return new WP_Error(
					'afspaces_rest_unauthorized',
					__( 'Anmeldung erforderlich.', 'afspaces' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			// Nur berechtigte Rollen dürfen suchen (Manager oder globale Capability).
			if ( user_can( $actor, Capabilities::MANAGE_ALL_SPACES )
				|| user_can( $actor, Capabilities::MANAGE_OWN_SPACE ) ) {
				return true;
			}

			return new WP_Error(
				'afspaces_rest_forbidden',
				__( 'Keine Berechtigung zur Suche.', 'afspaces' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		/**
		 * GET /spaces/{id}/members
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_members( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$space    = $this->spaces->get_space( $space_id );

			$group_ids = $this->asgaros->get_forum_group_ids( $space->forum_id );
			if ( empty( $group_ids ) ) {
				return new WP_Error(
					'afspaces_rest_no_group',
					__( 'Keine Zugriffsgruppe konfiguriert.', 'afspaces' ),
					array( 'status' => 409 )
				);
			}

			$result = $this->asgaros->list_group_members(
				(int) $group_ids[0],
				array(
					'page'     => (int) $request['page'],
					'per_page' => (int) $request['per_page'],
					'search'   => (string) $request['search'],
				)
			);

			return new WP_REST_Response(
				array(
					'members'  => $result['members'] ?? array(),
					'total'    => $result['total'] ?? 0,
					'page'     => $result['page'] ?? 1,
					'per_page' => $result['per_page'] ?? 20,
				),
				200
			);
		}

		/**
		 * POST /spaces/{id}/members
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function add_member( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$user_id  = (int) $request['user_id'];
			$actor    = get_current_user_id();

			try {
				$this->members->add_member( $space_id, $actor, $user_id );
			} catch ( DomainException $e ) {
				return new WP_Error(
					'afspaces_rest_add_failed',
					$e->getMessage(),
					array( 'status' => 400 )
				);
			}

			return new WP_REST_Response(
				array(
					'status'  => 'added',
					'user_id' => $user_id,
				),
				201
			);
		}

		/**
		 * DELETE /spaces/{id}/members/{user_id}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function remove_member( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$user_id  = (int) $request['user_id'];
			$actor    = get_current_user_id();

			try {
				$this->members->remove_member( $space_id, $actor, $user_id );
			} catch ( DomainException $e ) {
				return new WP_Error(
					'afspaces_rest_remove_failed',
					$e->getMessage(),
					array( 'status' => 400 )
				);
			}

			return new WP_REST_Response(
				array(
					'status'  => 'removed',
					'user_id' => $user_id,
				),
				200
			);
		}

		/**
		 * GET /users/search — gibt keine E-Mail-Adressen preis.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function search_users( WP_REST_Request $request ): WP_REST_Response {
			$search   = (string) $request['search'];
			$page     = (int) $request['page'];
			$per_page = (int) $request['per_page'];

			$result = $this->members->search_users( $search, $page, $per_page );

			// E-Mail-Adressen explizit entfernen (Datenschutz).
			$safe = array_map(
				static function ( array $u ): array {
					return array(
						'user_id'      => $u['user_id'],
						'display_name' => $u['display_name'],
						'user_login'   => $u['user_login'],
					);
				},
				$result['members'] ?? array()
			);

			return new WP_REST_Response(
				array(
					'members'  => $safe,
					'total'    => $result['total'] ?? 0,
					'page'     => $result['page'] ?? 1,
					'per_page' => $result['per_page'] ?? 20,
				),
				200
			);
		}
	}
}
