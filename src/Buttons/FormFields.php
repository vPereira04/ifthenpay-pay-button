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
 * Any field or text block can show only when an earlier choice is (or isn't) answered a certain way.
 *
 * @phpstan-type FormCondition array{field: string, op: string, values: array<int, string>}
 * @phpstan-type FormItem array{id: string, type: string, label: string, required: bool, placeholder: string, help: string, options: array<int, string>, width: string, when: FormCondition}
 * @phpstan-type FormStep array{title: string, items: array<int, FormItem>}
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

	// Types another item can show or hide by. An agreement's only answer is "ticked".
	public const CONDITION_TYPES = array( 'select', 'radio', 'checkboxes', 'checkbox' );
	public const CONDITION_OPS   = array( 'is', 'not' );
	public const TICKED          = '1';

	public const MAX_ITEMS   = 40;
	public const MAX_OPTIONS = 30;

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
	 * Which items show for these answers, by id. An item whose field is hidden is hidden too, so a chain
	 * of conditions folds up from the top.
	 *
	 * @param array<int, FormItem>              $items   A sanitized form.
	 * @param array<string, array<int, string>> $answers What each field holds, by id: its picked options, or TICKED.
	 * @return array<string, bool>
	 */
	public static function visibility( array $items, array $answers ): array {
		$shown = array();

		foreach ( $items as $item ) {
			$when = $item['when'];

			if ( '' === $when['field'] ) {
				$shown[ $item['id'] ] = true;

				continue;
			}

			$picked = ( $shown[ $when['field'] ] ?? false ) ? ( $answers[ $when['field'] ] ?? array() ) : array();
			$match  = array() !== array_intersect( $picked, $when['values'] );

			$shown[ $item['id'] ] = ( $shown[ $when['field'] ] ?? false ) && ( 'not' === $when['op'] ? ! $match : $match );
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
	 * The form split at its page breaks. A form without breaks is one step.
	 *
	 * @param array<int, FormItem> $items      A sanitized form.
	 * @param string               $first_step The first step's title.
	 * @return array<int, FormStep>
	 */
	public static function steps( array $items, string $first_step ): array {
		$steps = array(
			array(
				'title' => $first_step,
				'items' => array(),
			),
		);

		foreach ( $items as $item ) {
			if ( self::TYPE_PAGE === $item['type'] ) {
				$steps[] = array(
					'title' => $item['label'],
					'items' => array(),
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
		$options = array_values( array_unique( array_filter( array_map( static fn( $option ): string => is_scalar( $option ) ? mb_substr( trim( sanitize_text_field( (string) $option ) ), 0, 100 ) : '', $options ) ) ) );
		$label   = $text( 'label', self::TYPE_CONTENT === $type ? 160 : 120 );

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
			'width'       => in_array( $type, self::INPUT_TYPES, true ) && 'half' === ( $raw['width'] ?? '' ) ? 'half' : 'full',
			'when'        => self::TYPE_PAGE === $type ? self::no_condition() : self::sanitize_condition( $raw['when'] ?? null ),
		);
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
	 * Keeps only conditions on an earlier choice or agreement, with values that field still has.
	 * Renaming or removing the field, or its options, quietly drops what no longer fits.
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
				$allowed        = 'checkbox' === $controller['type'] ? array( self::TICKED ) : $controller['options'];
				$when['values'] = array_values( array_intersect( $when['values'], $allowed ) );
			}

			if ( null === $controller || array() === $when['values'] ) {
				$when = self::no_condition();
			}

			$items[ $index ]['when'] = $when;

			if ( in_array( $item['type'], self::CONDITION_TYPES, true ) ) {
				$earlier[ $item['id'] ] = $item;
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
			'width'       => 'full',
			'when'        => self::no_condition(),
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
