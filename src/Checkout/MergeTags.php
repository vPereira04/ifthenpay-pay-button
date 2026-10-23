<?php
/**
 * Merge tags: {name}, {amount}, {field:company} and friends.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fills a payment's details into text the merchant wrote: the hosted payment page description and the outcome popups.
 * A tag that has no value becomes empty; one I don't know stays as typed, so a typo is easy to spot.
 */
class MergeTags {

	private const PATTERN = '/\{([a-z_]+)(?::([a-z0-9_]+))?\}/';

	/**
	 * The tags the editor offers, with what each one holds.
	 *
	 * @param bool $after_payment Whether the payment has happened, so the method is known.
	 * @return array<string, string>
	 */
	public static function available( bool $after_payment ): array {
		$tags = array(
			'{name}'       => __( 'Payer name', 'ifthenpay-pay-button' ),
			'{email}'      => __( 'Payer email', 'ifthenpay-pay-button' ),
			'{phone}'      => __( 'Phone', 'ifthenpay-pay-button' ),
			'{nif}'        => __( 'Tax number', 'ifthenpay-pay-button' ),
			'{note}'       => __( 'Note', 'ifthenpay-pay-button' ),
			'{amount}'     => __( 'Amount', 'ifthenpay-pay-button' ),
			'{quantity}'   => __( 'Quantity', 'ifthenpay-pay-button' ),
			'{payment_id}' => __( 'Payment ID', 'ifthenpay-pay-button' ),
			'{order}'      => __( 'Reference from the link', 'ifthenpay-pay-button' ),
			'{button}'     => __( 'Button title', 'ifthenpay-pay-button' ),
			'{site}'       => __( 'Site name', 'ifthenpay-pay-button' ),
			'{date}'       => __( 'Date', 'ifthenpay-pay-button' ),
		);

		if ( $after_payment ) {
			$tags['{method}'] = __( 'Payment method', 'ifthenpay-pay-button' );
		}

		$tags['{field:id}'] = __( 'One of your own fields', 'ifthenpay-pay-button' );

		return $tags;
	}

	/**
	 * Every tag's value for one payment.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @param Button|null          $button Its button, if it has one.
	 * @return array<string, string> Keyed by tag name without braces; own fields as `field:id`.
	 */
	public static function values( array $record, ?Button $button = null ): array {
		$snapshot = TransactionStore::decode_snapshot( is_string( $record['field_snapshot'] ?? null ) ? $record['field_snapshot'] : '' );
		$value    = static fn( string $key ): string => is_array( $snapshot[ $key ] ?? null ) && is_scalar( $snapshot[ $key ]['value'] ?? null ) ? (string) $snapshot[ $key ]['value'] : '';
		$created  = is_numeric( $record['created_at'] ?? null ) && (int) $record['created_at'] > 0 ? (int) $record['created_at'] : time();

		$values = array(
			'name'       => is_string( $record['customer_name'] ?? null ) && '' !== $record['customer_name'] ? $record['customer_name'] : $value( 'customer_name' ),
			'email'      => is_string( $record['customer_email'] ?? null ) && '' !== $record['customer_email'] ? $record['customer_email'] : $value( 'customer_email' ),
			'phone'      => $value( 'phone' ),
			'nif'        => $value( 'nif' ),
			'note'       => $value( 'note' ),
			'amount'     => CheckoutService::format_money( is_numeric( $record['amount'] ?? null ) ? (float) $record['amount'] : 0.0 ),
			'quantity'   => '' !== $value( 'quantity' ) ? $value( 'quantity' ) : '1',
			'payment_id' => is_string( $record['ref'] ?? null ) ? $record['ref'] : '',
			'order'      => is_string( $record['external_ref'] ?? null ) ? $record['external_ref'] : '',
			'method'     => is_string( $record['pay_method'] ?? null ) ? $record['pay_method'] : '',
			'button'     => null !== $button ? $button->title : $value( 'button' ),
			'site'       => (string) get_bloginfo( 'name' ),
			'date'       => Dates::local( (string) get_option( 'date_format' ), $created ),
		);

		foreach ( $snapshot as $key => $pair ) {
			if ( is_string( $key ) && 0 === strpos( $key, FormFields::input_name( '' ) ) ) {
				$values[ 'field:' . substr( $key, strlen( FormFields::input_name( '' ) ) ) ] = $value( $key );
			}
		}

		return $values;
	}

	/**
	 * Swaps the tags in a text for their values.
	 *
	 * @param string                $text   The text, plain or admin HTML.
	 * @param array<string, string> $values From values().
	 * @param bool                  $html   Whether the text is HTML, so the values get escaped.
	 */
	public static function replace( string $text, array $values, bool $html ): string {
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}

		return (string) preg_replace_callback(
			self::PATTERN,
			static function ( array $found ) use ( $values, $html ): string {
				$key = isset( $found[2] ) && '' !== $found[2] ? $found[1] . ':' . $found[2] : $found[1];

				if ( ! array_key_exists( $key, $values ) ) {
					// An own field that wasn't answered is just empty; anything else unknown stays visible.
					return 'field' === $found[1] ? '' : $found[0];
				}

				return $html ? esc_html( $values[ $key ] ) : $values[ $key ];
			},
			$text
		);
	}
}
