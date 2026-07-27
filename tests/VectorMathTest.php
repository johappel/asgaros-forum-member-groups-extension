<?php
/**
 * Unit-Tests für VectorMath (Cosine, Normalisierung, (De-)Serialisierung).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Search\VectorMath;
use PHPUnit\Framework\TestCase;

final class VectorMathTest extends TestCase {

	public function test_cosine_identical_vectors_is_one(): void {
		$v = array( 1.0, 2.0, 3.0 );
		$this->assertEqualsWithDelta( 1.0, VectorMath::cosine( $v, $v ), 1e-9 );
	}

	public function test_cosine_orthogonal_vectors_is_zero(): void {
		$this->assertEqualsWithDelta( 0.0, VectorMath::cosine( array( 1.0, 0.0 ), array( 0.0, 1.0 ) ), 1e-9 );
	}

	public function test_cosine_opposite_vectors_is_minus_one(): void {
		$this->assertEqualsWithDelta( -1.0, VectorMath::cosine( array( 1.0, 1.0 ), array( -1.0, -1.0 ) ), 1e-9 );
	}

	public function test_cosine_mismatched_length_is_zero(): void {
		$this->assertSame( 0.0, VectorMath::cosine( array( 1.0, 2.0 ), array( 1.0 ) ) );
	}

	public function test_normalize_produces_unit_length(): void {
		$n   = VectorMath::normalize( array( 3.0, 4.0 ) );
		$len = sqrt( $n[0] * $n[0] + $n[1] * $n[1] );
		$this->assertEqualsWithDelta( 1.0, $len, 1e-9 );
		$this->assertEqualsWithDelta( 0.6, $n[0], 1e-9 );
		$this->assertEqualsWithDelta( 0.8, $n[1], 1e-9 );
	}

	public function test_dot_of_normalized_equals_cosine(): void {
		$a  = VectorMath::normalize( array( 1.0, 2.0, 3.0 ) );
		$b  = VectorMath::normalize( array( 2.0, 1.0, 0.5 ) );
		$this->assertEqualsWithDelta(
			VectorMath::cosine( array( 1.0, 2.0, 3.0 ), array( 2.0, 1.0, 0.5 ) ),
			VectorMath::dot( $a, $b ),
			1e-9
		);
	}

	public function test_pack_and_unpack_roundtrip(): void {
		$vector = array( 0.5, -1.25, 3.75, 0.0 );
		$blob   = VectorMath::pack_vector( $vector );
		$this->assertNotSame( '', $blob );

		$restored = VectorMath::unpack_vector( $blob );
		$this->assertCount( 4, $restored );
		foreach ( $vector as $i => $expected ) {
			$this->assertEqualsWithDelta( $expected, $restored[ $i ], 1e-6 );
		}
	}

	public function test_unpack_empty_blob_returns_empty(): void {
		$this->assertSame( array(), VectorMath::unpack_vector( '' ) );
	}
}
