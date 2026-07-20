<?php
/**
 * Use Cases für persönliche Einladungen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\InvitationRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Invitation;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\InvitationService' ) ) {

	/**
	 * Geschäftslogik rund um persönliche Einladungen.
	 */
	class InvitationService {

		/**
		 * Versanddrosselung pro Einladung in Sekunden.
		 */
		private const MAIL_THROTTLE_SECONDS = 60;

		private SpaceRepository $spaces;
		private InvitationRepository $invitations;
		private AsgarosAdapterInterface $asgaros;
		private SpacePolicy $policy;
		private AuditRepository $audit;

		/**
		 * Konstruktor.
		 */
		public function __construct(
			SpaceRepository $spaces,
			InvitationRepository $invitations,
			AsgarosAdapterInterface $asgaros,
			SpacePolicy $policy,
			AuditRepository $audit
		) {
			$this->spaces      = $spaces;
			$this->invitations = $invitations;
			$this->asgaros     = $asgaros;
			$this->policy      = $policy;
			$this->audit       = $audit;
		}

		/**
		 * Erstellt oder aktualisiert eine offene Einladung und versendet E-Mail.
		 *
		 * @param int    $space_id Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param int    $invitee_user_id Zielbenutzer.
		 * @param string $message Optionale Nachricht.
		 * @param int    $expires_in_days Ablauf in Tagen.
		 * @return Invitation
		 * @throws DomainException Bei Berechtigungs- oder Validierungsfehlern.
		 */
		public function create_invitation(
			int $space_id,
			int $actor_user_id,
			int $invitee_user_id,
			string $message = '',
			int $expires_in_days = 7
		): Invitation {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_invite_member( $space_id, $actor_user_id, $invitee_user_id ) ) {
				throw new DomainException( __( 'Du bist nicht berechtigt, für diesen Raum einzuladen.', 'afspaces' ) );
			}

			$invitee = get_userdata( $invitee_user_id );
			if ( ! $invitee ) {
				throw new DomainException( __( 'Der eingeladene Benutzer existiert nicht.', 'afspaces' ) );
			}

			if ( empty( $invitee->user_email ) ) {
				throw new DomainException( __( 'Der Benutzer hat keine gültige E-Mail-Adresse.', 'afspaces' ) );
			}

			if ( $this->is_user_blocked_for_space( $invitee_user_id, $space_id ) ) {
				throw new DomainException( __( 'Dieser Benutzer kann nicht eingeladen werden.', 'afspaces' ) );
			}

			if ( $this->asgaros->is_user_in_group( $invitee_user_id, $space->primary_group_id ) ) {
				throw new DomainException( __( 'Die Person ist bereits Mitglied dieses Raums.', 'afspaces' ) );
			}

			$safe_message = sanitize_textarea_field( $message );
			$days         = max( 1, min( 30, $expires_in_days ) );
			$expires_at   = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );

			$this->invitations->expire_pending();
			$existing = $this->invitations->find_pending_for_user( $space_id, $invitee_user_id );
			if ( $existing && Invitation::STATUS_PENDING === $existing->effective_status() ) {
				$invitation = $this->invitations->update_pending_details( $existing->id, $safe_message, $expires_at );
			} else {
				$invitation = $this->invitations->create(
					$space_id,
					$actor_user_id,
					$invitee_user_id,
					$safe_message,
					$expires_at
				);
			}

			$this->send_invitation_email( $invitation, false );
			$this->audit->log( $space_id, $actor_user_id, $invitee_user_id, 'invitation_created', 'invitation' );

			return $this->invitations->get_by_id( $invitation->id ) ?? $invitation;
		}

		/**
		 * Gibt Einladungen für einen Space zurück.
		 *
		 * @param int         $space_id Space-ID.
		 * @param int         $actor_user_id Akteur.
		 * @param string|null $status Statusfilter.
		 * @return Invitation[]
		 */
		public function list_space_invitations( int $space_id, int $actor_user_id, ?string $status = null ): array {
			if ( ! $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Keine Berechtigung für diesen Raum.', 'afspaces' ) );
			}
			$this->invitations->expire_pending();
			return $this->invitations->list_for_space( $space_id, $status );
		}

		/**
		 * Gibt Einladungen für den eingeloggten Benutzer zurück.
		 *
		 * @param int         $invitee_user_id Benutzer-ID.
		 * @param string|null $status Statusfilter.
		 * @return Invitation[]
		 */
		public function list_my_invitations( int $invitee_user_id, ?string $status = null ): array {
			$this->invitations->expire_pending();
			return $this->invitations->list_for_invitee( $invitee_user_id, $status );
		}

		/**
		 * Widerruft eine offene Einladung.
		 *
		 * @param int $invitation_id Einladung.
		 * @param int $actor_user_id Akteur.
		 * @return void
		 */
		public function revoke_invitation( int $invitation_id, int $actor_user_id ): void {
			$invitation = $this->must_get_invitation( $invitation_id );

			if ( ! $this->policy->can_revoke_invitation( $invitation->space_id, $actor_user_id, $invitation->invitee_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Einladung nicht widerrufen.', 'afspaces' ) );
			}

			$invitation->revoke();
			$this->invitations->save( $invitation );
			$this->audit->log( $invitation->space_id, $actor_user_id, $invitation->invitee_user_id, 'invitation_revoked', 'invitation' );
		}

		/**
		 * Sendet die bestehende Einladung erneut (gleiches Token, gleiche ID).
		 *
		 * @param int $invitation_id Einladung.
		 * @param int $actor_user_id Akteur.
		 * @return void
		 */
		public function resend_invitation( int $invitation_id, int $actor_user_id ): void {
			$invitation = $this->must_get_invitation( $invitation_id );

			if ( ! $this->policy->can_revoke_invitation( $invitation->space_id, $actor_user_id, $invitation->invitee_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Einladung nicht erneut senden.', 'afspaces' ) );
			}
			if ( Invitation::STATUS_PENDING !== $invitation->effective_status() ) {
				throw new DomainException( __( 'Nur offene Einladungen können erneut versendet werden.', 'afspaces' ) );
			}

			$this->send_invitation_email( $invitation, false );
		}

		/**
		 * Nimmt eine Einladung per Token an.
		 *
		 * @param string $token Token.
		 * @param int    $actor_user_id Benutzer, der annimmt.
		 * @return Invitation
		 */
		public function accept_invitation_by_token( string $token, int $actor_user_id ): Invitation {
			$invitation = $this->resolve_token_to_invitation( $token );
			$this->assert_token_actor( $invitation, $actor_user_id );

			if ( Invitation::STATUS_ACCEPTED === $invitation->status ) {
				return $invitation;
			}

			if ( Invitation::STATUS_PENDING !== $invitation->effective_status() ) {
				throw new DomainException( __( 'Diese Einladung ist nicht mehr annehmbar.', 'afspaces' ) );
			}

			$space = $this->spaces->get_space( $invitation->space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der zugehörige Raum existiert nicht mehr.', 'afspaces' ) );
			}

			// Reihenfolge für Konsistenz: erst Gruppenzuordnung, dann Status.
			$this->asgaros->add_user_to_group( $actor_user_id, $space->primary_group_id );
			$invitation->accept();
			$this->invitations->save( $invitation );
			$this->audit->log( $invitation->space_id, $actor_user_id, $actor_user_id, 'invitation_accepted', 'invitation' );

			return $invitation;
		}

		/**
		 * Lehnt eine Einladung per Token ab.
		 *
		 * @param string $token Token.
		 * @param int    $actor_user_id Benutzer, der ablehnt.
		 * @return Invitation
		 */
		public function decline_invitation_by_token( string $token, int $actor_user_id ): Invitation {
			$invitation = $this->resolve_token_to_invitation( $token );
			$this->assert_token_actor( $invitation, $actor_user_id );

			if ( Invitation::STATUS_DECLINED === $invitation->status ) {
				return $invitation;
			}

			if ( Invitation::STATUS_PENDING !== $invitation->effective_status() ) {
				throw new DomainException( __( 'Diese Einladung ist nicht mehr ablehnbar.', 'afspaces' ) );
			}

			$invitation->decline();
			$this->invitations->save( $invitation );
			$this->audit->log( $invitation->space_id, $actor_user_id, $actor_user_id, 'invitation_declined', 'invitation' );

			return $invitation;
		}

		/**
		 * Baut ein stabiles, signiertes Token für eine Einladung.
		 *
		 * @param Invitation $invitation Einladung.
		 * @return string
		 */
		public function build_token( Invitation $invitation ): string {
			$issued_at = '' !== $invitation->created_at ? strtotime( $invitation->created_at ) : time();
			$payload = implode( ':', array( (string) $invitation->id, (string) $invitation->invitee_user_id, (string) $issued_at ) );
			$sig = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
			return rtrim( strtr( base64_encode( $payload . ':' . $sig ), '+/', '-_' ), '=' );
		}

		/**
		 * Baut die Annahme-URL für E-Mails.
		 *
		 * @param Invitation $invitation Einladung.
		 * @return string
		 */
		public function build_accept_url( Invitation $invitation ): string {
			return \AFSpaces\Interface\SpacesUrls::hub_url(
				\AFSpaces\Interface\SpacesUrls::VIEW_MY_INVITATIONS,
				array( 'invitation_token' => $this->build_token( $invitation ) )
			);
		}

		/**
		 * @param int $invitation_id Einladung.
		 * @return Invitation
		 */
		private function must_get_invitation( int $invitation_id ): Invitation {
			$invitation = $this->invitations->get_by_id( $invitation_id );
			if ( ! $invitation ) {
				throw new DomainException( __( 'Einladung nicht gefunden.', 'afspaces' ) );
			}
			return $invitation;
		}

		/**
		 * @param string $token Token.
		 * @return Invitation
		 */
		private function resolve_token_to_invitation( string $token ): Invitation {
			$decoded = base64_decode( strtr( $token, '-_', '+/' ), true );
			if ( false === $decoded ) {
				throw new DomainException( __( 'Ungültiger Einladungslink.', 'afspaces' ) );
			}

			$parts = explode( ':', (string) $decoded );
			if ( 4 !== count( $parts ) ) {
				throw new DomainException( __( 'Ungültiger Einladungslink.', 'afspaces' ) );
			}

			$invitation_id = (int) $parts[0];
			$invitee_id    = (int) $parts[1];
			$issued_at     = $parts[2];
			$sig           = $parts[3];

			$payload = implode( ':', array( (string) $invitation_id, (string) $invitee_id, (string) $issued_at ) );
			$calc    = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
			if ( ! hash_equals( $calc, $sig ) ) {
				throw new DomainException( __( 'Ungültiger Einladungslink.', 'afspaces' ) );
			}

			$invitation = $this->must_get_invitation( $invitation_id );
			if ( $invitation->invitee_user_id !== $invitee_id ) {
				throw new DomainException( __( 'Ungültiger Einladungslink.', 'afspaces' ) );
			}

			return $invitation;
		}

		/**
		 * @param Invitation $invitation Einladung.
		 * @param int        $actor_user_id Benutzer.
		 * @return void
		 */
		private function assert_token_actor( Invitation $invitation, int $actor_user_id ): void {
			if ( $invitation->invitee_user_id !== $actor_user_id ) {
				throw new DomainException( __( 'Diese Einladung gehört nicht zu deinem Konto.', 'afspaces' ) );
			}
		}

		/**
		 * @param Invitation $invitation Einladung.
		 * @param bool       $force Ignoriere Drosselung.
		 * @return void
		 */
		private function send_invitation_email( Invitation $invitation, bool $force ): void {
			$key = 'afspaces_invite_mail_' . $invitation->id;
			if ( ! $force && get_transient( $key ) ) {
				throw new DomainException( __( 'Die Einladung wurde gerade erst versendet. Bitte kurz warten.', 'afspaces' ) );
			}

			$invitee = get_userdata( $invitation->invitee_user_id );
			$inviter = get_userdata( $invitation->inviter_user_id );
			$space   = $this->spaces->get_space( $invitation->space_id );
			if ( ! $invitee || ! $inviter || ! $space ) {
				throw new DomainException( __( 'Einladung konnte nicht versendet werden.', 'afspaces' ) );
			}

			$forum     = $this->asgaros->get_forum( $space->forum_id );
			$space_name = (string) ( $forum['name'] ?? sprintf( 'Space #%d', $space->id ) );
			$accept_url = $this->build_accept_url( $invitation );

			$subject = sprintf(
				/* translators: %s: Raumname */
				__( 'Einladung zu %s', 'afspaces' ),
				$space_name
			);
			$body = sprintf(
				/* translators: 1: Anzeigename Empfänger, 2: Anzeigename Einladender, 3: Raumname, 4: Ablaufdatum, 5: Link */
				__( "Hallo %1\$s,\n\n%2\$s hat dich zum Space \"%3\$s\" eingeladen.\n\nAblauf: %4\$s\n\nEinladung annehmen oder ablehnen:\n%5\$s\n", 'afspaces' ),
				$invitee->display_name,
				$inviter->display_name,
				$space_name,
				$invitation->expires_at,
				$accept_url
			);
			if ( '' !== $invitation->message ) {
				$body .= "\n" . __( 'Persönliche Nachricht:', 'afspaces' ) . "\n" . $invitation->message . "\n";
			}

			$subject = (string) apply_filters( 'afspaces_invitation_mail_subject', $subject, $invitation, $space );
			$body    = (string) apply_filters( 'afspaces_invitation_mail_body', $body, $invitation, $space, $accept_url );

			$sent = wp_mail( $invitee->user_email, $subject, $body );
			if ( ! $sent ) {
				throw new DomainException( __( 'E-Mail-Versand fehlgeschlagen. Du kannst den Versand erneut versuchen.', 'afspaces' ) );
			}

			set_transient( $key, '1', self::MAIL_THROTTLE_SECONDS );
			$this->invitations->mark_sent( $invitation->id );
			do_action( 'afspaces_invitation_notification_created', $invitation );
		}

		/**
		 * @param int $user_id Benutzer-ID.
		 * @param int $space_id Space-ID.
		 * @return bool
		 */
		private function is_user_blocked_for_space( int $user_id, int $space_id ): bool {
			if ( is_multisite() ) {
				$user = get_userdata( $user_id );
				if ( $user && isset( $user->spam ) && (bool) $user->spam ) {
					return true;
				}
			}

			$blocked = (bool) get_user_meta( $user_id, 'afspaces_invites_blocked', true );
			$excluded = (bool) get_user_meta( $user_id, 'afspaces_excluded_space_' . $space_id, true );

			return (bool) apply_filters( 'afspaces_is_user_blocked_for_invite', $blocked || $excluded, $user_id, $space_id );
		}
	}
}
