<?php
/**
 * Vektor-Hilfsfunktionen für die semantische Suche.
 *
 * Reine PHP-Logik ohne WordPress-Abhängigkeiten (unit-testbar):
 * Cosine-Ähnlichkeit sowie kompakte Serialisierung von Float-Vektoren
 * als 32-Bit-Little-Endian-Blob für die Speicherung im Index.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Search;

if ( ! class_exists( 'AFSpaces\\Search\\VectorMath' ) ) {

	/**
	 * Stellt Vektoroperationen für Embeddings bereit.
	 */
	final class VectorMath {

		/**
		 * Berechnet die Cosine-Ähnlichkeit zweier gleich langer Vektoren.
		 *
		 * @param float[] $a Erster Vektor.
		 * @param float[] $b Zweiter Vektor.
		 * @return float Wert im Bereich [-1, 1]; 0.0 bei ungültigen Eingaben.
		 */
		public static function cosine( array $a, array $b ): float {
			$len = count( $a );
			if ( 0 === $len || $len !== count( $b ) ) {
				return 0.0;
			}

			$dot = 0.0;
			$na  = 0.0;
			$nb  = 0.0;
			$bv  = array_values( $b );
			$i   = 0;
			foreach ( $a as $va ) {
				$va   = (float) $va;
				$vb   = (float) $bv[ $i ];
				$dot += $va * $vb;
				$na  += $va * $va;
				$nb  += $vb * $vb;
				$i++;
			}

			if ( $na <= 0.0 || $nb <= 0.0 ) {
				return 0.0;
			}

			return $dot / ( sqrt( $na ) * sqrt( $nb ) );
		}

		/**
		 * Cosine-Ähnlichkeit gegen einen bereits normalisierten Vektor.
		 *
		 * Erwartet, dass beide Vektoren L2-normalisiert sind, sodass nur das
		 * Skalarprodukt berechnet werden muss (schneller bei vielen Vergleichen).
		 *
		 * @param float[] $normalized_a Normalisierter Vektor.
		 * @param float[] $normalized_b Normalisierter Vektor.
		 * @return float
		 */
		public static function dot( array $normalized_a, array $normalized_b ): float {
			$len = count( $normalized_a );
			if ( 0 === $len || $len !== count( $normalized_b ) ) {
				return 0.0;
			}
			$bv  = array_values( $normalized_b );
			$dot = 0.0;
			$i   = 0;
			foreach ( $normalized_a as $va ) {
				$dot += (float) $va * (float) $bv[ $i ];
				$i++;
			}
			return $dot;
		}

		/**
		 * L2-normalisiert einen Vektor.
		 *
		 * @param float[] $vector Vektor.
		 * @return float[] Normalisierter Vektor (unverändert bei Nullvektor).
		 */
		public static function normalize( array $vector ): array {
			$sum = 0.0;
			foreach ( $vector as $v ) {
				$sum += (float) $v * (float) $v;
			}
			if ( $sum <= 0.0 ) {
				return array_map( 'floatval', $vector );
			}
			$norm   = sqrt( $sum );
			$result = array();
			foreach ( $vector as $v ) {
				$result[] = (float) $v / $norm;
			}
			return $result;
		}

		/**
		 * Serialisiert einen Float-Vektor als 32-Bit-Little-Endian-Binärblob.
		 *
		 * @param float[] $vector Vektor.
		 * @return string Binärdaten (4 Byte pro Dimension).
		 */
		public static function pack_vector( array $vector ): string {
			if ( empty( $vector ) ) {
				return '';
			}
			return pack( 'g*', ...array_map( 'floatval', $vector ) );
		}

		/**
		 * Deserialisiert einen 32-Bit-Little-Endian-Blob zu einem Float-Vektor.
		 *
		 * @param string $blob Binärdaten.
		 * @return float[] Vektor.
		 */
		public static function unpack_vector( string $blob ): array {
			if ( '' === $blob ) {
				return array();
			}
			$values = unpack( 'g*', $blob );
			if ( false === $values ) {
				return array();
			}
			return array_values( array_map( 'floatval', $values ) );
		}
	}
}
