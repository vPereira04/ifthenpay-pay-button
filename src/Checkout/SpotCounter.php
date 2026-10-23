<?php
/**
 * Counts a button's spots and guards the last ones.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Repository\TransactionStore;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What's left of a button's spots. A spot is taken by a paid payment, or by an unpaid one until its link
 * expires; it never comes back earlier, so a payer who still has the link open can't be oversold.
 *
 * @phpstan-type SpotsLeft array{total: int|null, options: array<string, int|null>}
 */
class SpotCounter {

	// MySQL caps lock names at 64 characters.
	private const LOCK_PREFIX  = 'iftp_btn_spots_';
	private const LOCK_TIMEOUT = 5;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the counter.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?TransactionStore $transactions = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Spots left overall and per option; null where there's no limit. An option's count already
	 * takes the overall one into account.
	 *
	 * @param Button                                              $button The button.
	 * @param array{total: int, options: array<string, int>}|null $taken  What it has taken, if the caller already counted.
	 * @return SpotsLeft
	 */
	public function left( Button $button, ?array $taken = null ): array {
		$spots = $button->config['spots'];
		$left  = array(
			'total'   => null,
			'options' => array(),
		);

		if ( Spots::MODE_NONE === $spots['mode'] ) {
			return $left;
		}

		$taken = $taken ?? ( $this->transactions->spots_taken( array( $button->id ) )[ $button->id ] ?? array(
			'total'   => 0,
			'options' => array(),
		) );

		if ( $spots['total'] > 0 ) {
			$left['total'] = max( 0, $spots['total'] - $taken['total'] );
		}

		if ( Spots::MODE_OPTIONS === $spots['mode'] ) {
			foreach ( $spots['options'] as $option ) {
				$own = $option['spots'] > 0 ? max( 0, $option['spots'] - ( $taken['options'][ $option['label'] ] ?? 0 ) ) : null;

				$left['options'][ $option['label'] ] = null === $own ? $left['total'] : ( null === $left['total'] ? $own : min( $own, $left['total'] ) );
			}
		}

		return $left;
	}

	/**
	 * Whether nothing can be bought any more.
	 *
	 * @param array<string, mixed> $left From left().
	 * @phpstan-param SpotsLeft $left
	 */
	public static function sold_out( array $left ): bool {
		if ( 0 === $left['total'] ) {
			return true;
		}

		return array() !== $left['options'] && array() === array_filter( $left['options'], static fn( ?int $count ): bool => 0 !== $count );
	}

	/**
	 * Whether this many spots on each picked option are still there, or why not.
	 *
	 * @param Button             $button  The button.
	 * @param array<int, string> $options The picked options; none for Limited spots.
	 * @param int                $count   How many spots on each.
	 * @return true|WP_Error
	 */
	public function check( Button $button, array $options, int $count ) {
		if ( Spots::MODE_NONE === $button->config['spots']['mode'] ) {
			return true;
		}

		$left = $this->left( $button );

		if ( Spots::MODE_OPTIONS === $button->config['spots']['mode'] ) {
			foreach ( $options as $option ) {
				$available = array_key_exists( $option, $left['options'] ) ? $left['options'][ $option ] : 0;

				if ( null !== $available && $available < $count ) {
					return 0 === $available
						? new WP_Error( 'iftp_btn_sold_out', CheckoutTexts::get( 'option_sold_out', array( 'option' => $option ) ), array( 'status' => 409 ) )
						: new WP_Error(
							'iftp_btn_spots_left',
							CheckoutTexts::get(
								'option_only_left',
								array(
									'count'  => $available,
									'option' => $option,
								)
							),
							array( 'status' => 409 )
						);
				}
			}
		}

		// Each ticked option takes its own spots out of the total.
		$wanted    = $count * max( 1, count( $options ) );
		$available = $left['total'];

		if ( null === $available || $available >= $wanted ) {
			return true;
		}

		if ( 0 === $available ) {
			return new WP_Error( 'iftp_btn_sold_out', CheckoutTexts::get( 'all_taken' ), array( 'status' => 409 ) );
		}

		return new WP_Error(
			'iftp_btn_spots_left',
			CheckoutTexts::get( 'only_left', array( 'count' => $available ) ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Takes the button's lock, so two payers can't both get its last spot. False when someone else held it too long.
	 *
	 * @param int $button_id The button's post ID.
	 */
	public static function lock( int $button_id ): bool {
		global $wpdb;

		// A row comes back only when the lock was refused (0).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a MySQL lock, nothing to cache.
		$refused = $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM DUAL WHERE GET_LOCK(%s, %d) = 0', self::lock_name( $button_id ), self::LOCK_TIMEOUT ) );

		// A host without locks gives NULL or an error, so no row; I'd rather take the payment than refuse everyone.
		return 1 !== $refused;
	}

	/**
	 * Gives the button's lock back.
	 *
	 * @param int $button_id The button's post ID.
	 */
	public static function unlock( int $button_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a MySQL lock, nothing to cache.
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name( $button_id ) ) );
	}

	/**
	 * The lock's name, unique to this site's table.
	 *
	 * @param int $button_id The button's post ID.
	 */
	private static function lock_name( int $button_id ): string {
		return substr( self::LOCK_PREFIX . md5( TransactionStore::table_name() ) . '_' . $button_id, 0, 64 );
	}
}
