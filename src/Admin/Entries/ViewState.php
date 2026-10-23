<?php
/**
 * Entries list view state: filters, sort and pagination.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, sanitizes and rebuilds the list's view state. It lives in plain GET params, so every view has a shareable URL;
 * AJAX mutations send it back as POST so the server can re-render the same view.
 *
 * @phpstan-type View array{s: string, status: string, date_from: string, date_to: string, button: int, pay_method: string, amount_min: string, amount_max: string, per_page: int, paged: int, orderby: string, order: string}
 */
class ViewState {

	public const DEFAULT_PER_PAGE = 50;
	public const PER_PAGE_OPTIONS = array( 10, 25, 50, 100, 250 );
	public const PER_PAGE_MIN     = 1;
	public const PER_PAGE_MAX     = 250;
	public const PER_PAGE_META    = 'iftp_btn_entries_per_page';

	// Not ref: references are random strings, so sorting by them tells the admin nothing.
	private const SORTABLE = array( 'customer_name', 'amount', 'status', 'created_at' );

	// The list's own status filters; cancelled rows show under Failed.
	public const LIST_STATUSES = array(
		TransactionStore::STATUS_PENDING,
		TransactionStore::STATUS_PAID,
		TransactionStore::STATUS_FAILED,
		TransactionStore::STATUS_EXPIRED,
	);

	/**
	 * Transactions storage, for the Method filter's allow-list.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * The current request's view, read once per request.
	 *
	 * @var View|null
	 */
	private ?array $current = null;

	/**
	 * Sets up the reader.
	 *
	 * @param TransactionStore $transactions Transactions storage.
	 */
	public function __construct( TransactionStore $transactions ) {
		$this->transactions = $transactions;
	}

	/**
	 * The current request's view, from `$_GET`.
	 *
	 * @return View
	 */
	public function current(): array {
		if ( null === $this->current ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state from this screen's query string; from_source() sanitizes every key.
			$this->current = $this->from_source( $_GET );
		}

		return $this->current;
	}

	/**
	 * The view an AJAX request posted back, so its response can re-render that same view.
	 * Its status always comes as `view_status`, since a bare `status` is the bulk action's target status.
	 *
	 * @param string $prefix '' or 'view_', for actions whose own fields would clash with the view's.
	 * @return View
	 */
	public function from_post( string $prefix = '' ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first; from_source() sanitizes every key.
		return $this->from_source( $_POST, $prefix, 'view_status' );
	}

	/**
	 * Reads and sanitizes a view from a raw (still slashed) request array.
	 *
	 * @param array<string, mixed> $source     The raw request array.
	 * @param string               $prefix     Prefix on every key but the status.
	 * @param string               $status_key Where the status lives.
	 * @return View
	 */
	public function from_source( array $source, string $prefix = '', string $status_key = 'status' ): array {
		$read = static function ( string $key ) use ( $source ): string {
			$value = $source[ $key ] ?? '';

			return is_scalar( $value ) ? sanitize_text_field( wp_unslash( (string) $value ) ) : '';
		};

		$status  = $read( $status_key );
		$orderby = in_array( $read( $prefix . 'orderby' ), self::SORTABLE, true ) ? $read( $prefix . 'orderby' ) : '';
		$order   = 'asc' === strtolower( $read( $prefix . 'order' ) ) ? 'asc' : 'desc';

		list( $amount_min, $amount_max ) = self::ordered_amount_range(
			self::sanitize_amount( $read( $prefix . 'amount_min' ) ),
			self::sanitize_amount( $read( $prefix . 'amount_max' ) )
		);

		return array(
			's'          => $read( $prefix . 's' ),
			'status'     => in_array( $status, self::LIST_STATUSES, true ) ? $status : '',
			'date_from'  => self::sanitize_date( $read( $prefix . 'date_from' ) ),
			'date_to'    => self::sanitize_date( $read( $prefix . 'date_to' ) ),
			'button'     => absint( $read( $prefix . 'button' ) ),
			'pay_method' => $this->sanitize_pay_method( $read( $prefix . 'pay_method' ) ),
			'amount_min' => $amount_min,
			'amount_max' => $amount_max,
			'per_page'   => '' !== $read( $prefix . 'per_page' ) ? self::resolve_per_page( $read( $prefix . 'per_page' ), $read( $prefix . 'per_page_custom' ) ) : $this->saved_per_page(),
			'paged'      => max( 1, absint( $read( $prefix . 'paged' ) ) ),
			'orderby'    => $orderby,
			'order'      => '' !== $orderby ? $order : '',
		);
	}

	/**
	 * Saves the admin's per-page choice whenever the URL carries one.
	 */
	public function remember_per_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- per-user display preference from this screen's GET view, like core's list-table mode.
		if ( ! isset( $_GET['per_page'] ) ) {
			return;
		}

		$per_page = $this->current()['per_page'];

		if ( self::DEFAULT_PER_PAGE === $per_page ) {
			delete_user_meta( get_current_user_id(), self::PER_PAGE_META );
		} elseif ( $this->saved_per_page() !== $per_page ) {
			update_user_meta( get_current_user_id(), self::PER_PAGE_META, $per_page );
		}
	}

	/**
	 * The current admin's saved per-page, or the default.
	 */
	public function saved_per_page(): int {
		return self::sanitize_per_page( (string) get_user_meta( get_current_user_id(), self::PER_PAGE_META, true ) );
	}

	/**
	 * A view as URL params (no `page`).
	 *
	 * @param array<string, mixed> $view A view from current()/from_source().
	 * @return array<string, mixed>
	 */
	public static function url_args( array $view ): array {
		return array(
			's'          => $view['s'],
			'status'     => $view['status'],
			'date_from'  => $view['date_from'],
			'date_to'    => $view['date_to'],
			'button'     => $view['button'] > 0 ? $view['button'] : '',
			'pay_method' => $view['pay_method'],
			'amount_min' => $view['amount_min'],
			'amount_max' => $view['amount_max'],
			'orderby'    => $view['orderby'],
			'order'      => $view['order'],
			'per_page'   => $view['per_page'],
			'paged'      => $view['paged'],
		);
	}

	/**
	 * The store's where-args: everything but status/sort/pagination.
	 *
	 * @param array<string, mixed> $view A view from current()/from_source().
	 * @return array<string, mixed>
	 */
	public static function store_filters( array $view ): array {
		return array(
			'date_from'  => self::day_bound( (string) $view['date_from'], '00:00:00' ),
			'date_to'    => self::day_bound( (string) $view['date_to'], '23:59:59' ),
			'search'     => $view['s'],
			'button_id'  => $view['button'],
			'pay_method' => $view['pay_method'],
			'amount_min' => '' !== $view['amount_min'] ? (float) $view['amount_min'] : null,
			'amount_max' => '' !== $view['amount_max'] ? (float) $view['amount_max'] : null,
		);
	}

	/**
	 * The list URL for a view with the given params overridden (null removes one).
	 *
	 * @param array<string, mixed>      $overrides The params to override.
	 * @param array<string, mixed>|null $base      url_args() to build from; defaults to the current request's (AJAX needs it, since $_GET is admin-ajax.php's).
	 */
	public function url( array $overrides, ?array $base = null ): string {
		$args = array( 'page' => EntriesPage::PAGE_SLUG ) + ( $base ?? self::url_args( $this->current() ) );

		foreach ( $overrides as $key => $value ) {
			if ( null === $value ) {
				unset( $args[ $key ] );
			} else {
				$args[ $key ] = $value;
			}
		}

		// per_page always stays, so even the "All" view isn't the bare URL that entries-restore.js redirects away from.
		return add_query_arg( self::query_values( $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Drops blank params and URL-encodes the rest. add_query_arg() doesn't encode values, so a search like "a&b" would split the query.
	 *
	 * @param array<string, mixed> $args Query params.
	 * @return array<string, string>
	 */
	public static function query_values( array $args ): array {
		$args = array_filter( $args, static fn( $value ): bool => '' !== $value && null !== $value );

		return array_map( static fn( $value ): string => rawurlencode( is_scalar( $value ) ? (string) $value : '' ), $args );
	}

	/**
	 * The quick date-range chips, as Y-m-d bounds in the site's timezone.
	 *
	 * @return array<int, array{label: string, from: string, to: string}>
	 */
	public static function date_presets(): array {
		$today = current_datetime();

		return array(
			array(
				'label' => __( 'Today', 'ifthenpay-pay-button' ),
				'from'  => $today->format( 'Y-m-d' ),
				'to'    => $today->format( 'Y-m-d' ),
			),
			array(
				'label' => __( 'Last 7 days', 'ifthenpay-pay-button' ),
				'from'  => $today->modify( '-6 days' )->format( 'Y-m-d' ),
				'to'    => $today->format( 'Y-m-d' ),
			),
			array(
				'label' => __( 'This month', 'ifthenpay-pay-button' ),
				'from'  => $today->modify( 'first day of this month' )->format( 'Y-m-d' ),
				'to'    => $today->modify( 'last day of this month' )->format( 'Y-m-d' ),
			),
			array(
				'label' => __( 'Last month', 'ifthenpay-pay-button' ),
				'from'  => $today->modify( 'first day of last month' )->format( 'Y-m-d' ),
				'to'    => $today->modify( 'last day of last month' )->format( 'Y-m-d' ),
			),
		);
	}

	/**
	 * A filter date as a timestamp in the site's timezone, or 0 for no bound. Not strtotime(): it reads UTC.
	 *
	 * @param string $date A Y-m-d date from sanitize_date(), or ''.
	 * @param string $time The time of day to pin it to.
	 */
	public static function day_bound( string $date, string $time ): int {
		if ( '' === $date ) {
			return 0;
		}

		try {
			return ( new \DateTimeImmutable( $date . ' ' . $time, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Validates a Y-m-d date, discarding anything malformed.
	 *
	 * @param string $value The sanitized request value.
	 */
	private static function sanitize_date( string $value ): string {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) ) {
			return '';
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : '';
	}

	/**
	 * Accepts only a method that actually appears in the table, else ''.
	 *
	 * @param string $value The sanitized request value.
	 */
	private function sanitize_pay_method( string $value ): string {
		return '' !== $value && in_array( $value, $this->transactions->distinct_methods(), true ) ? $value : '';
	}

	/**
	 * Normalizes an amount bound to a dot-decimal string, or '' for no bound.
	 *
	 * @param string $value The sanitized request value.
	 */
	private static function sanitize_amount( string $value ): string {
		$value = str_replace( ',', '.', trim( $value ) );

		return 1 === preg_match( '/^\d{1,12}(?:\.\d+)?$/', $value ) ? $value : '';
	}

	/**
	 * Swaps an inverted amount range, so "50 – 10" means 10 to 50.
	 *
	 * @param string $min From sanitize_amount().
	 * @param string $max From sanitize_amount().
	 * @return array{0: string, 1: string}
	 */
	private static function ordered_amount_range( string $min, string $max ): array {
		if ( '' !== $min && '' !== $max && (float) $min > (float) $max ) {
			return array( $max, $min );
		}

		return array( $min, $max );
	}

	/**
	 * Clamps a per-page value to [PER_PAGE_MIN, PER_PAGE_MAX]; non-numeric means the default.
	 *
	 * @param string $value The sanitized request value.
	 */
	private static function sanitize_per_page( string $value ): int {
		if ( '' === $value || ! is_numeric( $value ) ) {
			return self::DEFAULT_PER_PAGE;
		}

		return max( self::PER_PAGE_MIN, min( self::PER_PAGE_MAX, (int) $value ) );
	}

	/**
	 * Resolves the per-page select, using the custom box's value for "custom".
	 *
	 * @param string $value  The per_page value.
	 * @param string $custom The per_page_custom value.
	 */
	private static function resolve_per_page( string $value, string $custom ): int {
		return self::sanitize_per_page( 'custom' === $value ? $custom : $value );
	}
}
