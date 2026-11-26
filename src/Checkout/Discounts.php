<?php
/**
 * Works out what a discount code takes off a payment.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Repository\CouponStore;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a code is judged, for the live "Apply" and for the payment itself, so they can't disagree.
 *
 * @phpstan-import-type Coupon from CouponStore
 */
class Discounts {

	/**
	 * Codes storage.
	 *
	 * @var CouponStore
	 */
	private CouponStore $coupons;

	/**
	 * Sets up the checker.
	 *
	 * @param CouponStore|null $coupons Codes storage.
	 */
	public function __construct( ?CouponStore $coupons = null ) {
		$this->coupons = $coupons ?? new CouponStore();
	}

	/**
	 * Whether a button's checkout should show the code box.
	 *
	 * @param Button $button The button.
	 */
	public function offered( Button $button ): bool {
		return $this->coupons->offered_on( $button->id );
	}

	/**
	 * Judges a code against a payment's total.
	 *
	 * @param string $code     What the payer typed.
	 * @param Button $button   The button being paid.
	 * @param float  $subtotal The total before the code.
	 * @return array{coupon: Coupon, discount: float, total: float}|WP_Error
	 */
	public function apply( string $code, Button $button, float $subtotal ) {
		$invalid = new WP_Error( 'iftp_btn_code_invalid', CheckoutTexts::get( 'code_invalid' ), array( 'status' => 400 ) );
		$coupon  = $this->coupons->find_by_code( $code );

		if ( null === $coupon || ! $coupon['active'] ) {
			return $invalid;
		}

		if ( array() !== $coupon['button_ids'] && ! in_array( $button->id, $coupon['button_ids'], true ) ) {
			return $invalid;
		}

		$now = time();

		if ( $coupon['starts_at'] > $now ) {
			return $invalid;
		}

		if ( $coupon['expires_at'] > 0 && $coupon['expires_at'] < $now ) {
			return new WP_Error( 'iftp_btn_code_expired', CheckoutTexts::get( 'code_expired' ), array( 'status' => 400 ) );
		}

		if ( $subtotal < $coupon['min_amount'] ) {
			return new WP_Error( 'iftp_btn_code_minimum', CheckoutTexts::get( 'code_minimum', array( 'min' => CheckoutService::format_money( $coupon['min_amount'] ) ) ), array( 'status' => 400 ) );
		}

		if ( $coupon['max_uses'] > 0 && $this->coupons->used( $coupon['id'] ) >= $coupon['max_uses'] ) {
			return new WP_Error( 'iftp_btn_code_used_up', CheckoutTexts::get( 'code_used_up' ), array( 'status' => 400 ) );
		}

		$discount = CouponStore::TYPE_PERCENT === $coupon['type'] ? round( $subtotal * $coupon['value'] / 100, 2 ) : $coupon['value'];
		// ifthenpay won't take less than the smallest amount, so a code can't go below it.
		$discount = round( min( $discount, $subtotal - Button::MIN_AMOUNT ), 2 );

		if ( $discount <= 0 ) {
			return $invalid;
		}

		return array(
			'coupon'   => $coupon,
			'discount' => $discount,
			'total'    => round( $subtotal - $discount, 2 ),
		);
	}

	/**
	 * Records a payment's use of a code and, if that was one use too many (two payers racing for the last), says so.
	 *
	 * @param array{coupon: Coupon, discount: float, total: float} $applied What apply() returned.
	 * @param string                                               $ref     The payment's reference.
	 * @return bool False when the code ran out in the meantime.
	 */
	public function claim( array $applied, string $ref ): bool {
		$coupon = $applied['coupon'];

		// A use that couldn't be written would never count, so the code would be free to use again and again.
		if ( ! $this->coupons->record_use( $coupon['id'], $ref, $applied['discount'] ) ) {
			return false;
		}

		return 0 === $coupon['max_uses'] || $this->coupons->used( $coupon['id'] ) <= $coupon['max_uses'];
	}
}
