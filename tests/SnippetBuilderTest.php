<?php
/**
 * Unit-Tests für den SnippetBuilder (Ausschnitt + Hervorhebung).
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

namespace AFSpaces\Tests;

use AFSpaces\Search\SnippetBuilder;
use PHPUnit\Framework\TestCase;

final class SnippetBuilderTest extends TestCase {

	/**
	 * Fügt die Segmente wieder zu reinem Text zusammen.
	 *
	 * @param array<int,array{text:string,mark:bool}> $segments Segmente.
	 * @return string
	 */
	private function join( array $segments ): string {
		$text = '';
		foreach ( $segments as $segment ) {
			$text .= $segment['text'];
		}
		return $text;
	}

	/**
	 * Liefert alle als Treffer markierten Textteile.
	 *
	 * @param array<int,array{text:string,mark:bool}> $segments Segmente.
	 * @return string[]
	 */
	private function marked( array $segments ): array {
		$marked = array();
		foreach ( $segments as $segment ) {
			if ( ! empty( $segment['mark'] ) ) {
				$marked[] = $segment['text'];
			}
		}
		return $marked;
	}

	public function test_tokenize_splits_words_and_ignores_short_fragments(): void {
		$terms = SnippetBuilder::tokenize( 'KI a Werkzeuge' );
		$this->assertContains( 'Werkzeuge', $terms );
		$this->assertContains( 'KI', $terms );
		$this->assertNotContains( 'a', $terms );
	}

	public function test_tokenize_keeps_quoted_phrases_together(): void {
		$terms = SnippetBuilder::tokenize( '"künstliche Intelligenz" Fobizz' );
		$this->assertContains( 'künstliche Intelligenz', $terms );
		$this->assertContains( 'Fobizz', $terms );
	}

	public function test_build_marks_the_search_term(): void {
		$text     = 'Für unsere Fortbildung haben wir insbesondere ChatGPT und Fobizz verglichen.';
		$segments = SnippetBuilder::build( $text, 'ChatGPT' );

		$this->assertContains( 'ChatGPT', $this->marked( $segments ) );
	}

	public function test_build_is_case_insensitive_and_umlaut_aware(): void {
		$text     = 'Die Elterntherapie ist ein zentrales Thema unserer Arbeitsgruppe.';
		$segments = SnippetBuilder::build( $text, 'elterntherapie' );

		$marked = $this->marked( $segments );
		$this->assertNotEmpty( $marked );
		$this->assertSame( 'Elterntherapie', $marked[0] );
	}

	public function test_build_strips_html_markup(): void {
		$text     = '<p>Wir nutzen <strong>ChatGPT</strong> im Unterricht.</p>';
		$segments = SnippetBuilder::build( $text, 'ChatGPT' );
		$plain    = $this->join( $segments );

		$this->assertStringNotContainsString( '<strong>', $plain );
		$this->assertStringContainsString( 'ChatGPT', $plain );
	}

	public function test_build_adds_ellipsis_when_match_is_far_inside(): void {
		$prefix   = str_repeat( 'Lorem ipsum dolor sit amet. ', 20 );
		$text     = $prefix . 'Hier steht das gesuchte Zauberwort mittendrin.';
		$segments = SnippetBuilder::build( $text, 'Zauberwort', 30 );
		$plain    = $this->join( $segments );

		$this->assertStringStartsWith( '…', $plain );
		$this->assertStringContainsString( 'Zauberwort', $plain );
	}

	public function test_build_returns_empty_for_empty_text(): void {
		$this->assertSame( array(), SnippetBuilder::build( '', 'egal' ) );
	}

	public function test_build_without_match_returns_beginning(): void {
		$text     = 'Ein Beitrag ganz ohne das gesuchte Fremdwort.';
		$segments = SnippetBuilder::build( $text, 'xylophon' );
		$plain    = $this->join( $segments );

		$this->assertStringContainsString( 'Ein Beitrag', $plain );
		$this->assertSame( array(), $this->marked( $segments ) );
	}
}
