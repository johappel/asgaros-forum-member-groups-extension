<?php
/**
 * Integrationstest für die Freigabezählung gegen die echte WordPress-Datenbank.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Application\SpaceLifecycleService;
use AFSpaces\Core\Capabilities;
use AFSpaces\Domain\Space;

/**
 * Stellt sicher, dass die Navigation den Statuswechsel direkt aus der DB sieht.
 */
final class SpaceApprovalNavigationTest extends IntegrationTestCase {

	/** @var int[] */
	private array $approval_space_ids = array();

	public function test_pending_count_is_scoped_by_permission_and_status(): void {
		$moderator = wp_insert_user(
			array(
				'user_login' => 'afspaces_approval_nav_' . wp_generate_password( 8, false ),
				'user_pass'  => 'password',
				'user_email' => 'afspaces_approval_nav_' . wp_generate_password( 8, false ) . '@example.com',
			)
		);
		$this->assertIsInt( $moderator );
		$moderator_user = get_user_by( 'id', $moderator );
		$moderator_user->add_cap( Capabilities::MODERATE_SPACE );

		$space = new Space(
			array(
				'forum_id'         => 9001,
				'primary_group_id' => 9001,
				'owner_user_id'    => $moderator,
				'visibility'       => 'private',
				'status'           => 'pending',
			)
		);
		$this->approval_space_ids[] = $this->spaces->create_space( $space );

		$lifecycle = new SpaceLifecycleService(
			$this->spaces,
			$this->asgaros,
			$this->space_meta_repository,
			$this->audit
		);

		$this->assertSame( 0, $lifecycle->count_pending_for_actor( 0 ) );
		$this->assertSame( 1, $lifecycle->count_pending_for_actor( $moderator ) );

		$this->spaces->update_status( $this->approval_space_ids[0], 'active' );

		$this->assertSame( 0, $lifecycle->count_pending_for_actor( $moderator ) );
	}

	protected function tearDown(): void {
		foreach ( $this->approval_space_ids as $space_id ) {
			$this->spaces->delete_space( $space_id );
		}
		$this->approval_space_ids = array();

		parent::tearDown();
	}
}
