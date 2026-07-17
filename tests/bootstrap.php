<?php
/**
 * Bootstrap für PHPUnit-Unit-Tests ohne WordPress-Kern.
 *
 * Stubbt die wenigen WordPress-Funktionen, die unsere Domain/Adapter-Logik
 * direkt aufruft, damit die Unit-Tests ohne WP-Testumgebung laufen.
 *
 * @package AFSpaces\Tests
 */

declare( strict_types=1 );

// Verhindert mehrfaches Laden.
if ( defined( 'AFSPACES_TEST_BOOTSTRAPPED' ) ) {
	return;
}
define( 'AFSPACES_TEST_BOOTSTRAPPED', true );

// i18n-Stub: gibt den übersetzten Text (hier unübersetzt) zurück.
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text
	 * @param string $domain
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text
	 * @param string $domain
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'sprintf' ) ) {
	// native, nothing to do.
}

// user_can-Stub: kann pro Test via globalem Callback überschrieben werden.
if ( ! function_exists( 'user_can' ) ) {
	/**
	 * @param int    $user_id
	 * @param string $capability
	 * @return bool
	 */
	function user_can( int $user_id, string $capability ): bool {
		global $afspaces_user_can_callback;
		if ( is_callable( $afspaces_user_can_callback ) ) {
			return (bool) call_user_func( $afspaces_user_can_callback, $user_id, $capability );
		}
		return false;
	}
}

// sanitize_text_field-Stub.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $value
	 * @return string
	 */
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

// current_time-Stub.
if ( ! function_exists( 'current_time' ) ) {
	/**
	 * @param string $type
	 * @return string|int
	 */
	function current_time( string $type ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		return time();
	}
}
