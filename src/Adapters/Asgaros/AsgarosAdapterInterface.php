<?php
/**
 * Vertrag für die Asgaros-Integration.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Asgaros;

if ( ! interface_exists( 'AFSpaces\\Adapters\\Asgaros\\AsgarosAdapterInterface' ) ) {

	/**
	 * Definiert die von der Domain benötigte Asgaros-Schnittstelle.
	 */
	interface AsgarosAdapterInterface {

		/**
		 * Gibt zurück, ob Asgaros verfügbar und kompatibel ist.
		 *
		 * @return bool
		 */
		public function is_available(): bool;

		/**
		 * Gibt die erkannte Asgaros-Version zurück.
		 *
		 * @return string|null
		 */
		public function get_version(): ?string;

		/**
		 * Listet die Foren auf, die der Akteur verwalten darf.
		 *
		 * @param int $actor_user_id WordPress-Benutzer-ID.
		 * @return array<int,array<string,mixed>>
		 */
		public function list_manageable_forums( int $actor_user_id ): array;

		/**
		 * Gibt ein einzelnes Forum zurück.
		 *
		 * @param int $forum_id Forum-ID.
		 * @return array<string,mixed>|null
		 */
		public function get_forum( int $forum_id ): ?array;

		/**
		 * Gibt die zugeordneten Benutzergruppen-IDs eines Forums zurück.
		 *
		 * @param int $forum_id Forum-ID.
		 * @return int[]
		 */
		public function get_forum_group_ids( int $forum_id ): array;

		/**
		 * Listet die Mitglieder einer Benutzergruppe paginiert.
		 *
		 * @param int   $group_id Gruppen-ID.
		 * @param array $args     Optionen: page, per_page, search.
		 * @return array<int,array<string,mixed>>
		 */
		public function list_group_members( int $group_id, array $args = [] ): array;

		/**
		 * Fügt einen Benutzer einer Gruppe hinzu.
		 *
		 * @param int $user_id  Benutzer-ID.
		 * @param int $group_id Gruppen-ID.
		 * @return void
		 * @throws \AFSpaces\Core\DomainException Wenn das Hinzufügen fehlschlägt.
		 */
		public function add_user_to_group( int $user_id, int $group_id ): void;

		/**
		 * Entfernt einen Benutzer aus einer Gruppe.
		 *
		 * @param int $user_id  Benutzer-ID.
		 * @param int $group_id Gruppen-ID.
		 * @return void
		 * @throws \AFSpaces\Core\DomainException Wenn das Entfernen fehlschlägt.
		 */
		public function remove_user_from_group( int $user_id, int $group_id ): void;

		/**
		 * Prüft, ob ein Benutzer Mitglied einer bestimmten Gruppe ist.
		 *
		 * @param int $user_id Benutzer-ID.
		 * @param int $group_id Gruppen-ID.
		 * @return bool
		 */
		public function is_user_in_group( int $user_id, int $group_id ): bool;

		/**
		 * Durchsucht Forenbeiträge post-genau per Volltext.
		 *
		 * Die Treffer werden NICHT nach Thema zusammengefasst: jeder gefundene
		 * Beitrag bleibt ein eigenständiges Ergebnis mit eigener `post_id` und
		 * eigenem Deep-Link. Es werden ausschließlich Beiträge aus den für den
		 * aktuellen Benutzer zugänglichen Kategorien berücksichtigt; nicht
		 * freigegebene Themen werden ausgeschlossen.
		 *
		 * @param string              $keywords Suchbegriff(e).
		 * @param array<string,mixed> $args     Optionen: sort ('relevance'|'date'),
		 *                                       page (int), per_page (int).
		 * @return array{results: array<int,array<string,mixed>>, total: int}
		 */
		public function search_posts( string $keywords, array $args = [] ): array;

		/**
		 * Baut den Deep-Link zu einem einzelnen Beitrag.
		 *
		 * Verwendet dieselbe Sortierung und Seitengröße wie die Themenansicht
		 * und ergibt eine URL der Form `.../topic/<slug>/?part=<N>#postid-<ID>`.
		 *
		 * @param int $post_id  Beitrags-ID.
		 * @param int $topic_id Themen-ID.
		 * @return string
		 */
		public function get_post_link( int $post_id, int $topic_id ): string;

		/**
		 * Gibt die für den aktuellen Benutzer zugänglichen Kategorie-Term-IDs zurück.
		 *
		 * @return int[]
		 */
		public function list_accessible_category_ids(): array;

		/**
		 * Zählt alle Forenbeiträge (für die Indexierung).
		 *
		 * @return int
		 */
		public function count_all_posts(): int;

		/**
		 * Lädt Forenbeiträge inkl. Kontext für die Indexierung (batchweise).
		 *
		 * @param int $limit  Maximale Anzahl.
		 * @param int $offset Versatz.
		 * @return array<int,array<string,mixed>> Zeilen mit post_id, topic_id,
		 *         forum_id, category_id, is_private, author_id, post_date,
		 *         post_text, topic_name, forum_name.
		 */
		public function list_posts_for_index( int $limit, int $offset ): array;

		/**
		 * Gibt zurück, ob die aktuelle Anfrage die Asgaros-Suchansicht ist.
		 *
		 * Ermöglicht es, die eingebaute Asgaros-Suche durch die eigene
		 * Suchansicht zu ersetzen.
		 *
		 * @return bool
		 */
		public function is_search_request(): bool;
	}
}
