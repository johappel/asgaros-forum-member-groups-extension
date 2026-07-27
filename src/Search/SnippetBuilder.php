<?php
/**
 * Baut Textausschnitte mit hervorgehobenen Fundstellen.
 *
 * Reine PHP-Logik ohne WordPress-Abhängigkeiten, damit sie isoliert per
 * Unit-Test geprüft werden kann. Das Escaping erfolgt bewusst NICHT hier,
 * sondern in der View: Diese Klasse liefert strukturierte Segmente
 * (`text` + `mark`), die der Aufrufer kontextgerecht escapen muss.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\SnippetBuilder' ) ) {

	/**
	 * Erzeugt einen Ausschnitt rund um die relevanteste Fundstelle.
	 */
	final class SnippetBuilder {

		/**
		 * Zerlegt einen Suchbegriff in einzelne Suchwörter.
		 *
		 * Wortgruppen in Anführungszeichen werden als ein Suchwort behandelt.
		 * Sehr kurze Fragmente (< 2 Zeichen) werden ignoriert.
		 *
		 * @param string $query Roher Suchbegriff.
		 * @return string[] Liste eindeutiger, kleingeschriebener Suchwörter.
		 */
		public static function tokenize( string $query ): array {
			$query = trim( $query );
			if ( '' === $query ) {
				return array();
			}

			$terms = array();

			// Zuerst Wortgruppen in doppelten Anführungszeichen extrahieren.
			if ( preg_match_all( '/"([^"]+)"/u', $query, $matches ) ) {
				foreach ( $matches[1] as $phrase ) {
					$phrase = trim( $phrase );
					if ( '' !== $phrase ) {
						$terms[] = $phrase;
					}
				}
				$query = preg_replace( '/"[^"]+"/u', ' ', $query );
			}

			foreach ( preg_split( '/\s+/u', (string) $query ) ?: array() as $word ) {
				$word = trim( $word );
				if ( self::length( $word ) >= 2 ) {
					$terms[] = $word;
				}
			}

			// Eindeutig, aber Reihenfolge erhalten (längere Begriffe zuerst hervorheben).
			$terms = array_values( array_unique( $terms ) );
			usort(
				$terms,
				static function ( string $a, string $b ): int {
					return self::length( $b ) <=> self::length( $a );
				}
			);

			return $terms;
		}

		/**
		 * Baut einen Ausschnitt mit markierten Fundstellen.
		 *
		 * @param string $raw_text Beitragstext (darf HTML/Markup enthalten).
		 * @param string $query    Suchbegriff.
		 * @param int    $radius   Ungefähre Anzahl Zeichen vor/nach der Fundstelle.
		 * @return array<int,array{text:string,mark:bool}> Segmente für die Ausgabe.
		 */
		public static function build( string $raw_text, string $query, int $radius = 60 ): array {
			$text  = self::to_plain_text( $raw_text );
			$terms = self::tokenize( $query );

			if ( '' === $text ) {
				return array();
			}

			$total_length = self::length( $text );
			$lower_text   = self::lower( $text );

			// Erste Fundstelle bestimmen.
			$first_pos  = null;
			$first_term = '';
			foreach ( $terms as $term ) {
				$pos = self::position( $lower_text, self::lower( $term ) );
				if ( null !== $pos && ( null === $first_pos || $pos < $first_pos ) ) {
					$first_pos  = $pos;
					$first_term = $term;
				}
			}

			// Fenstergrenzen berechnen.
			if ( null === $first_pos ) {
				// Keine direkte Fundstelle (z. B. Volltext-Stammform): Anfang zeigen.
				$start  = 0;
				$length = min( $total_length, $radius * 2 );
			} else {
				$start  = max( 0, $first_pos - $radius );
				$length = self::length( $first_term ) + ( $radius * 2 );
				if ( $start + $length > $total_length ) {
					$length = $total_length - $start;
				}
			}

			$excerpt = self::substr( $text, $start, $length );
			$prefix  = $start > 0 ? '… ' : '';
			$suffix  = ( $start + $length ) < $total_length ? ' …' : '';

			$segments = self::mark_terms( $excerpt, $terms );

			if ( '' !== $prefix ) {
				array_unshift( $segments, array( 'text' => $prefix, 'mark' => false ) );
			}
			if ( '' !== $suffix ) {
				$segments[] = array( 'text' => $suffix, 'mark' => false );
			}

			return $segments;
		}

		/**
		 * Markiert alle Vorkommen der Suchwörter innerhalb eines Ausschnitts.
		 *
		 * @param string   $excerpt Ausschnitt.
		 * @param string[] $terms   Suchwörter.
		 * @return array<int,array{text:string,mark:bool}>
		 */
		private static function mark_terms( string $excerpt, array $terms ): array {
			if ( '' === $excerpt ) {
				return array();
			}

			if ( empty( $terms ) ) {
				return array( array( 'text' => $excerpt, 'mark' => false ) );
			}

			$lower    = self::lower( $excerpt );
			$length   = self::length( $excerpt );
			$marked   = array_fill( 0, $length, false );

			foreach ( $terms as $term ) {
				$term_lower = self::lower( $term );
				$term_len   = self::length( $term_lower );
				if ( $term_len < 1 ) {
					continue;
				}

				$offset = 0;
				while ( true ) {
					$pos = self::position( $lower, $term_lower, $offset );
					if ( null === $pos ) {
						break;
					}
					for ( $i = $pos; $i < $pos + $term_len && $i < $length; $i++ ) {
						$marked[ $i ] = true;
					}
					$offset = $pos + $term_len;
				}
			}

			// Zusammenhängende Bereiche gleicher Markierung zu Segmenten bündeln.
			$segments = array();
			$buffer   = '';
			$current  = $marked[0];
			for ( $i = 0; $i < $length; $i++ ) {
				if ( $marked[ $i ] === $current ) {
					$buffer .= self::substr( $excerpt, $i, 1 );
				} else {
					$segments[] = array( 'text' => $buffer, 'mark' => $current );
					$buffer     = self::substr( $excerpt, $i, 1 );
					$current    = $marked[ $i ];
				}
			}
			if ( '' !== $buffer ) {
				$segments[] = array( 'text' => $buffer, 'mark' => $current );
			}

			return $segments;
		}

		/**
		 * Wandelt Beitragsmarkup in reinen, kompakten Text um.
		 *
		 * @param string $raw Rohtext.
		 * @return string
		 */
		private static function to_plain_text( string $raw ): string {
			// HTML-Tags entfernen.
			$text = preg_replace( '/<[^>]*>/u', ' ', $raw );
			$text = (string) $text;
			// HTML-Entities auflösen.
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Whitespace normalisieren.
			$text = preg_replace( '/\s+/u', ' ', $text );

			return trim( (string) $text );
		}

		/**
		 * Multibyte-sichere Kleinschreibung.
		 *
		 * @param string $value Wert.
		 * @return string
		 */
		private static function lower( string $value ): string {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		}

		/**
		 * Multibyte-sichere Länge.
		 *
		 * @param string $value Wert.
		 * @return int
		 */
		private static function length( string $value ): int {
			return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		}

		/**
		 * Multibyte-sicherer Teilstring.
		 *
		 * @param string $value  Wert.
		 * @param int    $start  Startindex.
		 * @param int    $length Länge.
		 * @return string
		 */
		private static function substr( string $value, int $start, int $length ): string {
			return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length, 'UTF-8' ) : substr( $value, $start, $length );
		}

		/**
		 * Multibyte-sichere Positionssuche.
		 *
		 * @param string $haystack Text.
		 * @param string $needle   Suchwort.
		 * @param int    $offset   Startversatz.
		 * @return int|null
		 */
		private static function position( string $haystack, string $needle, int $offset = 0 ): ?int {
			if ( '' === $needle ) {
				return null;
			}
			$pos = function_exists( 'mb_strpos' )
				? mb_strpos( $haystack, $needle, $offset, 'UTF-8' )
				: strpos( $haystack, $needle, $offset );

			return ( false === $pos ) ? null : (int) $pos;
		}
	}
}
