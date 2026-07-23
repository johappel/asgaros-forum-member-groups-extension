<?php
/**
 * Geschaeftslogik fuer Beitrittsanfragen.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\JoinRequestRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\JoinRequest;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\JoinRequestService' ) ) {

	/**
	 * Verwaltet Erstellung und Entscheidung von Beitrittsanfragen.
	 */
	class JoinRequestService {

		private SpaceRepository $spaces;
		private JoinRequestRepository $requests;
		private AsgarosAdapterInterface $asgaros;
		private SpacePolicy $policy;
		private AuditRepository $audit;

		public function __construct(
			SpaceRepository $spaces,
			JoinRequestRepository $requests,
			AsgarosAdapterInterface $asgaros,
			SpacePolicy $policy,
			AuditRepository $audit
		) {
			$this->spaces   = $spaces;
			$this->requests = $requests;
			$this->asgaros  = $asgaros;
			$this->policy   = $policy;
			$this->audit    = $audit;
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Anfragender.
		 * @param string $message Optionale Nachricht.
		 * @return JoinRequest
		 */
		public function create_request( int $space_id, int $actor_user_id, string $message = '' ): JoinRequest {
			if ( $actor_user_id < 1 ) {
				throw new DomainException( __( 'Bitte melde dich an, um eine Beitrittsanfrage zu senden.', 'afspaces' ) );
			}

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du verwaltest diesen Raum bereits.', 'afspaces' ) );
			}

			if ( $this->asgaros->is_user_in_group( $actor_user_id, $space->primary_group_id ) ) {
				throw new DomainException( __( 'Du bist bereits Mitglied dieses Raums.', 'afspaces' ) );
			}

			$existing = $this->requests->find_pending_for_user( $space_id, $actor_user_id );
			if ( $existing ) {
				return $existing;
			}

			$request = $this->requests->create(
				$space_id,
				$actor_user_id,
				sanitize_textarea_field( $message )
			);

			$this->audit->log( $space_id, $actor_user_id, $actor_user_id, 'join_request_created', 'join_request' );
			$this->notify_responsibles( $request );
			return $request;
		}

		/**
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param string|null $status Optionaler Statusfilter.
		 * @return JoinRequest[]
		 */
		public function list_space_requests( int $space_id, int $actor_user_id, ?string $status = null ): array {
			if ( ! $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Keine Berechtigung fuer diesen Raum.', 'afspaces' ) );
			}

			return $this->requests->list_for_space( $space_id, $status );
		}

		/**
		 * @param int $requester_user_id Benutzer-ID.
		 * @param string|null $status Optionaler Status.
		 * @return JoinRequest[]
		 */
		public function list_my_requests( int $requester_user_id, ?string $status = null ): array {
			return $this->requests->list_for_requester( $requester_user_id, $status );
		}

		/**
		 * @param int $request_id Anfrage-ID.
		 * @param int $actor_user_id Manager.
		 * @param string $decision_message Optionaler Grund.
		 * @return JoinRequest
		 */
		public function approve_request( int $request_id, int $actor_user_id, string $decision_message = '' ): JoinRequest {
			$request = $this->must_get_request( $request_id );
			$space   = $this->spaces->get_space( $request->space_id );

			if ( ! $space ) {
				throw new DomainException( __( 'Der zugehoerige Raum existiert nicht mehr.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_manage( $request->space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Anfrage nicht entscheiden.', 'afspaces' ) );
			}

			if ( JoinRequest::STATUS_PENDING !== $request->status ) {
				throw new DomainException( __( 'Diese Anfrage ist bereits entschieden.', 'afspaces' ) );
			}

			$this->asgaros->add_user_to_group( $request->requester_user_id, $space->primary_group_id );
			$request->approve( $actor_user_id, $decision_message );
			$this->requests->save( $request );

			$this->audit->log( $request->space_id, $actor_user_id, $request->requester_user_id, 'join_request_approved', 'join_request' );
			$this->notify_requester( $request, true );

			return $request;
		}

		/**
		 * @param int $request_id Anfrage-ID.
		 * @param int $actor_user_id Manager.
		 * @param string $decision_message Optionaler Grund.
		 * @return JoinRequest
		 */
		public function reject_request( int $request_id, int $actor_user_id, string $decision_message = '' ): JoinRequest {
			$request = $this->must_get_request( $request_id );

			if ( ! $this->policy->can_manage( $request->space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst diese Anfrage nicht entscheiden.', 'afspaces' ) );
			}

			if ( JoinRequest::STATUS_PENDING !== $request->status ) {
				throw new DomainException( __( 'Diese Anfrage ist bereits entschieden.', 'afspaces' ) );
			}

			$request->reject( $actor_user_id, $decision_message );
			$this->requests->save( $request );

			$this->audit->log( $request->space_id, $actor_user_id, $request->requester_user_id, 'join_request_rejected', 'join_request' );
			$this->notify_requester( $request, false );

			return $request;
		}

		/**
		 * @param int $request_id Anfrage-ID.
		 * @return JoinRequest
		 */
		private function must_get_request( int $request_id ): JoinRequest {
			$request = $this->requests->get_by_id( $request_id );
			if ( ! $request ) {
				throw new DomainException( __( 'Beitrittsanfrage nicht gefunden.', 'afspaces' ) );
			}
			return $request;
		}

		/**
		 * @param JoinRequest $request Anfrage.
		 * @param bool $approved Genehmigt?
		 * @return void
		 */
		private function notify_requester( JoinRequest $request, bool $approved ): void {
			$user = get_userdata( $request->requester_user_id );
			if ( ! $user || empty( $user->user_email ) ) {
				return;
			}

			$forum_name = $this->working_group_name( $request->space_id );

			$subject = $approved
				? sprintf( __( 'Deine Beitrittsanfrage fuer "%s" wurde genehmigt', 'afspaces' ), $forum_name )
				: sprintf( __( 'Deine Beitrittsanfrage fuer "%s" wurde abgelehnt', 'afspaces' ), $forum_name );

			$body = $approved
				? __( 'Deine Beitrittsanfrage wurde genehmigt. Du kannst die Arbeitsgruppe nun nutzen.', 'afspaces' )
				: __( 'Deine Beitrittsanfrage wurde abgelehnt.', 'afspaces' );

			if ( '' !== $request->decision_message ) {
				$body .= "\n\n" . __( 'Hinweis:', 'afspaces' ) . "\n" . $request->decision_message;
			}

			wp_mail( (string) $user->user_email, $subject, $body );
			$this->audit->log( $request->space_id, 0, $request->requester_user_id, $approved ? 'join_request_requester_notified_approved' : 'join_request_requester_notified_rejected', 'notification' );
		}

		/**
		 * @param JoinRequest $request Anfrage.
		 * @return void
		 */
		private function notify_responsibles( JoinRequest $request ): void {
			$emails = array();
			foreach ( $this->spaces->get_managers( $request->space_id ) as $manager ) {
				$user = get_userdata( $manager->user_id );
				if ( ! $user || empty( $user->user_email ) ) {
					continue;
				}

				$emails[ strtolower( (string) $user->user_email ) ] = array(
					'email' => (string) $user->user_email,
					'user_id' => (int) $user->ID,
				);
			}

			$central = (string) get_option( 'afspaces_central_notification_email', '' );
			$central = (string) apply_filters( 'afspaces_central_notification_email', $central, $request );

			$requester = get_userdata( $request->requester_user_id );
			$requester_name = $requester ? (string) $requester->display_name : (string) $request->requester_user_id;
			$forum_name = $this->working_group_name( $request->space_id );
			$subject = sprintf( __( 'Neue Beitrittsanfrage fuer "%s"', 'afspaces' ), $forum_name );
			$body = sprintf( __( 'Für die Arbeitsgruppe "%s" ist eine neue Beitrittsanfrage eingegangen.', 'afspaces' ), $forum_name );
			$body .= "\n\n" . sprintf( __( 'Anfragende Person: %s', 'afspaces' ), $requester_name );

			if ( '' !== $request->request_message ) {
				$body .= "\n\n" . __( 'Nachricht:', 'afspaces' ) . "\n" . $request->request_message;
			}

			if ( class_exists( '\\AFSpaces\\Interface\\SpacesUrls' ) ) {
				$body .= "\n\n" . __( 'Verwaltung:', 'afspaces' ) . "\n" . \AFSpaces\Interface\SpacesUrls::hub_url( \AFSpaces\Interface\SpacesUrls::VIEW_JOIN_REQUESTS, array( 'space_id' => $request->space_id ) );
			}

			foreach ( $emails as $entry ) {
				wp_mail( $entry['email'], $subject, $body );
				$this->audit->log( $request->space_id, 0, (int) $entry['user_id'], 'join_request_manager_notified', 'notification' );
			}

			if ( '' !== $central && is_email( $central ) ) {
				wp_mail( $central, $subject, $body );
				$this->audit->log( $request->space_id, 0, 0, 'join_request_central_notified', 'notification' );
			}
		}

		/**
		 * @param int $space_id Space-ID.
		 * @return string
		 */
		private function working_group_name( int $space_id ): string {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				return sprintf( 'Arbeitsgruppe #%d', $space_id );
			}

			$forum = $this->asgaros->get_forum( $space->forum_id );
			if ( ! empty( $forum['name'] ) ) {
				return (string) $forum['name'];
			}

			return sprintf( 'Arbeitsgruppe #%d', $space_id );
		}
	}
}
