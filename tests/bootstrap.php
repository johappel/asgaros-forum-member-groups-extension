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

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Filtername.
	 * @param mixed  $value Ausgangswert.
	 * @param mixed  ...$args Weitere Argumente.
	 * @return mixed
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		global $afspaces_test_filters;
		foreach ( (array) ( $afspaces_test_filters[ $hook ] ?? array() ) as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'get_avatar_url' ) ) {
	function get_avatar_url( int $user_id, array $args = array() ): string {
		return 'https://example.test/avatar-' . $user_id . '-' . (int) ( $args['size'] ?? 40 ) . '.jpg';
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
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

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		global $afspaces_test_is_user_logged_in;
		return ! isset( $afspaces_test_is_user_logged_in ) || (bool) $afspaces_test_is_user_logged_in;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		global $afspaces_test_current_user_id;
		return (int) ( $afspaces_test_current_user_id ?? 7 );
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

// sanitize_key-Stub.
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param string $key
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
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

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme
	 * @return string
	 */
	function wp_salt( string $scheme = 'auth' ): string {
		return 'afspaces-test-salt-' . $scheme;
	}
}

// Options-Store-Stubs für Tests, die WordPress-Optionen benötigen.
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $name
	 * @param mixed  $default
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		global $afspaces_test_options;
		if ( ! is_array( $afspaces_test_options ) ) {
			$afspaces_test_options = array();
		}
		return array_key_exists( $name, $afspaces_test_options ) ? $afspaces_test_options[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return bool
	 */
	function update_option( string $name, $value ): bool {
		global $afspaces_test_options;
		if ( ! is_array( $afspaces_test_options ) ) {
			$afspaces_test_options = array();
		}
		$afspaces_test_options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * @param int $user_id
	 * @return object|false
	 */
	function get_userdata( int $user_id ) {
		global $afspaces_test_users;
		if ( is_array( $afspaces_test_users ) && array_key_exists( $user_id, $afspaces_test_users ) ) {
			return $afspaces_test_users[ $user_id ];
		}
		if ( $user_id < 1 ) {
			return false;
		}
		return false;
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * @param string $value
	 * @return string
	 */
	function sanitize_textarea_field( string $value ): string {
		return trim( $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $value
	 * @return int
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	/**
	 * @param int    $term_id
	 * @param string $key
	 * @param bool   $single
	 * @return mixed
	 */
	function get_term_meta( int $term_id, string $key = '', bool $single = false ) {
		global $afspaces_test_term_meta;
		if ( ! is_array( $afspaces_test_term_meta ) ) {
			$afspaces_test_term_meta = array();
		}
		$value = $afspaces_test_term_meta[ $term_id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	/**
	 * @param int    $term_id
	 * @param string $key
	 * @param mixed  $value
	 * @return bool
	 */
	function update_term_meta( int $term_id, string $key, $value ): bool {
		global $afspaces_test_term_meta;
		if ( ! is_array( $afspaces_test_term_meta ) ) {
			$afspaces_test_term_meta = array();
		}
		$afspaces_test_term_meta[ $term_id ][ $key ] = $value;
		return true;
	}
}
