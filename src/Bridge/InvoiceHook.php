<?php
/**
 * A button's request to an invoicing app when a payment is paid.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Bridge;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\TransactionStore;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a paid payment straight to an invoicing API (InvoiceXpress, Moloni, Vendus, TOConline…) or any other
 * endpoint, in the shape that API wants: the merchant writes the request, and merge tags fill it in.
 * The Connect tab's webhook stays for Zapier, Make and n8n; this one skips the middleman.
 *
 * @phpstan-import-type Transaction from TransactionStore
 * @phpstan-type InvoiceHookConfig array{enabled: bool, url: string, method: string, format: string, headers: string, body: string}
 * @phpstan-type InvoiceHookResult array{ok: bool, code: int, message: string, body: string}
 * @phpstan-type InvoiceHookLog array{time: int, ref: string, ok: bool, code: int, message: string}
 */
class InvoiceHook {

	public const RETRY_HOOK = 'iftp_btn_retry_invoice_hook';
	public const LOG_META   = '_iftp_btn_invoice_log';

	public const METHODS = array( 'POST', 'PUT' );
	public const FORMATS = array( 'json', 'form' );

	// The final-consumer tax number Portuguese invoices use when the payer gives none.
	public const FINAL_CONSUMER_NIF = '999999990';

	// Minutes to wait before each retry; the first send is attempt 0.
	private const RETRY_DELAYS = array( 5, 30, 180 );

	private const TIMEOUT  = 10;
	private const LOG_SIZE = 5;
	private const MAX_BODY = 20000;

	// Headers WordPress sets itself, or that would break the request.
	private const RESERVED_HEADERS = array( 'host', 'content-length', 'content-type', 'transfer-encoding', 'connection' );

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the sender.
	 *
	 * @param ButtonRepository|null $buttons      Buttons storage.
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?ButtonRepository $buttons = null, ?TransactionStore $transactions = null ) {
		$this->buttons      = $buttons ?? new ButtonRepository();
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Hooks the paid event and the retry.
	 */
	public function register(): void {
		add_action( 'iftp_btn_payment_paid', array( $this, 'on_paid' ) );
		add_action( self::RETRY_HOOK, array( $this, 'retry' ), 10, 2 );
	}

	/**
	 * Off, with a body that shows the shape most invoicing APIs want.
	 *
	 * @return InvoiceHookConfig
	 */
	public static function defaults(): array {
		return array(
			'enabled' => false,
			'url'     => '',
			'method'  => 'POST',
			'format'  => 'json',
			'headers' => '',
			'body'    => self::sample_body(),
		);
	}

	/**
	 * A starting body: the customer, one line for the button, and the total.
	 */
	public static function sample_body(): string {
		return "{\n\t\"reference\": \"{payment_id}\",\n\t\"date\": \"{date_iso}\",\n\t\"customer\": {\n\t\t\"name\": \"{name}\",\n\t\t\"email\": \"{email}\",\n\t\t\"tax_number\": \"{nif_final}\"\n\t},\n\t\"items\": [\n\t\t{\n\t\t\t\"description\": \"{button}\",\n\t\t\t\"quantity\": {quantity},\n\t\t\t\"unit_price\": {unit_price}\n\t\t}\n\t],\n\t\"total\": {amount_raw},\n\t\"currency\": \"EUR\",\n\t\"payment_method\": \"{method}\"\n}";
	}

	/**
	 * The tags only this request has, on top of MergeTags::available(): plain numbers and dates an API can read.
	 *
	 * @return array<string, string>
	 */
	public static function extra_tags(): array {
		return array(
			'{amount_raw}' => __( 'Amount as a number (49.90)', 'ifthenpay-pay-button' ),
			'{unit_price}' => __( 'Amount ÷ quantity, as a number', 'ifthenpay-pay-button' ),
			'{nif_final}'  => __( 'Tax number, or 999999990 when blank', 'ifthenpay-pay-button' ),
			'{date_iso}'   => __( 'Date as 2026-10-23', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * Cleans the editor's settings.
	 *
	 * @param mixed $input The raw settings.
	 * @return InvoiceHookConfig
	 */
	public static function sanitize( $input ): array {
		$hook  = self::defaults();
		$input = is_array( $input ) ? $input : array();

		$hook['enabled'] = ! empty( $input['enabled'] );
		$hook['url']     = is_string( $input['url'] ?? null ) ? esc_url_raw( trim( $input['url'] ), array( 'https', 'http' ) ) : '';
		$hook['method']  = is_string( $input['method'] ?? null ) && in_array( $input['method'], self::METHODS, true ) ? $input['method'] : 'POST';
		$hook['format']  = is_string( $input['format'] ?? null ) && in_array( $input['format'], self::FORMATS, true ) ? $input['format'] : 'json';
		$hook['headers'] = is_string( $input['headers'] ?? null ) ? self::clean_headers( $input['headers'] ) : '';

		if ( is_string( $input['body'] ?? null ) ) {
			// Not text-field sanitized: it's JSON or form pairs for another API, and it's only ever sent there or printed escaped.
			$hook['body'] = mb_substr( trim( str_replace( array( "\r\n", "\0" ), array( "\n", '' ), wp_check_invalid_utf8( $input['body'] ) ) ), 0, self::MAX_BODY );
		}

		// Nothing to send to means it's off, whatever the switch says.
		if ( '' === $hook['url'] ) {
			$hook['enabled'] = false;
		}

		return $hook;
	}

	/**
	 * "Name: value" lines, one header each, with anything that could split a header dropped.
	 *
	 * @param string $raw The textarea.
	 */
	private static function clean_headers( string $raw ): string {
		$lines = array();

		$split = preg_split( '/\R/', $raw );

		foreach ( false !== $split ? $split : array() as $line ) {
			$parts = explode( ':', $line, 2 );
			$name  = trim( $parts[0] );
			$value = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $parts[1] ?? '' ) );

			if ( 1 === preg_match( '/^[A-Za-z0-9-]{1,64}$/', $name ) && '' !== $value && ! in_array( strtolower( $name ), self::RESERVED_HEADERS, true ) ) {
				$lines[] = $name . ': ' . mb_substr( $value, 0, 1000 );
			}
		}

		return implode( "\n", array_slice( $lines, 0, 20 ) );
	}

	/**
	 * Sends a paid payment, when its button has the request on.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function on_paid( string $ref ): void {
		$this->dispatch( $ref, 0 );
	}

	/**
	 * A scheduled retry.
	 *
	 * @param string $ref     The transaction reference.
	 * @param int    $attempt Which attempt this is.
	 */
	public function retry( $ref, $attempt ): void {
		$this->dispatch( (string) $ref, (int) $attempt );
	}

	/**
	 * Sends the editor's unsaved settings with this button's latest paid payment, or a made-up one.
	 *
	 * @param array<string, mixed> $hook   Sanitized settings, straight from the form.
	 * @param Button               $button The button.
	 * @phpstan-param InvoiceHookConfig $hook
	 * @return InvoiceHookResult
	 */
	public function send_test( array $hook, Button $button ): array {
		$record = $this->transactions->latest_paid_for_button( $button->id );
		$values = null !== $record ? self::values( $record, $button ) : self::sample_values( $button );

		return $this->send( $hook, $values );
	}

	/**
	 * Builds and sends one payment, logs how it went, and schedules a retry when the other side didn't take it.
	 *
	 * @param string $ref     The transaction reference.
	 * @param int    $attempt Which attempt this is.
	 */
	private function dispatch( string $ref, int $attempt ): void {
		$record = $this->transactions->get( $ref );
		$button = null !== $record && $record['button_id'] > 0 ? $this->buttons->find( $record['button_id'] ) : null;

		if ( null === $record || null === $button || ! $button->config['invoice_hook']['enabled'] || TransactionStore::STATUS_PAID !== $record['status'] ) {
			return;
		}

		$result = $this->send( $button->config['invoice_hook'], self::values( $record, $button ) );

		self::remember( $button->id, $ref, $result );

		// A 4xx means the request itself is wrong; sending it again won't help.
		if ( $result['ok'] || ( $result['code'] >= 400 && $result['code'] < 500 && 429 !== $result['code'] ) || ! isset( self::RETRY_DELAYS[ $attempt ] ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::RETRY_DELAYS[ $attempt ] * MINUTE_IN_SECONDS, self::RETRY_HOOK, array( $ref, $attempt + 1 ) );
	}

	/**
	 * Every tag's value for a payment: the popup and description tags, plus the extra ones.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @param Button               $button The button.
	 * @phpstan-param Transaction $record
	 * @return array<string, string>
	 */
	public static function values( array $record, Button $button ): array {
		$values   = MergeTags::values( $record, $button );
		$quantity = max( 1, (int) $values['quantity'] );

		return $values + array(
			'amount_raw' => number_format( $record['amount'], 2, '.', '' ),
			'unit_price' => number_format( $record['amount'] / $quantity, 2, '.', '' ),
			'nif_final'  => '' !== $values['nif'] ? $values['nif'] : self::FINAL_CONSUMER_NIF,
			'date_iso'   => Dates::local( 'Y-m-d', $record['created_at'] ),
		);
	}

	/**
	 * Made-up values for a test before the button has any paid payment.
	 *
	 * @param Button $button The button.
	 * @return array<string, string>
	 */
	private static function sample_values( Button $button ): array {
		$amount = Button::MODE_FIXED === $button->mode() ? $button->config['amount'] : 10.0;
		$values = array(
			'name'       => 'Ana Silva',
			'email'      => 'ana@example.com',
			'phone'      => '+351 912 345 678',
			'nif'        => '',
			'note'       => '',
			'amount'     => number_format_i18n( $amount, 2 ) . ' €',
			'quantity'   => '1',
			'payment_id' => 'IFTP_TEST000000',
			'order'      => 'ORDER-123',
			'method'     => 'MBWAY',
			'button'     => $button->title,
			'site'       => (string) get_bloginfo( 'name' ),
			'date'       => Dates::local( (string) get_option( 'date_format' ) ),
		);

		foreach ( $button->config['form'] as $item ) {
			$values[ 'field:' . $item['id'] ] = array() !== $item['options'] ? $item['options'][0] : 'test';
		}

		return $values + array(
			'amount_raw' => number_format( $amount, 2, '.', '' ),
			'unit_price' => number_format( $amount, 2, '.', '' ),
			'nif_final'  => self::FINAL_CONSUMER_NIF,
			'date_iso'   => Dates::local( 'Y-m-d' ),
		);
	}

	/**
	 * The request with the tags filled in: JSON-escaped in a JSON body, URL-encoded in form pairs.
	 *
	 * @param array<string, mixed>  $hook   Sanitized settings.
	 * @param array<string, string> $values From values().
	 * @phpstan-param InvoiceHookConfig $hook
	 * @return array{headers: array<string, string>, body: string}|WP_Error
	 */
	public static function build( array $hook, array $values ) {
		$headers = array( 'Content-Type' => 'json' === $hook['format'] ? 'application/json' : 'application/x-www-form-urlencoded' );

		foreach ( '' !== $hook['headers'] ? explode( "\n", $hook['headers'] ) : array() as $line ) {
			$parts = explode( ':', $line, 2 );
			// A value can't break the header onto a new line, whatever the payer typed.
			$headers[ trim( $parts[0] ) ] = trim( (string) preg_replace( '/[\r\n]+/', ' ', MergeTags::replace( $parts[1] ?? '', $values, false ) ) );
		}

		if ( 'form' === $hook['format'] ) {
			$pairs = array();

			$split = preg_split( '/[\n&]/', $hook['body'] );

			foreach ( false !== $split ? $split : array() as $pair ) {
				$parts = explode( '=', trim( $pair ), 2 );

				if ( '' !== $parts[0] ) {
					$pairs[] = rawurlencode( $parts[0] ) . '=' . rawurlencode( MergeTags::replace( $parts[1] ?? '', $values, false ) );
				}
			}

			return array(
				'headers' => $headers,
				'body'    => implode( '&', $pairs ),
			);
		}

		// Each value as the inside of a JSON string, so a quote in a name can't break out of "{name}".
		$escaped = array_map( static fn( string $value ): string => substr( (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 1, -1 ), $values );
		$body    = MergeTags::replace( $hook['body'], $escaped, false );

		if ( '' !== $body && null === json_decode( $body ) ) {
			return new WP_Error( 'iftp_btn_invoice_json', __( 'The body isn\'t valid JSON once the tags are filled in. Check the quotes and commas, and put text tags inside quotes.', 'ifthenpay-pay-button' ) );
		}

		return array(
			'headers' => $headers,
			'body'    => $body,
		);
	}

	/**
	 * Builds and sends the request. wp_safe_remote_request() refuses local and private addresses.
	 *
	 * @param array<string, mixed>  $hook   Sanitized settings.
	 * @param array<string, string> $values From values().
	 * @phpstan-param InvoiceHookConfig $hook
	 * @return InvoiceHookResult
	 */
	private function send( array $hook, array $values ): array {
		$request = '' !== $hook['url'] ? self::build( $hook, $values ) : new WP_Error( 'iftp_btn_invoice_url', __( 'Add the address to send to.', 'ifthenpay-pay-button' ) );

		if ( is_wp_error( $request ) ) {
			return array(
				'ok'      => false,
				'code'    => 0,
				'message' => $request->get_error_message(),
				'body'    => '',
			);
		}

		$response = wp_safe_remote_request(
			$hook['url'],
			array(
				'method'      => $hook['method'],
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => $request['headers'] + array( 'User-Agent' => 'ifthenpay-pay-button/' . IFTP_BTN_VERSION . '; ' . home_url( '/' ) ),
				'body'        => $request['body'],
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- a failed delivery is worth a line in the log for whoever debugs the invoicing side.
			error_log( 'ifthenpay Pay Button: invoicing request to ' . $hook['url'] . ' failed - ' . $response->get_error_message() );

			return array(
				'ok'      => false,
				'code'    => 0,
				'message' => $response->get_error_message(),
				'body'    => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'ok'      => $code >= 200 && $code < 300,
			'code'    => $code,
			'message' => (string) wp_remote_retrieve_response_message( $response ),
			// The start of what it said, so a test can show why an API refused it.
			'body'    => mb_substr( wp_strip_all_tags( (string) wp_remote_retrieve_body( $response ) ), 0, 400 ),
		);
	}

	/**
	 * The last few deliveries, newest first, for the editor.
	 *
	 * @param int $button_id The button.
	 * @return array<int, InvoiceHookLog>
	 */
	public static function log( int $button_id ): array {
		$log     = get_post_meta( $button_id, self::LOG_META, true );
		$entries = array();

		foreach ( is_array( $log ) ? $log : array() as $entry ) {
			if ( is_array( $entry ) ) {
				$entries[] = array(
					'time'    => (int) ( $entry['time'] ?? 0 ),
					'ref'     => (string) ( $entry['ref'] ?? '' ),
					'ok'      => ! empty( $entry['ok'] ),
					'code'    => (int) ( $entry['code'] ?? 0 ),
					'message' => (string) ( $entry['message'] ?? '' ),
				);
			}
		}

		return $entries;
	}

	/**
	 * Adds a delivery to the button's log.
	 *
	 * @param int    $button_id The button.
	 * @param string $ref       The payment.
	 * @param array  $result    How it went.
	 * @phpstan-param InvoiceHookResult $result
	 */
	private static function remember( int $button_id, string $ref, array $result ): void {
		$entry = array(
			'time'    => time(),
			'ref'     => $ref,
			'ok'      => $result['ok'],
			'code'    => $result['code'],
			'message' => mb_substr( '' !== $result['body'] && ! $result['ok'] ? $result['message'] . ' · ' . $result['body'] : $result['message'], 0, 300 ),
		);

		update_post_meta( $button_id, self::LOG_META, array_slice( array_merge( array( $entry ), self::log( $button_id ) ), 0, self::LOG_SIZE ) );
	}
}
