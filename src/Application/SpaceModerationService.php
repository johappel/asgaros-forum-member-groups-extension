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
use AFSpaces\Core\ForumManagementSettings;
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
		 * Prüft die kombinierte Policy für zusätzliche Foren.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @return bool
		 */
		public function can_create_forum( int $space_id, int $actor_user_id ): bool {
			return ForumManagementSettings::group_managers_can_create_forums()
				&& $this->policy->can_moderate( $space_id, $actor_user_id );
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
			$forum_ids = $this->forum_ids_for_space( $space );
			$all_topics = array();
			foreach ( $forum_ids as $forum_id ) {
				$page = 1;
				do {
					$result = $this->asgaros->list_forum_topics( $forum_id, array( 'page' => $page, 'per_page' => 100 ) );
					foreach ( (array) ( $result['topics'] ?? array() ) as $topic ) {
						$topic['forum_id'] = $forum_id;
						$all_topics[] = $topic;
					}
					$total_forum = (int) ( $result['total'] ?? 0 );
					$page++;
				} while ( ! empty( $result['topics'] ) && count( $all_topics ) < 10000 && ( $page - 1 ) * 100 < $total_forum );
			}

			usort(
				$all_topics,
				static function ( array $left, array $right ): int {
					$sticky = (int) ! empty( $right['sticky'] ) <=> (int) ! empty( $left['sticky'] );
					if ( 0 !== $sticky ) {
						return $sticky;
					}
					$date = strcmp( (string) ( $right['last_date'] ?? '' ), (string) ( $left['last_date'] ?? '' ) );
					return 0 !== $date ? $date : ( (int) ( $right['id'] ?? 0 ) <=> (int) ( $left['id'] ?? 0 ) );
				}
			);

			$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
			$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
			return array(
				'topics' => array_slice( $all_topics, ( $page - 1 ) * $per_page, $per_page ),
				'total'  => count( $all_topics ),
			);
		}

		/**
		 * Legt ein zusätzliches Forum ausschließlich im eigenen Space an.
		 *
		 * @param int    $space_id      Space-ID.
		 * @param int    $actor_user_id Akteur.
		 * @param string $name          Forumname.
		 * @param string $description   Optionale Beschreibung.
		 * @return int Neue Forum-ID.
		 * @throws DomainException Bei fehlender Berechtigung oder ungültigen Daten.
		 */
		public function create_forum( int $space_id, int $actor_user_id, string $name, string $description = '' ): int {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			if ( ! ForumManagementSettings::group_managers_can_create_forums() ) {
				throw new DomainException( __( 'Das Anlegen zusätzlicher Foren ist derzeit nicht freigegeben.', 'afspaces' ) );
			}

			$name = trim( sanitize_text_field( $name ) );
			if ( '' === $name || strlen( $name ) > 120 ) {
				throw new DomainException( __( 'Bitte gib einen gültigen Forumname mit höchstens 120 Zeichen ein.', 'afspaces' ) );
			}
			$description = sanitize_textarea_field( $description );
			$primary = $this->asgaros->get_forum( $space->forum_id );
			$category_id = (int) ( $primary['category_id'] ?? 0 );
			if ( $category_id < 1 || $space->primary_group_id < 1 ) {
				throw new DomainException( __( 'Das Forum der Arbeitsgruppe ist nicht vollständig zugeordnet.', 'afspaces' ) );
			}

			$forum_id = 0;
			try {
				$forum_id = $this->asgaros->create_forum(
					array(
						'category_id' => $category_id,
						'name'        => $name,
						'description' => $description,
						'icon'        => 'fas fa-comments',
					)
				);
				$this->asgaros->assign_group_to_forum( $forum_id, $space->primary_group_id );
				$this->spaces->add_forum_to_space( $space_id, $forum_id );
			} catch ( \Throwable $e ) {
				if ( $forum_id > 0 ) {
					try {
						$this->asgaros->delete_forum( $forum_id );
					} catch ( \Throwable $ignored ) {
						unset( $ignored );
					}
				}
				if ( $e instanceof DomainException ) {
					throw $e;
				}
				throw new DomainException( __( 'Das zusätzliche Forum konnte nicht angelegt werden.', 'afspaces' ) );
			}

			$this->audit->log( $space_id, $actor_user_id, $forum_id, 'forum_created', 'forum' );
			return $forum_id;
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
		 * Hält ein Thema im eigenen Forum oben.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $topic_id      Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function pin_topic( int $space_id, int $actor_user_id, int $topic_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			$this->asgaros->set_topic_pinned( $topic_id, true );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_pinned', 'topic' );
		}

		/**
		 * Löst ein Thema im eigenen Forum.
		 *
		 * @param int $space_id      Space-ID.
		 * @param int $actor_user_id Akteur.
		 * @param int $topic_id      Themen-ID.
		 * @return void
		 * @throws DomainException Bei fehlender Berechtigung oder fremdem Thema.
		 */
		public function unpin_topic( int $space_id, int $actor_user_id, int $topic_id ): void {
			$space = $this->require_moderatable_space( $space_id, $actor_user_id );
			$this->assert_topic_in_space( $topic_id, $space );
			$this->asgaros->set_topic_pinned( $topic_id, false );
			$this->audit->log( $space_id, $actor_user_id, $topic_id, 'topic_unpinned', 'topic' );
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
			if ( null === $location || ! in_array( (int) $location['forum_id'], $this->forum_ids_for_space( $space ), true ) ) {
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
			if ( null === $location || ! in_array( (int) $location['forum_id'], $this->forum_ids_for_space( $space ), true ) ) {
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
			if ( $topic_id < 1 || ! in_array( $this->asgaros->get_topic_forum( $topic_id ), $this->forum_ids_for_space( $space ), true ) ) {
				throw new DomainException( __( 'Dieses Thema gehört nicht zu deinem Forum.', 'afspaces' ) );
			}
		}

		/**
		 * @param Space $space Space.
		 * @return int[]
		 */
		private function forum_ids_for_space( Space $space ): array {
			$ids = $this->spaces->list_forum_ids( $space->id );
			if ( empty( $ids ) ) {
				$ids = array( $space->forum_id );
			}
			return array_values( array_unique( array_map( 'intval', $ids ) ) );
		}
	}
}
