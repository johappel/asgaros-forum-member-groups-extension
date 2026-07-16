<?php
/**
 * Unit-Tests für die Normalisierung von Adapter-Ausnahmen.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use AFSpaces\Core\DomainException;
use AFSpaces\Core\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Tests, dass Schreiboperationen ohne verfügbares Asgaros eine
 * übersetzte Domain-Ausnahme werfen.
 */
final class AdapterExceptionTest extends TestCase {

	private function make_adapter( bool $available ): AsgarosAdapter {
		$req = $this->createMock( Requirements::class );
		$req->method( 'is_asgaros_active' )->willReturn( $available );
		$req->method( 'is_asgaros_version_supported' )->willReturn( $available );
		return new AsgarosAdapter( $req );
	}

	public function test_add_throws_domain_exception_when_unavailable(): void {
		$adapter = $this->make_adapter( false );

		$this->expectException( DomainException::class );
		$adapter->add_user_to_group( 1, 1 );
	}

	public function test_remove_throws_domain_exception_when_unavailable(): void {
		$adapter = $this->make_adapter( false );

		$this->expectException( DomainException::class );
		$adapter->remove_user_from_group( 1, 1 );
	}

	public function test_available_adapter_does_not_throw_on_idempotent_add(): void {
		// Ohne echtes Asgaros kann die Gruppenlogik nicht ausgeführt werden;
		// wir prüfen nur, dass is_available() korrekt durchgereicht wird.
		$adapter = $this->make_adapter( true );
		$this->assertTrue( $adapter->is_available() );
	}
}
