<?php
/**
 * Unit-Tests für den FulltextQuery-Builder (MVP 3).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Search\FulltextQuery;
use PHPUnit\Framework\TestCase;

final class FulltextQueryTest extends TestCase {

	public function test_single_word_gets_prefix(): void {
		$this->assertSame( 'funktion*', FulltextQuery::build( 'funktion' ) );
	}

	public function test_any_mode_combines_without_plus(): void {
		$this->assertSame( 'foo* bar*', FulltextQuery::build( 'foo bar', FulltextQuery::MODE_ANY ) );
	}

	public function test_all_mode_prefixes_each_with_plus(): void {
		$this->assertSame( '+foo* +bar*', FulltextQuery::build( 'foo bar', FulltextQuery::MODE_ALL ) );
	}

	public function test_quoted_phrase_is_preserved(): void {
		$this->assertSame( '"künstliche Intelligenz"', FulltextQuery::build( '"künstliche Intelligenz"' ) );
	}

	public function test_all_mode_phrase_and_word(): void {
		$this->assertSame( '+"neue Funktion" +test*', FulltextQuery::build( '"neue Funktion" test', FulltextQuery::MODE_ALL ) );
	}

	public function test_umlaut_word_kept(): void {
		$this->assertSame( 'überprüfung*', FulltextQuery::build( 'überprüfung' ) );
	}

	public function test_boolean_operators_are_stripped(): void {
		// Steuerzeichen dürfen die Boolean-Syntax nicht verändern.
		$out = FulltextQuery::build( '+foo -bar(baz)' );
		$this->assertStringNotContainsString( '(', $out );
		$this->assertStringNotContainsString( ')', $out );
		$this->assertStringContainsString( 'foo*', $out );
		$this->assertStringContainsString( 'bar*', $out );
		$this->assertStringContainsString( 'baz*', $out );
	}

	public function test_empty_query_returns_empty(): void {
		$this->assertSame( '', FulltextQuery::build( '   ' ) );
	}

	public function test_like_terms_returns_plain_terms(): void {
		$terms = FulltextQuery::like_terms( '"neue Funktion" KI' );
		$this->assertContains( 'neue Funktion', $terms );
		$this->assertContains( 'KI', $terms );
	}

	public function test_needs_like_fallback_for_short_tokens(): void {
		$this->assertTrue( FulltextQuery::needs_like_fallback( 'KI' ) );
		$this->assertTrue( FulltextQuery::needs_like_fallback( 'ab cd' ) );
		$this->assertFalse( FulltextQuery::needs_like_fallback( 'funktion' ) );
		// Eine ausreichend lange Phrase braucht keinen Fallback.
		$this->assertFalse( FulltextQuery::needs_like_fallback( '"neue Funktion"' ) );
	}

	public function test_longest_token_length(): void {
		$this->assertSame( 8, FulltextQuery::longest_token_length( 'ab funktion cd' ) );
	}
}
