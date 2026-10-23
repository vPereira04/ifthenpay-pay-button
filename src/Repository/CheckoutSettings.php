<?php
/**
 * Checkout settings storage: confirmation, look, texts, pay links, receipts, link signing.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

use Ifthenpay\PayButton\Checkout\CheckoutLook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What happens around a payment on the site: what the payer sees after it, where pay links live, who gets emailed.
 *
 * @phpstan-type Confirmation array{paid_type: string, paid_page: int, paid_url: string, show_details: bool, messages: array{paid: string, pending: string, failed: string, cancelled: string}, titles: array{paid: string, pending: string, failed: string, cancelled: string}}
 * @phpstan-import-type Look from CheckoutLook
 */
class CheckoutSettings {

	public const OUTCOMES   = array( 'paid', 'pending', 'failed', 'cancelled' );
	public const PAID_TYPES = array( 'popup', 'page', 'url' );

	public const DEFAULT_PAY_BASE = 'pay';

	private const OPTION_CONFIRMATION   = 'iftp_btn_confirmation';
	private const OPTION_PAY_BASE       = 'iftp_btn_pay_base';
	private const OPTION_RECEIPT_PAYER  = 'iftp_btn_receipt_payer';
	private const OPTION_NOTIFY_ADMIN   = 'iftp_btn_notify_admin';
	private const OPTION_NOTIFY_EMAIL   = 'iftp_btn_notify_email';
	private const OPTION_SIGNING_SECRET = 'iftp_btn_signing_secret';
	private const OPTION_LOOK           = 'iftp_btn_checkout_look';
	private const OPTION_TEXTS          = 'iftp_btn_checkout_texts';

	/**
	 * The default popup message per outcome.
	 *
	 * @return array{paid: string, pending: string, failed: string, cancelled: string}
	 */
	public static function default_messages(): array {
		return array(
			'paid'      => '<strong>' . __( 'Thank you!', 'ifthenpay-pay-button' ) . '</strong> ' . __( 'Your payment was received.', 'ifthenpay-pay-button' ),
			'pending'   => '<strong>' . __( 'Almost there.', 'ifthenpay-pay-button' ) . '</strong> ' . __( 'We are waiting for ifthenpay to confirm your payment. With Multibanco or Payshop this happens once you pay the reference.', 'ifthenpay-pay-button' ),
			'failed'    => '<strong>' . __( 'The payment did not go through.', 'ifthenpay-pay-button' ) . '</strong> ' . __( 'No money was taken. You can try again.', 'ifthenpay-pay-button' ),
			'cancelled' => '<strong>' . __( 'Payment cancelled.', 'ifthenpay-pay-button' ) . '</strong> ' . __( 'Nothing was charged.', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * The default popup title per outcome.
	 *
	 * @return array{paid: string, pending: string, failed: string, cancelled: string}
	 */
	public static function default_titles(): array {
		return array(
			'paid'      => __( 'Payment received', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Payment pending', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Payment failed', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'Payment cancelled', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * The confirmation settings, every key filled in.
	 *
	 * @return Confirmation
	 */
	public function get_confirmation(): array {
		$stored   = get_option( self::OPTION_CONFIRMATION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$messages = is_array( $stored['messages'] ?? null ) ? $stored['messages'] : array();
		$defaults = self::default_messages();
		$type     = is_string( $stored['paid_type'] ?? null ) && in_array( $stored['paid_type'], self::PAID_TYPES, true ) ? $stored['paid_type'] : 'popup';

		$titles   = is_array( $stored['titles'] ?? null ) ? $stored['titles'] : array();
		$headings = self::default_titles();

		$filled = array();
		$named  = array();

		foreach ( self::OUTCOMES as $outcome ) {
			$filled[ $outcome ] = is_string( $messages[ $outcome ] ?? null ) && '' !== trim( $messages[ $outcome ] ) ? $messages[ $outcome ] : $defaults[ $outcome ];
			$named[ $outcome ]  = is_string( $titles[ $outcome ] ?? null ) && '' !== trim( $titles[ $outcome ] ) ? $titles[ $outcome ] : $headings[ $outcome ];
		}

		/**
		 * The loop above fills every outcome.
		 *
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $filled
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $named
		 */
		return array(
			'paid_type'    => $type,
			'paid_page'    => is_numeric( $stored['paid_page'] ?? null ) ? (int) $stored['paid_page'] : 0,
			'paid_url'     => is_string( $stored['paid_url'] ?? null ) ? $stored['paid_url'] : '',
			'show_details' => ! isset( $stored['show_details'] ) || ! empty( $stored['show_details'] ),
			'messages'     => $filled,
			'titles'       => $named,
		);
	}

	/**
	 * Stores the confirmation settings. The caller sanitizes.
	 *
	 * @param array<string, mixed> $confirmation The settings to store.
	 * @phpstan-param Confirmation $confirmation
	 */
	public function set_confirmation( array $confirmation ): void {
		update_option( self::OPTION_CONFIRMATION, $confirmation, false );
	}

	/**
	 * How the checkout and the popups look, every key filled in.
	 *
	 * @return Look
	 */
	public function get_look(): array {
		return CheckoutLook::sanitize( get_option( self::OPTION_LOOK, array() ) );
	}

	/**
	 * Stores the look.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public function set_look( array $look ): void {
		// Autoloaded: every page with a button prints it.
		update_option( self::OPTION_LOOK, $look, true );
	}

	/**
	 * The merchant's own checkout texts, only the ones they changed.
	 *
	 * @return array<string, string>
	 */
	public function get_texts(): array {
		$texts = get_option( self::OPTION_TEXTS, array() );

		return is_array( $texts ) ? array_filter( $texts, 'is_string' ) : array();
	}

	/**
	 * Stores the checkout texts.
	 *
	 * @param array<string, string> $texts From CheckoutTexts::sanitize().
	 */
	public function set_texts( array $texts ): void {
		// Autoloaded: every page with a button prints them.
		update_option( self::OPTION_TEXTS, $texts, true );
	}

	/**
	 * Where the Paid outcome sends the payer, or '' when it's a popup (or its page is gone).
	 */
	public function get_paid_redirect(): string {
		$confirmation = $this->get_confirmation();

		if ( 'page' === $confirmation['paid_type'] && $confirmation['paid_page'] > 0 ) {
			$url = get_permalink( $confirmation['paid_page'] );

			return is_string( $url ) ? $url : '';
		}

		return 'url' === $confirmation['paid_type'] ? $confirmation['paid_url'] : '';
	}

	/**
	 * The first path segment of every pay link, e.g. `pay` in /pay/consultation/.
	 */
	public function get_pay_base(): string {
		$base = sanitize_title( (string) get_option( self::OPTION_PAY_BASE, self::DEFAULT_PAY_BASE ) );

		return '' !== $base ? $base : self::DEFAULT_PAY_BASE;
	}

	/**
	 * Stores the pay link base.
	 *
	 * @param string $base The new base, already a slug.
	 */
	public function set_pay_base( string $base ): void {
		// Autoloaded: the rewrite rule reads it on every request.
		update_option( self::OPTION_PAY_BASE, $base, true );
	}

	/**
	 * Whether the payer gets a receipt email once paid.
	 */
	public function get_receipt_payer(): bool {
		return '0' !== (string) get_option( self::OPTION_RECEIPT_PAYER, '1' );
	}

	/**
	 * Stores the payer receipt switch.
	 *
	 * @param bool $enabled Whether to send it.
	 */
	public function set_receipt_payer( bool $enabled ): void {
		update_option( self::OPTION_RECEIPT_PAYER, $enabled ? '1' : '0', false );
	}

	/**
	 * Whether the site owner gets an email for every paid payment.
	 */
	public function get_notify_admin(): bool {
		return '0' !== (string) get_option( self::OPTION_NOTIFY_ADMIN, '1' );
	}

	/**
	 * Stores the owner notification switch.
	 *
	 * @param bool $enabled Whether to send it.
	 */
	public function set_notify_admin( bool $enabled ): void {
		update_option( self::OPTION_NOTIFY_ADMIN, $enabled ? '1' : '0', false );
	}

	/**
	 * Where owner notifications go, falling back to the site's admin email.
	 */
	public function get_notify_email(): string {
		$email = (string) get_option( self::OPTION_NOTIFY_EMAIL, '' );

		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * The stored notification address, '' when it falls back to the admin email.
	 */
	public function get_notify_email_raw(): string {
		return (string) get_option( self::OPTION_NOTIFY_EMAIL, '' );
	}

	/**
	 * Stores the notification address.
	 *
	 * @param string $email A valid email, or '' for the admin email.
	 */
	public function set_notify_email( string $email ): void {
		update_option( self::OPTION_NOTIFY_EMAIL, $email, false );
	}

	/**
	 * The site secret signed links are signed with. Made on first use, and never leaves the server.
	 */
	public function get_signing_secret(): string {
		$secret = (string) get_option( self::OPTION_SIGNING_SECRET, '' );

		if ( strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, false, false );
			update_option( self::OPTION_SIGNING_SECRET, $secret, false );
		}

		return $secret;
	}

	/**
	 * Replaces the signing secret, so every signed link made so far stops working.
	 */
	public function rotate_signing_secret(): void {
		update_option( self::OPTION_SIGNING_SECRET, wp_generate_password( 64, false, false ), false );
	}

	/**
	 * Removes every checkout option. Used on uninstall.
	 */
	public function delete_all(): void {
		foreach ( array( self::OPTION_CONFIRMATION, self::OPTION_PAY_BASE, self::OPTION_RECEIPT_PAYER, self::OPTION_NOTIFY_ADMIN, self::OPTION_NOTIFY_EMAIL, self::OPTION_SIGNING_SECRET, self::OPTION_LOOK, self::OPTION_TEXTS ) as $option ) {
			delete_option( $option );
		}
	}
}
