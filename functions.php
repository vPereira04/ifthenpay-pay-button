<?php
/**
 * Public helpers for developers.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'iftp_btn_payment_url' ) ) {
	/**
	 * A signed pay link for a button: its amount and reference can't be edited, and it can expire.
	 *
	 * @param int                  $button_id The button's ID (the shortcode's id).
	 * @param array<string, mixed> $args      Any of: amount, reference, expires (Unix time), payer_name, email, phone, nif, note.
	 * @return string The link, or '' when there's no such button.
	 */
	function iftp_btn_payment_url( int $button_id, array $args = array() ): string {
		$button = ( new \Ifthenpay\PayButton\Buttons\ButtonRepository() )->find( $button_id );

		if ( isset( $args['name'] ) && ! isset( $args['payer_name'] ) ) {
			$args['payer_name'] = $args['name'];
		}

		return null !== $button ? ( new \Ifthenpay\PayButton\Bridge\PayLinks() )->signed_url( $button, $args ) : '';
	}
}
