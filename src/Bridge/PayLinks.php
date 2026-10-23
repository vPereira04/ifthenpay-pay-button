<?php
/**
 * Pay link URLs, the parameters they carry, and signed links.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Bridge;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The bridge to everything ifthenpay has no integration with. A form plugin redirects to a pay link after submit,
 * filling these parameters with its own merge tags; a developer signs a link so its amount can't be edited.
 */
class PayLinks {

	// Query var the rewrite rule fills; also the plain-permalink form of a pay link.
	public const QUERY_VAR = 'iftp_btn_pay_button';

	// Link parameter => checkout field. None of them is a WP query var (`name` is), or an affiliate one (`ref` is).
	public const FIELD_PARAMS = array(
		'payer_name' => 'name',
		'email'      => 'email',
		'phone'      => 'phone',
		'nif'        => 'nif',
		'note'       => 'note',
	);

	public const PARAM_AMOUNT    = 'amount';
	public const PARAM_REFERENCE = 'reference';
	public const PARAM_EXPIRES   = 'expires';
	public const PARAM_SIGNATURE = 'sig';

	private const REFERENCE_MAX_LENGTH = 191;

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $settings;

	/**
	 * Sets up the service.
	 *
	 * @param CheckoutSettings|null $settings Checkout settings.
	 */
	public function __construct( ?CheckoutSettings $settings = null ) {
		$this->settings = $settings ?? new CheckoutSettings();
	}

	/**
	 * A button's pay link, with no parameters.
	 *
	 * @param Button $button The button.
	 */
	public function url( Button $button ): string {
		// Plain permalinks never match the rewrite rule, but the query var is public, so this form works there.
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( self::QUERY_VAR, rawurlencode( $button->slug ), home_url( '/' ) );
		}

		return home_url( user_trailingslashit( $this->settings->get_pay_base() . '/' . $button->slug ) );
	}

	/**
	 * A pay link carrying the given values. With an amount or reference it's signed, so neither can be edited.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $args   Any of: amount, reference, expires (Unix time), and the FIELD_PARAMS keys.
	 */
	public function signed_url( Button $button, array $args ): string {
		$query = array();

		foreach ( array_keys( self::FIELD_PARAMS ) as $param ) {
			if ( is_scalar( $args[ $param ] ?? null ) && '' !== (string) $args[ $param ] ) {
				$query[ $param ] = (string) $args[ $param ];
			}
		}

		$amount    = is_numeric( $args[ self::PARAM_AMOUNT ] ?? null ) ? round( (float) $args[ self::PARAM_AMOUNT ], 2 ) : 0.0;
		$reference = is_scalar( $args[ self::PARAM_REFERENCE ] ?? null ) ? self::clean_reference( (string) $args[ self::PARAM_REFERENCE ] ) : '';
		$expires   = is_numeric( $args[ self::PARAM_EXPIRES ] ?? null ) ? (int) $args[ self::PARAM_EXPIRES ] : 0;

		if ( $amount > 0 || '' !== $reference ) {
			if ( $amount > 0 ) {
				$query[ self::PARAM_AMOUNT ] = number_format( $amount, 2, '.', '' );
			}

			if ( '' !== $reference ) {
				$query[ self::PARAM_REFERENCE ] = $reference;
			}

			if ( $expires > 0 ) {
				$query[ self::PARAM_EXPIRES ] = (string) $expires;
			}

			$query[ self::PARAM_SIGNATURE ] = $this->signature( $button->id, $amount, $reference, $expires );
		}

		return add_query_arg( array_map( 'rawurlencode', $query ), $this->url( $button ) );
	}

	/**
	 * The HMAC over what a signed link locks: button, amount, reference and expiry.
	 *
	 * @param int    $button_id The button's post ID.
	 * @param float  $amount    The amount, or 0 when the link doesn't set one.
	 * @param string $reference The reference, or ''.
	 * @param int    $expires   Unix time the link stops working, or 0 for never.
	 */
	public function signature( int $button_id, float $amount, string $reference, int $expires ): string {
		$amount_text = $amount > 0 ? number_format( $amount, 2, '.', '' ) : '';

		return hash_hmac( 'sha256', $button_id . '|' . $amount_text . '|' . $reference . '|' . $expires, $this->settings->get_signing_secret() );
	}

	/**
	 * Whether a signature matches and hasn't expired.
	 *
	 * @param int    $button_id The button's post ID.
	 * @param float  $amount    The amount the link carried, or 0.
	 * @param string $reference The reference the link carried, or ''.
	 * @param int    $expires   The expiry the link carried, or 0.
	 * @param string $signature The signature the link carried.
	 */
	public function verify( int $button_id, float $amount, string $reference, int $expires, string $signature ): bool {
		if ( '' === $signature || ( $expires > 0 && $expires < time() ) ) {
			return false;
		}

		return hash_equals( $this->signature( $button_id, $amount, $reference, $expires ), $signature );
	}

	/**
	 * What the current request's query carries, cleaned: the fields to prefill, plus amount, reference and signature.
	 *
	 * @return array{fields: array<string, string>, amount: string, reference: string, expires: int, sig: string}
	 */
	public static function read_query(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a pay link is a plain GET from anywhere; it only prefills, and the checkout re-validates every value.
		$fields = array();

		foreach ( self::FIELD_PARAMS as $param => $field ) {
			if ( isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ) {
				$value = 'note' === $field ? sanitize_textarea_field( wp_unslash( $_GET[ $param ] ) ) : sanitize_text_field( wp_unslash( $_GET[ $param ] ) );

				if ( '' !== $value ) {
					$fields[ $field ] = $value;
				}
			}
		}

		// The button's own fields ride along as f_{id}.
		foreach ( array_keys( $_GET ) as $param ) {
			if ( is_string( $param ) && 1 === preg_match( '/^f_([a-z0-9_]{1,40})$/', $param, $match ) && is_string( $_GET[ $param ] ) ) {
				$value = sanitize_textarea_field( wp_unslash( $_GET[ $param ] ) );

				if ( '' !== $value ) {
					$fields[ 'cf_' . $match[1] ] = $value;
				}
			}
		}

		$read = static fn( string $param ): string => isset( $_GET[ $param ] ) && is_string( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'fields'    => $fields,
			'amount'    => self::clean_amount( $read( self::PARAM_AMOUNT ) ),
			'reference' => self::clean_reference( $read( self::PARAM_REFERENCE ) ),
			'expires'   => absint( $read( self::PARAM_EXPIRES ) ),
			'sig'       => (string) preg_replace( '/[^a-f0-9]/', '', strtolower( $read( self::PARAM_SIGNATURE ) ) ),
		);
	}

	/**
	 * An amount as a form plugin might write it ("12,50", "€ 12.50", "1.234,56"), as "12.50", or '' when it isn't one.
	 *
	 * @param string $raw The raw amount.
	 */
	public static function clean_amount( string $raw ): string {
		$raw = (string) preg_replace( '/[^0-9.,]/', '', $raw );

		if ( '' === $raw ) {
			return '';
		}

		// The last separator is the decimal one when two digits or fewer follow it; any other is a thousands separator.
		$last = max( (int) strrpos( $raw, ',' ), (int) strrpos( $raw, '.' ) );
		$has  = false !== strpos( $raw, ',' ) || false !== strpos( $raw, '.' );

		if ( $has && strlen( $raw ) - $last - 1 <= 2 ) {
			$raw = str_replace( array( ',', '.' ), '', substr( $raw, 0, $last ) ) . '.' . substr( $raw, $last + 1 );
		} else {
			$raw = str_replace( array( ',', '.' ), '', $raw );
		}

		return is_numeric( $raw ) && (float) $raw > 0 ? number_format( (float) $raw, 2, '.', '' ) : '';
	}

	/**
	 * A reference cut to what the table holds, with no markup.
	 *
	 * @param string $raw The raw reference.
	 */
	public static function clean_reference( string $raw ): string {
		return mb_substr( trim( sanitize_text_field( $raw ) ), 0, self::REFERENCE_MAX_LENGTH );
	}
}
