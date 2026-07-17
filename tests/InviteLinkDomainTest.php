<?php
/**
 * Unit-Tests für Einladungslinks.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Application\InviteLinkToken;
use AFSpaces\Core\DomainException;
use AFSpaces\Domain\InviteLink;
use PHPUnit\Framework\TestCase;

final class InviteLinkDomainTest extends TestCase {

	private function make_link( array $overrides = array() ): InviteLink {
		return new InviteLink(
			array_merge(
				array(
					'id'                 => 5,
					'space_id'           => 2,
					'creator_user_id'    => 9,
					'token_hash'         => 'hash',
					'status'             => InviteLink::STATUS_ACTIVE,
					'approval_mode'      => InviteLink::MODE_AUTO_JOIN,
					'max_uses'           => 3,
					'use_count'          => 0,
					'allow_registration' => false,
					'expires_at'         => '2099-01-01 00:00:00',
					'created_at'         => '2026-01-01 00:00:00',
				),
				$overrides
			)
		);
	}

	public function test_active_link_stays_active_before_expiry_and_limit(): void {
		$link = $this->make_link();

		$this->assertSame( InviteLink::STATUS_ACTIVE, $link->effective_status( '2026-01-02 00:00:00' ) );
	}

	public function test_expired_link_reports_expired_status(): void {
		$link = $this->make_link(
			array(
				'expires_at' => '2026-01-01 00:00:00',
			)
		);

		$this->assertSame( InviteLink::STATUS_EXPIRED, $link->effective_status( '2026-01-02 00:00:00' ) );
	}

	public function test_exhausted_link_reports_exhausted_status(): void {
		$link = $this->make_link(
			array(
				'max_uses'  => 2,
				'use_count' => 2,
			)
		);

		$this->assertSame( InviteLink::STATUS_EXHAUSTED, $link->effective_status( '2026-01-02 00:00:00' ) );
	}

	public function test_increment_use_updates_counter_until_limit(): void {
		$link = $this->make_link(
			array(
				'max_uses'  => 2,
				'use_count' => 1,
			)
		);

		$link->increment_use( '2026-01-02 12:00:00' );

		$this->assertSame( 2, $link->use_count );
		$this->assertSame( InviteLink::STATUS_EXHAUSTED, $link->effective_status( '2026-01-02 12:00:01' ) );
	}

	public function test_increment_use_rejects_exhausted_link(): void {
		$this->expectException( DomainException::class );

		$link = $this->make_link(
			array(
				'max_uses'  => 1,
				'use_count' => 1,
			)
		);

		$link->increment_use( '2026-01-02 12:00:00' );
	}

	public function test_token_hash_matches_only_original_token(): void {
		$tokens = new InviteLinkToken();
		$plain  = 'example-token-value';
		$hash   = $tokens->hash( $plain );

		$this->assertTrue( $tokens->matches( $plain, $hash ) );
		$this->assertFalse( $tokens->matches( 'different-token', $hash ) );
	}

	public function test_generated_token_is_non_empty_and_url_safe(): void {
		$tokens = new InviteLinkToken();
		$plain  = $tokens->generate();

		$this->assertNotSame( '', $plain );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9\-_]+$/', $plain );
	}
}