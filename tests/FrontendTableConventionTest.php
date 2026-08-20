<?php
/**
 * Regressionstests für die gemeinsame AFSpaces-Frontendtabellen-Konvention.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Adapters\Asgaros\AsgarosAdapter;
use PHPUnit\Framework\TestCase;

final class FrontendTableConventionTest extends TestCase {

	/**
	 * Alle produktiven Frontendtabellen müssen die gemeinsame Darstellung nutzen.
	 */
	public function test_all_frontend_tables_use_the_shared_markup(): void {
		$root = dirname( __DIR__ );
		$views = array(
			'src/Interface/MembersView.php',
			'src/Interface/InvitationsView.php',
			'src/Interface/JoinRequestsView.php',
			'src/Interface/FrontendController.php',
			'src/Interface/ModerationView.php',
		);

		foreach ( $views as $view ) {
			$source = (string) file_get_contents( $root . '/' . $view );
			self::assertStringContainsString( 'class="afspaces-table afspaces-table--responsive', $source, $view );
			self::assertStringContainsString( 'class="afspaces-table-wrap"', $source, $view );
		}

		$styles = (string) file_get_contents( $root . '/assets/afspaces.css' );
		self::assertStringContainsString( '.afspaces-table__actions', $styles );
		self::assertStringContainsString( '.afspaces-badge', $styles );
		self::assertStringContainsString( '.afspaces-table-wrap', $styles );
	}

	public function test_moderation_view_has_state_specific_actions_and_conditional_forum_management(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ModerationView.php' );

		self::assertStringContainsString( "__( 'Thema schließen', 'afspaces' )", $source );
		self::assertStringContainsString( "__( 'Wieder öffnen', 'afspaces' )", $source );
		self::assertStringContainsString( "__( 'Im Forum moderieren', 'afspaces' )", $source );
		self::assertStringContainsString( 'empty( $additional_forums ) && ! $can_create_forum', $source );
		self::assertStringContainsString( 'afspaces-forum-management-table', $source );
		self::assertStringNotContainsString( 'Im Forum öffnen', $source );
	}

	public function test_invite_link_table_avoids_wrapping_and_omits_usage_column(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/InvitationsView.php' );
		$styles = (string) file_get_contents( dirname( __DIR__ ) . '/assets/afspaces.css' );

		self::assertStringContainsString( 'afspaces-invite-links-table', $source );
		self::assertStringNotContainsString( "__( 'Nutzungen', 'afspaces' )", $source );
		self::assertStringContainsString( '.afspaces-invite-links-table th', $styles );
		self::assertStringContainsString( 'white-space: nowrap;', $styles );
		self::assertStringContainsString( 'flex-direction: column;', $styles );
	}

	public function test_legacy_escaped_quotes_are_normalized_without_dropping_normal_backslashes(): void {
		$adapter = (new \ReflectionClass( AsgarosAdapter::class ))->newInstanceWithoutConstructor();
		$method  = new \ReflectionMethod( AsgarosAdapter::class, 'normalize_topic_title' );
		$method->setAccessible( true );

		$normalized = $method->invoke( $adapter, 'Die Angst vor der \\"Vorstellungsrunde\\" und C:\\Temp' );

		self::assertSame( 'Die Angst vor der "Vorstellungsrunde" und C:\\Temp', $normalized );
		self::assertStringContainsString( 'esc_html( (string) $topic[\'name\'] )', (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ModerationView.php' ) );
	}
}
