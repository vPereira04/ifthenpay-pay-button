<?php
/**
 * Pay by Link creation for stored payments.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Api;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asks ifthenpay for a hosted payment page for a stored payment, with signed return URLs.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class PaymentLinks {

	public const RETURN_VAR = 'iftp_btn_pay';
	public const RETURN_SIG = 'iftp_btn_sig';

	// Prefixed, since a bare `ref` is what affiliate plugins read.
	public const RETURN_REF = 'iftp_btn_txn_ref';
	public const RETURN_TXN = 'iftp_btn_txn_id';

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Ifthenpay API client.
	 *
	 * @var IfthenpayClient
	 */
	private IfthenpayClient $client;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the service.
	 *
	 * @param SettingsRepository|null $settings     Settings storage.
	 * @param IfthenpayClient|null    $client       Ifthenpay API client.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?IfthenpayClient $client = null, ?TransactionStore $transactions = null ) {
		$this->settings     = $settings ?? new SettingsRepository();
		$this->client       = $client ?? new IfthenpayClient();
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Whether ifthenpay is set up enough to create a link: connected, a Gateway Key, and at least one enabled method.
	 */
	public function is_ready(): bool {
		return $this->settings->is_connected() && '' !== $this->settings->get_gateway_key() && array() !== $this->settings->get_enabled_methods();
	}

	/**
	 * Creates and stores a link for a pending row, with the site-wide description, methods and expiry.
	 *
	 * @param array<string, mixed> $record A pending transaction row.
	 * @phpstan-param Transaction $record
	 * @return string The new link, or '' when ifthenpay isn't set up or refused it.
	 */
	public function create_link_for( array $record ): string {
		if ( ! $this->is_ready() ) {
			return '';
		}

		return $this->request_link( $record, self::description( $this->settings->get_description(), $record, null ), $this->settings->get_enabled_methods(), $this->settings->get_default_method_position(), $this->settings->get_expiry_days() );
	}

	/**
	 * Creates and stores a link for a payment a button started, with that button's methods, description and expiry.
	 *
	 * @param array<string, mixed> $record A pending transaction row.
	 * @param Button               $button The button it came from.
	 * @phpstan-param Transaction $record
	 * @return string The new link, or '' when ifthenpay isn't set up or refused it.
	 */
	public function create_link_for_button( array $record, Button $button ): string {
		$methods = $this->button_methods( $button );

		if ( ! $this->settings->is_connected() || '' === $this->settings->get_gateway_key() || array() === $methods ) {
			return '';
		}

		$entities = array_column( $methods, 'entity' );
		$default  = in_array( $this->settings->get_default_method(), $entities, true ) ? $this->settings->get_default_method_position() : '';
		$expiry   = $this->expiry_days();
		// The button's own description wins; otherwise the hosted payment page description from Gateway Configuration.
		$template = '' !== $button->config['pay_description'] ? $button->config['pay_description'] : $this->settings->get_description();

		return $this->request_link( $record, self::description( $template, $record, $button ), $methods, $default, $expiry );
	}

	/**
	 * How many days a link stays payable, from Gateway Configuration.
	 */
	public function expiry_days(): int {
		return max( 1, $this->settings->get_expiry_days() );
	}

	/**
	 * When a link made now stops taking payment: the end of its `expiredate` day. I take whichever ends later,
	 * the site's day or Lisbon's, so a spot is never freed while ifthenpay could still take the money for it.
	 *
	 * @param int $days Days the link stays payable.
	 */
	public static function link_ends_at( int $days ): int {
		$date = Dates::local( 'Y-m-d', time() + $days * DAY_IN_SECONDS );
		$ends = array();

		foreach ( array( wp_timezone(), new \DateTimeZone( 'Europe/Lisbon' ) ) as $zone ) {
			$end    = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date . ' 23:59:59', $zone );
			$ends[] = false !== $end ? $end->getTimestamp() : time() + ( $days + 1 ) * DAY_IN_SECONDS;
		}

		return max( $ends );
	}

	/**
	 * A description template with the payment's merge tags filled in, cut to what ifthenpay takes.
	 *
	 * @param string               $template The text, maybe with {tags}.
	 * @param array<string, mixed> $record   The transaction row.
	 * @param Button|null          $button   Its button, if any.
	 */
	private static function description( string $template, array $record, ?Button $button ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', MergeTags::replace( $template, MergeTags::values( $record, $button ), false ) ) );

		return mb_substr( '' !== $text ? $text : (string) get_bloginfo( 'name' ), 0, 200 );
	}

	/**
	 * The enabled methods a button offers: all of them, or the ones it picked that are still enabled.
	 *
	 * @param Button $button The button.
	 * @return array<int, array{entity: string, account: string}>
	 */
	public function button_methods( Button $button ): array {
		$enabled = $this->settings->get_enabled_methods();

		if ( array() === $button->config['methods'] ) {
			return $enabled;
		}

		$picked = array_values( array_filter( $enabled, static fn( array $method ): bool => in_array( strtoupper( $method['entity'] ), $button->config['methods'], true ) ) );

		// A button whose picks were all switched off site-wide falls back to every method, rather than not working at all.
		return array() !== $picked ? $picked : $enabled;
	}

	/**
	 * Asks ifthenpay for the link and stores it on the row.
	 *
	 * @param array<string, mixed>                               $record      A pending transaction row.
	 * @param string                                             $description What the payer reads on the ifthenpay page.
	 * @param array<int, array{entity: string, account: string}> $methods     The methods to offer.
	 * @param string                                             $position    The catalog Position of the pre-selected method, or ''.
	 * @param int                                                $expiry_days Days the link stays payable.
	 * @phpstan-param Transaction $record
	 * @return string The new link, or '' when ifthenpay refused it.
	 */
	private function request_link( array $record, string $description, array $methods, string $position, int $expiry_days ): string {
		$gateway_key  = $this->settings->get_gateway_key();
		$ref          = $record['ref'];
		$redirect_url = $this->client->create_payment_link(
			$gateway_key,
			IfthenpayPayload::build_payment_payload(
				$ref,
				$record['amount'],
				$description,
				$methods,
				array(
					'success' => self::return_url( 'success', $ref ),
					'error'   => self::return_url( 'error', $ref ),
					'cancel'  => self::return_url( 'cancel', $ref ),
				),
				IfthenpayPayload::locale_to_lang( determine_locale() ),
				$position,
				$expiry_days
			)
		);

		if ( '' === $redirect_url ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- I log failed API calls on purpose, for admin troubleshooting.
			error_log( 'ifthenpay Pay Button: create_payment_link failed - ' . $this->client->get_last_error() );

			return '';
		}

		$this->transactions->record_payment_link( $ref, $redirect_url, $gateway_key );

		return $redirect_url;
	}

	/**
	 * Signs a return URL's ref + outcome. The payer comes back through a plain GET from ifthenpay, so there's
	 * no nonce; this proves I issued the URL, and that nobody flipped a success URL into a cancel one.
	 *
	 * @param string $ref    The transaction reference.
	 * @param string $status One of 'success', 'error', 'cancel'.
	 */
	public static function return_signature( string $ref, string $status ): string {
		return wp_hash( 'iftp_btn_return|' . $ref . '|' . $status );
	}

	/**
	 * A return URL for one outcome. Only success gets the `[TRANSACTIONID]` placeholder, since error/cancel have nothing to look up.
	 *
	 * @param string $status One of 'success', 'error', 'cancel'.
	 * @param string $ref    The transaction reference.
	 */
	private static function return_url( string $status, string $ref ): string {
		$args = array(
			self::RETURN_VAR => $status,
			self::RETURN_REF => $ref,
			self::RETURN_SIG => self::return_signature( $ref, $status ),
		);

		if ( 'success' === $status ) {
			$args[ self::RETURN_TXN ] = '[TRANSACTIONID]';
		}

		return add_query_arg( $args, home_url( '/' ) );
	}
}
