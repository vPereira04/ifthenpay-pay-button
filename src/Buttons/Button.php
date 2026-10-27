<?php
/**
 * One pay button.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

use Ifthenpay\PayButton\Bridge\InvoiceHook;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A button as the rest of the plugin reads it: the post's title, slug and status plus its config meta, every key filled in.
 *
 * @phpstan-import-type ButtonDesignConfig from ButtonDesign
 * @phpstan-import-type InvoiceHookConfig from InvoiceHook
 * @phpstan-import-type FormItem from FormFields
 * @phpstan-import-type FormStep from FormFields
 * @phpstan-import-type Look from CheckoutLook
 * @phpstan-import-type SpotsConfig from Spots
 * @phpstan-type ButtonConfig array{amount_mode: string, amount: float, min: float, max: float, presets: array<int, float>, label: string, show_amount: bool, summary: string, fields: array<string, string>, note_label: string, methods: array<int, string>, paid_redirect: string, paid_page: int, paid_url: string, skip_form: bool, spots: SpotsConfig, webhook_url: string, webhook_secret: string, invoice_hook: InvoiceHookConfig, design: ButtonDesignConfig, form: array<int, FormItem>, first_step: string, pay_description: string, look_mode: string, look: Look}
 */
class Button {

	public const MODE_FIXED = 'fixed';
	public const MODE_PAYER = 'payer';
	public const MODE_LINK  = 'link';
	public const MODES      = array( self::MODE_FIXED, self::MODE_PAYER, self::MODE_LINK );

	public const FIELD_OFF      = 'off';
	public const FIELD_OPTIONAL = 'optional';
	public const FIELD_REQUIRED = 'required';
	public const FIELD_STATES   = array( self::FIELD_OFF, self::FIELD_OPTIONAL, self::FIELD_REQUIRED );

	// Every field the checkout can ask for, in the order it asks.
	public const FIELDS = array( 'name', 'email', 'phone', 'nif', 'note' );

	// DECIMAL(10,2) holds up to this; ifthenpay won't take less than a cent.
	public const MIN_AMOUNT = 0.01;
	public const MAX_AMOUNT = 99999999.99;

	public const MAX_PRESETS = 6;

	// Whose checkout look it uses: the site's, or its own.
	public const LOOK_SITE = 'site';
	public const LOOK_OWN  = 'own';

	/**
	 * The post ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * The name the admin and the payer see.
	 *
	 * @var string
	 */
	public string $title;

	/**
	 * The slug its pay link uses.
	 *
	 * @var string
	 */
	public string $slug;

	/**
	 * Whether it takes payments.
	 *
	 * @var bool
	 */
	public bool $active;

	/**
	 * The config meta.
	 *
	 * @var array<string, mixed>
	 * @phpstan-var ButtonConfig
	 */
	public array $config;

	/**
	 * Builds a button from its parts.
	 *
	 * @param int                  $id     The post ID.
	 * @param string               $title  The title.
	 * @param string               $slug   The slug.
	 * @param bool                 $active Whether it takes payments.
	 * @param array<string, mixed> $config The stored config; missing keys get their defaults.
	 */
	public function __construct( int $id, string $title, string $slug, bool $active, array $config ) {
		$this->id     = $id;
		$this->title  = $title;
		$this->slug   = $slug;
		$this->active = $active;
		$this->config = self::sanitize_config( $config );
	}

	/**
	 * A new button's config.
	 *
	 * @return ButtonConfig
	 */
	public static function defaults(): array {
		return array(
			'amount_mode'     => self::MODE_FIXED,
			'amount'          => 10.0,
			'min'             => 1.0,
			'max'             => 1000.0,
			'presets'         => array(),
			'label'           => '',
			'show_amount'     => true,
			'summary'         => '',
			'fields'          => array(
				'name'  => self::FIELD_REQUIRED,
				'email' => self::FIELD_REQUIRED,
				'phone' => self::FIELD_OFF,
				'nif'   => self::FIELD_OFF,
				'note'  => self::FIELD_OFF,
			),
			'note_label'      => '',
			'methods'         => array(),
			'paid_redirect'   => '',
			'paid_page'       => 0,
			'paid_url'        => '',
			'skip_form'       => true,
			'spots'           => Spots::defaults(),
			'webhook_url'     => '',
			'webhook_secret'  => '',
			'invoice_hook'    => InvoiceHook::defaults(),
			// New buttons wear the site's default design until they're given their own.
			'design'          => array( 'source' => ButtonDesign::SOURCE_SITE ) + ButtonDesign::defaults(),
			'form'            => FormFields::defaults(),
			'first_step'      => '',
			'pay_description' => '',
			'look_mode'       => self::LOOK_SITE,
			'look'            => CheckoutLook::defaults(),
		);
	}

	/**
	 * Cleans a config from anywhere (the editor, the database), so callers can trust every key and type.
	 *
	 * @param array<string, mixed> $input The raw config.
	 * @return ButtonConfig
	 */
	public static function sanitize_config( array $input ): array {
		$config = self::defaults();
		$text   = static fn( string $key, int $max ): string => is_scalar( $input[ $key ] ?? null ) ? mb_substr( sanitize_text_field( (string) $input[ $key ] ), 0, $max ) : '';
		$money  = static fn( $value ): float => is_numeric( $value ) ? round( max( 0.0, min( self::MAX_AMOUNT, (float) $value ) ), 2 ) : 0.0;

		if ( is_string( $input['amount_mode'] ?? null ) && in_array( $input['amount_mode'], self::MODES, true ) ) {
			$config['amount_mode'] = $input['amount_mode'];
		}

		foreach ( array( 'amount', 'min', 'max' ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$config[ $key ] = $money( $input[ $key ] );
			}
		}

		$config['min'] = max( self::MIN_AMOUNT, $config['min'] );
		$config['max'] = max( $config['min'], $config['max'] > 0 ? $config['max'] : self::MAX_AMOUNT );

		$presets = $input['presets'] ?? array();
		$presets = is_string( $presets ) ? explode( ',', $presets ) : ( is_array( $presets ) ? $presets : array() );
		$presets = array_values( array_unique( array_filter( array_map( $money, array_map( static fn( $value ) => is_scalar( $value ) ? str_replace( ',', '.', trim( (string) $value ) ) : '', $presets ) ) ) ) );
		sort( $presets );

		$config['presets']     = array_slice( $presets, 0, self::MAX_PRESETS );
		$config['label']       = $text( 'label', 60 );
		$config['summary']     = $text( 'summary', 200 );
		$config['note_label']  = $text( 'note_label', 60 );
		$config['show_amount'] = ! empty( $input['show_amount'] ) || ! array_key_exists( 'show_amount', $input );
		$config['skip_form']   = ! empty( $input['skip_form'] ) || ! array_key_exists( 'skip_form', $input );

		$fields = is_array( $input['fields'] ?? null ) ? $input['fields'] : array();

		foreach ( self::FIELDS as $field ) {
			if ( is_string( $fields[ $field ] ?? null ) && in_array( $fields[ $field ], self::FIELD_STATES, true ) ) {
				$config['fields'][ $field ] = $fields[ $field ];
			}
		}

		$methods           = is_array( $input['methods'] ?? null ) ? $input['methods'] : array();
		$config['methods'] = array_values( array_unique( array_filter( array_map( static fn( $entity ): string => is_scalar( $entity ) ? strtoupper( sanitize_key( (string) $entity ) ) : '', $methods ) ) ) );

		if ( is_string( $input['paid_redirect'] ?? null ) && in_array( $input['paid_redirect'], array( '', 'popup', 'page', 'url' ), true ) ) {
			$config['paid_redirect'] = $input['paid_redirect'];
		}

		$config['paid_page'] = is_numeric( $input['paid_page'] ?? null ) ? max( 0, (int) $input['paid_page'] ) : 0;
		$config['paid_url']  = is_string( $input['paid_url'] ?? null ) ? esc_url_raw( trim( $input['paid_url'] ), array( 'http', 'https' ) ) : '';

		$config['webhook_url']    = is_string( $input['webhook_url'] ?? null ) ? esc_url_raw( trim( $input['webhook_url'] ), array( 'https', 'http' ) ) : '';
		$config['webhook_secret'] = is_string( $input['webhook_secret'] ?? null ) ? preg_replace( '/[^A-Za-z0-9]/', '', $input['webhook_secret'] ) ?? '' : '';
		$config['invoice_hook']   = InvoiceHook::sanitize( $input['invoice_hook'] ?? null );
		$config['design']         = ButtonDesign::sanitize( $input['design'] ?? array() );
		$config['form']           = FormFields::sanitize( $input['form'] ?? array() );
		// A button saved before spots existed had a plain "Stop after" cap; it becomes Limited spots.
		$config['spots']           = Spots::sanitize( $input['spots'] ?? null, $config['form'], is_numeric( $input['max_payments'] ?? null ) ? (int) $input['max_payments'] : 0 );
		$config['form']            = Spots::require_field( $config['form'], $config['spots'] );
		$config['first_step']      = $text( 'first_step', 60 );
		$config['pay_description'] = $text( 'pay_description', 200 );
		$config['look_mode']       = self::LOOK_OWN === ( $input['look_mode'] ?? '' ) ? self::LOOK_OWN : self::LOOK_SITE;
		// A button's own look has no CSS of its own; the site's Custom CSS still applies to it.
		$config['look'] = array( 'css' => '' ) + CheckoutLook::sanitize( $input['look'] ?? array() );

		return $config;
	}

	/**
	 * The amount mode: fixed, payer or link.
	 */
	public function mode(): string {
		return $this->config['amount_mode'];
	}

	/**
	 * Whether a field shows, and whether it must be filled.
	 *
	 * @param string $field One of FIELDS.
	 */
	public function field_state( string $field ): string {
		return $this->config['fields'][ $field ] ?? self::FIELD_OFF;
	}

	/**
	 * The fields the checkout asks for, keyed by field => required.
	 *
	 * @return array<string, bool>
	 */
	public function visible_fields(): array {
		$visible = array();

		foreach ( self::FIELDS as $field ) {
			$state = $this->field_state( $field );

			if ( self::FIELD_OFF !== $state ) {
				$visible[ $field ] = self::FIELD_REQUIRED === $state;
			}
		}

		return $visible;
	}

	/**
	 * The button text, or the default one.
	 */
	public function label(): string {
		return '' !== $this->config['label'] ? $this->config['label'] : CheckoutTexts::get( 'pay_now' );
	}

	/**
	 * The text on the button itself: the label, or an override, plus the amount when the button shows it.
	 *
	 * @param string $override A label from the block or shortcode, '' for the button's own.
	 */
	public function display_label( string $override = '' ): string {
		$label = '' !== trim( $override ) ? trim( $override ) : $this->label();

		if ( self::MODE_FIXED === $this->mode() && $this->config['show_amount'] ) {
			list( $low, $high ) = Spots::price_range( $this );
			$price              = $low < $high ? CheckoutTexts::get( 'from', array( 'price' => CheckoutService::format_money( $low ) ) ) : CheckoutService::format_money( $low );
			/* translators: 1: the button text, 2: the amount */
			$label = sprintf( __( '%1$s · %2$s', 'ifthenpay-pay-button' ), $label, $price );
		}

		return $label;
	}

	/**
	 * The checkout's steps; one when there are no page breaks.
	 *
	 * @return array<int, FormStep>
	 */
	public function steps(): array {
		return FormFields::steps( $this->config['form'], '' !== $this->config['first_step'] ? $this->config['first_step'] : CheckoutTexts::get( 'first_step' ) );
	}

	/**
	 * The label for the free-text field.
	 */
	public function note_label(): string {
		return '' !== $this->config['note_label'] ? $this->config['note_label'] : CheckoutTexts::get( 'note' );
	}

	/**
	 * Where a paid payment sends the payer: '' to follow the site setting, or this button's own page/URL/popup.
	 */
	public function paid_redirect_url(): string {
		if ( 'page' === $this->config['paid_redirect'] && $this->config['paid_page'] > 0 ) {
			$url = get_permalink( $this->config['paid_page'] );

			return is_string( $url ) ? $url : '';
		}

		return 'url' === $this->config['paid_redirect'] ? $this->config['paid_url'] : '';
	}

	/**
	 * Whether the button has its own Paid outcome instead of the site's.
	 */
	public function overrides_paid(): bool {
		return '' !== $this->config['paid_redirect'];
	}
}
