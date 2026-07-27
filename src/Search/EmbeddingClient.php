<?php
/**
 * HTTP-Client für die Embedding-API (OpenRouter-kompatibel).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

use AFSpaces\Core\DomainException;

if ( ! class_exists( 'AFSpaces\\Search\\EmbeddingClient' ) ) {

	/**
	 * Ruft Embeddings über eine OpenRouter-kompatible API ab.
	 *
	 * Der API-Schlüssel wird ausschließlich im Authorization-Header gesendet
	 * und niemals geloggt. Batch-Anfragen (Array von Texten) werden unterstützt.
	 */
	final class EmbeddingClient {

		/**
		 * API-Endpunkt.
		 *
		 * @var string
		 */
		private string $api_url;

		/**
		 * API-Schlüssel.
		 *
		 * @var string
		 */
		private string $api_key;

		/**
		 * Modellname.
		 *
		 * @var string
		 */
		private string $model;

		/**
		 * Timeout in Sekunden.
		 *
		 * @var int
		 */
		private int $timeout;

		/**
		 * Konstruktor.
		 *
		 * @param string $api_url API-Endpunkt.
		 * @param string $api_key API-Schlüssel.
		 * @param string $model   Modellname.
		 * @param int    $timeout Timeout in Sekunden.
		 */
		public function __construct( string $api_url, string $api_key, string $model, int $timeout = 30 ) {
			$this->api_url = $api_url;
			$this->api_key = $api_key;
			$this->model   = $model;
			$this->timeout = max( 5, $timeout );
		}

		/**
		 * Erzeugt einen SearchSettings-basierten Client.
		 *
		 * @return self
		 */
		public static function from_settings(): self {
			return new self(
				SearchSettings::api_url(),
				SearchSettings::api_key(),
				SearchSettings::model()
			);
		}

		/**
		 * Bettet einen einzelnen Text ein.
		 *
		 * @param string $text Text.
		 * @return float[] Vektor.
		 * @throws DomainException Bei Fehlern.
		 */
		public function embed_one( string $text ): array {
			$vectors = $this->embed( array( $text ) );
			return $vectors[0] ?? array();
		}

		/**
		 * Bettet mehrere Texte in einem Aufruf ein (Batch).
		 *
		 * @param string[] $texts Texte.
		 * @return array<int,float[]> Vektoren in Eingabereihenfolge.
		 * @throws DomainException Bei Konfigurations- oder Transportfehlern.
		 */
		public function embed( array $texts ): array {
			$texts = array_values( array_map( 'strval', $texts ) );
			if ( empty( $texts ) ) {
				return array();
			}

			if ( '' === trim( $this->api_url ) || '' === trim( $this->api_key ) || '' === trim( $this->model ) ) {
				throw new DomainException(
					__( 'Die Embedding-API ist nicht vollständig konfiguriert.', 'afspaces' )
				);
			}

			if ( ! function_exists( 'wp_remote_post' ) ) {
				throw new DomainException(
					__( 'HTTP-Funktionen sind nicht verfügbar.', 'afspaces' )
				);
			}

			$body = wp_json_encode(
				array(
					'model'           => $this->model,
					'input'           => count( $texts ) === 1 ? $texts[0] : $texts,
					'encoding_format' => 'float',
				)
			);

			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout' => $this->timeout,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->api_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				throw new DomainException(
					sprintf(
						/* translators: %s: Fehlermeldung */
						__( 'Embedding-Anfrage fehlgeschlagen: %s', 'afspaces' ),
						$response->get_error_message()
					)
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code < 200 || $code >= 300 ) {
				throw new DomainException(
					sprintf(
						/* translators: %d: HTTP-Statuscode */
						__( 'Embedding-API antwortete mit Status %d.', 'afspaces' ),
						$code
					)
				);
			}

			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $decoded ) || empty( $decoded['data'] ) || ! is_array( $decoded['data'] ) ) {
				throw new DomainException(
					__( 'Unerwartete Antwort der Embedding-API.', 'afspaces' )
				);
			}

			// Nach dem `index`-Feld sortieren, um die Eingabereihenfolge sicherzustellen.
			$rows = $decoded['data'];
			usort(
				$rows,
				static function ( $a, $b ): int {
					return ( (int) ( $a['index'] ?? 0 ) ) <=> ( (int) ( $b['index'] ?? 0 ) );
				}
			);

			$vectors = array();
			foreach ( $rows as $row ) {
				$embedding = isset( $row['embedding'] ) && is_array( $row['embedding'] )
					? array_map( 'floatval', $row['embedding'] )
					: array();
				$vectors[] = $embedding;
			}

			return $vectors;
		}
	}
}
