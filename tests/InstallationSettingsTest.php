<?php
/**
 * Unit-Tests für die sichere Deinstallations-Einstellung.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\InstallationSettingsPage;
use AFSpaces\Core\ForumManagementSettings;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Interface/InstallationSettingsPage.php';

final class InstallationSettingsTest extends TestCase {

	public function test_cleanup_is_disabled_by_default_when_option_is_missing(): void {
		$this->assertFalse( (bool) get_option( InstallationSettingsPage::OPTION, false ) );
	}

	public function test_sanitize_option_is_strictly_boolean(): void {
		$page = new InstallationSettingsPage();

		$this->assertFalse( $page->sanitize_option( false ) );
		$this->assertFalse( $page->sanitize_option( '0' ) );
		$this->assertTrue( $page->sanitize_option( '1' ) );
		$this->assertFalse( $page->sanitize_option( 'false' ) );
	}

	public function test_forum_creation_option_has_false_default_and_stable_name(): void {
		$this->assertSame( 'afspaces_group_managers_can_create_forums', ForumManagementSettings::OPTION );
		$this->assertFalse( (bool) get_option( ForumManagementSettings::OPTION, ForumManagementSettings::DEFAULT ) );
	}
}
