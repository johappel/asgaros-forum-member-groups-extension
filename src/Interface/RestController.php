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
use AFSpaces\Application\InviteLinkService;
use AFSpaces\Application\InvitationService;
use AFSpaces\Application\JoinRequestService;
use AFSpaces\Application\MemberService;
use AFSpaces\Application\WorkingGroupService;
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
		 * @var InvitationService
		 */
		private InvitationService $invitations;

		/**
		 * @var InviteLinkService
		 */
		private InviteLinkService $invite_links;

		/**
		 * @var JoinRequestService
		 */
		private JoinRequestService $join_requests;

		/**
		 * @var WorkingGroupService
		 */
		private WorkingGroupService $working_groups;

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository         $spaces  Space-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param MemberService           $members Mitglieder-Service.
		 * @param InvitationService       $invitations Einladungs-Service.
		 */
		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			MemberService $members,
			InvitationService $invitations,
			JoinRequestService $join_requests,
			InviteLinkService $invite_links,
			WorkingGroupService $working_groups
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->members = $members;
			$this->invitations = $invitations;
			$this->join_requests = $join_requests;
			$this->invite_links = $invite_links;
			$this->working_groups = $working_groups;
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
				'/spaces/discover',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_discover_spaces' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
						'args'                => array(
							'search' => array(
								'type' => 'string',
								'required' => false,
								'sanitize_callback' => 'sanitize_text_field',
							),
							'topic_id' => array(
								'type' => 'integer',
								'required' => false,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/working-group',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_working_group' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'update_working_group' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/profiles/(?P<user_id>\d+)/working-groups',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_profile_working_groups' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/join-requests',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_join_requests' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_join_request' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
						'args'                => array(
							'request_message' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_textarea_field',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/join-requests/(?P<request_id>\d+)/approve',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'approve_join_request' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'decision_message' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_textarea_field',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/join-requests/(?P<request_id>\d+)/reject',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'reject_join_request' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'decision_message' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_textarea_field',
							),
						),
					),
				)
			);

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

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/invitations',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_invitations' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'status' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_text_field',
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_invitation' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'invitee_user_id' => array(
								'type'              => 'integer',
								'required'          => true,
								'minimum'           => 1,
								'sanitize_callback' => 'absint',
							),
							'message' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_textarea_field',
							),
							'expires_in_days' => array(
								'type'              => 'integer',
								'required'          => false,
								'default'           => 7,
								'sanitize_callback' => 'absint',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/invite-links',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_invite_links' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_invite_link' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'approval_mode' => array(
								'type'              => 'string',
								'required'          => false,
								'sanitize_callback' => 'sanitize_text_field',
							),
							'max_uses' => array(
								'type'              => 'integer',
								'required'          => false,
								'default'           => 1,
								'sanitize_callback' => 'absint',
							),
							'expires_in_days' => array(
								'type'              => 'integer',
								'required'          => false,
								'default'           => 7,
								'sanitize_callback' => 'absint',
							),
							'allow_registration' => array(
								'type'              => 'boolean',
								'required'          => false,
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/invite-links/(?P<link_id>\d+)',
				array(
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'revoke_invite_link' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'shorten_invite_link' ),
						'permission_callback' => array( $this, 'can_manage' ),
						'args'                => array(
							'expires_at' => array(
								'type'              => 'string',
								'required'          => true,
								'sanitize_callback' => 'sanitize_text_field',
							),
						),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/invitations/(?P<invitation_id>\d+)',
				array(
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'revoke_invitation' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/spaces/(?P<space_id>\d+)/invitations/(?P<invitation_id>\d+)/resend',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'resend_invitation' ),
						'permission_callback' => array( $this, 'can_manage' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/invitations/(?P<token>[A-Za-z0-9\-_]+)/accept',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'accept_invitation' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/invitations/(?P<token>[A-Za-z0-9\-_]+)/decline',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'decline_invitation' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
					),
				)
			);

			register_rest_route(
				$namespace,
				'/invite-links/preview/(?P<token>[A-Za-z0-9\-_]+)',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'preview_invite_link' ),
						'permission_callback' => '__return_true',
					),
				)
			);

			register_rest_route(
				$namespace,
				'/invite-links/use/(?P<token>[A-Za-z0-9\-_]+)',
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'use_invite_link' ),
						'permission_callback' => array( $this, 'can_respond_to_invitation' ),
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
		 * Permission-Callback für Accept/Decline-Endpunkte.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return bool|WP_Error
		 */
		public function can_respond_to_invitation( WP_REST_Request $request ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error(
					'afspaces_rest_unauthorized',
					__( 'Anmeldung erforderlich.', 'afspaces' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return true;
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

		/**
		 * GET /spaces/{id}/invitations
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_invitations( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();
			$status   = isset( $request['status'] ) ? (string) $request['status'] : null;

			try {
				$list = $this->invitations->list_space_invitations( $space_id, $actor, $status );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_list_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			$items = array_map(
				static function ( $inv ): array {
					return array(
						'id'              => $inv->id,
						'space_id'        => $inv->space_id,
						'inviter_user_id' => $inv->inviter_user_id,
						'invitee_user_id' => $inv->invitee_user_id,
						'status'          => $inv->effective_status(),
						'expires_at'      => $inv->expires_at,
						'message'         => $inv->message,
						'send_count'      => $inv->send_count,
					);
				},
				$list
			);

			return new WP_REST_Response( array( 'invitations' => $items ), 200 );
		}

		/**
		 * GET /spaces/{id}/invite-links
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_invite_links( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();

			try {
				$list = $this->invite_links->list_links( $space_id, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_list_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			$items = array_map(
				static function ( $link ): array {
					return array(
						'id'                 => $link->id,
						'space_id'           => $link->space_id,
						'creator_user_id'    => $link->creator_user_id,
						'status'             => $link->effective_status(),
						'approval_mode'      => $link->approval_mode,
						'max_uses'           => $link->max_uses,
						'use_count'          => $link->use_count,
						'allow_registration' => $link->allows_registration(),
						'expires_at'         => $link->expires_at,
					);
				},
				$list
			);

			return new WP_REST_Response( array( 'invite_links' => $items ), 200 );
		}

		/**
		 * POST /spaces/{id}/invite-links
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_invite_link( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();

			try {
				$result = $this->invite_links->create_link(
					$space_id,
					$actor,
					array(
						'approval_mode'      => (string) ( $request['approval_mode'] ?? '' ),
						'max_uses'           => (int) ( $request['max_uses'] ?? 1 ),
						'expires_in_days'    => (int) ( $request['expires_in_days'] ?? 7 ),
						'allow_registration' => (bool) ( $request['allow_registration'] ?? false ),
					)
				);
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_create_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			/** @var \AFSpaces\Domain\InviteLink $link */
			$link = $result['link'];

			return new WP_REST_Response(
				array(
					'id'                 => $link->id,
					'status'             => $link->effective_status(),
					'approval_mode'      => $link->approval_mode,
					'max_uses'           => $link->max_uses,
					'use_count'          => $link->use_count,
					'allow_registration' => $link->allows_registration(),
					'expires_at'         => $link->expires_at,
					'url'                => $result['url'],
				),
				201
			);
		}

		/**
		 * DELETE /spaces/{id}/invite-links/{link_id}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function revoke_invite_link( WP_REST_Request $request ) {
			$link_id = (int) $request['link_id'];
			$actor   = get_current_user_id();

			try {
				$this->invite_links->revoke_link( $link_id, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_revoke_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => 'revoked' ), 200 );
		}

		/**
		 * PATCH /spaces/{id}/invite-links/{link_id}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function shorten_invite_link( WP_REST_Request $request ) {
			$link_id = (int) $request['link_id'];
			$actor   = get_current_user_id();

			try {
				$link = $this->invite_links->shorten_expiry( $link_id, $actor, (string) $request['expires_at'] );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_update_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => $link->effective_status(), 'expires_at' => $link->expires_at ), 200 );
		}

		/**
		 * POST /spaces/{id}/invitations
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_invitation( WP_REST_Request $request ) {
			$space_id        = (int) $request['space_id'];
			$invitee_user_id = (int) $request['invitee_user_id'];
			$message         = (string) ( $request['message'] ?? '' );
			$expires_days    = (int) ( $request['expires_in_days'] ?? 7 );
			$actor           = get_current_user_id();

			try {
				$inv = $this->invitations->create_invitation( $space_id, $actor, $invitee_user_id, $message, $expires_days );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_create_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response(
				array(
					'id'         => $inv->id,
					'status'     => $inv->effective_status(),
					'expires_at' => $inv->expires_at,
				),
				201
			);
		}

		/**
		 * DELETE /spaces/{id}/invitations/{invitation_id}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function revoke_invitation( WP_REST_Request $request ) {
			$invitation_id = (int) $request['invitation_id'];
			$actor         = get_current_user_id();

			try {
				$this->invitations->revoke_invitation( $invitation_id, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_revoke_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => 'revoked' ), 200 );
		}

		/**
		 * POST /spaces/{id}/invitations/{invitation_id}/resend
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function resend_invitation( WP_REST_Request $request ) {
			$invitation_id = (int) $request['invitation_id'];
			$actor         = get_current_user_id();

			try {
				$this->invitations->resend_invitation( $invitation_id, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_resend_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => 'resent' ), 200 );
		}

		/**
		 * POST /invitations/{token}/accept
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function accept_invitation( WP_REST_Request $request ) {
			$token = (string) $request['token'];
			$actor = get_current_user_id();

			try {
				$inv = $this->invitations->accept_invitation_by_token( $token, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_accept_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => $inv->status, 'space_id' => $inv->space_id ), 200 );
		}

		/**
		 * POST /invitations/{token}/decline
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function decline_invitation( WP_REST_Request $request ) {
			$token = (string) $request['token'];
			$actor = get_current_user_id();

			try {
				$inv = $this->invitations->decline_invitation_by_token( $token, $actor );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invitation_decline_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'status' => $inv->status, 'space_id' => $inv->space_id ), 200 );
		}

		/**
		 * GET /invite-links/preview/{token}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function preview_invite_link( WP_REST_Request $request ) {
			try {
				$preview = $this->invite_links->preview_link( (string) $request['token'], get_current_user_id() );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_preview_failed', $e->getMessage(), array( 'status' => $this->status_for_domain_error( $e ) ) );
			}

			return new WP_REST_Response(
				array(
					'forum_name'       => $preview['forum_name'],
					'state'            => $preview['state'],
					'can_register'     => $preview['can_register'],
					'action_label'     => $preview['action_label'],
					'status_message'   => $preview['status_message'],
				),
				200
			);
		}

		/**
		 * POST /invite-links/use/{token}
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function use_invite_link( WP_REST_Request $request ) {
			try {
				$result = $this->invite_links->use_link( (string) $request['token'], get_current_user_id() );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_invite_link_use_failed', $e->getMessage(), array( 'status' => $this->status_for_domain_error( $e ) ) );
			}

			return new WP_REST_Response( $result, 200 );
		}

		/**
		 * GET /spaces/discover
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_discover_spaces( WP_REST_Request $request ): WP_REST_Response {
			$actor = get_current_user_id();
			$spaces = $this->spaces->list_spaces();
			$search = isset( $request['search'] ) ? strtolower( (string) $request['search'] ) : '';
			$topic_id = isset( $request['topic_id'] ) ? (int) $request['topic_id'] : 0;
			$invitations = $this->invitations->list_my_invitations( $actor );
			$requests = $this->join_requests->list_my_requests( $actor );
			$invitation_by_space = array();
			$requests_by_space = array();
			foreach ( $invitations as $invitation ) {
				$invitation_by_space[ (int) $invitation->space_id ] = $invitation;
			}
			foreach ( $requests as $request_item ) {
				$requests_by_space[ (int) $request_item->space_id ] = $request_item;
			}
			$items = array();

			foreach ( $spaces as $space ) {
				if ( 'active' !== $space->status ) {
					continue;
				}

				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( ! $forum ) {
					continue;
				}

				$meta = $this->working_groups->get_metadata( $space->id );
				$is_manager = $this->spaces->is_manager( $space->id, $actor ) || user_can( $actor, Capabilities::MANAGE_ALL_SPACES );
				$is_member = $this->asgaros->is_user_in_group( $actor, $space->primary_group_id );
				if ( ! $this->working_groups->can_view_group( $meta, $is_member, $is_manager ) ) {
					continue;
				}

				if ( '' !== $search ) {
					$haystack = strtolower( implode( ' ', array( (string) ( $forum['name'] ?? '' ), $meta->description, $meta->contact_text ) ) );
					if ( false === strpos( $haystack, $search ) ) {
						continue;
					}
				}

				if ( $topic_id > 0 && ! in_array( $topic_id, $meta->topic_ids, true ) ) {
					continue;
				}

				$current_invitation = $invitation_by_space[ $space->id ] ?? null;
				$current_request = $requests_by_space[ $space->id ] ?? null;
				$can_request = $this->working_groups->can_request_join(
					$meta,
					$is_member,
					$is_manager,
					null !== $current_request && 'pending' === $current_request->status,
					null !== $current_invitation && 'pending' === $current_invitation->effective_status()
				);

				$items[] = array(
					'space_id'                => $space->id,
					'forum_name'              => (string) ( $forum['name'] ?? sprintf( 'Arbeitsgruppe #%d', $space->id ) ),
					'description'             => '' !== $meta->description ? $meta->description : (string) ( $forum['description'] ?? '' ),
					'accent_color'            => $meta->accent_color,
					'icon'                    => $meta->icon,
					'contact_text'            => $meta->contact_text,
					'directory_visibility'    => $meta->directory_visibility,
					'join_policy'             => $meta->join_policy,
					'join_requests_enabled'   => $meta->join_requests_enabled,
					'topic_names'             => $this->working_groups->topic_names( $meta ),
					'responsibles'            => $this->working_groups->list_responsibles( $space->id ),
					'current_user_status'     => $this->working_group_state_label( $is_manager, $is_member, $current_request, $current_invitation ),
					'can_request_join'        => $can_request,
				);
			}

			return new WP_REST_Response( array( 'spaces' => $items ), 200 );
		}

		/**
		 * GET /spaces/{space_id}/working-group
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_working_group( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor = get_current_user_id();
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return new WP_Error( 'afspaces_rest_working_group_not_found', __( 'Arbeitsgruppe nicht gefunden.', 'afspaces' ), array( 'status' => 404 ) );
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			if ( ! $forum ) {
				return new WP_Error( 'afspaces_rest_working_group_forum_missing', __( 'Das zugehörige Forum ist nicht verfügbar.', 'afspaces' ), array( 'status' => 404 ) );
			}

			$meta = $this->working_groups->get_metadata( $space_id );
			$is_manager = $this->spaces->is_manager( $space_id, $actor ) || user_can( $actor, Capabilities::MANAGE_ALL_SPACES );
			$is_member = $this->asgaros->is_user_in_group( $actor, $space->primary_group_id );
			if ( ! $this->working_groups->can_view_group( $meta, $is_member, $is_manager ) ) {
				return new WP_Error( 'afspaces_rest_working_group_forbidden', __( 'Diese Arbeitsgruppe ist für dich nicht sichtbar.', 'afspaces' ), array( 'status' => 403 ) );
			}

			$invitations = $this->invitations->list_my_invitations( $actor );
			$requests = $this->join_requests->list_my_requests( $actor );
			$current_invitation = $this->first_matching_space_item( $invitations, $space_id );
			$current_request = $this->first_matching_space_item( $requests, $space_id );
			$can_request = $this->working_groups->can_request_join(
				$meta,
				$is_member,
				$is_manager,
				null !== $current_request && 'pending' === $current_request->status,
				null !== $current_invitation && 'pending' === $current_invitation->effective_status()
			);

			return new WP_REST_Response(
				array(
					'space_id'              => $space_id,
					'forum_name'            => (string) ( $forum['name'] ?? sprintf( 'Arbeitsgruppe #%d', $space_id ) ),
					'description'           => '' !== $meta->description ? $meta->description : (string) ( $forum['description'] ?? '' ),
					'accent_color'          => $meta->accent_color,
					'icon'                  => $meta->icon,
					'contact_text'          => $meta->contact_text,
					'directory_visibility'  => $meta->directory_visibility,
					'join_policy'           => $meta->join_policy,
					'join_requests_enabled' => $meta->join_requests_enabled,
					'topic_names'           => $this->working_groups->topic_names( $meta ),
					'responsibles'          => $this->working_groups->list_responsibles( $space_id ),
					'current_user_status'   => $this->working_group_state_label( $is_manager, $is_member, $current_request, $current_invitation ),
					'can_request_join'      => $can_request,
				),
				200
			);
		}

		/**
		 * PATCH /spaces/{space_id}/working-group
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function update_working_group( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor = get_current_user_id();

			try {
				$meta = $this->working_groups->save_metadata( $space_id, $actor, $request->get_params() );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_working_group_update_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( $meta->to_array(), 200 );
		}

		/**
		 * GET /profiles/{user_id}/working-groups
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_profile_working_groups( WP_REST_Request $request ) {
			$viewer = get_current_user_id();
			$profile_user_id = (int) $request['user_id'];
			$user = get_userdata( $profile_user_id );
			if ( ! $user ) {
				return new WP_Error( 'afspaces_rest_profile_not_found', __( 'Profil nicht gefunden.', 'afspaces' ), array( 'status' => 404 ) );
			}

			$items = array();
			foreach ( $this->spaces->list_spaces() as $space ) {
				$forum = $this->asgaros->get_forum( $space->forum_id );
				if ( ! $forum ) {
					continue;
				}

				$is_profile_manager = $this->spaces->is_manager( $space->id, $profile_user_id );
				$is_profile_member = $this->asgaros->is_user_in_group( $profile_user_id, $space->primary_group_id );
				if ( ! $is_profile_manager && ! $is_profile_member ) {
					continue;
				}

				$meta = $this->working_groups->get_metadata( $space->id );
				$viewer_is_manager = $this->spaces->is_manager( $space->id, $viewer ) || user_can( $viewer, Capabilities::MANAGE_ALL_SPACES );
				$viewer_is_member = $this->asgaros->is_user_in_group( $viewer, $space->primary_group_id );
				if ( ! $this->working_groups->can_view_group( $meta, $viewer_is_member, $viewer_is_manager, $viewer === $profile_user_id ) ) {
					continue;
				}

				$items[] = array(
					'space_id'     => $space->id,
					'forum_name'   => (string) ( $forum['name'] ?? sprintf( 'Arbeitsgruppe #%d', $space->id ) ),
					'description'  => '' !== $meta->description ? $meta->description : (string) ( $forum['description'] ?? '' ),
					'role'         => $is_profile_manager ? __( 'Arbeitsgruppenverantwortlich', 'afspaces' ) : __( 'Mitglied', 'afspaces' ),
					'topic_names'  => $this->working_groups->topic_names( $meta ),
				);
			}

			return new WP_REST_Response( array( 'user_id' => $profile_user_id, 'working_groups' => $items ), 200 );
		}

		/**
		 * GET /spaces/{id}/join-requests
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_join_requests( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();

			try {
				$list = $this->join_requests->list_space_requests( $space_id, $actor, null );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_join_request_list_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			$items = array_map(
				static function ( $request_item ): array {
					return array(
						'id'               => $request_item->id,
						'space_id'         => $request_item->space_id,
						'requester_user_id' => $request_item->requester_user_id,
						'request_message'  => $request_item->request_message,
						'status'           => $request_item->status,
						'decider_user_id'  => $request_item->decider_user_id,
						'decision_message' => $request_item->decision_message,
					);
				},
				$list
			);

			return new WP_REST_Response( array( 'join_requests' => $items ), 200 );
		}

		/**
		 * POST /spaces/{id}/join-requests
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_join_request( WP_REST_Request $request ) {
			$space_id = (int) $request['space_id'];
			$actor    = get_current_user_id();
			$message  = (string) ( $request['request_message'] ?? '' );

			try {
				$join_request = $this->join_requests->create_request( $space_id, $actor, $message );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_join_request_create_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response(
				array(
					'id'               => $join_request->id,
					'space_id'         => $join_request->space_id,
					'requester_user_id' => $join_request->requester_user_id,
					'status'           => $join_request->status,
				),
				201
			);
		}

		/**
		 * POST /spaces/{space_id}/join-requests/{request_id}/approve
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function approve_join_request( WP_REST_Request $request ) {
			$request_id = (int) $request['request_id'];
			$actor      = get_current_user_id();
			$message    = (string) ( $request['decision_message'] ?? '' );

			try {
				$join_request = $this->join_requests->approve_request( $request_id, $actor, $message );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_join_request_approve_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'id' => $join_request->id, 'status' => $join_request->status ), 200 );
		}

		/**
		 * POST /spaces/{space_id}/join-requests/{request_id}/reject
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function reject_join_request( WP_REST_Request $request ) {
			$request_id = (int) $request['request_id'];
			$actor      = get_current_user_id();
			$message    = (string) ( $request['decision_message'] ?? '' );

			try {
				$join_request = $this->join_requests->reject_request( $request_id, $actor, $message );
			} catch ( DomainException $e ) {
				return new WP_Error( 'afspaces_rest_join_request_reject_failed', $e->getMessage(), array( 'status' => 400 ) );
			}

			return new WP_REST_Response( array( 'id' => $join_request->id, 'status' => $join_request->status ), 200 );
		}

		/**
		 * @param array<int,mixed> $items Items.
		 * @param int $space_id Space-ID.
		 * @return mixed|null
		 */
		private function first_matching_space_item( array $items, int $space_id ) {
			foreach ( $items as $item ) {
				if ( $space_id === (int) $item->space_id ) {
					return $item;
				}
			}

			return null;
		}

		/**
		 * @param bool $is_manager Verantwortlich?
		 * @param bool $is_member Mitglied?
		 * @param mixed $request Antrag.
		 * @param mixed $invitation Einladung.
		 * @return string
		 */
		private function working_group_state_label( bool $is_manager, bool $is_member, $request, $invitation ): string {
			if ( $is_manager ) {
				return __( 'Arbeitsgruppenverantwortlich', 'afspaces' );
			}

			if ( $is_member ) {
				return __( 'Mitglied', 'afspaces' );
			}

			if ( null !== $invitation && 'pending' === $invitation->effective_status() ) {
				return __( 'Eingeladen', 'afspaces' );
			}

			if ( null !== $request && 'pending' === $request->status ) {
				return __( 'Anfrage offen', 'afspaces' );
			}

			if ( null !== $request && 'rejected' === $request->status ) {
				return __( 'Anfrage abgelehnt', 'afspaces' );
			}

			return __( 'Keine Zugehörigkeit', 'afspaces' );
		}

		/**
		 * @param DomainException $exception Fehler.
		 * @return int
		 */
		private function status_for_domain_error( DomainException $exception ): int {
			if ( false !== stripos( $exception->getMessage(), 'Zu viele Versuche' ) ) {
				return 429;
			}

			return 400;
		}
	}
}
