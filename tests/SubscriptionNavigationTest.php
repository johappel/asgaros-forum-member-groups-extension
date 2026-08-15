<?php
/**
 * Statische VertragsprÃ¼fung fÃ¼r die Verlagerung der Asgaros-Abonnement-Controls.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use PHPUnit\Framework\TestCase;

final class SubscriptionNavigationTest extends TestCase {

	public function test_subscription_control_is_relocated_without_duplicate_output(): void {
		$adapter_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Adapters/Asgaros/AsgarosAdapter.php' );
		$navigation_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ForumNavigation.php' );

		self::assertStringContainsString( "remove_action(\n\t\t\t\t'asgarosforum_bottom_navigation'", $adapter_source );
		self::assertStringContainsString( "'asgarosforum_forum_custom_content_top'", $adapter_source );
		self::assertStringContainsString( "'asgarosforum_topic_custom_content_top'", $adapter_source );
		self::assertStringContainsString( 'show_subscription_navigation( $view )', $adapter_source );
		self::assertStringContainsString( 'relocate_subscription_navigation()', $navigation_source );
	}
}
