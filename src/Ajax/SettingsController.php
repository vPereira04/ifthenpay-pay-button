<?php
/**
 * Backoffice connection and Gateway methods AJAX router.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ajax;

use Ifthenpay\PayButton\Admin\MethodsTable;
use Ifthenpay\PayButton\Api\IfthenpayClient;
use Ifthenpay\PayButton\Api\Webhook\WebhookRegistrar;
use Ifthenpay\PayButton\Mail\IfthenpayEmailHelper;
use Ifthenpay\PayButton\Mail\WeeklySummaryEmail;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Sync\GatewaySync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX for the Configuration screen's Backoffice tab and the button editor's Gateway tab.
 */
class SettingsController {

	public const NONCE_ACTION = 'iftp_btn_settings';

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Gateway catalog sync.
	 *
	 * @var GatewaySync
	 */
	private GatewaySync $sync;

	/**
	 * Methods table renderer.
	 *
	 * @var MethodsTable
	 */
	private MethodsTable $methods_table;

	/**
	 * Webhook callback registration.
	 *
	 * @var WebhookRegistrar
	 */
	private WebhookRegistrar $webhooks;

	/**
	 * Sets up the router.
	 *
	 * @param SettingsRepository|null $settings Settings storage.
	 * @param IfthenpayClient|null    $client   Ifthenpay API client.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?IfthenpayClient $client = null ) {
		$this->settings      = $settings ?? new SettingsRepository();
		$client              = $client ?? new IfthenpayClient();
		$this->sync          = new GatewaySync( $this->settings, $client );
		$this->methods_table = new MethodsTable( $this->settings );
		$this->webhooks      = new WebhookRegistrar( $this->settings, $client );
	}

	/**
	 * Registers every AJAX action this router handles.
	 */
	public function register(): void {
		add_action( 'wp_ajax_iftp_btn_connect', array( $this, 'connect' ) );
		add_action( 'wp_ajax_iftp_btn_disconnect', array( $this, 'disconnect' ) );
		add_action( 'wp_ajax_iftp_btn_gateway_methods', array( $this, 'gateway_methods' ) );
		add_action( 'wp_ajax_iftp_btn_request_activation', array( $this, 'request_activation' ) );
		add_action( 'wp_ajax_iftp_btn_send_summary_test', array( $this, 'send_summary_test' ) );
	}

	/**
	 * The 24h cooldown transient for one method's activation request. Per method, never per table.
	 *
	 * @param string $gateway_key The Gateway Key.
	 * @param string $entity      The method entity code.
	 */
	public static function activation_cooldown_key( string $gateway_key, string $entity ): string {
		return 'iftp_btn_activation_' . md5( $gateway_key . '|' . strtoupper( $entity ) );
	}

	/**
	 * Connects a Backoffice Key: checks its format, then that it has a gateway of our type.
	 */
	public function connect(): void {
		$this->guard();

		$backoffice_key = $this->post_text( 'backoffice_key' );

		if ( 1 !== preg_match( '/^\d{4}-\d{4}-\d{4}-\d{4}$/', $backoffice_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Backoffice Key...', 'ifthenpay-pay-button' ) ), 400 );
		}

		if ( ! $this->sync->connect( $backoffice_key ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'This Backoffice Key has no Pay Button gateway yet. Ask ifthenpay to add one to your contract.', 'ifthenpay-pay-button' ),
				)
			);
		}

		$this->webhooks->register_all();

		wp_send_json_success();
	}

	/**
	 * Disconnects the Backoffice Key, wiping every stored setting but the anti-phishing keys.
	 */
	public function disconnect(): void {
		$this->guard();

		$this->settings->delete_all();
		delete_transient( IfthenpayClient::GATEWAY_ROWS_TRANSIENT );

		wp_send_json_success();
	}

	/**
	 * The methods table of one Gateway Key, for the button editor to swap in when its Gateway Key changes.
	 */
	public function gateway_methods(): void {
		$this->guard_connected();

		$gateway_key = $this->post_text( 'gateway_key' );

		if ( ! in_array( $gateway_key, $this->settings->get_gateway_keys(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown Gateway Key.', 'ifthenpay-pay-button' ) ), 400 );
		}

		wp_send_json_success(
			array(
				'tableHtml' => $this->methods_table->render( $gateway_key, array(), $this->post_text( 'default_method' ), 'config[methods][]', 'config[default_method]' ),
			)
		);
	}

	/**
	 * Emails ifthenpay support to activate a not-yet-provisioned method. The Backoffice Key is read here, never from the browser.
	 */
	public function request_activation(): void {
		$this->guard_connected();

		$entity      = strtoupper( $this->post_text( 'entity' ) );
		$gateway_key = $this->settings->resolve_gateway_key( $this->post_text( 'gateway_key' ) );

		if ( '' === $entity || '' === $gateway_key || ! in_array( $entity, array_column( $this->settings->get_methods( $gateway_key ), 'entity' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing method or Gateway Key.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$cooldown_key = self::activation_cooldown_key( $gateway_key, $entity );

		if ( false !== get_transient( $cooldown_key ) ) {
			wp_send_json_error( array( 'message' => __( 'An activation request for this method was already sent in the last 24 hours.', 'ifthenpay-pay-button' ) ), 429 );
		}

		$sent = IfthenpayEmailHelper::send_activation_email(
			array(
				'gateway_key'    => $gateway_key,
				'entity'         => $entity,
				'backoffice_key' => $this->settings->get_backoffice_key(),
				'customer_email' => wp_get_current_user()->user_email,
				'site_url'       => home_url( '/' ),
				'site_name'      => (string) get_bloginfo( 'name' ),
				'wp_version'     => (string) get_bloginfo( 'version' ),
				'plugin_version' => IFTP_BTN_VERSION,
			)
		);

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Could not send the activation request. Please try again.', 'ifthenpay-pay-button' ) ), 500 );
		}

		set_transient( $cooldown_key, 1, DAY_IN_SECONDS );

		wp_send_json_success();
	}

	/**
	 * Sends the weekly summary right now, to the address in the field (saved or not), so the admin sees what it looks like.
	 */
	public function send_summary_test(): void {
		$this->guard_connected();

		$to = $this->post_text( 'summary_email' );
		$to = false !== is_email( $to ) ? $to : $this->settings->get_summary_email();

		if ( ! ( new WeeklySummaryEmail() )->send( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not send the summary. Check that this site can send mail.', 'ifthenpay-pay-button' ) ), 500 );
		}

		/* translators: %s: email address */
		wp_send_json_success( array( 'message' => sprintf( __( 'Summary sent to %s.', 'ifthenpay-pay-button' ), $to ) ) );
	}

	/**
	 * A single-line POST value.
	 *
	 * @param string $key      The POST key.
	 * @param string $fallback Returned when the key is missing or not a string.
	 */
	private function post_text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every handler runs guard() first.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $fallback;
	}

	/**
	 * Verifies the nonce and the admin capability.
	 */
	private function guard(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ifthenpay-pay-button' ) ), 403 );
		}
	}

	/**
	 * Same as guard(), plus a connected Backoffice Key.
	 */
	private function guard_connected(): void {
		$this->guard();

		if ( ! $this->settings->is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'Connect your ifthenpay account first.', 'ifthenpay-pay-button' ) ), 409 );
		}
	}
}
