<?php
/**
 * The admin's own Entries layout, from editor mode.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which blocks show, in what order, and how Performance and the status cards look — per admin, in user meta.
 * The entries table is always there and always last: it's the screen's reason to exist.
 *
 * @phpstan-type LayoutData array{order: array<int, string>, hidden: array<int, string>, perf: array{height: string, headline: bool, compare: bool, period: string}, status: array{deltas: bool}}
 */
class Layout {

	public const META = 'iftp_btn_layout';

	// Every block editor mode knows, in default order. The table isn't here: it's fixed.
	public const BLOCKS = array( 'performance', 'goal', 'waiting', 'methods', 'status' );

	// Blocks that sit two to a row when they're next to each other and both showing.
	public const HALF_BLOCKS = array( 'goal', 'waiting' );

	public const HEIGHTS = array( 'compact', 'regular', 'tall' );
	public const PERIODS = array( '7d', '30d', '12m' );

	/**
	 * The default layout: everything showing, in the default order.
	 *
	 * @return LayoutData
	 */
	public static function defaults(): array {
		return array(
			'order'  => self::BLOCKS,
			'hidden' => array(),
			'perf'   => array(
				'height'   => 'regular',
				'headline' => true,
				'compare'  => true,
				'period'   => '7d',
			),
			'status' => array( 'deltas' => true ),
		);
	}

	/**
	 * The current admin's layout, cleaned.
	 *
	 * @return LayoutData
	 */
	public static function current(): array {
		return self::sanitize( get_user_meta( get_current_user_id(), self::META, true ) );
	}

	/**
	 * Saves a layout for the current admin; the defaults are stored as nothing at all.
	 *
	 * @param mixed $raw A decoded layout from the browser.
	 * @return LayoutData What was stored.
	 */
	public static function save( $raw ): array {
		$layout = self::sanitize( $raw );

		if ( self::defaults() === $layout ) {
			delete_user_meta( get_current_user_id(), self::META );
		} else {
			update_user_meta( get_current_user_id(), self::META, $layout );
		}

		return $layout;
	}

	/**
	 * Keeps only known blocks and values; anything missing falls back to the default.
	 *
	 * @param mixed $raw A stored or posted layout.
	 * @return LayoutData
	 */
	public static function sanitize( $raw ): array {
		$layout = self::defaults();
		$raw    = is_array( $raw ) ? $raw : array();
		$order  = isset( $raw['order'] ) && is_array( $raw['order'] ) ? array_values( array_unique( array_intersect( array_filter( $raw['order'], 'is_string' ), self::BLOCKS ) ) ) : array();
		$hidden = isset( $raw['hidden'] ) && is_array( $raw['hidden'] ) ? array_values( array_unique( array_intersect( array_filter( $raw['hidden'], 'is_string' ), self::BLOCKS ) ) ) : array();
		$perf   = isset( $raw['perf'] ) && is_array( $raw['perf'] ) ? $raw['perf'] : array();
		$status = isset( $raw['status'] ) && is_array( $raw['status'] ) ? $raw['status'] : array();
		$height = (string) ( $perf['height'] ?? '' );
		$period = (string) ( $perf['period'] ?? '' );

		$layout['order']  = array_merge( $order, array_values( array_diff( self::BLOCKS, $order ) ) );
		$layout['hidden'] = $hidden;

		$layout['perf'] = array(
			'height'   => in_array( $height, self::HEIGHTS, true ) ? $height : 'regular',
			'headline' => ! array_key_exists( 'headline', $perf ) || (bool) $perf['headline'],
			'compare'  => ! array_key_exists( 'compare', $perf ) || (bool) $perf['compare'],
			'period'   => in_array( $period, self::PERIODS, true ) ? $period : '7d',
		);

		$layout['status'] = array( 'deltas' => ! array_key_exists( 'deltas', $status ) || (bool) $status['deltas'] );

		return $layout;
	}

	/**
	 * The half blocks that pair up: next to each other among the showing blocks, both showing.
	 * The rest take the full row. Mirrored by entries.js while editing.
	 *
	 * @param array<int, string> $order     Block order, only the blocks on the page.
	 * @param array<int, string> $hidden    Hidden blocks.
	 * @return array<int, string> The blocks that render half width.
	 */
	public static function paired( array $order, array $hidden ): array {
		$showing = array_values( array_diff( $order, $hidden ) );
		$last    = count( $showing ) - 1;
		$paired  = array();

		for ( $i = 0; $i < $last; $i++ ) {
			if ( in_array( $showing[ $i ], self::HALF_BLOCKS, true ) && in_array( $showing[ $i + 1 ], self::HALF_BLOCKS, true ) ) {
				$paired[] = $showing[ $i ];
				$paired[] = $showing[ $i + 1 ];
				++$i;
			}
		}

		return $paired;
	}
}
