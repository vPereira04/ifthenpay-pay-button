<?php
/**
 * Live tags: {first_name} or {field:first_name} in a form's own text, filled in as the payer types.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Buttons\FormFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the tags in a text block, a label or a help text into small spans the checkout script keeps up to date.
 * The text around them is escaped, and the script only ever sets textContent, so a typed value can't add markup.
 * A tag that isn't one of this form's fields stays as typed, so a typo is easy to spot.
 */
class LiveTags {

	/**
	 * {id}, {field:id}, and either with a fallback for while it's empty: {id|there}.
	 */
	private const PATTERN = '/\{(?:field:)?([a-z0-9_]{1,40})(?:\|([^{}|]{0,60}))?\}/';

	/**
	 * The ids a form's tags can name.
	 *
	 * @param array<int, array<string, mixed>> $items The form's items.
	 * @return array<int, string>
	 */
	public static function ids( array $items ): array {
		$ids = array();

		foreach ( $items as $item ) {
			if ( is_string( $item['id'] ?? null ) && in_array( $item['type'] ?? '', FormFields::INPUT_TYPES, true ) ) {
				$ids[] = $item['id'];
			}
		}

		return $ids;
	}

	/**
	 * Escapes a text and wraps its tags.
	 *
	 * @param string             $text The text as the merchant wrote it.
	 * @param array<int, string> $ids  From ids().
	 * @return string Safe HTML.
	 */
	public static function html( string $text, array $ids ): string {
		if ( false === strpos( $text, '{' ) || array() === $ids ) {
			return esc_html( $text );
		}

		$out  = '';
		$last = 0;

		if ( preg_match_all( self::PATTERN, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found as $match ) {
				if ( ! in_array( $match[1][0], $ids, true ) ) {
					continue;
				}

				$fallback = isset( $match[2][0] ) ? trim( $match[2][0] ) : '';

				$out .= esc_html( substr( $text, $last, $match[0][1] - $last ) );
				$out .= '<span class="iftp-btn-form__tag" data-iftp-btn-tag="' . esc_attr( FormFields::input_name( $match[1][0] ) ) . '" data-iftp-btn-fallback="' . esc_attr( $fallback ) . '">' . esc_html( $fallback ) . '</span>';
				$last = $match[0][1] + strlen( $match[0][0] );
			}
		}

		return $out . esc_html( substr( $text, $last ) );
	}
}
