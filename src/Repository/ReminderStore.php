<?php
/**
 * Which unpaid payments were already reminded.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

use Ifthenpay\PayButton\Checkout\TestMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the pending payments that are due a reminder and remembers the ones that got it, so each is reminded once.
 */
class ReminderStore {

	// A payment this young has only just been started; the payer is probably still on the way.
	public const MIN_AGE = 2 * HOUR_IN_SECONDS;

	/**
	 * Pending payments whose link runs out within the lead time, that have an email and a link, and weren't reminded.
	 * A row with no hold uses its created time plus the expiry days, like the expiry sweep does.
	 *
	 * @param int $now         Unix timestamp.
	 * @param int $lead_hours  How many hours before the end the reminder goes.
	 * @param int $expiry_days The site's expiry days, for rows with no hold.
	 * @param int $limit       The most to return in one run.
	 * @return array<int, string> Refs, soonest to run out first.
	 */
	public function due( int $now, int $lead_hours, int $expiry_days, int $limit = 100 ): array {
		global $wpdb;

		$then = $now + $lead_hours * HOUR_IN_SECONDS;
		$span = $expiry_days * DAY_IN_SECONDS;

		// The end is the hold, or for a row with none its created time plus the expiry days; written out each time so the query stays one literal.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own tables, read by a cron sweep.
		$refs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.ref FROM %i t LEFT JOIN %i r ON r.ref = t.ref WHERE r.ref IS NULL AND t.status = %s AND t.gateway_key <> %s AND t.customer_email <> '' AND t.payment_url <> '' AND t.ref NOT LIKE %s AND t.created_at <= %d AND IF(t.hold_until > 0, t.hold_until, t.created_at + %d) > %d AND IF(t.hold_until > 0, t.hold_until, t.created_at + %d) <= %d ORDER BY IF(t.hold_until > 0, t.hold_until, t.created_at + %d) ASC LIMIT %d",
				TransactionStore::table_name(),
				ExtrasSchema::reminders_table(),
				TransactionStore::STATUS_PENDING,
				TransactionStore::MANUAL_GATEWAY_KEY,
				$wpdb->esc_like( TestMode::PREFIX ) . '%',
				$now - self::MIN_AGE,
				$span,
				$now,
				$span,
				$then,
				$span,
				$limit
			)
		);

		return array_map( 'strval', is_array( $refs ) ? $refs : array() );
	}

	/**
	 * Marks a payment as reminded before the email goes, so two runs at once can't both send it.
	 *
	 * @param string $ref The payment's reference.
	 * @return bool False when another run already has it.
	 */
	public function claim( string $ref ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table; INSERT IGNORE is the lock.
		$added = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (ref, sent_at) VALUES (%s, %d)', ExtrasSchema::reminders_table(), $ref, time() ) );

		return 1 === $added;
	}

	/**
	 * Takes the mark back when the email failed, so the next run tries again.
	 *
	 * @param string $ref The payment's reference.
	 */
	public function release( string $ref ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		$wpdb->delete( ExtrasSchema::reminders_table(), array( 'ref' => $ref ) );
	}

	/**
	 * When a payment was reminded, or 0.
	 *
	 * @param string $ref The payment's reference.
	 */
	public function sent_at( string $ref ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own table.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT sent_at FROM %i WHERE ref = %s', ExtrasSchema::reminders_table(), $ref ) );
	}
}
