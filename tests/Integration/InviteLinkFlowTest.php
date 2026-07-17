<?php
/**
 * Integrationstests für sichere Einladungslinks.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Core\DomainException;

final class InviteLinkFlowTest extends IntegrationTestCase {

	private function make_user( string $suffix ): int {
		$login = 'afspaces_link_' . $suffix;
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return (int) $user->ID;
		}

		$user_id = wp_create_user( $login, 'password', $suffix . '@example.com' );
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	public function test_valid_link_adds_group_membership(): void {
		$owner    = $this->make_user( 'owner_' . uniqid( '', false ) );
		$target   = $this->make_user( 'target_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$result = $this->invite_link_service->create_link( $space_id, $owner, array( 'max_uses' => 1 ) );
		$used   = $this->invite_link_service->use_link( $result['token'], $target );

		$this->assertSame( 'joined', $used['result'] );
		$this->assertTrue( \AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ) );

		$this->cleanup_user_from_group( $target );
	}

	public function test_last_allowed_use_works_exactly_once(): void {
		$owner      = $this->make_user( 'owner_last_' . uniqid( '', false ) );
		$first      = $this->make_user( 'first_' . uniqid( '', false ) );
		$second     = $this->make_user( 'second_' . uniqid( '', false ) );
		$space_id   = $this->create_test_space( $owner );

		$result = $this->invite_link_service->create_link( $space_id, $owner, array( 'max_uses' => 1 ) );

		$this->invite_link_service->use_link( $result['token'], $first );

		$this->expectException( DomainException::class );
		$this->invite_link_service->use_link( $result['token'], $second );
	}

	public function test_revoked_link_is_rejected(): void {
		$owner    = $this->make_user( 'owner_rev_' . uniqid( '', false ) );
		$target   = $this->make_user( 'target_rev_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$result = $this->invite_link_service->create_link( $space_id, $owner, array() );
		$this->invite_link_service->revoke_link( $result['link']->id, $owner );

		$this->expectException( DomainException::class );
		$this->invite_link_service->use_link( $result['token'], $target );
	}

	public function test_approval_required_creates_no_membership(): void {
		$owner    = $this->make_user( 'owner_req_' . uniqid( '', false ) );
		$target   = $this->make_user( 'target_req_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$result = $this->invite_link_service->create_link(
			$space_id,
			$owner,
			array(
				'approval_mode' => 'approval_required',
			)
		);
		$used = $this->invite_link_service->use_link( $result['token'], $target );

		$this->assertSame( 'request_created', $used['result'] );
		$this->assertFalse( \AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ) );
		$this->assertNotEmpty( $this->audit->list_for_space( $space_id ) );
	}

	public function test_plain_token_never_appears_in_stored_logs(): void {
		global $wpdb;

		$owner    = $this->make_user( 'owner_log_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );
		$result   = $this->invite_link_service->create_link( $space_id, $owner, array() );

		$audit_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}afspaces_audit WHERE space_id = %d;", $space_id ), ARRAY_A );
		$link_rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}afspaces_invite_links WHERE space_id = %d;", $space_id ), ARRAY_A );

		$this->assertStringNotContainsString( $result['token'], wp_json_encode( $audit_rows ) );
		$this->assertStringNotContainsString( $result['token'], wp_json_encode( $link_rows ) );
	}
}