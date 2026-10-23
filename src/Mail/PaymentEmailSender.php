<?php
/**
 * Sends a pending payment its payment email, creating the link first when needed.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Mail;

use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One path for every payment email: "Send payment email", "Remind", "Remind all" and the automatic reminders.
 * Each send stamps the row's reminded_at, so the automatic reminder never doubles a manual one.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class PaymentEmailSender {

	public const SENT        = 'sent';
	public const INELIGIBLE  = 'ineligible';
	public const NO_LINK     = 'no_link';
	public const MAIL_FAILED = 'mail_failed';

	/**
	 * Settings storage, where the template lives.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Pay by Link creation.
	 *
	 * @var PaymentLinks
	 */
	private PaymentLinks $links;

	/**
	 * Sets up the sender.
	 *
	 * @param SettingsRepository|null $settings     Settings storage.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 * @param PaymentLinks|null       $links        Pay by Link creation.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?TransactionStore $transactions = null, ?PaymentLinks $links = null ) {
		$this->settings     = $settings ?? new SettingsRepository();
		$this->transactions = $transactions ?? new TransactionStore();
		$this->links        = $links ?? new PaymentLinks( $this->settings, null, $this->transactions );
	}

	/**
	 * Whether a row is a pending ifthenpay payment with a valid customer email to send its link to.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function can_email( array $record ): bool {
		return TransactionStore::STATUS_PENDING === $record['status']
			&& TransactionStore::MANUAL_GATEWAY_KEY !== $record['gateway_key']
			&& false !== is_email( $record['customer_email'] );
	}

	/**
	 * Emails a pending payment its link, creating the link first if it never got one.
	 * Eligibility is checked here, at send time, since the row may have been paid since the page loaded.
	 *
	 * @param array<string, mixed>|null $record The transaction row, or null when the ref is gone.
	 * @phpstan-param Transaction|null $record
	 * @return string One of the outcome constants.
	 */
	public function send( ?array $record ): string {
		if ( null === $record || ! self::can_email( $record ) ) {
			return self::INELIGIBLE;
		}

		$payment_url = '' !== $record['payment_url'] ? $record['payment_url'] : $this->links->create_link_for( $record );

		if ( '' === $payment_url ) {
			return self::NO_LINK;
		}

		$sent = PaymentRequestEmail::send(
			array(
				'ref'            => $record['ref'],
				'amount'         => $record['amount'],
				'payment_url'    => $payment_url,
				'customer_name'  => $record['customer_name'],
				'customer_email' => $record['customer_email'],
			),
			$this->transactions->entry_data_pairs( $record ),
			$this->settings
		);

		if ( ! $sent ) {
			return self::MAIL_FAILED;
		}

		$this->transactions->mark_reminded( array( $record['ref'] ) );

		return self::SENT;
	}
}
