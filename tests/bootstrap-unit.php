<?php
/**
 * Bootstrap for the unit suite: no WordPress, only the plugin's autoloader and the few functions the code under test calls.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stand-in for WordPress's wp_unslash().
	 *
	 * @param mixed $value The value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stand-in for WordPress's sanitize_text_field(): enough for the plain values the tests send.
	 *
	 * @param string $value The value.
	 */
	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}
}

// A secret of the plugin's own, so the Vault works without WordPress's salts.
if ( ! defined( 'IFTP_BTN_SECRET' ) ) {
	define( 'IFTP_BTN_SECRET', str_repeat( 'k', 40 ) );
}

// Options in memory, so the repositories can be exercised without a database.
$GLOBALS['iftp_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stand-in for get_option().
	 *
	 * @param string $name    The option.
	 * @param mixed  $default Returned when unset.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['iftp_test_options'] ) ? $GLOBALS['iftp_test_options'][ $name ] : $default;
	}

	/**
	 * Stand-in for update_option().
	 *
	 * @param string $name  The option.
	 * @param mixed  $value The value.
	 */
	function update_option( $name, $value ): bool {
		$GLOBALS['iftp_test_options'][ $name ] = $value;

		return true;
	}

	/**
	 * Stand-in for delete_option().
	 *
	 * @param string $name The option.
	 */
	function delete_option( $name ): bool {
		unset( $GLOBALS['iftp_test_options'][ $name ] );

		return true;
	}

	/**
	 * Stand-in for wp_generate_password().
	 *
	 * @param int $length How long.
	 */
	function wp_generate_password( $length = 12 ): string {
		return substr( str_repeat( bin2hex( random_bytes( 16 ) ), (int) ceil( $length / 32 ) ), 0, (int) $length );
	}
}

// What the AI's drawn-code checks call: who may post unfiltered HTML is up to the test (DrawnCheckoutTest).
if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Stand-in for current_user_can(): the test says who may post unfiltered HTML.
	 *
	 * @param string $capability The capability.
	 */
	function current_user_can( $capability ): bool {
		return 'unfiltered_html' === $capability ? ! empty( $GLOBALS['iftp_test_unfiltered'] ) : true;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stand-in for __().
	 *
	 * @param string $text The text.
	 */
	function __( $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stand-in for wp_check_invalid_utf8().
	 *
	 * @param string $text The text.
	 */
	function wp_check_invalid_utf8( $text ): string {
		return (string) $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Stand-in for wp_strip_all_tags().
	 *
	 * @param string $text The text.
	 */
	function wp_strip_all_tags( $text ): string {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stand-in for sanitize_key().
	 *
	 * @param string $key The key.
	 */
	function sanitize_key( $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Stand-in for esc_url_raw().
	 *
	 * @param mixed $value The value.
	 * @return mixed
	 */
	function esc_url_raw( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	/**
	 * Stand-in for wp_attachment_is_image(): the tests have no media library.
	 *
	 * @param mixed $post The attachment.
	 */
	function wp_attachment_is_image( $post ): bool {
		return false;
	}
}
