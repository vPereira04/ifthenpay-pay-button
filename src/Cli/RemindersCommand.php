<?php
/**
 * WP-CLI: payment reminders.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cli;

use Ifthenpay\PayButton\Cron\PaymentRemindersCron;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the reminder sweep now instead of waiting for the hour.
 */
class RemindersCommand {

	/**
	 * Sends the reminders that are due.
	 *
	 * Honours the lead time from Configuration > Emails. Without --dry-run it only sends when reminders are switched on.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Count what would go out, send nothing and mark nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp iftp-btn reminders send --dry-run
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function send( array $args, array $assoc_args ): void {
		unset( $args );

		$dry    = isset( $assoc_args['dry-run'] );
		$result = ( new PaymentRemindersCron() )->run( $dry );

		if ( $dry ) {
			WP_CLI::success( sprintf( '%d payments are due a reminder.', $result['due'] ) );

			return;
		}

		if ( 0 === $result['due'] && ! ( new \Ifthenpay\PayButton\Repository\CheckoutSettings() )->get_reminder_enabled() ) {
			WP_CLI::warning( 'Reminders are switched off in Configuration > Emails, so nothing was sent.' );

			return;
		}

		WP_CLI::success( sprintf( 'Sent %d of %d reminders; %d failed.', $result['sent'], $result['due'], $result['failed'] ) );
	}
}
