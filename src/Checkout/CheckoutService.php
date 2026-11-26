<?php
/**
 * Starts a payment from a button.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Repository\CouponStore;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks what the payer sent against the button, writes the pending payment and asks ifthenpay for its page.
 *
 * @phpstan-import-type FormItem from FormFields
 */
class CheckoutService {

	// A field nobody sees; anything in it is a bot.
	public const HONEYPOT = 'website';

	// Every start costs an ifthenpay API call, so a loop mustn't be able to run them up.
	private const PER_IP_PER_MINUTE     = 10;
	private const PER_SITE_PER_MINUTE   = 60;
	private const CODE_TRIES_PER_MINUTE = 15;

	private const NOTE_MAX_LENGTH  = 500;
	private const FIELD_MAX_LENGTH = 191;

	/**
	 * Gateway settings.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Pay by Link creation.
	 *
	 * @var PaymentLinks
	 */
	private PaymentLinks $links;

	/**
	 * Pay links and their signatures.
	 *
	 * @var PayLinks
	 */
	private PayLinks $pay_links;

	/**
	 * Spot counting.
	 *
	 * @var SpotCounter
	 */
	private SpotCounter $spots;

	/**
	 * Discount codes.
	 *
	 * @var Discounts
	 */
	private Discounts $discounts;

	/**
	 * Sets up the service.
	 *
	 * @param SettingsRepository|null $settings     Gateway settings.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 * @param PaymentLinks|null       $links        Pay by Link creation.
	 * @param PayLinks|null           $pay_links    Pay links and their signatures.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?TransactionStore $transactions = null, ?PaymentLinks $links = null, ?PayLinks $pay_links = null ) {
		$this->settings     = $settings ?? new SettingsRepository();
		$this->transactions = $transactions ?? new TransactionStore();
		$this->links        = $links ?? new PaymentLinks( $this->settings, null, $this->transactions );
		$this->pay_links    = $pay_links ?? new PayLinks();
		$this->spots        = new SpotCounter( $this->transactions );
		$this->discounts    = new Discounts();
	}

	/**
	 * What a discount code would do to the total right now, for the checkout's "Apply". Nothing is stored.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $input  The raw input, like start()'s.
	 * @return array{code: string, subtotal: float, discount: float, total: float}|WP_Error
	 */
	public function quote( Button $button, array $input ) {
		if ( $this->code_tries_exceeded() ) {
			return new WP_Error( 'iftp_btn_rate_limited', CheckoutTexts::get( 'rate_limited' ), array( 'status' => 429 ) );
		}

		$available = $this->availability( $button );

		if ( is_wp_error( $available ) ) {
			return $available;
		}

		$code = is_scalar( $input['discount_code'] ?? null ) ? CouponStore::normalize( (string) $input['discount_code'] ) : '';
		$link = $this->read_link( $button, $input );

		if ( is_wp_error( $link ) ) {
			return $link;
		}

		// A link with a signed amount is the merchant's own price; codes don't apply to it.
		if ( '' === $code || $link['signed'] ) {
			return new WP_Error( 'iftp_btn_code_invalid', CheckoutTexts::get( 'code_invalid' ), array( 'status' => 400 ) );
		}

		$spot = $this->read_spot( $button, $input, $link );

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$subtotal = $this->resolve_amount( $button, $input, $link, $spot );

		if ( is_wp_error( $subtotal ) ) {
			return $subtotal;
		}

		$applied = $this->discounts->apply( $code, $button, $subtotal );

		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		return array(
			'code'     => $applied['coupon']['code'],
			'subtotal' => $subtotal,
			'discount' => $applied['discount'],
			'total'    => $applied['total'],
		);
	}

	/**
	 * Whether a button can take a payment right now, or why not.
	 *
	 * @param Button $button The button.
	 * @return true|WP_Error
	 */
	public function availability( Button $button ) {
		if ( ! $button->active ) {
			return new WP_Error( 'iftp_btn_inactive', CheckoutTexts::get( 'unavailable' ), array( 'status' => 404 ) );
		}

		if ( ! $this->settings->is_connected() || array() === $this->links->button_methods( $button ) ) {
			return new WP_Error( 'iftp_btn_not_ready', __( 'Payments are not set up on this site yet.', 'ifthenpay-pay-button' ), array( 'status' => 503 ) );
		}

		if ( Spots::limited( $button ) && SpotCounter::sold_out( $this->spots->left( $button ) ) ) {
			return new WP_Error( 'iftp_btn_sold_out', CheckoutTexts::get( 'all_taken' ), array( 'status' => 410 ) );
		}

		return true;
	}

	/**
	 * Starts a payment and returns the ifthenpay page to send the payer to.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $input  Raw input: amount, the field values, reference, expires, sig and the honeypot.
	 * @param string               $source The page the payer is on.
	 * @return string|WP_Error The ifthenpay page URL, or why the payment couldn't start.
	 */
	public function start( Button $button, array $input, string $source ) {
		$generic = new WP_Error( 'iftp_btn_failed', CheckoutTexts::get( 'failed' ), array( 'status' => 502 ) );

		if ( is_scalar( $input[ self::HONEYPOT ] ?? null ) && '' !== (string) $input[ self::HONEYPOT ] ) {
			return new WP_Error( 'iftp_btn_spam', $generic->get_error_message(), array( 'status' => 400 ) );
		}

		if ( $this->is_rate_limited() ) {
			return new WP_Error( 'iftp_btn_rate_limited', CheckoutTexts::get( 'rate_limited' ), array( 'status' => 429 ) );
		}

		$available = $this->availability( $button );

		if ( is_wp_error( $available ) ) {
			return $available;
		}

		$link = $this->read_link( $button, $input );

		if ( is_wp_error( $link ) ) {
			return $link;
		}

		$snapshot = $this->collect_fields( $button, $input );

		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$spot = $this->read_spot( $button, $input, $link );

		if ( is_wp_error( $spot ) ) {
			return $spot;
		}

		$amount = $this->resolve_amount( $button, $input, $link, $spot );

		if ( is_wp_error( $amount ) ) {
			return $amount;
		}

		$extra = array(
			'button' => array(
				'label' => __( 'Button', 'ifthenpay-pay-button' ),
				'value' => $button->title,
			),
		);

		// A code takes its amount off before anything is stored; a signed link already has the merchant's own price.
		$code    = is_scalar( $input['discount_code'] ?? null ) ? CouponStore::normalize( (string) $input['discount_code'] ) : '';
		$applied = null;

		if ( '' !== $code ) {
			$applied = $link['signed'] ? new WP_Error( 'iftp_btn_code_invalid', CheckoutTexts::get( 'code_invalid' ), array( 'status' => 400 ) ) : $this->discounts->apply( $code, $button, $amount );

			if ( is_wp_error( $applied ) ) {
				return $applied;
			}

			$extra['discount'] = array(
				'label' => __( 'Discount code', 'ifthenpay-pay-button' ),
				'value' => sprintf(
					/* translators: 1: the code, 2: what it took off, 3: the total before it */
					__( '%1$s: -%2$s (was %3$s)', 'ifthenpay-pay-button' ),
					$applied['coupon']['code'],
					self::format_money( $applied['discount'] ),
					self::format_money( $amount )
				),
			);
			$amount            = $applied['total'];
		}

		if ( $button->config['spots']['quantity'] ) {
			$extra['quantity'] = array(
				'label' => __( 'Quantity', 'ifthenpay-pay-button' ),
				'value' => (string) $spot['count'],
			);
		}

		$snapshot = $extra + $snapshot;
		$limited  = Spots::limited( $button );

		// Counting and taking the spot happen under one lock, or two payers could both get the last one.
		if ( $limited && ! SpotCounter::lock( $button->id ) ) {
			return new WP_Error( 'iftp_btn_busy', CheckoutTexts::get( 'busy' ), array( 'status' => 503 ) );
		}

		$free = $limited ? $this->spots->check( $button, $spot['options'], $spot['count'], $spot['extra'] ) : true;

		if ( is_wp_error( $free ) ) {
			SpotCounter::unlock( $button->id );

			return $free;
		}

		$spot['hold_until'] = PaymentLinks::link_ends_at( $this->links->expiry_days( $button ) );

		// A payment from the demo page carries a signed token: it's a real payment, but its ref says TEST- and it takes no spot.
		$test = is_scalar( $input[ TestMode::FIELD ] ?? null ) && TestMode::verify( $button->id, (string) $input[ TestMode::FIELD ] );

		if ( $test ) {
			$spot['count']   = 0;
			$spot['options'] = array();
			$spot['extra']   = array();
		}

		$ref = $this->transactions->create_for_button( $button->id, $amount, $this->links->gateway_key_for( $button ), $snapshot, $link['reference'], esc_url_raw( $source ), $spot, $test );

		if ( $limited ) {
			SpotCounter::unlock( $button->id );
		}

		$record = null !== $ref ? $this->transactions->get( $ref ) : null;

		if ( null === $record ) {
			return $generic;
		}

		// Two payers can race for the last use of a code; whoever comes second gives the payment up.
		if ( null !== $applied && ! $this->discounts->claim( $applied, $record['ref'] ) ) {
			$this->transactions->mark_failed( $record['ref'] );
			$this->transactions->release_spots( $record['ref'] );

			return new WP_Error( 'iftp_btn_code_used_up', CheckoutTexts::get( 'code_used_up' ), array( 'status' => 400 ) );
		}

		$url = $this->links->create_link_for_button( $record, $button );

		if ( '' === $url ) {
			// Nobody can pay a row without a link; failed, so it doesn't sit in Pending forever or keep its spots.
			$this->transactions->mark_failed( $record['ref'] );
			$this->transactions->release_spots( $record['ref'] );

			return $generic;
		}

		/**
		 * Fires when a button starts a payment, before the payer goes to ifthenpay.
		 *
		 * @param string $ref       The transaction reference.
		 * @param int    $button_id The button's post ID.
		 */
		do_action( 'iftp_btn_payment_started', $record['ref'], $button->id );

		return $url;
	}

	/**
	 * Whether this address has tried codes too often this minute, so a script can't walk through guesses.
	 */
	private function code_tries_exceeded(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'iftp_btn_rlc_' . md5( wp_salt() . $ip ) . '_' . (int) floor( time() / MINUTE_IN_SECONDS );
		$n   = (int) get_transient( $key );

		if ( $n >= self::CODE_TRIES_PER_MINUTE ) {
			return true;
		}

		set_transient( $key, $n + 1, 2 * MINUTE_IN_SECONDS );

		return false;
	}

	/**
	 * The reference and whether the link was signed. A signature that doesn't check out stops the payment:
	 * someone edited the amount or the reference.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $input  The raw input.
	 * @return array{reference: string, signed: bool, amount: float}|WP_Error
	 */
	private function read_link( Button $button, array $input ) {
		$reference = is_scalar( $input['reference'] ?? null ) ? PayLinks::clean_reference( (string) $input['reference'] ) : '';
		$signature = is_scalar( $input['sig'] ?? null ) ? (string) preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $input['sig'] ) ) : '';

		if ( '' === $signature ) {
			return array(
				'reference' => $reference,
				'signed'    => false,
				'amount'    => 0.0,
			);
		}

		$amount  = is_scalar( $input['amount'] ?? null ) ? (float) PayLinks::clean_amount( (string) $input['amount'] ) : 0.0;
		$expires = is_numeric( $input['expires'] ?? null ) ? (int) $input['expires'] : 0;

		if ( ! $this->pay_links->verify( $button->id, $amount, $reference, $expires, $signature ) ) {
			return new WP_Error( 'iftp_btn_bad_signature', CheckoutTexts::get( 'link_invalid' ), array( 'status' => 403 ) );
		}

		return array(
			'reference' => $reference,
			'signed'    => true,
			'amount'    => $amount,
		);
	}

	/**
	 * How many spots the payer takes, and on which options.
	 *
	 * @param Button                                                $button The button.
	 * @param array<string, mixed>                                  $input  The raw input, already checked by collect_fields().
	 * @param array{reference: string, signed: bool, amount: float} $link What read_link() found.
	 * @return array{count: int, options: array<int, string>, extra: array<int, string>}|WP_Error
	 */
	private function read_spot( Button $button, array $input, array $link ) {
		$spots = $button->config['spots'];
		$count = 1;

		if ( $spots['quantity'] ) {
			$raw = is_scalar( $input['quantity'] ?? null ) ? trim( (string) $input['quantity'] ) : '1';

			// A signed total is the price of one booking, so it can't be stretched over more spots.
			$most = $link['signed'] ? 1 : $spots['max_each'];

			if ( ! ctype_digit( $raw ) || (int) $raw < 1 || (int) $raw > $most ) {
				return new WP_Error( 'iftp_btn_bad_quantity', CheckoutTexts::get( 'bad_quantity', array( 'max' => $most ) ), array( 'status' => 400 ) );
			}

			$count = (int) $raw;
		}

		$options = array();

		if ( Spots::MODE_OPTIONS === $spots['mode'] ) {
			// From the raw answer, not the snapshot: Multiple choice joins it with ", " there, and a label can hold a comma.
			$raw     = is_scalar( $input[ FormFields::input_name( $spots['field'] ) ] ?? null ) ? (string) $input[ FormFields::input_name( $spots['field'] ) ] : '';
			$options = array_values( array_unique( array_filter( array_map( static fn( string $part ): string => trim( sanitize_text_field( $part ) ), explode( FormFields::MULTI_SEPARATOR, $raw ) ), static fn( string $part ): bool => null !== Spots::option( $button, $part ) ) ) );

			if ( array() === $options ) {
				return new WP_Error( 'iftp_btn_required', CheckoutTexts::get( 'pick_option' ), array( 'status' => 400 ) );
			}
		}

		return array(
			'count'   => $count,
			'options' => $options,
			'extra'   => Spots::extra_keys( $button, $input ),
		);
	}

	/**
	 * The amount to charge. A signed amount wins; otherwise the button's mode decides, within its limits.
	 * A fixed amount is per spot: each picked option's price, or the button's, plus any priced options on the other fields, times how many.
	 *
	 * @param Button                                                                    $button The button.
	 * @param array<string, mixed>                                                      $input  The raw input.
	 * @param array{reference: string, signed: bool, amount: float}                     $link   What read_link() found.
	 * @param array{count: int, options: array<int, string>, extra: array<int, string>} $spot   What read_spot() found.
	 * @return float|WP_Error
	 */
	private function resolve_amount( Button $button, array $input, array $link, array $spot ) {
		if ( $link['signed'] && $link['amount'] >= Button::MIN_AMOUNT ) {
			return $link['amount'];
		}

		// "From the link" takes its total from a signed link only: a typed amount would let anyone pay what they like.
		if ( Button::MODE_LINK === $button->mode() ) {
			return new WP_Error( 'iftp_btn_no_amount', CheckoutTexts::get( 'link_no_amount' ), array( 'status' => 400 ) );
		}

		if ( Button::MODE_FIXED === $button->mode() ) {
			$unit = array() === $spot['options'] ? Spots::unit_price( $button, '' ) : array_sum( array_map( static fn( string $option ): float => Spots::unit_price( $button, $option ), $spot['options'] ) );
			// Priced options on the other fields (accessories, say) add to every spot.
			$unit += FormFields::extras( $button->config['form'], Spots::MODE_OPTIONS === $button->config['spots']['mode'] ? $button->config['spots']['field'] : '', $input );

			if ( $unit < Button::MIN_AMOUNT ) {
				return new WP_Error( 'iftp_btn_no_amount', __( 'This payment has no amount set.', 'ifthenpay-pay-button' ), array( 'status' => 400 ) );
			}

			$total = round( $unit * $spot['count'], 2 );

			return $total <= Button::MAX_AMOUNT ? $total : new WP_Error( 'iftp_btn_amount_range', CheckoutTexts::get( 'too_much' ), array( 'status' => 400 ) );
		}

		$raw    = is_scalar( $input['amount'] ?? null ) ? PayLinks::clean_amount( (string) $input['amount'] ) : '';
		$amount = '' !== $raw ? (float) $raw : 0.0;

		if ( $amount <= 0 ) {
			return new WP_Error( 'iftp_btn_no_amount', CheckoutTexts::get( 'enter_amount' ), array( 'status' => 400 ) );
		}

		if ( $amount < $button->config['min'] || $amount > $button->config['max'] ) {
			return new WP_Error(
				'iftp_btn_amount_range',
				CheckoutTexts::get(
					'amount_range',
					array(
						'min' => self::format_money( $button->config['min'] ),
						'max' => self::format_money( $button->config['max'] ),
					)
				),
				array( 'status' => 400 )
			);
		}

		return $amount;
	}

	/**
	 * The payer's details as the snapshot the table stores, after checking the button's required fields.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $input  The raw input.
	 * @return array<string, array{label: string, value: string}>|WP_Error
	 */
	private function collect_fields( Button $button, array $input ) {
		$labels   = self::field_labels( $button );
		$keys     = array(
			'name'  => 'customer_name',
			'email' => 'customer_email',
			'phone' => 'phone',
			'nif'   => 'nif',
			'note'  => 'note',
		);
		$snapshot = array();

		foreach ( $button->visible_fields() as $field => $required ) {
			$raw   = is_scalar( $input[ $field ] ?? null ) ? (string) $input[ $field ] : '';
			$value = self::clean_field( $field, $raw );

			if ( '' === $value && $required ) {
				return new WP_Error( 'iftp_btn_required', CheckoutTexts::get( 'fill_in', array( 'field' => $labels[ $field ] ) ), array( 'status' => 400 ) );
			}

			if ( 'email' === $field && '' !== $value && ! is_email( $value ) ) {
				return new WP_Error( 'iftp_btn_bad_email', CheckoutTexts::get( 'bad_email' ), array( 'status' => 400 ) );
			}

			if ( 'nif' === $field && '' !== $value && 1 !== preg_match( '/^[A-Z0-9]{5,20}$/', $value ) ) {
				return new WP_Error( 'iftp_btn_bad_nif', CheckoutTexts::get( 'bad_nif' ), array( 'status' => 400 ) );
			}

			if ( '' !== $value ) {
				$snapshot[ $keys[ $field ] ] = array(
					'label' => $labels[ $field ],
					'value' => $value,
				);
			}
		}

		// A field its condition hides wasn't asked, so it's neither required nor kept.
		$shown = FormFields::visibility( $button->config['form'], FormFields::answers( $button->config['form'], $input ) );

		foreach ( FormFields::inputs( $button->config['form'] ) as $item ) {
			if ( empty( $shown[ $item['id'] ] ) ) {
				continue;
			}

			$raw   = is_scalar( $input[ FormFields::input_name( $item['id'] ) ] ?? null ) ? (string) $input[ FormFields::input_name( $item['id'] ) ] : '';
			$value = self::clean_custom( $item, $raw );

			if ( is_wp_error( $value ) ) {
				return $value;
			}

			if ( '' === $value && $item['required'] ) {
				$message = 'checkbox' === $item['type']
					? CheckoutTexts::get( 'tick', array( 'field' => FormFields::plain_label( $item['label'] ) ) )
					: CheckoutTexts::get( 'fill_in', array( 'field' => $item['label'] ) );

				return new WP_Error( 'iftp_btn_required', $message, array( 'status' => 400 ) );
			}

			if ( '' !== $value ) {
				$snapshot[ FormFields::input_name( $item['id'] ) ] = array(
					'label' => FormFields::plain_label( $item['label'] ),
					'value' => $value,
				);
			}
		}

		return $snapshot;
	}

	/**
	 * One of the merchant's own fields, cleaned and checked for its type.
	 *
	 * @param array<string, mixed> $item The field.
	 * @param string               $raw  What the payer sent.
	 * @phpstan-param FormItem $item
	 * @return string|WP_Error The value to store, '' for none, or why it isn't valid.
	 */
	private static function clean_custom( array $item, string $raw ) {
		$value = self::clean_typed( $item, $raw );

		return is_wp_error( $value ) ? $value : self::check_rules( $item, $value );
	}

	/**
	 * The merchant's limits on a field: a smallest and largest number, whole numbers only, a shortest and longest text.
	 * An empty answer passes; whether it is allowed is the field's "required".
	 *
	 * @param array<string, mixed> $item  The field.
	 * @param string               $value The cleaned value.
	 * @phpstan-param FormItem $item
	 * @return string|WP_Error The value, or why it breaks a limit.
	 */
	private static function check_rules( array $item, string $value ) {
		$rules = $item['rules'];
		$field = $item['label'];
		$fail  = static fn( string $key, array $tokens ): WP_Error => new WP_Error( 'iftp_btn_out_of_range', CheckoutTexts::get( $key, array( 'field' => $field ) + $tokens ), array( 'status' => 400 ) );

		if ( '' === $value ) {
			return $value;
		}

		if ( 'number' === $item['type'] ) {
			$number = (float) $value;

			if ( $rules['whole'] && floor( $number ) !== $number ) {
				return $fail( 'number_whole', array() );
			}

			if ( '' !== $rules['min'] && $number < (float) $rules['min'] ) {
				return $fail( 'number_min', array( 'min' => $rules['min'] ) );
			}

			if ( '' !== $rules['max'] && $number > (float) $rules['max'] ) {
				return $fail( 'number_max', array( 'max' => $rules['max'] ) );
			}
		}

		if ( in_array( $item['type'], array( 'text', 'textarea', 'tel' ), true ) ) {
			$length = mb_strlen( $value );

			if ( $rules['min_len'] > 0 && $length < $rules['min_len'] ) {
				return $fail( 'text_min', array( 'min' => (string) $rules['min_len'] ) );
			}

			if ( $rules['max_len'] > 0 && $length > $rules['max_len'] ) {
				return $fail( 'text_max', array( 'max' => (string) $rules['max_len'] ) );
			}
		}

		return $value;
	}

	/**
	 * A field's value cleaned for its type, before the merchant's limits.
	 *
	 * @param array<string, mixed> $item The field.
	 * @param string               $raw  What the payer sent.
	 * @phpstan-param FormItem $item
	 * @return string|WP_Error The value to store, '' for none, or why it isn't valid.
	 */
	private static function clean_typed( array $item, string $raw ) {
		$invalid = new WP_Error( 'iftp_btn_invalid_field', CheckoutTexts::get( 'check_field', array( 'field' => $item['label'] ) ), array( 'status' => 400 ) );

		switch ( $item['type'] ) {
			case 'textarea':
				return mb_substr( trim( sanitize_textarea_field( $raw ) ), 0, 1000 );
			case 'email':
				$value = self::clean_field( 'email', $raw );

				return '' === $value || is_email( $value ) ? $value : new WP_Error( 'iftp_btn_bad_email', CheckoutTexts::get( 'bad_email' ), array( 'status' => 400 ) );
			case 'tel':
				return mb_substr( (string) preg_replace( '/[^0-9+ ()-]/', '', $raw ), 0, 30 );
			case 'number':
				$value = str_replace( ',', '.', trim( $raw ) );

				return '' === $value || is_numeric( $value ) ? mb_substr( $value, 0, 30 ) : $invalid;
			case 'date':
				$value = trim( $raw );

				if ( '' === $value ) {
					return '';
				}

				return 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $value : $invalid;
			case 'select':
			case 'radio':
				$value = trim( sanitize_text_field( $raw ) );

				return '' === $value || in_array( $value, $item['options'], true ) ? $value : $invalid;
			case 'checkboxes':
				$picked = array_values( array_filter( array_map( static fn( string $part ): string => trim( sanitize_text_field( $part ) ), explode( FormFields::MULTI_SEPARATOR, $raw ) ) ) );

				return array() === array_diff( $picked, $item['options'] ) ? implode( ', ', $picked ) : $invalid;
			case 'checkbox':
				return '' !== $raw && '0' !== $raw ? __( 'Yes', 'ifthenpay-pay-button' ) : '';
			default:
				return mb_substr( trim( sanitize_text_field( $raw ) ), 0, self::FIELD_MAX_LENGTH );
		}
	}

	/**
	 * Each field's label, as the payer sees it.
	 *
	 * @param Button $button The button, for its note label.
	 * @return array<string, string>
	 */
	public static function field_labels( Button $button ): array {
		return array(
			'name'  => CheckoutTexts::get( 'name' ),
			'email' => CheckoutTexts::get( 'email' ),
			'phone' => CheckoutTexts::get( 'phone' ),
			'nif'   => CheckoutTexts::get( 'nif' ),
			'note'  => $button->note_label(),
		);
	}

	/**
	 * One field's value, cleaned for its kind.
	 *
	 * @param string $field One of Button::FIELDS.
	 * @param string $raw   The raw value.
	 */
	private static function clean_field( string $field, string $raw ): string {
		switch ( $field ) {
			case 'email':
				// Not sanitize_email() yet: it would quietly turn a typo into a different address instead of failing is_email().
				return mb_substr( strtolower( trim( sanitize_text_field( $raw ) ) ), 0, self::FIELD_MAX_LENGTH );
			case 'phone':
				return mb_substr( (string) preg_replace( '/[^0-9+ ]/', '', $raw ), 0, 30 );
			case 'nif':
				return strtoupper( (string) preg_replace( '/[^A-Za-z0-9]/', '', $raw ) );
			case 'note':
				return mb_substr( trim( sanitize_textarea_field( $raw ) ), 0, self::NOTE_MAX_LENGTH );
			default:
				return mb_substr( trim( sanitize_text_field( $raw ) ), 0, self::FIELD_MAX_LENGTH );
		}
	}

	/**
	 * Counts this start against the per-address and site-wide limits, and says whether either is used up.
	 */
	private function is_rate_limited(): bool {
		$minute = (int) floor( time() / MINUTE_IN_SECONDS );
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters how many payments the whole site may start per minute, whatever the address.
		 *
		 * @param int $limit Payments per minute.
		 */
		$site_limit = (int) apply_filters( 'iftp_btn_site_rate_limit', self::PER_SITE_PER_MINUTE );

		// The address is hashed with a salt, so no IP ends up stored in an option name.
		$keys = array(
			'iftp_btn_rl_' . md5( wp_salt() . $ip ) . '_' . $minute => self::PER_IP_PER_MINUTE,
			'iftp_btn_rl_site_' . $minute => $site_limit,
		);

		foreach ( $keys as $key => $limit ) {
			$count = (int) get_transient( $key );

			if ( $count >= $limit ) {
				return true;
			}

			set_transient( $key, $count + 1, 2 * MINUTE_IN_SECONDS );
		}

		return false;
	}

	/**
	 * An amount the way the payer reads it, in euros.
	 *
	 * @param float $amount The amount.
	 */
	public static function format_money( float $amount ): string {
		return number_format_i18n( $amount, 2 ) . ' €';
	}
}
