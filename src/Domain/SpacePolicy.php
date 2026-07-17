<?php
/**
 * Zentrale Berechtigungs-Policy für Spaces.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Domain;

use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;

if ( ! class_exists( 'AFSpaces\\Domain\\SpacePolicy' ) ) {

	/**
	 * Entscheidet, ob ein Akteur eine Aktion an einem Space ausführen darf.
	 */
	class SpacePolicy {

		/**
		 * @var SpaceRepository
		 */
		private SpaceRepository $repository;

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository $repository Space-Repository.
		 */
		public function __construct( SpaceRepository $repository ) {
			$this->repository = $repository;
		}

		/**
		 * Darf der Akteur den Space verwalten (ansehen, Mitglieder ändern)?
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Benutzer-ID des Akteurs.
		 * @return bool
		 */
		public function can_manage( int $space_id, int $actor_user_id ): bool {
			if ( user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES ) ) {
				return true;
			}
			return $this->repository->is_manager( $space_id, $actor_user_id );
		}

		/**
		 * Darf der Akteur einen Benutzer zum Space hinzufügen?
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Benutzer-ID des Akteurs.
		 * @return bool
		 */
		public function can_add_member( int $space_id, int $actor_user_id ): bool {
			return $this->can_manage( $space_id, $actor_user_id );
		}

		/**
		 * Darf der Akteur eine persönliche Einladung erstellen?
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return bool
		 */
		public function can_invite_member( int $space_id, int $actor_user_id, int $target_user_id ): bool {
			if ( $target_user_id < 1 ) {
				return false;
			}

			return $this->can_manage( $space_id, $actor_user_id );
		}

		/**
		 * Darf der Akteur eine Einladung widerrufen oder erneut senden?
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return bool
		 */
		public function can_revoke_invitation( int $space_id, int $actor_user_id, int $target_user_id ): bool {
			if ( $target_user_id < 1 ) {
				return false;
			}

			return $this->can_manage( $space_id, $actor_user_id );
		}

		/**
		 * Darf der Akteur Invite-Links für einen Space erstellen oder verwalten?
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_manage_invite_links( int $space_id, int $actor_user_id ): bool {
			return $this->can_manage( $space_id, $actor_user_id );
		}

		/**
		 * Darf der Akteur unbegrenzte Invite-Links erstellen?
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_create_unlimited_invite_links( int $space_id, int $actor_user_id ): bool {
			if ( ! $this->can_manage_invite_links( $space_id, $actor_user_id ) ) {
				return false;
			}

			return user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES )
				|| user_can( $actor_user_id, Capabilities::CREATE_INVITE_LINKS );
		}

		/**
		 * Darf der Akteur einen Benutzer aus dem Space entfernen?
		 *
		 * Schützt den letzten Owner vor dem Entfernen.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Benutzer-ID des Akteurs.
		 * @param int $target_user_id Betroffener Benutzer.
		 * @return bool
		 */
		public function can_remove_member( int $space_id, int $actor_user_id, int $target_user_id ): bool {
			if ( ! $this->can_manage( $space_id, $actor_user_id ) ) {
				return false;
			}

			// Letzte verantwortliche Person (Owner/Manager) vor Selbstentfernung schützen.
			$space = $this->repository->get_space( $space_id );
			if ( $space && $target_user_id === $space->owner_user_id ) {
				if ( $this->repository->count_responsibles( $space_id ) <= 1 ) {
					return false;
				}
			}

			return true;
		}
	}
}
