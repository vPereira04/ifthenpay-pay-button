<?php
/**
 * WP-CLI: discount codes.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cli;

use Ifthenpay\PayButton\Repository\CouponStore;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Discount codes screen, from a terminal.
 */
class CodesCommand {

	/**
	 * Codes storage.
	 *
	 * @var CouponStore
	 */
	private CouponStore $coupons;

	/**
	 * Sets up the command.
	 *
	 * @param CouponStore|null $coupons Codes storage.
	 */
	public function __construct( ?CouponStore $coupons = null ) {
		$this->coupons = $coupons ?? new CouponStore();
	}

	/**
	 * Lists the discount codes.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json, yaml or count. Default table.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );

		$all  = $this->coupons->all();
		$used = $this->coupons->used_many( array_column( $all, 'id' ) );
		$rows = array();

		foreach ( $all as $coupon ) {
			$rows[] = array(
				'id'      => $coupon['id'],
				'code'    => $coupon['code'],
				'type'    => $coupon['type'],
				'value'   => $coupon['value'],
				'used'    => $used[ $coupon['id'] ] ?? 0,
				'max'     => $coupon['max_uses'] > 0 ? $coupon['max_uses'] : '',
				'ends'    => $coupon['expires_at'] > 0 ? wp_date( 'Y-m-d', $coupon['expires_at'] ) : '',
				'buttons' => implode( ',', $coupon['button_ids'] ),
				'active'  => $coupon['active'] ? 'yes' : 'no',
			);
		}

		Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'code', 'type', 'value', 'used', 'max', 'ends', 'buttons', 'active' ) );
	}

	/**
	 * Adds a discount code.
	 *
	 * ## OPTIONS
	 *
	 * <code>
	 * : The code, 3 to 40 letters, numbers, dashes or underscores.
	 *
	 * --percent=<n>
	 * : Takes this percentage off. Use this or --amount.
	 *
	 * --amount=<euros>
	 * : Takes this many euros off. Use this or --percent.
	 *
	 * [--min=<euros>]
	 * : Works from this total.
	 *
	 * [--max-uses=<n>]
	 * : Can be used this many times in all.
	 *
	 * [--from=<date>]
	 * : First valid day, YYYY-MM-DD.
	 *
	 * [--until=<date>]
	 * : Last valid day, YYYY-MM-DD, included.
	 *
	 * [--buttons=<ids>]
	 * : Comma-separated button IDs. Default: every button.
	 *
	 * [--off]
	 * : Add it switched off.
	 *
	 * ## EXAMPLES
	 *
	 *     wp iftp-btn codes create SUMMER10 --percent=10 --until=2026-08-31
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function create( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['percent'] ) === isset( $assoc_args['amount'] ) ) {
			WP_CLI::error( 'Give either --percent or --amount.' );
		}

		$day = static function ( string $flag, bool $until ) use ( $assoc_args ): int {
			if ( ! isset( $assoc_args[ $flag ] ) ) {
				return 0;
			}

			$date = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $assoc_args[ $flag ] ) ? date_create_immutable( $assoc_args[ $flag ] . ( $until ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() ) : false;

			if ( false === $date ) {
				WP_CLI::error( '--' . $flag . ' must look like 2026-11-25.' );
			}

			return $date->getTimestamp();
		};

		$id = $this->coupons->save(
			array(
				'code'       => $args[0],
				'type'       => isset( $assoc_args['percent'] ) ? CouponStore::TYPE_PERCENT : CouponStore::TYPE_FIXED,
				'value'      => $assoc_args['percent'] ?? $assoc_args['amount'],
				'min_amount' => $assoc_args['min'] ?? 0,
				'max_uses'   => $assoc_args['max-uses'] ?? 0,
				'button_ids' => isset( $assoc_args['buttons'] ) ? array_map( 'absint', explode( ',', $assoc_args['buttons'] ) ) : array(),
				'starts_at'  => $day( 'from', false ),
				'expires_at' => $day( 'until', true ),
				'active'     => ! isset( $assoc_args['off'] ),
			)
		);

		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Added code %s (id %d).', CouponStore::normalize( $args[0] ), $id ) );
	}

	/**
	 * Switches a code on.
	 *
	 * ## OPTIONS
	 *
	 * <code>
	 * : The code.
	 *
	 * @param array<int, string> $args Positional args.
	 */
	public function enable( array $args ): void {
		$this->toggle( $args[0], true );
	}

	/**
	 * Switches a code off.
	 *
	 * ## OPTIONS
	 *
	 * <code>
	 * : The code.
	 *
	 * @param array<int, string> $args Positional args.
	 */
	public function disable( array $args ): void {
		$this->toggle( $args[0], false );
	}

	/**
	 * Deletes a code. Payments that used it keep what they paid.
	 *
	 * ## OPTIONS
	 *
	 * <code>
	 * : The code.
	 *
	 * @param array<int, string> $args Positional args.
	 */
	public function delete( array $args ): void {
		$coupon = $this->coupons->find_by_code( $args[0] );

		if ( null === $coupon ) {
			WP_CLI::error( 'No such code.' );
		}

		$this->coupons->delete( $coupon['id'] );
		WP_CLI::success( 'Deleted ' . $coupon['code'] . '.' );
	}

	/**
	 * Switches a code on or off.
	 *
	 * @param string $code   The code.
	 * @param bool   $active Whether payers can use it.
	 */
	private function toggle( string $code, bool $active ): void {
		$coupon = $this->coupons->find_by_code( $code );

		if ( null === $coupon ) {
			WP_CLI::error( 'No such code.' );
		}

		$this->coupons->set_active( $coupon['id'], $active );
		WP_CLI::success( $coupon['code'] . ( $active ? ' is on.' : ' is off.' ) );
	}
}
