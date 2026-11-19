<?php
/**
 * A button's spots: how many people can pay, in total or per option.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three modes: no limit, one shared number of spots, or spots (and prices) split across the options of one
 * choice field. Any mode can let the payer take more than one spot in a payment. With Multiple choice, each
 * ticked option takes its own spots and adds its own price.
 *
 * @phpstan-import-type FormItem from FormFields
 * @phpstan-type SpotOption array{label: string, spots: int, price: float}
 * @phpstan-type SpotsConfig array{mode: string, total: int, field: string, options: array<int, SpotOption>, quantity: bool, max_each: int, show_left: bool, left_below: int}
 */
class Spots {

	public const MODE_NONE    = 'none';
	public const MODE_TOTAL   = 'total';
	public const MODE_OPTIONS = 'options';
	public const MODES        = array( self::MODE_NONE, self::MODE_TOTAL, self::MODE_OPTIONS );

	// Field types whose answer picks options.
	public const FIELD_TYPES = array( 'select', 'radio', 'checkboxes' );

	public const MAX_SPOTS = 100000;
	public const MAX_EACH  = 50;

	/**
	 * A new button's spots: no limit.
	 *
	 * @return SpotsConfig
	 */
	public static function defaults(): array {
		return array(
			'mode'       => self::MODE_NONE,
			'total'      => 0,
			'field'      => '',
			'options'    => array(),
			'quantity'   => false,
			'max_each'   => 10,
			'show_left'  => true,
			'left_below' => 10,
		);
	}

	/**
	 * Cleans spots from the editor or the database, and lines them up with the button's form.
	 *
	 * @param mixed                $input The raw spots.
	 * @param array<int, FormItem> $form  The button's sanitized form.
	 * @return SpotsConfig
	 */
	public static function sanitize( $input, array $form ): array {
		$spots = self::defaults();

		if ( ! is_array( $input ) ) {
			return $spots;
		}

		$count = static fn( $value, int $max ): int => is_numeric( $value ) ? max( 0, min( $max, (int) $value ) ) : 0;

		if ( is_string( $input['mode'] ?? null ) && in_array( $input['mode'], self::MODES, true ) ) {
			$spots['mode'] = $input['mode'];
		}

		$spots['total']      = $count( $input['total'] ?? 0, self::MAX_SPOTS );
		$spots['field']      = is_scalar( $input['field'] ?? null ) ? sanitize_key( (string) $input['field'] ) : '';
		$spots['quantity']   = ! empty( $input['quantity'] );
		$spots['max_each']   = max( 1, $count( $input['max_each'] ?? 10, self::MAX_EACH ) );
		$spots['show_left']  = ! empty( $input['show_left'] ) || ! array_key_exists( 'show_left', $input );
		$spots['left_below'] = max( 1, $count( $input['left_below'] ?? 10, self::MAX_SPOTS ) );

		$saved = array();

		foreach ( is_array( $input['options'] ?? null ) ? $input['options'] : array() as $raw ) {
			if ( ! is_array( $raw ) || ! is_scalar( $raw['label'] ?? null ) ) {
				continue;
			}

			$label           = mb_substr( trim( sanitize_text_field( (string) $raw['label'] ) ), 0, 100 );
			$price           = is_numeric( $raw['price'] ?? null ) ? round( max( 0.0, min( Button::MAX_AMOUNT, (float) $raw['price'] ) ), 2 ) : 0.0;
			$saved[ $label ] = array(
				'label' => $label,
				'spots' => $count( $raw['spots'] ?? 0, self::MAX_SPOTS ),
				'price' => $price,
			);
		}

		// The options always follow the field's own, in its order; one the field lost goes, a new one starts open.
		$item             = self::find_field( $form, $spots['field'] );
		$spots['options'] = array();

		foreach ( null !== $item ? $item['options'] : array() as $option ) {
			$spots['options'][] = $saved[ $option ] ?? array(
				'label' => $option,
				'spots' => 0,
				'price' => 0.0,
			);
		}

		if ( self::MODE_TOTAL === $spots['mode'] && 0 === $spots['total'] ) {
			$spots['mode'] = self::MODE_NONE;
		}

		if ( self::MODE_OPTIONS === $spots['mode'] && null === $item ) {
			$spots['mode'] = self::MODE_NONE;
		}

		return $spots;
	}

	/**
	 * The form with the spots field made required and always shown, since every payment has to land on an option.
	 *
	 * @param array<int, FormItem> $form  The sanitized form.
	 * @param array<string, mixed> $spots The sanitized spots.
	 * @phpstan-param SpotsConfig $spots
	 * @return array<int, FormItem>
	 */
	public static function require_field( array $form, array $spots ): array {
		if ( self::MODE_OPTIONS !== $spots['mode'] ) {
			return $form;
		}

		$page = -1;

		foreach ( $form as $index => $item ) {
			if ( FormFields::TYPE_PAGE === $item['type'] ) {
				$page = $index;
			}

			if ( $item['id'] === $spots['field'] ) {
				// Always asked, too: a payer who never sees it couldn't pick a spot, so neither it nor its page can be hidden.
				$form[ $index ]['required'] = true;
				$form[ $index ]['when']     = FormFields::no_condition();

				if ( $page >= 0 ) {
					$form[ $page ]['when'] = FormFields::no_condition();
				}
			}
		}

		return $form;
	}

	/**
	 * The choice field with this id, or null.
	 *
	 * @param array<int, FormItem> $form The form.
	 * @param string               $id   The field's id.
	 * @return FormItem|null
	 */
	public static function find_field( array $form, string $id ): ?array {
		if ( '' === $id ) {
			return null;
		}

		foreach ( $form as $item ) {
			if ( $item['id'] === $id && in_array( $item['type'], self::FIELD_TYPES, true ) && array() !== $item['options'] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Whether the button counts spots at all.
	 *
	 * @param Button $button The button.
	 */
	public static function limited( Button $button ): bool {
		return self::MODE_NONE !== $button->config['spots']['mode'] || array() !== self::extra_limits( $button );
	}

	/**
	 * The id of the main Spots field, or '' when the options aren't split by one.
	 *
	 * @param Button $button The button.
	 */
	public static function main_field( Button $button ): string {
		return self::MODE_OPTIONS === $button->config['spots']['mode'] ? $button->config['spots']['field'] : '';
	}

	/**
	 * The spots set on the options of the other choice fields, by what they're counted under.
	 *
	 * @param Button $button The button.
	 * @return array<string, int>
	 */
	public static function extra_limits( Button $button ): array {
		$main   = self::main_field( $button );
		$limits = array();

		foreach ( $button->config['form'] as $item ) {
			if ( $item['id'] === $main ) {
				continue;
			}

			foreach ( $item['limits'] as $label => $limit ) {
				if ( in_array( (string) $label, $item['options'], true ) ) {
					$limits[ FormFields::spot_key( $item['id'], (string) $label ) ] = $limit;
				}
			}
		}

		return $limits;
	}

	/**
	 * What a payment's picks on the other fields are counted under: only options that have spots.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $input  The raw input.
	 * @return array<int, string>
	 */
	public static function extra_keys( Button $button, array $input ): array {
		$limits = self::extra_limits( $button );
		$keys   = array();

		foreach ( FormFields::picks( $button->config['form'], self::main_field( $button ), $input ) as $pick ) {
			$key = FormFields::spot_key( $pick['item']['id'], $pick['label'] );

			if ( isset( $limits[ $key ] ) ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * The option a payer picked, by its label, or null when it isn't one.
	 *
	 * @param Button $button The button.
	 * @param string $label  The picked option.
	 * @return SpotOption|null
	 */
	public static function option( Button $button, string $label ): ?array {
		foreach ( $button->config['spots']['options'] as $option ) {
			if ( $option['label'] === $label ) {
				return $option;
			}
		}

		return null;
	}

	/**
	 * What one spot costs: the picked option's own price, else the button's amount. Only a fixed amount has a price.
	 *
	 * @param Button $button The button.
	 * @param string $label  The picked option, or ''.
	 */
	public static function unit_price( Button $button, string $label ): float {
		$option = self::MODE_OPTIONS === $button->config['spots']['mode'] ? self::option( $button, $label ) : null;

		return null !== $option && $option['price'] > 0 ? $option['price'] : $button->config['amount'];
	}

	/**
	 * The cheapest and dearest spot, for "from 20 €" on the button.
	 *
	 * @param Button $button The button.
	 * @return array{0: float, 1: float}
	 */
	public static function price_range( Button $button ): array {
		if ( self::MODE_OPTIONS !== $button->config['spots']['mode'] || array() === $button->config['spots']['options'] ) {
			return array( $button->config['amount'], $button->config['amount'] );
		}

		$prices = array_map( static fn( array $option ): float => self::unit_price( $button, $option['label'] ), $button->config['spots']['options'] );

		return array( min( $prices ), max( $prices ) );
	}

	/**
	 * How many spots the button has in all, or 0 when there's no overall number.
	 *
	 * @param Button $button The button.
	 */
	public static function capacity( Button $button ): int {
		$spots = $button->config['spots'];

		if ( self::MODE_TOTAL === $spots['mode'] || ( self::MODE_OPTIONS === $spots['mode'] && $spots['total'] > 0 ) ) {
			return $spots['total'];
		}

		if ( self::MODE_OPTIONS !== $spots['mode'] ) {
			return 0;
		}

		// No total: the options add up, but only when every one of them has its own number.
		$counts = array_column( $spots['options'], 'spots' );

		return in_array( 0, $counts, true ) ? 0 : (int) array_sum( $counts );
	}
}
