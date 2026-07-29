<?php
/**
 * Unit-Tests für den ForumSearchService (Mapping und Pagination).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapterInterface;
use AFSpaces\Application\ForumSearchService;
use AFSpaces\Search\SearchHit;
use PHPUnit\Framework\TestCase;

/**
 * Minimaler Adapter-Stub für Suchtests.
 */
final class FakeSearchAdapter implements AsgarosAdapterInterface {

	/**
	 * @var array{results: array<int,array<string,mixed>>, total: int}
	 */
	public array $response = array(
		'results' => array(),
		'total'   => 0,
	);

	/**
	 * @var array<string,mixed>|null
	 */
	public ?array $last_args = null;

	public function is_available(): bool {
		return true;
	}

	public function get_version(): ?string {
		return '3.4.0';
	}

	public function list_manageable_forums( int $actor_user_id ): array {
		return array();
	}

	public function get_forum( int $forum_id ): ?array {
		return null;
	}

	public function get_forum_group_ids( int $forum_id ): array {
		return array();
	}

	public function list_group_members( int $group_id, array $args = [] ): array {
		return array();
	}

	public function add_user_to_group( int $user_id, int $group_id ): void {
	}

	public function remove_user_from_group( int $user_id, int $group_id ): void {
	}

	public function is_user_in_group( int $user_id, int $group_id ): bool {
		return false;
	}

	public function search_posts( string $keywords, array $args = [] ): array {
		$this->last_args = $args;
		return $this->response;
	}

	public function get_post_link( int $post_id, int $topic_id ): string {
		return '/forum/topic/x/?part=2#postid-' . $post_id;
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

	public function set_topic_closed( int $topic_id, bool $closed ): void {
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

final class ForumSearchServiceTest extends TestCase {

	public function test_empty_query_returns_no_hits_and_does_not_query(): void {
		$adapter = new FakeSearchAdapter();
		$service = new ForumSearchService( $adapter );

		$result = $service->search( '   ' );

		$this->assertSame( 0, $result['total'] );
		$this->assertSame( array(), $result['hits'] );
		$this->assertNull( $adapter->last_args, 'Adapter sollte bei leerer Query nicht aufgerufen werden.' );
	}

	public function test_maps_rows_to_search_hits(): void {
		$adapter = new FakeSearchAdapter();
		$adapter->response = array(
			'total'   => 1,
			'results' => array(
				array(
					'post_id'    => 142,
					'topic_id'   => 12,
					'forum_id'   => 3,
					'author_id'  => 7,
					'post_text'  => 'Wir haben ChatGPT und Fobizz verglichen.',
					'post_date'  => '2026-07-18 09:30:00',
					'topic_name' => 'Welche KI-Werkzeuge nutzen wir?',
					'forum_name' => 'Digitalisierung',
					'score'      => 4.2,
					'url'        => '/forum/topic/welche-ki-werkzeuge/?part=3#postid-142',
				),
			),
		);
		$service = new ForumSearchService( $adapter );

		$result = $service->search( 'ChatGPT', 'relevance', 1, 10 );

		$this->assertSame( 1, $result['total'] );
		$this->assertCount( 1, $result['hits'] );

		$hit = $result['hits'][0];
		$this->assertInstanceOf( SearchHit::class, $hit );
		$this->assertSame( SearchHit::SOURCE_FORUM, $hit->source );
		$this->assertSame( 'Welche KI-Werkzeuge nutzen wir?', $hit->title );
		$this->assertSame( '/forum/topic/welche-ki-werkzeuge/?part=3#postid-142', $hit->url );
		$this->assertSame( 'Digitalisierung', $hit->context_label );

		// Snippet muss die Fundstelle markieren.
		$marked = array_filter(
			$hit->snippet,
			static fn( array $seg ): bool => ! empty( $seg['mark'] )
		);
		$this->assertNotEmpty( $marked );
	}

	public function test_pagination_totals_are_computed(): void {
		$adapter = new FakeSearchAdapter();
		$adapter->response = array(
			'total'   => 25,
			'results' => array(
				array(
					'post_id'    => 1,
					'topic_id'   => 1,
					'forum_id'   => 1,
					'author_id'  => 1,
					'post_text'  => 'Treffer',
					'post_date'  => '2026-01-01 00:00:00',
					'topic_name' => 'Thema',
					'forum_name' => 'Forum',
					'score'      => 1.0,
					'url'        => '/x',
				),
			),
		);
		$service = new ForumSearchService( $adapter );

		$result = $service->search( 'Treffer', 'date', 2, 10 );

		$this->assertSame( 25, $result['total'] );
		$this->assertSame( 3, $result['total_pages'] );
		$this->assertSame( 2, $result['page'] );
		$this->assertSame( 'date', $adapter->last_args['sort'] ?? null );
	}
}
