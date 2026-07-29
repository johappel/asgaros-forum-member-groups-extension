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
		 * Listet die für den aktuellen Benutzer zugänglichen Foren (Arbeitsgruppen).
		 *
		 * @return array<int,array{id:int,name:string}>
		 */
		public function list_accessible_forums(): array;

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

		/**
		 * Erstellt eine dedizierte Asgaros-Forenkategorie für einen privaten Raum.
		 *
		 * @param array<string,mixed> $data Optionen: name, access (loggedin|everyone|moderator).
		 * @return int Term-ID der neuen Kategorie.
		 * @throws \AFSpaces\Core\DomainException Wenn die Erstellung fehlschlägt.
		 */
		public function create_forum_category( array $data ): int;

		/**
		 * Erstellt ein Asgaros-Forum in einer Kategorie.
		 *
		 * @param array<string,mixed> $data Optionen: category_id, name, description, icon.
		 * @return int Forum-ID.
		 * @throws \AFSpaces\Core\DomainException Wenn die Erstellung fehlschlägt.
		 */
		public function create_forum( array $data ): int;

		/**
		 * Erstellt eine Asgaros-Benutzergruppe.
		 *
		 * @param array<string,mixed> $data Optionen: name, color, icon.
		 * @return int Gruppen-Term-ID.
		 * @throws \AFSpaces\Core\DomainException Wenn die Erstellung fehlschlägt.
		 */
		public function create_group( array $data ): int;

		/**
		 * Ordnet einem Forum (über dessen Kategorie) eine Benutzergruppe zugriffssteuernd zu.
		 *
		 * @param int $forum_id Forum-ID.
		 * @param int $group_id Gruppen-ID.
		 * @return void
		 * @throws \AFSpaces\Core\DomainException Wenn die Zuordnung fehlschlägt.
		 */
		public function assign_group_to_forum( int $forum_id, int $group_id ): void;

		/**
		 * Setzt die Sichtbarkeit/Zugriffssteuerung eines Forums über dessen Kategorie.
		 *
		 * @param int                 $forum_id Forum-ID.
		 * @param array<string,mixed> $data     Optionen: access (everyone|loggedin|moderator),
		 *                                       restrict (bool), group_id (int).
		 * @return void
		 * @throws \AFSpaces\Core\DomainException Wenn die Änderung fehlschlägt.
		 */
		public function set_forum_visibility( int $forum_id, array $data ): void;

		/**
		 * Aktualisiert Stammdaten eines Forums (z. B. Name, Beschreibung).
		 *
		 * @param int                 $forum_id Forum-ID.
		 * @param array<string,mixed> $data     Zu aktualisierende Felder: name, description.
		 * @return void
		 * @throws \AFSpaces\Core\DomainException Wenn die Aktualisierung fehlschlägt.
		 */
		public function update_forum( int $forum_id, array $data ): void;

		/**
		 * Löscht ein Asgaros-Forum (für Rollback und endgültige Löschung).
		 *
		 * @param int $forum_id Forum-ID.
		 * @return void
		 */
		public function delete_forum( int $forum_id ): void;

		/**
		 * Löscht eine Asgaros-Forenkategorie.
		 *
		 * @param int $category_id Kategorie-Term-ID.
		 * @return void
		 */
		public function delete_forum_category( int $category_id ): void;

		/**
		 * Löscht eine Asgaros-Benutzergruppe.
		 *
		 * @param int $group_id Gruppen-Term-ID.
		 * @return void
		 */
		public function delete_group( int $group_id ): void;

		/**
		 * Listet die Themen eines Forums (für die raum-begrenzte Moderation).
		 *
		 * @param int                 $forum_id Forum-ID.
		 * @param array<string,mixed> $args     Optionen: page, per_page.
		 * @return array{topics: array<int,array<string,mixed>>, total: int}
		 */
		public function list_forum_topics( int $forum_id, array $args = [] ): array;

		/**
		 * Gibt die Forum-ID (parent) eines Themas zurück (0, falls unbekannt).
		 *
		 * Dient der Eigentümerprüfung, damit nur Themen des eigenen Forums
		 * moderiert werden können.
		 *
		 * @param int $topic_id Themen-ID.
		 * @return int
		 */
		public function get_topic_forum( int $topic_id ): int;

		/**
		 * Öffnet oder schließt ein Thema.
		 *
		 * @param int  $topic_id Themen-ID.
		 * @param bool $closed   true = schließen, false = öffnen.
		 * @return void
		 */
		public function set_topic_closed( int $topic_id, bool $closed ): void;

		/**
		 * Löscht ein Thema inklusive aller Beiträge (nutzt die Asgaros-Kernlogik).
		 *
		 * @param int $topic_id Themen-ID.
		 * @return void
		 */
		public function delete_forum_topic( int $topic_id ): void;

		/**
		 * Gibt Themen- und Forum-ID eines Beitrags zurück (für die Eigentümerprüfung).
		 *
		 * @param int $post_id Beitrags-ID.
		 * @return array{topic_id:int, forum_id:int, is_first:bool}|null
		 */
		public function get_post_location( int $post_id ): ?array;

		/**
		 * Löscht einen einzelnen Beitrag (nutzt die Asgaros-Kernlogik).
		 *
		 * @param int $post_id Beitrags-ID.
		 * @return void
		 */
		public function delete_forum_post( int $post_id ): void;
	}
}
