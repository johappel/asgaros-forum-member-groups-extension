<?php
/**
 * Tests für die Corporate-Design-Farbwahl der Arbeitsgruppen.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use PHPUnit\Framework\TestCase;

final class WorkingGroupColorPolicyTest extends TestCase {

	public function test_settings_use_palette_select_instead_of_free_color_input(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/WorkingGroupSettingsView.php' );

		self::assertStringContainsString( 'WorkingGroupService::accent_color_options()', $source );
		self::assertStringContainsString( '<select id="afspaces-accent-color"', $source );
		self::assertStringNotContainsString( 'type="color" id="afspaces-accent-color"', $source );
	}
}
