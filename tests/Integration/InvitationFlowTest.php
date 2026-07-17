<?php
/**
 * Integrationstests: persönlicher Einladungsfluss.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Domain\Invitation;

final class InvitationFlowTest extends IntegrationTestCase {

	/**
	 * @param string $suffix Suffix.
	 * @return int
	 */
	private function make_user( string $suffix ): int {
		$login = 'afspaces_inv_' . $suffix;
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return (int) $user->ID;
		}
		$user_id = wp_create_user( $login, 'password', $suffix . '@example.com' );
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	public function test_invitation_accept_adds_group_membership(): void {
		$actor    = $this->make_user( 'actor_acc_' . uniqid( '', false ) );
		$invitee  = $this->make_user( 'invitee_acc_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $actor );

		$inv = $this->invitation_service->create_invitation( $space_id, $actor, $invitee, 'Willkommen', 7 );
		$token = $this->invitation_service->build_token( $inv );

		$result = $this->invitation_service->accept_invitation_by_token( $token, $invitee );

		$this->assertSame( Invitation::STATUS_ACCEPTED, $result->status );
		$this->assertTrue( \AsgarosForumUserGroups::isUserInUserGroup( $invitee, $this->group_id ) );

		$this->cleanup_user_from_group( $invitee );
	}

	public function test_decline_does_not_add_group_membership(): void {
		$actor    = $this->make_user( 'actor_dec_' . uniqid( '', false ) );
		$invitee  = $this->make_user( 'invitee_dec_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $actor );

		$inv = $this->invitation_service->create_invitation( $space_id, $actor, $invitee, 'Info', 7 );
		$token = $this->invitation_service->build_token( $inv );

		$result = $this->invitation_service->decline_invitation_by_token( $token, $invitee );

		$this->assertSame( Invitation::STATUS_DECLINED, $result->status );
		$this->assertFalse( \AsgarosForumUserGroups::isUserInUserGroup( $invitee, $this->group_id ) );
	}

	public function test_revoke_prevents_later_acceptance(): void {
		$actor    = $this->make_user( 'actor_rev_' . uniqid( '', false ) );
		$invitee  = $this->make_user( 'invitee_rev_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $actor );

		$inv = $this->invitation_service->create_invitation( $space_id, $actor, $invitee, '', 7 );
		$token = $this->invitation_service->build_token( $inv );

		$this->invitation_service->revoke_invitation( $inv->id, $actor );

		$this->expectException( \AFSpaces\Core\DomainException::class );
		$this->invitation_service->accept_invitation_by_token( $token, $invitee );
	}

	public function test_resend_keeps_same_token(): void {
		$actor    = $this->make_user( 'actor_res_' . uniqid( '', false ) );
		$invitee  = $this->make_user( 'invitee_res_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $actor );

		$inv = $this->invitation_service->create_invitation( $space_id, $actor, $invitee, '', 7 );
		$before = $this->invitation_service->build_token( $inv );

		// Drosselung umgehen für Test.
		delete_transient( 'afspaces_invite_mail_' . $inv->id );
		$this->invitation_service->resend_invitation( $inv->id, $actor );
		$after = $this->invitation_service->build_token( $this->invitation_repository->get_by_id( $inv->id ) );

		$this->assertSame( $before, $after );
	}

	public function test_expired_invitation_cannot_be_accepted(): void {
		$actor    = $this->make_user( 'actor_exp_' . uniqid( '', false ) );
		$invitee  = $this->make_user( 'invitee_exp_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $actor );

		$inv = $this->invitation_repository->create(
			$space_id,
			$actor,
			$invitee,
			'',
			gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
		);

		$token = $this->invitation_service->build_token( $inv );

		$this->expectException( \AFSpaces\Core\DomainException::class );
		$this->invitation_service->accept_invitation_by_token( $token, $invitee );
	}
}
