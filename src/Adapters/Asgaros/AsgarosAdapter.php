<?php
/**
 * Konkreter Asgaros-Adapter (gegen Asgaros Forum 3.4.0).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Asgaros;

use AFSpaces\Core\DomainException;
use AFSpaces\Core\Requirements;

if ( ! class_exists( 'AFSpaces\\Adapters\\Asgaros\\AsgarosAdapter' ) ) {

	/**
	 * Kapselt alle Asgaros-internen Aufrufe hinter dem Adaptervertrag.
	 *
	 * Verwendete interne Asgaros-APIs (geprüft gegen 3.4.0):
	 * - Klasse `AsgarosForum` (Singleton-Instanz über globale Variable `$asgarosforum`).
	 * - `AsgarosForumUserGroups::getUserGroupsIDsOfForumCategory( $category_id )`
	 * - `AsgarosForumUserGroups::get_users_in_usergroup( $group_id )`
	 * - `AsgarosForumUserGroups::isUserInUserGroup( $user_id, $group_id )`
	 * - `AsgarosForumUserGroups::insertUserGroupsOfUsers( $user_id, $group_ids )`
	 * - `AsgarosForumUserGroups::deleteUserGroupsOfUser( $user_id )`
	 * - `AsgarosForum::get_forums( $category_id )` und `get_subforums( $forum_id )`
	 * - `AsgarosForumUserGroups::$taxonomyName` (Term-Taxonomie `asgarosforum-usergroup`)
	 * - `AsgarosForumPermissions::isAdministrator( $user_id )`
	 *
	 * Gruppenmitgliedschaften werden in Asgaros als WP-Term-Zuordnung
	 * (Taxonomie `asgarosforum-usergroup`) an Benutzer gespeichert.
	 */
	class AsgarosAdapter implements AsgarosAdapterInterface {

		/**
		 * Anforderungsprüfer.
		 *
		 * @var Requirements
		 */
		private Requirements $requirements;

		/**
		 * Konstruktor.
		 *
		 * @param Requirements $requirements Anforderungsprüfer.
		 */
		public function __construct( Requirements $requirements ) {
			$this->requirements = $requirements;
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_available(): bool {
			return $this->requirements->is_asgaros_active()
				&& $this->requirements->is_asgaros_version_supported();
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_version(): ?string {
			return $this->requirements->get_asgaros_version();
		}

		/**
		 * Gibt die globale Asgaros-Forum-Instanz zurück.
		 *
		 * @return object|null
		 */
		private function forum(): ?object {
			global $asgarosforum;
			return isset( $asgarosforum ) ? $asgarosforum : null;
		}

		/**
		 * Wirft eine Domain-Ausnahme, wenn Asgaros nicht schreibbar ist.
		 *
		 * @return void
		 * @throws DomainException
		 */
		private function assert_writable(): void {
			if ( ! $this->is_available() ) {
				throw new DomainException(
					__( 'Asgaros Forum ist nicht verfügbar oder inkompatibel. Schreibvorgänge sind deaktiviert.', 'afspaces' )
				);
			}
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_manageable_forums( int $actor_user_id ): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}

			// Asgaros-Administratoren dürfen alle Foren verwalten.
			if ( $forum->permissions->isAdministrator( $actor_user_id ) ) {
				return $this->collect_forums( $forum );
			}

			// Für MVP 1 verwalten nur Asgaros-Administratoren Räume.
			// Space-spezifische Managerlogik folgt in M1.3/M1.4.
			return array();
		}

		/**
		 * Sammelt alle Foren (Kategorien + Foren + Unterforen) aus Asgaros.
		 *
		 * @param object $forum Asgaros-Forum-Instanz.
		 * @return array<int,array<string,mixed>>
		 */
		private function collect_forums( object $forum ): array {
			$result = array();

			$categories = $forum->content->get_categories( false );
			if ( empty( $categories ) ) {
				return $result;
			}

			foreach ( $categories as $category ) {
				$forums = $forum->get_forums( (int) $category->id );
				if ( empty( $forums ) ) {
					continue;
				}
				foreach ( $forums as $f ) {
					$result[] = $this->normalize_forum( (array) $f, (int) $category->id );
					$subforums = $forum->get_subforums( (int) $f->id );
					if ( ! empty( $subforums ) ) {
						foreach ( $subforums as $sf ) {
							$result[] = $this->normalize_forum( (array) $sf, (int) $category->id );
						}
					}
				}
			}

			return $result;
		}

		/**
		 * Normalisiert einen Asgaros-Forum-Datensatz.
		 *
		 * @param array $row         Rohdaten.
		 * @param int   $category_id Kategorie-ID.
		 * @return array<string,mixed>
		 */
		private function normalize_forum( array $row, int $category_id ): array {
			return array(
				'id'          => (int) ( $row['id'] ?? 0 ),
				'category_id' => $category_id,
				'name'        => $row['name'] ?? '',
				'parent_forum' => (int) ( $row['parent_forum'] ?? 0 ),
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_forum( int $forum_id ): ?array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return null;
			}

			$row = $forum->db->get_row(
				$forum->db->prepare( "SELECT * FROM {$forum->tables->forums} WHERE id = %d;", $forum_id ),
				ARRAY_A
			);

			if ( empty( $row ) ) {
				return null;
			}

			return $this->normalize_forum( $row, (int) ( $row['parent_id'] ?? 0 ) );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_forum_group_ids( int $forum_id ): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}

			// Foren sind Kategorien in Asgaros zugeordnet; die Gruppenzuordnung
			// liegt auf Kategorieebene (term_meta `usergroups`).
			$forum_row = $this->get_forum( $forum_id );
			if ( null === $forum_row ) {
				return array();
			}

			$ids = \AsgarosForumUserGroups::getUserGroupsIDsOfForumCategory( $forum_row['category_id'] );
			if ( empty( $ids ) ) {
				return array();
			}

			return array_map( 'intval', (array) $ids );
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_group_members( int $group_id, array $args = [] ): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}

			$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
			$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;

			$user_ids = \AsgarosForumUserGroups::get_ids_of_users_in_usergroup( $group_id );
			if ( empty( $user_ids ) ) {
				return array();
			}

			$search = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
			if ( '' !== $search ) {
				$user_ids = $this->filter_user_ids_by_search( $user_ids, $search );
			}

			$total = count( $user_ids );
			$offset = ( $page - 1 ) * $per_page;
			$paged_ids = array_slice( $user_ids, $offset, $per_page );

			$members = array();
			foreach ( $paged_ids as $user_id ) {
				$user = get_userdata( (int) $user_id );
				if ( ! $user ) {
					continue;
				}
				$members[] = array(
					'user_id'      => (int) $user->ID,
					'display_name' => $user->display_name,
					'user_login'   => $user->user_login,
				);
			}

			return array(
				'members' => $members,
				'total'   => $total,
				'page'    => $page,
				'per_page' => $per_page,
			);
		}

		/**
		 * Filtert Benutzer-IDs nach Anzeigename oder Login.
		 *
		 * @param int[]  $user_ids Zu filternde IDs.
		 * @param string $search   Suchbegriff.
		 * @return int[]
		 */
		private function filter_user_ids_by_search( array $user_ids, string $search ): array {
			$found = array();
			$term = strtolower( $search );
			foreach ( $user_ids as $user_id ) {
				$user = get_userdata( (int) $user_id );
				if ( ! $user ) {
					continue;
				}
				if ( false !== strpos( strtolower( $user->display_name ), $term )
					|| false !== strpos( strtolower( $user->user_login ), $term ) ) {
					$found[] = (int) $user_id;
				}
			}
			return $found;
		}

		/**
		 * {@inheritDoc}
		 */
		public function add_user_to_group( int $user_id, int $group_id ): void {
			$this->assert_writable();

			$current = \AsgarosForumUserGroups::getUserGroupsOfUser( $user_id, 'ids' );
			if ( in_array( $group_id, $current, true ) ) {
				// Idempotent: bereits Mitglied.
				return;
			}

			$current[] = $group_id;
			$result = \AsgarosForumUserGroups::insertUserGroupsOfUsers( $user_id, $current );
			if ( is_wp_error( $result ) ) {
				throw new DomainException(
					sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Benutzer konnte nicht hinzugefügt werden: %s', 'afspaces' ),
						$result->get_error_message()
					)
				);
			}
		}

		/**
		 * {@inheritDoc}
		 */
		public function remove_user_from_group( int $user_id, int $group_id ): void {
			$this->assert_writable();

			$current = \AsgarosForumUserGroups::getUserGroupsOfUser( $user_id, 'ids' );
			if ( ! in_array( $group_id, $current, true ) ) {
				// Idempotent: war nicht Mitglied.
				return;
			}

			$updated = array_values( array_diff( $current, array( $group_id ) ) );
			if ( empty( $updated ) ) {
				\AsgarosForumUserGroups::deleteUserGroupsOfUser( $user_id );
			} else {
				\AsgarosForumUserGroups::insertUserGroupsOfUsers( $user_id, $updated );
			}
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_user_in_group( int $user_id, int $group_id ): bool {
			if ( ! class_exists( '\\AsgarosForumUserGroups' ) ) {
				return false;
			}

			return (bool) \AsgarosForumUserGroups::isUserInUserGroup( $user_id, $group_id );
		}
	}
}
