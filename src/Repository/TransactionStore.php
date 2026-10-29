<?php
/**
 * Custom transactions table storage.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every ifthenpay payment a button started, or one recorded by hand. There's no host plugin, so this table is the only record.
 *
 * @phpstan-type Transaction array{ref: string, gateway_key: string, button_id: int, external_ref: string, source_url: string, amount: float, pay_method: string, request_id: string, transaction_id: string, payment_url: string, customer_name: string, customer_email: string, status: string, field_snapshot: string, created_at: int, updated_at: int, spots: int, spot_option: string, hold_until: int}
 */
class TransactionStore {

	public const STATUS_PENDING   = 'pending';
	public const STATUS_PAID      = 'paid';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_EXPIRED   = 'expired';

	public const ALL_STATUSES = array(
		self::STATUS_PENDING,
		self::STATUS_PAID,
		self::STATUS_FAILED,
		self::STATUS_CANCELLED,
		self::STATUS_EXPIRED,
	);

	/**
	 * Stamped on rows recorded by hand, which never go through ifthenpay.
	 */
	public const MANUAL_GATEWAY_KEY = 'manual';
	public const AD_HOC_METHOD      = 'Ad Hoc';

	/**
	 * Allow-list for `query()`'s orderby, since a column name can't go through prepare().
	 */
	public const SORTABLE_COLUMNS = array( 'ref', 'customer_name', 'amount', 'status', 'created_at' );

	private const EXTERNAL_REF_MAX_LENGTH = 191;
	private const SOURCE_URL_MAX_LENGTH   = 500;

	private const CUSTOMER_MAX_LENGTH = 191;

	// The list and CSV never show the snapshot, so I leave the LONGTEXT out of their reads.
	private const LIST_COLUMNS = 't.ref, t.gateway_key, t.button_id, t.external_ref, t.source_url, t.amount, t.pay_method, t.request_id, t.transaction_id, t.payment_url, t.customer_name, t.customer_email, t.status, t.created_at, t.updated_at, t.spots, t.spot_option, t.hold_until';

	private const CACHE_GROUP     = 'iftp_btn';
	private const ENTRY_CACHE_TTL = 600;
	private const LIST_CACHE_TTL  = 30;

	/**
	 * The custom table name, including the site's table prefix.
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'iftp_btn_transactions';
	}

	/**
	 * Creates or upgrades the table; a no-op once the stored schema version is current.
	 */
	public static function maybe_create_table(): void {
		if ( version_compare( (string) get_option( 'iftp_btn_db_version', '0' ), IFTP_BTN_DB_VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$table} (
            ref VARCHAR(191) NOT NULL,
            gateway_key VARCHAR(100) NOT NULL DEFAULT '',
            button_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            external_ref VARCHAR(191) NOT NULL DEFAULT '',
            source_url VARCHAR(500) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            pay_method VARCHAR(50) NOT NULL DEFAULT '',
            request_id VARCHAR(100) NOT NULL DEFAULT '',
            transaction_id VARCHAR(100) NOT NULL DEFAULT '',
            payment_url VARCHAR(500) NOT NULL DEFAULT '',
            customer_name VARCHAR(191) NOT NULL DEFAULT '',
            customer_email VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            field_snapshot LONGTEXT NULL,
            created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            spots SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            spot_option TEXT NULL,
            hold_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (ref),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_created_at (status,created_at),
            KEY pay_method (pay_method),
            KEY button_status (button_id,status),
            KEY external_ref (external_ref),
            KEY customer_name (customer_name)
        ) {$charset_collate};"
		);

		// Payments from before spots each took one, so a button that turns on a limit counts them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade on the plugin's own table.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET spots = 1 WHERE button_id > 0 AND spots = 0', $table ) );
		wp_cache_set( 'last_changed', microtime(), self::CACHE_GROUP );

		// Autoloaded, since boot() reads it on every request.
		update_option( 'iftp_btn_db_version', IFTP_BTN_DB_VERSION, true );
	}

	/**
	 * Inserts a pending payment that goes through ifthenpay. The link comes after, with record_payment_link().
	 *
	 * @param string $customer_name  The customer's name, or ''.
	 * @param string $customer_email The customer's email, or ''.
	 * @param float  $amount         The payment amount.
	 * @param string $gateway_key    The Gateway Key the link is created under.
	 * @return string|null The generated ref, or null if the insert failed.
	 */
	public function create_pending( string $customer_name, string $customer_email, float $amount, string $gateway_key ): ?string {
		$ref = $this->unique_ref( 'IFTP_', 10 );

		$inserted = $this->insert(
			array(
				'ref'            => $ref,
				'gateway_key'    => $gateway_key,
				'amount'         => $amount,
				'status'         => self::STATUS_PENDING,
				'field_snapshot' => self::customer_snapshot( $customer_name, $customer_email ),
			)
		);

		return $inserted ? $ref : null;
	}

	/**
	 * Inserts a pending payment a button started. The link comes after, with record_payment_link().
	 *
	 * @param int                                                             $button_id    The button's post ID.
	 * @param float                                                           $amount       The payment amount.
	 * @param string                                                          $gateway_key  The Gateway Key the link is created under.
	 * @param array<string, array{label: string, value: string}>              $snapshot     What the payer filled in, keyed by field.
	 * @param string                                                          $external_ref The reference another plugin passed in the link, or ''.
	 * @param string                                                          $source_url   The page the payment started on.
	 * @param array{count: int, options: array<int, string>, hold_until: int} $spot The spots it takes on each option, the options, and until when an unpaid one keeps them.
	 * @return string|null The generated ref, or null if the insert failed.
	 */
	public function create_for_button( int $button_id, float $amount, string $gateway_key, array $snapshot, string $external_ref, string $source_url, array $spot ): ?string {
		$ref = $this->unique_ref( 'IFTP_', 10 );

		$inserted = $this->insert(
			array(
				'ref'            => $ref,
				'gateway_key'    => $gateway_key,
				'button_id'      => $button_id,
				'external_ref'   => $external_ref,
				'source_url'     => $source_url,
				'amount'         => $amount,
				'status'         => self::STATUS_PENDING,
				'field_snapshot' => $snapshot,
				'spots'          => $spot['count'],
				'spot_option'    => implode( "\n", $spot['options'] ),
				'hold_until'     => $spot['hold_until'],
			)
		);

		return $inserted ? $ref : null;
	}

	/**
	 * Spots each button has taken: paid ones, plus unpaid ones whose link can still be paid. Cached until the next
	 * write or until the first of those holds runs out, whichever comes first, so the checkout never sees a stale count.
	 *
	 * @param array<int, int> $button_ids The buttons' post IDs.
	 * @return array<int, array{total: int, options: array<string, int>}> Keyed by button ID; buttons with none are left out.
	 */
	public function spots_taken( array $button_ids ): array {
		global $wpdb;

		$button_ids = array_values( array_unique( array_filter( array_map( 'intval', $button_ids ) ) ) );

		if ( array() === $button_ids ) {
			return array();
		}

		$cache_key = 'spots_' . $this->last_changed() . '_' . md5( implode( ',', $button_ids ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$now          = time();
		$placeholders = implode( ', ', array_fill( 0, count( $button_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, cached below; $placeholders is only %d markers, and the values go in as one array.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT button_id, spot_option, SUM(spots) AS taken, MIN(CASE WHEN status <> %s THEN hold_until END) AS held_until FROM %i WHERE button_id IN ({$placeholders}) AND spots > 0 AND (status = %s OR (status <> %s AND hold_until >= %d)) GROUP BY button_id, spot_option", array_merge( array( self::STATUS_PAID, self::table_name() ), $button_ids, array( self::STATUS_PAID, self::STATUS_EXPIRED, $now ) ) ), ARRAY_A );

		$taken = array();
		// A hold counts up to and including its hold_until second, so the count changes the second after.
		$expires = self::LIST_CACHE_TTL;

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$id    = (int) $row['button_id'];
			$count = (int) $row['taken'];

			if ( null !== $row['held_until'] ) {
				$expires = min( $expires, (int) $row['held_until'] - $now + 1 );
			}

			$taken[ $id ] = $taken[ $id ] ?? array(
				'total'   => 0,
				'options' => array(),
			);

			// A Multiple choice payment holds its spots on every option it ticked, one line each.
			foreach ( explode( "\n", (string) $row['spot_option'] ) as $option ) {
				$taken[ $id ]['total']             += $count;
				$taken[ $id ]['options'][ $option ] = ( $taken[ $id ]['options'][ $option ] ?? 0 ) + $count;
			}
		}

		wp_cache_set( $cache_key, $taken, self::CACHE_GROUP, $expires );

		return $taken;
	}

	/**
	 * Lets go of a payment's spots at once, e.g. when its link was never made.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function release_spots( string $ref ): bool {
		return $this->update( $ref, array( 'hold_until' => 0 ) );
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

		$cache_key = 'since_' . $this->last_changed() . '_' . md5( (string) wp_json_encode( $since ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$where = implode( ' OR ', array_fill( 0, count( $since ), '(button_id = %d AND created_at >= %d)' ) );
		$args  = array( self::table_name(), self::STATUS_PAID );

		foreach ( $since as $id => $time ) {
			$args[] = (int) $id;
			$args[] = $time;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, cached below; $where is only fixed %d pairs, and the values go in as one array.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT button_id, COUNT(*) AS paid FROM %i WHERE status = %s AND ({$where}) GROUP BY button_id", $args ), ARRAY_A );
		$paid = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$paid[ (int) $row['button_id'] ] = (int) $row['paid'];
		}

		wp_cache_set( $cache_key, $paid, self::CACHE_GROUP, self::LIST_CACHE_TTL );

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

		$cache_key = 'buttons_' . $this->last_changed() . '_' . md5( implode( ',', $button_ids ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $button_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom plugin table, cached below; $placeholders is only %d markers, one per id, which the sniff can't count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT button_id, COUNT(*) AS total, SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) AS paid_count, SUM(CASE WHEN status = %s THEN amount ELSE 0 END) AS paid_amount FROM %i WHERE button_id IN ({$placeholders}) GROUP BY button_id", array_merge( array( self::STATUS_PAID, self::STATUS_PAID, self::table_name() ), $button_ids ) ), ARRAY_A );

		$stats = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$stats[ (int) $row['button_id'] ] = array(
				'paid_count'  => (int) $row['paid_count'],
				'paid_amount' => round( (float) $row['paid_amount'], 2 ),
				'count'       => (int) $row['total'],
			);
		}

		wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $stats;
	}

	/**
	 * One page of full rows for a payer's email, oldest first. For the personal data exporter and eraser.
	 *
	 * @param string $email  The payer's email.
	 * @param int    $limit  Rows per page.
	 * @param int    $offset Rows to skip.
	 * @return array<int, Transaction>
	 */
	public function for_email( string $email, int $limit, int $offset ): array {
		global $wpdb;

		// The eraser rereads page one after each batch; its writes bump last_changed, so it never gets a stale page.
		$cache_key = 'email_' . $this->last_changed() . '_' . md5( $email . '|' . $limit . '|' . $offset );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached below.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE customer_email = %s ORDER BY created_at ASC, ref ASC LIMIT %d OFFSET %d', self::table_name(), $email, $limit, $offset ), ARRAY_A );
		$rows = array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );

		wp_cache_set( $cache_key, $rows, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $rows;
	}

	/**
	 * Strips a row of the payer's details, keeping the amount, status and dates as payment history.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function anonymize( string $ref ): bool {
		$record   = $this->get( $ref );
		$snapshot = null !== $record ? self::decode_snapshot( $record['field_snapshot'] ) : array();

		return null !== $record && $this->update(
			$ref,
			array(
				'customer_name'  => '',
				'customer_email' => '',
				'source_url'     => '',
				'field_snapshot' => (string) wp_json_encode( array_intersect_key( $snapshot, array( 'button' => true ) ) ),
			)
		);
	}

	/**
	 * Records a payment taken outside ifthenpay, in any status.
	 *
	 * @param string $customer_name  The customer's name, or ''.
	 * @param string $customer_email The customer's email, or ''.
	 * @param float  $amount         The payment amount.
	 * @param string $status         One of ALL_STATUSES.
	 * @return string|null The generated ref, or null if the insert failed.
	 */
	public function create_manual( string $customer_name, string $customer_email, float $amount, string $status ): ?string {
		$ref = $this->unique_ref( 'MANUAL_', 8 );

		$inserted = $this->insert(
			array(
				'ref'            => $ref,
				'gateway_key'    => self::MANUAL_GATEWAY_KEY,
				'amount'         => $amount,
				'pay_method'     => self::AD_HOC_METHOD,
				'status'         => $status,
				'field_snapshot' => self::customer_snapshot( $customer_name, $customer_email ),
			)
		);

		return $inserted ? $ref : null;
	}

	/**
	 * A reference no row uses yet.
	 *
	 * @param string $prefix Readable prefix, e.g. `IFTP_`.
	 * @param int    $length Number of random characters after it.
	 */
	public function unique_ref( string $prefix, int $length ): string {
		do {
			$ref = $prefix . strtoupper( wp_generate_password( $length, false, false ) );
		} while ( null !== $this->get( $ref ) );

		return $ref;
	}

	/**
	 * Reads a transaction row by reference.
	 *
	 * @param string $ref The transaction reference.
	 * @return Transaction|null
	 */
	public function get( string $ref ): ?array {
		global $wpdb;

		$cached = wp_cache_get( self::row_cache_key( $ref ), self::CACHE_GROUP, false, $found );

		if ( $found ) {
			return is_array( $cached ) ? self::hydrate( $cached ) : null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE ref = %s', self::table_name(), $ref ), ARRAY_A );

		$record = is_array( $row ) ? self::hydrate( $row ) : null;

		wp_cache_set( self::row_cache_key( $ref ), $record, self::CACHE_GROUP, self::ENTRY_CACHE_TTL );

		return $record;
	}

	/**
	 * A button's most recent paid payment, for the invoicing test.
	 *
	 * @param int $button_id The button's post ID.
	 * @return Transaction|null
	 */
	public function latest_paid_for_button( int $button_id ): ?array {
		global $wpdb;

		// An empty array means "no paid payment yet", so that answer gets cached too.
		$cache_key = 'latest_paid_' . $this->last_changed() . '_' . $button_id;
		$row       = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( ! is_array( $row ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached below.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE button_id = %d AND status = %s ORDER BY created_at DESC, ref DESC LIMIT 1', self::table_name(), $button_id, self::STATUS_PAID ), ARRAY_A );
			$row = is_array( $row ) ? $row : array();

			wp_cache_set( $cache_key, $row, self::CACHE_GROUP, self::LIST_CACHE_TTL );
		}

		return array() !== $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Marks a transaction paid. One conditional UPDATE, so when the webhook fires twice at once only one call gets true.
	 *
	 * @param string $ref        The transaction reference.
	 * @param string $pay_method The payment method used.
	 * @param string $request_id The ifthenpay request id.
	 */
	public function mark_paid( string $ref, string $pay_method, string $request_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table; cache is invalidated right below.
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, pay_method = %s, request_id = %s, updated_at = %d WHERE ref = %s AND status <> %s',
				self::table_name(),
				self::STATUS_PAID,
				$pay_method,
				$request_id,
				time(),
				$ref,
				self::STATUS_PAID
			)
		);

		$this->forget( array( $ref ) );

		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Marks a transaction failed, unless it's already paid.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function mark_failed( string $ref ): bool {
		return $this->set_status_unless_paid( $ref, self::STATUS_FAILED );
	}

	/**
	 * Marks a transaction cancelled, unless it's already paid.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function mark_cancelled( string $ref ): bool {
		return $this->set_status_unless_paid( $ref, self::STATUS_CANCELLED );
	}

	/**
	 * Expires pending ifthenpay rows created before the cutoff, except ones still holding a spot. A late webhook still wins: mark_paid() only skips paid rows.
	 *
	 * @param int $cutoff Unix timestamp; older pending rows expire.
	 * @return int How many rows changed.
	 */
	public function expire_pending_before( int $cutoff ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table; a cron sweep, caching it would be pointless.
		$refs = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT ref FROM %i WHERE status = %s AND gateway_key <> %s AND created_at < %d AND hold_until < %d',
				self::table_name(),
				self::STATUS_PENDING,
				self::MANUAL_GATEWAY_KEY,
				$cutoff,
				time()
			)
		);

		return $this->set_status_many( array_map( 'strval', $refs ), self::STATUS_EXPIRED, self::STATUS_PENDING );
	}

	/**
	 * Expires pending payments whose spot hold ran out, which is when their ifthenpay link stopped taking payment.
	 *
	 * @param int $now Unix timestamp.
	 * @return int How many rows changed.
	 */
	public function expire_past_hold( int $now ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table; a cron sweep, caching it would be pointless.
		$refs = $wpdb->get_col( $wpdb->prepare( 'SELECT ref FROM %i WHERE status = %s AND hold_until > 0 AND hold_until < %d', self::table_name(), self::STATUS_PENDING, $now ) );

		return $this->set_status_many( array_map( 'strval', $refs ), self::STATUS_EXPIRED, self::STATUS_PENDING );
	}

	/**
	 * Records a payment link plus its Gateway Key, which the webhook validates against.
	 *
	 * @param string $ref         The transaction reference.
	 * @param string $payment_url The ifthenpay hosted payment page URL.
	 * @param string $gateway_key The Gateway Key the link belongs to.
	 */
	public function record_payment_link( string $ref, string $payment_url, string $gateway_key ): bool {
		return $this->update(
			$ref,
			array(
				'payment_url' => $payment_url,
				'gateway_key' => $gateway_key,
			)
		);
	}

	/**
	 * Records the ifthenpay transaction id.
	 *
	 * @param string $ref            The transaction reference.
	 * @param string $transaction_id The ifthenpay transaction id.
	 */
	public function record_transaction_id( string $ref, string $transaction_id ): bool {
		return $this->update( $ref, array( 'transaction_id' => $transaction_id ) );
	}

	/**
	 * Admin bulk-action override; unlike mark_*(), it can move a row off "paid".
	 *
	 * @param array<int, string> $refs   The transaction references.
	 * @param string             $status One of ALL_STATUSES.
	 * @return int How many rows actually changed status.
	 */
	public function admin_set_status_many( array $refs, string $status ): int {
		if ( ! in_array( $status, self::ALL_STATUSES, true ) ) {
			return 0;
		}

		$changed = $this->set_status_many( $refs, $status );

		// An admin closing a payment by hand wants its spots back for someone else.
		if ( ! in_array( $status, array( self::STATUS_PENDING, self::STATUS_PAID ), true ) && array() !== self::clean_refs( $refs ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cache invalidated below; prepare_for_refs() prepares it.
			$wpdb->query( self::prepare_for_refs( 'UPDATE %i SET hold_until = 0 WHERE ref IN ({refs})', self::clean_refs( $refs ), array( self::table_name() ) ) );
			$this->forget( self::clean_refs( $refs ) );
		}

		return $changed;
	}

	/**
	 * Deletes the given rows in one DELETE. Paid rows are payment history, so they stay.
	 *
	 * @param array<int, string> $refs The transaction references.
	 * @return int How many rows were deleted.
	 */
	public function delete_unpaid( array $refs ): int {
		global $wpdb;

		$refs = self::clean_refs( $refs );

		if ( array() === $refs ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cache invalidated below; prepare_for_refs() prepares it.
		$affected = $wpdb->query( self::prepare_for_refs( 'DELETE FROM %i WHERE ref IN ({refs}) AND status <> %s', $refs, array( self::table_name() ), array( self::STATUS_PAID ) ) );

		$this->forget( $refs );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Loads the given rows into the object cache in one SELECT, so a loop of get() calls doesn't query per ref.
	 *
	 * @param array<int, string> $refs The transaction references.
	 */
	public function prime( array $refs ): void {
		global $wpdb;

		$refs   = self::clean_refs( $refs );
		$cached = wp_cache_get_multiple( array_map( array( self::class, 'row_cache_key' ), $refs ), self::CACHE_GROUP );
		$refs   = array_values( array_filter( $refs, static fn( string $ref ): bool => false === ( $cached[ self::row_cache_key( $ref ) ] ?? false ) ) );

		if ( array() === $refs ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cached right below; prepare_for_refs() prepares it.
		$rows  = $wpdb->get_results( self::prepare_for_refs( 'SELECT * FROM %i WHERE ref IN ({refs})', $refs, array( self::table_name() ) ), ARRAY_A );
		$found = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$record                  = self::hydrate( $row );
			$found[ $record['ref'] ] = $record;
		}

		// Misses are cached too, same as get() does.
		foreach ( $refs as $ref ) {
			wp_cache_set( self::row_cache_key( $ref ), $found[ $ref ] ?? null, self::CACHE_GROUP, self::ENTRY_CACHE_TTL );
		}
	}

	/**
	 * A row's snapshot flattened to label/value pairs, skipping empty values.
	 *
	 * @param array<string, mixed> $record A transaction row, as returned by get().
	 * @return array<int, array{label: string, value: string}>
	 */
	public function entry_data_pairs( array $record ): array {
		$pairs    = array();
		$snapshot = is_string( $record['field_snapshot'] ?? null ) ? $record['field_snapshot'] : '';

		foreach ( self::decode_snapshot( $snapshot ) as $key => $pair ) {
			$value = self::snapshot_value( $pair );

			if ( '' === $value ) {
				continue;
			}

			$label = is_array( $pair ) && isset( $pair['label'] ) && is_scalar( $pair['label'] ) ? (string) $pair['label'] : (string) $key;

			$pairs[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $pairs;
	}

	/**
	 * A stored snapshot as an array, or empty when it's missing or malformed.
	 *
	 * @param string $json The field_snapshot column.
	 * @return array<string, mixed>
	 */
	public static function decode_snapshot( string $json ): array {
		$snapshot = '' !== $json ? json_decode( $json, true ) : null;

		return is_array( $snapshot ) ? $snapshot : array();
	}

	/**
	 * One page of the Entries list, filtered, sorted and paginated in SQL. Rows come without field_snapshot; get() has it.
	 *
	 * @param array<string, mixed> $args See build_where()/build_order(), plus paged and per_page.
	 * @return array{items: array<int, Transaction>, total: int, pages: int, paged: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$cache_key = 'query_' . $this->last_changed() . '_' . md5( (string) wp_json_encode( $args ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			/**
			 * Only this method writes the key, so the shape is known.
			 *
			 * @var array{items: array<int, Transaction>, total: int, pages: int, paged: int} $cached
			 */
			return $cached;
		}

		$table     = self::table_name();
		$where_sql = $this->build_where( $args );
		$order_sql = $this->build_order( $args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cached below; $where_sql is built from prepare() calls.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i' . $where_sql, $table ) );

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( $pages, max( 1, (int) ( $args['paged'] ?? 1 ) ) );

		$result = array(
			'items' => $this->fetch_page( $where_sql, $order_sql, $per_page, ( $paged - 1 ) * $per_page ),
			'total' => $total,
			'pages' => $pages,
			'paged' => $paged,
		);

		wp_cache_set( $cache_key, $result, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $result;
	}

	/**
	 * Every row matching query()'s filters, unpaginated and without field_snapshot, for the export.
	 * Uncached and read in batches so memory stays flat on a big table.
	 *
	 * @param array<string, mixed> $args  Same filter/sort args as query(); paged/per_page are ignored. `with_snapshot` adds field_snapshot.
	 * @param int                  $batch Rows per SELECT.
	 * @return \Generator<int, Transaction, void, void>
	 */
	public function iterate( array $args, int $batch = 500 ): \Generator {
		global $wpdb;

		$batch     = max( 1, $batch );
		$where_sql = $this->build_where( $args );
		$order_sql = $this->build_order( $args );
		$offset    = 0;

		do {
			$rows = $this->fetch_page( $where_sql, $order_sql, $batch, $offset, ! empty( $args['with_snapshot'] ) );

			// wpdb keeps its own copy of the last result set; I drop it before yielding.
			$wpdb->flush();

			$fetched = count( $rows );

			yield from $rows;

			$offset += $batch;
		} while ( $fetched === $batch );
	}

	/**
	 * The distinct non-empty payment methods, sorted, for the Method filter.
	 *
	 * @return array<int, string>
	 */
	public function distinct_methods(): array {
		global $wpdb;

		$cache_key = 'distinct_methods_' . $this->last_changed();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$methods = array_map( 'strval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pay_method FROM %i WHERE pay_method <> '' ORDER BY pay_method ASC", self::table_name() ) ) );

		wp_cache_set( $cache_key, $methods, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $methods;
	}

	/**
	 * The money still waiting: every pending payment's count and total.
	 *
	 * @return array{count: int, amount: float}
	 */
	public function waiting(): array {
		global $wpdb;

		$cache_key = 'waiting_' . $this->last_changed();
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			/**
			 * Only this method writes the key, so the shape is known.
			 *
			 * @var array{count: int, amount: float} $cached
			 */
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s', self::table_name(), self::STATUS_PENDING ), ARRAY_A );

		$waiting = array(
			'count'  => (int) ( $row['total'] ?? 0 ),
			'amount' => round( (float) ( $row['amount'] ?? 0 ), 2 ),
		);

		wp_cache_set( $cache_key, $waiting, self::CACHE_GROUP, self::LIST_CACHE_TTL );

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

		$cache_key = 'methods_' . $this->last_changed() . '_' . $from . '_' . $to;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT pay_method, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s AND created_at >= %d AND created_at < %d GROUP BY pay_method', self::table_name(), self::STATUS_PAID, $from, $to ), ARRAY_A );

		$totals = array_map(
			static fn( array $row ): array => array(
				'method' => (string) $row['pay_method'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $totals, self::CACHE_GROUP, self::LIST_CACHE_TTL );

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

		$cache_key = 'top_' . $this->last_changed() . '_' . $from . '_' . $to . '_' . $limit;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached below.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT MAX(customer_name) AS name, MAX(customer_email) AS email, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE status = %s AND created_at >= %d AND created_at < %d AND (customer_email <> '' OR customer_name <> '') GROUP BY IF(customer_email <> '', customer_email, customer_name) ORDER BY amount DESC LIMIT %d", self::table_name(), self::STATUS_PAID, $from, $to, $limit ), ARRAY_A );

		$top = array_map(
			static fn( array $row ): array => array(
				'name'   => (string) $row['name'],
				'email'  => (string) $row['email'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $top, self::CACHE_GROUP, self::LIST_CACHE_TTL );

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

		$cache_key = 'daily_' . $this->last_changed() . '_' . md5( $from . '|' . $to . '|' . $offset );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table, cached right below.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT FLOOR((created_at + %d) / 86400) AS day, status, COUNT(*) AS total, SUM(amount) AS amount FROM %i WHERE created_at >= %d AND created_at < %d GROUP BY day, status', $offset, self::table_name(), $from, $to ), ARRAY_A );

		$totals = array_map(
			static fn( array $row ): array => array(
				'day'    => (int) $row['day'],
				'status' => (string) $row['status'],
				'count'  => (int) $row['total'],
				'amount' => (float) $row['amount'],
			),
			is_array( $rows ) ? $rows : array()
		);

		wp_cache_set( $cache_key, $totals, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $totals;
	}

	/**
	 * Count and amount per status for the given filters, ignoring their `status`.
	 * Cancelled rolls into Failed, since the admin list has no Cancelled tab.
	 *
	 * @param array<string, mixed> $args Same filter args as build_where().
	 * @return array<string, array{count: int, amount: float}>
	 */
	public function status_summary( array $args = array() ): array {
		global $wpdb;

		unset( $args['status'] );

		$cache_key = 'status_summary_' . $this->last_changed() . '_' . md5( (string) wp_json_encode( $args ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cached below; the WHERE clause is built from prepare() calls.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total, SUM(amount) AS amount FROM %i' . $this->build_where( $args ) . ' GROUP BY status', self::table_name() ), ARRAY_A );

		$summary = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$status = self::STATUS_CANCELLED === $row['status'] ? self::STATUS_FAILED : (string) $row['status'];
			$prior  = $summary[ $status ] ?? array(
				'count'  => 0,
				'amount' => 0.0,
			);

			$summary[ $status ] = array(
				'count'  => $prior['count'] + (int) $row['total'],
				'amount' => round( $prior['amount'] + (float) $row['amount'], 2 ),
			);
		}

		wp_cache_set( $cache_key, $summary, self::CACHE_GROUP, self::LIST_CACHE_TTL );

		return $summary;
	}

	/**
	 * The snapshot for a row entered by hand: just the customer.
	 *
	 * @param string $customer_name  The customer's name, or ''.
	 * @param string $customer_email The customer's email, or ''.
	 * @return array<string, array{label: string, value: string}>
	 */
	private static function customer_snapshot( string $customer_name, string $customer_email ): array {
		$snapshot = array();

		if ( '' !== $customer_name ) {
			$snapshot['customer_name'] = array(
				'label' => __( 'Customer name', 'ifthenpay-pay-button' ),
				'value' => $customer_name,
			);
		}

		if ( '' !== $customer_email ) {
			$snapshot['customer_email'] = array(
				'label' => __( 'Customer email', 'ifthenpay-pay-button' ),
				'value' => $customer_email,
			);
		}

		return $snapshot;
	}

	/**
	 * Inserts a row, filling in the customer columns and timestamps.
	 *
	 * @param array{ref: string, gateway_key: string, amount: float, status: string, field_snapshot: array<string, array{label: string, value: string}>, pay_method?: string, button_id?: int, external_ref?: string, source_url?: string, spots?: int, spot_option?: string, hold_until?: int} $row The row to insert.
	 */
	private function insert( array $row ): bool {
		global $wpdb;

		$now      = time();
		$customer = $this->storable_customer(
			self::snapshot_value( $row['field_snapshot']['customer_name'] ?? '' ),
			self::snapshot_value( $row['field_snapshot']['customer_email'] ?? '' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom plugin table; no WP Core API available.
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'ref'            => $row['ref'],
				'gateway_key'    => $row['gateway_key'],
				'button_id'      => $row['button_id'] ?? 0,
				'external_ref'   => mb_substr( $row['external_ref'] ?? '', 0, self::EXTERNAL_REF_MAX_LENGTH ),
				'source_url'     => mb_substr( $row['source_url'] ?? '', 0, self::SOURCE_URL_MAX_LENGTH ),
				'amount'         => $row['amount'],
				'pay_method'     => $row['pay_method'] ?? '',
				'customer_name'  => $customer['name'],
				'customer_email' => $customer['email'],
				'status'         => $row['status'],
				'field_snapshot' => (string) wp_json_encode( $row['field_snapshot'] ),
				'created_at'     => $now,
				'updated_at'     => $now,
				'spots'          => $row['spots'] ?? 0,
				'spot_option'    => mb_substr( $row['spot_option'] ?? '', 0, 4000 ),
				'hold_until'     => $row['hold_until'] ?? 0,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d' )
		);

		// unique_ref() cached a miss for this ref; a persistent cache would hide the row from the webhook.
		$this->forget( array( $row['ref'] ) );

		return false !== $result;
	}

	/**
	 * Writes a status unless the row is already paid — in the WHERE clause, so a webhook landing at the same moment can't be overwritten.
	 *
	 * @param string $ref    The transaction reference.
	 * @param string $status One of ALL_STATUSES.
	 */
	private function set_status_unless_paid( string $ref, string $status ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table; cache is invalidated right below.
		$affected = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, updated_at = %d WHERE ref = %s AND status <> %s',
				self::table_name(),
				$status,
				time(),
				$ref,
				self::STATUS_PAID
			)
		);

		$this->forget( array( $ref ) );

		return is_int( $affected ) && $affected > 0;
	}

	/**
	 * Sets one status on many rows in one UPDATE.
	 *
	 * @param array<int, string> $refs        The transaction references.
	 * @param string             $status      The status to write.
	 * @param string             $only_status Only touch rows currently in this status, or '' for any.
	 * @return int How many rows changed.
	 */
	private function set_status_many( array $refs, string $status, string $only_status = '' ): int {
		global $wpdb;

		$refs = self::clean_refs( $refs );

		if ( array() === $refs ) {
			return 0;
		}

		$sql     = '' !== $only_status
			? 'UPDATE %i SET status = %s, updated_at = %d WHERE ref IN ({refs}) AND status = %s'
			: 'UPDATE %i SET status = %s, updated_at = %d WHERE ref IN ({refs}) AND status <> %s';
		$guarded = '' !== $only_status ? $only_status : $status;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, cache invalidated below; prepare_for_refs() prepares it.
		$affected = $wpdb->query( self::prepare_for_refs( $sql, $refs, array( self::table_name(), $status, time() ), array( $guarded ) ) );

		$this->forget( $refs );

		return is_int( $affected ) ? $affected : 0;
	}

	/**
	 * Updates a transaction row and invalidates its cache entry.
	 *
	 * @param string               $ref    The transaction reference.
	 * @param array<string, mixed> $fields The columns to update.
	 */
	private function update( string $ref, array $fields ): bool {
		global $wpdb;

		$fields['updated_at'] = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table; cache is invalidated right below.
		$result = $wpdb->update( self::table_name(), $fields, array( 'ref' => $ref ) );

		$this->forget( array( $ref ) );

		return false !== $result;
	}

	/**
	 * One page of full rows. I pick the refs first and join the rows back, so a deep OFFSET doesn't sort every row's snapshot along with it.
	 *
	 * @param string $where_sql From build_where().
	 * @param string $order_sql From build_order().
	 * @param int    $limit     Rows to fetch.
	 * @param int    $offset    Rows to skip.
	 * @param bool   $snapshot  Whether to read field_snapshot too.
	 * @return array<int, Transaction>
	 */
	private function fetch_page( string $where_sql, string $order_sql, int $limit, int $offset, bool $snapshot = false ): array {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom plugin table, callers cache; $where_sql is built from prepare() calls, $order_sql from SORTABLE_COLUMNS, and LIST_COLUMNS is a constant.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . self::LIST_COLUMNS . ( $snapshot ? ', t.field_snapshot' : '' ) . ' FROM %i AS t INNER JOIN (SELECT ref FROM %i' . $where_sql . $order_sql . ' LIMIT %d OFFSET %d) AS page USING (ref)' . $order_sql, $table, $table, $limit, $offset ), ARRAY_A );

		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * A fully prepared ` WHERE …` clause, or '' for no filter.
	 *
	 * @param array<string, mixed> $args Filter args: status, button_id, pay_method, amount_min/max, refs, date_from/to, search.
	 */
	private function build_where( array $args ): string {
		global $wpdb;

		$conditions = array();
		$status     = is_string( $args['status'] ?? null ) ? $args['status'] : '';

		if ( self::STATUS_FAILED === $status ) {
			// The admin list treats cancelled as a kind of failed.
			$conditions[] = $wpdb->prepare( 'status IN (%s, %s)', self::STATUS_FAILED, self::STATUS_CANCELLED );
		} elseif ( '' !== $status ) {
			$conditions[] = $wpdb->prepare( 'status = %s', $status );
		}

		$button_id = is_numeric( $args['button_id'] ?? null ) ? (int) $args['button_id'] : 0;

		if ( $button_id > 0 ) {
			$conditions[] = $wpdb->prepare( 'button_id = %d', $button_id );
		}

		$pay_method = is_string( $args['pay_method'] ?? null ) ? $args['pay_method'] : '';

		if ( '' !== $pay_method ) {
			$conditions[] = $wpdb->prepare( 'pay_method = %s', $pay_method );
		}

		if ( is_numeric( $args['amount_min'] ?? null ) ) {
			$conditions[] = $wpdb->prepare( 'amount >= %f', (float) $args['amount_min'] );
		}

		if ( is_numeric( $args['amount_max'] ?? null ) ) {
			$conditions[] = $wpdb->prepare( 'amount <= %f', (float) $args['amount_max'] );
		}

		if ( isset( $args['refs'] ) && is_array( $args['refs'] ) && array() !== $args['refs'] ) {
			$refs = self::clean_refs( $args['refs'] );

			// A selection with nothing valid in it must match nothing, not everything.
			$conditions[] = array() === $refs ? '1 = 0' : self::prepare_for_refs( 'ref IN ({refs})', $refs );
		}

		$date_from = is_numeric( $args['date_from'] ?? null ) ? (int) $args['date_from'] : 0;

		if ( $date_from > 0 ) {
			$conditions[] = $wpdb->prepare( 'created_at >= %d', $date_from );
		}

		$date_to = is_numeric( $args['date_to'] ?? null ) ? (int) $args['date_to'] : 0;

		if ( $date_to > 0 ) {
			$conditions[] = $wpdb->prepare( 'created_at <= %d', $date_to );
		}

		$search = is_string( $args['search'] ?? null ) ? $args['search'] : '';

		if ( '' !== $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = '(' . $wpdb->prepare( 'ref LIKE %s OR external_ref LIKE %s OR pay_method LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s', $like, $like, $like, $like, $like ) . ')';
		}

		return array() === $conditions ? '' : ' WHERE ' . implode( ' AND ', $conditions );
	}

	/**
	 * The ` ORDER BY …` clause. Ties break on `ref` so LIMIT/OFFSET pages never repeat or skip a row.
	 *
	 * @param array<string, mixed> $args Sort args: orderby, order.
	 */
	private function build_order( array $args ): string {
		$orderby = $args['orderby'] ?? '';

		if ( ! in_array( $orderby, self::SORTABLE_COLUMNS, true ) ) {
			return ' ORDER BY created_at DESC, ref DESC';
		}

		$order = 'asc' === ( $args['order'] ?? '' ) ? 'ASC' : 'DESC';

		return 'ref' === $orderby ? " ORDER BY ref {$order}" : " ORDER BY {$orderby} {$order}, ref {$order}";
	}

	/**
	 * Casts a raw ARRAY_A row to its typed shape once, so callers don't have to.
	 *
	 * @param array<string, mixed> $row A raw table row.
	 * @return Transaction
	 */
	private static function hydrate( array $row ): array {
		$text = static fn( string $key ): string => is_scalar( $row[ $key ] ?? null ) ? (string) $row[ $key ] : '';
		$int  = static fn( string $key ): int => is_numeric( $row[ $key ] ?? null ) ? (int) $row[ $key ] : 0;

		return array(
			'ref'            => $text( 'ref' ),
			'gateway_key'    => $text( 'gateway_key' ),
			'button_id'      => $int( 'button_id' ),
			'external_ref'   => $text( 'external_ref' ),
			'source_url'     => $text( 'source_url' ),
			'amount'         => is_numeric( $row['amount'] ?? null ) ? (float) $row['amount'] : 0.0,
			'pay_method'     => $text( 'pay_method' ),
			'request_id'     => $text( 'request_id' ),
			'transaction_id' => $text( 'transaction_id' ),
			'payment_url'    => $text( 'payment_url' ),
			'customer_name'  => $text( 'customer_name' ),
			'customer_email' => $text( 'customer_email' ),
			'status'         => $text( 'status' ),
			'field_snapshot' => $text( 'field_snapshot' ),
			'created_at'     => $int( 'created_at' ),
			'updated_at'     => $int( 'updated_at' ),
			'spots'          => $int( 'spots' ),
			'spot_option'    => $text( 'spot_option' ),
			'hold_until'     => $int( 'hold_until' ),
		);
	}

	/**
	 * The customer cut down to what its columns can store.
	 * wpdb fails the whole insert on a character the charset can't hold (emoji on utf8mb3), so I strip those.
	 *
	 * @param string $name  The customer's name.
	 * @param string $email The customer's email.
	 * @return array{name: string, email: string}
	 */
	private function storable_customer( string $name, string $email ): array {
		global $wpdb;

		$table = self::table_name();
		$name  = $wpdb->strip_invalid_text_for_column( $table, 'customer_name', mb_substr( $name, 0, self::CUSTOMER_MAX_LENGTH ) );
		$email = false !== is_email( $email ) ? $wpdb->strip_invalid_text_for_column( $table, 'customer_email', mb_substr( $email, 0, self::CUSTOMER_MAX_LENGTH ) ) : '';

		return array(
			'name'  => is_string( $name ) ? $name : '',
			'email' => is_string( $email ) ? $email : '',
		);
	}

	/**
	 * A snapshot entry's trimmed value — either a {label, value} pair or a bare scalar.
	 *
	 * @param mixed $pair The snapshot entry.
	 */
	private static function snapshot_value( $pair ): string {
		$value = is_array( $pair ) ? ( $pair['value'] ?? '' ) : $pair;

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Prepares $sql with `{refs}` swapped for one %s per ref. The refs sit between $before and $after in the args.
	 *
	 * @param string             $sql    The query, with a `{refs}` token inside its `IN ( … )`.
	 * @param array<int, string> $refs   The refs, already cleaned.
	 * @param array<int, mixed>  $before Args for the placeholders before `{refs}`.
	 * @param array<int, mixed>  $after  Args for the placeholders after it.
	 */
	private static function prepare_for_refs( string $sql, array $refs, array $before = array(), array $after = array() ): string {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $refs ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- callers pass fixed SQL; {refs} becomes one %s per ref.
		return (string) $wpdb->prepare( str_replace( '{refs}', $placeholders, $sql ), array_merge( $before, $refs, $after ) );
	}

	/**
	 * Scalar, non-empty, de-duplicated refs, re-indexed.
	 *
	 * @param array<mixed> $refs Raw refs.
	 * @return array<int, string>
	 */
	private static function clean_refs( array $refs ): array {
		$refs = array_map( 'strval', array_filter( $refs, 'is_scalar' ) );

		return array_values( array_unique( array_filter( $refs, static fn( string $ref ): bool => '' !== $ref ) ) );
	}

	/**
	 * Drops the given rows' cached copies and every cached list.
	 *
	 * @param array<int, string> $refs The transaction references.
	 */
	private function forget( array $refs ): void {
		wp_cache_delete_multiple( array_map( array( self::class, 'row_cache_key' ), $refs ), self::CACHE_GROUP );
		wp_cache_set( 'last_changed', microtime(), self::CACHE_GROUP );
	}

	/**
	 * The cache key for one row.
	 *
	 * @param string $ref The transaction reference.
	 */
	private static function row_cache_key( string $ref ): string {
		return 'txn_' . $ref;
	}

	/**
	 * A token bumped on every write, so list caches never need per-row invalidation.
	 */
	private function last_changed(): string {
		$last_changed = wp_cache_get( 'last_changed', self::CACHE_GROUP );

		if ( ! is_string( $last_changed ) ) {
			$last_changed = microtime();
			wp_cache_set( 'last_changed', $last_changed, self::CACHE_GROUP );
		}

		return $last_changed;
	}
}
