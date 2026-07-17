<?php
/**
 * Use Cases für die Mitglieder-Verwaltung.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\SpaceManager;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\MemberService' ) ) {

	/**
	 * Bündelt die Geschäftslogik für Mitgliederänderungen.
	 */
	class MemberService {

		/**
		 * @var SpaceRepository
		 */
		private SpaceRepository $spaces;

		/**
		 * @var AsgarosAdapterInterface
		 */
		private AsgarosAdapterInterface $asgaros;

		/**
		 * @var SpacePolicy
		 */
		private SpacePolicy $policy;

		/**
		 * @var AuditRepository
		 */
		private AuditRepository $audit;

		/**
		 * Konstruktor.
		 *
		 * @param SpaceRepository         $spaces  Space-Repository.
		 * @param AsgarosAdapterInterface $asgaros Asgaros-Adapter.
		 * @param SpacePolicy            $policy  Policy.
		 * @param AuditRepository        $audit   Audit-Repository.
		 */
		public function __construct(
			SpaceRepository $spaces,
			AsgarosAdapterInterface $asgaros,
			SpacePolicy $policy,
			AuditRepository $audit
		) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
			$this->policy  = $policy;
			$this->audit   = $audit;
		}

		/**
		 * Fügt einen Benutzer zum primären Space-Forum hinzu.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return void
		 * @throws DomainException Wenn keine Berechtigung oder fehlgeschlagen.
		 */
		public function add_member( int $space_id, int $actor_user_id, int $target_user_id ): void {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( $target_user_id < 1 || ! get_user_by( 'id', $target_user_id ) ) {
				throw new DomainException( __( 'Der Zielbenutzer existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_add_member( $space_id, $actor_user_id ) ) {
				throw new DomainException(
					__( 'Du bist nicht berechtigt, Mitglieder zu diesem Raum hinzuzufügen.', 'afspaces' )
				);
			}

			$this->asgaros->add_user_to_group( $target_user_id, $space->primary_group_id );
			$this->audit->log( $space_id, $actor_user_id, $target_user_id, 'member_added' );
		}

		/**
		 * Entfernt einen Benutzer aus dem primären Space-Forum.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return void
		 * @throws DomainException Wenn keine Berechtigung oder fehlgeschlagen.
		 */
		public function remove_member( int $space_id, int $actor_user_id, int $target_user_id ): void {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_remove_member( $space_id, $actor_user_id, $target_user_id ) ) {
				throw new DomainException(
					__( 'Du bist nicht berechtigt, dieses Mitglied zu entfernen. Der letzte Raumverantwortliche kann nicht entfernt werden.', 'afspaces' )
				);
			}

			$replacement_owner_id = 0;
			if ( (int) $space->owner_user_id === $target_user_id ) {
				foreach ( $this->spaces->get_managers( $space_id ) as $manager ) {
					if ( (int) $manager->user_id !== $target_user_id ) {
						$replacement_owner_id = (int) $manager->user_id;
						break;
					}
				}
			}

			$this->asgaros->remove_user_from_group( $target_user_id, $space->primary_group_id );

			if ( $this->spaces->is_manager( $space_id, $target_user_id ) ) {
				$this->spaces->remove_manager( $space_id, $target_user_id );
			}

			if ( $replacement_owner_id > 0 ) {
				$this->spaces->set_owner_user( $space_id, $replacement_owner_id );
				$this->spaces->add_manager(
					new SpaceManager(
						array(
							'space_id' => $space_id,
							'user_id'  => $replacement_owner_id,
							'role'     => SpaceManager::ROLE_OWNER,
						)
					)
				);
			}

			$this->audit->log( $space_id, $actor_user_id, $target_user_id, 'member_removed' );
		}

		/**
		 * Macht ein bestehendes Mitglied zum Raumverantwortlichen.
		 *
		 * @param int $space_id       Space-ID.
		 * @param int $actor_user_id  Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return void
		 * @throws DomainException Wenn keine Berechtigung oder Ziel ungültig.
		 */
		public function assign_manager( int $space_id, int $actor_user_id, int $target_user_id ): void {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( $target_user_id < 1 || ! get_user_by( 'id', $target_user_id ) ) {
				throw new DomainException( __( 'Der Zielbenutzer existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du bist nicht berechtigt, Raumverantwortliche zu verwalten.', 'afspaces' ) );
			}

			if ( ! $this->asgaros->is_user_in_group( $target_user_id, $space->primary_group_id ) ) {
				throw new DomainException( __( 'Die Person muss erst Mitglied dieses Raums sein.', 'afspaces' ) );
			}

			$this->spaces->add_manager(
				new SpaceManager(
					array(
						'space_id' => $space_id,
						'user_id'  => $target_user_id,
						'role'     => SpaceManager::ROLE_MANAGER,
					)
				)
			);

			$this->audit->log( $space_id, $actor_user_id, $target_user_id, 'manager_assigned' );
		}

		/**
		 * Entzieht einer Person die Raumverantwortung.
		 *
		 * @param int $space_id       Space-ID.
		 * @param int $actor_user_id  Akteur.
		 * @param int $target_user_id Zielbenutzer.
		 * @return void
		 * @throws DomainException Wenn keine Berechtigung oder Schutzregel greift.
		 */
		public function revoke_manager( int $space_id, int $actor_user_id, int $target_user_id ): void {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum existiert nicht.', 'afspaces' ) );
			}

			if ( ! $this->policy->can_manage( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du bist nicht berechtigt, Raumverantwortliche zu verwalten.', 'afspaces' ) );
			}

			if ( (int) $space->owner_user_id === $target_user_id && $this->spaces->count_owners( $space_id ) <= 1 ) {
				throw new DomainException( __( 'Der letzte Owner kann nicht als Raumverantwortlicher entfernt werden.', 'afspaces' ) );
			}

			$this->spaces->remove_manager( $space_id, $target_user_id );
			$this->audit->log( $space_id, $actor_user_id, $target_user_id, 'manager_revoked' );
		}

		/**
		 * Sucht WordPress-Benutzer (serverseitig, ohne E-Mail-Leakage).
		 *
		 * @param string $search Suchbegriff.
		 * @param int    $page   Seite.
		 * @param int    $per_page Pro Seite.
		 * @return array<string,mixed>
		 */
		public function search_users( string $search, int $page = 1, int $per_page = 20 ): array {
			$args = array(
				'search'         => '*' . sanitize_text_field( $search ) . '*',
				'search_columns' => array( 'display_name', 'user_login' ),
				'number'         => $per_page,
				'paged'          => max( 1, $page ),
				'fields'         => array( 'ID', 'display_name', 'user_login' ),
			);

			$user_query = new \WP_User_Query( $args );
			$results    = $user_query->get_results();

			$members = array();
			foreach ( $results as $user ) {
				$members[] = array(
					'user_id'      => (int) $user->ID,
					'display_name' => $user->display_name,
					'user_login'   => $user->user_login,
				);
			}

			return array(
				'members'  => $members,
				'total'    => (int) $user_query->get_total(),
				'page'     => max( 1, $page ),
				'per_page' => $per_page,
			);
		}
	}
}
