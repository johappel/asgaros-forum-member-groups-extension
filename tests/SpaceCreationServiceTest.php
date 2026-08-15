<?php
/**
 * Unit-Tests für den transaktionsähnlichen SpaceCreationService (inkl. Rollback).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\AuditRepository;
use AFSpaces\Adapters\Database\SpaceMetaRepository;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceCreationService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Core\SpaceCreationSettings;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceLifecycle;
use AFSpaces\Domain\SpaceManager;
use AFSpaces\Domain\WorkingGroupMeta;
use PHPUnit\Framework\TestCase;

final class StubCreationRepository extends SpaceRepository {

	/** @var array<int,Space> */
	public array $spaces = array();

	/** @var SpaceManager[] */
	public array $managers = array();

	/** @var int[] */
	public array $deleted = array();

	public int $live_count = 0;

	public ?string $latest = null;

	private int $next_id = 1;

	public function __construct() {}

	public function count_owner_live_spaces( int $user_id ): int {
		return $this->live_count;
	}

	public function latest_created_at_for_owner( int $user_id ): ?string {
		return $this->latest;
	}

	public function create_space( Space $space ): int {
		$space->id = $this->next_id++;
		$this->spaces[ $space->id ] = $space;
		return $space->id;
	}

	public function add_manager( SpaceManager $manager ): void {
		$this->managers[] = $manager;
	}

	public function get_space( int $space_id ): ?Space {
		return $this->spaces[ $space_id ] ?? null;
	}

	public function delete_space( int $space_id ): void {
		$this->deleted[] = $space_id;
		unset( $this->spaces[ $space_id ] );
	}
}

final class StubCreationMeta extends SpaceMetaRepository {

	/** @var WorkingGroupMeta[] */
	public array $saved = array();

	public function __construct() {}

	public function save( WorkingGroupMeta $meta ): void {
		$this->saved[] = $meta;
	}
}

final class StubCreationAudit extends AuditRepository {

	/** @var array<int,array<string,mixed>> */
	public array $entries = array();

	public function __construct() {}

	public function log( int $space_id, int $actor_user_id, int $target_user_id, string $action, string $object_type = 'member' ): void {
		$this->entries[] = compact( 'space_id', 'actor_user_id', 'target_user_id', 'action', 'object_type' );
	}
}

final class StubCreationAdapter implements AsgarosAdapterInterface {

	public bool $fail_forum = false;
	public array $calls = array();

	private int $next = 100;

	public function is_available(): bool { return true; }
	public function get_version(): ?string { return '3.4.0'; }
	public function relocate_subscription_navigation(): void {}
	public function list_manageable_forums( int $actor_user_id ): array { return array(); }
	public function get_forum( int $forum_id ): ?array { return array( 'id' => $forum_id, 'category_id' => 900 ); }
	public function get_forum_group_ids( int $forum_id ): array { return array(); }
	public function get_group_name( int $group_id ): ?string { return null; }
	public function list_group_members( int $group_id, array $args = [] ): array { return array(); }
	public function add_user_to_group( int $user_id, int $group_id ): void { $this->calls[] = 'add_user_to_group'; }
	public function remove_user_from_group( int $user_id, int $group_id ): void {}
	public function is_user_in_group( int $user_id, int $group_id ): bool { return false; }
	public function search_posts( string $keywords, array $args = [] ): array { return array( 'results' => array(), 'total' => 0 ); }
	public function get_post_link( int $post_id, int $topic_id ): string { return ''; }
	public function list_accessible_category_ids(): array { return array(); }
	public function list_accessible_forums(): array { return array(); }
	public function count_all_posts(): int { return 0; }
	public function list_posts_for_index( int $limit, int $offset ): array { return array(); }
	public function is_search_request(): bool { return false; }

	public function create_forum_category( array $data ): int {
		$this->calls[] = 'create_forum_category';
		return $this->next++;
	}

	public function create_forum( array $data ): int {
		$this->calls[] = 'create_forum';
		if ( $this->fail_forum ) {
			throw new DomainException( 'forum failure' );
		}
		return $this->next++;
	}

	public function create_group( array $data ): int {
		$this->calls[] = 'create_group';
		return $this->next++;
	}

	public function assign_group_to_forum( int $forum_id, int $group_id ): void { $this->calls[] = 'assign_group_to_forum'; }
	public function set_forum_visibility( int $forum_id, array $data ): void { $this->calls[] = 'set_forum_visibility'; }
	public function update_forum( int $forum_id, array $data ): void {}
	public function delete_forum( int $forum_id ): void { $this->calls[] = 'delete_forum'; }
	public function delete_forum_category( int $category_id ): void { $this->calls[] = 'delete_forum_category'; }
	public function delete_group( int $group_id ): void { $this->calls[] = 'delete_group'; }

	public function list_forum_topics( int $forum_id, array $args = [] ): array { return array( 'topics' => array(), 'total' => 0 ); }
	public function get_topic_forum( int $topic_id ): int { return 0; }
	public function is_topic_pinned( int $topic_id ): bool { return false; }
	public function set_topic_closed( int $topic_id, bool $closed ): void {}
	public function set_topic_pinned( int $topic_id, bool $pinned ): void {}
	public function delete_forum_topic( int $topic_id ): void {}
	public function get_post_location( int $post_id ): ?array { return null; }
	public function delete_forum_post( int $post_id ): void {}
	public function move_topic( int $topic_id, int $target_forum_id ): void {}
	public function list_topic_posts( int $topic_id, array $args = [] ): array { return array( 'posts' => array(), 'total' => 0 ); }
	public function move_post( int $post_id, int $target_topic_id, int $target_forum_id ): void {}
}

final class SpaceCreationServiceTest extends TestCase {

	private StubCreationRepository $repo;
	private StubCreationMeta $meta;
	private StubCreationAudit $audit;
	private StubCreationAdapter $adapter;
	private SpaceCreationService $service;

	protected function setUp(): void {
		parent::setUp();

		global $afspaces_test_options, $afspaces_user_can_callback;
		$afspaces_test_options = array(
			SpaceCreationSettings::OPTION => array(
				'enabled'              => true,
				'require_approval'     => true,
				'max_spaces_per_user'  => 3,
				'allowed_roles'        => array( 'editor' ),
				'allowed_visibilities' => array( 'private', 'public' ),
				'rate_limit_seconds'   => 0,
			),
		);
		$afspaces_user_can_callback = static function ( int $user_id, string $cap ): bool {
			return 42 === $user_id && Capabilities::CREATE_SPACE === $cap;
		};

		$this->repo    = new StubCreationRepository();
		$this->meta    = new StubCreationMeta();
		$this->audit   = new StubCreationAudit();
		$this->adapter = new StubCreationAdapter();
		$this->service = new SpaceCreationService( $this->repo, $this->adapter, $this->meta, $this->audit );
	}

	public function test_create_produces_pending_space_with_owner(): void {
		$space = $this->service->create( 42, array( 'name' => 'Mein Raum', 'description' => 'Hallo', 'visibility' => 'private' ) );

		$this->assertSame( SpaceLifecycle::STATUS_PENDING, $space->status );
		$this->assertSame( 42, $space->owner_user_id );
		$this->assertCount( 1, $this->repo->managers );
		$this->assertSame( SpaceManager::ROLE_OWNER, $this->repo->managers[0]->role );
		$this->assertCount( 1, $this->meta->saved );
		$this->assertContains( 'create_forum_category', $this->adapter->calls );
		$this->assertContains( 'assign_group_to_forum', $this->adapter->calls, 'Pending-Räume müssen zugriffsbeschränkt sein.' );
		$this->assertSame( array(), $this->repo->deleted, 'Kein Rollback bei Erfolg.' );
	}

	public function test_rollback_on_forum_failure(): void {
		$this->adapter->fail_forum = true;

		try {
			$this->service->create( 42, array( 'name' => 'Mein Raum', 'visibility' => 'private' ) );
			$this->fail( 'Erwartete DomainException wurde nicht geworfen.' );
		} catch ( DomainException $e ) {
			// erwartet.
			$this->assertNotSame( '', $e->getMessage() );
		}

		$this->assertContains( 'delete_group', $this->adapter->calls, 'Gruppe muss beim Rollback entfernt werden.' );
		$this->assertContains( 'delete_forum_category', $this->adapter->calls, 'Kategorie muss beim Rollback entfernt werden.' );
		$this->assertSame( array(), $this->repo->spaces, 'Es darf kein Space-Datensatz zurückbleiben.' );
	}

	public function test_missing_permission_throws(): void {
		$this->expectException( DomainException::class );
		$this->service->create( 7, array( 'name' => 'Fremd', 'visibility' => 'private' ) );
	}

	public function test_disabled_setting_blocks_creation_even_when_legacy_flag_is_true(): void {
		global $afspaces_test_options;
		$afspaces_test_options[ SpaceCreationSettings::OPTION ]['enabled'] = false;
		$afspaces_test_options['afspaces_enable_space_creation'] = true;

		$this->assertFalse( $this->service->can_user_create( 42 ) );
	}

	public function test_quota_blocks_creation(): void {
		$this->repo->live_count = 3;
		$this->expectException( DomainException::class );
		$this->service->create( 42, array( 'name' => 'Zu viele', 'visibility' => 'private' ) );
	}
}
