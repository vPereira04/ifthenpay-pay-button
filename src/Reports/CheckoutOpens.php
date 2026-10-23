<?php
/**
 * How many times each button's checkout was opened.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Reports;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One counter per button, kept in its post meta, plus when it started counting. Paid payments since then,
 * against these opens, give the share of payers who opened the checkout and never paid.
 */
class CheckoutOpens {

	public const COUNT_META = '_iftp_btn_opens';
	public const SINCE_META = '_iftp_btn_opens_since';

	/**
	 * Adds one open. An UPDATE that adds in SQL, so two payers at once both count.
	 *
	 * @param int $button_id The button's post ID.
	 */
	public static function record( int $button_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- update_post_meta() would read, add and write back, losing opens that land together; the meta cache is cleared right below.
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s', $wpdb->postmeta, $button_id, self::COUNT_META ) );

		if ( 0 === $updated ) {
			add_post_meta( $button_id, self::COUNT_META, 1, true );
			add_post_meta( $button_id, self::SINCE_META, time(), true );
		}

		wp_cache_delete( $button_id, 'post_meta' );
	}

	/**
	 * Each button's opens and since when, for buttons that have any.
	 *
	 * @param array<int, int> $button_ids The buttons' post IDs.
	 * @return array<int, array{opens: int, since: int}>
	 */
	public static function counts( array $button_ids ): array {
		$counts = array();

		update_meta_cache( 'post', $button_ids );

		foreach ( $button_ids as $id ) {
			$opens = (int) get_post_meta( $id, self::COUNT_META, true );

			if ( $opens > 0 ) {
				$counts[ $id ] = array(
					'opens' => $opens,
					'since' => (int) get_post_meta( $id, self::SINCE_META, true ),
				);
			}
		}

		return $counts;
	}

	/**
	 * The share of opens that didn't end in a payment, 0 to 1; null before anyone opened the checkout.
	 *
	 * @param int $opens Checkout opens.
	 * @param int $paid  Paid payments since counting began.
	 */
	public static function abandon_rate( int $opens, int $paid ): ?float {
		return $opens > 0 ? max( 0.0, min( 1.0, ( $opens - $paid ) / $opens ) ) : null;
	}
}
