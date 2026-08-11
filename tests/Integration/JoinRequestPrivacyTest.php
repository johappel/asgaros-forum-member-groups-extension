<?php
/**
 * Integrationstest für die Join-Request-Privacy-Datenminimierung.
 *
 * @package AFSpaces\Tests\Integration
 */

declare( strict_types=1 );

namespace AFSpaces\Tests\Integration;

use AFSpaces\Domain\JoinRequest;

final class JoinRequestPrivacyTest extends IntegrationTestCase {

	/** @var int[] */
	private array $request_ids = array();

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->request_ids as $request_id ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}afspaces_join_requests WHERE id = %d", $request_id ) );
		}
		parent::tearDown();
	}

	public function test_eraser_clears_request_message_but_keeps_status_and_timestamps(): void {
		$request = $this->join_request_repository->create( $this->forum_id, 901001, 'Persönliche Begründung' );
		$this->request_ids[] = $request->id;

		$changed = $this->join_request_repository->erase_personal_messages_for_user( 901001 );
		$erased = $this->join_request_repository->get_by_id( $request->id );

		$this->assertGreaterThanOrEqual( 1, $changed );
		$this->assertInstanceOf( JoinRequest::class, $erased );
		$this->assertSame( '', $erased->request_message );
		$this->assertSame( JoinRequest::STATUS_PENDING, $erased->status );
		$this->assertNotSame( '', $erased->created_at );
	}
}
