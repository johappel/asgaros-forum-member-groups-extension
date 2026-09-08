<?php
/**
 * Schmaler Vertrag für den lesenden Zugriff auf Asgaros-Anhänge.
 *
 * Bewusst getrennt vom umfangreichen {@see AsgarosAdapterInterface}
 * (Interface-Segregation): Der Dokumentdienst benötigt nur diese wenigen,
 * lesenden Zugriffe auf bestehende Asgaros-Uploads und -Beiträge. So bleiben
 * die vorhandenen Test-Stubs des Haupt-Interfaces unberührt.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Adapters\Asgaros;

if ( ! interface_exists( 'AFSpaces\\Adapters\\Asgaros\\DocumentSourceInterface' ) ) {

	/**
	 * Kapselt den lesenden Zugriff auf die bestehende Asgaros-Upload-Struktur.
	 */
	interface DocumentSourceInterface {

		/**
		 * Gibt die tatsächlich vorhandenen Anhang-Dateinamen eines Beitrags zurück.
		 *
		 * Liest ausschließlich das bestehende Feld `forum_posts.uploads` und
		 * filtert auf physisch vorhandene Dateien.
		 *
		 * @param int $post_id Asgaros-Beitrags-ID.
		 * @return string[] Liste von Dateinamen (ohne Pfad).
		 */
		public function get_post_uploads( int $post_id ): array;

		/**
		 * Prüft, ob ein Anhang mit diesem Dateinamen am Beitrag existiert.
		 *
		 * Prüft sowohl die Registrierung in `forum_posts.uploads` als auch die
		 * physische Existenz der Datei.
		 *
		 * @param int    $post_id  Asgaros-Beitrags-ID.
		 * @param string $filename Dateiname (ohne Pfad).
		 * @return bool
		 */
		public function post_upload_exists( int $post_id, string $filename ): bool;

		/**
		 * Baut die deterministische Download-URL eines Anhangs.
		 *
		 * @param int    $post_id  Asgaros-Beitrags-ID.
		 * @param string $filename Dateiname (ohne Pfad).
		 * @return string Leerer String, wenn die Datei nicht existiert.
		 */
		public function get_upload_file_url( int $post_id, string $filename ): string;

		/**
		 * Ermittelt den Kontext eines Beitrags (für Herkunftsanzeige).
		 *
		 * @param int $post_id Asgaros-Beitrags-ID.
		 * @return array{topic_id:int,forum_id:int,topic_name:string,author_id:int}|null
		 */
		public function resolve_post_context( int $post_id ): ?array;

		/**
		 * Baut den Deep-Link zu einem einzelnen Beitrag.
		 *
		 * @param int $post_id  Beitrags-ID.
		 * @param int $topic_id Themen-ID.
		 * @return string
		 */
		public function get_post_link( int $post_id, int $topic_id ): string;

		/**
		 * Prüft, ob ein Benutzer Mitglied einer bestimmten Gruppe ist.
		 *
		 * @param int $user_id  Benutzer-ID.
		 * @param int $group_id Gruppen-ID.
		 * @return bool
		 */
		public function is_user_in_group( int $user_id, int $group_id ): bool;
	}
}
