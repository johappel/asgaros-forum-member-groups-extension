<?php
/**
 * Raum-begrenzte Moderation von Forenthemen und -beiträgen (MVP 4, Option b).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\SpaceModerationService' ) ) {

	/**
	 * Ermöglicht Raumverantwortlichen die Moderation ausschließlich im eigenen Forum.
	 *
	 * Es werden bewusst KEINE globalen Asgaros-Moderatorrechte vergeben. Jede
	 * Aktion prüft zusätzlich, dass das betroffene Thema bzw. der Beitrag zum
	 * Forum des jeweiligen Space gehört (Objektberechtigung).
	 */
	class SpaceModerationService {

		private SpaceRepository $spaces;
		private AsgarosAdapterInterface $asgaros;
		private SpacePolicy $policy;
		private AuditRepository $audit;

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
		 * Prüft ohne Ausnahme, ob der Akteur den Space moderieren darf.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_moderate( int $space_id, int $actor_user_id ): bool {
			return $this->policy->can_moderate( $space_id, $actor_user_id );
		}

		/**
		 * Listet die Themen des Space-Forums für die Moderation.
		 *
		 * @param int                 $space_id      Space-ID.
		 * @param int                 $actor_user_id Akteur.
		 * @param array<string,mixed> $args          Optionen: page, per_page.
		 * @return array{topics: array<int,array<string,mixed>>, total: int}
		 * @throws DomainException Wenn der Akteur nicht moderieren darf.
		 */
		public function list_topics( int $space_id, int $actor_user_id, array $args = array() ): array {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			return $this->asgaros->list_forum_topics( $space->forum_id, $args );
		}

		/**
		 * Schließt ein Thema im eigenen Forum.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $topic_id      Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function close_topic( int $space_id, int $actor_user_id, int $topic_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			$this->asgaros->set_topic_closed( $topic_id, true );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_closed', 'topic' );
		}

		/**
		 * Öffnet ein Thema im eigenen Forum wieder.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $topic_id      Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function reopen_topic( int $space_id, int $actor_user_id, int $topic_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			$this->asgaros->set_topic_closed( $topic_id, false );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_reopened', 'topic' );
		}

		/**
		 * Löscht ein Thema im eigenen Forum (inklusive Beiträge).
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $topic_id      Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function delete_topic( int $space_id, int $actor_user_id, int $topic_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			$this->asgaros->delete_forum_topic( $topic_id );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_deleted', 'topic' );
		}

		/**
		 * Löscht einen einzelnen Beitrag im eigenen Forum.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $post_id       Beitrags-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Beitrag.
		 */
		public function delete_post( int $space_id, int $actor_user_id, int $post_id ): void {
			$space    = $this->require_moderatable_space( $space_id, $actor_user_id );
			$location = $this->asgaros->get_post_location( $post_id );
			if ( null === $location || (int) $location['forum_id'] !== $space->forum_id ) {
				throw new DomainException( __( 'Dieser Beitrag gehört nicht zu deinem Forum.', 'afspaces' ) );
			}
			$this->asgaros->delete_forum_post( $post_id );
			$this->audit->log( $space_id, $actor_user_id, $post_id, 'post_deleted', 'post' );
		}

		/**
		 * Listet die Beiträge eines Themas des eigenen Forums (Beitragsebene).
		 *
		 * @param int                 $space_id      Space-ID.
		 * @param int                 $actor_user_id Akteur.
		 * @param int                 $topic_id      Themen-ID.
		 * @param array<string,mixed> $args          Optionen: page, per_page.
		 * @return array{posts: array<int,array<string,mixed>>, total: int}
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function list_posts( int $space_id, int $actor_user_id, int $topic_id, array $args = array() ): array {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			return $this->asgaros->list_topic_posts( $topic_id, $args );
		}

		/**
		 * Verschiebt ein Thema in ein anderes Forum, das der Akteur ebenfalls verwaltet.
		 *
		 * @param int $space_id        Quell-Space-ID.
		 * @param int $actor_user_id   Akteur.
		 * @param int $topic_id        Themen-ID.
		 * @param int $target_space_id Ziel-Space-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung, fremdem Thema oder unzulässigem Ziel.
		 */
		public function move_topic( int $space_id, int $actor_user_id, int $topic_id, int $target_space_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );

			if ( $target_space_id === $space_id ) {
				throw new DomainException( __( 'Bitte wähle ein anderes Zielforum.', 'afspaces' ) );
			}

			// Auch das Zielforum muss der Akteur moderieren dürfen.
			$target = $this->require_moderatable_space( $target_space_id, $actor_user_id );

			$this->asgaros->move_topic( $topic_id, $target->forum_id );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_moved', 'topic' );
		}

		/**
		 * Verschiebt einen einzelnen Beitrag in ein anderes Thema (im eigenen
		 * oder einem ebenfalls verwalteten Forum).
		 *
		 * @param int $space_id        Quell-Space-ID.
		 * @param int $actor_user_id   Akteur.
		 * @param int $post_id         Beitrags-ID.
		 * @param int $target_topic_id Ziel-Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung, Eröffnungsbeitrag oder fremdem Ziel.
		 */
		public function move_post( int $space_id, int $actor_user_id, int $post_id, int $target_topic_id ): void {
			$space    = $this->require_moderatable_space( $space_id, $actor_user_id );
			$location = $this->asgaros->get_post_location( $post_id );
			if ( null === $location || (int) $location['forum_id'] !== $space->forum_id ) {
				throw new DomainException( __( 'Dieser Beitrag gehört nicht zu deinem Forum.', 'afspaces' ) );
			}
			if ( ! empty( $location['is_first'] ) ) {
				throw new DomainException( __( 'Der Eröffnungsbeitrag kann nicht in ein anderes Thema verschoben werden.', 'afspaces' ) );
			}
			if ( $target_topic_id < 1 || $target_topic_id === (int) $location['topic_id'] ) {
				throw new DomainException( __( 'Bitte wähle ein anderes Zielthema.', 'afspaces' ) );
			}

			$target_forum = $this->asgaros->get_topic_forum( $target_topic_id );
			if ( $target_forum < 1 ) {
				throw new DomainException( __( 'Das Zielthema wurde nicht gefunden.', 'afspaces' ) );
			}

			$target_space = $this->spaces->get_space_by_forum( $target_forum );
			if ( ! $target_space || ! $this->policy->can_moderate( (int) $target_space->id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst nicht in dieses Zielthema verschieben.', 'afspaces' ) );
			}

			$this->asgaros->move_post( $post_id, $target_topic_id, $target_forum );
			$this->audit->log( $space_id, $actor_user_id, $post_id, 'post_moved', 'post' );
		}

		/**
		 * Listet mögliche Zielthemen für das Verschieben eines Beitrags
		 * (weitere Themen desselben Forums).
		 *
		 * @param int $space_id         Space-ID.
		 * @param int $actor_user_id    Akteur.
		 * @param int $exclude_topic_id Auszuschließendes (aktuelles) Thema.
		 * @return array<int,array{topic_id:int, name:string}>
		 */
		public function list_post_move_targets( int $space_id, int $actor_user_id, int $exclude_topic_id ): array {
			$space  = $this->require_moderatable_space( $space_id, $actor_user_id );
			$result = $this->asgaros->list_forum_topics( $space->forum_id, array( 'per_page' => 100 ) );

			$targets = array();
			foreach ( ( $result['topics'] ?? array() ) as $topic ) {
				$topic_id = (int) ( $topic['id'] ?? 0 );
				if ( $topic_id < 1 || $topic_id === $exclude_topic_id ) {
					continue;
				}
				$targets[] = array(
					'topic_id' => $topic_id,
					'name'     => (string) ( $topic['name'] ?? ( 'Thema #' . $topic_id ) ),
				);
			}

			return $targets;
		}

		/**
		 * Listet die Foren, in die der Akteur Themen verschieben darf (seine verwalteten Räume).
		 *
		 * @param int $actor_user_id Akteur.
		 * @param int $exclude_space_id Optional auszuschließende Space-ID.
		 * @return array<int,array{space_id:int, forum_id:int, name:string}>
		 */
		public function list_move_targets( int $actor_user_id, int $exclude_space_id = 0 ): array {
			if ( user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES ) ) {
				$spaces = $this->spaces->list_spaces();
			} else {
				$spaces = array();
				foreach ( $this->spaces->list_manager_space_ids( $actor_user_id ) as $sid ) {
					$s = $this->spaces->get_space( $sid );
					if ( $s ) {
						$spaces[] = $s;
					}
				}
			}

			$targets = array();
			foreach ( $spaces as $s ) {
				if ( $s->id === $exclude_space_id || 'active' !== $s->status ) {
					continue;
				}
				$forum = $this->asgaros->get_forum( $s->forum_id );
				if ( empty( $forum ) ) {
					continue;
				}
				$targets[] = array(
					'space_id' => $s->id,
					'forum_id' => $s->forum_id,
					'name'     => (string) ( $forum['name'] ?? ( 'Forum #' . $s->forum_id ) ),
				);
			}

			return $targets;
		}

		/**
		 * Lädt den Space und stellt sicher, dass der Akteur moderieren darf.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return Space
		 * @throws DomainException Wenn der Space fehlt oder keine Berechtigung besteht.
		 */
		private function require_moderatable_space( int $space_id, int $actor_user_id ): Space {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space ) {
				throw new DomainException( __( 'Diese Arbeitsgruppe existiert nicht.', 'afspaces' ) );
			}
			if ( ! $this->policy->can_moderate( $space_id, $actor_user_id ) ) {
				throw new DomainException( __( 'Du darfst dieses Forum nicht moderieren.', 'afspaces' ) );
			}
			return $space;
		}

		/**
		 * Stellt sicher, dass ein Thema zum Forum des Space gehört.
		 *
		 * @param int   $topic_id Themen-ID.
		 * @param Space $space    Space.
		 * @return void
		 * @throws DomainException Wenn das Thema zu einem fremden Forum gehört.
		 */
		private function assert_topic_in_space( int $topic_id, Space $space ): void {
			if ( $topic_id < 1 || $this->asgaros->get_topic_forum( $topic_id ) !== $space->forum_id ) {
				throw new DomainException( __( 'Dieses Thema gehört nicht zu deinem Forum.', 'afspaces' ) );
			}
		}
	}
}
