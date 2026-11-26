<?php
/**
 * The Configuration tabs saved by one form.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Entries\PaymentEmailSettings;
use Ifthenpay\PayButton\Ai\AiSettings;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Texts, Emails, Pay links and AI: what the payer reads, who gets emailed, where links live and how the AI
 * behaves. One form across the five tabs, so a save keeps them all; the Backoffice tab saves on its own.
 */
class ConfigurationForm {

	public const FORM_ID     = 'iftp-btn-config-form';
	public const SAVE_ACTION = 'iftp_btn_save_config';

	// Transient (plus the user id) holding why the payment email wasn't saved, for the toast after the redirect.
	public const EMAIL_ERROR_KEY = 'iftp_btn_email_error_';

	private const TABS = array( 'texts', 'emails', 'links', 'ai', 'data' );

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $settings;

	/**
	 * Gateway settings, for the weekly summary.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $gateway;

	/**
	 * Sets up the form.
	 *
	 * @param CheckoutSettings   $settings Checkout settings.
	 * @param SettingsRepository $gateway  Gateway settings.
	 */
	public function __construct( CheckoutSettings $settings, SettingsRepository $gateway ) {
		$this->settings = $settings;
		$this->gateway  = $gateway;
	}

	/**
	 * Hooks the save.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
	}

	/**
	 * The form and its five tabs.
	 *
	 * @param bool $connected Whether a Backoffice Key is connected.
	 */
	public function render( bool $connected ): void {
		?>
		<form class="iftp-btn-config__form" id="<?php echo esc_attr( self::FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-iftp-config-form>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
			<input type="hidden" name="tab" value="backoffice" data-iftp-tab-input />
			<?php wp_nonce_field( self::SAVE_ACTION ); ?>

			<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-texts" aria-labelledby="iftp-btn-tab-texts" data-iftp-panel="texts">
				<?php $this->render_texts(); ?>
			</div>
			<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-emails" aria-labelledby="iftp-btn-tab-emails" data-iftp-panel="emails">
				<?php $this->render_emails(); ?>
			</div>
			<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-links" aria-labelledby="iftp-btn-tab-links" data-iftp-panel="links">
				<?php $this->render_links(); ?>
			</div>
			<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-ai" aria-labelledby="iftp-btn-tab-ai" data-iftp-panel="ai">
				<?php $this->render_ai( $connected ); ?>
			</div>
			<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-data" aria-labelledby="iftp-btn-tab-data" data-iftp-panel="data">
				<?php $this->render_data(); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Saves every tab of the form.
	 */
	public function save(): void {
		check_admin_referer( self::SAVE_ACTION );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		$text = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

		// CheckoutTexts::sanitize() cleans each text, so here it's only unslashed.
		$this->settings->set_texts( CheckoutTexts::sanitize( isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->settings->set_receipt_payer( isset( $_POST['receipt_payer'] ) );
		$this->settings->set_receipt_pdf( isset( $_POST['receipt_pdf'] ) );
		$this->settings->set_receipt_look( isset( $_POST['receipt'] ) && is_array( $_POST['receipt'] ) ? map_deep( wp_unslash( $_POST['receipt'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- RECEIPT_LOOK cleans each part in set_receipt_look().
		$this->settings->set_delete_data( isset( $_POST['delete_data'] ) );
		$this->settings->set_reminder_enabled( isset( $_POST['reminder_on'] ) );
		$this->settings->set_reminder_hours( (int) $text( 'reminder_hours' ) );
		$this->settings->set_notify_admin( isset( $_POST['notify_admin'] ) );

		$notify = sanitize_email( $text( 'notify_email' ) );
		$this->settings->set_notify_email( is_email( $notify ) ? $notify : '' );

		$summary = sanitize_email( $text( 'summary_email' ) );
		$this->gateway->set_summary_enabled( isset( $_POST['summary_enabled'] ) );
		$this->gateway->set_summary_email( is_email( $summary ) ? $summary : '' );

		// AiSettings::sanitize() cleans every key by its own rules, so here it's only unslashed.
		AiSettings::set( AiSettings::sanitize( isset( $_POST['ai'] ) && is_array( $_POST['ai'] ) ? map_deep( wp_unslash( $_POST['ai'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$base = sanitize_title( $text( 'pay_base' ) );
		$base = '' !== $base ? $base : CheckoutSettings::DEFAULT_PAY_BASE;
		$tab  = sanitize_key( $text( 'tab' ) );

		if ( isset( $_POST['rotate_signing'] ) ) {
			$this->settings->rotate_signing_secret();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $base !== $this->settings->get_pay_base() ) {
			$this->settings->set_pay_base( $base );
			Plugin::flush_rewrites();
		}

		// The payment email is saved with the rest, so Save Settings never drops what was typed in its editor.
		$notice = 'saved';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		if ( $this->gateway->is_connected() && isset( $_POST['payment_email'] ) && is_array( $_POST['payment_email'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the body is HTML, so PaymentEmailTemplate cleans it with its own allow-list.
			$email  = wp_unslash( $_POST['payment_email'] );
			$stored = ( new PaymentEmailSettings( $this->gateway ) )->store(
				is_string( $email['subject'] ?? null ) ? $email['subject'] : '',
				is_string( $email['body'] ?? null ) ? str_replace( "\r\n", "\n", $email['body'] ) : ''
			);

			if ( ! $stored['ok'] ) {
				set_transient( self::EMAIL_ERROR_KEY . get_current_user_id(), $stored['message'], MINUTE_IN_SECONDS );
				$notice = 'email_invalid';
			}
		}

		// Back to the tab they saved from.
		wp_safe_redirect( add_query_arg( 'iftp_notice', $notice, ConfigurationPage::url() ) . ( in_array( $tab, self::TABS, true ) ? '#' . $tab : '' ) );
		exit;
	}

	/**
	 * Opens a card that folds away on its heading, like the Default Design's: a long tab shows only what is being worked on.
	 *
	 * @param string $id          Its key; the page remembers whether it was left open.
	 * @param string $title_id    The heading's id.
	 * @param string $title       The heading.
	 * @param string $description The line under it.
	 * @param bool   $open        Whether it starts open.
	 * @param string $attributes  Extra attributes, already escaped.
	 */
	public static function open_card( string $id, string $title_id, string $title, string $description, bool $open = false, string $attributes = '' ): void {
		?>
		<details class="iftp-btn-ecard iftp-btn-ecard--fold" data-iftp-fold="<?php echo esc_attr( $id ); ?>"<?php echo $open ? ' open' : ''; ?><?php echo $attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute names from the callers. ?>>
			<summary class="iftp-btn-ecard__intro">
				<h2 id="<?php echo esc_attr( $title_id ); ?>" class="iftp-btn-ecard__title"><?php echo esc_html( $title ); ?></h2>
				<p class="iftp-btn-ecard__description"><?php echo esc_html( $description ); ?></p>
			</summary>
			<div class="iftp-btn-ecard__body">
		<?php
	}

	/**
	 * Closes a card.
	 */
	public static function close_card(): void {
		?>
			</div>
		</details>
		<?php
	}

	/**
	 * The Texts tab: the original next to a box for the merchant's own, one card per place the payer reads them.
	 */
	private function render_texts(): void {
		$own      = $this->settings->get_texts();
		$defaults = CheckoutTexts::defaults();
		?>
		<p class="iftp-btn-texts__intro"><?php esc_html_e( 'Most sites never need to change these: blank keeps the original, which follows the site\'s language. Open a group to write any of them in your own words or language. Keep words in {braces}: the payer sees the number, price or name there.', 'ifthenpay-pay-button' ); ?></p>
		<?php
		$first = true;

		foreach ( CheckoutTexts::groups() as $group => $info ) :
			self::open_card( 'texts-' . $group, 'iftp-btn-texts-' . $group, $info['title'], $info['description'], $first, ' data-iftp-text-view="' . esc_attr( $info['view'] ) . '"' );
			$first = false;
			?>
				<div class="iftp-btn-texts">
					<?php
					foreach ( $info['items'] as $key => $hint ) :
						$field_id = 'iftp-btn-text-' . str_replace( '_', '-', $key );
						?>
						<div class="iftp-btn-texts__row">
							<label class="iftp-btn-texts__original" for="<?php echo esc_attr( $field_id ); ?>">
								<span class="iftp-btn-texts__default"><?php echo esc_html( $defaults[ $key ] ); ?></span>
								<?php if ( '' !== $hint ) : ?>
									<span class="iftp-btn-texts__hint"><?php echo esc_html( $hint ); ?></span>
								<?php endif; ?>
							</label>
							<input type="text" class="iftp-btn-field__control" id="<?php echo esc_attr( $field_id ); ?>" name="texts[<?php echo esc_attr( $key ); ?>]" maxlength="<?php echo esc_attr( (string) CheckoutTexts::MAX_LENGTH ); ?>" value="<?php echo esc_attr( $own[ $key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $defaults[ $key ] ); ?>" data-iftp-text-input="<?php echo esc_attr( $key ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>
			<?php
			self::close_card();
		endforeach;
	}

	/**
	 * The receipt's own part of the Emails tab: logo, name and details, title, a message and the barcode.
	 */
	private function render_receipt_look(): void {
		$look  = $this->settings->get_receipt_look();
		$thumb = $look['logo_id'] > 0 ? wp_get_attachment_image_url( $look['logo_id'], 'medium' ) : false;
		$full  = $look['logo_id'] > 0 ? wp_get_attachment_image_url( $look['logo_id'], 'large' ) : false;
		?>
		<?php self::open_card( 'config-receipt', 'iftp-btn-receipt-title', __( 'Proof of payment look', 'ifthenpay-pay-button' ), __( 'The PDF the payer keeps as proof of payment. It is not an invoice and not valid for tax purposes. Logo, business details, title, message, colours and barcode. Black and white unless you switch colours on.', 'ifthenpay-pay-button' ), false, ' data-iftp-receipt' ); ?>
			<div class="iftp-btn-stack">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-logo"><?php esc_html_e( 'Logo', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-receipt-logo" name="receipt[logo]" data-iftp-receipt-logo>
						<option value="none" <?php selected( $look['logo'], 'none' ); ?>><?php esc_html_e( 'No logo', 'ifthenpay-pay-button' ); ?></option>
						<option value="site" <?php selected( $look['logo'], 'site' ); ?>><?php esc_html_e( 'My site\'s logo', 'ifthenpay-pay-button' ); ?></option>
						<option value="image" <?php selected( $look['logo'], 'image' ); ?>><?php esc_html_e( 'An image of mine', 'ifthenpay-pay-button' ); ?></option>
					</select>
				</div>
				<div class="iftp-btn-field" data-iftp-receipt-image <?php echo 'image' === $look['logo'] ? '' : 'hidden'; ?>>
					<span class="iftp-btn-field__label"><?php esc_html_e( 'Image', 'ifthenpay-pay-button' ); ?></span>
					<div class="iftp-btn-imagepick">
						<span class="iftp-btn-imagepick__thumb" data-iftp-receipt-thumb<?php echo is_string( $thumb ) ? ' style="background-image: url(' . esc_url( $thumb ) . ')"' : ''; ?>></span>
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-receipt-pick><?php esc_html_e( 'Choose image', 'ifthenpay-pay-button' ); ?></button>
						<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-receipt-clear<?php echo is_string( $thumb ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'ifthenpay-pay-button' ); ?></button>
						<input type="hidden" name="receipt[logo_id]" value="<?php echo esc_attr( (string) $look['logo_id'] ); ?>" data-iftp-receipt-id data-url="<?php echo esc_attr( is_string( $full ) ? $full : '' ); ?>" />
					</div>
					<p class="iftp-btn-field__help"><?php esc_html_e( 'In grey tones, or in its own colours when colours are on. A wide logo on a white or transparent background works best.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-name"><?php esc_html_e( 'Business name', 'ifthenpay-pay-button' ); ?></label>
					<input type="text" class="iftp-btn-field__control" id="iftp-btn-receipt-name" name="receipt[name]" maxlength="60" value="<?php echo esc_attr( $look['name'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>" />
					<p class="iftp-btn-field__help"><?php esc_html_e( 'Blank uses the site\'s name. With a logo, the name is only printed when you fill this in.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-details"><?php esc_html_e( 'Business details', 'ifthenpay-pay-button' ); ?></label>
					<textarea class="iftp-btn-field__control" id="iftp-btn-receipt-details" name="receipt[details]" rows="3" maxlength="240" placeholder="<?php esc_attr_e( 'Address, tax number, phone…', 'ifthenpay-pay-button' ); ?>"><?php echo esc_textarea( $look['details'] ); ?></textarea>
					<p class="iftp-btn-field__help"><?php esc_html_e( 'Printed under the name, one line each.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-title-input"><?php esc_html_e( 'Title', 'ifthenpay-pay-button' ); ?></label>
					<input type="text" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-receipt-title-input" name="receipt[title]" maxlength="24" value="<?php echo esc_attr( $look['title'] ); ?>" placeholder="<?php echo esc_attr( __( 'Proof of payment', 'ifthenpay-pay-button' ) ); ?>" />
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-message"><?php esc_html_e( 'Message', 'ifthenpay-pay-button' ); ?></label>
					<textarea class="iftp-btn-field__control" id="iftp-btn-receipt-message" name="receipt[message]" rows="3" maxlength="200" placeholder="<?php esc_attr_e( 'See you next time!', 'ifthenpay-pay-button' ); ?>"><?php echo esc_textarea( $look['message'] ); ?></textarea>
					<p class="iftp-btn-field__help"><?php esc_html_e( 'Printed at the bottom, above the thank-you line.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="receipt[color]" value="1" <?php checked( $look['color'] ); ?> data-iftp-receipt-colour />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Colours', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Off prints in black and white, logo included. On keeps the logo\'s own colours and uses one colour for the title, the total and the PAID line.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<div class="iftp-btn-field" data-iftp-receipt-accent <?php echo $look['color'] ? '' : 'hidden'; ?>>
					<label class="iftp-btn-field__label" for="iftp-btn-receipt-accent"><?php esc_html_e( 'Colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color">
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( $look['accent'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the proof of payment colour', 'ifthenpay-pay-button' ); ?>" data-iftp-receipt-swatch />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-receipt-accent" name="receipt[accent]" value="<?php echo esc_attr( $look['accent'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-receipt-hex />
					</span>
				</div>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="receipt[barcode]" value="1" <?php checked( $look['barcode'] ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Barcode with the reference', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'The strip of bars at the bottom, like a shop receipt.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
			</div>
		<?php self::close_card(); ?>
		<?php
	}

	/**
	 * The Emails tab: what goes out per payment, and the Monday summary.
	 */
	private function render_emails(): void {
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-emails-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-emails-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Payment emails', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Sent once ifthenpay confirms a payment, never before.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="receipt_payer" value="1" <?php checked( $this->settings->get_receipt_payer() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Proof of payment to the payer', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Only when the button asks for an email.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="receipt_pdf" value="1" <?php checked( $this->settings->get_receipt_pdf() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Attach a PDF proof of payment', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'A copy the payer can keep. Its look is set in Proof of payment look, below.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="notify_admin" value="1" <?php checked( $this->settings->get_notify_admin() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Tell me about every payment', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'With the amount, the payer and a link to it.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-notify-email"><?php esc_html_e( 'Send it to', 'ifthenpay-pay-button' ); ?></label>
					<input type="email" class="iftp-btn-field__control" id="iftp-btn-notify-email" name="notify_email" value="<?php echo esc_attr( $this->settings->get_notify_email_raw() ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" />
				</div>
			</div>
		</section>

		<?php
		$this->render_receipt_look();

		// The template editor needs a connected account, like the "Send payment email" action it belongs to.
		if ( $this->gateway->is_connected() ) {
			( new PaymentEmailSettings( $this->gateway ) )->render_panel();
		}
		?>

		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-reminder-panel-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-reminder-panel-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Payment reminders', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'One email to a payer who started a payment and hasn\'t paid, shortly before the link runs out, with the link to finish it.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="reminder_on" value="1" <?php checked( $this->settings->get_reminder_enabled() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Remind payers who haven\'t paid', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Only when the button asked for an email. Each payment is reminded once, never after it is paid, failed or expired.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-reminder-hours"><?php esc_html_e( 'Send it', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-reminder-hours" name="reminder_hours">
						<?php foreach ( CheckoutSettings::REMINDER_CHOICES as $hours ) : ?>
							<option value="<?php echo esc_attr( (string) $hours ); ?>" <?php selected( $this->settings->get_reminder_hours(), $hours ); ?>>
								<?php
								/* translators: %d: hours */
								echo esc_html( sprintf( _n( '%d hour before the link runs out', '%d hours before the link runs out', $hours, 'ifthenpay-pay-button' ), $hours ) );
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		</section>

		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-summary-panel-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-summary-panel-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Weekly summary', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Every Monday at 08:00: last week\'s revenue and its change, the top methods and customers, and the money still waiting.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<label class="iftp-btn-np__toggle iftp-btn-summary-toggle">
					<input type="checkbox" class="iftp-btn-switch" name="summary_enabled" value="1" <?php checked( $this->gateway->get_summary_enabled() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Send the weekly summary', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'An email in the same look as these screens.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<div class="iftp-btn-field iftp-btn-summary-to">
					<label class="iftp-btn-field__label" for="iftp-btn-summary-email"><?php esc_html_e( 'Send it to', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-inline">
						<input type="email" class="iftp-btn-field__control" id="iftp-btn-summary-email" name="summary_email" value="<?php echo esc_attr( $this->gateway->get_summary_email() ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" data-iftp-summary-email />
						<?php if ( $this->gateway->is_connected() ) : ?>
							<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-summary-test><?php esc_html_e( 'Send a test now', 'ifthenpay-pay-button' ); ?></button>
						<?php endif; ?>
					</span>
					<p class="iftp-btn-field__help" data-iftp-summary-status role="status"></p>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * The Pay links tab.
	 */
	private function render_links(): void {
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-links-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-links-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Pay links', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Every button has its own pay page. Changing the base breaks links you already shared.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-pay-base"><?php esc_html_e( 'Link base', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-affix">
						<span class="iftp-btn-affix__text"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', home_url( '/' ) ) ); ?></span>
						<input type="text" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-pay-base" name="pay_base" maxlength="40" pattern="[a-z0-9\-]+" value="<?php echo esc_attr( $this->settings->get_pay_base() ); ?>" />
						<span class="iftp-btn-affix__text">/<?php esc_html_e( 'button', 'ifthenpay-pay-button' ); ?>/</span>
					</span>
				</div>
				<?php DesignControls::open_fold( 'config-links-advanced', __( 'Advanced', 'ifthenpay-pay-button' ), __( 'The secret behind links that carry an amount.', 'ifthenpay-pay-button' ) ); ?>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'A pay link that sets the amount carries a signature made with a secret kept on this site, so nobody can change the price in the address. A new secret cancels every signed link you already shared.', 'ifthenpay-pay-button' ); ?></p>
				<label class="iftp-btn-check">
					<input type="checkbox" name="rotate_signing" value="1" />
					<?php esc_html_e( 'Make a new secret now and cancel the signed links so far', 'ifthenpay-pay-button' ); ?>
				</label>
				<?php DesignControls::close_fold(); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * The Data tab: what happens to the payment records when the plugin is deleted.
	 */
	private function render_data(): void {
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-data-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-data-title" class="iftp-btn-panel__title"><?php esc_html_e( 'When the plugin is deleted', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Payments, discount codes and reminders are your accounting history, so they stay in the database unless you say otherwise.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<label class="iftp-btn-check">
					<input type="checkbox" name="delete_data" value="1" <?php checked( $this->settings->get_delete_data() ); ?> />
					<?php esc_html_e( 'Also delete all payments, discount codes and reminders when the plugin is deleted', 'ifthenpay-pay-button' ); ?>
				</label>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'Your settings, keys and buttons are always removed. This cannot be undone.', 'ifthenpay-pay-button' ); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * The AI tab: the connection, then the merchant's caps and preferences.
	 *
	 * @param bool $connected Whether a Backoffice Key is connected; the AI builds buttons, which need one.
	 */
	private function render_ai( bool $connected ): void {
		$ai = AiSettings::get();

		if ( $connected ) {
			( new AiPanel() )->render_panel();
		} else {
			?>
			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-ai-off-title">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-ai-off-title" class="iftp-btn-panel__title"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'AI assistant', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-panel__description"><?php esc_html_e( 'It builds pay buttons, so it opens once your ifthenpay account is connected.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-panel__body">
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-goto-tab="backoffice"><?php esc_html_e( 'Connect ifthenpay first', 'ifthenpay-pay-button' ); ?></button>
				</div>
			</section>
			<?php
		}
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-ai-style-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-ai-style-title" class="iftp-btn-panel__title"><?php esc_html_e( 'How it works', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Faster or more careful, safer or more surprising. Change these any time; the next message uses them.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<?php
				$this->print_ai_choice(
					'thinking',
					__( 'Thinking', 'ifthenpay-pay-button' ),
					$ai['thinking'],
					array(
						'quick'    => array( __( 'Quick', 'ifthenpay-pay-button' ), __( 'Answers in seconds.', 'ifthenpay-pay-button' ) ),
						'balanced' => array( __( 'Balanced', 'ifthenpay-pay-button' ), __( 'A little slower, more careful.', 'ifthenpay-pay-button' ) ),
						'deep'     => array( __( 'Deep', 'ifthenpay-pay-button' ), __( 'Slowest and dearest, for big forms.', 'ifthenpay-pay-button' ) ),
					),
					__( 'Gemini and ChatGPT\'s reasoning models only. Claude always answers straight away.', 'ifthenpay-pay-button' )
				);
				$this->print_ai_choice(
					'creativity',
					__( 'Creativity', 'ifthenpay-pay-button' ),
					$ai['creativity'],
					array(
						'focused'  => array( __( 'Focused', 'ifthenpay-pay-button' ), __( 'Sticks closely to what you ask.', 'ifthenpay-pay-button' ) ),
						'balanced' => array( __( 'Balanced', 'ifthenpay-pay-button' ), __( 'Good ideas, no surprises.', 'ifthenpay-pay-button' ) ),
						'creative' => array( __( 'Creative', 'ifthenpay-pay-button' ), __( 'Bolder looks and wording.', 'ifthenpay-pay-button' ) ),
					),
					__( 'Not used by ChatGPT\'s reasoning models, which pick their own.', 'ifthenpay-pay-button' )
				);
				?>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="ai[fallback]" value="1" <?php checked( $ai['fallback'] ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Try other models when yours is busy', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Up to two more from your key, so a busy moment doesn\'t stop you. Off, it only ever uses the model you picked.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="ai[prices]" value="1" <?php checked( $ai['prices'] ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Let the AI set prices', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'The amount, its range and the price of each option. Off, it keeps your prices and you set new ones in the editor.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
			</div>
		</section>

		<?php self::open_card( 'config-ai-limits', 'iftp-btn-ai-limits-title', __( 'Limits and advanced', 'ifthenpay-pay-button' ), __( 'Message caps, chat memory, answer size and timeouts. The defaults suit most sites; every message is billed to your own key, so these stop a stuck key from running up the bill.', 'ifthenpay-pay-button' ) ); ?>
			<div class="iftp-btn-stack">
				<div class="iftp-btn-config__pair">
					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-ai-hourly"><?php esc_html_e( 'Messages per hour, per admin', 'ifthenpay-pay-button' ); ?></label>
						<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-hourly" name="ai[hourly]" min="1" max="<?php echo esc_attr( (string) AiSettings::MAX_HOURLY ); ?>" step="1" inputmode="numeric" required value="<?php echo esc_attr( (string) $ai['hourly'] ); ?>" />
					</div>
					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-ai-daily"><?php esc_html_e( 'Messages per day, whole site', 'ifthenpay-pay-button' ); ?></label>
						<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-daily" name="ai[daily]" min="0" max="<?php echo esc_attr( (string) AiSettings::MAX_DAILY ); ?>" step="1" inputmode="numeric" required value="<?php echo esc_attr( (string) $ai['daily'] ); ?>" aria-describedby="iftp-btn-ai-daily-help" />
						<p class="iftp-btn-field__help" id="iftp-btn-ai-daily-help"><?php esc_html_e( '0 means no daily limit. Counted from midnight UTC.', 'ifthenpay-pay-button' ); ?></p>
					</div>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-ai-memory"><?php esc_html_e( 'Chat memory', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-memory" name="ai[memory]" aria-describedby="iftp-btn-ai-memory-help">
						<?php foreach ( AiSettings::MEMORY as $count ) : ?>
							<?php /* translators: %d: how many earlier chat messages go along with each new one */ ?>
							<option value="<?php echo esc_attr( (string) $count ); ?>" <?php selected( $ai['memory'], $count ); ?>><?php echo esc_html( sprintf( _n( 'The last %d message', 'The last %d messages', $count, 'ifthenpay-pay-button' ), $count ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="iftp-btn-field__help" id="iftp-btn-ai-memory-help"><?php esc_html_e( 'How much of the chat goes along with each message. The button itself always goes in full; a shorter memory costs less.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-ai-keep"><?php esc_html_e( 'Keep chats for', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-keep" name="ai[keep_days]" aria-describedby="iftp-btn-ai-keep-help">
						<?php foreach ( AiSettings::KEEP_DAYS as $days ) : ?>
							<?php /* translators: %d: number of days */ ?>
							<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( $ai['keep_days'], $days ); ?>><?php echo esc_html( sprintf( _n( '%d day', '%d days', $days, 'ifthenpay-pay-button' ), $days ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="iftp-btn-field__help" id="iftp-btn-ai-keep-help"><?php esc_html_e( 'After that, a button\'s chat starts fresh. Saved buttons are never touched.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-ai-answer"><?php esc_html_e( 'Longest answer', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-answer" name="ai[answer]" aria-describedby="iftp-btn-ai-answer-help">
						<?php
						$answer_names = array(
							8192  => __( 'Short (8,192 tokens)', 'ifthenpay-pay-button' ),
							16000 => __( 'Long (16,000 tokens)', 'ifthenpay-pay-button' ),
							32000 => __( 'Longest (32,000 tokens)', 'ifthenpay-pay-button' ),
						);
						?>
						<?php foreach ( AiSettings::ANSWER as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( $ai['answer'], $size ); ?>><?php echo esc_html( $answer_names[ $size ] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="iftp-btn-field__help" id="iftp-btn-ai-answer-help"><?php esc_html_e( 'The most the AI may write in one answer. A button or checkout it draws in code is long, and a cap that is too small cuts it off. You only pay for what it writes. Some models stop at 16,000, so pick Long if one refuses the longest.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-ai-wait"><?php esc_html_e( 'Wait for each model', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-ai-wait" name="ai[wait]" aria-describedby="iftp-btn-ai-wait-help">
						<?php foreach ( AiSettings::WAIT as $seconds ) : ?>
							<?php /* translators: %d: seconds */ ?>
							<option value="<?php echo esc_attr( (string) $seconds ); ?>" <?php selected( $ai['wait'], $seconds ); ?>><?php echo esc_html( 0 === $seconds ? __( 'Follow Thinking', 'ifthenpay-pay-button' ) : sprintf( __( '%d seconds', 'ifthenpay-pay-button' ), $seconds ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="iftp-btn-field__help" id="iftp-btn-ai-wait-help"><?php esc_html_e( 'How long one model may take before the next one is tried. Long answers, like a drawn checkout, can need two minutes. Your host has to allow a request to last as long as this and a second try; if a message ends with a timeout page, lower it.', 'ifthenpay-pay-button' ); ?></p>
				</div>
			</div>
		<?php self::close_card(); ?>
		<?php
	}

	/**
	 * A row of choices, each with a short line under its name.
	 *
	 * @param string                                     $key     The setting.
	 * @param string                                     $label   Its name.
	 * @param string                                     $value   The current choice.
	 * @param array<string, array{0: string, 1: string}> $options Value => [ name, line ].
	 * @param string                                     $help    A line under the row.
	 */
	private function print_ai_choice( string $key, string $label, string $value, array $options, string $help ): void {
		?>
		<fieldset class="iftp-btn-field">
			<legend class="iftp-btn-field__label"><?php echo esc_html( $label ); ?></legend>
			<div class="iftp-btn-segment">
				<?php foreach ( $options as $option => $labels ) : ?>
					<label class="iftp-btn-segment__option">
						<input type="radio" name="ai[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $option ); ?>" <?php checked( $value, $option ); ?> />
						<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
						<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<p class="iftp-btn-field__help"><?php echo esc_html( $help ); ?></p>
		</fieldset>
		<?php
	}
}
