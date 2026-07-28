<?php
/**
 * Baut MySQL-FULLTEXT-Boolean-Ausdrücke aus einer Nutzereingabe.
 *
 * Reine PHP-Logik ohne WordPress-Abhängigkeiten (unit-testbar). Unterstützt
 * Wortgruppen in Anführungszeichen, Kombination mehrerer Begriffe sowie die
 * Modi „alle Wörter“ (AND) und „eines der Wörter“ (OR).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\FulltextQuery' ) ) {

	/**
	 * Wandelt Suchbegriffe in sichere Boolean-Mode-Ausdrücke um.
	 */
	final class FulltextQuery {

		public const MODE_ANY = 'any';
		public const MODE_ALL = 'all';

		/**
		 * Standard-Mindestlänge eines FULLTEXT-Tokens (innodb_ft_min_token_size).
		 */
		public const MIN_TOKEN = 3;

		/**
		 * Zerlegt eine Eingabe in Tokens (Wörter und Phrasen).
		 *
		 * @param string $query Rohe Eingabe.
		 * @return array<int,array{type:string,value:string}> type ist 'phrase' oder 'word'.
		 */
		public static function tokenize( string $query ): array {
			$query  = trim( $query );
			$tokens = array();
			if ( '' === $query ) {
				return $tokens;
			}

			// Phrasen in doppelten Anführungszeichen zuerst extrahieren.
			if ( preg_match_all( '/"([^"]+)"/u', $query, $matches ) ) {
				foreach ( $matches[1] as $phrase ) {
					$phrase = trim( $phrase );
					if ( '' !== $phrase ) {
						$tokens[] = array( 'type' => 'phrase', 'value' => $phrase );
					}
				}
				$query = preg_replace( '/"[^"]+"/u', ' ', $query );
			}

			foreach ( preg_split( '/\s+/u', (string) $query ) ?: array() as $word ) {
				$clean = self::sanitize_word( $word );
				if ( '' === $clean ) {
					continue;
				}
				// Das Entfernen von Sonderzeichen kann zwei Wörter zusammenfügen
				// (z. B. "bar(baz)" -> "bar baz"); daher erneut auftrennen.
				foreach ( preg_split( '/\s+/u', $clean ) ?: array() as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$tokens[] = array( 'type' => 'word', 'value' => $part );
					}
				}
			}

			return $tokens;
		}

		/**
		 * Baut einen Boolean-Mode-Ausdruck.
		 *
		 * @param string $query Rohe Eingabe.
		 * @param string $mode  MODE_ANY oder MODE_ALL.
		 * @return string Leerstring, wenn keine verwertbaren Tokens vorhanden sind.
		 */
		public static function build( string $query, string $mode = self::MODE_ANY ): string {
			$mode   = ( self::MODE_ALL === $mode ) ? self::MODE_ALL : self::MODE_ANY;
			$tokens = self::tokenize( $query );
			if ( empty( $tokens ) ) {
				return '';
			}

			$parts = array();
			foreach ( $tokens as $token ) {
				if ( 'phrase' === $token['type'] ) {
					$part = '"' . $token['value'] . '"';
				} else {
					// Präfixsuche für einzelne Wörter (fängt Wortformen teilweise ab).
					$part = $token['value'] . '*';
				}
				if ( self::MODE_ALL === $mode ) {
					$part = '+' . $part;
				}
				$parts[] = $part;
			}

			return implode( ' ', $parts );
		}

		/**
		 * Liefert die Begriffe für eine LIKE-Ersatzsuche (unquotiert).
		 *
		 * @param string $query Rohe Eingabe.
		 * @return string[] Eindeutige, nicht-leere Begriffe.
		 */
		public static function like_terms( string $query ): array {
			$terms = array();
			foreach ( self::tokenize( $query ) as $token ) {
				$value = trim( $token['value'] );
				if ( '' !== $value ) {
					$terms[] = $value;
				}
			}
			return array_values( array_unique( $terms ) );
		}

		/**
		 * Länge des längsten Tokens (zur Erkennung sehr kurzer Suchbegriffe).
		 *
		 * @param string $query Rohe Eingabe.
		 * @return int
		 */
		public static function longest_token_length( string $query ): int {
			$max = 0;
			foreach ( self::tokenize( $query ) as $token ) {
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( $token['value'], 'UTF-8' ) : strlen( $token['value'] );
				if ( $len > $max ) {
					$max = $len;
				}
			}
			return $max;
		}

		/**
		 * Prüft, ob eine LIKE-Ersatzsuche nötig ist (alle Tokens zu kurz für FULLTEXT).
		 *
		 * @param string $query    Rohe Eingabe.
		 * @param int    $min      Mindest-Tokenlänge.
		 * @return bool
		 */
		public static function needs_like_fallback( string $query, int $min = self::MIN_TOKEN ): bool {
			$tokens = self::tokenize( $query );
			if ( empty( $tokens ) ) {
				return false;
			}
			return self::longest_token_length( $query ) < max( 1, $min );
		}

		/**
		 * Entfernt Boolean-Operatoren und Sonderzeichen aus einem einzelnen Wort.
		 *
		 * @param string $word Rohwort.
		 * @return string
		 */
		private static function sanitize_word( string $word ): string {
			$word = trim( $word );
			// Boolean-Operatoren und Klammern entfernen, damit sie die Syntax nicht stören.
			$word = preg_replace( '/[+\-<>()~*@"]+/u', ' ', $word );
			return trim( (string) $word );
		}
	}
}
