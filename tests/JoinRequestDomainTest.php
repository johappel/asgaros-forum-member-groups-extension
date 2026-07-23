<?php
/**
 * Unit-Tests fuer Zustandswechsel des JoinRequest-Domainmodells.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Core\DomainException;
use AFSpaces\Domain\JoinRequest;
use PHPUnit\Framework\TestCase;

final class JoinRequestDomainTest extends TestCase {

	private function make_pending(): JoinRequest {
		return new JoinRequest(
			array(
				'id'                => 1,
				'space_id'          => 9,
				'requester_user_id' => 20,
				'request_message'   => 'Bitte aufnehmen',
				'status'            => JoinRequest::STATUS_PENDING,
			)
		);
	}

	public function test_pending_can_be_approved(): void {
		$request = $this->make_pending();
		$request->approve( 77, 'Willkommen', '2026-07-22 10:00:00' );

		$this->assertSame( JoinRequest::STATUS_APPROVED, $request->status );
		$this->assertSame( 77, $request->decider_user_id );
		$this->assertSame( 'Willkommen', $request->decision_message );
		$this->assertSame( '2026-07-22 10:00:00', $request->approved_at );
	}

	public function test_pending_can_be_rejected(): void {
		$request = $this->make_pending();
		$request->reject( 88, 'Aktuell voll', '2026-07-22 11:00:00' );

		$this->assertSame( JoinRequest::STATUS_REJECTED, $request->status );
		$this->assertSame( 88, $request->decider_user_id );
		$this->assertSame( 'Aktuell voll', $request->decision_message );
		$this->assertSame( '2026-07-22 11:00:00', $request->rejected_at );
	}

	public function test_non_pending_cannot_be_approved(): void {
		$this->expectException( DomainException::class );

		$request = new JoinRequest(
			array(
				'id'                => 2,
				'space_id'          => 9,
				'requester_user_id' => 20,
				'status'            => JoinRequest::STATUS_REJECTED,
			)
		);

		$request->approve( 1, '', '2026-07-22 12:00:00' );
	}
}
