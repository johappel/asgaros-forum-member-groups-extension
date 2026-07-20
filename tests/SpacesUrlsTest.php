<?php
/**
 * Unit-Tests für die Hub-URL- und View-Logik.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\SpacesUrls;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Interface/SpacesUrls.php';

/**
 * Prüft Whitelist, Normalisierung und Legacy-Zuordnung ohne WordPress-Kern.
 */
final class SpacesUrlsTest extends TestCase {

	public function test_views_contains_expected_entries(): void {
		$views = SpacesUrls::views();
		$this->assertContains( SpacesUrls::VIEW_DASHBOARD, $views );
		$this->assertContains( SpacesUrls::VIEW_MEMBERS, $views );
		$this->assertContains( SpacesUrls::VIEW_INVITATIONS, $views );
		$this->assertContains( SpacesUrls::VIEW_MY_INVITATIONS, $views );
		$this->assertContains( SpacesUrls::VIEW_CREATE, $views );
	}

	public function test_normalize_view_accepts_valid_views(): void {
		$this->assertSame( SpacesUrls::VIEW_MEMBERS, SpacesUrls::normalize_view( 'members' ) );
		$this->assertSame( SpacesUrls::VIEW_MY_INVITATIONS, SpacesUrls::normalize_view( 'my-invitations' ) );
		$this->assertSame( SpacesUrls::VIEW_CREATE, SpacesUrls::normalize_view( 'create' ) );
	}

	public function test_normalize_view_falls_back_to_dashboard(): void {
		$this->assertSame( SpacesUrls::VIEW_DASHBOARD, SpacesUrls::normalize_view( '' ) );
		$this->assertSame( SpacesUrls::VIEW_DASHBOARD, SpacesUrls::normalize_view( 'unknown' ) );
		$this->assertSame( SpacesUrls::VIEW_DASHBOARD, SpacesUrls::normalize_view( 123 ) );
		$this->assertSame( SpacesUrls::VIEW_DASHBOARD, SpacesUrls::normalize_view( '<script>' ) );
	}

	public function test_legacy_slug_map_covers_all_old_pages(): void {
		$map = SpacesUrls::legacy_slug_map();
		$this->assertSame( SpacesUrls::VIEW_DASHBOARD, $map['afspaces-dashboard'] );
		$this->assertSame( SpacesUrls::VIEW_MEMBERS, $map['afspaces-members'] );
		$this->assertSame( SpacesUrls::VIEW_INVITATIONS, $map['afspaces-invitations'] );
		$this->assertSame( SpacesUrls::VIEW_MY_INVITATIONS, $map['afspaces-my-invitations'] );
	}
}
