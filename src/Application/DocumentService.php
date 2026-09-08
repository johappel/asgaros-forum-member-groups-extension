<?php
/**
 * Anwendungsdienst für Arbeitsgruppen-Dokumente.
 *
 * Ein Dokument ist eine zusätzliche Bedeutung „Gruppendokument“ für einen
 * bestehenden Asgaros-Anhang. Der Dienst kapselt Rechte-, Sichtbarkeits- und
 * Konsistenzprüfungen und trennt Dokument- von Forumsberechtigung.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Application;

use AFSpaces\Adapters\Asgaros\DocumentSourceInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceDocumentRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceDocument;
use AFSpaces\Domain\SpacePolicy;

if ( ! class_exists( 'AFSpaces\\Application\\DocumentService' ) ) {

	/**
	 * Kapselt Lese- und Verwaltungslogik für Gruppendokumente.
	 */
	class DocumentService {

		public const SORT_DATE_DESC = 'date_desc';
		public const SORT_DATE_ASC  = 'date_asc';
		public const SORT_NAME_ASC  = 'name_asc';
		public const SORT_NAME_DESC = 'name_desc';

		private SpaceRepository $spaces;
		private SpaceDocumentRepository $documents;
		private SpacePolicy $policy;
		private DocumentSourceInterface $source;
		private AuditRepository $audit;

		/**
		 * Konstruktor.
		 */
		public function __construct(
			SpaceRepository $spaces,
			SpaceDocumentRepository $documents,
			SpacePolicy $policy,
			DocumentSourceInterface $source,
			AuditRepository $audit
		) {
			$this->spaces    = $spaces;
			$this->documents = $documents;
			$this->policy    = $policy;
			$this->source    = $source;
			$this->audit     = $audit;
		}

		/**
		 * Listet die für den Akteur sichtbaren, gültigen Dokumente einer Gruppe.
		 *
		 * Verwaiste Datensätze (Beitrag/Datei gelöscht, Forum nicht mehr im
		 * Space) werden defensiv herausgefiltert.
		 *
		 * @param int                 $space_id Space-ID.
		 * @param int                 $actor    Handelnde Benutzer-ID.
		 * @param array<string,mixed> $filters  topic, search, sort.
		 * @return SpaceDocument[]
		 */
		public function list_documents( int $space_id, int $actor, array $filters = array() ): array {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space || 'active' !== $space->status ) {
				return array();
			}

			$topic_filter  = isset( $filters['topic'] ) ? SpaceDocument::sanitize_topic( (string) $filters['topic'] ) : '';
			$search_filter = isset( $filters['search'] ) ? $this->normalize_search( (string) $filters['search'] ) : '';
			$sort          = $this->normalize_sort( $filters['sort'] ?? '' );

			$documents = array();
			foreach ( $this->documents->list_for_space( $space_id ) as $document ) {
				if ( ! $this->is_document_valid( $document, $space ) ) {
					continue;
				}
				if ( ! $this->can_view_document( $document, $space, $actor ) ) {
					continue;
				}
				if ( '' !== $topic_filter && strcasecmp( $document->document_topic, $topic_filter ) !== 0 ) {
					continue;
				}
				if ( '' !== $search_filter && ! $this->matches_search( $document, $search_filter ) ) {
					continue;
				}
				$documents[] = $document;
			}

			return $this->sort_documents( $documents, $sort );
		}

		/**
		 * Gibt ein einzelnes Dokument zurück, wenn der Akteur es sehen darf.
		 *
		 * @param int $document_id Dokument-ID.
		 * @param int $actor       Handelnde Benutzer-ID.
		 * @return SpaceDocument|null
		 */
		public function get_document( int $document_id, int $actor ): ?SpaceDocument {
			$document = $this->documents->get( $document_id );
			if ( ! $document ) {
				return null;
			}

			$space = $this->spaces->get_space( $document->space_id );
			if ( ! $space || 'active' !== $space->status ) {
				return null;
			}

			if ( ! $this->is_document_valid( $document, $space ) ) {
				return null;
			}

			if ( ! $this->can_view_document( $document, $space, $actor ) ) {
				return null;
			}

			return $document;
		}

		/**
		 * Kennzeichnet einen bestehenden Asgaros-Anhang als Gruppendokument.
		 *
		 * @param int                 $space_id Space-ID.
		 * @param int                 $post_id  Asgaros-Beitrags-ID.
		 * @param string              $filename Dateiname des Anhangs.
		 * @param int                 $actor    Handelnde Benutzer-ID.
		 * @param array<string,mixed> $input    title, document_topic, visibility.
		 * @return int Dokument-ID (bestehende bei Idempotenz).
		 *
		 * @throws DomainException Bei ungültigem Ziel oder fehlender Berechtigung.
		 */
		public function create_document( int $space_id, int $post_id, string $filename, int $actor, array $input ): int {
			$filename = $this->require_valid_attachment( $space_id, $post_id, $filename, $space, $context );

			// Idempotenz: bereits vorhandenes Dokument zurückgeben.
			$existing = $this->documents->find_by_natural_key( $space_id, $post_id, $filename );
			if ( $existing ) {
				return $existing->id;
			}

			$is_manager = $this->policy->can_manage_documents( $space_id, $actor );
			$is_member  = $this->is_space_member( $space, $actor );
			$is_own     = (int) ( $context['author_id'] ?? 0 ) === $actor && $actor > 0;

			if ( ! $is_manager && ! ( $is_member && $is_own ) ) {
				throw new DomainException( __( 'Sie dürfen diesen Anhang nicht als Gruppendokument aufnehmen.', 'afspaces' ) );
			}

			$visibility = $this->resolve_requested_visibility( $space_id, $actor, $input, $is_manager );

			$title = SpaceDocument::sanitize_title( (string) ( $input['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = SpaceDocument::default_title_from_filename( $filename );
			}

			$document = new SpaceDocument(
				array(
					'space_id'        => $space_id,
					'asgaros_post_id' => $post_id,
					'filename'        => $filename,
					'title'           => $title,
					'document_topic'  => SpaceDocument::sanitize_topic( (string) ( $input['document_topic'] ?? '' ) ),
					'visibility'      => $visibility,
					'created_by'      => $actor,
				)
			);

			$document_id = $this->documents->create( $document );
			$this->audit->log( $space_id, $actor, 0, 'document_created', 'document' );

			return $document_id;
		}

		/**
		 * Aktualisiert Titel, Thema und Sichtbarkeit eines Dokuments.
		 *
		 * @param int                 $document_id Dokument-ID.
		 * @param int                 $actor       Handelnde Benutzer-ID.
		 * @param array<string,mixed> $input       title, document_topic, visibility.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		public function update_document( int $document_id, int $actor, array $input ): void {
			$document = $this->documents->get( $document_id );
			if ( ! $document ) {
				throw new DomainException( __( 'Das Dokument wurde nicht gefunden.', 'afspaces' ) );
			}

			$space = $this->spaces->get_space( $document->space_id );
			if ( ! $space || 'active' !== $space->status ) {
				throw new DomainException( __( 'Die Arbeitsgruppe ist nicht aktiv.', 'afspaces' ) );
			}

			$is_manager = $this->policy->can_manage_documents( $document->space_id, $actor );
			$is_owner   = $document->created_by === $actor && $actor > 0;
			if ( ! $is_manager && ! $is_owner ) {
				throw new DomainException( __( 'Sie dürfen dieses Dokument nicht bearbeiten.', 'afspaces' ) );
			}

			$title = SpaceDocument::sanitize_title( (string) ( $input['title'] ?? '' ) );
			if ( '' === $title ) {
				$title = SpaceDocument::default_title_from_filename( $document->filename );
			}

			$document->title          = $title;
			$document->document_topic = SpaceDocument::sanitize_topic( (string) ( $input['document_topic'] ?? '' ) );
			$document->visibility     = $this->resolve_requested_visibility(
				$document->space_id,
				$actor,
				$input,
				$is_manager,
				$document->visibility
			);

			$this->documents->update( $document );
			$this->audit->log( $document->space_id, $actor, 0, 'document_updated', 'document' );
		}

		/**
		 * Entfernt die Dokument-Metadaten (die Datei bleibt unberührt).
		 *
		 * @param int $document_id Dokument-ID.
		 * @param int $actor       Handelnde Benutzer-ID.
		 * @return void
		 *
		 * @throws DomainException Bei fehlender Berechtigung.
		 */
		public function remove_document( int $document_id, int $actor ): void {
			$document = $this->documents->get( $document_id );
			if ( ! $document ) {
				throw new DomainException( __( 'Das Dokument wurde nicht gefunden.', 'afspaces' ) );
			}

			$is_manager = $this->policy->can_manage_documents( $document->space_id, $actor );
			$is_owner   = $document->created_by === $actor && $actor > 0;
			if ( ! $is_manager && ! $is_owner ) {
				throw new DomainException( __( 'Sie dürfen dieses Dokument nicht entfernen.', 'afspaces' ) );
			}

			$this->documents->delete( $document_id );
			$this->audit->log( $document->space_id, $actor, 0, 'document_removed', 'document' );
		}

		/**
		 * Ermittelt den Dokument-Kontext eines Beitrags für die Forum-Integration.
		 *
		 * @param int $post_id Asgaros-Beitrags-ID.
		 * @param int $actor   Handelnde Benutzer-ID.
		 * @return array{space:Space,is_manager:bool,post_link:string,uploads:array<int,array<string,mixed>>}|null
		 */
		public function resolve_document_context( int $post_id, int $actor ): ?array {
			if ( $post_id < 1 || $actor < 1 ) {
				return null;
			}

			$context = $this->source->resolve_post_context( $post_id );
			if ( null === $context ) {
				return null;
			}

			$space = $this->spaces->get_space_by_forum( (int) $context['forum_id'] );
			if ( ! $space || 'active' !== $space->status ) {
				return null;
			}

			$uploads = $this->source->get_post_uploads( $post_id );
			if ( empty( $uploads ) ) {
				return null;
			}

			$is_manager = $this->policy->can_manage_documents( $space->id, $actor );
			$is_member  = $this->is_space_member( $space, $actor );
			$is_own     = (int) ( $context['author_id'] ?? 0 ) === $actor;
			$can_use    = $is_manager || ( $is_member && $is_own );

			$existing = $this->documents->map_for_post( $space->id, $post_id );

			$rows = array();
			foreach ( $uploads as $filename ) {
				$document        = $existing[ $filename ] ?? null;
				$rows[]          = array(
					'filename' => $filename,
					'document' => $document,
					'can_use'  => $can_use || null !== $document,
				);
			}

			return array(
				'space'      => $space,
				'is_manager' => $is_manager,
				'post_link'  => $this->source->get_post_link( $post_id, (int) $context['topic_id'] ),
				'uploads'    => $rows,
			);
		}

		/**
		 * Baut ein Anzeige-Modell für ein Dokument (inkl. Sichtbarkeitsschutz des
		 * Forumkontexts).
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param int           $actor    Betrachter.
		 * @return array<string,mixed>
		 */
		public function document_view_model( SpaceDocument $document, int $actor ): array {
			$space = $this->spaces->get_space( $document->space_id );

			$model = array(
				'id'                 => $document->id,
				'title'              => '' !== $document->title ? $document->title : $document->filename,
				'filename'           => $document->filename,
				'extension'          => SpaceDocument::file_extension( $document->filename ),
				'topic'              => $document->document_topic,
				'visibility'         => $document->visibility,
				'created_at'         => $document->created_at,
				'url'                => $this->source->get_upload_file_url( $document->asgaros_post_id, $document->filename ),
				'show_forum_context' => false,
				'topic_name'         => '',
				'post_link'          => '',
			);

			if ( $space && $this->can_read_forum_context( $space, $actor ) ) {
				$context = $this->source->resolve_post_context( $document->asgaros_post_id );
				if ( null !== $context ) {
					$model['show_forum_context'] = true;
					$model['topic_name']         = (string) $context['topic_name'];
					$model['post_link']          = $this->source->get_post_link( $document->asgaros_post_id, (int) $context['topic_id'] );
				}
			}

			return $model;
		}

		/**
		 * Gibt die belegten Dokumentthemen einer Gruppe zurück (für Betrachter sichtbar).
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Betrachter.
		 * @return string[]
		 */
		public function list_topics( int $space_id, int $actor ): array {
			$topics = array();
			foreach ( $this->list_documents( $space_id, $actor ) as $document ) {
				$topic = trim( $document->document_topic );
				if ( '' !== $topic ) {
					$topics[ strtolower( $topic ) ] = $topic;
				}
			}
			$values = array_values( $topics );
			sort( $values, SORT_NATURAL | SORT_FLAG_CASE );
			return $values;
		}

		/**
		 * Zählt die für den Akteur sichtbaren Dokumente einer Gruppe.
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Betrachter.
		 * @return int
		 */
		public function count_visible_documents( int $space_id, int $actor ): int {
			return count( $this->list_documents( $space_id, $actor ) );
		}

		/**
		 * Darf der Akteur ein Dokument mit erweiterter Sichtbarkeit veröffentlichen?
		 *
		 * @param int $space_id Space-ID.
		 * @param int $actor    Benutzer-ID.
		 * @return bool
		 */
		public function can_publish_for( int $space_id, int $actor ): bool {
			return $actor > 0 && $this->policy->can_publish_document( $space_id, $actor );
		}

		/**
		 * Validiert das Ziel eines neuen Dokuments und gibt den bereinigten
		 * Dateinamen zurück. Setzt zusätzlich Space und Beitragskontext.
		 *
		 * @param int         $space_id  Space-ID.
		 * @param int         $post_id   Beitrags-ID.
		 * @param string      $filename  Dateiname.
		 * @param Space|null  $space     Ausgabeparameter: aufgelöster Space.
		 * @param array|null  $context   Ausgabeparameter: Beitragskontext.
		 * @return string Bereinigter Dateiname.
		 *
		 * @throws DomainException Bei ungültigem Ziel.
		 */
		private function require_valid_attachment( int $space_id, int $post_id, string $filename, ?Space &$space, ?array &$context ): string {
			$space = $this->spaces->get_space( $space_id );
			if ( ! $space || 'active' !== $space->status ) {
				throw new DomainException( __( 'Die Arbeitsgruppe ist nicht aktiv.', 'afspaces' ) );
			}

			$filename = function_exists( 'wp_basename' ) ? (string) wp_basename( $filename ) : basename( $filename );
			$filename = function_exists( 'sanitize_file_name' ) ? (string) sanitize_file_name( $filename ) : $filename;
			if ( '' === $filename ) {
				throw new DomainException( __( 'Der Dateiname ist ungültig.', 'afspaces' ) );
			}

			$context = $this->source->resolve_post_context( $post_id );
			if ( null === $context ) {
				throw new DomainException( __( 'Der Forumsbeitrag existiert nicht.', 'afspaces' ) );
			}

			$forum_id = (int) $context['forum_id'];
			if ( $forum_id !== $space->forum_id && ! $this->spaces->is_forum_in_space( $space_id, $forum_id ) ) {
				throw new DomainException( __( 'Der Beitrag gehört nicht zu dieser Arbeitsgruppe.', 'afspaces' ) );
			}

			if ( ! $this->source->post_upload_exists( $post_id, $filename ) ) {
				throw new DomainException( __( 'Die Datei ist an diesem Beitrag nicht (mehr) vorhanden.', 'afspaces' ) );
			}

			return $filename;
		}

		/**
		 * Bestimmt die zulässige Sichtbarkeit einer Eingabe.
		 *
		 * @param int                 $space_id   Space-ID.
		 * @param int                 $actor      Akteur.
		 * @param array<string,mixed> $input      Eingaben.
		 * @param bool                $is_manager Akteur darf veröffentlichen.
		 * @param string              $current    Aktuelle Sichtbarkeit (bei Update).
		 * @return string
		 */
		private function resolve_requested_visibility( int $space_id, int $actor, array $input, bool $is_manager, string $current = SpaceDocument::VISIBILITY_MEMBERS ): string {
			$requested = SpaceDocument::sanitize_visibility( (string) ( $input['visibility'] ?? $current ) );

			// Nur Verantwortliche dürfen über „nur Arbeitsgruppe“ hinaus freigeben.
			if ( SpaceDocument::VISIBILITY_MEMBERS !== $requested && ! $is_manager ) {
				// Beim Bearbeiten eine bereits erweiterte Sichtbarkeit erhalten,
				// aber keine Ausweitung durch Nicht-Verantwortliche zulassen.
				if ( SpaceDocument::VISIBILITY_MEMBERS === $current ) {
					return SpaceDocument::VISIBILITY_MEMBERS;
				}
				return $current;
			}

			return $requested;
		}

		/**
		 * Prüft, ob ein Dokument noch gültig (nicht verwaist) ist.
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param Space         $space    Zugehörige Arbeitsgruppe.
		 * @return bool
		 */
		private function is_document_valid( SpaceDocument $document, Space $space ): bool {
			$context = $this->source->resolve_post_context( $document->asgaros_post_id );
			if ( null === $context ) {
				return false;
			}

			$forum_id = (int) $context['forum_id'];
			if ( $forum_id !== $space->forum_id && ! $this->spaces->is_forum_in_space( $space->id, $forum_id ) ) {
				return false;
			}

			return $this->source->post_upload_exists( $document->asgaros_post_id, $document->filename );
		}

		/**
		 * Darf der Akteur das Dokument entsprechend seiner Sichtbarkeit sehen?
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param Space         $space    Arbeitsgruppe.
		 * @param int           $actor    Betrachter.
		 * @return bool
		 */
		public function can_view_document( SpaceDocument $document, Space $space, int $actor ): bool {
			if ( $actor > 0 && $this->policy->can_manage( $space->id, $actor ) ) {
				return true;
			}

			switch ( $document->visibility ) {
				case SpaceDocument::VISIBILITY_PUBLIC:
					return SpaceDocument::public_enabled();
				case SpaceDocument::VISIBILITY_AUTHENTICATED:
					return $actor > 0;
				case SpaceDocument::VISIBILITY_MEMBERS:
				default:
					return $this->is_space_member( $space, $actor );
			}
		}

		/**
		 * Darf der Akteur den internen Forumkontext des Beitrags sehen?
		 *
		 * @param Space $space Arbeitsgruppe.
		 * @param int   $actor Betrachter.
		 * @return bool
		 */
		public function can_read_forum_context( Space $space, int $actor ): bool {
			if ( $actor > 0 && $this->policy->can_manage( $space->id, $actor ) ) {
				return true;
			}
			if ( $this->is_space_member( $space, $actor ) ) {
				return true;
			}
			if ( 'public' === $space->visibility ) {
				return true;
			}
			if ( 'protected' === $space->visibility ) {
				return $actor > 0;
			}
			return false;
		}

		/**
		 * Prüft die Mitgliedschaft eines Akteurs in der Zugriffsgruppe des Space.
		 *
		 * @param Space $space Arbeitsgruppe.
		 * @param int   $actor Benutzer-ID.
		 * @return bool
		 */
		private function is_space_member( Space $space, int $actor ): bool {
			if ( $actor < 1 || $space->primary_group_id < 1 ) {
				return false;
			}
			return $this->source->is_user_in_group( $actor, $space->primary_group_id );
		}

		/**
		 * Normalisiert einen Sortierschlüssel.
		 *
		 * @param mixed $sort Roh-Wert.
		 * @return string
		 */
		private function normalize_sort( $sort ): string {
			$sort = is_string( $sort ) ? strtolower( trim( $sort ) ) : '';
			$allowed = array(
				self::SORT_DATE_DESC,
				self::SORT_DATE_ASC,
				self::SORT_NAME_ASC,
				self::SORT_NAME_DESC,
			);
			return in_array( $sort, $allowed, true ) ? $sort : self::SORT_DATE_DESC;
		}

		/**
		 * Sortiert eine Dokumentliste.
		 *
		 * @param SpaceDocument[] $documents Dokumente.
		 * @param string          $sort      Sortierschlüssel.
		 * @return SpaceDocument[]
		 */
		private function sort_documents( array $documents, string $sort ): array {
			usort(
				$documents,
				static function ( SpaceDocument $a, SpaceDocument $b ) use ( $sort ): int {
					switch ( $sort ) {
						case self::SORT_DATE_ASC:
							return strcmp( $a->created_at, $b->created_at ) ?: ( $a->id <=> $b->id );
						case self::SORT_NAME_ASC:
							return strcasecmp( $a->filename, $b->filename ) ?: ( $a->id <=> $b->id );
						case self::SORT_NAME_DESC:
							return strcasecmp( $b->filename, $a->filename ) ?: ( $b->id <=> $a->id );
						case self::SORT_DATE_DESC:
						default:
							return strcmp( $b->created_at, $a->created_at ) ?: ( $b->id <=> $a->id );
					}
				}
			);

			return $documents;
		}

		/**
		 * Normalisiert einen Suchbegriff.
		 *
		 * @param string $search Roh-Eingabe.
		 * @return string
		 */
		private function normalize_search( string $search ): string {
			$search = trim( $search );
			if ( function_exists( 'sanitize_text_field' ) ) {
				$search = (string) sanitize_text_field( $search );
			}
			return $search;
		}

		/**
		 * Prüft, ob ein Dokument zu einem Suchbegriff passt.
		 *
		 * @param SpaceDocument $document Dokument.
		 * @param string        $needle  Suchbegriff.
		 * @return bool
		 */
		private function matches_search( SpaceDocument $document, string $needle ): bool {
			$haystack = strtolower( $document->title . ' ' . $document->filename . ' ' . $document->document_topic );
			return false !== strpos( $haystack, strtolower( $needle ) );
		}
	}
}
