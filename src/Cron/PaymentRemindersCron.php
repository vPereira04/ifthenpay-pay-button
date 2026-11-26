<?php
/**
 * Hourly sweep that reminds payers whose payment link is about to run out.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cron;

use Ifthenpay\PayButton\Mail\PaymentReminderEmail;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\ReminderStore;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends each due payment one reminder. Hourly, because the lead time is in hours.
 */
class PaymentRemindersCron {

	public const HOOK = 'iftp_btn_send_reminders';

	// A run stops here, so a backlog (the switch just went on) spreads over a few hours instead of one burst of mail.
	private const PER_RUN = 50;

	/**
	 * Checkout settings, where the switch lives.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout;

	/**
	 * Gateway settings, for the expiry days.
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
	 * Reminders storage.
	 *
	 * @var ReminderStore
	 */
	private ReminderStore $reminders;

	/**
	 * Sets up the sweep.
	 *
	 * @param CheckoutSettings|null   $checkout     Checkout settings.
	 * @param SettingsRepository|null $settings     Gateway settings.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 * @param ReminderStore|null      $reminders    Reminders storage.
	 */
	public function __construct( ?CheckoutSettings $checkout = null, ?SettingsRepository $settings = null, ?TransactionStore $transactions = null, ?ReminderStore $reminders = null ) {
		$this->checkout     = $checkout ?? new CheckoutSettings();
		$this->settings     = $settings ?? new SettingsRepository();
		$this->transactions = $transactions ?? new TransactionStore();
		$this->reminders    = $reminders ?? new ReminderStore();
	}

	/**
	 * Hooks the sweep and makes sure it's scheduled.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'handle' ) );
		add_action( 'admin_init', array( self::class, 'schedule' ) );
	}

	/**
	 * Schedules the hourly sweep, unless it already is.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Removes the scheduled sweep.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * The scheduled event: runs the sweep and drops its tally, since an action returns nothing.
	 */
	public function handle(): void {
		$this->run();
	}

	/**
	 * Reminds every payment that is due, if reminders are on.
	 *
	 * @param bool $dry_run Count what would go out without sending or marking anything; ignores the switch, for the CLI.
	 * @return array{due: int, sent: int, failed: int}
	 */
	public function run( bool $dry_run = false ): array {
		$result = array(
			'due'    => 0,
			'sent'   => 0,
			'failed' => 0,
		);

		if ( ! $dry_run && ! $this->checkout->get_reminder_enabled() ) {
			return $result;
		}

		$days = $this->settings->get_expiry_days();
		$refs = $this->reminders->due( time(), $this->checkout->get_reminder_hours(), $days, self::PER_RUN );

		$result['due'] = count( $refs );

		if ( $dry_run ) {
			return $result;
		}

		foreach ( $refs as $ref ) {
			if ( ! $this->reminders->claim( $ref ) ) {
				continue;
			}

			$record = $this->transactions->get( $ref );

			// Checked again with the full row: it may have been paid since the list was read.
			if ( null === $record || TransactionStore::STATUS_PENDING !== $record['status'] || false === is_email( $record['customer_email'] ) ) {
				$this->reminders->release( $ref );
				continue;
			}

			$ends = $record['hold_until'] > 0 ? $record['hold_until'] : $record['created_at'] + $days * DAY_IN_SECONDS;

			if ( PaymentReminderEmail::send( $record, $ends ) ) {
				++$result['sent'];

				/**
				 * Fires after a payer was reminded about an unpaid payment.
				 *
				 * @param string $ref The transaction reference.
				 */
				do_action( 'iftp_btn_payment_reminded', $ref );
			} else {
				$this->reminders->release( $ref );
				++$result['failed'];
			}
		}

		return $result;
	}
}
