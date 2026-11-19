<?php
/**
 * Aggregate reads over the transactions table: per-button stats, method and day totals, top customers, money waiting.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Reports;

use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only, cached on the store's change token, so a write anywhere drops every report at once.
 */
class TransactionReports {

	/**
	 * Where the change token comes from.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $store;

	/**
	 * Sets up the reports.
	 *
	 * @param TransactionStore|null $store Transactions storage.
	 */
	public function __construct( ?TransactionStore $store = null ) {
		$this->store = $store ?? new TransactionStore();
	}

	/**
	 * Paid payments per button that started on or after a moment of its own: when its checkout opens began being counted.
	 *
	 * @param array<int, int> $since Button ID => Unix time.
	 * @return array<int, int> Keyed by button ID; buttons with none are left out.
	 */
	public function paid_since( array $since ): array {
		global $wpdb;

		$since = array_filter( array_map( 'intval', $since ) );

		if ( array() === $since ) {
			return array();
		}

		$cache_key = 'since_' . $this->store->last_changed() . '_' . md5( (string) wp_json_encode( $since ) );
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$where = implode( ' OR ', array_fill( 0, count( $since ), '(button_id = %d AND created_at >= %d)' ) );
		$args  = array( TransactionStore::table_name(), TransactionStore::STATUS_PAID );

		foreach ( $since as $id => $time ) {
			$args[] = (int) $id;
			$args[] = $time;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, cached below; $where is only fixed %d pairs, and the values go in as one array.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT button_id, COUNT(*) AS paid FROM %i WHERE status = %s AND ({$where}) GROUP BY button_id", $args ), ARRAY_A );
		$paid = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$paid[ (int) $row['button_id'] ] = (int) $row['paid'];
		}

		wp_cache_set( $cache_key, $paid, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $paid;
	}

	/**
	 * Paid count and amount per button, plus the count of every payment it started.
	 *
	 * @param array<int, int> $button_ids The buttons' post IDs.
	 * @return array<int, array{paid_count: int, paid_amount: float, count: int}> Keyed by button ID; buttons with no payments are left out.
	 */
	public function button_stats( array $button_ids ): array {
		global $wpdb;

		$button_ids = array_values( array_unique( array_filter( array_map( 'intval', $button_ids ) ) ) );

		if ( array() === $button_ids ) {
			return array();
		}

		$cache_key = 'buttons_' . $this->store->last_changed() . '_' . md5( implode( ',', $button_ids ) );
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $button_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, cached below; $placeholders is only %d markers, one per id, which the sniff can't count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT button_id, COUNT(*) AS total, SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS paid_count, SUM(CASE WHEN status = %s THEN amount ELSE 0 END) AS paid_amount FROM %i WHERE button_id IN ({$placeholders}) GROUP BY button_id", array_merge( array( TransactionStore::STATUS_PAID, TransactionStore::STATUS_PAID, TransactionStore::table_name() ), $button_ids ) ), ARRAY_A );

		$stats = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$stats[ (int) $row['button_id'] ] = array(
				'paid_count'  => (int) $row['paid_count'],
				'paid_amount' => round( (float) $row['paid_amount'], 2 ),
				'count'       => (int) $row['total'],
			);
		}

		wp_cache_set( $cache_key, $stats, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $stats;
	}

	/**
	 * The money still waiting: every pending payment's count and total.
	 *
	 * @return array{count: int, amount: float}
	 */
	public function waiting(): array {
		global $wpdb;

		$cache_key = 'waiting_' . $this->store->last_changed();
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			/**
			 * Only this method writes the key, so the shape is known.
			 *
			 * @var array{count: int, amount: float} $cached
			 */
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s', TransactionStore::table_name(), TransactionStore::STATUS_PENDING ), ARRAY_A );

		$waiting = array(
			'count'  => (int) ( $row['total'] ?? 0 ),
			'amount' => round( (float) ( $row['amount'] ?? 0 ), 2 ),
		);

		wp_cache_set( $cache_key, $waiting, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $waiting;
	}

	/**
	 * Paid count and amount per payment method, between two timestamps.
	 *
	 * @param int $from Unix timestamp, inclusive.
	 * @param int $to   Unix timestamp, exclusive.
	 * @return array<int, array{method: string, count: int, amount: float}>
	 */
	public function method_totals( int $from, int $to ): array {
		global $wpdb;

		$cache_key = 'methods_' . $this->store->last_changed() . '_' . $from . '_' . $to;
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT pay_method, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s AND created_at >= %d AND created_at < %d GROUP BY pay_method', TransactionStore::table_name(), TransactionStore::STATUS_PAID, $from, $to ), ARRAY_A );

		$totals = array_map(
			static fn( array $row ): array => array(
				'method' => (string) $row['pay_method'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $totals, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $totals;
	}

	/**
	 * The customers who paid the most between two timestamps, by email (or name when there's none).
	 *
	 * @param int $from  Unix timestamp, inclusive.
	 * @param int $to    Unix timestamp, exclusive.
	 * @param int $limit How many.
	 * @return array<int, array{name: string, email: string, count: int, amount: float}>
	 */
	public function top_customers( int $from, int $to, int $limit ): array {
		global $wpdb;

		$cache_key = 'top_' . $this->store->last_changed() . '_' . $from . '_' . $to . '_' . $limit;
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached below.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT MAX(customer_name) AS name, MAX(customer_email) AS email, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s AND created_at >= %d AND created_at < %d AND (customer_email <> '' OR customer_name <> '') GROUP BY IF(customer_email <> '', customer_email, customer_name) ORDER BY amount DESC LIMIT %d", TransactionStore::table_name(), TransactionStore::STATUS_PAID, $from, $to, $limit ), ARRAY_A );

		$top = array_map(
			static fn( array $row ): array => array(
				'name'   => (string) $row['name'],
				'email'  => (string) $row['email'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $top, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $top;
	}

	/**
	 * Count and amount per local day and status, for the charts. Days are counted in the site's timezone.
	 *
	 * @param int $from   Unix timestamp, inclusive.
	 * @param int $to     Unix timestamp, exclusive.
	 * @param int $offset The site's UTC offset in seconds, so a "day" is the site's day.
	 * @return array<int, array{day: int, status: string, count: int, amount: float}>
	 */
	public function daily_totals( int $from, int $to, int $offset ): array {
		global $wpdb;

		$cache_key = 'daily_' . $this->store->last_changed() . '_' . md5( $from . '|' . $to . '|' . $offset );
		$cached    = wp_cache_get( $cache_key, TransactionStore::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT FLOOR((created_at + %d) / 86400) AS day, status, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE created_at >= %d AND created_at < %d GROUP BY day, status', $offset, TransactionStore::table_name(), $from, $to ), ARRAY_A );

		$totals = array_map(
			static fn( array $row ): array => array(
				'day'    => (int) $row['day'],
				'status' => (string) $row['status'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $totals, TransactionStore::CACHE_GROUP, TransactionStore::LIST_CACHE_TTL );

		return $totals;
	}
}
