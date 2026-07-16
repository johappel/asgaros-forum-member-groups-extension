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
	}
}
