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

	public function test_asgaros_preset_uses_the_corporate_palette(): void {
		$settings = AppearanceSettingsPage::presets()['asgaros'];

		self::assertSame( '#2d5d7f', $settings['heading_color'] );
		self::assertSame( '#561188', $settings['purple_color'] );
		self::assertSame( '#2d5d7f', $settings['button_primary_bg'] );
		self::assertSame( '#3a4f66', $settings['text_color'] );
		self::assertSame( '#364149', $settings['button_secondary_bg'] );
		self::assertSame( '#ffffff', $settings['button_text_color'] );
		self::assertSame( '#ffffff', $settings['button_secondary_text_color'] );
		self::assertSame( '#f5ae35', $settings['button_hover_bg'] );
		self::assertSame( '#3a4f66', $settings['button_hover_text_color'] );
		self::assertSame( '#d9d9d9', $settings['wrapper_background'] );
	}

	public function test_frontend_css_declares_the_corporate_palette(): void {
		$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.css' );

		self::assertStringContainsString( '--afspaces-color-blue: #2d5d7f;', $css );
		self::assertStringContainsString( '--afspaces-color-yellow: #f5ae35;', $css );
		self::assertStringContainsString( '--afspaces-color-purple: #561188;', $css );
		self::assertStringContainsString( '--afspaces-color-text: #3a4f66;', $css );
		self::assertStringContainsString( '--afspaces-color-secondary-background: #364149;', $css );
		self::assertStringContainsString( '--afspaces-color-light-background: #d9d9d9;', $css );
	}

	public function test_inline_button_states_override_stored_appearance_values(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/AppearanceSettingsPage.php' );

		self::assertStringContainsString( '--afspaces-color-purple: %23$s;', $source );
		self::assertStringContainsString( '.afspaces-button:hover, #af-wrapper.afspaces-wrapper .afspaces-button:focus', $source );
		self::assertStringContainsString( 'background: %21$s !important;', $source );
		self::assertStringContainsString( '.afspaces-button-secondary { background: %17$s !important;', $source );
	}

	public function test_color_values_are_normalized_for_paste_and_copy(): void {
		$page = new AppearanceSettingsPage();
		$settings = $page->sanitize_options(
			array(
				'heading_color'  => '2d5d7f',
				'purple_color'   => '#abc',
				'button_hover_bg' => 'not-a-color',
			)
		);

		self::assertSame( '#2D5D7F', $settings['heading_color'] );
		self::assertSame( '#AABBCC', $settings['purple_color'] );
		self::assertSame( '#f5ae35', $settings['button_hover_bg'] );
	}

	public function test_settings_page_exposes_picker_and_hex_input_for_all_color_options(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/AppearanceSettingsPage.php' );
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces-admin.js' );

		self::assertStringContainsString( 'data-afspaces-color-picker', $source );
		self::assertStringContainsString( 'data-afspaces-hex-input', $source );
		self::assertStringContainsString( 'pattern="#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?"', $source );
		self::assertStringContainsString( 'normalizeHex', $script );
		self::assertStringContainsString( 'setCustomValidity', $script );
		self::assertStringContainsString( "'purple_color'", $source );
	}
}
