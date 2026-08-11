<?php
/**
 * Unit-Tests für die zentrale AFSpaces-Settingsseite.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\AFSpacesSettingsPage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Interface/AFSpacesSettingsPage.php';

final class AFSpacesSettingsPageTest extends TestCase {

	public function test_central_page_slug_is_stable(): void {
		$this->assertSame( 'afspaces-settings', AFSpacesSettingsPage::PAGE_SLUG );
	}

	public function test_parent_menu_is_asgaros_forum_structure(): void {
		$reflection = new \ReflectionClass( AFSpacesSettingsPage::class );

		$this->assertSame( 'asgarosforum-structure', $reflection->getConstant( 'PARENT_SLUG' ) );
	}

	public function test_legacy_settings_slugs_map_to_the_expected_tabs(): void {
		$reflection = new \ReflectionClass( AFSpacesSettingsPage::class );
		$legacy_tabs = $reflection->getConstant( 'LEGACY_TABS' );

		$this->assertSame(
			array(
				'afspaces-appearance'    => 'appearance',
				'afspaces-look-and-feel' => 'appearance',
				'afspaces-creation'      => 'creation',
				'afspaces-search'        => 'search',
				'afspaces-installation'  => 'installation',
			),
			$legacy_tabs
		);
	}
}
