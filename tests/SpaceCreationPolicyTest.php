<?php
/**
 * Unit-Tests für die Raumgründungs-Policy.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Core\DomainException;
use AFSpaces\Core\SpaceCreationSettings;
use AFSpaces\Domain\SpaceCreationPolicy;
use PHPUnit\Framework\TestCase;

final class SpaceCreationPolicyTest extends TestCase {

	private SpaceCreationPolicy $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->policy = new SpaceCreationPolicy();
	}

	private function settings( array $overrides = array() ): SpaceCreationSettings {
		return new SpaceCreationSettings(
			array_merge(
				array(
					'enabled'              => true,
					'max_spaces_per_user'  => 2,
					'allowed_visibilities' => array( 'private', 'public' ),
					'name_min_length'      => 3,
					'name_max_length'      => 20,
					'reserved_names'       => array( 'admin' ),
					'rate_limit_seconds'   => 300,
				),
				$overrides
			)
		);
	}

	public function test_disabled_feature_blocks_creation(): void {
		$this->expectException( DomainException::class );
		$this->policy->assert_can_create( $this->settings( array( 'enabled' => false ) ), false, true, array( 'author' ) );
	}

	public function test_enabled_without_roles_allows_any_logged_in_user(): void {
		// Aktiv + keine Rolleneinschränkung => alle angemeldeten Personen dürfen gründen.
		$this->policy->assert_can_create( $this->settings( array( 'allowed_roles' => array() ) ), false, false, array( 'author' ) );
		$this->assertTrue( true );
	}

	public function test_admin_does_not_bypass_disabled_feature(): void {
		$this->expectException( DomainException::class );
		$this->policy->assert_can_create( $this->settings( array( 'enabled' => false ) ), true, false, array() );
	}

	public function test_admin_bypasses_quota_and_rate_limit(): void {
		$this->policy->assert_within_quota( $this->settings( array( 'max_spaces_per_user' => 1 ) ), true, 99 );
		$this->policy->assert_rate_limit( $this->settings(), true, 0 );
		$this->assertTrue( true );
	}

	public function test_role_restriction(): void {
		$settings = $this->settings( array( 'allowed_roles' => array( 'editor' ) ) );

		// Freigeschaltete Rolle darf auch ohne explizite Capability gründen.
		$this->policy->assert_can_create( $settings, false, false, array( 'editor' ) );

		// Weder passende Rolle noch Capability -> blockiert.
		$this->expectException( DomainException::class );
		$this->policy->assert_can_create( $settings, false, false, array( 'author' ) );
	}

	public function test_capability_grants_even_with_role_restriction(): void {
		// Bei eingeschränkten Rollen berechtigt auch die Gründungs-Capability.
		$settings = $this->settings( array( 'allowed_roles' => array( 'editor' ) ) );
		$this->policy->assert_can_create( $settings, false, true, array( 'author' ) );
		$this->assertTrue( true );
	}

	public function test_quota_enforced(): void {
		$this->policy->assert_within_quota( $this->settings(), false, 1 );

		$this->expectException( DomainException::class );
		$this->policy->assert_within_quota( $this->settings(), false, 2 );
	}

	public function test_rate_limit_enforced(): void {
		$this->policy->assert_rate_limit( $this->settings(), false, 301 );
		$this->policy->assert_rate_limit( $this->settings(), false, null );

		$this->expectException( DomainException::class );
		$this->policy->assert_rate_limit( $this->settings(), false, 10 );
	}

	public function test_name_validation(): void {
		$this->assertSame( 'Mein Raum', $this->policy->validate_name( $this->settings(), '  Mein   Raum  ' ) );
	}

	public function test_name_too_short(): void {
		$this->expectException( DomainException::class );
		$this->policy->validate_name( $this->settings(), 'ab' );
	}

	public function test_name_reserved(): void {
		$this->expectException( DomainException::class );
		$this->policy->validate_name( $this->settings(), 'Admin' );
	}

	public function test_visibility_validation(): void {
		$this->assertSame( 'private', $this->policy->validate_visibility( $this->settings(), 'private' ) );

		$this->expectException( DomainException::class );
		$this->policy->validate_visibility( $this->settings(), 'protected' );
	}
}
