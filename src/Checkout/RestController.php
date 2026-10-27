<?php
/**
 * Public checkout REST routes.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Api\IfthenpayClient;
use Ifthenpay\PayButton\Api\ReturnHandler;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Reports\CheckoutOpens;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\TransactionStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public routes, since a payer has no account: count a checkout open, start a payment, and read the outcome of one.
 * Starting guards itself (honeypot, rate limit); reading needs the signature ReturnHandler put on the return URL.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class RestController {

	public const NAMESPACE = 'iftp-btn/v1';

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
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout_settings;

	/**
	 * Sets up the routes.
	 *
	 * @param ButtonRepository|null $buttons           Buttons storage.
	 * @param TransactionStore|null $transactions      Transactions storage.
	 * @param CheckoutSettings|null $checkout_settings Checkout settings.
	 */
	public function __construct( ?ButtonRepository $buttons = null, ?TransactionStore $transactions = null, ?CheckoutSettings $checkout_settings = null ) {
		$this->buttons           = $buttons ?? new ButtonRepository();
		$this->transactions      = $transactions ?? new TransactionStore();
		$this->checkout_settings = $checkout_settings ?? new CheckoutSettings();
	}

	/**
	 * Hooks the route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/opens',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'count_open' ),
				'permission_callback' => array( $this, 'can_start' ),
				'args'                => array(
					'button' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start' ),
				'permission_callback' => array( $this, 'can_start' ),
				'args'                => array(
					'button' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
					'source' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payments/(?P<ref>[A-Za-z0-9_]{1,191})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_read_status' ),
				'args'                => array(
					'sig'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'check' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Payers have no account, so I let a payment start only on a button that exists.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public function can_start( WP_REST_Request $request ) {
		if ( null === $this->buttons->find( (int) $request->get_param( 'button' ) ) ) {
			return new WP_Error( 'iftp_btn_unknown', CheckoutTexts::get( 'unavailable' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * Only the browser holding the signed return URL may read a payment.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return true|WP_Error
	 */
	public function can_read_status( WP_REST_Request $request ) {
		$ref       = (string) $request->get_param( 'ref' );
		$signature = (string) $request->get_param( 'sig' );

		if ( ! hash_equals( ReturnHandler::result_signature( $ref ), $signature ) ) {
			return new WP_Error( 'iftp_btn_unknown_payment', __( 'No payment found.', 'ifthenpay-pay-button' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * POST /opens: a payer opened a button's checkout, for the Buttons screen's abandonment rate.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function count_open( WP_REST_Request $request ): WP_REST_Response {
		$button = $this->buttons->find( (int) $request->get_param( 'button' ) );

		if ( null === $button || ! $button->active ) {
			return new WP_REST_Response( null, 204 );
		}

		// The route is public, so one address counts once per button every half hour; a script can't inflate the stats.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'iftp_btn_open_' . md5( wp_salt() . $ip . '|' . $button->id );

		if ( false === get_transient( $key ) ) {
			set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );
			CheckoutOpens::record( $button->id );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * POST /checkout: starts a payment and answers with the ifthenpay page to go to.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		$button = $this->buttons->find( (int) $request->get_param( 'button' ) );

		if ( null === $button ) {
			return new WP_Error( 'iftp_btn_unknown', CheckoutTexts::get( 'unavailable' ), array( 'status' => 404 ) );
		}

		$body   = $request->get_json_params();
		$input  = is_array( $body ) ? array_filter( $body, 'is_scalar' ) : array();
		$source = (string) $request->get_param( 'source' );
		$source = '' !== $source ? wp_validate_redirect( $source, '' ) : '';
		$result = ( new CheckoutService() )->start( $button, $input, $source );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'redirect' => $result ), 201 );
	}

	/**
	 * GET /payments/{ref}: where a payment stands, for the outcome popup.
	 *
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function status( WP_REST_Request $request ) {
		$record = $this->transactions->get( (string) $request->get_param( 'ref' ) );

		if ( null === $record ) {
			return new WP_Error( 'iftp_btn_unknown_payment', __( 'No payment found.', 'ifthenpay-pay-button' ), array( 'status' => 404 ) );
		}

		$status = $record['status'];

		// Report-only: the transaction id came from the browser, so only the webhook ever stores "paid".
		if ( TransactionStore::STATUS_PENDING === $status && $request->get_param( 'check' ) && '' !== $record['transaction_id'] ) {
			$live   = ( new IfthenpayClient() )->get_transaction_status( $record['transaction_id'] );
			$status = 'paid' === $live ? TransactionStore::STATUS_PAID : $status;
		}

		// The popup's words, with this payment's merge tags filled in. Expired reads as failed, like the popup does.
		$outcome      = in_array( $status, CheckoutSettings::OUTCOMES, true ) ? $status : 'failed';
		$confirmation = $this->checkout_settings->get_confirmation();
		$button       = $record['button_id'] > 0 ? $this->buttons->find( $record['button_id'] ) : null;
		$values       = MergeTags::values( $record, $button );

		$response = rest_ensure_response(
			array(
				'status'    => $status,
				'amount'    => CheckoutService::format_money( $record['amount'] ),
				'reference' => $record['ref'],
				'method'    => $record['pay_method'],
				'redirect'  => TransactionStore::STATUS_PAID === $status ? $this->paid_redirect( $record ) : '',
				'title'     => MergeTags::replace( $confirmation['titles'][ $outcome ], $values, false ),
				'message'   => wp_kses_post( MergeTags::replace( $confirmation['messages'][ $outcome ], $values, true ) ),
				// A button with its own look styles its popup too.
				'look'      => CheckoutLook::popup_look( $button ),
			)
		);

		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Where a paid payment sends the payer: its button's own choice, else the site's; '' for the popup.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function paid_redirect( array $record ): string {
		$button = $record['button_id'] > 0 ? $this->buttons->find( $record['button_id'] ) : null;
		$url    = null !== $button && $button->overrides_paid() ? $button->paid_redirect_url() : $this->checkout_settings->get_paid_redirect();

		return '' !== $url ? esc_url_raw( $url ) : '';
	}
}
