<?php
/**
 * Unit-Tests für die Space-Policy.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Database\SpaceRepository;
use AFSpaces\Domain\Space;
use AFSpaces\Domain\SpaceManager;
use AFSpaces\Domain\SpacePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Stub für SpaceRepository mit konfigurierbarem Verhalten.
 */
class StubSpaceRepository extends SpaceRepository {

	private bool $manager_flag = false;
	private int $owner_count = 1;
	private ?Space $space = null;

	public function set_is_manager( bool $flag ): void {
		$this->manager_flag = $flag;
	}

	public function set_owner_count( int $count ): void {
		$this->owner_count = $count;
	}

	public function set_space( ?Space $space ): void {
		$this->space = $space;
	}

	public function is_manager( int $space_id, int $user_id ): bool {
		return $this->manager_flag;
	}

	public function count_owners( int $space_id ): int {
		return $this->owner_count;
	}

	public function get_space( int $space_id ): ?Space {
		return $this->space;
	}
}

/**
 * Tests der Berechtigungslogik.
 */
final class SpacePolicyTest extends TestCase {

	private StubSpaceRepository $repo;
	private SpacePolicy $policy;

	protected function setUp(): void {
		parent::setUp();
		$this->repo   = new StubSpaceRepository();
		$this->policy = new SpacePolicy( $this->repo );
	}

	/**
	 * @testdox Manager darf den eigenen Space verwalten.
	 */
	public function test_manager_can_manage_own_space(): void {
		$this->repo->set_is_manager( true );
		$this->assertTrue( $this->policy->can_manage( 1, 42 ) );
	}

	/**
	 * @testdox Nicht-Manager darf fremden Space nicht verwalten.
	 */
	public function test_non_manager_cannot_manage_foreign_space(): void {
		$this->repo->set_is_manager( false );
		$this->assertFalse( $this->policy->can_manage( 1, 42 ) );
	}

	/**
	 * @testdox Letzter Owner kann nicht entfernt werden.
	 */
	public function test_last_owner_cannot_be_removed(): void {
		$space = new Space( array( 'id' => 1, 'owner_user_id' => 7 ) );
		$this->repo->set_space( $space );
		$this->repo->set_is_manager( true );
		$this->repo->set_owner_count( 1 );

		$this->assertFalse( $this->policy->can_remove_member( 1, 42, 7 ) );
	}

	/**
	 * @testdox Owner kann entfernt werden, wenn andere Owner existieren.
	 */
	public function test_owner_can_be_removed_when_other_owners_exist(): void {
		$space = new Space( array( 'id' => 1, 'owner_user_id' => 7 ) );
		$this->repo->set_space( $space );
		$this->repo->set_is_manager( true );
		$this->repo->set_owner_count( 2 );

		$this->assertTrue( $this->policy->can_remove_member( 1, 42, 7 ) );
	}

	/**
	 * @testdox Nicht-Owner-Mitglied kann entfernt werden.
	 */
	public function test_non_owner_member_can_be_removed(): void {
		$space = new Space( array( 'id' => 1, 'owner_user_id' => 7 ) );
		$this->repo->set_space( $space );
		$this->repo->set_is_manager( true );
		$this->repo->set_owner_count( 1 );

		$this->assertTrue( $this->policy->can_remove_member( 1, 42, 99 ) );
	}

	/**
	 * @testdox Manager darf persönliche Einladungen erstellen.
	 */
	public function test_manager_can_invite_member(): void {
		$this->repo->set_is_manager( true );
		$this->assertTrue( $this->policy->can_invite_member( 1, 42, 88 ) );
	}

	/**
	 * @testdox Nicht-Manager darf keine Einladung widerrufen.
	 */
	public function test_non_manager_cannot_revoke_invitation(): void {
		$this->repo->set_is_manager( false );
		$this->assertFalse( $this->policy->can_revoke_invitation( 1, 42, 88 ) );
	}

	/**
	 * @testdox Manager darf Invite-Links verwalten.
	 */
	public function test_manager_can_manage_invite_links(): void {
		$this->repo->set_is_manager( true );
		$this->assertTrue( $this->policy->can_manage_invite_links( 1, 42 ) );
	}

	/**
	 * @testdox Nicht-Manager darf keine unbegrenzten Invite-Links erstellen.
	 */
	public function test_non_manager_cannot_create_unlimited_invite_links(): void {
		$this->repo->set_is_manager( false );
		$this->assertFalse( $this->policy->can_create_unlimited_invite_links( 1, 42 ) );
	}
}
