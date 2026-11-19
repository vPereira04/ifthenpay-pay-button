<?php
/**
 * A button's own checkout fields and pages.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The checkout as an ordered list of items: the two built-in blocks (the amount, and the payer details the
 * Payer details card switches on), the merchant's own fields, text blocks, and page breaks that split it into steps.
 *
 * Any field, text block or page can show only when an earlier answer meets a rule: a choice is (or isn't) one of some
 * options, a number is more or less than something, a field is filled in or left empty. A hidden page takes its fields with it.
 * Number and text fields can also carry limits (a largest number, a longest text) the server holds the payer to.
 *
 * @phpstan-type FormCondition array{field: string, op: string, values: array<int, string>}
 * @phpstan-type FormRules array{min: string, max: string, whole: bool, min_len: int, max_len: int}
 * @phpstan-type FormItem array{id: string, type: string, label: string, required: bool, placeholder: string, help: string, options: array<int, string>, prices: array<string, float>, limits: array<string, int>, images: array<string, int>, pictures: bool, picture_shape: string, picture_layout: string, picture_cols: int, picture_size: string, icon: string, width: string, when: FormCondition, rules: FormRules}
 * @phpstan-type FormStep array{id: string, title: string, items: array<int, FormItem>, when: FormCondition}
 */
class FormFields {

	// The two blocks every checkout has. They move, but can't be removed.
	public const ANCHOR_AMOUNT  = 'amount';
	public const ANCHOR_DETAILS = 'details';
	public const ANCHORS        = array( self::ANCHOR_AMOUNT, self::ANCHOR_DETAILS );

	public const TYPE_CONTENT = 'content';
	public const TYPE_PAGE    = 'page';

	// Types the payer fills in.
	public const INPUT_TYPES = array( 'text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkboxes', 'checkbox' );

	// Types whose answer comes from a list.
	public const CHOICE_TYPES = array( 'select', 'radio', 'checkboxes' );

	// Types another item can show or hide by: any input. An agreement's only answer is "ticked".
	public const CONDITION_TYPES = self::INPUT_TYPES;
	// is / not: one of the options, the number, or exactly the text; gt / lt: a number more or less than one; contains: a text with these words in it; filled / empty: any answer or none.
	public const CONDITION_OPS = array( 'is', 'not', 'gt', 'lt', 'contains', 'filled', 'empty' );
	public const VALUELESS_OPS = array( 'filled', 'empty' );
	public const TICKED        = '1';

	// The most characters any text field takes, and the largest number a limit can be.
	public const MAX_TEXT   = 1000;
	public const MAX_NUMBER = 1000000000;

	public const MAX_ITEMS   = 40;
	public const MAX_OPTIONS = 30;

	// Types whose options can carry a picture, which turns them into cards.
	public const IMAGE_TYPES = array( 'radio', 'checkboxes' );

	/**
	 * How a picture card is shaped: Auto follows the pictures when they all share one shape.
	 */
	public const PICTURE_SHAPES = array( 'auto', 'landscape', 'square', 'portrait' );

	/**
	 * How the picture cards are laid out (a grid, or one row that scrolls sideways) and how big they are.
	 */
	public const PICTURE_LAYOUTS = array( 'grid', 'scroll' );
	public const PICTURE_SIZES   = array( 'small', 'medium', 'large' );
	public const PICTURE_MAX_COLS = 6;

	// What the browser joins a checkboxes answer with; option labels can hold commas, never a newline.
	public const MULTI_SEPARATOR = "\n";

	/**
	 * A new button's form: the amount, then the payer's details.
	 *
	 * @return array<int, FormItem>
	 */
	public static function defaults(): array {
		return array( self::anchor( self::ANCHOR_AMOUNT ), self::anchor( self::ANCHOR_DETAILS ) );
	}

	/**
	 * What each type is called in the editor.
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			'text'       => __( 'Short text', 'ifthenpay-pay-button' ),
			'textarea'   => __( 'Paragraph', 'ifthenpay-pay-button' ),
			'email'      => __( 'Email', 'ifthenpay-pay-button' ),
			'tel'        => __( 'Phone', 'ifthenpay-pay-button' ),
			'number'     => __( 'Number', 'ifthenpay-pay-button' ),
			'date'       => __( 'Date', 'ifthenpay-pay-button' ),
			'select'     => __( 'Dropdown', 'ifthenpay-pay-button' ),
			'radio'      => __( 'Single choice', 'ifthenpay-pay-button' ),
			'checkboxes' => __( 'Multiple choice', 'ifthenpay-pay-button' ),
			'checkbox'   => __( 'Agreement', 'ifthenpay-pay-button' ),
			'content'    => __( 'Text block', 'ifthenpay-pay-button' ),
			'page'       => __( 'Page break', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * Cleans a form from anywhere: the editor's JSON, or the database. Always comes back with both anchors,
	 * unique ids, and page breaks only where they split something.
	 *
	 * @param mixed $input The raw list, or its JSON.
	 * @return array<int, FormItem>
	 */
	public static function sanitize( $input ): array {
		if ( is_string( $input ) ) {
			$input = json_decode( $input, true );
		}

		$items   = array();
		$seen    = array();
		$anchors = array();

		foreach ( is_array( $input ) ? array_slice( $input, 0, self::MAX_ITEMS + 2 ) : array() as $raw ) {
			$item = is_array( $raw ) ? self::sanitize_item( $raw ) : null;

			if ( null === $item ) {
				continue;
			}

			if ( in_array( $item['type'], self::ANCHORS, true ) ) {
				if ( isset( $anchors[ $item['type'] ] ) ) {
					continue;
				}

				$anchors[ $item['type'] ] = true;
			} else {
				$item['id'] = self::unique_id( $item['id'], $seen, count( $items ) + 1 );
			}

			$seen[ $item['id'] ] = true;
			$items[]             = $item;
		}

		// An old or broken form gets back whichever anchor it lost, where a new one would have it.
		if ( ! isset( $anchors[ self::ANCHOR_AMOUNT ] ) ) {
			array_unshift( $items, self::anchor( self::ANCHOR_AMOUNT ) );
		}

		if ( ! isset( $anchors[ self::ANCHOR_DETAILS ] ) ) {
			array_splice( $items, (int) array_search( self::ANCHOR_AMOUNT, array_column( $items, 'type' ), true ) + 1, 0, array( self::anchor( self::ANCHOR_DETAILS ) ) );
		}

		return self::tidy_conditions( self::tidy_pages( $items ) );
	}

	/**
	 * What a condition can compare, by the controlling field's type: the ops it offers in the editor.
	 *
	 * @param string $type The controlling field's type.
	 * @return array<int, string>
	 */
	public static function ops_for( string $type ): array {
		if ( in_array( $type, self::CHOICE_TYPES, true ) || 'checkbox' === $type ) {
			return array( 'is', 'not' );
		}

		return 'number' === $type ? array( 'gt', 'lt', 'is', 'not', 'filled', 'empty' ) : array( 'is', 'not', 'contains', 'filled', 'empty' );
	}

	/**
	 * Whether a field's answer is typed text, which conditions compare without minding capitals.
	 *
	 * @param string $type The field's type.
	 */
	public static function is_typed( string $type ): bool {
		return ! in_array( $type, self::CHOICE_TYPES, true ) && 'checkbox' !== $type;
	}

	/**
	 * Whether an answer meets a condition.
	 *
	 * @param FormCondition      $when   The condition.
	 * @param array<int, string> $picked What the field holds: its picked options, TICKED, one number or text, or nothing.
	 * @param bool               $typed  Whether it is typed text or a number, so capitals don't matter.
	 */
	public static function meets( array $when, array $picked, bool $typed = false ): bool {
		$op = $when['op'];

		if ( 'filled' === $op ) {
			return array() !== $picked;
		}

		if ( 'empty' === $op ) {
			return array() === $picked;
		}

		if ( 'gt' === $op || 'lt' === $op ) {
			$have = $picked[0] ?? '';
			$want = $when['values'][0] ?? '';

			if ( ! is_numeric( $have ) || ! is_numeric( $want ) ) {
				return false;
			}

			return 'gt' === $op ? (float) $have > (float) $want : (float) $have < (float) $want;
		}

		if ( 'contains' === $op ) {
			$want = mb_strtolower( $when['values'][0] ?? '' );

			return '' !== $want && false !== mb_strpos( mb_strtolower( $picked[0] ?? '' ), $want );
		}

		$match = array() !== array_intersect( $picked, $when['values'] );

		if ( ! $match && $typed && isset( $picked[0], $when['values'][0] ) ) {
			$match = mb_strtolower( $picked[0] ) === mb_strtolower( $when['values'][0] );
		}

		// A number is the same number however it was typed: 5, 5.0 and 5,00.
		if ( ! $match && is_numeric( $picked[0] ?? null ) ) {
			foreach ( $when['values'] as $value ) {
				$match = $match || ( is_numeric( $value ) && (float) $value === (float) $picked[0] );
			}
		}

		return 'not' === $op ? ! $match : $match;
	}

	/**
	 * Which items show for these answers, by id. An item whose field is hidden is hidden too, so a chain
	 * of conditions folds up from the top, and a page that is hidden takes every item on it along.
	 *
	 * @param array<int, FormItem>              $items   A sanitized form.
	 * @param array<string, array<int, string>> $answers What each field holds, by id.
	 * @return array<string, bool>
	 */
	public static function visibility( array $items, array $answers ): array {
		$shown = array();
		$types = array();
		$page  = true;

		foreach ( $items as $item ) {
			$when = $item['when'];
			$show = '' === $when['field'] || ( ( $shown[ $when['field'] ] ?? false ) && self::meets( $when, $answers[ $when['field'] ] ?? array(), self::is_typed( $types[ $when['field'] ] ?? 'select' ) ) );

			$types[ $item['id'] ] = $item['type'];

			if ( self::TYPE_PAGE === $item['type'] ) {
				$page = $show;

				$shown[ $item['id'] ] = $show;

				continue;
			}

			$shown[ $item['id'] ] = $page && $show;
		}

		return $shown;
	}

	/**
	 * The answers visibility() reads, from what a browser or a pay link sent.
	 *
	 * @param array<int, FormItem> $items A sanitized form.
	 * @param array<string, mixed> $input Raw values, keyed by input name.
	 * @return array<string, array<int, string>>
	 */
	public static function answers( array $items, array $input ): array {
		$answers = array();

		foreach ( $items as $item ) {
			if ( ! in_array( $item['type'], self::CONDITION_TYPES, true ) ) {
				continue;
			}

			$raw = is_scalar( $input[ self::input_name( $item['id'] ) ] ?? null ) ? trim( (string) $input[ self::input_name( $item['id'] ) ] ) : '';

			if ( 'checkbox' === $item['type'] ) {
				$answers[ $item['id'] ] = '' !== $raw && '0' !== $raw ? array( self::TICKED ) : array();

				continue;
			}

			// A number or a text is one answer, so a comma in it stays; a number may be typed with a decimal comma.
			if ( ! in_array( $item['type'], self::CHOICE_TYPES, true ) ) {
				$text = trim( sanitize_text_field( $raw ) );
				$text = 'number' === $item['type'] ? str_replace( ',', '.', $text ) : $text;

				$answers[ $item['id'] ] = '' !== $text ? array( $text ) : array();

				continue;
			}

			// A pay link joins ticked boxes with commas, the browser with newlines.
			$parts = 'checkboxes' === $item['type'] && false === strpos( $raw, self::MULTI_SEPARATOR ) ? explode( ',', $raw ) : explode( self::MULTI_SEPARATOR, $raw );

			$answers[ $item['id'] ] = array_values( array_filter( array_map( static fn( string $part ): string => trim( sanitize_text_field( $part ) ), $parts ) ) );
		}

		return $answers;
	}

	/**
	 * No condition: the item always shows.
	 *
	 * @return FormCondition
	 */
	public static function no_condition(): array {
		return array(
			'field'  => '',
			'op'     => 'is',
			'values' => array(),
		);
	}

	/**
	 * The form split at its page breaks. A form without breaks is one step. A page keeps the condition of the break before it.
	 *
	 * @param array<int, FormItem> $items      A sanitized form.
	 * @param string               $first_step The first step's title.
	 * @return array<int, FormStep>
	 */
	public static function steps( array $items, string $first_step ): array {
		$steps = array(
			array(
				'id'    => '',
				'title' => $first_step,
				'items' => array(),
				'when'  => self::no_condition(),
			),
		);

		foreach ( $items as $item ) {
			if ( self::TYPE_PAGE === $item['type'] ) {
				$steps[] = array(
					'id'    => $item['id'],
					'title' => $item['label'],
					'items' => array(),
					'when'  => $item['when'],
				);

				continue;
			}

			$steps[ count( $steps ) - 1 ]['items'][] = $item;
		}

		return $steps;
	}

	/**
	 * Only the items a payer answers, in order.
	 *
	 * @param array<int, FormItem> $items A sanitized form.
	 * @return array<int, FormItem>
	 */
	public static function inputs( array $items ): array {
		return array_values( array_filter( $items, static fn( array $item ): bool => in_array( $item['type'], self::INPUT_TYPES, true ) ) );
	}

	/**
	 * The name a field's value travels under, kept apart from the built-in fields.
	 *
	 * @param string $id The field's id.
	 */
	public static function input_name( string $id ): string {
		return 'cf_' . $id;
	}

	/**
	 * An agreement's label as plain text, its [text](https://link) parts reduced to the text.
	 *
	 * @param string $label The stored label.
	 */
	public static function plain_label( string $label ): string {
		return (string) preg_replace( '/\[([^\]]+)\]\(https?:\/\/[^\s)]+\)/', '$1', $label );
	}

	/**
	 * An agreement's label with its [text](https://link) parts as links, escaped.
	 *
	 * @param string $label The stored label.
	 */
	public static function linked_label( string $label ): string {
		$out = '';
		$at  = 0;

		if ( preg_match_all( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', $label, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found as $match ) {
				$out .= esc_html( substr( $label, $at, $match[0][1] - $at ) )
					. '<a href="' . esc_url( $match[2][0] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $match[1][0] ) . '</a>';
				$at   = $match[0][1] + strlen( $match[0][0] );
			}
		}

		return $out . esc_html( substr( $label, $at ) );
	}

	/**
	 * One item, cleaned, or null when it isn't a known type.
	 *
	 * @param array<string, mixed> $raw The raw item.
	 * @return FormItem|null
	 */
	private static function sanitize_item( array $raw ): ?array {
		$type = is_string( $raw['type'] ?? null ) ? $raw['type'] : '';

		if ( in_array( $type, self::ANCHORS, true ) ) {
			return self::anchor( $type );
		}

		if ( ! in_array( $type, array_merge( self::INPUT_TYPES, array( self::TYPE_CONTENT, self::TYPE_PAGE ) ), true ) ) {
			return null;
		}

		$text    = static fn( string $key, int $max ): string => is_scalar( $raw[ $key ] ?? null ) ? mb_substr( trim( sanitize_text_field( (string) $raw[ $key ] ) ), 0, $max ) : '';
		$options = $raw['options'] ?? array();
		$options = is_string( $options ) ? explode( "\n", $options ) : ( is_array( $options ) ? $options : array() );
		$options = self::split_options( $options, is_array( $raw['prices'] ?? null ) ? $raw['prices'] : array(), is_array( $raw['limits'] ?? null ) ? $raw['limits'] : array(), is_array( $raw['images'] ?? null ) ? $raw['images'] : array() );
		$prices  = $options['prices'];
		$limits  = $options['limits'];
		$images  = $options['images'];
		$options = $options['labels'];
		$label   = $text( 'label', self::TYPE_CONTENT === $type ? 160 : ( 'checkbox' === $type ? 300 : 120 ) );

		if ( '' === $label ) {
			$label = self::TYPE_PAGE === $type ? __( 'More details', 'ifthenpay-pay-button' ) : ( self::type_labels()[ $type ] ?? '' );
		}

		$help = is_scalar( $raw['help'] ?? null ) ? mb_substr( trim( sanitize_textarea_field( (string) $raw['help'] ) ), 0, 500 ) : '';

		return array(
			'id'          => sanitize_key( is_scalar( $raw['id'] ?? null ) ? (string) $raw['id'] : '' ),
			'type'        => $type,
			'label'       => $label,
			'required'    => in_array( $type, self::INPUT_TYPES, true ) && ! empty( $raw['required'] ),
			'placeholder' => in_array( $type, array( 'text', 'textarea', 'email', 'tel', 'number', 'select' ), true ) ? $text( 'placeholder', 120 ) : '',
			'help'        => $help,
			'options'     => in_array( $type, self::CHOICE_TYPES, true ) ? array_slice( $options, 0, self::MAX_OPTIONS ) : array(),
			'prices'      => in_array( $type, self::CHOICE_TYPES, true ) ? array_intersect_key( $prices, array_flip( array_slice( $options, 0, self::MAX_OPTIONS ) ) ) : array(),
			'limits'      => in_array( $type, self::CHOICE_TYPES, true ) ? array_intersect_key( $limits, array_flip( array_slice( $options, 0, self::MAX_OPTIONS ) ) ) : array(),
			'images'      => in_array( $type, self::IMAGE_TYPES, true ) ? array_intersect_key( $images, array_flip( array_slice( $options, 0, self::MAX_OPTIONS ) ) ) : array(),
			'pictures'    => in_array( $type, self::IMAGE_TYPES, true ) && ! empty( $raw['pictures'] ),
			'picture_shape' => is_string( $raw['picture_shape'] ?? null ) && in_array( $raw['picture_shape'], self::PICTURE_SHAPES, true ) ? $raw['picture_shape'] : 'auto',
			'picture_layout' => is_string( $raw['picture_layout'] ?? null ) && in_array( $raw['picture_layout'], self::PICTURE_LAYOUTS, true ) ? $raw['picture_layout'] : 'grid',
			// 0 is automatic: as many as fit at the picked size.
			'picture_cols' => is_numeric( $raw['picture_cols'] ?? null ) ? max( 0, min( self::PICTURE_MAX_COLS, (int) $raw['picture_cols'] ) ) : 0,
			'picture_size' => is_string( $raw['picture_size'] ?? null ) && in_array( $raw['picture_size'], self::PICTURE_SIZES, true ) ? $raw['picture_size'] : 'medium',
			'icon'        => in_array( $type, self::INPUT_TYPES, true ) && 'checkbox' !== $type && is_string( $raw['icon'] ?? null ) && in_array( $raw['icon'], FieldIcons::names(), true ) ? $raw['icon'] : '',
			'width'       => in_array( $type, self::INPUT_TYPES, true ) && 'half' === ( $raw['width'] ?? '' ) ? 'half' : 'full',
			'when'        => self::sanitize_condition( $raw['when'] ?? null ),
			'rules'       => self::sanitize_rules( $raw['rules'] ?? null, $type ),
		);
	}

	/**
	 * No limits.
	 *
	 * @return FormRules
	 */
	public static function no_rules(): array {
		return array(
			'min'     => '',
			'max'     => '',
			'whole'   => false,
			'min_len' => 0,
			'max_len' => 0,
		);
	}

	/**
	 * A field's limits, kept only for the types they apply to: a smallest and largest number (and whole numbers only)
	 * for a Number, a shortest and longest text for the text fields. A smallest above the largest is swapped.
	 *
	 * @param mixed  $raw  The raw limits.
	 * @param string $type The field's type.
	 * @return FormRules
	 */
	private static function sanitize_rules( $raw, string $type ): array {
		$rules = self::no_rules();

		if ( ! is_array( $raw ) ) {
			return $rules;
		}

		if ( 'number' === $type ) {
			$number = static function ( $value ): string {
				$value = is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '';

				return is_numeric( $value ) ? (string) (float) max( -self::MAX_NUMBER, min( self::MAX_NUMBER, (float) $value ) ) : '';
			};

			$rules['min']   = $number( $raw['min'] ?? '' );
			$rules['max']   = $number( $raw['max'] ?? '' );
			$rules['whole'] = ! empty( $raw['whole'] );

			if ( '' !== $rules['min'] && '' !== $rules['max'] && (float) $rules['min'] > (float) $rules['max'] ) {
				list( $rules['min'], $rules['max'] ) = array( $rules['max'], $rules['min'] );
			}
		}

		if ( in_array( $type, array( 'text', 'textarea', 'tel' ), true ) ) {
			$length = static fn( $value ): int => is_numeric( $value ) ? max( 0, min( self::MAX_TEXT, (int) $value ) ) : 0;

			$rules['min_len'] = $length( $raw['min_len'] ?? 0 );
			$rules['max_len'] = $length( $raw['max_len'] ?? 0 );

			if ( $rules['max_len'] > 0 && $rules['min_len'] > $rules['max_len'] ) {
				list( $rules['min_len'], $rules['max_len'] ) = array( $rules['max_len'], $rules['min_len'] );
			}
		}

		return $rules;
	}

	/**
	 * The option labels, the price each adds to the payment, the spots each has and the picture each shows.
	 *
	 * @param array<int|string, mixed> $options The raw options.
	 * @param array<int|string, mixed> $prices  The raw prices, by label.
	 * @param array<int|string, mixed> $limits  The raw spots, by label.
	 * @param array<int|string, mixed> $images  The raw media library IDs, by label.
	 * @return array{labels: array<int, string>, prices: array<string, float>, limits: array<string, int>, images: array<string, int>}
	 */
	private static function split_options( array $options, array $prices, array $limits, array $images = array() ): array {
		$out   = array(
			'labels' => array(),
			'prices' => array(),
			'limits' => array(),
			'images' => array(),
		);
		$money = static fn( $value ): float => is_numeric( $value ) ? round( max( 0.0, min( Button::MAX_AMOUNT, (float) $value ) ), 2 ) : 0.0;
		$count = static fn( $value ): int => is_numeric( $value ) ? max( 0, min( Spots::MAX_SPOTS, (int) $value ) ) : 0;

		foreach ( $options as $option ) {
			$label = is_scalar( $option ) ? mb_substr( trim( sanitize_text_field( (string) $option ) ), 0, 100 ) : '';

			if ( '' === $label || in_array( $label, $out['labels'], true ) ) {
				continue;
			}

			$out['labels'][] = $label;

			if ( $money( $prices[ $label ] ?? 0 ) > 0 ) {
				$out['prices'][ $label ] = $money( $prices[ $label ] );
			}

			if ( $count( $limits[ $label ] ?? 0 ) > 0 ) {
				$out['limits'][ $label ] = $count( $limits[ $label ] );
			}

			$image = is_numeric( $images[ $label ] ?? null ) ? absint( $images[ $label ] ) : 0;

			// Only a real picture from the media library; a deleted or non-image ID just drops off.
			if ( $image > 0 && wp_attachment_is_image( $image ) ) {
				$out['images'][ $label ] = $image;
			}
		}

		return $out;
	}

	/**
	 * The options the payer picked on the visible choice fields, bar one field (the main Spots field).
	 *
	 * @param array<int, FormItem> $items A sanitized form.
	 * @param string               $skip  A field id to leave out, or ''.
	 * @param array<string, mixed> $input Raw values, keyed by input name.
	 * @return array<int, array{item: FormItem, label: string}>
	 */
	public static function picks( array $items, string $skip, array $input ): array {
		$shown = self::visibility( $items, self::answers( $items, $input ) );
		$picks = array();

		foreach ( $items as $item ) {
			if ( ! in_array( $item['type'], self::CHOICE_TYPES, true ) || $item['id'] === $skip || ! ( $shown[ $item['id'] ] ?? false ) ) {
				continue;
			}

			$picked = self::answers( array( $item ), $input )[ $item['id'] ] ?? array();

			foreach ( array_unique( $picked ) as $label ) {
				if ( in_array( $label, $item['options'], true ) ) {
					$picks[] = array(
						'item'  => $item,
						'label' => $label,
					);
				}
			}
		}

		return $picks;
	}

	/**
	 * What the payer's picks add to a fixed amount: the price of every picked option on the visible fields, bar the
	 * spots field, whose options carry their own prices.
	 *
	 * @param array<int, FormItem> $items A sanitized form.
	 * @param string               $skip  The spots field's id, or ''.
	 * @param array<string, mixed> $input Raw values, keyed by input name.
	 */
	public static function extras( array $items, string $skip, array $input ): float {
		$total = 0.0;

		foreach ( self::picks( $items, $skip, $input ) as $pick ) {
			$total += $pick['item']['prices'][ $pick['label'] ] ?? 0.0;
		}

		return round( $total, 2 );
	}

	/**
	 * What an option's spots are counted under, apart from the main Spots field's bare labels.
	 *
	 * @param string $field The field's id.
	 * @param string $label The option's label.
	 */
	public static function spot_key( string $field, string $label ): string {
		return '@' . $field . ':' . $label;
	}

	/**
	 * A condition, cleaned on its own; tidy_conditions() then checks it against the form.
	 *
	 * @param mixed $raw The raw condition.
	 * @return FormCondition
	 */
	private static function sanitize_condition( $raw ): array {
		if ( ! is_array( $raw ) || ! is_scalar( $raw['field'] ?? null ) ) {
			return self::no_condition();
		}

		$values = is_array( $raw['values'] ?? null ) ? $raw['values'] : array();

		return array(
			'field'  => sanitize_key( (string) $raw['field'] ),
			'op'     => in_array( $raw['op'] ?? '', self::CONDITION_OPS, true ) ? (string) $raw['op'] : 'is',
			'values' => array_values( array_unique( array_filter( array_map( static fn( $value ): string => is_scalar( $value ) ? mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, 100 ) : '', $values ) ) ) ),
		);
	}

	/**
	 * Keeps only conditions on an earlier field, with an op that field offers and values it still has.
	 * Renaming or removing the field, or its options, quietly drops what no longer fits. A page that holds the
	 * amount or the payer details can't be hidden, since they always have to be asked.
	 *
	 * @param array<int, FormItem> $items The items.
	 * @return array<int, FormItem>
	 */
	private static function tidy_conditions( array $items ): array {
		$earlier = array();

		foreach ( $items as $index => $item ) {
			$when       = $item['when'];
			$controller = '' !== $when['field'] ? ( $earlier[ $when['field'] ] ?? null ) : null;

			if ( null !== $controller ) {
				if ( ! in_array( $when['op'], self::ops_for( $controller['type'] ), true ) ) {
					$controller = null;
				} elseif ( in_array( $when['op'], self::VALUELESS_OPS, true ) ) {
					$when['values'] = array();
				} elseif ( 'gt' === $when['op'] || 'lt' === $when['op'] ) {
					$when['values'] = array_slice( array_values( array_filter( $when['values'], 'is_numeric' ) ), 0, 1 );
				} elseif ( 'number' === $controller['type'] ) {
					$when['values'] = array_slice( array_values( array_filter( $when['values'], 'is_numeric' ) ), 0, 1 );
				} elseif ( self::is_typed( $controller['type'] ) ) {
					$when['values'] = array_slice( $when['values'], 0, 1 );
				} else {
					$allowed        = 'checkbox' === $controller['type'] ? array( self::TICKED ) : $controller['options'];
					$when['values'] = array_values( array_intersect( $when['values'], $allowed ) );
				}
			}

			if ( null === $controller || ( array() === $when['values'] && ! in_array( $when['op'], self::VALUELESS_OPS, true ) ) ) {
				$when = self::no_condition();
			}

			$items[ $index ]['when'] = $when;

			if ( in_array( $item['type'], self::CONDITION_TYPES, true ) ) {
				$earlier[ $item['id'] ] = $item;
			}
		}

		return self::keep_anchors_shown( $items );
	}

	/**
	 * Takes the condition off a page break whose page holds the amount or the payer details.
	 *
	 * @param array<int, FormItem> $items The items.
	 * @return array<int, FormItem>
	 */
	private static function keep_anchors_shown( array $items ): array {
		$page = -1;

		foreach ( $items as $index => $item ) {
			if ( self::TYPE_PAGE === $item['type'] ) {
				$page = $index;
			} elseif ( $page >= 0 && in_array( $item['type'], self::ANCHORS, true ) ) {
				$items[ $page ]['when'] = self::no_condition();
			}
		}

		return $items;
	}

	/**
	 * An anchor item.
	 *
	 * @param string $type One of ANCHORS.
	 * @return FormItem
	 */
	private static function anchor( string $type ): array {
		return array(
			'id'          => $type,
			'type'        => $type,
			'label'       => '',
			'required'    => false,
			'placeholder' => '',
			'help'        => '',
			'options'     => array(),
			'prices'      => array(),
			'limits'      => array(),
			'images'      => array(),
			'pictures'    => false,
			'picture_shape' => 'auto',
			'picture_layout' => 'grid',
			'picture_cols' => 0,
			'picture_size' => 'medium',
			'icon'        => '',
			'width'       => 'full',
			'when'        => self::no_condition(),
			'rules'       => self::no_rules(),
		);
	}

	/**
	 * The pictures a choice field's options show, ready to print: none when its pictures are off. An ID whose file is gone is left out.
	 *
	 * @param array<string, mixed> $item A sanitized item.
	 * @phpstan-param FormItem $item
	 * @return array<string, array{url: string, large: string, alt: string, width: int, height: int}>
	 */
	public static function option_images( array $item ): array {
		$out = array();

		if ( ! $item['pictures'] || ! in_array( $item['type'], self::IMAGE_TYPES, true ) ) {
			return $out;
		}

		foreach ( $item['images'] as $label => $id ) {
			$src = wp_get_attachment_image_src( $id, 'medium' );

			if ( ! is_array( $src ) || '' === (string) $src[0] ) {
				continue;
			}

			$large = wp_get_attachment_image_src( $id, 'large' );

			// I keep a GIF's original file, the resized copies are still frames.
			if ( 'image/gif' === get_post_mime_type( $id ) ) {
				$src   = wp_get_attachment_image_src( $id, 'full' );
				$large = $src;

				if ( ! is_array( $src ) || '' === (string) $src[0] ) {
					continue;
				}
			}

			$out[ (string) $label ] = array(
				'url'    => (string) $src[0],
				'large'  => is_array( $large ) && '' !== (string) $large[0] ? (string) $large[0] : (string) $src[0],
				'alt'    => trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}

		return $out;
	}

	/**
	 * The shape of a field's picture cards: the width over the height, and whether the cards show the pictures whole.
	 * Auto takes the pictures' own shape when they all agree (within a few percent), and the usual 4:3 when they don't.
	 *
	 * @param array<string, mixed>                                                                    $item   A sanitized item.
	 * @param array<string, array{url: string, large: string, alt: string, width: int, height: int}> $images option_images() of it.
	 * @phpstan-param FormItem $item
	 * @return array{ratio: float, whole: bool}
	 */
	public static function picture_shape( array $item, array $images ): array {
		$fixed = array(
			'landscape' => 4 / 3,
			'square'    => 1.0,
			'portrait'  => 2 / 3,
		);

		if ( isset( $fixed[ $item['picture_shape'] ] ) ) {
			return array(
				'ratio' => round( $fixed[ $item['picture_shape'] ], 4 ),
				'whole' => 'landscape' !== $item['picture_shape'],
			);
		}

		$ratios = array();

		foreach ( $images as $image ) {
			if ( $image['width'] > 0 && $image['height'] > 0 ) {
				$ratios[] = $image['width'] / $image['height'];
			}
		}

		if ( array() !== $ratios && max( $ratios ) / min( $ratios ) <= 1.08 ) {
			// Kept to a sane range, so one odd panorama can't make a strip of cards.
			return array(
				'ratio' => round( max( 0.5, min( 2.0, array_sum( $ratios ) / count( $ratios ) ) ), 4 ),
				'whole' => true,
			);
		}

		return array(
			'ratio' => round( 4 / 3, 4 ),
			'whole' => false,
		);
	}

	/**
	 * An id no other item has, so each field gets its own name and merge tag.
	 *
	 * @param string              $id       The wanted id.
	 * @param array<string, bool> $seen   Ids taken so far.
	 * @param int                 $position The item's position, for a fallback id.
	 */
	private static function unique_id( string $id, array $seen, int $position ): string {
		$id   = substr( '' !== $id ? $id : 'field_' . $position, 0, 40 );
		$base = $id;
		$n    = 2;

		while ( isset( $seen[ $id ] ) || in_array( $id, self::ANCHORS, true ) ) {
			$id = substr( $base, 0, 36 ) . '_' . $n;
			++$n;
		}

		return $id;
	}

	/**
	 * Drops page breaks that split nothing: at the very start, at the end, or right after another.
	 *
	 * @param array<int, FormItem> $items The items.
	 * @return array<int, FormItem>
	 */
	private static function tidy_pages( array $items ): array {
		$tidy = array();

		foreach ( $items as $item ) {
			$last = end( $tidy );

			if ( self::TYPE_PAGE === $item['type'] && ( false === $last || self::TYPE_PAGE === $last['type'] ) ) {
				continue;
			}

			$tidy[] = $item;
		}

		while ( array() !== $tidy && self::TYPE_PAGE === end( $tidy )['type'] ) {
			array_pop( $tidy );
		}

		return $tidy;
	}
}
