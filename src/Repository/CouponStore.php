<?php
/**
 * Discount codes storage.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes discount codes and counts their uses. A use counts while its payment is pending or paid, so a
 * payment that fails or expires gives its use back without anything to undo.
 *
 * @phpstan-type Coupon array{id: int, code: string, type: string, value: float, min_amount: float, max_uses: int, button_ids: array<int, int>, starts_at: int, expires_at: int, active: bool, created_at: int}
 */
class CouponStore {

	public const TYPE_PERCENT = 'percent';
	public const TYPE_FIXED   = 'fixed';

	private const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9_-]{2,39}$/';

	/**
	 * Which buttons can take a code, per request, so a page of buttons asks once.
	 *
	 * @var array<int, bool>
	 */
	private static array $button_cache = array();

	/**
	 * A code as it is stored and compared: trimmed, upper case, no spaces.
	 *
	 * @param string $code What was typed.
	 */
	public static function normalize( string $code ): string {
		return strtoupper( (string) preg_replace( '/\s+/', '', trim( $code ) ) );
	}

	/**
	 * Every code, newest first.
	 *
	 * @return array<int, Coupon>
	 */
	public function all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', ExtrasSchema::coupons_table() ), ARRAY_A );

		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * One code by its id.
	 *
	 * @param int $id The code's id.
	 * @return Coupon|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', ExtrasSchema::coupons_table(), $id ), ARRAY_A );

		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * One code by what the payer typed.
	 *
	 * @param string $code The code, in any case.
	 * @return Coupon|null
	 */
	public function find_by_code( string $code ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE code = %s', ExtrasSchema::coupons_table(), self::normalize( $code ) ), ARRAY_A );

		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Checks and stores a code.
	 *
	 * @param array<string, mixed> $data The form: code, type, value, min_amount, max_uses, button_ids, starts_at, expires_at, active.
	 * @param int                  $id   The code to change, or 0 to add one.
	 * @return int|WP_Error The id, or what is wrong with the input.
	 */
	public function save( array $data, int $id = 0 ) {
		global $wpdb;

		$code = self::normalize( is_string( $data['code'] ?? null ) ? $data['code'] : '' );

		if ( 1 !== preg_match( self::CODE_PATTERN, $code ) ) {
			return new WP_Error( 'iftp_btn_coupon_code', __( 'A code is 3 to 40 letters, numbers, dashes or underscores.', 'ifthenpay-pay-button' ) );
		}

		$type  = self::TYPE_FIXED === ( $data['type'] ?? '' ) ? self::TYPE_FIXED : self::TYPE_PERCENT;
		$value = is_numeric( $data['value'] ?? null ) ? round( (float) $data['value'], 2 ) : 0.0;

		if ( $value <= 0 || ( self::TYPE_PERCENT === $type && $value > 100 ) ) {
			return new WP_Error( 'iftp_btn_coupon_value', self::TYPE_PERCENT === $type ? __( 'A percentage is between 0 and 100.', 'ifthenpay-pay-button' ) : __( 'Enter how much it takes off.', 'ifthenpay-pay-button' ) );
		}

		$other = $this->find_by_code( $code );

		if ( null !== $other && $other['id'] !== $id ) {
			return new WP_Error( 'iftp_btn_coupon_taken', __( 'There is already a code like that.', 'ifthenpay-pay-button' ) );
		}

		$starts  = is_numeric( $data['starts_at'] ?? null ) ? max( 0, (int) $data['starts_at'] ) : 0;
		$expires = is_numeric( $data['expires_at'] ?? null ) ? max( 0, (int) $data['expires_at'] ) : 0;

		if ( $expires > 0 && $starts > $expires ) {
			return new WP_Error( 'iftp_btn_coupon_dates', __( 'It ends before it starts.', 'ifthenpay-pay-button' ) );
		}

		$buttons = isset( $data['button_ids'] ) && is_array( $data['button_ids'] ) ? array_values( array_unique( array_filter( array_map( 'intval', $data['button_ids'] ), static fn( int $button ): bool => $button > 0 ) ) ) : array();

		$row = array(
			'code'       => $code,
			'type'       => $type,
			'value'      => $value,
			'min_amount' => is_numeric( $data['min_amount'] ?? null ) ? max( 0.0, round( (float) $data['min_amount'], 2 ) ) : 0.0,
			'max_uses'   => is_numeric( $data['max_uses'] ?? null ) ? max( 0, (int) $data['max_uses'] ) : 0,
			'button_ids' => implode( ',', $buttons ),
			'starts_at'  => $starts,
			'expires_at' => $expires,
			'active'     => array_key_exists( 'active', $data ) && empty( $data['active'] ) ? 0 : 1,
		);

		self::$button_cache = array();

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
			$ok = false !== $wpdb->update( ExtrasSchema::coupons_table(), $row, array( 'id' => $id ) );

			return $ok ? $id : new WP_Error( 'iftp_btn_coupon_db', __( 'Could not save the code.', 'ifthenpay-pay-button' ) );
		}

		$row['created_at'] = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		$ok = false !== $wpdb->insert( ExtrasSchema::coupons_table(), $row );

		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'iftp_btn_coupon_db', __( 'Could not save the code.', 'ifthenpay-pay-button' ) );
	}

	/**
	 * Switches a code on or off.
	 *
	 * @param int  $id     The code's id.
	 * @param bool $active Whether payers can use it.
	 */
	public function set_active( int $id, bool $active ): bool {
		global $wpdb;

		self::$button_cache = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		return false !== $wpdb->update( ExtrasSchema::coupons_table(), array( 'active' => $active ? 1 : 0 ), array( 'id' => $id ) );
	}

	/**
	 * Deletes a code. Payments that used it keep what they were charged; only the use record goes.
	 *
	 * @param int $id The code's id.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		self::$button_cache = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		$wpdb->delete( ExtrasSchema::uses_table(), array( 'coupon_id' => $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		return false !== $wpdb->delete( ExtrasSchema::coupons_table(), array( 'id' => $id ) );
	}

	/**
	 * How many payments hold a code right now: pending (not yet expired) or paid.
	 *
	 * @param int $id The code's id.
	 */
	public function used( int $id ): int {
		return $this->used_many( array( $id ) )[ $id ] ?? 0;
	}

	/**
	 * Uses for several codes at once.
	 *
	 * @param array<int, int> $ids The codes' ids.
	 * @return array<int, int> Keyed by id; codes with none are 0.
	 */
	public function used_many( array $ids ): array {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		$out = array_fill_keys( $ids, 0 );

		if ( array() === $ids ) {
			return $out;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %d per id, built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.coupon_id AS id, COUNT(*) AS n FROM %i u INNER JOIN %i t ON t.ref = u.ref WHERE t.status IN ('pending', 'paid') AND u.coupon_id IN ({$placeholders}) GROUP BY u.coupon_id", array_merge( array( ExtrasSchema::uses_table(), TransactionStore::table_name() ), $ids ) ), ARRAY_A );

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[ (int) $row['id'] ] = (int) $row['n'];
		}

		return $out;
	}

	/**
	 * Records that a payment used a code.
	 *
	 * @param int    $coupon_id The code's id.
	 * @param string $ref       The payment's reference.
	 * @param float  $discount  What it took off.
	 */
	public function record_use( int $coupon_id, string $ref, float $discount ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own small table.
		return false !== $wpdb->insert(
			ExtrasSchema::uses_table(),
			array(
				'coupon_id'  => $coupon_id,
				'ref'        => $ref,
				'discount'   => $discount,
				'created_at' => time(),
			)
		);
	}

	/**
	 * Whether a button has at least one code a payer could use, so its checkout shows the code box only then.
	 *
	 * @param int $button_id The button's post ID.
	 */
	public function offered_on( int $button_id ): bool {
		if ( isset( self::$button_cache[ $button_id ] ) ) {
			return self::$button_cache[ $button_id ];
		}

		$offered = false;
		$now     = time();

		foreach ( $this->all() as $coupon ) {
			if ( $coupon['active'] && ( 0 === $coupon['expires_at'] || $coupon['expires_at'] >= $now ) && ( array() === $coupon['button_ids'] || in_array( $button_id, $coupon['button_ids'], true ) ) ) {
				$offered = true;
				break;
			}
		}

		self::$button_cache[ $button_id ] = $offered;

		return $offered;
	}

	/**
	 * A raw row as a typed code.
	 *
	 * @param array<string, mixed> $row A table row.
	 * @return Coupon
	 */
	private static function hydrate( array $row ): array {
		$int   = static fn( string $key ): int => is_numeric( $row[ $key ] ?? null ) ? (int) $row[ $key ] : 0;
		$float = static fn( string $key ): float => is_numeric( $row[ $key ] ?? null ) ? (float) $row[ $key ] : 0.0;
		$ids   = is_string( $row['button_ids'] ?? null ) && '' !== $row['button_ids'] ? array_map( 'intval', explode( ',', $row['button_ids'] ) ) : array();

		return array(
			'id'         => $int( 'id' ),
			'code'       => is_string( $row['code'] ?? null ) ? $row['code'] : '',
			'type'       => self::TYPE_FIXED === ( $row['type'] ?? '' ) ? self::TYPE_FIXED : self::TYPE_PERCENT,
			'value'      => $float( 'value' ),
			'min_amount' => $float( 'min_amount' ),
			'max_uses'   => $int( 'max_uses' ),
			'button_ids' => $ids,
			'starts_at'  => $int( 'starts_at' ),
			'expires_at' => $int( 'expires_at' ),
			'active'     => 1 === $int( 'active' ),
			'created_at' => $int( 'created_at' ),
		);
	}
}
