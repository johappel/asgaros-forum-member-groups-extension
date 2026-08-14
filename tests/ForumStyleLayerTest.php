<?php
/**
 * Tests für den Asgaros-Forum-Override-Layer.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Interface\ForumStyleLayer;
use PHPUnit\Framework\TestCase;

final class ForumStyleLayerTest extends TestCase {

	public function test_override_css_is_scoped_and_contains_the_initial_forum_rules(): void {
		$css_path = dirname( __DIR__ ) . '/assets/afspaces-forum-overrides.css';
		self::assertFileExists( $css_path );

		$css = (string) file_get_contents( $css_path );
		self::assertStringContainsString( '#af-wrapper .button-normal:hover', $css );
		self::assertStringContainsString( '#f5ae35', $css );
		self::assertStringContainsString( 'background-color: var(--afspaces-forum-button-hover) !important;', $css );

		foreach ( array( '.forum-post-date', '.post-author-block-meta', '.post-author-block-group', '.post-meta', '.post-edit-date' ) as $selector ) {
			self::assertStringContainsString( '#af-wrapper ' . $selector, $css );
		}

		self::assertStringNotContainsString( 'body ', $css );
		self::assertStringNotContainsString( 'html ', $css );
	}

	public function test_registered_dependencies_preserve_afspaces_then_asgaros_order(): void {
		$method = new \ReflectionMethod( ForumStyleLayer::class, 'get_registered_dependencies' );
		$method->setAccessible( true );

		$dependencies = $method->invoke(
			null,
			array(
				'asgarosforum-custom-style',
				'asgarosforum-style',
				'afspaces-frontend',
				'unrelated-style',
			)
		);

		self::assertSame(
			array( 'afspaces-frontend', 'asgarosforum-style', 'asgarosforum-custom-style' ),
			$dependencies
		);
	}

	public function test_enqueue_implementation_is_late_and_forum_specific(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ForumStyleLayer.php' );

		self::assertStringContainsString( "add_action( 'wp_enqueue_scripts', array( \$this, 'enqueue' ), 999 )", $source );
		self::assertStringContainsString( "'assets/afspaces-forum-overrides.css'", $source );
		self::assertStringContainsString( "has_shortcode( (string) get_post_field( 'post_content', \$post_id ), 'forum' )", $source );
	}
}
