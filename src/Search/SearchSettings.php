<?php
/**
 * Zugriff auf die Sucheinstellungen (Option `afspaces_search_options`).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\SearchSettings' ) ) {

	/**
	 * Kapselt Lesen, Defaults und Sanitizing der Sucheinstellungen.
	 *
	 * Der API-Schlüssel wird niemals in Logs oder Frontend ausgegeben.
	 */
	final class SearchSettings {

		public const OPTION_KEY = 'afspaces_search_options';

		/**
		 * Standardwerte.
		 *
		 * @return array<string,mixed>
		 */
		public static function defaults(): array {
			return array(
				'embedding_enabled'   => false,
				'embedding_api_url'   => 'https://openrouter.ai/api/v1/embeddings',
				'embedding_api_key'   => '',
				'embedding_model'     => 'perplexity/pplx-embed-v1-0.6b',
				'index_private'       => false,
				'index_wp'            => true,
				'wp_post_types'       => array( 'post', 'page' ),
				'semantic_weight'     => 1.0,
				'keyword_weight'      => 1.0,
				'semantic_min_score'  => 0.30,
				'replace_wp_search'   => false,
			);
		}

		/**
		 * Liest die gespeicherten Optionen (mit Defaults zusammengeführt).
		 *
		 * @return array<string,mixed>
		 */
		public static function all(): array {
			$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_KEY, array() ) : array();
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			return array_merge( self::defaults(), $stored );
		}

		/**
		 * Ist die semantische Suche vollständig konfiguriert und aktiviert?
		 *
		 * @return bool
		 */
		public static function is_semantic_enabled(): bool {
			$o = self::all();
			return ! empty( $o['embedding_enabled'] )
				&& '' !== trim( (string) $o['embedding_api_url'] )
				&& '' !== trim( (string) $o['embedding_api_key'] )
				&& '' !== trim( (string) $o['embedding_model'] );
		}

		/**
		 * Sollen private Arbeitsgruppen-Inhalte extern eingebettet werden?
		 *
		 * @return bool
		 */
		public static function index_private(): bool {
			$o = self::all();
			return ! empty( $o['index_private'] );
		}

		/**
		 * Sollen WordPress-Beiträge indexiert werden?
		 *
		 * @return bool
		 */
		public static function index_wp(): bool {
			$o = self::all();
			return ! empty( $o['index_wp'] );
		}

		/**
		 * Durchsuchbare WP-Beitragstypen.
		 *
		 * @return string[]
		 */
		public static function wp_post_types(): array {
			$o     = self::all();
			$types = isset( $o['wp_post_types'] ) ? (array) $o['wp_post_types'] : array();
			$types = array_values( array_filter( array_map( 'strval', $types ) ) );
			return empty( $types ) ? array( 'post', 'page' ) : $types;
		}

		/**
		 * Gewicht der semantischen Rangliste in der Fusion.
		 *
		 * @return float
		 */
		public static function semantic_weight(): float {
			$o = self::all();
			return max( 0.0, (float) $o['semantic_weight'] );
		}

		/**
		 * Gewicht der Keyword-Ranglisten in der Fusion.
		 *
		 * @return float
		 */
		public static function keyword_weight(): float {
			$o = self::all();
			return max( 0.0, (float) $o['keyword_weight'] );
		}

		/**
		 * Mindest-Cosine-Ähnlichkeit, damit ein semantischer Treffer zählt.
		 *
		 * Filtert schwache „Treffer“ heraus, die sonst bei kleinen Korpora
		 * unverständliche Ergebnisse liefern.
		 *
		 * @return float
		 */
		public static function semantic_min_score(): float {
			$o = self::all();
			return min( 1.0, max( 0.0, (float) $o['semantic_min_score'] ) );
		}

		/**
		 * Soll das Such-Overlay auch die normale WordPress-Suche ersetzen?
		 *
		 * @return bool
		 */
		public static function replace_wp_search(): bool {
			$o = self::all();
			return ! empty( $o['replace_wp_search'] );
		}

		/**
		 * API-Endpunkt.
		 *
		 * @return string
		 */
		public static function api_url(): string {
			return (string) self::all()['embedding_api_url'];
		}

		/**
		 * API-Schlüssel (nur serverseitig verwenden).
		 *
		 * @return string
		 */
		public static function api_key(): string {
			return (string) self::all()['embedding_api_key'];
		}

		/**
		 * Modellname.
		 *
		 * @return string
		 */
		public static function model(): string {
			return (string) self::all()['embedding_model'];
		}

		/**
		 * Bereinigt eingehende Optionswerte.
		 *
		 * Ein leer eingereichter API-Schlüssel überschreibt den bestehenden
		 * NICHT (damit er nicht versehentlich gelöscht wird).
		 *
		 * @param mixed $input Rohe Eingabe.
		 * @return array<string,mixed>
		 */
		public static function sanitize( $input ): array {
			$input    = is_array( $input ) ? $input : array();
			$existing = self::all();
			$out      = self::defaults();

			$out['embedding_enabled'] = ! empty( $input['embedding_enabled'] );
			$out['index_private']     = ! empty( $input['index_private'] );
			$out['index_wp']          = ! empty( $input['index_wp'] );

			$out['embedding_api_url'] = isset( $input['embedding_api_url'] )
				? esc_url_raw( trim( (string) $input['embedding_api_url'] ) )
				: $existing['embedding_api_url'];

			$out['embedding_model'] = isset( $input['embedding_model'] )
				? sanitize_text_field( (string) $input['embedding_model'] )
				: $existing['embedding_model'];

			// API-Schlüssel nur überschreiben, wenn ein neuer Wert übergeben wurde.
			$submitted_key = isset( $input['embedding_api_key'] ) ? trim( (string) $input['embedding_api_key'] ) : '';
			$out['embedding_api_key'] = ( '' !== $submitted_key )
				? sanitize_text_field( $submitted_key )
				: (string) $existing['embedding_api_key'];

			$types = isset( $input['wp_post_types'] ) ? (array) $input['wp_post_types'] : array( 'post', 'page' );
			$out['wp_post_types'] = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
			if ( empty( $out['wp_post_types'] ) ) {
				$out['wp_post_types'] = array( 'post', 'page' );
			}

			$out['semantic_weight'] = isset( $input['semantic_weight'] ) ? max( 0.0, (float) $input['semantic_weight'] ) : 1.0;
			$out['keyword_weight']  = isset( $input['keyword_weight'] ) ? max( 0.0, (float) $input['keyword_weight'] ) : 1.0;
			$out['semantic_min_score'] = isset( $input['semantic_min_score'] ) ? min( 1.0, max( 0.0, (float) $input['semantic_min_score'] ) ) : 0.30;
			$out['replace_wp_search']  = ! empty( $input['replace_wp_search'] );

			return $out;
		}
	}
}
