<?php
/**
 * Geschäftslogik für sichere Einladungslinks.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\InviteLinkRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\InviteLink;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\InviteLinkService' ) ) {

	/**
	 * Verwaltet Erstellung, Prüfung und Nutzung von Invite-Links.
	 */
	class InviteLinkService {

		private const DEFAULT_EXPIRES_DAYS = 7;
		private const MAX_EXPIRES_DAYS = 30;
		private const DEFAULT_MAX_USES = 1;
		private const MAX_RATE_LIMIT_WINDOW = 300;
		private const TOKEN_VERIFY_LIMIT = 15;
		private const TOKEN_ACCEPT_LIMIT = 8;

		private SpaceRepository $spaces;
		private InviteLinkRepository $links;
		private AsgarosAdapterInterface $asgaros;
		private SpacePolicy $policy;
		private AuditRepository $audit;
		private InviteLinkToken $tokens;

		public function __construct(
			SpaceRepository $spaces,
			InviteLinkRepository $links,
			AsgarosAdapterInterface $asgaros,
			SpacePolicy $policy,
			AuditRepository $audit,
			?InviteLinkToken $tokens = null
		) {
			$this->spaces  = $spaces;
			$this->links   = $links;
			$this->asgaros = $asgaros;
			$this->policy  = $policy;
			$this->audit   = $audit;
			$this->tokens  = $tokens ?? new InviteLinkToken();
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param array<string,mixed> $args Bedingungen.
		 * @return array{link:InviteLink,token:string,url:string}
		 */
		public function create_link( int $space_id, int $actor_user_id, array $args = array() ): array {
			$space = $this->require_space( $space_id );
			$this->assert_can_manage_links( $space_id, $actor_user_id );

			$approval_mode = $this->normalize_approval_mode( (string) ( $args['approval_mode'] ?? InviteLink::MODE_AUTO_JOIN ) );
			$max_uses      = $this->normalize_max_uses( (int) ( $args['max_uses'] ?? self::DEFAULT_MAX_USES ), $space_id, $actor_user_id );
			$expires_at    = $this->normalize_expiry_from_days( (int) ( $args['expires_in_days'] ?? self::DEFAULT_EXPIRES_DAYS ) );
			$allow_registration = $this->normalize_registration_flag( ! empty( $args['allow_registration'] ), $space );

			$token = $this->tokens->generate();
			$link  = $this->links->create(
				$space_id,
				$actor_user_id,
				$this->tokens->hash( $token ),
				$approval_mode,
				$max_uses,
				$allow_registration,
				$expires_at
			);

			$this->audit->log( $space_id, $actor_user_id, 0, 'invite_link_created', 'invite_link' );

			return array(
				'link'  => $link,
				'token' => $token,
				'url'   => $this->build_link_url( $token ),
			);
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param string|null $status Filter.
		 * @return InviteLink[]
		 */
		public function list_links( int $space_id, int $actor_user_id, ?string $status = null ): array {
			$this->assert_can_manage_links( $space_id, $actor_user_id );
			$list = $this->links->list_for_space( $space_id );

			if ( null === $status || '' === $status ) {
				return $list;
			}

			return array_values(
				array_filter(
					$list,
					static fn( InviteLink $link ): bool => $link->effective_status() === $status
				)
			);
		}

		/**
		 * @param int $link_id Link-ID.
		 * @param int $actor_user_id Akteur.
		 * @return void
		 */
		public function revoke_link( int $link_id, int $actor_user_id ): void {
			$link = $this->require_link( $link_id );
			$this->assert_can_manage_links( $link->space_id, $actor_user_id );

			$link->revoke();
			$this->links->save( $link );
			$this->audit->log( $link->space_id, $actor_user_id, 0, 'invite_link_revoked', 'invite_link' );
		}

		/**
		 * @param int $link_id Link-ID.
		 * @param int $actor_user_id Akteur.
		 * @param string $expires_at Neuer Ablauf im mysql-Format.
		 * @return InviteLink
		 */
		public function shorten_expiry( int $link_id, int $actor_user_id, string $expires_at ): InviteLink {
			$link = $this->require_link( $link_id );
			$this->assert_can_manage_links( $link->space_id, $actor_user_id );

			if ( InviteLink::STATUS_ACTIVE !== $link->effective_status() ) {
				throw new DomainException( __( 'Nur aktive Einladungslinks können angepasst werden.', 'afspaces' ) );
			}

			$new_expiry = $this->normalize_mysql_datetime( $expires_at );
			if ( $new_expiry >= $link->expires_at ) {
				throw new DomainException( __( 'Das neue Ablaufdatum muss früher als das bisherige sein.', 'afspaces' ) );
			}
			if ( $new_expiry <= (string) current_time( 'mysql' ) ) {
				throw new DomainException( __( 'Das neue Ablaufdatum muss in der Zukunft liegen.', 'afspaces' ) );
			}

			$link->expires_at = $new_expiry;
			$link->updated_at = (string) current_time( 'mysql' );
			$this->links->save( $link );
			$this->audit->log( $link->space_id, $actor_user_id, 0, 'invite_link_expiry_shortened', 'invite_link' );

			return $link;
		}

		/**
		 * @param string $token Klartext-Token.
		 * @param int $actor_user_id Aktueller Benutzer oder 0.
		 * @return array<string,mixed>
		 */
		public function preview_link( string $token, int $actor_user_id = 0 ): array {
			$link  = $this->resolve_link( $token, true, false );
			$space = $this->require_space( $link->space_id );
			$forum = $this->asgaros->get_forum( $space->forum_id );
			$state = 'ready';

			if ( 0 === $actor_user_id ) {
				$state = 'login_required';
			} elseif ( $this->asgaros->is_user_in_group( $actor_user_id, $space->primary_group_id ) ) {
				$state = 'already_member';
			} elseif ( InviteLink::MODE_APPROVAL_REQUIRED === $link->approval_mode ) {
				$state = 'approval_required';
			}

			$current_url = $this->build_link_url( $token );

			return array(
				'link'               => $link,
				'space'              => $space,
				'forum_name'         => (string) ( $forum['name'] ?? sprintf( 'Space #%d', $space->id ) ),
				'state'              => $state,
				'login_url'          => wp_login_url( $current_url ),
				'registration_url'   => $this->build_registration_url( $current_url, $space ),
				'can_register'       => $this->is_registration_available_for_space( $space ),
				'action_label'       => InviteLink::MODE_APPROVAL_REQUIRED === $link->approval_mode ? __( 'Beitrittsanfrage senden', 'afspaces' ) : __( 'Raum beitreten', 'afspaces' ),
				'status_message'     => $this->status_message_for_preview( $link, $state ),
			);
		}

		/**
		 * @param string $token Klartext-Token.
		 * @param int $actor_user_id Benutzer-ID.
		 * @return array{result:string,space_id:int,forum_url:string}
		 */
		public function use_link( string $token, int $actor_user_id ): array {
			if ( $actor_user_id < 1 ) {
				throw new DomainException( __( 'Bitte melde dich an, um diesen Einladungslink zu verwenden.', 'afspaces' ) );
			}

			$link  = $this->resolve_link( $token, true, true, $actor_user_id );
			$space = $this->require_space( $link->space_id );
			$forum_url = (string) apply_filters( 'afspaces_forum_url_after_invite_link', home_url( '/forum/' ), $space, $link, $actor_user_id );

			if ( $this->asgaros->is_user_in_group( $actor_user_id, $space->primary_group_id ) ) {
				return array(
					'result'    => 'already_member',
					'space_id'  => $space->id,
					'forum_url' => $forum_url,
				);
			}

			if ( ! $this->links->claim_use( $link->id ) ) {
				$link = $this->require_link( $link->id );
				throw new DomainException( $this->message_for_unavailable_link( $link ) );
			}

			if ( InviteLink::MODE_APPROVAL_REQUIRED === $link->approval_mode ) {
				$this->audit->log( $space->id, $actor_user_id, $actor_user_id, 'invite_link_join_requested', 'invite_link' );
				return array(
					'result'    => 'request_created',
					'space_id'  => $space->id,
					'forum_url' => $forum_url,
				);
			}

			$this->asgaros->add_user_to_group( $actor_user_id, $space->primary_group_id );
			$this->audit->log( $space->id, $actor_user_id, $actor_user_id, 'invite_link_joined', 'invite_link' );

			return array(
				'result'    => 'joined',
				'space_id'  => $space->id,
				'forum_url' => $forum_url,
			);
		}

		/**
		 * @param string $token Klartext-Token.
		 * @return string
		 */
		public function build_link_url( string $token ): string {
			return \AFSpaces\Interface\SpacesUrls::hub_url(
				\AFSpaces\Interface\SpacesUrls::VIEW_MY_INVITATIONS,
				array( 'invite_link' => $token )
			);
		}

		/**
		 * @param Space $space Raum.
		 * @return bool
		 */
		public function is_registration_available_for_space( Space $space ): bool {
			$enabled = (bool) get_option( 'users_can_register', false );
			return (bool) apply_filters( 'afspaces_allow_invite_link_registration', $enabled, $space );
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_create_unlimited_links( int $space_id, int $actor_user_id ): bool {
			return (bool) apply_filters(
				'afspaces_allow_unlimited_invite_links',
				$this->policy->can_create_unlimited_invite_links( $space_id, $actor_user_id ),
				$space_id,
				$actor_user_id
			);
		}

		private function assert_can_manage_links( int $space_id, int $actor_user_id ): void {
			if ( ! $this->policy->can_manage_invite_links( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst für diesen Raum keine Einladungslinks verwalten.', 'afspaces' ) );
			}
		}

		private function require_space( int $space_id ): Space {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			return $space;
		}

		private function require_link( int $link_id ): InviteLink {
			$link = $this->links->get_by_id( $link_id );
			if ( ! $link ) {
				throw new DomainException( __( 'Einladungslink nicht gefunden.', 'afspaces' ) );
			}

			return $link;
		}

		private function normalize_approval_mode( string $approval_mode ): string {
			$allowed = array( InviteLink::MODE_AUTO_JOIN, InviteLink::MODE_APPROVAL_REQUIRED );
			if ( ! in_array( $approval_mode, $allowed, true ) ) {
				return InviteLink::MODE_AUTO_JOIN;
			}

			return $approval_mode;
		}

		private function normalize_max_uses( int $max_uses, int $space_id, int $actor_user_id ): int {
			if ( $max_uses < 0 ) {
				throw new DomainException( __( 'Die maximale Nutzungszahl ist ungültig.', 'afspaces' ) );
			}

			if ( 0 === $max_uses && ! $this->can_create_unlimited_links( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Unbegrenzte Einladungslinks sind hier nicht freigegeben.', 'afspaces' ) );
			}

			if ( 0 === $max_uses ) {
				return 0;
			}

			return max( 1, min( 1000, $max_uses ) );
		}

		private function normalize_expiry_from_days( int $days ): string {
			$days = max( 1, min( self::MAX_EXPIRES_DAYS, $days ) );
			return gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
		}

		private function normalize_registration_flag( bool $allow_registration, Space $space ): bool {
			if ( ! $allow_registration ) {
				return false;
			}

			return $this->is_registration_available_for_space( $space );
		}

		private function normalize_mysql_datetime( string $value ): string {
			$timestamp = strtotime( $value );
			if ( false === $timestamp ) {
				throw new DomainException( __( 'Das Ablaufdatum ist ungültig.', 'afspaces' ) );
			}

			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		private function resolve_link( string $token, bool $count_verify_attempt, bool $count_accept_attempt, int $actor_user_id = 0 ): InviteLink {
			$token = sanitize_text_field( $token );
			if ( '' === $token || 1 !== preg_match( '/^[A-Za-z0-9\-_]+$/', $token ) ) {
				$this->log_suspicious_attempt( 0, $actor_user_id, 'invite_link_invalid_token' );
				throw new DomainException( __( 'Dieser Einladungslink ist ungültig oder nicht mehr verfugbar.', 'afspaces' ) );
			}

			$subject_hash = md5( $token );
			if ( $count_verify_attempt ) {
				$this->assert_rate_limit( 'verify', $subject_hash, self::TOKEN_VERIFY_LIMIT, $actor_user_id, 0 );
			}
			if ( $count_accept_attempt ) {
				$this->assert_rate_limit( 'accept', $subject_hash, self::TOKEN_ACCEPT_LIMIT, $actor_user_id, 0 );
			}

			$hash = $this->tokens->hash( $token );
			$link = $this->links->get_by_token_hash( $hash );
			if ( ! $link || ! $this->tokens->matches( $token, $link->token_hash ) ) {
				$this->log_suspicious_attempt( 0, $actor_user_id, 'invite_link_invalid_token' );
				throw new DomainException( __( 'Dieser Einladungslink ist ungültig oder nicht mehr verfugbar.', 'afspaces' ) );
			}

			if ( InviteLink::STATUS_ACTIVE !== $link->effective_status() ) {
				throw new DomainException( $this->message_for_unavailable_link( $link ) );
			}

			return $link;
		}

		private function message_for_unavailable_link( InviteLink $link ): string {
			switch ( $link->effective_status() ) {
				case InviteLink::STATUS_REVOKED:
					return __( 'Dieser Einladungslink wurde widerrufen.', 'afspaces' );
				case InviteLink::STATUS_EXPIRED:
					return __( 'Dieser Einladungslink ist abgelaufen.', 'afspaces' );
				case InviteLink::STATUS_EXHAUSTED:
					return __( 'Dieser Einladungslink wurde bereits vollstandig aufgebraucht.', 'afspaces' );
				default:
					return __( 'Dieser Einladungslink ist derzeit nicht verfugbar.', 'afspaces' );
			}
		}

		private function status_message_for_preview( InviteLink $link, string $state ): string {
			if ( 'login_required' === $state ) {
				return __( 'Bitte melde dich an, um diese Raumeinladung zu verwenden.', 'afspaces' );
			}
			if ( 'already_member' === $state ) {
				return __( 'Du bist bereits Mitglied dieses Raums.', 'afspaces' );
			}
			if ( 'approval_required' === $state ) {
				return __( 'Nach dem Absenden wird eine Beitrittsanfrage fur diesen Raum erstellt.', 'afspaces' );
			}

			return sprintf(
				/* translators: %s: Ablaufdatum */
				__( 'Dieser Einladungslink ist bis %s verfugbar.', 'afspaces' ),
				$link->expires_at
			);
		}

		private function build_registration_url( string $redirect_url, Space $space ): string {
			if ( ! $this->is_registration_available_for_space( $space ) ) {
				return '';
			}

			$registration_url = wp_registration_url();
			return add_query_arg( 'redirect_to', $redirect_url, $registration_url );
		}

		private function assert_rate_limit( string $bucket, string $subject_hash, int $limit, int $actor_user_id, int $space_id ): void {
			$key = 'afspaces_invite_link_' . $bucket . '_' . md5( $this->current_rate_subject( $subject_hash, $actor_user_id ) );
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				$this->log_suspicious_attempt( $space_id, $actor_user_id, 'invite_link_' . $bucket . '_rate_limited' );
				throw new DomainException( __( 'Zu viele Versuche. Bitte probiere es spater erneut.', 'afspaces' ) );
			}

			set_transient( $key, (string) ( $count + 1 ), self::MAX_RATE_LIMIT_WINDOW );
		}

		private function current_rate_subject( string $subject_hash, int $actor_user_id ): string {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			return $ip . '|' . $actor_user_id . '|' . $subject_hash;
		}

		private function log_suspicious_attempt( int $space_id, int $actor_user_id, string $action ): void {
			$this->audit->log( $space_id, $actor_user_id, 0, $action, 'invite_link' );
		}
	}
}