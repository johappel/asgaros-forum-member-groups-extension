<?php
/**
 * Integrationstests fuer Arbeitsgruppen-Metadaten und Benachrichtigungen.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Application\WorkingGroupService;

final class WorkingGroupMetaFlowTest extends IntegrationTestCase {

	private function make_user( string $suffix ): int {
		$login = 'afspaces_wg_' . $suffix;
		$user  = get_user_by( 'login', $login );
		if ( $user ) {
			return (int) $user->ID;
		}

		$user_id = wp_create_user( $login, 'password', $suffix . '@example.com' );
		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	public function test_working_group_meta_is_saved_and_loaded(): void {
		$owner = $this->make_user( 'meta_owner_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );
		$service = new WorkingGroupService( $this->spaces, $this->space_meta_repository, $this->asgaros, $this->policy, $this->audit );

		$meta = $service->save_metadata(
			$space_id,
			$owner,
			array(
				'description' => 'Arbeitsgruppe fuer regionale Kooperation',
				'accent_color' => '#114488',
				'icon' => 'briefcase',
				'contact_text' => 'Kontakt ueber das Team Nord',
				'directory_visibility' => 'listed',
				'join_policy' => 'invite_only',
				'join_requests_enabled' => false,
			)
		);

		$this->assertSame( 'Arbeitsgruppe fuer regionale Kooperation', $meta->description );
		$stored = $this->space_meta_repository->get_for_space( $space_id );
		$this->assertSame( '#114488', $stored->accent_color );
		$this->assertSame( 'briefcase', $stored->icon );
		$this->assertSame( 'Kontakt ueber das Team Nord', $stored->contact_text );
		$this->assertSame( 'invite_only', $stored->join_policy );
		$this->assertFalse( $stored->join_requests_enabled );
	}

	public function test_join_request_notifies_responsibles_and_central_address(): void {
		$owner = $this->make_user( 'notify_owner_' . uniqid( '', false ) );
		$requester = $this->make_user( 'notify_requester_' . uniqid( '', false ) );
		$space_id = $this->create_test_space( $owner );

		$mails = array();
		$collector = static function ( $return, $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};

		update_option( 'afspaces_central_notification_email', 'zentrale@example.com' );
		add_filter( 'pre_wp_mail', $collector, 10, 2 );
		$this->join_request_service->create_request( $space_id, $requester, 'Bitte aufnehmen' );
		remove_filter( 'pre_wp_mail', $collector, 10 );
		delete_option( 'afspaces_central_notification_email' );

		$this->assertCount( 2, $mails );
		$owner_user = get_userdata( $owner );
		$recipients = array_map( static fn( array $atts ): string => (string) $atts['to'], $mails );
		sort( $recipients );
		$this->assertSame( array( (string) $owner_user->user_email, 'zentrale@example.com' ), $recipients );

		$entries = $this->audit->list_for_space( $space_id, 20 );
		$actions = array_column( $entries, 'action' );
		$this->assertContains( 'join_request_manager_notified', $actions );
		$this->assertContains( 'join_request_central_notified', $actions );
	}
}