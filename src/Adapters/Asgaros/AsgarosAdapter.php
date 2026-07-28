<?php
/**
 * Konkreter Asgaros-Adapter (gegen Asgaros Forum 3.4.0).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Asgaros;

use AFSpaces\Core\Capabilities;
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
	 * - `AsgarosForum::content->get_categories()` (liefert zugängliche Kategorien)
	 * - `AsgarosForum::rewrite->get_post_link( $post_id, $topic_id )` (Deep-Link mit `?part=N#postid-ID`)
	 * - Tabellen `$forum->tables->{posts,topics,forums}` mit FULLTEXT auf `posts.text` und `topics.name`
	 *   (Volltextsuche via `MATCH ... AGAINST ( ... IN BOOLEAN MODE )`).
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

			if ( user_can( $actor_user_id, Capabilities::MANAGE_ALL_SPACES )
				|| user_can( $actor_user_id, Capabilities::CREATE_SPACE ) ) {
				return $this->collect_forums( $forum );
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
				// Fallback: In manchen Frontend-Kontexten liefert Asgaros hier leer.
				$rows = $forum->db->get_results( "SELECT * FROM {$forum->tables->forums} ORDER BY id ASC;", ARRAY_A );
				if ( empty( $rows ) ) {
					return $result;
				}

				foreach ( $rows as $row ) {
					$result[] = $this->normalize_forum( $row, (int) ( $row['parent_id'] ?? 0 ) );
				}

				return $result;
			}

			foreach ( $categories as $category ) {
				$category_id = (int) ( $category->id ?? $category->term_id ?? 0 );
				if ( $category_id < 1 ) {
					continue;
				}

				$forums = $forum->get_forums( $category_id );
				if ( empty( $forums ) ) {
					continue;
				}
				foreach ( $forums as $f ) {
					$result[] = $this->normalize_forum( (array) $f, $category_id );
					$subforums = $forum->get_subforums( (int) $f->id );
					if ( ! empty( $subforums ) ) {
						foreach ( $subforums as $sf ) {
							$result[] = $this->normalize_forum( (array) $sf, $category_id );
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
				'slug'        => (string) ( $row['slug'] ?? '' ),
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

		/**
		 * Gibt die für den aktuellen Benutzer zugänglichen Kategorie-Term-IDs zurück.
		 *
		 * Nutzt `AsgarosForum::content->get_categories()`, das intern bereits die
		 * Zugriffsprüfung (Benutzergruppen, `category_access`) vornimmt. Damit
		 * verhält sich die Suche identisch zur regulären Asgaros-Sichtbarkeit.
		 *
		 * @param object $forum Asgaros-Forum-Instanz.
		 * @return int[]
		 */
		private function accessible_category_ids( object $forum ): array {
			if ( ! isset( $forum->content ) || ! method_exists( $forum->content, 'get_categories' ) ) {
				return array();
			}

			$categories = $forum->content->get_categories();
			if ( empty( $categories ) ) {
				return array();
			}

			$ids = array();
			foreach ( $categories as $category ) {
				$term_id = (int) ( $category->term_id ?? $category->id ?? 0 );
				if ( $term_id > 0 ) {
					$ids[] = $term_id;
				}
			}

			return array_values( array_unique( $ids ) );
		}

		/**
		 * {@inheritDoc}
		 */
		public function search_posts( string $keywords, array $args = [] ): array {
			$empty = array(
				'results' => array(),
				'total'   => 0,
			);

			$forum = $this->forum();
			if ( null === $forum ) {
				return $empty;
			}

			$keywords = trim( sanitize_text_field( $keywords ) );
			if ( '' === $keywords ) {
				return $empty;
			}

			$category_ids = $this->accessible_category_ids( $forum );
			if ( empty( $category_ids ) ) {
				return $empty;
			}

			$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
			$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
			$sort     = ( isset( $args['sort'] ) && 'date' === $args['sort'] ) ? 'date' : 'relevance';
			$offset   = ( $page - 1 ) * $per_page;

			$db     = $forum->db;
			$posts  = $forum->tables->posts;
			$topics = $forum->tables->topics;
			$forums = $forum->tables->forums;

			// Kategorie-IDs sind bereits zu int normalisiert und daher sicher.
			$cats_csv = implode( ',', array_map( 'intval', $category_ids ) );

			// Boolean-Modus mit Präfixsuche, analog zur Asgaros-Bestandssuche.
			$match_term = $keywords . '*';

			// Vereinigt Treffer aus Beitragstexten (post-genau) und Thementiteln
			// (dem Eröffnungsbeitrag des Themas zugeordnet). Titeltreffer werden
			// stärker gewichtet.
			$hits_sql =
				"SELECT hits.post_id AS post_id, MAX(hits.score) AS score FROM ("
				. "SELECT p.id AS post_id, MATCH(p.text) AGAINST (%s IN BOOLEAN MODE) AS score "
				. "FROM {$posts} p "
				. "INNER JOIN {$topics} t ON p.parent_id = t.id "
				. "INNER JOIN {$forums} f ON t.parent_id = f.id "
				. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) "
				. "AND MATCH(p.text) AGAINST (%s IN BOOLEAN MODE) "
				. "UNION ALL "
				. "SELECT (SELECT MIN(p2.id) FROM {$posts} p2 WHERE p2.parent_id = t.id) AS post_id, "
				. "MATCH(t.name) AGAINST (%s IN BOOLEAN MODE) * 2 AS score "
				. "FROM {$topics} t "
				. "INNER JOIN {$forums} f ON t.parent_id = f.id "
				. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) "
				. "AND MATCH(t.name) AGAINST (%s IN BOOLEAN MODE)"
				. ") AS hits WHERE hits.post_id IS NOT NULL GROUP BY hits.post_id";

			// Gesamtzahl der eindeutigen Beitragstreffer.
			$count_sql   = "SELECT COUNT(*) FROM ({$hits_sql}) AS counted";
			$total       = (int) $db->get_var(
				$db->prepare( $count_sql, $match_term, $match_term, $match_term, $match_term )
			);

			if ( 0 === $total ) {
				return $empty;
			}

			$order_by = ( 'date' === $sort )
				? 'p.date DESC, p.id DESC'
				: 'hits.score DESC, p.date DESC, p.id DESC';

			$page_sql =
				"SELECT p.id AS post_id, p.parent_id AS topic_id, p.forum_id AS forum_id, "
				. "p.author_id AS author_id, p.text AS post_text, p.date AS post_date, "
				. "t.name AS topic_name, f.name AS forum_name, hits.score AS score "
				. "FROM ({$hits_sql}) AS hits "
				. "INNER JOIN {$posts} p ON p.id = hits.post_id "
				. "INNER JOIN {$topics} t ON p.parent_id = t.id "
				. "INNER JOIN {$forums} f ON t.parent_id = f.id "
				. "ORDER BY {$order_by} LIMIT %d, %d";

			$rows = $db->get_results(
				$db->prepare(
					$page_sql,
					$match_term,
					$match_term,
					$match_term,
					$match_term,
					$offset,
					$per_page
				),
				ARRAY_A
			);

			$results = array();
			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$post_id  = (int) ( $row['post_id'] ?? 0 );
					$topic_id = (int) ( $row['topic_id'] ?? 0 );
					if ( $post_id < 1 || $topic_id < 1 ) {
						continue;
					}

					$results[] = array(
						'post_id'    => $post_id,
						'topic_id'   => $topic_id,
						'forum_id'   => (int) ( $row['forum_id'] ?? 0 ),
						'author_id'  => (int) ( $row['author_id'] ?? 0 ),
						'post_text'  => (string) ( $row['post_text'] ?? '' ),
						'post_date'  => (string) ( $row['post_date'] ?? '' ),
						'topic_name' => (string) ( $row['topic_name'] ?? '' ),
						'forum_name' => (string) ( $row['forum_name'] ?? '' ),
						'score'      => (float) ( $row['score'] ?? 0 ),
						'url'        => $this->get_post_link( $post_id, $topic_id ),
					);
				}
			}

			return array(
				'results' => $results,
				'total'   => $total,
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_post_link( int $post_id, int $topic_id ): string {
			$forum = $this->forum();
			if ( null === $forum || ! isset( $forum->rewrite ) || ! method_exists( $forum->rewrite, 'get_post_link' ) ) {
				return '';
			}

			$topic = $topic_id > 0 ? $topic_id : false;

			return (string) $forum->rewrite->get_post_link( $post_id, $topic );
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_accessible_category_ids(): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}
			return $this->accessible_category_ids( $forum );
		}

		/**
		 * {@inheritDoc}
		 */
		public function count_all_posts(): int {
			$forum = $this->forum();
			if ( null === $forum ) {
				return 0;
			}
			return (int) $forum->db->get_var( "SELECT COUNT(*) FROM {$forum->tables->posts}" );
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_posts_for_index( int $limit, int $offset ): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}

			$limit  = max( 1, $limit );
			$offset = max( 0, $offset );

			$db     = $forum->db;
			$posts  = $forum->tables->posts;
			$topics = $forum->tables->topics;
			$forums = $forum->tables->forums;

			$sql =
				"SELECT p.id AS post_id, p.parent_id AS topic_id, p.forum_id AS forum_id, "
				. "p.author_id AS author_id, p.text AS post_text, p.date AS post_date, "
				. "t.name AS topic_name, t.approved AS approved, "
				. "f.name AS forum_name, f.parent_id AS category_id, f.forum_status AS forum_status "
				. "FROM {$posts} p "
				. "INNER JOIN {$topics} t ON p.parent_id = t.id "
				. "INNER JOIN {$forums} f ON t.parent_id = f.id "
				. "WHERE t.approved = 1 "
				. "ORDER BY p.id ASC LIMIT %d OFFSET %d";

			$rows = $db->get_results( $db->prepare( $sql, $limit, $offset ), ARRAY_A );
			if ( empty( $rows ) ) {
				return array();
			}

			$result = array();
			foreach ( $rows as $row ) {
				$result[] = array(
					'post_id'    => (int) ( $row['post_id'] ?? 0 ),
					'topic_id'   => (int) ( $row['topic_id'] ?? 0 ),
					'forum_id'   => (int) ( $row['forum_id'] ?? 0 ),
					'category_id' => (int) ( $row['category_id'] ?? 0 ),
					'is_private' => ( 'private' === (string) ( $row['forum_status'] ?? '' ) ),
					'author_id'  => (int) ( $row['author_id'] ?? 0 ),
					'post_date'  => (string) ( $row['post_date'] ?? '' ),
					'post_text'  => (string) ( $row['post_text'] ?? '' ),
					'topic_name' => (string) ( $row['topic_name'] ?? '' ),
					'forum_name' => (string) ( $row['forum_name'] ?? '' ),
				);
			}

			return $result;
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_search_request(): bool {
			$forum = $this->forum();
			if ( null === $forum || ! isset( $forum->current_view ) ) {
				return false;
			}
			return 'search' === $forum->current_view;
		}
	}
}