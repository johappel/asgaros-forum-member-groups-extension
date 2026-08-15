<?php
/**
 * Unit-Tests für die Registrierung bestehender Foren als Space.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Application\SpaceRegistrationService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceManager;
use PHPUnit\Framework\TestCase;

final class StubSpaceRegistrationRepository extends SpaceRepository {

	/** @var array<int,Space> */
	public array $spaces = array();

	/** @var SpaceManager[] */
	public array $managers = array();

	private int $next_id = 1;

	public function __construct() {}

	public function get_space_by_forum( int $forum_id ): ?Space {
		foreach ( $this->spaces as $space ) {
			if ( $space->forum_id === $forum_id ) {
				return $space;
			}
		}

		return null;
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

	public function is_manager( int $space_id, int $user_id ): bool {
		return false;
	}
}

final class StubSpaceRegistrationAdapter implements AsgarosAdapterInterface {

	/** @var array<int,array<string,mixed>> */
	public array $forums = array();

	/** @var array<int,int[]> */
	public array $group_map = array();

	/** @var array<int,string> */
	public array $group_names = array();

	public function is_available(): bool {
		return true;
	}

	public function get_version(): ?string {
		return '3.4.0';
	}

	public function relocate_subscription_navigation(): void {
	}

	public function list_manageable_forums( int $actor_user_id ): array {
		return array_values( $this->forums );
	}

	public function get_forum( int $forum_id ): ?array {
		return $this->forums[ $forum_id ] ?? null;
	}

	public function get_forum_group_ids( int $forum_id ): array {
		return $this->group_map[ $forum_id ] ?? array();
	}

	public function get_group_name( int $group_id ): ?string {
		return $this->group_names[ $group_id ] ?? null;
	}

	public function list_group_members( int $group_id, array $args = [] ): array {
		return array();
	}

	public function add_user_to_group( int $user_id, int $group_id ): void {}

	public function remove_user_from_group( int $user_id, int $group_id ): void {}

	public function is_user_in_group( int $user_id, int $group_id ): bool {
		return false;
	}

	public function search_posts( string $keywords, array $args = [] ): array {
		return array( 'results' => array(), 'total' => 0 );
	}

	public function get_post_link( int $post_id, int $topic_id ): string {
		return '';
	}

	public function list_accessible_category_ids(): array {
		return array();
	}

	public function list_accessible_forums(): array {
		return array();
	}

	public function count_all_posts(): int {
		return 0;
	}

	public function list_posts_for_index( int $limit, int $offset ): array {
		return array();
	}

	public function is_search_request(): bool {
		return false;
	}

	public function create_forum_category( array $data ): int {
		return 0;
	}

	public function create_forum( array $data ): int {
		return 0;
	}

	public function create_group( array $data ): int {
		return 0;
	}

	public function assign_group_to_forum( int $forum_id, int $group_id ): void {
	}

	public function set_forum_visibility( int $forum_id, array $data ): void {
	}

	public function update_forum( int $forum_id, array $data ): void {
	}

	public function delete_forum( int $forum_id ): void {
	}

	public function delete_forum_category( int $category_id ): void {
	}

	public function delete_group( int $group_id ): void {
	}

	public function list_forum_topics( int $forum_id, array $args = [] ): array {
		return array( 'topics' => array(), 'total' => 0 );
	}

	public function get_topic_forum( int $topic_id ): int {
		return 0;
	}

	public function is_topic_pinned( int $topic_id ): bool {
		return false;
	}

	public function set_topic_closed( int $topic_id, bool $closed ): void {
	}

	public function set_topic_pinned( int $topic_id, bool $pinned ): void {
	}

	public function delete_forum_topic( int $topic_id ): void {
	}

	public function get_post_location( int $post_id ): ?array {
		return null;
	}

	public function delete_forum_post( int $post_id ): void {
	}

	public function move_topic( int $topic_id, int $target_forum_id ): void {
	}

	public function list_topic_posts( int $topic_id, array $args = [] ): array {
		return array( 'posts' => array(), 'total' => 0 );
	}

	public function move_post( int $post_id, int $target_topic_id, int $target_forum_id ): void {
	}
}

final class SpaceRegistrationServiceTest extends TestCase {

	private StubSpaceRegistrationRepository $repo;
	private StubSpaceRegistrationAdapter $adapter;
	private SpaceRegistrationService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->repo = new StubSpaceRegistrationRepository();
		$this->adapter = new StubSpaceRegistrationAdapter();
		$this->service = new SpaceRegistrationService( $this->repo, $this->adapter );

		$this->adapter->forums = array(
			7 => array( 'id' => 7, 'name' => 'AFSpaces Testforum', 'category_id' => 3 ),
		);
		$this->adapter->group_map = array(
			7 => array( 23 ),
		);
		$this->adapter->group_names = array(
			23 => 'NEKED',
		);

		global $afspaces_user_can_callback;
		$afspaces_user_can_callback = static function ( int $user_id, string $capability ): bool {
			return 55 === $user_id && in_array( $capability, array( Capabilities::CREATE_SPACE, Capabilities::MANAGE_ALL_SPACES ), true );
		};
	}

	public function test_register_existing_forum_creates_space_and_owner_mapping(): void {
		$space = $this->service->register_existing_forum( 7, 55 );

		$this->assertSame( 1, $space->id );
		$this->assertSame( 7, $space->forum_id );
		$this->assertSame( 23, $space->primary_group_id );
		$this->assertCount( 1, $this->repo->managers );
		$this->assertSame( SpaceManager::ROLE_OWNER, $this->repo->managers[0]->role );
	}

	public function test_register_existing_forum_requires_group_mapping(): void {
		$this->expectException( DomainException::class );
		$this->adapter->group_map = array();

		$this->service->register_existing_forum( 7, 55 );
	}

	public function test_register_existing_forum_rejects_unresolved_group(): void {
		$this->expectException( DomainException::class );
		$this->adapter->group_names = array();

		$this->service->register_existing_forum( 7, 55 );
	}

	public function test_list_registrable_forums_exposes_group_name_and_status(): void {
		$list = $this->service->list_registrable_forums( 55 );

		$this->assertSame( array( 'NEKED' ), $list[0]['group_names'] );
		$this->assertSame( 23, $list[0]['primary_group_id'] );
		$this->assertSame( 'NEKED', $list[0]['primary_group_name'] );
		$this->assertSame( 'registrable', $list[0]['status'] );
		$this->assertTrue( $list[0]['can_register'] );
	}

	public function test_list_registrable_forums_marks_missing_group_as_setup_required(): void {
		$this->adapter->group_names = array();

		$list = $this->service->list_registrable_forums( 55 );

		$this->assertSame( 'setup_required', $list[0]['status'] );
		$this->assertTrue( $list[0]['group_resolution_failed'] );
		$this->assertFalse( $list[0]['can_register'] );
	}

	public function test_list_registrable_forums_sorts_by_actionable_status_then_name(): void {
		$this->adapter->forums = array(
			7  => array( 'id' => 7, 'name' => 'Zeta', 'category_id' => 3 ),
			8  => array( 'id' => 8, 'name' => 'Alpha', 'category_id' => 3 ),
			9  => array( 'id' => 9, 'name' => 'Beta', 'category_id' => 3 ),
			10 => array( 'id' => 10, 'name' => 'Gamma', 'category_id' => 3 ),
		);
		$this->adapter->group_map = array(
			7  => array( 23 ),
			8  => array(),
			9  => array( 24 ),
			10 => array( 25 ),
		);
		$this->adapter->group_names = array(
			23 => 'NEKED',
			24 => 'Entwicklung',
			25 => 'Marketing',
		);
		$this->repo->create_space(
			new Space(
				array(
					'forum_id'         => 10,
					'primary_group_id' => 25,
					'owner_user_id'    => 55,
				)
			)
		);

		$list = $this->service->list_registrable_forums( 55 );

		$this->assertSame( array( 'Beta', 'Zeta', 'Alpha', 'Gamma' ), array_column( $list, 'name' ) );
		$this->assertSame( array( 'registrable', 'registrable', 'setup_required', 'registered' ), array_column( $list, 'status' ) );
	}

	public function test_list_registrable_forums_marks_existing_space(): void {
		$this->repo->create_space(
			new Space(
				array(
					'forum_id'         => 7,
					'primary_group_id' => 23,
					'owner_user_id'    => 55,
				)
			)
		);

		$list = $this->service->list_registrable_forums( 55 );

		$this->assertCount( 1, $list );
		$this->assertTrue( $list[0]['is_registered'] );
		$this->assertFalse( $list[0]['can_register'] );
	}
}
