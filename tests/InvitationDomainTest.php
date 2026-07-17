<?php
/**
 * Unit-Tests für Zustandswechsel des Invitation-Domainmodells.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Core\DomainException;
use AFSpaces\Domain\Invitation;
use PHPUnit\Framework\TestCase;

final class InvitationDomainTest extends TestCase {

	private function make_pending(): Invitation {
		return new Invitation(
			array(
				'id'              => 1,
				'space_id'        => 5,
				'inviter_user_id' => 10,
				'invitee_user_id' => 20,
				'status'          => Invitation::STATUS_PENDING,
				'expires_at'      => '2099-01-01 00:00:00',
				'created_at'      => '2026-01-01 00:00:00',
			)
		);
	}

	public function test_pending_can_be_accepted(): void {
		$inv = $this->make_pending();
		$inv->accept( '2026-01-02 12:00:00' );

		$this->assertSame( Invitation::STATUS_ACCEPTED, $inv->status );
		$this->assertSame( '2026-01-02 12:00:00', $inv->accepted_at );
	}

	public function test_pending_can_be_declined(): void {
		$inv = $this->make_pending();
		$inv->decline( '2026-01-03 12:00:00' );

		$this->assertSame( Invitation::STATUS_DECLINED, $inv->status );
		$this->assertSame( '2026-01-03 12:00:00', $inv->declined_at );
	}

	public function test_pending_can_be_revoked(): void {
		$inv = $this->make_pending();
		$inv->revoke( '2026-01-04 12:00:00' );

		$this->assertSame( Invitation::STATUS_REVOKED, $inv->status );
		$this->assertSame( '2026-01-04 12:00:00', $inv->revoked_at );
	}

	public function test_expired_pending_reports_effective_expired_status(): void {
		$inv = new Invitation(
			array(
				'id'              => 2,
				'space_id'        => 5,
				'inviter_user_id' => 10,
				'invitee_user_id' => 20,
				'status'          => Invitation::STATUS_PENDING,
				'expires_at'      => '2025-01-01 00:00:00',
			)
		);

		$this->assertSame( Invitation::STATUS_EXPIRED, $inv->effective_status( '2026-01-01 00:00:00' ) );
	}

	public function test_accepted_cannot_be_accepted_again_from_invalid_state(): void {
		$this->expectException( DomainException::class );

		$inv = new Invitation(
			array(
				'id'         => 3,
				'space_id'   => 5,
				'status'     => Invitation::STATUS_DECLINED,
				'expires_at' => '2099-01-01 00:00:00',
			)
		);

		$inv->accept( '2026-01-05 00:00:00' );
	}
}
