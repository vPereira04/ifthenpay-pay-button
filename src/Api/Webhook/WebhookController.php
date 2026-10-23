<?php
/**
 * Inbound webhook handler.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Api\Webhook;

use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only path that marks a payment paid.
 */
class WebhookController {

	/**
	 * Ifthenpay calls back into `/{SLUG}/{ref}/?apk=...&val=...&mtd=...&req=...`.
	 */
	public const SLUG = 'iftp_btn_callback';

	/**
	 * Flags a request as hitting this endpoint.
	 */
	public const QUERY_VAR = 'iftp_btn_ep';

	public const REF_VAR = 'iftp_btn_ref';

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Anti-phishing + amount validator.
	 *
	 * @var WebhookValidator
	 */
	private WebhookValidator $validator;

	/**
	 * Sets up the controller.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 * @param WebhookValidator|null $validator    Anti-phishing + amount validator.
	 */
	public function __construct( ?TransactionStore $transactions = null, ?WebhookValidator $validator = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
		$this->validator    = $validator ?? new WebhookValidator();
	}

	/**
	 * Wires up the rewrite endpoint ifthenpay calls back into.
	 */
	public function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( self::class, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
	}

	/**
	 * `/{SLUG}/{ref}/` → `index.php?{QUERY_VAR}=1&{REF_VAR}={ref}`.
	 */
	public static function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^' . self::SLUG . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=1&' . self::REF_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Registers the endpoint's query vars as public.
	 *
	 * @param string[] $vars WordPress's registered public query vars.
	 * @return string[]
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::REF_VAR;

		return $vars;
	}

	/**
	 * Registers and flushes the rewrite rule. Run on activation, or the endpoint 404s until something else flushes the rules.
	 */
	public static function flush(): void {
		self::add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * The callback URL template registered with ifthenpay. No status placeholder: ifthenpay only calls the webhook for paid payments.
	 */
	public static function url(): string {
		$params = 'apk=[ANTI_PHISHING_KEY]&val=[AMOUNT]&mtd=[PAYMENT_METHOD]&req=[REQUEST_ID]';

		// With plain permalinks the rewrite rule never matches, but the query vars are public, so the query-string form works there.
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return home_url( '/' ) . '?' . self::QUERY_VAR . '=1&' . self::REF_VAR . '=[ORDER_ID]&' . $params;
		}

		return home_url( '/' . self::SLUG . '/' ) . '[ORDER_ID]?' . $params;
	}

	/**
	 * Validates and processes an inbound webhook call. The anti-phishing key is checked before anything changes.
	 */
	public function handle(): void {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}

		$ref = sanitize_text_field( wp_unslash( (string) get_query_var( self::REF_VAR ) ) );

		if ( '' === $ref ) {
			self::respond( 400, 'Missing reference' );
		}

		$record = $this->transactions->get( $ref );

		if ( null === $record ) {
			self::respond( 404, 'Unknown reference' );
		}

		$payload = WebhookPayload::from_request( $ref );

		if ( ! $this->validator->is_valid( $payload, $record ) ) {
			self::respond( 403, 'Invalid anti-phishing key or amount' );
		}

		// false = already paid, i.e. a repeat call; still a 200 so ifthenpay stops retrying, and nothing fires twice.
		if ( $this->transactions->mark_paid( $payload->ref(), $payload->method(), $payload->request_id() ) ) {
			/**
			 * Fires once, when ifthenpay confirms a payment. Receipts and the button's outgoing webhook hang off this.
			 *
			 * @param string $ref The transaction reference.
			 */
			do_action( 'iftp_btn_payment_paid', $payload->ref() );
		}

		self::respond( 200, 'OK' );
	}

	/**
	 * Sends a plain-text response and exits. Not wp_die(): outside admin-ajax it renders a full HTML screen.
	 *
	 * @param int    $status The HTTP status code to send.
	 * @param string $body   The plain-text response body.
	 * @return never
	 */
	private static function respond( int $status, string $body ): void {
		status_header( $status );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html( $body );
		exit;
	}
}
