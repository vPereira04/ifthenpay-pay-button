<?php
/**
 * Payer return redirect handler.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Api;

use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the payer landing back from ifthenpay. ifthenpay only calls the webhook for paid payments,
 * so this redirect is my only signal for a decline or a cancel.
 *
 * I record the outcome, then send the payer back to the page the payment started on, carrying a signed
 * result ref. checkout.js picks that up there and shows the outcome popup (or the Paid redirect).
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class ReturnHandler {

	public const RESULT_VAR = 'iftp_btn_result';
	public const RESULT_SIG = 'iftp_btn_rsig';

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the handler.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?TransactionStore $transactions = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Hooks the return check.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ) );
	}

	/**
	 * Signs a result ref, so only a payer I sent back can ask for its status.
	 *
	 * @param string $ref The transaction reference.
	 */
	public static function result_signature( string $ref ): string {
		return wp_hash( 'iftp_btn_result|' . $ref );
	}

	/**
	 * Where a payer lands after ifthenpay: the page the payment started on, or the home page.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function result_url( array $record ): string {
		$home   = home_url( '/' );
		$source = '' !== $record['source_url'] ? wp_validate_redirect( $record['source_url'], $home ) : $home;
		$source = remove_query_arg( array( self::RESULT_VAR, self::RESULT_SIG ), $source );

		return add_query_arg(
			array(
				self::RESULT_VAR => rawurlencode( $record['ref'] ),
				self::RESULT_SIG => self::result_signature( $record['ref'] ),
			),
			$source
		);
	}

	/**
	 * Records a signed return, then redirects away from the URL that carries it.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- ifthenpay's redirect can't carry a nonce; the signature below authenticates it instead.
		if ( ! isset( $_GET[ PaymentLinks::RETURN_VAR ] ) ) {
			return;
		}

		$outcome   = sanitize_key( wp_unslash( $_GET[ PaymentLinks::RETURN_VAR ] ) );
		$ref       = sanitize_text_field( wp_unslash( $_GET[ PaymentLinks::RETURN_REF ] ?? '' ) );
		$signature = sanitize_text_field( wp_unslash( $_GET[ PaymentLinks::RETURN_SIG ] ?? '' ) );
		$txn_id    = sanitize_text_field( wp_unslash( $_GET[ PaymentLinks::RETURN_TXN ] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$record = '' !== $ref && hash_equals( PaymentLinks::return_signature( $ref, $outcome ), $signature ) ? $this->transactions->get( $ref ) : null;

		if ( null === $record ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$this->record_outcome( $record['ref'], $record['status'], $outcome, $record['transaction_id'], $txn_id );

		wp_safe_redirect( self::result_url( $record ) );
		exit;
	}

	/**
	 * A later webhook still wins: the mark_*() calls never touch a paid row.
	 *
	 * @param string $ref        The transaction reference.
	 * @param string $status     The row's current status.
	 * @param string $outcome    'success', 'error' or 'cancel'.
	 * @param string $stored_txn The transaction id already on the row.
	 * @param string $txn_id     The transaction id ifthenpay put on a success return, or ''.
	 */
	private function record_outcome( string $ref, string $status, string $outcome, string $stored_txn, string $txn_id ): void {
		// The placeholder comes back unreplaced when ifthenpay has no id to give.
		if ( 'success' === $outcome && '' !== $txn_id && '[TRANSACTIONID]' !== $txn_id && '' === $stored_txn ) {
			$this->transactions->record_transaction_id( $ref, $txn_id );
		}

		if ( TransactionStore::STATUS_PENDING !== $status ) {
			return;
		}

		if ( 'error' === $outcome && $this->transactions->mark_failed( $ref ) ) {
			/**
			 * Fires when a payer comes back from a declined payment.
			 *
			 * @param string $ref The transaction reference.
			 */
			do_action( 'iftp_btn_payment_failed', $ref );
		} elseif ( 'cancel' === $outcome && $this->transactions->mark_cancelled( $ref ) ) {
			/**
			 * Fires when a payer cancels on the ifthenpay page.
			 *
			 * @param string $ref The transaction reference.
			 */
			do_action( 'iftp_btn_payment_cancelled', $ref );
		}
	}
}
