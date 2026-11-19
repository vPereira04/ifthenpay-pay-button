<?php
/**
 * The webhook's anti-phishing and amount checks.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Api\Webhook\WebhookPayload;
use Ifthenpay\PayButton\Api\Webhook\WebhookValidator;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;
use PHPUnit\Framework\TestCase;

/**
 * Money moves on this check, so each way it can say no gets its own case.
 */
final class WebhookValidatorTest extends TestCase {

	/**
	 * Clears what the test set up.
	 */
	protected function tearDown(): void {
		$_GET = array();
	}

	/**
	 * A payload as ifthenpay's callback would send it.
	 *
	 * @param string $apk    The anti-phishing key.
	 * @param string $amount The amount.
	 */
	private function payload( string $apk, string $amount ): WebhookPayload {
		$_GET = array(
			'apk' => $apk,
			'val' => $amount,
			'mtd' => 'MBWAY',
			'req' => 'REQ1',
		);

		return WebhookPayload::from_request( 'IFTP_ABC' );
	}

	/**
	 * A validator whose settings hold the given key for every Gateway Key.
	 *
	 * @param string $stored_key The stored anti-phishing key, or '' for none.
	 */
	private function validator( string $stored_key ): WebhookValidator {
		$settings = $this->createMock( SettingsRepository::class );
		$settings->method( 'get_anti_phishing_key' )->willReturn( $stored_key );

		return new WebhookValidator( $settings );
	}

	/**
	 * A stored row.
	 *
	 * @param string $gateway_key The row's Gateway Key.
	 * @param float  $amount      The row's amount.
	 * @return array<string, mixed>
	 */
	private function record( string $gateway_key = 'AAAA-000000', float $amount = 25.0 ): array {
		return array(
			'gateway_key' => $gateway_key,
			'amount'      => $amount,
		);
	}

	/**
	 * Accepts a matching key and amount.
	 */
	public function test_accepts_a_matching_key_and_amount(): void {
		$this->assertTrue( $this->validator( 'secret' )->is_valid( $this->payload( 'secret', '25.00' ), $this->record() ) );
	}

	/**
	 * Accepts an amount within a cent.
	 */
	public function test_accepts_an_amount_within_a_cent(): void {
		$this->assertTrue( $this->validator( 'secret' )->is_valid( $this->payload( 'secret', '25.01' ), $this->record() ) );
	}

	/**
	 * Rejects an amount more than a cent off.
	 */
	public function test_rejects_an_amount_more_than_a_cent_off(): void {
		$this->assertFalse( $this->validator( 'secret' )->is_valid( $this->payload( 'secret', '24.50' ), $this->record() ) );
	}

	/**
	 * Rejects a wrong key.
	 */
	public function test_rejects_a_wrong_key(): void {
		$this->assertFalse( $this->validator( 'secret' )->is_valid( $this->payload( 'guess', '25.00' ), $this->record() ) );
	}

	/**
	 * Rejects a missing key in the request.
	 */
	public function test_rejects_a_missing_key_in_the_request(): void {
		$this->assertFalse( $this->validator( 'secret' )->is_valid( $this->payload( '', '25.00' ), $this->record() ) );
	}

	/**
	 * Rejects when no key is stored.
	 */
	public function test_rejects_when_no_key_is_stored(): void {
		$this->assertFalse( $this->validator( '' )->is_valid( $this->payload( '', '25.00' ), $this->record() ) );
	}

	/**
	 * Rejects a row without a gateway key.
	 */
	public function test_rejects_a_row_without_a_gateway_key(): void {
		$this->assertFalse( $this->validator( 'secret' )->is_valid( $this->payload( 'secret', '25.00' ), $this->record( '' ) ) );
	}

	/**
	 * Rejects a row recorded by hand.
	 */
	public function test_rejects_a_row_recorded_by_hand(): void {
		$this->assertFalse( $this->validator( 'secret' )->is_valid( $this->payload( 'secret', '25.00' ), $this->record( TransactionStore::MANUAL_GATEWAY_KEY ) ) );
	}
}
