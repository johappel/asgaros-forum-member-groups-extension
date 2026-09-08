<?php
/**
 * Unit-Tests für den Toolbox-Dienst (Rechte, Validierung, Sortierung).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceLinkRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\ToolboxService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceLink;
use AFSpaces\Domain\SpacePolicy;
use PHPUnit\Framework\TestCase;

final class StubToolboxSpaceRepository extends SpaceRepository {

	/** @var array<int,Space> */
	public array $spaces = array();

	/** @var array<int,int[]> */
	public array $managers = array();

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
		return null;
	}

	public function is_manager( int $space_id, int $user_id ): bool {
		return in_array( $user_id, $this->managers[ $space_id ] ?? array(), true );
	}
}

final class StubSpaceLinkRepository extends SpaceLinkRepository {

	/** @var array<int,SpaceLink> */
	public array $links = array();

	private int $next_id = 1;

	public function __construct() {}

	public function list_for_space( int $space_id ): array {
		$rows = array_filter(
			$this->links,
			static fn ( SpaceLink $link ): bool => $link->space_id === $space_id
		);
		usort(
			$rows,
			static function ( SpaceLink $a, SpaceLink $b ): int {
				return $a->sort_order <=> $b->sort_order ?: $a->id <=> $b->id;
			}
		);
		return array_values( $rows );
	}

	public function get_link( int $link_id ): ?SpaceLink {
		return $this->links[ $link_id ] ?? null;
	}

	public function create( SpaceLink $link ): int {
		$id                  = $this->next_id++;
		$link->id            = $id;
		$this->links[ $id ]  = $link;
		return $id;
	}

	public function update( SpaceLink $link ): void {
		$this->links[ $link->id ] = $link;
	}

	public function set_sort_order( int $link_id, int $sort_order ): void {
		if ( isset( $this->links[ $link_id ] ) ) {
			$this->links[ $link_id ]->sort_order = $sort_order;
		}
	}

	public function delete( int $link_id ): void {
		unset( $this->links[ $link_id ] );
	}

	public function max_sort_order( int $space_id ): int {
		$max = 0;
		foreach ( $this->list_for_space( $space_id ) as $link ) {
			$max = max( $max, $link->sort_order );
		}
		return $max;
	}
}

final class StubToolboxAudit extends AuditRepository {

	/** @var array<int,array<string,mixed>> */
	public array $entries = array();

	public function __construct() {}

	public function log( int $space_id, int $actor_user_id, int $target_user_id, string $action, string $object_type = 'member' ): void {
		$this->entries[] = compact( 'space_id', 'actor_user_id', 'target_user_id', 'action', 'object_type' );
	}
}

final class ToolboxServiceTest extends TestCase {

	private StubToolboxSpaceRepository $spaces;
	private StubSpaceLinkRepository $links;
	private StubToolboxAudit $audit;
	private ToolboxService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->spaces = new StubToolboxSpaceRepository();
		$this->links  = new StubSpaceLinkRepository();
		$this->audit  = new StubToolboxAudit();

		$this->spaces->spaces[10] = new Space(
			array(
				'id'       => 10,
				'forum_id' => 5,
				'status'   => 'active',
			)
		);
		$this->spaces->managers[10] = array( 42 );

		$policy        = new SpacePolicy( $this->spaces );
		$this->service = new ToolboxService( $this->spaces, $this->links, $policy, $this->audit );

		// Standard: niemand hat globale Capabilities.
		$GLOBALS['afspaces_user_can_callback'] = static fn ( int $user_id, string $cap ): bool => false;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['afspaces_user_can_callback'] );
		parent::tearDown();
	}

	public function test_manager_can_add_link(): void {
		$id = $this->service->add_link( 10, 42, array( 'title' => 'Wiki', 'url' => 'https://example.test/wiki' ) );

		$this->assertGreaterThan( 0, $id );
		$this->assertCount( 1, $this->links->list_for_space( 10 ) );
		$this->assertSame( 'Wiki', $this->links->get_link( $id )->title );
	}

	public function test_non_manager_cannot_add_link(): void {
		$this->expectException( DomainException::class );
		$this->service->add_link( 10, 99, array( 'title' => 'Wiki', 'url' => 'https://example.test' ) );
	}

	public function test_global_admin_can_manage(): void {
		$GLOBALS['afspaces_user_can_callback'] = static fn ( int $user_id, string $cap ): bool => Capabilities::MANAGE_ALL_SPACES === $cap;

		$id = $this->service->add_link( 10, 7, array( 'title' => 'Admin-Link', 'url' => 'https://example.test' ) );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_invalid_url_is_rejected(): void {
		$this->expectException( DomainException::class );
		$this->service->add_link( 10, 42, array( 'title' => 'Böse', 'url' => 'javascript:alert(1)' ) );
	}

	public function test_missing_title_is_rejected(): void {
		$this->expectException( DomainException::class );
		$this->service->add_link( 10, 42, array( 'title' => '   ', 'url' => 'https://example.test' ) );
	}

	public function test_update_link_of_foreign_space_is_rejected(): void {
		$foreign = new SpaceLink( array( 'id' => 500, 'space_id' => 99, 'title' => 'Fremd', 'url' => 'https://example.test' ) );
		$this->links->links[500] = $foreign;

		$this->expectException( DomainException::class );
		$this->service->update_link( 10, 42, 500, array( 'title' => 'Neu', 'url' => 'https://example.test' ) );
	}

	public function test_delete_link_removes_it(): void {
		$id = $this->service->add_link( 10, 42, array( 'title' => 'Weg', 'url' => 'https://example.test' ) );
		$this->service->delete_link( 10, 42, $id );

		$this->assertNull( $this->links->get_link( $id ) );
	}

	public function test_reorder_sets_sequential_positions(): void {
		$a = $this->service->add_link( 10, 42, array( 'title' => 'A', 'url' => 'https://example.test/a' ) );
		$b = $this->service->add_link( 10, 42, array( 'title' => 'B', 'url' => 'https://example.test/b' ) );
		$c = $this->service->add_link( 10, 42, array( 'title' => 'C', 'url' => 'https://example.test/c' ) );

		$this->service->reorder_links( 10, 42, array( $c, $a, $b ) );

		$ordered = array_map( static fn ( SpaceLink $l ): string => $l->title, $this->links->list_for_space( 10 ) );
		$this->assertSame( array( 'C', 'A', 'B' ), $ordered );
	}

	public function test_move_link_up_swaps_with_previous(): void {
		$a = $this->service->add_link( 10, 42, array( 'title' => 'A', 'url' => 'https://example.test/a' ) );
		$b = $this->service->add_link( 10, 42, array( 'title' => 'B', 'url' => 'https://example.test/b' ) );

		$this->service->move_link( 10, 42, $b, 'up' );

		$ordered = array_map( static fn ( SpaceLink $l ): string => $l->title, $this->links->list_for_space( 10 ) );
		$this->assertSame( array( 'B', 'A' ), $ordered );
	}

	public function test_resolve_space_by_forum_requires_active_space(): void {
		$this->assertNotNull( $this->service->resolve_space_by_forum( 5 ) );
		$this->assertNull( $this->service->resolve_space_by_forum( 999 ) );

		$this->spaces->spaces[10]->status = 'archived';
		$this->assertNull( $this->service->resolve_space_by_forum( 5 ) );
	}
}
