<?php
/**
 * A button's outgoing webhook.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Bridge;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tells another system about a button's payments: Zapier, Make, n8n, or the plugin the link came from.
 * Signed like Stripe's: `X-Iftp-Signature: t={time},v1={hmac_sha256(time.body, secret)}`.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class OutgoingWebhook {

	public const RETRY_HOOK = 'iftp_btn_retry_webhook';

	// Minutes to wait before each retry; the first send is attempt 0.
	private const RETRY_DELAYS = array( 5, 30, 180 );

	private const TIMEOUT = 8;

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the sender.
	 *
	 * @param ButtonRepository|null $buttons      Buttons storage.
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?ButtonRepository $buttons = null, ?TransactionStore $transactions = null ) {
		$this->buttons      = $buttons ?? new ButtonRepository();
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Hooks the payment events and the retry.
	 */
	public function register(): void {
		add_action( 'iftp_btn_payment_paid', array( $this, 'on_paid' ) );
		add_action( 'iftp_btn_payment_failed', array( $this, 'on_failed' ) );
		add_action( 'iftp_btn_payment_cancelled', array( $this, 'on_cancelled' ) );
		add_action( self::RETRY_HOOK, array( $this, 'retry' ), 10, 3 );
	}

	/**
	 * Sends payment.paid.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function on_paid( string $ref ): void {
		$this->dispatch( $ref, 'payment.paid', 0 );
	}

	/**
	 * Sends payment.failed.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function on_failed( string $ref ): void {
		$this->dispatch( $ref, 'payment.failed', 0 );
	}

	/**
	 * Sends payment.cancelled.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function on_cancelled( string $ref ): void {
		$this->dispatch( $ref, 'payment.cancelled', 0 );
	}

	/**
	 * A scheduled retry.
	 *
	 * @param string $ref     The transaction reference.
	 * @param string $event   The event name.
	 * @param int    $attempt Which attempt this is.
	 */
	public function retry( $ref, $event, $attempt ): void {
		$this->dispatch( (string) $ref, (string) $event, (int) $attempt );
	}

	/**
	 * Sends a `ping` with sample data, for the editor's test button.
	 *
	 * @param Button $button The button.
	 * @return array{ok: bool, code: int, message: string}
	 */
	public function send_test( Button $button ): array {
		$sample = array(
			'event'     => 'ping',
			'ref'       => 'IFTP_TEST000000',
			'status'    => TransactionStore::STATUS_PAID,
			'amount'    => number_format( Button::MODE_FIXED === $button->mode() ? $button->config['amount'] : 10.0, 2, '.', '' ),
			'currency'  => 'EUR',
			'method'    => 'MBWAY',
			'reference' => 'ORDER-123',
			'button'    => array(
				'id'    => $button->id,
				'title' => $button->title,
			),
			'payer'     => array(
				'name'  => 'Ana Silva',
				'email' => 'ana@example.com',
			),
			'created'   => gmdate( 'c' ),
			'updated'   => gmdate( 'c' ),
			'test'      => true,
		);

		return $this->post( $button, $sample );
	}

	/**
	 * Builds and sends one event for a row, and schedules a retry when the other side didn't take it.
	 *
	 * @param string $ref     The transaction reference.
	 * @param string $event   The event name.
	 * @param int    $attempt Which attempt this is.
	 */
	private function dispatch( string $ref, string $event, int $attempt ): void {
		$record = $this->transactions->get( $ref );
		$button = null !== $record && $record['button_id'] > 0 ? $this->buttons->find( $record['button_id'] ) : null;

		if ( null === $record || null === $button || '' === $button->config['webhook_url'] ) {
			return;
		}

		$result = $this->post( $button, $this->payload( $record, $button, $event ) );

		if ( $result['ok'] || ! isset( self::RETRY_DELAYS[ $attempt ] ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::RETRY_DELAYS[ $attempt ] * MINUTE_IN_SECONDS, self::RETRY_HOOK, array( $ref, $event, $attempt + 1 ) );
	}

	/**
	 * The JSON body for one event.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @param Button               $button The button.
	 * @param string               $event  The event name.
	 * @phpstan-param Transaction $record
	 * @return array<string, mixed>
	 */
	private function payload( array $record, Button $button, string $event ): array {
		$snapshot = TransactionStore::decode_snapshot( $record['field_snapshot'] );
		$value    = static fn( string $key ): string => is_array( $snapshot[ $key ] ?? null ) && is_scalar( $snapshot[ $key ]['value'] ?? null ) ? (string) $snapshot[ $key ]['value'] : '';
		$payer    = array_filter(
			array(
				'name'  => $record['customer_name'],
				'email' => $record['customer_email'],
				'phone' => $value( 'phone' ),
				'nif'   => $value( 'nif' ),
				'note'  => $value( 'note' ),
			),
			static fn( string $field ): bool => '' !== $field
		);

		$fields = array();

		foreach ( MergeTags::values( $record, $button ) as $key => $answer ) {
			if ( 0 === strpos( $key, 'field:' ) && '' !== $answer ) {
				$fields[ substr( $key, 6 ) ] = $answer;
			}
		}

		return array(
			'event'     => $event,
			'ref'       => $record['ref'],
			'status'    => $record['status'],
			'amount'    => number_format( $record['amount'], 2, '.', '' ),
			'currency'  => 'EUR',
			'quantity'  => max( 1, $record['spots'] ),
			'method'    => $record['pay_method'],
			'reference' => $record['external_ref'],
			'button'    => array(
				'id'    => $button->id,
				'title' => $button->title,
			),
			'payer'     => (object) $payer,
			'fields'    => (object) $fields,
			'created'   => gmdate( 'c', $record['created_at'] ),
			'updated'   => gmdate( 'c', $record['updated_at'] ),
		);
	}

	/**
	 * Signs and posts a body. wp_safe_remote_post() refuses local and private addresses.
	 *
	 * @param Button               $button The button, for its URL and secret.
	 * @param array<string, mixed> $body   The event.
	 * @return array{ok: bool, code: int, message: string}
	 */
	private function post( Button $button, array $body ): array {
		$json      = (string) wp_json_encode( $body );
		$timestamp = (string) time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $json, $button->config['webhook_secret'] );

		$response = wp_safe_remote_post(
			$button->config['webhook_url'],
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'     => 'application/json',
					'User-Agent'       => 'ifthenpay-pay-button/' . IFTP_BTN_VERSION . '; ' . home_url( '/' ),
					'X-Iftp-Event'     => is_string( $body['event'] ?? null ) ? $body['event'] : '',
					'X-Iftp-Signature' => 't=' . $timestamp . ',v1=' . $signature,
				),
				'body'        => $json,
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a failed delivery is worth a line in the log for whoever debugs the other side.
			error_log( 'ifthenpay Pay Button: webhook to ' . $button->config['webhook_url'] . ' failed - ' . $response->get_error_message() );

			return array(
				'ok'      => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'ok'      => $code >= 200 && $code < 300,
			'code'    => $code,
			'message' => (string) wp_remote_retrieve_response_message( $response ),
		);
	}
}
