<?php
/**
 * Rangfusion mehrerer Trefferlisten (Reciprocal Rank Fusion).
 *
 * Reine PHP-Logik ohne WordPress-Abhängigkeiten (unit-testbar).
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\ResultFusion' ) ) {

	/**
	 * Fusioniert mehrere gewichtete Rangfolgen zu einer Gesamtrangfolge.
	 *
	 * Verwendet Reciprocal Rank Fusion (RRF): Ein Element auf Rang r einer
	 * Liste trägt `gewicht / (k + r)` zum Gesamtscore bei. Elemente, die in
	 * mehreren Listen erscheinen, summieren ihre Beiträge und steigen dadurch
	 * auf. Die Fusion ist robust gegenüber unterschiedlichen Score-Skalen
	 * (Keyword-Relevanz vs. Cosine-Ähnlichkeit).
	 */
	final class ResultFusion {

		/**
		 * RRF-Dämpfungskonstante (Standardwert aus der Literatur).
		 */
		public const DEFAULT_K = 60;

		/**
		 * Fusioniert mehrere Ranglisten von Schlüsseln.
		 *
		 * @param array<int,array{keys: string[], weight?: float}> $lists Ranglisten.
		 *        Jede Liste ist eine nach Relevanz absteigend sortierte Schlüsselliste.
		 * @param int                                              $k     Dämpfungskonstante.
		 * @return array<int,array{key:string,score:float}> Absteigend nach Score sortiert.
		 */
		public static function fuse( array $lists, int $k = self::DEFAULT_K ): array {
			$k      = max( 1, $k );
			$scores = array();

			foreach ( $lists as $list ) {
				$keys   = isset( $list['keys'] ) ? array_values( (array) $list['keys'] ) : array();
				$weight = isset( $list['weight'] ) ? (float) $list['weight'] : 1.0;
				if ( $weight <= 0.0 ) {
					continue;
				}

				foreach ( $keys as $rank => $key ) {
					$key = (string) $key;
					if ( '' === $key ) {
						continue;
					}
					$contribution = $weight / ( $k + ( $rank + 1 ) );
					if ( ! isset( $scores[ $key ] ) ) {
						$scores[ $key ] = 0.0;
					}
					$scores[ $key ] += $contribution;
				}
			}

			$result = array();
			foreach ( $scores as $key => $score ) {
				$result[] = array(
					'key'   => (string) $key,
					'score' => (float) $score,
				);
			}

			usort(
				$result,
				static function ( array $a, array $b ): int {
					if ( $a['score'] === $b['score'] ) {
						return strcmp( $a['key'], $b['key'] );
					}
					return ( $a['score'] < $b['score'] ) ? 1 : -1;
				}
			);

			return $result;
		}
	}
}
