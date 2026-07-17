<?php
/**
 * Registriert bestehende Asgaros-Foren als AFSpaces-Räume.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceManager;

if ( ! class_exists( 'AFSpaces\\Application\\SpaceRegistrationService' ) ) {

	/**
	 * Kapselt die Registrierung vorhandener Foren als Space.
	 */
	class SpaceRegistrationService {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;

		public function __construct( SpaceRepository $spaces, AsgarosAdapterInterface $asgaros ) {
			$this->spaces  = $spaces;
			$this->asgaros = $asgaros;
		}

		/**
		 * @param int $actor_user_id Benutzer-ID.
		 * @return array<int,array<string,mixed>>
		 */
		public function list_registrable_forums( int $actor_user_id ): array {
			if ( ! $this->can_register_spaces( $actor_user_id ) ) {
				return array();
			}

			$forums = $this->asgaros->list_manageable_forums( $actor_user_id );
			$result = array();

			foreach ( $forums as $forum ) {
				$forum_id = (int) ( $forum['id'] ?? 0 );
				if ( $forum_id < 1 ) {
					continue;
				}

				$existing = $this->spaces->get_space_by_forum( $forum_id );
				$group_ids = $this->asgaros->get_forum_group_ids( $forum_id );

				$result[] = array(
					'forum_id'        => $forum_id,
					'name'            => (string) ( $forum['name'] ?? '' ),
					'category_id'     => (int) ( $forum['category_id'] ?? 0 ),
					'group_ids'       => $group_ids,
					'is_registered'   => null !== $existing,
					'space_id'        => $existing ? $existing->id : 0,
					'can_register'    => null === $existing && ! empty( $group_ids ),
				);
			}

			return $result;
		}

		/**
		 * @param int $forum_id Forum-ID.
		 * @param int $actor_user_id Benutzer-ID.
		 * @return Space
		 */
		public function register_existing_forum( int $forum_id, int $actor_user_id ): Space {
			if ( ! $this->can_register_spaces( $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst keine bestehenden Foren als Raum registrieren.', 'afspaces' ) );
			}

			$forum = $this->asgaros->get_forum( $forum_id );
			if ( ! $forum ) {
				throw new DomainException( __( 'Das gewählte Forum existiert nicht.', 'afspaces' ) );
			}

			if ( $this->spaces->get_space_by_forum( $forum_id ) ) {
				throw new DomainException( __( 'Dieses Forum ist bereits als Raum registriert.', 'afspaces' ) );
			}

			$group_ids = $this->asgaros->get_forum_group_ids( $forum_id );
			if ( empty( $group_ids ) ) {
				throw new DomainException( __( 'Dieses Forum hat noch keine zugriffssteuernde Asgaros-Gruppe. Ordne zuerst der Kategorie eine Benutzergruppe zu.', 'afspaces' ) );
			}

			$space_id = $this->spaces->create_space(
				new Space(
					array(
						'forum_id'         => $forum_id,
						'primary_group_id' => (int) $group_ids[0],
						'owner_user_id'    => $actor_user_id,
						'visibility'       => 'private',
						'status'           => 'active',
					)
				)
			);

			$this->spaces->add_manager(
				new SpaceManager(
					array(
						'space_id' => $space_id,
						'user_id'  => $actor_user_id,
						'role'     => SpaceManager::ROLE_OWNER,
					)
				)
			);

			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Der Raum konnte nicht gespeichert werden.', 'afspaces' ) );
			}

			return $space;
		}

		private function can_register_spaces( int $actor_user_id ): bool {
			return user_can( $actor_user_id, Capabilities::CREATE_SPACE )
				|| user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES );
		}
	}
}