<?php
/**
 * The money rules of a payment: what a payer can and can't change about the total.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Integration;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\Discounts;
use Ifthenpay\PayButton\Repository\CouponStore;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Error;

/**
 * Drives the private steps of CheckoutService that decide the total, since nothing else reaches them without a real gateway.
 */
final class CheckoutAmountTest extends TestCase {

	/**
	 * A button in the given amount mode.
	 *
	 * @param string               $mode  Button::MODE_*.
	 * @param array<string, mixed> $extra More config.
	 */
	private function button( string $mode, array $extra = array() ): Button {
		return new Button( 1, 'Test', 'test', true, array( 'amount_mode' => $mode ) + $extra );
	}

	/**
	 * Calls a private method of the service.
	 *
	 * @param string            $method The method.
	 * @param array<int, mixed> $args   Its arguments.
	 * @return mixed
	 */
	private function call( string $method, array $args ) {
		$step = new ReflectionMethod( CheckoutService::class, $method );
		$step->setAccessible( true );

		return $step->invokeArgs( new CheckoutService(), $args );
	}

	/**
	 * What read_link() returns for a link.
	 *
	 * @param bool  $signed Whether the link carried a good signature.
	 * @param float $amount The signed amount.
	 * @return array{reference: string, signed: bool, amount: float}
	 */
	private function link( bool $signed, float $amount = 0.0 ): array {
		return array(
			'reference' => '',
			'signed'    => $signed,
			'amount'    => $amount,
		);
	}

	/**
	 * What read_spot() returns for one booking.
	 *
	 * @return array{count: int, options: array<int, string>, extra: array<int, string>}
	 */
	private function one_spot(): array {
		return array(
			'count'   => 1,
			'options' => array(),
			'extra'   => array(),
		);
	}

	/**
	 * A "from the link" button must never take the amount the payer typed.
	 */
	public function test_link_mode_ignores_a_typed_amount_without_a_signature(): void {
		$result = $this->call( 'resolve_amount', array( $this->button( Button::MODE_LINK ), array( 'amount' => '1.00' ), $this->link( false ), $this->one_spot() ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'iftp_btn_no_amount', $result->get_error_code() );
	}

	/**
	 * A signed link is the one source of the total in that mode.
	 */
	public function test_link_mode_takes_the_signed_amount(): void {
		$result = $this->call( 'resolve_amount', array( $this->button( Button::MODE_LINK ), array( 'amount' => '1.00' ), $this->link( true, 900.0 ), $this->one_spot() ) );

		$this->assertSame( 900.0, $result );
	}

	/**
	 * A signature on an amount below the smallest payment doesn't open the typed-amount door either.
	 */
	public function test_link_mode_with_a_signed_zero_is_refused(): void {
		$result = $this->call( 'resolve_amount', array( $this->button( Button::MODE_LINK ), array( 'amount' => '50' ), $this->link( true, 0.0 ), $this->one_spot() ) );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * The payer-chosen mode still takes a typed amount, within its limits.
	 */
	public function test_payer_mode_keeps_taking_a_typed_amount_within_limits(): void {
		$button = $this->button(
			Button::MODE_PAYER,
			array(
				'min' => 5.0,
				'max' => 100.0,
			)
		);

		$this->assertSame( 20.5, $this->call( 'resolve_amount', array( $button, array( 'amount' => '20,50' ), $this->link( false ), $this->one_spot() ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->call( 'resolve_amount', array( $button, array( 'amount' => '4' ), $this->link( false ), $this->one_spot() ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->call( 'resolve_amount', array( $button, array( 'amount' => '101' ), $this->link( false ), $this->one_spot() ) ) );
	}

	/**
	 * A fixed button charges its own price, whatever the payer sends.
	 */
	public function test_fixed_mode_ignores_a_typed_amount(): void {
		$button = $this->button( Button::MODE_FIXED, array( 'amount' => 30.0 ) );

		$this->assertSame( 30.0, $this->call( 'resolve_amount', array( $button, array( 'amount' => '1' ), $this->link( false ), $this->one_spot() ) ) );
	}

	/**
	 * A signed total is for one booking: asking for more spots at that price is refused.
	 */
	public function test_a_signed_link_cannot_take_more_than_one_spot(): void {
		$button = $this->button( Button::MODE_FIXED, array( 'spots' => array( 'quantity' => true ) + Spots::defaults() ) );

		$signed = $this->call( 'read_spot', array( $button, array( 'quantity' => '3' ), $this->link( true, 50.0 ) ) );
		$one    = $this->call( 'read_spot', array( $button, array( 'quantity' => '1' ), $this->link( true, 50.0 ) ) );
		$open   = $this->call( 'read_spot', array( $button, array( 'quantity' => '3' ), $this->link( false ) ) );

		$this->assertInstanceOf( WP_Error::class, $signed );
		$this->assertSame( 'iftp_btn_bad_quantity', $signed->get_error_code() );
		$this->assertSame( 1, $one['count'] );
		$this->assertSame( 3, $open['count'] );
	}

	/**
	 * A use that couldn't be recorded must not let the code through.
	 */
	public function test_a_code_use_that_failed_to_record_is_not_granted(): void {
		$store = new class() extends CouponStore {
			/**
			 * Pretends the insert failed.
			 *
			 * @param int    $coupon_id Unused.
			 * @param string $ref       Unused.
			 * @param float  $discount  Unused.
			 */
			public function record_use( int $coupon_id, string $ref, float $discount ): bool {
				return false;
			}
		};

		$this->assertFalse( ( new Discounts( $store ) )->claim( $this->applied( 0 ), 'REF1' ) );
	}

	/**
	 * Two payers racing for the last use: the one who ends up over the limit gives up.
	 */
	public function test_the_use_over_the_limit_is_refused(): void {
		$over = new class() extends CouponStore {
			/**
			 * A use that was written.
			 *
			 * @param int    $coupon_id Unused.
			 * @param string $ref       Unused.
			 * @param float  $discount  Unused.
			 */
			public function record_use( int $coupon_id, string $ref, float $discount ): bool {
				return true;
			}

			/**
			 * Two payments hold the code.
			 *
			 * @param int $id Unused.
			 */
			public function used( int $id ): int {
				return 2;
			}
		};

		$this->assertFalse( ( new Discounts( $over ) )->claim( $this->applied( 1 ), 'REF2' ) );
		$this->assertTrue( ( new Discounts( $over ) )->claim( $this->applied( 0 ), 'REF3' ) );
		$this->assertTrue( ( new Discounts( $over ) )->claim( $this->applied( 2 ), 'REF4' ) );
	}

	/**
	 * What Discounts::apply() returns, for a code with the given use limit.
	 *
	 * @param int $max_uses The code's limit (0 = none).
	 * @return array<string, mixed>
	 */
	private function applied( int $max_uses ): array {
		return array(
			'coupon'   => array(
				'id'         => 7,
				'code'       => 'TEST',
				'type'       => CouponStore::TYPE_FIXED,
				'value'      => 5.0,
				'min_amount' => 0.0,
				'max_uses'   => $max_uses,
				'starts_at'  => 0,
				'expires_at' => 0,
				'button_ids' => array(),
				'active'     => true,
			),
			'discount' => 5.0,
			'total'    => 5.0,
		);
	}
}
