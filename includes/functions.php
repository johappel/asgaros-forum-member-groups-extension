<?php
/**
 * Hilfsfunktionen für das Plugin.
 *
 * @package AFSpaces
 */

declare( strict_types=1 );

if ( ! function_exists( 'afspaces' ) ) {
	/**
	 * Gibt die zentrale Plugin-Instanz zurück.
	 *
	 * @return AFSpaces\Plugin
	 */
	function afspaces(): AFSpaces\Plugin {
		return AFSpaces\Plugin::instance();
	}
}
