<?php
/**
 * Tests für die Asgaros-Kategorie-Farbregeln.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use PHPUnit\Framework\TestCase;

final class ForumNavigationStyleTest extends TestCase {

	public function test_category_color_selector_beats_asgaros_title_element_rule(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Interface/ForumNavigation.php' );

		self::assertStringContainsString( '#af-wrapper #forum-category-%1$d', $source );
		self::assertStringContainsString( '#af-wrapper #forum-category-%1$d .title-element', $source );
		self::assertStringNotContainsString( "'#forum-category-%1\$d{", $source );
	}
}
