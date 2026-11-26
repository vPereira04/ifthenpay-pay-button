<?php
/**
 * Checkout settings storage: confirmation, look, button design, texts, pay links, receipts, link signing.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

use Ifthenpay\PayButton\Ai\Vault;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Checkout\CheckoutLook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What happens around a payment on the site: what the payer sees after it, where pay links live, who gets emailed.
 *
 * @phpstan-type Confirmation array{paid_type: string, paid_page: int, paid_url: string, show_details: bool, messages: array{paid: string, pending: string, failed: string, cancelled: string}, titles: array{paid: string, pending: string, failed: string, cancelled: string}}
 * @phpstan-import-type Look from CheckoutLook
 * @phpstan-import-type ButtonDesignConfig from ButtonDesign
 */
class CheckoutSettings {

	public const OUTCOMES   = array( 'paid', 'pending', 'failed', 'cancelled' );
	public const PAID_TYPES = array( 'popup', 'page', 'url' );

	public const DEFAULT_PAY_BASE = 'pay';

	// Hours before a link runs out that a reminder can go.
	public const REMINDER_CHOICES = array( 6, 12, 24, 48 );

	// The receipt's colour until the merchant picks one.
	public const RECEIPT_ACCENT = '#00609c';

	private const OPTION_CONFIRMATION   = 'iftp_btn_confirmation';
	private const OPTION_PAY_BASE       = 'iftp_btn_pay_base';
	private const OPTION_RECEIPT_PAYER  = 'iftp_btn_receipt_payer';
	private const OPTION_RECEIPT_PDF    = 'iftp_btn_receipt_pdf';
	private const OPTION_RECEIPT_LOOK   = 'iftp_btn_receipt_look';
	private const OPTION_DELETE_DATA    = 'iftp_btn_delete_data';
	private const OPTION_REMINDER_ON    = 'iftp_btn_reminder_on';
	private const OPTION_REMINDER_HOURS = 'iftp_btn_reminder_hours';
	private const OPTION_NOTIFY_ADMIN   = 'iftp_btn_notify_admin';
	private const OPTION_NOTIFY_EMAIL   = 'iftp_btn_notify_email';
	private const OPTION_SIGNING_SECRET = 'iftp_btn_signing_secret';
	private const OPTION_LOOK           = 'iftp_btn_checkout_look';
	private const OPTION_TEXTS          = 'iftp_btn_checkout_texts';
	private const OPTION_BUTTON_DESIGN  = 'iftp_btn_button_design';

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
	 * The confirmation a button's payers see: the site's, with any title or message the button sets itself laid over it.
	 *
	 * @param Button|null $button The button, or null for the site's own.
	 * @return Confirmation
	 */
	public function get_confirmation_for( ?Button $button ): array {
		$confirmation = $this->get_confirmation();

		if ( null === $button ) {
			return $confirmation;
		}

		foreach ( self::OUTCOMES as $outcome ) {
			$confirmation['titles'][ $outcome ]   = $button->config['confirmation']['titles'][ $outcome ] ?? $confirmation['titles'][ $outcome ];
			$confirmation['messages'][ $outcome ] = $button->config['confirmation']['messages'][ $outcome ] ?? $confirmation['messages'][ $outcome ];
		}

		return $confirmation;
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
	 * The button design every button set to "Default design" wears, every key filled in.
	 *
	 * @return ButtonDesignConfig
	 */
	public function get_button_design(): array {
		return self::own_design( ButtonDesign::sanitize( get_option( self::OPTION_BUTTON_DESIGN, array() ) ) );
	}

	/**
	 * Stores the default button design.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @phpstan-param ButtonDesignConfig $design
	 */
	public function set_button_design( array $design ): void {
		// Autoloaded: every page with a button reads it.
		update_option( self::OPTION_BUTTON_DESIGN, self::own_design( $design ), true );
	}

	/**
	 * The default can't follow itself, so "site" there means the theme's look.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @phpstan-param ButtonDesignConfig $design
	 * @return ButtonDesignConfig
	 */
	private static function own_design( array $design ): array {
		if ( ! in_array( $design['source'], ButtonDesign::OWN_SOURCES, true ) ) {
			$design['source'] = ButtonDesign::SOURCE_THEME;
		}

		return $design;
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
	 * Whether the payer's receipt carries a PDF copy.
	 */
	public function get_receipt_pdf(): bool {
		return '0' !== (string) get_option( self::OPTION_RECEIPT_PDF, '1' );
	}

	/**
	 * Stores the PDF receipt switch.
	 *
	 * @param bool $enabled Whether to attach it.
	 */
	public function set_receipt_pdf( bool $enabled ): void {
		update_option( self::OPTION_RECEIPT_PDF, $enabled ? '1' : '0', false );
	}

	/**
	 * What the merchant put on their receipts: logo, name, details, title, message and the barcode switch.
	 *
	 * @return array{logo: string, logo_id: int, name: string, details: string, title: string, message: string, barcode: bool, color: bool, accent: string}
	 */
	public function get_receipt_look(): array {
		$saved = get_option( self::OPTION_RECEIPT_LOOK, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$logo  = is_string( $saved['logo'] ?? null ) && in_array( $saved['logo'], array( 'site', 'image' ), true ) ? $saved['logo'] : 'none';

		return array(
			'logo'    => $logo,
			'logo_id' => is_numeric( $saved['logo_id'] ?? null ) ? max( 0, (int) $saved['logo_id'] ) : 0,
			'name'    => is_string( $saved['name'] ?? null ) ? $saved['name'] : '',
			'details' => is_string( $saved['details'] ?? null ) ? $saved['details'] : '',
			'title'   => is_string( $saved['title'] ?? null ) ? $saved['title'] : '',
			'message' => is_string( $saved['message'] ?? null ) ? $saved['message'] : '',
			'barcode' => ! isset( $saved['barcode'] ) || '0' !== (string) $saved['barcode'],
			'color'   => isset( $saved['color'] ) && '1' === (string) $saved['color'],
			'accent'  => is_string( $saved['accent'] ?? null ) && null !== sanitize_hex_color( $saved['accent'] ) ? strtolower( $saved['accent'] ) : self::RECEIPT_ACCENT,
		);
	}

	/**
	 * Stores the receipt's look, cleaning every part: single lines for the name and title, a few short lines for the rest.
	 *
	 * @param array<string, mixed> $input Raw values, already unslashed.
	 */
	public function set_receipt_look( array $input ): void {
		$text  = static fn( string $key, int $max ): string => is_string( $input[ $key ] ?? null ) ? mb_substr( sanitize_text_field( $input[ $key ] ), 0, $max ) : '';
		$lines = static fn( string $key, int $max ): string => is_string( $input[ $key ] ?? null ) ? mb_substr( sanitize_textarea_field( $input[ $key ] ), 0, $max ) : '';
		$logo  = is_string( $input['logo'] ?? null ) && in_array( $input['logo'], array( 'site', 'image' ), true ) ? $input['logo'] : 'none';
		$id    = is_numeric( $input['logo_id'] ?? null ) ? absint( $input['logo_id'] ) : 0;

		// A picked image has to be an image in the library, or there is nothing to print.
		if ( 'image' === $logo && ( $id < 1 || ! wp_attachment_is_image( $id ) ) ) {
			$logo = 'none';
			$id   = 0;
		}

		update_option(
			self::OPTION_RECEIPT_LOOK,
			array(
				'logo'    => $logo,
				'logo_id' => $id,
				'name'    => $text( 'name', 60 ),
				'details' => $lines( 'details', 240 ),
				'title'   => $text( 'title', 24 ),
				'message' => $lines( 'message', 200 ),
				'barcode' => ! empty( $input['barcode'] ) ? '1' : '0',
				'color'   => ! empty( $input['color'] ) ? '1' : '0',
				'accent'  => is_string( $input['accent'] ?? null ) && null !== sanitize_hex_color( $input['accent'] ) ? strtolower( $input['accent'] ) : self::RECEIPT_ACCENT,
			),
			false
		);
	}

	/**
	 * Whether deleting the plugin also deletes the payment records (payments, discount codes, reminders).
	 * Off until the merchant says so: they are accounting history, and an accidental delete shouldn't lose them.
	 */
	public function get_delete_data(): bool {
		return '1' === (string) get_option( self::OPTION_DELETE_DATA, '0' );
	}

	/**
	 * Stores the delete-on-uninstall switch.
	 *
	 * @param bool $enabled Whether to delete the records when the plugin is deleted.
	 */
	public function set_delete_data( bool $enabled ): void {
		update_option( self::OPTION_DELETE_DATA, $enabled ? '1' : '0', false );
	}

	/**
	 * Whether payers who started a payment and didn't finish it get one reminder before their link runs out.
	 * Off until the merchant switches it on: it emails people on its own.
	 */
	public function get_reminder_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_REMINDER_ON, '0' );
	}

	/**
	 * Stores the reminder switch.
	 *
	 * @param bool $enabled Whether to send reminders.
	 */
	public function set_reminder_enabled( bool $enabled ): void {
		update_option( self::OPTION_REMINDER_ON, $enabled ? '1' : '0', false );
	}

	/**
	 * How many hours before a link runs out the reminder goes.
	 */
	public function get_reminder_hours(): int {
		$hours = (int) get_option( self::OPTION_REMINDER_HOURS, 24 );

		return in_array( $hours, self::REMINDER_CHOICES, true ) ? $hours : 24;
	}

	/**
	 * Stores the reminder lead time, if it is one of the choices.
	 *
	 * @param int $hours Hours before expiry.
	 */
	public function set_reminder_hours( int $hours ): void {
		update_option( self::OPTION_REMINDER_HOURS, in_array( $hours, self::REMINDER_CHOICES, true ) ? $hours : 24, false );
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
		$stored = (string) get_option( self::OPTION_SIGNING_SECRET, '' );
		$secret = Vault::reveal( $stored );

		// Missing, too short, or sealed under keys this site no longer has: make a new one. Links signed with the old one stop working.
		if ( strlen( $secret ) < 32 ) {
			$secret = wp_generate_password( 64, false, false );
			$this->store_signing_secret( $secret );
		} elseif ( ! Vault::is_sealed( $stored ) ) {
			// One saved before secrets were sealed is sealed on its first read.
			$this->store_signing_secret( $secret );
		}

		return $secret;
	}

	/**
	 * Replaces the signing secret, so every signed link made so far stops working.
	 */
	public function rotate_signing_secret(): void {
		$this->store_signing_secret( wp_generate_password( 64, false, false ) );
	}

	/**
	 * Saves the signing secret, sealed.
	 *
	 * @param string $secret The plain secret.
	 */
	private function store_signing_secret( string $secret ): void {
		update_option( self::OPTION_SIGNING_SECRET, Vault::seal( $secret ), false );
	}

	/**
	 * Removes every checkout option. Used on uninstall.
	 */
	public function delete_all(): void {
		foreach ( array( self::OPTION_CONFIRMATION, self::OPTION_PAY_BASE, self::OPTION_RECEIPT_PAYER, self::OPTION_RECEIPT_PDF, self::OPTION_RECEIPT_LOOK, self::OPTION_DELETE_DATA, self::OPTION_REMINDER_ON, self::OPTION_REMINDER_HOURS, self::OPTION_NOTIFY_ADMIN, self::OPTION_NOTIFY_EMAIL, self::OPTION_SIGNING_SECRET, self::OPTION_LOOK, self::OPTION_TEXTS, self::OPTION_BUTTON_DESIGN ) as $option ) {
			delete_option( $option );
		}
	}
}
