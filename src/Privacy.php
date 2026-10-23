<?php
/**
 * WordPress personal data export and erase.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton;

use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks the payments table into Tools › Export/Erase Personal Data, and suggests privacy policy text.
 * Erasing keeps the payment (amount, status, dates) as accounting history and drops who paid it.
 */
class Privacy {

	private const PAGE_SIZE = 50;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the hooks' owner.
	 *
	 * @param TransactionStore $transactions Transactions storage.
	 */
	public function __construct( TransactionStore $transactions ) {
		$this->transactions = $transactions;
	}

	/**
	 * Hooks the exporter, the eraser and the policy text.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'admin_init', array( $this, 'add_policy_text' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array<string, mixed> $exporters The registered exporters.
	 * @return array<string, mixed>
	 */
	public function add_exporter( array $exporters ): array {
		$exporters['ifthenpay-pay-button'] = array(
			'exporter_friendly_name' => __( 'ifthenpay payments', 'ifthenpay-pay-button' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array<string, mixed> $erasers The registered erasers.
	 * @return array<string, mixed>
	 */
	public function add_eraser( array $erasers ): array {
		$erasers['ifthenpay-pay-button'] = array(
			'eraser_friendly_name' => __( 'ifthenpay payments', 'ifthenpay-pay-button' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * One page of a payer's payments, for the export.
	 *
	 * @param string $email The payer's email.
	 * @param int    $page  The page, from 1.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$rows = $this->transactions->for_email( $email, self::PAGE_SIZE, ( max( 1, $page ) - 1 ) * self::PAGE_SIZE );
		$data = array();

		foreach ( $rows as $record ) {
			$items = array(
				array(
					'name'  => __( 'Reference', 'ifthenpay-pay-button' ),
					'value' => $record['ref'],
				),
				array(
					'name'  => __( 'Amount', 'ifthenpay-pay-button' ),
					'value' => number_format( $record['amount'], 2, '.', '' ) . ' EUR',
				),
				array(
					'name'  => __( 'Status', 'ifthenpay-pay-button' ),
					'value' => $record['status'],
				),
				array(
					'name'  => __( 'Created', 'ifthenpay-pay-button' ),
					'value' => Dates::local( 'Y-m-d H:i:s', $record['created_at'] ),
				),
			);

			foreach ( $this->transactions->entry_data_pairs( $record ) as $pair ) {
				$items[] = array(
					'name'  => $pair['label'],
					'value' => $pair['value'],
				);
			}

			$data[] = array(
				'group_id'    => 'ifthenpay-pay-button',
				'group_label' => __( 'ifthenpay payments', 'ifthenpay-pay-button' ),
				'item_id'     => 'iftp-btn-' . $record['ref'],
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Strips one page of a payer's payments. Always page 1, since erased rows no longer match the email.
	 *
	 * @param string $email The payer's email.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email ): array {
		$rows    = $this->transactions->for_email( $email, self::PAGE_SIZE, 0 );
		$removed = false;

		foreach ( $rows as $record ) {
			$removed = $this->transactions->anonymize( $record['ref'] ) || $removed;
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => array() !== $rows,
			'messages'       => array() !== $rows ? array( __( 'ifthenpay payments were kept as accounting records, without the payer\'s details.', 'ifthenpay-pay-button' ) ) : array(),
			'done'           => count( $rows ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Suggests privacy policy text under Settings › Privacy.
	 */
	public function add_policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$text = '<p>' . esc_html__( 'When you pay through an ifthenpay pay button, we store the details the button asks for (such as your name, email, phone, tax number and note), the amount, the payment method and the page you paid from, to record and confirm your payment.', 'ifthenpay-pay-button' ) . '</p>'
			. '<p>' . esc_html__( 'The payment itself happens on ifthenpay\'s own page. We send ifthenpay the amount, a reference and a description; your card or bank details never reach this site.', 'ifthenpay-pay-button' ) . '</p>';

		wp_add_privacy_policy_content( __( 'ifthenpay Pay Button', 'ifthenpay-pay-button' ), wp_kses_post( $text ) );
	}
}
