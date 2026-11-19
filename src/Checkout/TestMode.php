<?php
/**
 * Test payments: what the demo page signs so a payment made there is marked as a test.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The demo page prints a signed token for a saved button. If the checkout gets it back, the payment goes through
 * as normal but its reference starts with TEST-, so it can't be mistaken for a real one in the entries.
 * Only an admin ever sees the token, and it names one button and runs out.
 */
class TestMode {

	/**
	 * The form field the token travels in.
	 */
	public const FIELD = 'test_token';

	/**
	 * Start of a test payment's reference.
	 */
	public const PREFIX = 'TEST-';

	/**
	 * How long a token works, in seconds.
	 */
	private const LIFETIME = 6 * HOUR_IN_SECONDS;

	/**
	 * A token for a button: the time it runs out, then the signature.
	 *
	 * @param int $button_id The button's post ID.
	 */
	public static function token( int $button_id ): string {
		$expires = time() + self::LIFETIME;

		return $expires . '.' . self::sign( $button_id, $expires );
	}

	/**
	 * Whether a token is one I made for this button and hasn't run out.
	 *
	 * @param int    $button_id The button's post ID.
	 * @param string $token     What came back from the form.
	 */
	public static function verify( int $button_id, string $token ): bool {
		$parts = explode( '.', $token );

		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || (int) $parts[0] < time() ) {
			return false;
		}

		return hash_equals( self::sign( $button_id, (int) $parts[0] ), $parts[1] );
	}

	/**
	 * Signs a button and an expiry.
	 *
	 * @param int $button_id The button's post ID.
	 * @param int $expires   When the token runs out.
	 */
	private static function sign( int $button_id, int $expires ): string {
		return wp_hash( 'iftp_btn_test|' . $button_id . '|' . $expires );
	}
}
