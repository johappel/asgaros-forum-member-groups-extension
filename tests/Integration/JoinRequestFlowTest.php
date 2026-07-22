<?php
/**
 * Integrationstests fuer den Join-Request-Flow.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Domain\JoinRequest;

final class JoinRequestFlowTest extends IntegrationTestCase {

	private function make_user( string $suffix ): int {
		$login = 'afspaces_join_' . $suffix;
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

	public function test_create_request_is_idempotent_for_pending_state(): void {
		$owner    = $this->make_user( 'owner_req_' . uniqid( '', false ) );
		$requester = $this->make_user( 'requester_req_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$first  = $this->join_request_service->create_request( $space_id, $requester, 'Bitte aufnehmen' );
		$second = $this->join_request_service->create_request( $space_id, $requester, 'Zweiter Versuch' );

		$this->assertSame( $first->id, $second->id );
		$this->assertSame( JoinRequest::STATUS_PENDING, $second->status );
	}

	public function test_approve_request_adds_group_membership(): void {
		$owner    = $this->make_user( 'owner_app_' . uniqid( '', false ) );
		$requester = $this->make_user( 'requester_app_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$request = $this->join_request_service->create_request( $space_id, $requester, '' );
		$result  = $this->join_request_service->approve_request( $request->id, $owner, 'ok' );

		$this->assertSame( JoinRequest::STATUS_APPROVED, $result->status );
		$this->assertTrue( \AsgarosForumUserGroups::isUserInUserGroup( $requester, $this->group_id ) );

		$this->cleanup_user_from_group( $requester );
	}

	public function test_reject_request_keeps_user_outside_group(): void {
		$owner    = $this->make_user( 'owner_rej_' . uniqid( '', false ) );
		$requester = $this->make_user( 'requester_rej_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$request = $this->join_request_service->create_request( $space_id, $requester, '' );
		$result  = $this->join_request_service->reject_request( $request->id, $owner, 'nein' );

		$this->assertSame( JoinRequest::STATUS_REJECTED, $result->status );
		$this->assertFalse( \AsgarosForumUserGroups::isUserInUserGroup( $requester, $this->group_id ) );
	}
}
