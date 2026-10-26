<?php
/**
 * Plugin settings repository.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide ifthenpay settings.
 *
 * @phpstan-type Method array{entity: string, alias: string, logo?: string, logo_dark?: string, enabled: bool, account: string, position: int, visible?: bool}
 */
class SettingsRepository {

	// The hosted payment page description until it's set; merge tags fill it per payment.
	public const DEFAULT_DESCRIPTION = '{button}';

	private const OPTION_BACKOFFICE_KEY = 'iftp_btn_backoffice_key';
	private const OPTION_GATEWAY_KEY    = 'iftp_btn_gateway_key';
	private const OPTION_GATEWAY_KEYS   = 'iftp_btn_gateway_keys';
	private const OPTION_APK_KEYS       = 'iftp_btn_anti_phishing_keys';
	private const OPTION_CALLBACK_HASH  = 'iftp_btn_callback_hash';
	private const OPTION_METHODS        = 'iftp_btn_methods';
	private const OPTION_DEFAULT_METHOD = 'iftp_btn_default_method';
	private const OPTION_DESCRIPTION    = 'iftp_btn_description';
	private const OPTION_EXPIRY_DAYS    = 'iftp_btn_expiry_days';

	private const OPTION_SUMMARY_ON = 'iftp_btn_summary_enabled';
	private const OPTION_SUMMARY_TO = 'iftp_btn_summary_email';

	private const OPTION_PAYMENT_EMAIL_SUBJECT = 'iftp_btn_payment_email_subject';
	private const OPTION_PAYMENT_EMAIL_BODY    = 'iftp_btn_payment_email_body';

	/**
	 * The stored Backoffice Key, or '' when not connected.
	 */
	public function get_backoffice_key(): string {
		return (string) get_option( self::OPTION_BACKOFFICE_KEY, '' );
	}

	/**
	 * Stores the Backoffice Key.
	 *
	 * @param string $key The Backoffice Key.
	 */
	public function set_backoffice_key( string $key ): void {
		update_option( self::OPTION_BACKOFFICE_KEY, $key, false );
	}

	/**
	 * Whether a Backoffice Key is stored.
	 */
	public function is_connected(): bool {
		return '' !== $this->get_backoffice_key();
	}

	/**
	 * The currently selected Gateway Key.
	 */
	public function get_gateway_key(): string {
		return (string) get_option( self::OPTION_GATEWAY_KEY, '' );
	}

	/**
	 * Stores the currently selected Gateway Key.
	 *
	 * @param string $gateway_key The Gateway Key.
	 */
	public function set_gateway_key( string $gateway_key ): void {
		update_option( self::OPTION_GATEWAY_KEY, $gateway_key, false );
	}

	/**
	 * Every Gateway Key row available under the connected Backoffice Key.
	 *
	 * @return array<int, string>
	 */
	public function get_gateway_keys(): array {
		$keys = get_option( self::OPTION_GATEWAY_KEYS, array() );

		return is_array( $keys ) ? array_values( array_map( 'strval', $keys ) ) : array();
	}

	/**
	 * Stores the full list of available Gateway Keys.
	 *
	 * @param array<int, string> $gateway_keys The Gateway Keys to store.
	 */
	public function set_gateway_keys( array $gateway_keys ): void {
		update_option( self::OPTION_GATEWAY_KEYS, array_values( array_unique( $gateway_keys ) ), false );
	}

	/**
	 * The anti-phishing key registered with ifthenpay for a Gateway Key, or '' when none yet.
	 *
	 * @param string $gateway_key The Gateway Key.
	 */
	public function get_anti_phishing_key( string $gateway_key ): string {
		$keys = get_option( self::OPTION_APK_KEYS, array() );

		return is_array( $keys ) ? (string) ( $keys[ $gateway_key ] ?? '' ) : '';
	}

	/**
	 * Stores the anti-phishing key registered for a Gateway Key.
	 *
	 * @param string $gateway_key       The Gateway Key.
	 * @param string $anti_phishing_key The random key sent as `apKey` on callback activation.
	 */
	public function set_anti_phishing_key( string $gateway_key, string $anti_phishing_key ): void {
		$keys = get_option( self::OPTION_APK_KEYS, array() );
		$keys = is_array( $keys ) ? $keys : array();

		$keys[ $gateway_key ] = $anti_phishing_key;

		update_option( self::OPTION_APK_KEYS, $keys, false );
	}

	/**
	 * Deletes every stored anti-phishing key (uninstall only).
	 */
	public function delete_anti_phishing_keys(): void {
		delete_option( self::OPTION_APK_KEYS );
	}

	/**
	 * Hash of the Gateway Key + callback URL ifthenpay last accepted, or '' when none yet.
	 */
	public function get_callback_hash(): string {
		return (string) get_option( self::OPTION_CALLBACK_HASH, '' );
	}

	/**
	 * Stores the hash of the callback ifthenpay just accepted.
	 *
	 * @param string $hash Hash of the Gateway Key + callback URL.
	 */
	public function set_callback_hash( string $hash ): void {
		update_option( self::OPTION_CALLBACK_HASH, $hash, false );
	}

	/**
	 * The full payment methods catalog for the current Gateway Key.
	 *
	 * @return array<int, Method>
	 */
	public function get_methods(): array {
		$methods = get_option( self::OPTION_METHODS, array() );

		if ( ! is_array( $methods ) ) {
			return array();
		}

		$defaults = array(
			'entity'   => '',
			'alias'    => '',
			'enabled'  => false,
			'account'  => '',
			'position' => 0,
		);

		/**
		 * A row saved by an older build can miss a key, so I fill them in and callers can trust the shape.
		 *
		 * @var array<int, Method> $methods
		 */
		$methods = array_values( array_map( static fn ( $method ) => (array) $method + $defaults, $methods ) );

		return $methods;
	}

	/**
	 * Stores the payment methods catalog.
	 *
	 * @param array<int, Method> $methods The methods to store.
	 */
	public function set_methods( array $methods ): void {
		update_option( self::OPTION_METHODS, $methods, false );
	}

	/**
	 * The subset of get_methods() that are enabled and provisioned.
	 *
	 * @return array<int, array{entity: string, account: string}>
	 */
	public function get_enabled_methods(): array {
		$enabled = array();

		foreach ( $this->get_methods() as $method ) {
			if ( ! empty( $method['enabled'] ) && '' !== $method['account'] ) {
				$enabled[] = array(
					'entity'  => $method['entity'],
					'account' => $method['account'],
				);
			}
		}

		return $enabled;
	}

	/**
	 * The configured default method entity, or '' when none is set.
	 */
	public function get_default_method(): string {
		return (string) get_option( self::OPTION_DEFAULT_METHOD, '' );
	}

	/**
	 * The default method's catalog Position (what the payload's `selected_method` expects), or ''.
	 */
	public function get_default_method_position(): string {
		$default = $this->get_default_method();

		if ( '' === $default ) {
			return '';
		}

		foreach ( $this->get_methods() as $method ) {
			if ( $method['entity'] === $default && ! empty( $method['enabled'] ) ) {
				return (string) $method['position'];
			}
		}

		return '';
	}

	/**
	 * Stores the default method entity.
	 *
	 * @param string $entity The method entity, or '' to clear.
	 */
	public function set_default_method( string $entity ): void {
		update_option( self::OPTION_DEFAULT_METHOD, $entity );
	}

	/**
	 * The hosted payment page description, with merge tags. Until it's set, the button's title; a payment
	 * without a button then falls back to the site name.
	 */
	public function get_description(): string {
		return (string) get_option( self::OPTION_DESCRIPTION, self::DEFAULT_DESCRIPTION );
	}

	/**
	 * Stores the payer-facing description.
	 *
	 * @param string $description The description text.
	 */
	public function set_description( string $description ): void {
		update_option( self::OPTION_DESCRIPTION, $description );
	}

	/**
	 * The number of days an unpaid payment link stays valid.
	 */
	public function get_expiry_days(): int {
		return max( 1, (int) get_option( self::OPTION_EXPIRY_DAYS, 3 ) );
	}

	/**
	 * Stores the expiry days, floored at 1.
	 *
	 * @param int $days The number of days.
	 */
	public function set_expiry_days( int $days ): void {
		update_option( self::OPTION_EXPIRY_DAYS, max( 1, $days ) );
	}

	/**
	 * Whether the Monday summary email goes out.
	 */
	public function get_summary_enabled(): bool {
		return (bool) get_option( self::OPTION_SUMMARY_ON, false );
	}

	/**
	 * Turns the Monday summary email on or off.
	 *
	 * @param bool $enabled Whether it goes out.
	 */
	public function set_summary_enabled( bool $enabled ): void {
		update_option( self::OPTION_SUMMARY_ON, $enabled, false );
	}

	/**
	 * Where the summary goes: the stored address, else the site's admin email.
	 */
	public function get_summary_email(): string {
		$email = (string) get_option( self::OPTION_SUMMARY_TO, '' );

		return false !== is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Stores the summary's recipient; '' goes back to the admin email.
	 *
	 * @param string $email A valid email, or ''.
	 */
	public function set_summary_email( string $email ): void {
		update_option( self::OPTION_SUMMARY_TO, false !== is_email( $email ) ? $email : '', false );
	}

	/**
	 * Deletes the weekly summary settings, on uninstall. A disconnect keeps them.
	 */
	public function delete_summary_settings(): void {
		delete_option( self::OPTION_SUMMARY_ON );
		delete_option( self::OPTION_SUMMARY_TO );
	}

	/**
	 * The custom payment email subject, or '' for the translated default.
	 */
	public function get_payment_email_subject(): string {
		return (string) get_option( self::OPTION_PAYMENT_EMAIL_SUBJECT, '' );
	}

	/**
	 * The custom payment email body, or '' for the translated default.
	 */
	public function get_payment_email_body(): string {
		return (string) get_option( self::OPTION_PAYMENT_EMAIL_BODY, '' );
	}

	/**
	 * Stores the payment email template; '' for either part goes back to its default.
	 *
	 * @param string $subject The sanitized subject.
	 * @param string $body    The sanitized body.
	 */
	public function set_payment_email( string $subject, string $body ): void {
		$this->store_or_delete( self::OPTION_PAYMENT_EMAIL_SUBJECT, $subject );
		$this->store_or_delete( self::OPTION_PAYMENT_EMAIL_BODY, $body );
	}

	/**
	 * Deletes the payment email template, on uninstall. A disconnect keeps it, since it's the admin's own copy.
	 */
	public function delete_payment_email(): void {
		delete_option( self::OPTION_PAYMENT_EMAIL_SUBJECT );
		delete_option( self::OPTION_PAYMENT_EMAIL_BODY );
	}

	/**
	 * Deletes every option I own, on disconnect and uninstall.
	 * I keep the anti-phishing keys: payments started before a disconnect still need them to validate their callback.
	 */
	public function delete_all(): void {
		delete_option( self::OPTION_BACKOFFICE_KEY );
		delete_option( self::OPTION_GATEWAY_KEY );
		delete_option( self::OPTION_GATEWAY_KEYS );
		delete_option( self::OPTION_CALLBACK_HASH );
		delete_option( self::OPTION_METHODS );
		delete_option( self::OPTION_DEFAULT_METHOD );
		delete_option( self::OPTION_DESCRIPTION );
		delete_option( self::OPTION_EXPIRY_DAYS );
	}

	/**
	 * Stores a non-autoloaded option, or deletes it when the value is ''.
	 *
	 * @param string $option The option name.
	 * @param string $value  The value to store.
	 */
	private function store_or_delete( string $option, string $value ): void {
		if ( '' === $value ) {
			delete_option( $option );

			return;
		}

		update_option( $option, $value, false );
	}
}
