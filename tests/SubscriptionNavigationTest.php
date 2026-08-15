<?php
/**
 * Statische VertragsprÃ¼fung fÃ¼r die Verlagerung der Asgaros-Abonnement-Controls.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StubSubscriptionNotifications {
	public string $html = '';

	public function show_subscription_navigation( string $view ): void {
		echo $this->html;
	}
}

final class SubscriptionNavigationTest extends TestCase {

	protected function tearDown(): void {
		global $asgarosforum, $afspaces_test_is_user_logged_in;
		unset( $asgarosforum, $afspaces_test_is_user_logged_in );
		parent::tearDown();
	}

	public function test_forum_action_is_inserted_before_subscription_management(): void {
		$notifications = new StubSubscriptionNotifications();
		$notifications->html = '<span id="forum-subscription"></span><a href="https://example.test/forum/?subscribe_forum=42&amp;_wpnonce=abc"><b>Subscribe</b></a>';

		global $asgarosforum;
		$asgarosforum = (object) array(
			'notifications' => $notifications,
			'options'       => array( 'allow_subscriptions' => true ),
			'current_view'  => 'forum',
		);

		$adapter = ( new ReflectionClass( AsgarosAdapter::class ) )->newInstanceWithoutConstructor();
		$result  = $adapter->add_subscription_menu_entry(
			array(
				'home'         => array(),
				'subscription' => array(),
				'afspaces'     => array(),
			)
		);

		self::assertSame( array( 'home', 'afspaces_context_subscription', 'subscription', 'afspaces' ), array_keys( $result ) );
		self::assertSame( 'Forum abonnieren', $result['afspaces_context_subscription']['menu_link_text'] );
		self::assertSame( 'https://example.test/forum/?subscribe_forum=42&_wpnonce=abc', $result['afspaces_context_subscription']['menu_url'] );
	}

	public function test_topic_unsubscribe_action_uses_clear_label(): void {
		$notifications = new StubSubscriptionNotifications();
		$notifications->html = '<span id="topic-subscription"></span><a href="https://example.test/topic/?unsubscribe_topic=7&amp;_wpnonce=abc"><b>Unsubscribe</b></a>';

		global $asgarosforum;
		$asgarosforum = (object) array(
			'notifications' => $notifications,
			'options'       => array( 'allow_subscriptions' => true ),
			'current_view'  => 'topic',
		);

		$adapter = ( new ReflectionClass( AsgarosAdapter::class ) )->newInstanceWithoutConstructor();
		$result  = $adapter->add_subscription_menu_entry( array( 'subscription' => array() ) );

		self::assertSame( 'Themen-Abo beenden', $result['afspaces_context_subscription']['menu_link_text'] );
	}

	public function test_global_subscription_does_not_duplicate_management_link(): void {
		$notifications = new StubSubscriptionNotifications();
		$notifications->html = '<span id="forum-subscription"></span><a href="https://example.test/subscriptions/">All forums subscribed</a>';

		global $asgarosforum;
		$asgarosforum = (object) array(
			'notifications' => $notifications,
			'options'       => array( 'allow_subscriptions' => true ),
			'current_view'  => 'forum',
		);

		$adapter = ( new ReflectionClass( AsgarosAdapter::class ) )->newInstanceWithoutConstructor();
		$menu    = array( 'home' => array(), 'subscription' => array() );

		self::assertSame( $menu, $adapter->add_subscription_menu_entry( $menu ) );
	}

	public function test_subscription_control_is_relocated_without_duplicate_output(): void {
		$adapter_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Adapters/Asgaros/AsgarosAdapter.php' );
		$navigation_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ForumNavigation.php' );

		self::assertStringContainsString( "remove_action(\n\t\t\t\t'asgarosforum_bottom_navigation'", $adapter_source );
		self::assertStringContainsString( "'asgarosforum_filter_header_menu'", $adapter_source );
		self::assertStringContainsString( "'afspaces_context_subscription'", $adapter_source );
		self::assertStringContainsString( "'subscription' === (string) \$key", $adapter_source );
		self::assertStringContainsString( 'show_subscription_navigation( $view )', $adapter_source );
		self::assertStringNotContainsString( 'afspaces-subscription-action', $adapter_source );
		self::assertStringNotContainsString( 'asgarosforum_forum_custom_content_top', $adapter_source );
		self::assertStringNotContainsString( 'asgarosforum_topic_custom_content_top', $adapter_source );
		self::assertStringContainsString( 'relocate_subscription_navigation()', $navigation_source );
	}
}
