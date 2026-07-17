<?php
/**
 * REST/Sicherheit-Integrationstests gegen echte WP + Asgaros.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Core\Capabilities;

/**
 * Testet REST-Endpunkte und Berechtigungsprüfungen.
 */
final class RestSecurityTest extends IntegrationTestCase {

	/**
	 * Erstellt einen Benutzer mit einer bestimmten Capability.
	 *
	 * @param string $suffix  Eindeutiges Suffix.
	 * @param string $cap     Optional zuzuweisende Capability.
	 * @return int Benutzer-ID.
	 */
	private function make_user_with_cap( string $suffix, string $cap = '' ): int {
		$login = 'afspaces_rest_' . $suffix;
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return (int) $user->ID;
		}
		$user_id = wp_create_user( $login, 'password', $suffix . '@example.com' );
		if ( ! is_wp_error( $user_id ) && $cap ) {
			$u = get_user_by( 'id', $user_id );
			$u->add_cap( $cap );
		}
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	/**
	 * Test: Anonymer Zugriff auf Schreibendpunkt scheitert.
	 */
	public function test_anonymous_write_is_forbidden(): void {
		$space_id = $this->create_test_space( 1 );

		$request = new \WP_REST_Request( 'POST', '/afspaces/v1/spaces/' . $space_id . '/members' );
		$request->set_param( 'user_id', 5 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status(), 'Anonym muss 401 erhalten.' );
	}

	/**
	 * Test: Manager eines anderen Spaces scheitert.
	 */
	public function test_other_manager_is_forbidden(): void {
		$actor  = $this->make_user_with_cap( 'other_mgr', Capabilities::MANAGE_OWN_SPACE );
		$target = $this->make_user_with_cap( 'other_tgt' );
		$space_id = $this->create_test_space( $actor ); // actor ist Owner dieses Space

		// Zweiter Space, dessen Owner ein anderer Benutzer ist.
		$other_owner = $this->make_user_with_cap( 'other_owner' );
		$other_space = $this->create_test_space( $other_owner );

		// actor (nur Manager von $space_id) versucht, $other_space zu ändern.
		wp_set_current_user( $actor );
		$request = new \WP_REST_Request( 'DELETE', '/afspaces/v1/spaces/' . $other_space . '/members/' . $target );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Fremder Manager muss 403 erhalten.' );
	}

	/**
	 * Test: Nicht existierende user_id wird vom Service abgewiesen.
	 */
	public function test_invalid_user_id_rejected(): void {
		$actor    = $this->make_user_with_cap( 'inv_mgr', Capabilities::MANAGE_ALL_SPACES );
		$space_id = $this->create_test_space( $actor );

		wp_set_current_user( $actor );
		// Eine nicht existierende Benutzer-ID (absint('not-a-number') = 0).
		$request = new \WP_REST_Request( 'POST', '/afspaces/v1/spaces/' . $space_id . '/members' );
		$request->set_param( 'user_id', 0 );
		$response = rest_get_server()->dispatch( $request );

		// Der Service muss eine DomainException werfen → 400.
		$this->assertSame( 400, $response->get_status(), 'Nicht existierende user_id muss abgewiesen werden.' );
	}

	/**
	 * Test: Suche gibt keine E-Mail-Daten preis.
	 */
	public function test_search_exposes_no_email(): void {
		$actor = $this->make_user_with_cap( 'search_mgr', Capabilities::MANAGE_ALL_SPACES );
		wp_set_current_user( $actor );

		$request = new \WP_REST_Request( 'GET', '/afspaces/v1/users/search' );
		$request->set_param( 'search', 'a' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'members', $data );

		foreach ( $data['members'] as $member ) {
			$this->assertArrayNotHasKey( 'user_email', $member, 'E-Mail darf nicht preisgegeben werden.' );
			$this->assertArrayNotHasKey( 'email', $member, 'E-Mail darf nicht preisgegeben werden.' );
		}
	}

	/**
	 * Test: Berechtigter Manager kann Mitglied hinzufügen (Erfolgspfad).
	 */
	public function test_authorized_manager_can_add(): void {
		$actor    = $this->make_user_with_cap( 'ok_mgr', Capabilities::MANAGE_ALL_SPACES );
		$target   = $this->make_user_with_cap( 'ok_tgt' );
		$space_id = $this->create_test_space( $actor );

		wp_set_current_user( $actor );
		$request = new \WP_REST_Request( 'POST', '/afspaces/v1/spaces/' . $space_id . '/members' );
		$request->set_param( 'user_id', $target );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status(), 'Berechtigter Manager erhält 201.' );
		$this->assertTrue(
			\AsgarosForumUserGroups::isUserInUserGroup( $target, $this->group_id ),
			'Benutzer muss in der Gruppe sein.'
		);

		$this->cleanup_user_from_group( $target );
	}

	/**
	 * Test: Fremder Benutzer kann Einladung nicht annehmen.
	 */
	public function test_foreign_user_cannot_accept_invitation(): void {
		$actor      = $this->make_user_with_cap( 'inv_owner', Capabilities::MANAGE_ALL_SPACES );
		$invitee    = $this->make_user_with_cap( 'inv_target' );
		$other_user = $this->make_user_with_cap( 'inv_other' );
		$space_id   = $this->create_test_space( $actor );

		add_filter( 'pre_wp_mail', '__return_true' );
		$inv = $this->invitation_service->create_invitation( $space_id, $actor, $invitee, 'Hallo', 7 );
		$token = $this->invitation_service->build_token( $inv );
		remove_filter( 'pre_wp_mail', '__return_true' );

		wp_set_current_user( $other_user );
		$request = new \WP_REST_Request( 'POST', '/afspaces/v1/invitations/' . $token . '/accept' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status(), 'Fremder Benutzer darf Einladung nicht annehmen.' );
	}

	/**
	 * Test: Manager eines anderen Spaces darf Einladung nicht widerrufen.
	 */
	public function test_other_space_manager_cannot_revoke_invitation(): void {
		$uniq = uniqid( '', false );
		$owner_a    = $this->make_user_with_cap( 'inv_owner_a_' . $uniq );
		$owner_b    = $this->make_user_with_cap( 'inv_owner_b_' . $uniq );
		$invitee    = $this->make_user_with_cap( 'inv_target_b_' . $uniq );

		$space_a = $this->create_test_space( $owner_a );
		$space_b = $this->create_test_space( $owner_b );

		add_filter( 'pre_wp_mail', '__return_true' );
		$inv = $this->invitation_service->create_invitation( $space_b, $owner_b, $invitee, '', 7 );
		remove_filter( 'pre_wp_mail', '__return_true' );

		wp_set_current_user( $owner_a );
		$request = new \WP_REST_Request( 'DELETE', '/afspaces/v1/spaces/' . $space_b . '/invitations/' . $inv->id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status(), 'Manager eines anderen Spaces darf nicht widerrufen.' );
	}

	/**
	 * Test: Preview für erratenes Token bleibt anonym und generisch.
	 */
	public function test_invite_link_preview_for_unknown_token_is_generic(): void {
		$request = new \WP_REST_Request( 'GET', '/afspaces/v1/invite-links/preview/not-a-real-token' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Dieser Einladungslink ist ungültig oder nicht mehr verfugbar.', $data['message'] );
	}

	/**
	 * Test: Brute-Force-Drosselung greift für wiederholte Token-Prüfungen.
	 */
	public function test_invite_link_preview_is_rate_limited(): void {
		for ( $i = 0; $i < 15; $i++ ) {
			$request = new \WP_REST_Request( 'GET', '/afspaces/v1/invite-links/preview/not-a-real-token-2' );
			rest_get_server()->dispatch( $request );
		}

		$request = new \WP_REST_Request( 'GET', '/afspaces/v1/invite-links/preview/not-a-real-token-2' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 429, $response->get_status() );
	}

	/**
	 * Test: Berechtigter Manager erhält den Link nur einmal im Erstellungs-Response.
	 */
	public function test_create_invite_link_response_includes_url_but_list_does_not(): void {
		$actor    = $this->make_user_with_cap( 'link_mgr', Capabilities::MANAGE_ALL_SPACES );
		$space_id = $this->create_test_space( $actor );

		wp_set_current_user( $actor );
		$create = new \WP_REST_Request( 'POST', '/afspaces/v1/spaces/' . $space_id . '/invite-links' );
		$create->set_param( 'max_uses', 1 );
		$response = rest_get_server()->dispatch( $create );

		$this->assertSame( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'url', $data );

		$list = new \WP_REST_Request( 'GET', '/afspaces/v1/spaces/' . $space_id . '/invite-links' );
		$list_response = rest_get_server()->dispatch( $list );
		$list_data = $list_response->get_data();

		$this->assertSame( 200, $list_response->get_status() );
		$this->assertArrayHasKey( 'invite_links', $list_data );
		$this->assertArrayNotHasKey( 'url', $list_data['invite_links'][0] );
	}
}
