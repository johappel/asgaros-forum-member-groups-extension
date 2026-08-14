<?php
/**
 * Unit-Tests fuer Arbeitsgruppen-Metadaten.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Domain\WorkingGroupMeta;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Domain/WorkingGroupMeta.php';

final class WorkingGroupMetaTest extends TestCase {

	public function test_defaults_for_existing_spaces_are_safe(): void {
		$meta = WorkingGroupMeta::defaults_for_space( 42 );

		$this->assertSame( 42, $meta->space_id );
		$this->assertSame( '#2d5d7f', $meta->accent_color );
		$this->assertSame( WorkingGroupMeta::JOIN_POLICY_REQUEST, $meta->join_policy );
		$this->assertTrue( $meta->join_requests_enabled );
		$this->assertSame( array(), $meta->topic_ids );
	}

	public function test_topic_ids_are_normalized_from_json(): void {
		$meta = new WorkingGroupMeta(
			array(
				'space_id'  => 7,
				'topic_ids' => '[3, 5, 5, 0, -1]',
			)
		);

		$this->assertSame( array( 3, 5 ), $meta->topic_ids );
	}

	public function test_accent_color_is_limited_to_the_corporate_palette(): void {
		$this->assertSame(
			array( '#77429e', '#2d5d7f', '#f5ae35', '#5563a5' ),
			WorkingGroupMeta::accent_colors()
		);

		$invalid = new WorkingGroupMeta( array( 'accent_color' => '#114488' ) );
		$this->assertSame( '#2d5d7f', $invalid->accent_color );

		$allowed = new WorkingGroupMeta( array( 'accent_color' => '#F5AE35' ) );
		$this->assertSame( '#f5ae35', $allowed->accent_color );
	}
}
