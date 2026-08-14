<?php
/**
 * Tests für die Standardfarbe der AFSpaces-Überschriften.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\AppearanceSettingsPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Interface/AppearanceSettingsPage.php';

final class AppearanceHeadingColorTest extends TestCase {

	public function test_default_heading_color_is_corporate_blue(): void {
		$settings = AppearanceSettingsPage::defaults();

		self::assertSame( '#2d5d7f', $settings['heading_color'] );
	}

	public function test_dashboard_heading_rule_uses_corporate_blue(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.css' );

		self::assertStringContainsString( 'color: #2d5d7f;', $css );
	}
}
