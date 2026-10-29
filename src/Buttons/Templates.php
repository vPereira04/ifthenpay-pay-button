<?php
/**
 * Starter buttons for a first-time merchant.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three starters the welcome screen offers. Each is only a head start: a title and a few config keys that
 * go through the same sanitizing as the editor's form, and the merchant lands in the editor to change anything.
 */
class Templates {

	public const DONATION = 'donation';
	public const EVENT    = 'event';
	public const FIXED    = 'fixed';

	/**
	 * Whether a key names a starter.
	 *
	 * @param string $key The template key.
	 */
	public static function exists( string $key ): bool {
		return in_array( $key, array( self::DONATION, self::EVENT, self::FIXED ), true );
	}

	/**
	 * A starter's title and config, or null when the key is unknown.
	 *
	 * @param string $key The template key.
	 * @return array{title: string, config: array<string, mixed>}|null
	 */
	public static function get( string $key ): ?array {
		switch ( $key ) {
			case self::DONATION:
				return array(
					'title'  => __( 'Donation', 'ifthenpay-pay-button' ),
					'config' => array(
						'amount_mode' => Button::MODE_PAYER,
						'amount'      => 10.0,
						'min'         => 1.0,
						'presets'     => array( 5, 10, 25, 50 ),
						'label'       => __( 'Donate', 'ifthenpay-pay-button' ),
						'summary'     => __( 'Thank you for your support.', 'ifthenpay-pay-button' ),
						'fields'      => array(
							'name'  => Button::FIELD_OPTIONAL,
							'email' => Button::FIELD_REQUIRED,
						),
					),
				);

			case self::EVENT:
				return array(
					'title'  => __( 'Event ticket', 'ifthenpay-pay-button' ),
					'config' => array(
						'amount_mode' => Button::MODE_FIXED,
						'amount'      => 25.0,
						'label'       => __( 'Buy ticket', 'ifthenpay-pay-button' ),
						'summary'     => __( 'One ticket for the event.', 'ifthenpay-pay-button' ),
						'fields'      => array(
							'name'  => Button::FIELD_REQUIRED,
							'email' => Button::FIELD_REQUIRED,
							'phone' => Button::FIELD_OPTIONAL,
						),
						'spots'       => array(
							'mode'      => Spots::MODE_TOTAL,
							'total'     => 100,
							'quantity'  => true,
							'max_each'  => 5,
							'show_left' => true,
						),
					),
				);

			case self::FIXED:
				return array(
					'title'  => __( 'Fixed price', 'ifthenpay-pay-button' ),
					'config' => array(
						'amount_mode' => Button::MODE_FIXED,
						'amount'      => 20.0,
						'label'       => __( 'Pay now', 'ifthenpay-pay-button' ),
						'fields'      => array(
							'name'  => Button::FIELD_REQUIRED,
							'email' => Button::FIELD_REQUIRED,
						),
					),
				);
		}

		return null;
	}
}
