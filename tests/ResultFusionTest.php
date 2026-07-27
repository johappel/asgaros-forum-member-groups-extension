<?php
/**
 * Unit-Tests für ResultFusion (Reciprocal Rank Fusion).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Search\ResultFusion;
use PHPUnit\Framework\TestCase;

final class ResultFusionTest extends TestCase {

	/**
	 * @param array<int,array{key:string,score:float}> $fused Ergebnis.
	 * @return string[]
	 */
	private function keys( array $fused ): array {
		return array_map( static fn( array $r ): string => $r['key'], $fused );
	}

	public function test_single_list_preserves_order(): void {
		$fused = ResultFusion::fuse(
			array(
				array( 'keys' => array( 'a', 'b', 'c' ) ),
			)
		);
		$this->assertSame( array( 'a', 'b', 'c' ), $this->keys( $fused ) );
	}

	public function test_item_in_multiple_lists_ranks_higher(): void {
		$fused = ResultFusion::fuse(
			array(
				array( 'keys' => array( 'x', 'shared', 'y' ) ),
				array( 'keys' => array( 'z', 'shared', 'w' ) ),
			)
		);
		// 'shared' erscheint in beiden Listen und muss ganz oben stehen.
		$this->assertSame( 'shared', $this->keys( $fused )[0] );
	}

	public function test_weight_influences_ranking(): void {
		$low = ResultFusion::fuse(
			array(
				array( 'keys' => array( 'keyword' ), 'weight' => 1.0 ),
				array( 'keys' => array( 'semantic' ), 'weight' => 0.1 ),
			)
		);
		$this->assertSame( 'keyword', $this->keys( $low )[0] );

		$high = ResultFusion::fuse(
			array(
				array( 'keys' => array( 'keyword' ), 'weight' => 0.1 ),
				array( 'keys' => array( 'semantic' ), 'weight' => 5.0 ),
			)
		);
		$this->assertSame( 'semantic', $this->keys( $high )[0] );
	}

	public function test_zero_weight_list_is_ignored(): void {
		$fused = ResultFusion::fuse(
			array(
				array( 'keys' => array( 'a' ), 'weight' => 0.0 ),
				array( 'keys' => array( 'b' ), 'weight' => 1.0 ),
			)
		);
		$this->assertSame( array( 'b' ), $this->keys( $fused ) );
	}

	public function test_empty_input_returns_empty(): void {
		$this->assertSame( array(), ResultFusion::fuse( array() ) );
	}
}
