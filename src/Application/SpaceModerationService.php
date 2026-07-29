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
