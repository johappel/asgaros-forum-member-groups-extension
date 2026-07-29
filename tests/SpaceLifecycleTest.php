<?php
/**
 * Unit-Tests für das Space-Lebenszyklusmodell.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Domain\SpaceLifecycle;
use PHPUnit\Framework\TestCase;

final class SpaceLifecycleTest extends TestCase {

	public function test_valid_statuses(): void {
		$this->assertTrue( SpaceLifecycle::is_valid_status( SpaceLifecycle::STATUS_PENDING ) );
		$this->assertTrue( SpaceLifecycle::is_valid_status( SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertFalse( SpaceLifecycle::is_valid_status( 'unknown' ) );
	}

	public function test_allowed_transitions(): void {
		$this->assertTrue( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_PENDING, SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertTrue( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_PENDING, SpaceLifecycle::STATUS_REJECTED ) );
		$this->assertTrue( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_ACTIVE, SpaceLifecycle::STATUS_ARCHIVED ) );
		$this->assertTrue( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_ARCHIVED, SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertTrue( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_ACTIVE, SpaceLifecycle::STATUS_DELETED ) );
	}

	public function test_disallowed_transitions(): void {
		$this->assertFalse( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_ACTIVE, SpaceLifecycle::STATUS_PENDING ) );
		$this->assertFalse( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_REJECTED, SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertFalse( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_DELETED, SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertFalse( SpaceLifecycle::can_transition( SpaceLifecycle::STATUS_ACTIVE, SpaceLifecycle::STATUS_ACTIVE ), 'Selbstübergang ist unzulässig.' );
	}

	public function test_accessibility_and_liveness(): void {
		$this->assertTrue( SpaceLifecycle::is_accessible( SpaceLifecycle::STATUS_ACTIVE ) );
		$this->assertFalse( SpaceLifecycle::is_accessible( SpaceLifecycle::STATUS_PENDING ) );
		$this->assertFalse( SpaceLifecycle::is_accessible( SpaceLifecycle::STATUS_ARCHIVED ) );

		$this->assertTrue( SpaceLifecycle::is_live( SpaceLifecycle::STATUS_ARCHIVED ) );
		$this->assertFalse( SpaceLifecycle::is_live( SpaceLifecycle::STATUS_DELETED ) );
	}
}
