<?php
/**
 * The Monday summary email.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cron;

use Ifthenpay\PayButton\Mail\WeeklySummaryEmail;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every Monday at 08:00 (site time) the summary goes to its recipient, when it's turned on.
 */
class WeeklySummaryCron {

	public const HOOK = 'iftp_btn_weekly_summary';

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Sets up the summary.
	 *
	 * @param SettingsRepository|null $settings Settings storage.
	 */
	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/**
	 * Hooks the run and makes sure it's scheduled.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( self::class, 'schedule' ) );
	}

	/**
	 * Schedules it for next Monday 08:00 in the site's timezone, then weekly, unless it already is.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		$monday = current_datetime()->modify( 'next monday' )->setTime( 8, 0 );

		wp_schedule_event( $monday->getTimestamp(), 'weekly', self::HOOK );
	}

	/**
	 * Removes the schedule.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Sends this week's summary, when it's on.
	 */
	public function run(): void {
		if ( $this->settings->get_summary_enabled() && $this->settings->is_connected() ) {
			( new WeeklySummaryEmail() )->send( $this->settings->get_summary_email() );
		}
	}
}
