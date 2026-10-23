<?php
/**
 * Automatic payment-email reminders for money still waiting.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cron;

use Ifthenpay\PayButton\Mail\PaymentEmailSender;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Once an unpaid payment is older than the chosen number of days, its customer gets one reminder email.
 * A Focus-mode tool: it only runs while the display mode is Focus and a delay is set. One reminder per payment,
 * ever, since every payment email stamps reminded_at.
 */
class ReminderCron {

	public const HOOK = 'iftp_btn_send_reminders';

	// A run stops taking new emails after this long, and handles at most this many; the next hour picks up the rest.
	private const TIME_BUDGET = 20;
	private const BATCH       = 50;

	/**
	 * Settings storage.
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
	 * Sets up the reminders.
	 *
	 * @param SettingsRepository|null $settings     Settings storage.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?TransactionStore $transactions = null ) {
		$this->settings     = $settings ?? new SettingsRepository();
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Hooks the run and makes sure it's scheduled.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( self::class, 'schedule' ) );
	}

	/**
	 * Schedules the hourly check, unless it already is.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Removes the scheduled check.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Sends the reminders that are due.
	 */
	public function run(): void {
		$days = $this->settings->get_remind_days();

		if ( $days <= 0 || 'focus' !== $this->settings->get_display_mode() || ! $this->settings->is_connected() ) {
			return;
		}

		$refs    = $this->transactions->due_for_reminder( time() - $days * DAY_IN_SECONDS, self::BATCH );
		$sender  = new PaymentEmailSender( $this->settings, $this->transactions );
		$started = microtime( true );

		$this->transactions->prime( $refs );

		foreach ( $refs as $ref ) {
			if ( microtime( true ) - $started > self::TIME_BUDGET ) {
				break;
			}

			$outcome = $sender->send( $this->transactions->get( $ref ) );

			// A row that can't be emailed (no link from ifthenpay, mail down) is stamped anyway, so it isn't retried every hour.
			if ( PaymentEmailSender::SENT !== $outcome ) {
				$this->transactions->mark_reminded( array( $ref ) );
			}
		}
	}
}
