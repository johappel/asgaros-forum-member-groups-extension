<?php
/**
 * Unit-Tests für die raum-begrenzte Moderation (Eigentümer- und Policy-Prüfung).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceModerationService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpacePolicy;
use PHPUnit\Framework\TestCase;

final class StubModerationRepository extends SpaceRepository {

	/** @var array<int,Space> */
	public array $spaces = array();

	/** @var array<int,int[]> space_id => manager user ids */
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

final class StubModerationAudit extends AuditRepository {
	/** @var array<int,array<string,mixed>> */
	public array $entries = array();
	public function __construct() {}
	public function log( int $space_id, int $actor_user_id, int $target_user_id, string $action, string $object_type = 'member' ): void {
		$this->entries[] = compact( 'space_id', 'actor_user_id', 'target_user_id', 'action', 'object_type' );
	}
}

final class StubModerationAdapter implements AsgarosAdapterInterface {

	/** @var array<int,int> topic_id => forum_id */
	public array $topic_forum = array();

	/** @var array<int,array{topic_id:int,forum_id:int,is_first:bool}> */
	public array $post_location = array();

	/** @var array<int,string> */
	public array $calls = array();

	public function is_available(): bool { return true; }
	public function get_version(): ?string { return '3.4.0'; }
	public function list_manageable_forums( int $actor_user_id ): array { return array(); }
	public function get_forum( int $forum_id ): ?array { return array( 'id' => $forum_id, 'name' => 'F' . $forum_id, 'category_id' => 1 ); }
	public function get_forum_group_ids( int $forum_id ): array { return array(); }
	public function get_group_name( int $group_id ): ?string { return null; }
	public function list_group_members( int $group_id, array $args = [] ): array { return array(); }
	public function add_user_to_group( int $user_id, int $group_id ): void {}
	public function remove_user_from_group( int $user_id, int $group_id ): void {}
	public function is_user_in_group( int $user_id, int $group_id ): bool { return false; }
	public function search_posts( string $keywords, array $args = [] ): array { return array( 'results' => array(), 'total' => 0 ); }
	public function get_post_link( int $post_id, int $topic_id ): string { return ''; }
	public function list_accessible_category_ids(): array { return array(); }
	public function list_accessible_forums(): array { return array(); }
	public function count_all_posts(): int { return 0; }
	public function list_posts_for_index( int $limit, int $offset ): array { return array(); }
	public function is_search_request(): bool { return false; }
	public function create_forum_category( array $data ): int { return 0; }
	public function create_forum( array $data ): int { return 0; }
	public function create_group( array $data ): int { return 0; }
	public function assign_group_to_forum( int $forum_id, int $group_id ): void {}
	public function set_forum_visibility( int $forum_id, array $data ): void {}
	public function update_forum( int $forum_id, array $data ): void {}
	public function delete_forum( int $forum_id ): void {}
	public function delete_forum_category( int $category_id ): void {}
	public function delete_group( int $group_id ): void {}

	public function list_forum_topics( int $forum_id, array $args = [] ): array {
		$this->calls[] = 'list_forum_topics:' . $forum_id;
		return array( 'topics' => array(), 'total' => 0 );
	}
	public function get_topic_forum( int $topic_id ): int {
		return $this->topic_forum[ $topic_id ] ?? 0;
	}
	public function set_topic_closed( int $topic_id, bool $closed ): void {
		$this->calls[] = ( $closed ? 'close:' : 'open:' ) . $topic_id;
	}
	public function delete_forum_topic( int $topic_id ): void {
		$this->calls[] = 'delete_topic:' . $topic_id;
	}
	public function get_post_location( int $post_id ): ?array {
		return $this->post_location[ $post_id ] ?? null;
	}
	public function delete_forum_post( int $post_id ): void {
		$this->calls[] = 'delete_post:' . $post_id;
	}

	public function move_topic( int $topic_id, int $target_forum_id ): void {
		$this->calls[] = 'move_topic:' . $topic_id . '->' . $target_forum_id;
	}

	public function list_topic_posts( int $topic_id, array $args = [] ): array {
		return array( 'posts' => array(), 'total' => 0 );
	}

	public function move_post( int $post_id, int $target_topic_id, int $target_forum_id ): void {
		$this->calls[] = 'move_post:' . $post_id . '->' . $target_topic_id . '@' . $target_forum_id;
	}
}

final class SpaceModerationServiceTest extends TestCase {

	private StubModerationRepository $repo;
	private StubModerationAdapter $adapter;
	private SpaceModerationService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->repo = new StubModerationRepository();
		$this->repo->spaces[10] = new Space( array( 'id' => 10, 'forum_id' => 500, 'primary_group_id' => 1, 'owner_user_id' => 7, 'status' => 'active' ) );
		$this->repo->spaces[20] = new Space( array( 'id' => 20, 'forum_id' => 600, 'primary_group_id' => 2, 'owner_user_id' => 7, 'status' => 'active' ) );
		$this->repo->spaces[30] = new Space( array( 'id' => 30, 'forum_id' => 700, 'primary_group_id' => 3, 'owner_user_id' => 8, 'status' => 'active' ) );
		$this->repo->managers[10] = array( 7 );
		$this->repo->managers[20] = array( 7 );
		$this->repo->managers[30] = array( 8 );

		$this->adapter = new StubModerationAdapter();
		$this->adapter->topic_forum = array(
			99 => 500, // gehört zu Space 10.
			77 => 999, // gehört zu einem fremden Forum.
		);

		$policy = new SpacePolicy( $this->repo );
		$this->service = new SpaceModerationService( $this->repo, $this->adapter, $policy, new StubModerationAudit() );

		global $afspaces_user_can_callback;
		$afspaces_user_can_callback = static function ( int $user_id, string $cap ): bool {
			return 1 === $user_id && Capabilities::MANAGE_ALL_SPACES === $cap;
		};
	}

	public function test_manager_can_close_own_topic(): void {
		$this->service->close_topic( 10, 7, 99 );
		$this->assertContains( 'close:99', $this->adapter->calls );
	}

	public function test_admin_can_delete_own_topic(): void {
		$this->service->delete_topic( 10, 1, 99 );
		$this->assertContains( 'delete_topic:99', $this->adapter->calls );
	}

	public function test_non_manager_cannot_moderate(): void {
		$this->expectException( DomainException::class );
		$this->service->close_topic( 10, 42, 99 );
	}

	public function test_cannot_moderate_foreign_topic(): void {
		$this->expectException( DomainException::class );
		// Topic 77 gehört zu Forum 999, nicht zu Space 10 (Forum 500).
		$this->service->delete_topic( 10, 7, 77 );
	}

	public function test_cannot_delete_foreign_post(): void {
		$this->adapter->post_location[ 5 ] = array( 'topic_id' => 3, 'forum_id' => 999, 'is_first' => false );
		$this->expectException( DomainException::class );
		$this->service->delete_post( 10, 7, 5 );
	}

	public function test_can_delete_own_post(): void {
		$this->adapter->post_location[ 5 ] = array( 'topic_id' => 3, 'forum_id' => 500, 'is_first' => false );
		$this->service->delete_post( 10, 7, 5 );
		$this->assertContains( 'delete_post:5', $this->adapter->calls );
	}

	public function test_list_topics_requires_permission(): void {
		$this->expectException( DomainException::class );
		$this->service->list_topics( 10, 42 );
	}

	public function test_move_topic_to_managed_target(): void {
		$this->service->move_topic( 10, 7, 99, 20 );
		$this->assertContains( 'move_topic:99->600', $this->adapter->calls );
	}

	public function test_cannot_move_to_unmanaged_target(): void {
		$this->expectException( DomainException::class );
		// Space 30 wird von Nutzer 7 nicht verwaltet.
		$this->service->move_topic( 10, 7, 99, 30 );
	}

	public function test_cannot_move_foreign_topic(): void {
		$this->expectException( DomainException::class );
		// Topic 77 gehört nicht zu Space 10.
		$this->service->move_topic( 10, 7, 77, 20 );
	}

	public function test_cannot_move_into_same_space(): void {
		$this->expectException( DomainException::class );
		$this->service->move_topic( 10, 7, 99, 10 );
	}

	public function test_move_post_to_topic_in_managed_forum(): void {
		// Beitrag 50 liegt in Thema 3 / Forum 500 (Space 10). Ziel: Thema 99 (auch Forum 500).
		$this->adapter->post_location[50] = array( 'topic_id' => 3, 'forum_id' => 500, 'is_first' => false );
		$this->service->move_post( 10, 7, 50, 99 );
		$this->assertContains( 'move_post:50->99@500', $this->adapter->calls );
	}

	public function test_cannot_move_first_post(): void {
		$this->adapter->post_location[50] = array( 'topic_id' => 3, 'forum_id' => 500, 'is_first' => true );
		$this->expectException( DomainException::class );
		$this->service->move_post( 10, 7, 50, 99 );
	}

	public function test_cannot_move_post_from_foreign_forum(): void {
		$this->adapter->post_location[50] = array( 'topic_id' => 3, 'forum_id' => 999, 'is_first' => false );
		$this->expectException( DomainException::class );
		$this->service->move_post( 10, 7, 50, 99 );
	}

	public function test_cannot_move_post_to_unmanaged_target_topic(): void {
		// Zielthema 77 liegt in Forum 999, das keinem verwalteten Space gehört.
		$this->adapter->post_location[50] = array( 'topic_id' => 3, 'forum_id' => 500, 'is_first' => false );
		$this->expectException( DomainException::class );
		$this->service->move_post( 10, 7, 50, 77 );
	}
}
