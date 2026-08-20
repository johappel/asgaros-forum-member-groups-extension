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
	public string $last_method = '';

	public function show_subscription_navigation( string $view ): void {
		$this->last_method = 'show_subscription_navigation';
		echo $this->html;
	}

	public function show_forum_subscription_link( int $forum_id ): void {
		$this->last_method = 'show_forum_subscription_link';
		echo $this->html;
	}

	public function show_topic_subscription_link( int $topic_id ): void {
		$this->last_method = 'show_topic_subscription_link';
		echo $this->html;
	}
}

final class SubscriptionNavigationTest extends TestCase {

	protected function tearDown(): void {
		global $asgarosforum, $afspaces_test_is_user_logged_in;
		unset( $asgarosforum, $afspaces_test_is_user_logged_in );
		parent::tearDown();
	}

	public function test_forum_action_is_inserted_into_existing_forum_menu(): void {
		$notifications = new StubSubscriptionNotifications();
		$notifications->html = '<span id="forum-subscription"></span><a class="button button-normal" href="https://example.test/forum/?subscribe_forum=42&amp;_wpnonce=abc"><b>Subscribe</b></a>';

		global $asgarosforum;
		$asgarosforum = (object) array(
			'notifications' => $notifications,
			'options'       => array( 'allow_subscriptions' => true ),
			'current_view'  => 'forum',
			'current_element' => 42,
		);

		$adapter = ( new ReflectionClass( AsgarosAdapter::class ) )->newInstanceWithoutConstructor();
		$result = $adapter->add_forum_subscription_menu_entry(
			'<div class="forum-menu"><a class="button button-normal" href="/forum/addtopic/42">New Topic</a></div>'
		);

		self::assertStringContainsString( 'New Topic</a><a class="button button-normal afspaces-subscription-link"', $result );
		self::assertStringContainsString( 'href="https://example.test/forum/?subscribe_forum=42&amp;_wpnonce=abc"', $result );
		self::assertStringContainsString( '>Forum abonnieren</a>', $result );
		self::assertSame( 'show_forum_subscription_link', $notifications->last_method );
	}

	public function test_topic_unsubscribe_action_uses_clear_label(): void {
		$notifications = new StubSubscriptionNotifications();
		$notifications->html = '<span id="topic-subscription"></span><a class="button button-normal" href="https://example.test/topic/?unsubscribe_topic=7&amp;_wpnonce=abc"><b>Unsubscribe</b></a>';

		global $asgarosforum;
		$asgarosforum = (object) array(
			'notifications' => $notifications,
			'options'       => array( 'allow_subscriptions' => true ),
			'current_view'  => 'topic',
			'current_element' => 7,
		);

		$adapter = ( new ReflectionClass( AsgarosAdapter::class ) )->newInstanceWithoutConstructor();
		$result = $adapter->add_topic_subscription_menu_entry(
			'<div class="forum-menu"><a class="button button-normal" href="/forum/addpost/7">Antworten</a></div>'
		);

		self::assertStringContainsString( 'Antworten</a><a class="button button-normal afspaces-subscription-link"', $result );
		self::assertStringContainsString( 'href="https://example.test/topic/?unsubscribe_topic=7&amp;_wpnonce=abc"', $result );
		self::assertStringContainsString( '>Themen-Abo beenden</a>', $result );
		self::assertSame( 'show_topic_subscription_link', $notifications->last_method );
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
		$menu = '<div class="forum-menu"><a href="/forum/addtopic/42">New Topic</a></div>';

		self::assertSame( $menu, $adapter->add_forum_subscription_menu_entry( $menu ) );
	}

	public function test_subscription_control_is_relocated_without_duplicate_output(): void {
		$adapter_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Adapters/Asgaros/AsgarosAdapter.php' );
		$navigation_source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ForumNavigation.php' );

		self::assertStringContainsString( "remove_action(\n\t\t\t\t'asgarosforum_bottom_navigation'", $adapter_source );
		self::assertStringContainsString( "'asgarosforum_filter_forum_menu'", $adapter_source );
		self::assertStringContainsString( "'asgarosforum_filter_topic_menu'", $adapter_source );
		self::assertStringContainsString( '`.forum-menu`', $adapter_source );
		self::assertStringContainsString( 'show_subscription_navigation( $view )', $adapter_source );
		self::assertStringNotContainsString( "'asgarosforum_filter_header_menu'", $adapter_source );
		self::assertStringNotContainsString( 'afspaces_context_subscription', $adapter_source );
		self::assertStringContainsString( 'relocate_subscription_navigation()', $navigation_source );
	}
}
