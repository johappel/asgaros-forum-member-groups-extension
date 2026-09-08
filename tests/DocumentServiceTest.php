<?php
/**
 * Unit-Tests für den Dokumentdienst (Rechte, Sichtbarkeit, Lifecycle, Sortierung).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\DocumentSourceInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceDocumentRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\DocumentService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceDocument;
use AFSpaces\Domain\SpacePolicy;
use PHPUnit\Framework\TestCase;

final class StubDocumentSpaceRepository extends SpaceRepository {

	/** @var array<int,Space> */
	public array $spaces = array();

	/** @var array<int,int[]> */
	public array $managers = array();

	/** @var array<int,int[]> Zusätzliche Foren je Space. */
	public array $extra_forums = array();

	public function __construct() {}

	public function get_space( int $space_id ): ?Space {
		return $this->spaces[ $space_id ] ?? null;
	}

	public function get_space_by_forum( int $forum_id ): ?Space {
		foreach ( $this->spaces as $space ) {
			if ( $space->forum_id === $forum_id ) {
				return $space;
			}
		}
		foreach ( $this->extra_forums as $space_id => $forums ) {
			if ( in_array( $forum_id, $forums, true ) ) {
				return $this->spaces[ $space_id ] ?? null;
			}
		}
		return null;
	}

	public function is_manager( int $space_id, int $user_id ): bool {
		return in_array( $user_id, $this->managers[ $space_id ] ?? array(), true );
	}

	public function is_forum_in_space( int $space_id, int $forum_id ): bool {
		$space = $this->spaces[ $space_id ] ?? null;
		if ( $space && $space->forum_id === $forum_id ) {
			return true;
		}
		return in_array( $forum_id, $this->extra_forums[ $space_id ] ?? array(), true );
	}
}

final class StubDocumentRepository extends SpaceDocumentRepository {

	/** @var array<int,SpaceDocument> */
	public array $documents = array();

	private int $next_id = 1;

	public function __construct() {}

	public function list_for_space( int $space_id ): array {
		$rows = array_filter(
			$this->documents,
			static fn ( SpaceDocument $d ): bool => $d->space_id === $space_id
		);
		usort( $rows, static fn ( SpaceDocument $a, SpaceDocument $b ): int => strcmp( $b->created_at, $a->created_at ) ?: ( $b->id <=> $a->id ) );
		return array_values( $rows );
	}

	public function get( int $document_id ): ?SpaceDocument {
		return $this->documents[ $document_id ] ?? null;
	}

	public function find_by_natural_key( int $space_id, int $post_id, string $filename ): ?SpaceDocument {
		foreach ( $this->documents as $document ) {
			if ( $document->space_id === $space_id && $document->asgaros_post_id === $post_id && $document->filename === $filename ) {
				return $document;
			}
		}
		return null;
	}

	public function map_for_post( int $space_id, int $post_id ): array {
		$map = array();
		foreach ( $this->documents as $document ) {
			if ( $document->space_id === $space_id && $document->asgaros_post_id === $post_id ) {
				$map[ $document->filename ] = $document;
			}
		}
		return $map;
	}

	public function create( SpaceDocument $document ): int {
		$id                     = $this->next_id++;
		$document->id           = $id;
		$document->created_at   = $document->created_at ?: sprintf( '2026-09-%02d 10:00:00', $id );
		$this->documents[ $id ] = $document;
		return $id;
	}

	public function update( SpaceDocument $document ): void {
		$this->documents[ $document->id ] = $document;
	}

	public function delete( int $document_id ): void {
		unset( $this->documents[ $document_id ] );
	}

	public function delete_by_post( int $post_id ): void {
		foreach ( $this->documents as $id => $document ) {
			if ( $document->asgaros_post_id === $post_id ) {
				unset( $this->documents[ $id ] );
			}
		}
	}
}

final class StubDocumentAudit extends AuditRepository {

	/** @var array<int,array<string,mixed>> */
	public array $entries = array();

	public function __construct() {}

	public function log( int $space_id, int $actor_user_id, int $target_user_id, string $action, string $object_type = 'member' ): void {
		$this->entries[] = compact( 'space_id', 'actor_user_id', 'action', 'object_type' );
	}
}

final class FakeDocumentSource implements DocumentSourceInterface {

	/** @var array<int,string[]> */
	public array $uploads = array();

	/** @var array<int,array{topic_id:int,forum_id:int,topic_name:string,author_id:int}> */
	public array $contexts = array();

	/** @var array<int,int[]> */
	public array $group_members = array();

	public function get_post_uploads( int $post_id ): array {
		return $this->uploads[ $post_id ] ?? array();
	}

	public function post_upload_exists( int $post_id, string $filename ): bool {
		return in_array( $filename, $this->uploads[ $post_id ] ?? array(), true );
	}

	public function get_upload_file_url( int $post_id, string $filename ): string {
		return $this->post_upload_exists( $post_id, $filename ) ? "https://files.test/{$post_id}/{$filename}" : '';
	}

	public function resolve_post_context( int $post_id ): ?array {
		return $this->contexts[ $post_id ] ?? null;
	}

	public function get_post_link( int $post_id, int $topic_id ): string {
		return "https://forum.test/post/{$post_id}";
	}

	public function is_user_in_group( int $user_id, int $group_id ): bool {
		return in_array( $user_id, $this->group_members[ $group_id ] ?? array(), true );
	}
}

final class DocumentServiceTest extends TestCase {

	private StubDocumentSpaceRepository $spaces;
	private StubDocumentRepository $repo;
	private StubDocumentAudit $audit;
	private FakeDocumentSource $source;
	private DocumentService $service;

	private const OWNER  = 1;   // Manager des Space.
	private const MEMBER = 42;  // Mitglied der Gruppe.
	private const OUTSIDER_LOGGED_IN = 50; // Angemeldet, kein Mitglied.
	private const ADMIN  = 999; // MANAGE_ALL_SPACES.

	protected function setUp(): void {
		parent::setUp();

		$this->spaces = new StubDocumentSpaceRepository();
		$this->repo   = new StubDocumentRepository();
		$this->audit  = new StubDocumentAudit();
		$this->source = new FakeDocumentSource();

		$this->spaces->spaces[10] = new Space(
			array(
				'id'               => 10,
				'forum_id'         => 5,
				'primary_group_id' => 100,
				'owner_user_id'    => self::OWNER,
				'visibility'       => 'private',
				'status'           => 'active',
			)
		);
		$this->spaces->managers[10]     = array( self::OWNER );
		$this->spaces->extra_forums[10] = array( 6 ); // Zusatzforum.

		// Gruppenmitgliedschaft (Gruppe 100): Owner + Member.
		$this->source->group_members[100] = array( self::OWNER, self::MEMBER );

		// Beitrag 500 im Primärforum 5, verfasst vom Mitglied.
		$this->source->contexts[500] = array(
			'topic_id'   => 70,
			'forum_id'   => 5,
			'topic_name' => 'Treffen am 8. September',
			'author_id'  => self::MEMBER,
		);
		$this->source->uploads[500] = array( 'Protokoll.pdf', 'Anlage.docx' );

		// Beitrag 600 im Zusatzforum 6, verfasst vom Owner.
		$this->source->contexts[600] = array(
			'topic_id'   => 80,
			'forum_id'   => 6,
			'topic_name' => 'Zusatzforum-Thema',
			'author_id'  => self::OWNER,
		);
		$this->source->uploads[600] = array( 'Konzept.pdf' );

		// Beitrag 700 in einem fremden Forum 99.
		$this->source->contexts[700] = array(
			'topic_id'   => 90,
			'forum_id'   => 99,
			'topic_name' => 'Fremd',
			'author_id'  => self::MEMBER,
		);
		$this->source->uploads[700] = array( 'Fremd.pdf' );

		$policy        = new SpacePolicy( $this->spaces );
		$this->service = new DocumentService( $this->spaces, $this->repo, $policy, $this->source, $this->audit );

		$GLOBALS['afspaces_user_can_callback'] = static function ( int $user_id, string $cap ): bool {
			return DocumentServiceTest::ADMIN === $user_id && Capabilities::MANAGE_ALL_SPACES === $cap;
		};
	}

	protected function tearDown(): void {
		unset( $GLOBALS['afspaces_user_can_callback'] );
		parent::tearDown();
	}

	// --- Erstellung / Duplikate ---------------------------------------

	public function test_member_can_mark_own_attachment_as_document(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array( 'document_topic' => 'Protokolle' ) );

		$this->assertGreaterThan( 0, $id );
		$document = $this->repo->get( $id );
		$this->assertSame( 'Protokoll', $document->title, 'Standardtitel aus Dateiname.' );
		$this->assertSame( 'Protokolle', $document->document_topic );
		$this->assertSame( SpaceDocument::VISIBILITY_MEMBERS, $document->visibility );
	}

	public function test_duplicate_creation_is_idempotent(): void {
		$first  = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$second = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $this->repo->documents );
	}

	public function test_additional_forum_attachment_is_accepted(): void {
		$id = $this->service->create_document( 10, 600, 'Konzept.pdf', self::OWNER, array() );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_foreign_forum_is_rejected(): void {
		$this->expectException( DomainException::class );
		$this->service->create_document( 10, 700, 'Fremd.pdf', self::MEMBER, array() );
	}

	public function test_unknown_filename_is_rejected(): void {
		$this->expectException( DomainException::class );
		$this->service->create_document( 10, 500, 'Gibtsnicht.pdf', self::MEMBER, array() );
	}

	public function test_outsider_cannot_create_document(): void {
		$this->expectException( DomainException::class );
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::OUTSIDER_LOGGED_IN, array() );
	}

	public function test_member_cannot_mark_foreign_authored_attachment(): void {
		// Beitrag 600 wurde vom Owner verfasst; das Mitglied ist nicht Autor und kein Manager.
		$this->expectException( DomainException::class );
		$this->service->create_document( 10, 600, 'Konzept.pdf', self::MEMBER, array() );
	}

	// --- Sichtbarkeit bei Erstellung ----------------------------------

	public function test_member_visibility_is_capped_to_members(): void {
		$id       = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array( 'visibility' => 'authenticated' ) );
		$document = $this->repo->get( $id );
		$this->assertSame( SpaceDocument::VISIBILITY_MEMBERS, $document->visibility );
	}

	public function test_manager_can_publish_for_authenticated(): void {
		$id       = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::OWNER, array( 'visibility' => 'authenticated' ) );
		$document = $this->repo->get( $id );
		$this->assertSame( SpaceDocument::VISIBILITY_AUTHENTICATED, $document->visibility );
	}

	// --- Aktualisieren / Entfernen ------------------------------------

	public function test_owner_of_document_can_update_but_not_escalate(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$this->service->update_document( $id, self::MEMBER, array( 'title' => 'Neuer Titel', 'visibility' => 'authenticated' ) );

		$document = $this->repo->get( $id );
		$this->assertSame( 'Neuer Titel', $document->title );
		$this->assertSame( SpaceDocument::VISIBILITY_MEMBERS, $document->visibility, 'Mitglied darf nicht erweitern.' );
	}

	public function test_manager_can_update_visibility(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$this->service->update_document( $id, self::OWNER, array( 'visibility' => 'authenticated' ) );
		$this->assertSame( SpaceDocument::VISIBILITY_AUTHENTICATED, $this->repo->get( $id )->visibility );
	}

	public function test_outsider_cannot_update_or_remove(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		$this->expectException( DomainException::class );
		$this->service->remove_document( $id, self::OUTSIDER_LOGGED_IN );
	}

	public function test_document_owner_can_remove(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$this->service->remove_document( $id, self::MEMBER );
		$this->assertNull( $this->repo->get( $id ) );
	}

	public function test_manager_can_remove_foreign_document(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$this->service->remove_document( $id, self::OWNER );
		$this->assertNull( $this->repo->get( $id ) );
	}

	// --- Sichtbarkeit beim Listen -------------------------------------

	public function test_members_document_visible_only_to_members_and_managers(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		$this->assertCount( 1, $this->service->list_documents( 10, self::MEMBER ) );
		$this->assertCount( 1, $this->service->list_documents( 10, self::OWNER ) );
		$this->assertCount( 1, $this->service->list_documents( 10, self::ADMIN ) );
		$this->assertCount( 0, $this->service->list_documents( 10, self::OUTSIDER_LOGGED_IN ) );
		$this->assertCount( 0, $this->service->list_documents( 10, 0 ) );
	}

	public function test_authenticated_document_visible_to_any_logged_in_user(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::OWNER, array( 'visibility' => 'authenticated' ) );

		$this->assertCount( 1, $this->service->list_documents( 10, self::OUTSIDER_LOGGED_IN ) );
		$this->assertCount( 0, $this->service->list_documents( 10, 0 ), 'Gast sieht kein authenticated-Dokument.' );
	}

	public function test_non_member_sees_document_but_no_internal_forum_context(): void {
		$id = $this->service->create_document( 10, 500, 'Protokoll.pdf', self::OWNER, array( 'visibility' => 'authenticated' ) );

		// Nicht-Mitglied (angemeldet) sieht das Dokument ...
		$documents = $this->service->list_documents( 10, self::OUTSIDER_LOGGED_IN );
		$this->assertCount( 1, $documents );

		// ... aber keinerlei internen Forumkontext.
		$model = $this->service->document_view_model( $documents[0], self::OUTSIDER_LOGGED_IN );
		$this->assertFalse( $model['show_forum_context'] );
		$this->assertSame( '', $model['topic_name'] );
		$this->assertSame( '', $model['post_link'] );

		// Ein Mitglied hingegen bekommt die Herkunft.
		$member_model = $this->service->document_view_model( $this->repo->get( $id ), self::MEMBER );
		$this->assertTrue( $member_model['show_forum_context'] );
		$this->assertSame( 'Treffen am 8. September', $member_model['topic_name'] );
		$this->assertNotSame( '', $member_model['post_link'] );
	}

	// --- Sortierung ---------------------------------------------------

	public function test_sorting_by_name_and_date(): void {
		$this->source->uploads[500] = array( 'Beta.pdf', 'Alpha.pdf', 'Protokoll.pdf', 'Anlage.docx' );
		$a = $this->service->create_document( 10, 500, 'Beta.pdf', self::MEMBER, array() );
		$b = $this->service->create_document( 10, 500, 'Alpha.pdf', self::MEMBER, array() );

		$by_name_asc = $this->service->list_documents( 10, self::MEMBER, array( 'sort' => DocumentService::SORT_NAME_ASC ) );
		$this->assertSame( 'Alpha.pdf', $by_name_asc[0]->filename );

		$by_name_desc = $this->service->list_documents( 10, self::MEMBER, array( 'sort' => DocumentService::SORT_NAME_DESC ) );
		$this->assertSame( 'Beta.pdf', $by_name_desc[0]->filename );

		$by_date_asc = $this->service->list_documents( 10, self::MEMBER, array( 'sort' => DocumentService::SORT_DATE_ASC ) );
		$this->assertSame( $a, $by_date_asc[0]->id, 'Ältestes zuerst.' );

		$by_date_desc = $this->service->list_documents( 10, self::MEMBER, array( 'sort' => DocumentService::SORT_DATE_DESC ) );
		$this->assertSame( $b, $by_date_desc[0]->id, 'Neuestes zuerst.' );
	}

	public function test_topic_filter_and_search(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array( 'document_topic' => 'Protokolle' ) );
		$this->service->create_document( 10, 500, 'Anlage.docx', self::MEMBER, array( 'document_topic' => 'Konzepte', 'title' => 'Positionspapier' ) );

		$this->assertCount( 1, $this->service->list_documents( 10, self::MEMBER, array( 'topic' => 'Protokolle' ) ) );
		$this->assertCount( 1, $this->service->list_documents( 10, self::MEMBER, array( 'search' => 'position' ) ) );
		$this->assertCount( 2, $this->service->list_documents( 10, self::MEMBER ) );
	}

	// --- Lifecycle ----------------------------------------------------

	public function test_orphaned_document_is_filtered_when_attachment_removed(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		// Datei wird aus dem Beitrag entfernt.
		$this->source->uploads[500] = array( 'Anlage.docx' );

		$this->assertCount( 0, $this->service->list_documents( 10, self::MEMBER ) );
	}

	public function test_orphaned_document_is_filtered_when_post_deleted(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		unset( $this->source->contexts[500] );

		$this->assertCount( 0, $this->service->list_documents( 10, self::MEMBER ) );
	}

	public function test_document_is_filtered_when_forum_leaves_space(): void {
		$id = $this->service->create_document( 10, 600, 'Konzept.pdf', self::OWNER, array() );
		$this->assertCount( 1, $this->service->list_documents( 10, self::OWNER ) );

		// Zusatzforum 6 wird aus dem Space entfernt.
		$this->spaces->extra_forums[10] = array();

		$this->assertCount( 0, $this->service->list_documents( 10, self::OWNER ) );
		$this->assertNotNull( $this->repo->get( $id ), 'Metadaten bleiben; nur Anzeige gefiltert.' );
	}

	public function test_cleanup_by_post_removes_metadata_only(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );
		$this->repo->delete_by_post( 500 );
		$this->assertCount( 0, $this->repo->documents );
		// Datei bleibt in der Fake-Quelle unangetastet.
		$this->assertTrue( $this->source->post_upload_exists( 500, 'Protokoll.pdf' ) );
	}

	// --- Forum-Integration --------------------------------------------

	public function test_resolve_document_context_lists_uploads_with_flags(): void {
		$this->service->create_document( 10, 500, 'Protokoll.pdf', self::MEMBER, array() );

		$context = $this->service->resolve_document_context( 500, self::MEMBER );
		$this->assertNotNull( $context );
		$this->assertCount( 2, $context['uploads'] );

		$by_name = array();
		foreach ( $context['uploads'] as $row ) {
			$by_name[ $row['filename'] ] = $row;
		}
		$this->assertNotNull( $by_name['Protokoll.pdf']['document'] );
		$this->assertNull( $by_name['Anlage.docx']['document'] );
		$this->assertTrue( $by_name['Anlage.docx']['can_use'] );
	}

	public function test_resolve_document_context_returns_null_for_outsider_without_documents(): void {
		$context = $this->service->resolve_document_context( 500, self::OUTSIDER_LOGGED_IN );
		$this->assertNotNull( $context );
		foreach ( $context['uploads'] as $row ) {
			$this->assertFalse( $row['can_use'], 'Nicht-Mitglied darf fremden Beitrag nicht kennzeichnen.' );
		}
	}
}
