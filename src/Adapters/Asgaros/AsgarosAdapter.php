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
use AFSpaces\Application\UserIdentityService;

if ( ! class_exists( 'AFSpaces\\Adapters\\Asgaros\\AsgarosAdapter' ) ) {

	/**
	 * Kapselt alle Asgaros-internen Aufrufe hinter dem Adaptervertrag.
	 *
	 * Verwendete interne Asgaros-APIs (geprüft gegen 3.4.0):
	 * - Klasse `AsgarosForum` (Singleton-Instanz über globale Variable `$asgarosforum`).
	 * - `AsgarosForumUserGroups::getUserGroupsIDsOfForumCategory( $category_id )`
	 * - `AsgarosForumUserGroups::get_users_in_usergroup( $group_id )`
	 * - `get_term( $group_id, $taxonomy )` für die Anzeige des Gruppennamens
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
		 * Zentrale Benutzeridentität.
		 *
		 * @var UserIdentityService
		 */
		private UserIdentityService $identity;

		/**
		 * Konstruktor.
		 *
		 * @param Requirements        $requirements Anforderungsprüfer.
		 * @param UserIdentityService|null $identity Benutzeridentität.
		 */
		public function __construct( Requirements $requirements, ?UserIdentityService $identity = null ) {
			$this->requirements = $requirements;
			$this->identity     = $identity ?: new UserIdentityService();
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
		public function get_group_name( int $group_id ): ?string {
			if ( $group_id < 1 || ! function_exists( 'get_term' ) ) {
				return null;
			}

			$taxonomy = apply_filters( 'asgarosforum_filter_user_groups_taxonomy_name', 'asgarosforum-usergroup' );
			$term     = get_term( $group_id, $taxonomy );
			if ( is_wp_error( $term ) || ! $term instanceof \WP_Term ) {
				return null;
			}

			$name = trim( (string) $term->name );
			return '' === $name ? null : $name;
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
					'display_name' => $this->identity->get_display_name( (int) $user->ID ),
					'user_login'   => (string) ( $user->user_login ?? '' ),
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
				$display_name = strtolower( $this->identity->get_display_name( (int) $user->ID ) );
				$user_login    = strtolower( (string) ( $user->user_login ?? '' ) );
				if ( false !== strpos( $display_name, $term ) || false !== strpos( $user_login, $term ) ) {
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

			// Optionale Filter (MVP 2): Autor, Forum/Arbeitsgruppe, Zeitraum.
			$author_id = isset( $args['author_id'] ) ? max( 0, (int) $args['author_id'] ) : 0;
			$forum_id  = isset( $args['forum_id'] ) ? max( 0, (int) $args['forum_id'] ) : 0;
			$date_from = isset( $args['date_from'] ) ? $this->normalize_date( (string) $args['date_from'], false ) : '';
			$date_to   = isset( $args['date_to'] ) ? $this->normalize_date( (string) $args['date_to'], true ) : '';

			// Suchqualität (MVP 3): Modus (alle/eines der Wörter) und Suchbereich (Titel/alles).
			$mode      = ( isset( $args['match_mode'] ) && \AFSpaces\Search\FulltextQuery::MODE_ALL === $args['match_mode'] )
				? \AFSpaces\Search\FulltextQuery::MODE_ALL
				: \AFSpaces\Search\FulltextQuery::MODE_ANY;
			$title_only = ( isset( $args['in'] ) && 'title' === $args['in'] );

			$db     = $forum->db;
			$posts  = $forum->tables->posts;
			$topics = $forum->tables->topics;
			$forums = $forum->tables->forums;

			// Kategorie-IDs sind bereits zu int normalisiert und daher sicher.
			$cats_csv = implode( ',', array_map( 'intval', $category_ids ) );

			// Text- und Titelzweig als (SQL, args) aufbauen – entweder per FULLTEXT
			// (Standard) oder per LIKE-Ersatzsuche für sehr kurze Suchbegriffe.
			$use_like = \AFSpaces\Search\FulltextQuery::needs_like_fallback( $keywords );
			$branches = array();
			$hits_args = array();

			if ( $use_like ) {
				$terms = \AFSpaces\Search\FulltextQuery::like_terms( $keywords );
				if ( empty( $terms ) ) {
					return $empty;
				}
				$glue = ( \AFSpaces\Search\FulltextQuery::MODE_ALL === $mode ) ? ' AND ' : ' OR ';

				if ( ! $title_only ) {
					$conds = array();
					foreach ( $terms as $term ) {
						$conds[]     = 'p.text LIKE %s';
						$hits_args[] = '%' . $db->esc_like( $term ) . '%';
					}
					$branches[] = "SELECT p.id AS post_id, 1 AS score FROM {$posts} p "
						. "INNER JOIN {$topics} t ON p.parent_id = t.id "
						. "INNER JOIN {$forums} f ON t.parent_id = f.id "
						. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) AND (" . implode( $glue, $conds ) . ')';
				}

				$conds = array();
				foreach ( $terms as $term ) {
					$conds[]     = 't.name LIKE %s';
					$hits_args[] = '%' . $db->esc_like( $term ) . '%';
				}
				$branches[] = "SELECT (SELECT MIN(p2.id) FROM {$posts} p2 WHERE p2.parent_id = t.id) AS post_id, 2 AS score "
					. "FROM {$topics} t INNER JOIN {$forums} f ON t.parent_id = f.id "
					. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) AND (" . implode( $glue, $conds ) . ')';
			} else {
				$boolean = \AFSpaces\Search\FulltextQuery::build( $keywords, $mode );
				if ( '' === $boolean ) {
					return $empty;
				}

				if ( ! $title_only ) {
					$branches[]  = "SELECT p.id AS post_id, MATCH(p.text) AGAINST (%s IN BOOLEAN MODE) AS score "
						. "FROM {$posts} p "
						. "INNER JOIN {$topics} t ON p.parent_id = t.id "
						. "INNER JOIN {$forums} f ON t.parent_id = f.id "
						. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) "
						. "AND MATCH(p.text) AGAINST (%s IN BOOLEAN MODE)";
					$hits_args[] = $boolean;
					$hits_args[] = $boolean;
				}

				$branches[]  = "SELECT (SELECT MIN(p2.id) FROM {$posts} p2 WHERE p2.parent_id = t.id) AS post_id, "
					. "MATCH(t.name) AGAINST (%s IN BOOLEAN MODE) * 2 AS score "
					. "FROM {$topics} t INNER JOIN {$forums} f ON t.parent_id = f.id "
					. "WHERE t.approved = 1 AND f.parent_id IN ({$cats_csv}) "
					. "AND MATCH(t.name) AGAINST (%s IN BOOLEAN MODE)";
				$hits_args[] = $boolean;
				$hits_args[] = $boolean;
			}

			$hits_sql =
				'SELECT hits.post_id AS post_id, MAX(hits.score) AS score FROM ('
				. implode( ' UNION ALL ', $branches )
				. ') AS hits WHERE hits.post_id IS NOT NULL GROUP BY hits.post_id';

			// Filter-WHERE für die äußere Ebene (gilt einheitlich für Text- und Titeltreffer).
			$filter_where = '';
			$filter_args  = array();
			if ( $author_id > 0 ) {
				$filter_where .= ' AND p.author_id = %d';
				$filter_args[] = $author_id;
			}
			if ( $forum_id > 0 ) {
				$filter_where .= ' AND p.forum_id = %d';
				$filter_args[] = $forum_id;
			}
			if ( '' !== $date_from ) {
				$filter_where .= ' AND p.date >= %s';
				$filter_args[] = $date_from;
			}
			if ( '' !== $date_to ) {
				$filter_where .= ' AND p.date <= %s';
				$filter_args[] = $date_to;
			}

			$outer_from =
				"FROM ({$hits_sql}) AS hits "
				. "INNER JOIN {$posts} p ON p.id = hits.post_id "
				. "INNER JOIN {$topics} t ON p.parent_id = t.id "
				. "INNER JOIN {$forums} f ON t.parent_id = f.id "
				. "WHERE 1 = 1{$filter_where}";

			// Gesamtzahl der eindeutigen, gefilterten Beitragstreffer.
			$count_sql = "SELECT COUNT(*) {$outer_from}";
			$total     = (int) $db->get_var(
				$db->prepare( $count_sql, ...array_merge( $hits_args, $filter_args ) )
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
				. "{$outer_from} "
				. "ORDER BY {$order_by} LIMIT %d, %d";

			$rows = $db->get_results(
				$db->prepare(
					$page_sql,
					...array_merge( $hits_args, $filter_args, array( $offset, $per_page ) )
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
		 * Normalisiert ein Datum (Y-m-d) zu einem MySQL-Zeitstempel.
		 *
		 * @param string $raw Rohwert.
		 * @param bool   $end true für Tagesende (23:59:59), sonst Tagesbeginn.
		 * @return string Leerstring bei ungültiger Eingabe.
		 */
		private function normalize_date( string $raw, bool $end ): string {
			$raw = trim( $raw );
			if ( '' === $raw || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
				return '';
			}
			return $raw . ( $end ? ' 23:59:59' : ' 00:00:00' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_accessible_forums(): array {
			$forum = $this->forum();
			if ( null === $forum ) {
				return array();
			}

			$category_ids = $this->accessible_category_ids( $forum );
			if ( empty( $category_ids ) ) {
				return array();
			}

			$cats_csv = implode( ',', array_map( 'intval', $category_ids ) );
			$rows     = $forum->db->get_results(
				"SELECT id, name FROM {$forum->tables->forums} WHERE parent_id IN ({$cats_csv}) ORDER BY name ASC",
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				return array();
			}

			$result = array();
			foreach ( $rows as $row ) {
				$id = (int) ( $row['id'] ?? 0 );
				if ( $id > 0 ) {
					$result[] = array(
						'id'   => $id,
						'name' => (string) ( $row['name'] ?? '' ),
					);
				}
			}

			return $result;
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

		/**
		 * {@inheritDoc}
		 */
		public function create_forum_category( array $data ): int {
			$this->assert_writable();

			$name   = trim( (string) ( $data['name'] ?? '' ) );
			$access = (string) ( $data['access'] ?? 'loggedin' );
			if ( '' === $name ) {
				throw new DomainException( __( 'Für die Kategorie wird ein Name benötigt.', 'afspaces' ) );
			}
			if ( ! in_array( $access, array( 'everyone', 'loggedin', 'moderator' ), true ) ) {
				$access = 'loggedin';
			}

			$term = wp_insert_term( $name, 'asgarosforum-category' );
			if ( is_wp_error( $term ) ) {
				throw new DomainException(
					sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Die Forenkategorie konnte nicht angelegt werden: %s', 'afspaces' ),
						$term->get_error_message()
					)
				);
			}

			$category_id = (int) $term['term_id'];
			update_term_meta( $category_id, 'category_access', $access );

			// Sortierung ans Ende stellen, damit Asgaros die Kategorie korrekt einordnet.
			$order = isset( $data['order'] ) ? (int) $data['order'] : ( time() % 100000 );
			update_term_meta( $category_id, 'order', $order );

			return $category_id;
		}

		/**
		 * {@inheritDoc}
		 */
		public function create_forum( array $data ): int {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || ! isset( $forum->content ) || ! method_exists( $forum->content, 'insert_forum' ) ) {
				throw new DomainException( __( 'Die Asgaros-Foren-API steht nicht zur Verfügung.', 'afspaces' ) );
			}

			$category_id = (int) ( $data['category_id'] ?? 0 );
			$name        = trim( (string) ( $data['name'] ?? '' ) );
			$description = (string) ( $data['description'] ?? '' );
			$icon        = (string) ( $data['icon'] ?? 'fas fa-comments' );
			$order       = isset( $data['order'] ) ? (int) $data['order'] : 1;

			if ( $category_id < 1 || '' === $name ) {
				throw new DomainException( __( 'Für das Forum werden Kategorie und Name benötigt.', 'afspaces' ) );
			}

			$forum_id = (int) $forum->content->insert_forum( $category_id, $name, $description, 0, $icon, $order );
			if ( $forum_id < 1 ) {
				throw new DomainException( __( 'Das Forum konnte nicht angelegt werden.', 'afspaces' ) );
			}

			return $forum_id;
		}

		/**
		 * {@inheritDoc}
		 */
		public function create_group( array $data ): int {
			$this->assert_writable();

			$name  = trim( (string) ( $data['name'] ?? '' ) );
			$color = (string) ( $data['color'] ?? '#2d5d7f' );
			$icon  = (string) ( $data['icon'] ?? '' );
			if ( '' === $name ) {
				throw new DomainException( __( 'Für die Benutzergruppe wird ein Name benötigt.', 'afspaces' ) );
			}

			// parent 0 = Gruppe ohne übergeordnete Usergroup-Kategorie.
			$result = \AsgarosForumUserGroups::insertUserGroup( 0, $name, $color, 'normal', 'no', $icon );
			if ( is_wp_error( $result ) ) {
				throw new DomainException(
					sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Die Benutzergruppe konnte nicht angelegt werden: %s', 'afspaces' ),
						$result->get_error_message()
					)
				);
			}

			// insertUserGroup gibt bei Erfolg das Ergebnis der letzten Meta-Operation
			// zurück; die Gruppen-ID ermitteln wir zuverlässig über den Namen.
			$taxonomy = apply_filters( 'asgarosforum_filter_user_groups_taxonomy_name', 'asgarosforum-usergroup' );
			$term     = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term instanceof \WP_Term ) {
				throw new DomainException( __( 'Die neue Benutzergruppe konnte nicht ermittelt werden.', 'afspaces' ) );
			}

			return (int) $term->term_id;
		}

		/**
		 * {@inheritDoc}
		 */
		public function assign_group_to_forum( int $forum_id, int $group_id ): void {
			$this->assert_writable();

			$forum_row = $this->get_forum( $forum_id );
			if ( null === $forum_row ) {
				throw new DomainException( __( 'Das Forum für die Gruppenzuordnung wurde nicht gefunden.', 'afspaces' ) );
			}

			$category_id = (int) ( $forum_row['category_id'] ?? 0 );
			if ( $category_id < 1 ) {
				throw new DomainException( __( 'Dem Forum ist keine Kategorie zugeordnet.', 'afspaces' ) );
			}

			$existing = \AsgarosForumUserGroups::getUserGroupsIDsOfForumCategory( $category_id );
			$existing = is_array( $existing ) ? array_map( 'intval', $existing ) : array();
			if ( ! in_array( $group_id, $existing, true ) ) {
				$existing[] = $group_id;
			}

			\AsgarosForumUserGroups::insertUserGroupsOfForumCategory( $category_id, array_values( array_unique( $existing ) ) );

			// Kategorie zugriffsbeschränkt halten, damit sie für Normalnutzer nicht offen ist.
			$access = get_term_meta( $category_id, 'category_access', true );
			if ( '' === (string) $access ) {
				update_term_meta( $category_id, 'category_access', 'loggedin' );
			}
		}

		/**
		 * {@inheritDoc}
		 */
		public function set_forum_visibility( int $forum_id, array $data ): void {
			$this->assert_writable();

			$forum_row = $this->get_forum( $forum_id );
			if ( null === $forum_row ) {
				throw new DomainException( __( 'Das Forum wurde nicht gefunden.', 'afspaces' ) );
			}

			$category_id = (int) ( $forum_row['category_id'] ?? 0 );
			if ( $category_id < 1 ) {
				throw new DomainException( __( 'Dem Forum ist keine Kategorie zugeordnet.', 'afspaces' ) );
			}

			$access = (string) ( $data['access'] ?? 'loggedin' );
			if ( ! in_array( $access, array( 'everyone', 'loggedin', 'moderator' ), true ) ) {
				$access = 'loggedin';
			}
			update_term_meta( $category_id, 'category_access', $access );

			$restrict = ! empty( $data['restrict'] );
			$group_id = (int) ( $data['group_id'] ?? 0 );

			$existing = \AsgarosForumUserGroups::getUserGroupsIDsOfForumCategory( $category_id );
			$existing = is_array( $existing ) ? array_map( 'intval', $existing ) : array();

			if ( $restrict && $group_id > 0 ) {
				if ( ! in_array( $group_id, $existing, true ) ) {
					$existing[] = $group_id;
				}
			} elseif ( $group_id > 0 ) {
				$existing = array_values( array_diff( $existing, array( $group_id ) ) );
			} elseif ( ! $restrict ) {
				$existing = array();
			}

			\AsgarosForumUserGroups::insertUserGroupsOfForumCategory( $category_id, array_values( array_unique( $existing ) ) );
		}

		/**
		 * {@inheritDoc}
		 */
		public function update_forum( int $forum_id, array $data ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum ) {
				throw new DomainException( __( 'Asgaros steht nicht zur Verfügung.', 'afspaces' ) );
			}

			$fields  = array();
			$formats = array();
			if ( array_key_exists( 'name', $data ) ) {
				$fields['name'] = (string) $data['name'];
				$formats[]      = '%s';
			}
			if ( array_key_exists( 'description', $data ) ) {
				$fields['description'] = (string) $data['description'];
				$formats[]             = '%s';
			}
			if ( array_key_exists( 'forum_status', $data ) ) {
				$fields['forum_status'] = (string) $data['forum_status'];
				$formats[]              = '%s';
			}

			if ( empty( $fields ) ) {
				return;
			}

			$forum->db->update(
				$forum->tables->forums,
				$fields,
				array( 'id' => $forum_id ),
				$formats,
				array( '%d' )
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function delete_forum( int $forum_id ): void {
			$forum = $this->forum();
			if ( null === $forum || $forum_id < 1 ) {
				return;
			}
			$forum->db->delete( $forum->tables->forums, array( 'id' => $forum_id ), array( '%d' ) );
		}

		/**
		 * {@inheritDoc}
		 */
		public function delete_forum_category( int $category_id ): void {
			if ( $category_id < 1 ) {
				return;
			}
			wp_delete_term( $category_id, 'asgarosforum-category' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function delete_group( int $group_id ): void {
			if ( $group_id < 1 || ! class_exists( '\\AsgarosForumUserGroups' ) ) {
				return;
			}
			\AsgarosForumUserGroups::deleteUserGroup( $group_id );
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_forum_topics( int $forum_id, array $args = [] ): array {
			$empty = array(
				'topics' => array(),
				'total'  => 0,
			);

			$forum = $this->forum();
			if ( null === $forum || $forum_id < 1 ) {
				return $empty;
			}

			$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
			$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
			$offset   = ( $page - 1 ) * $per_page;

			$db     = $forum->db;
			$topics = $forum->tables->topics;
			$posts  = $forum->tables->posts;

			$total = (int) $db->get_var(
				$db->prepare( "SELECT COUNT(*) FROM {$topics} WHERE parent_id = %d;", $forum_id )
			);
			if ( 0 === $total ) {
				return $empty;
			}

			$rows = $db->get_results(
				$db->prepare(
					"SELECT t.id AS id, t.name AS name, t.closed AS closed, t.sticky AS sticky, "
					. "t.author_id AS author_id, t.approved AS approved, "
					. "(SELECT COUNT(*) FROM {$posts} p WHERE p.parent_id = t.id) AS post_count, "
					. "(SELECT MAX(p.date) FROM {$posts} p WHERE p.parent_id = t.id) AS last_date "
					. "FROM {$topics} t WHERE t.parent_id = %d "
					. "ORDER BY t.sticky DESC, last_date DESC, t.id DESC LIMIT %d, %d;",
					$forum_id,
					$offset,
					$per_page
				),
				ARRAY_A
			);

			$topics_out = array();
			foreach ( (array) $rows as $row ) {
				$author_id = (int) ( $row['author_id'] ?? 0 );
				$topics_out[] = array(
					'id'          => (int) ( $row['id'] ?? 0 ),
					'name'        => (string) ( $row['name'] ?? '' ),
					'closed'      => 1 === (int) ( $row['closed'] ?? 0 ),
					'sticky'      => (int) ( $row['sticky'] ?? 0 ) > 0,
					'approved'    => 1 === (int) ( $row['approved'] ?? 1 ),
					'author_id'   => $author_id,
					'author_name' => $author_id > 0 ? $this->identity->get_display_name( $author_id ) : '',
					'post_count'  => (int) ( $row['post_count'] ?? 0 ),
					'last_date'   => (string) ( $row['last_date'] ?? '' ),
				);
			}

			return array(
				'topics' => $topics_out,
				'total'  => $total,
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_topic_forum( int $topic_id ): int {
			$forum = $this->forum();
			if ( null === $forum || $topic_id < 1 ) {
				return 0;
			}
			return (int) $forum->db->get_var(
				$forum->db->prepare( "SELECT parent_id FROM {$forum->tables->topics} WHERE id = %d;", $topic_id )
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function set_topic_closed( int $topic_id, bool $closed ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || $topic_id < 1 ) {
				return;
			}

			$forum->db->update(
				$forum->tables->topics,
				array( 'closed' => $closed ? 1 : 0 ),
				array( 'id' => $topic_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function delete_forum_topic( int $topic_id ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || $topic_id < 1 || ! method_exists( $forum, 'delete_topic' ) ) {
				return;
			}

			// admin_action = true: kein Redirect; permission_check = false: die
			// raum-begrenzte Berechtigung wurde bereits im Service geprüft.
			$forum->delete_topic( $topic_id, true, false );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_post_location( int $post_id ): ?array {
			$forum = $this->forum();
			if ( null === $forum || $post_id < 1 ) {
				return null;
			}

			$row = $forum->db->get_row(
				$forum->db->prepare(
					"SELECT id, parent_id AS topic_id, forum_id FROM {$forum->tables->posts} WHERE id = %d;",
					$post_id
				),
				ARRAY_A
			);
			if ( empty( $row ) ) {
				return null;
			}

			$topic_id = (int) ( $row['topic_id'] ?? 0 );
			$first_id = (int) $forum->db->get_var(
				$forum->db->prepare( "SELECT MIN(id) FROM {$forum->tables->posts} WHERE parent_id = %d;", $topic_id )
			);

			return array(
				'topic_id' => $topic_id,
				'forum_id' => (int) ( $row['forum_id'] ?? 0 ),
				'is_first' => $post_id === $first_id,
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function delete_forum_post( int $post_id ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || $post_id < 1 || ! method_exists( $forum, 'remove_post' ) ) {
				return;
			}

			$location = $this->get_post_location( $post_id );

			// Wird der Eröffnungsbeitrag oder der letzte verbleibende Beitrag
			// gelöscht, wird stattdessen das gesamte Thema entfernt, damit keine
			// leeren Themen zurückbleiben.
			if ( null !== $location ) {
				$topic_id   = (int) $location['topic_id'];
				$post_count = (int) $forum->db->get_var(
					$forum->db->prepare( "SELECT COUNT(*) FROM {$forum->tables->posts} WHERE parent_id = %d;", $topic_id )
				);
				if ( ! empty( $location['is_first'] ) || $post_count <= 1 ) {
					$this->delete_forum_topic( $topic_id );
					return;
				}
			}

			$forum->remove_post( $post_id, false );
		}

		/**
		 * {@inheritDoc}
		 */
		public function move_topic( int $topic_id, int $target_forum_id ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || $topic_id < 1 || $target_forum_id < 1 ) {
				return;
			}

			$forum->db->update(
				$forum->tables->topics,
				array( 'parent_id' => $target_forum_id ),
				array( 'id' => $topic_id ),
				array( '%d' ),
				array( '%d' )
			);
			$forum->db->update(
				$forum->tables->posts,
				array( 'forum_id' => $target_forum_id ),
				array( 'parent_id' => $topic_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function list_topic_posts( int $topic_id, array $args = [] ): array {
			$empty = array(
				'posts' => array(),
				'total' => 0,
			);

			$forum = $this->forum();
			if ( null === $forum || $topic_id < 1 ) {
				return $empty;
			}

			$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
			$per_page = isset( $args['per_page'] ) ? max( 1, min( 100, (int) $args['per_page'] ) ) : 20;
			$offset   = ( $page - 1 ) * $per_page;

			$db    = $forum->db;
			$posts = $forum->tables->posts;

			$total = (int) $db->get_var(
				$db->prepare( "SELECT COUNT(*) FROM {$posts} WHERE parent_id = %d;", $topic_id )
			);
			if ( 0 === $total ) {
				return $empty;
			}

			$rows = $db->get_results(
				$db->prepare(
					"SELECT id, text, author_id, date FROM {$posts} WHERE parent_id = %d ORDER BY id ASC LIMIT %d, %d;",
					$topic_id,
					$offset,
					$per_page
				),
				ARRAY_A
			);

			$first_id = (int) $db->get_var(
				$db->prepare( "SELECT MIN(id) FROM {$posts} WHERE parent_id = %d;", $topic_id )
			);

			$out = array();
			foreach ( (array) $rows as $row ) {
				$post_id   = (int) ( $row['id'] ?? 0 );
				$author_id = (int) ( $row['author_id'] ?? 0 );
				$out[]     = array(
					'id'          => $post_id,
					'text'        => (string) ( $row['text'] ?? '' ),
					'author_id'   => $author_id,
					'author_name' => $author_id > 0 ? $this->identity->get_display_name( $author_id ) : '',
					'date'        => (string) ( $row['date'] ?? '' ),
					'is_first'    => $post_id === $first_id,
				);
			}

			return array(
				'posts' => $out,
				'total' => $total,
			);
		}

		/**
		 * {@inheritDoc}
		 */
		public function move_post( int $post_id, int $target_topic_id, int $target_forum_id ): void {
			$this->assert_writable();

			$forum = $this->forum();
			if ( null === $forum || $post_id < 1 || $target_topic_id < 1 || $target_forum_id < 1 ) {
				return;
			}

			$forum->db->update(
				$forum->tables->posts,
				array(
					'parent_id' => $target_topic_id,
					'forum_id'  => $target_forum_id,
				),
				array( 'id' => $post_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		}
	}
}
