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

	public function test_settings_use_visible_palette_choices_instead_of_free_color_input(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/WorkingGroupSettingsView.php' );
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.css' );
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.js' );

		self::assertStringContainsString( 'WorkingGroupService::accent_color_options()', $source );
		self::assertStringContainsString( 'class="afspaces-accent-fieldset"', $source );
		self::assertStringContainsString( 'class="afspaces-accent-option', $source );
		self::assertStringContainsString( 'type="radio" name="accent_color"', $source );
		self::assertStringContainsString( 'class="afspaces-accent-swatch"', $source );
		self::assertStringContainsString( 'strtoupper( $color )', $source );
		self::assertStringNotContainsString( '<select id="afspaces-accent-color"', $source );
		self::assertStringContainsString( '.afspaces-accent-option.is-selected', $css );
		self::assertStringContainsString( "classList.toggle('is-selected'", $script );
	}
}
