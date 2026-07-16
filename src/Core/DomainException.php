<?php
/**
 * Domain-Ausnahme für übersetzte Fehler aus Adapter-Schichten.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

namespace AFSpaces\Core;

use RuntimeException;

if ( ! class_exists( 'AFSpaces\\Core\\DomainException' ) ) {

	/**
	 * Allgemeine Domain-Ausnahme mit übersetztem, nutzernahem Text.
	 */
	class DomainException extends RuntimeException {

		/**
		 * Erzeugt eine Ausnahme mit übersetztem Standardtext.
		 *
		 * @param string         $message  Übersetzter Fehlertext.
		 * @param int            $code     Optionaler Fehlercode.
		 * @param \Throwable|null $previous Vorherige Ausnahme.
		 */
		public function __construct( string $message, int $code = 0, ?\Throwable $previous = null ) {
			parent::__construct( $message, $code, $previous );
		}
	}
}
