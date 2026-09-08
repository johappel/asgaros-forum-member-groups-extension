<?php
/**
 * Unit-Tests für das Toolbox-Link-Domainmodell.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Domain\SpaceLink;
use PHPUnit\Framework\TestCase;

final class SpaceLinkDomainTest extends TestCase {

	public function test_sanitize_url_accepts_http_and_https(): void {
		$this->assertSame( 'https://example.test/pfad', SpaceLink::sanitize_url( 'https://example.test/pfad' ) );
		$this->assertSame( 'http://example.test', SpaceLink::sanitize_url( 'http://example.test' ) );
	}

	public function test_sanitize_url_allows_site_relative_paths(): void {
		$this->assertSame( '/forum/thema', SpaceLink::sanitize_url( '/forum/thema' ) );
	}

	public function test_sanitize_url_rejects_dangerous_schemes(): void {
		$this->assertSame( '', SpaceLink::sanitize_url( 'javascript:alert(1)' ) );
		$this->assertSame( '', SpaceLink::sanitize_url( 'data:text/html;base64,PHN2Zz4=' ) );
		$this->assertSame( '', SpaceLink::sanitize_url( 'ftp://example.test/datei' ) );
	}

	public function test_sanitize_url_rejects_protocol_relative(): void {
		// Schemalose //host-URLs sind mehrdeutig und werden verworfen.
		$this->assertSame( '', SpaceLink::sanitize_url( '//evil.test' ) );
	}

	public function test_sanitize_url_rejects_empty_and_missing_host(): void {
		$this->assertSame( '', SpaceLink::sanitize_url( '' ) );
		$this->assertSame( '', SpaceLink::sanitize_url( '   ' ) );
		$this->assertSame( '', SpaceLink::sanitize_url( 'https://' ) );
	}

	public function test_sanitize_title_trims_and_collapses_whitespace(): void {
		$this->assertSame( 'Titel mit Raum', SpaceLink::sanitize_title( "  Titel   mit\n\tRaum  " ) );
		$this->assertSame( '', SpaceLink::sanitize_title( '   ' ) );
	}

	public function test_sanitize_icon_only_allows_known_keys(): void {
		$this->assertSame( 'calendar', SpaceLink::sanitize_icon( 'calendar' ) );
		$this->assertSame( 'calendar', SpaceLink::sanitize_icon( 'CALENDAR' ) );
		$this->assertSame( '', SpaceLink::sanitize_icon( 'unbekannt' ) );
	}

	public function test_icon_class_maps_known_keys_and_empty_default(): void {
		$this->assertSame( 'fas fa-calendar-alt', SpaceLink::icon_class( 'calendar' ) );
		$this->assertSame( '', SpaceLink::icon_class( '' ) );
		$this->assertSame( '', SpaceLink::icon_class( 'unbekannt' ) );
	}
}
