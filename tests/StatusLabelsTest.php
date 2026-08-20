<?php
/**
 * Unit-Tests für deutsche Statusbezeichnungen der Frontend-Oberfläche.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\StatusLabels;
use PHPUnit\Framework\TestCase;

final class StatusLabelsTest extends TestCase {

	public function test_join_request_statuses_are_translated(): void {
		self::assertSame( 'Offen', StatusLabels::join_request( 'pending' ) );
		self::assertSame( 'Genehmigt', StatusLabels::join_request( 'approved' ) );
		self::assertSame( 'Abgelehnt', StatusLabels::join_request( 'rejected' ) );
	}

	public function test_invitation_statuses_are_translated(): void {
		self::assertSame( 'Ausstehend', StatusLabels::invitation( 'pending' ) );
		self::assertSame( 'Angenommen', StatusLabels::invitation( 'accepted' ) );
		self::assertSame( 'Abgelehnt', StatusLabels::invitation( 'declined' ) );
		self::assertSame( 'Widerrufen', StatusLabels::invitation( 'revoked' ) );
		self::assertSame( 'Abgelaufen', StatusLabels::invitation( 'expired' ) );
	}

	public function test_invite_link_and_space_statuses_are_translated(): void {
		self::assertSame( 'Aktiv', StatusLabels::invite_link( 'active' ) );
		self::assertSame( 'Aufgebraucht', StatusLabels::invite_link( 'exhausted' ) );
		self::assertSame( 'Wartet auf Freigabe', StatusLabels::space( 'pending' ) );
		self::assertSame( 'Archiviert', StatusLabels::space( 'archived' ) );
	}

	public function test_unknown_status_remains_visible_for_diagnosis(): void {
		self::assertSame( 'future_status', StatusLabels::invitation( 'future_status' ) );
	}
}
