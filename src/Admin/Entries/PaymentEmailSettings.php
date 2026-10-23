<?php
/**
 * Payment email template editor, opened from the Entries masthead.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Mail\PaymentEmailTemplate;
use Ifthenpay\PayButton\Mail\PaymentRequestEmail;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Payment email" popup behind the Entries masthead's settings button: subject, HTML body, tags and a live preview.
 */
final class PaymentEmailSettings {

	// Debounce for the live preview, in ms.
	private const PREVIEW_DELAY = 400;

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Sets up the editor.
	 *
	 * @param SettingsRepository|null $settings Settings storage; a fresh instance is used when omitted.
	 */
	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/**
	 * Registers the AJAX endpoints.
	 */
	public function register(): void {
		add_action( 'wp_ajax_iftp_btn_save_payment_email', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_iftp_btn_preview_payment_email', array( $this, 'ajax_preview' ) );
	}

	/**
	 * Loads WP's CodeMirror for the body, and returns what entries.js needs.
	 *
	 * The editor is false when the admin turned syntax highlighting off in their profile; the JS keeps the plain textarea then.
	 *
	 * @return array{editor: array<string, mixed>|false, defaults: array{subject: string, body: string}, previewDelay: int}
	 */
	public function enqueue(): array {
		$editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/html',
				'codemirror' => array(
					'lineWrapping' => true,
					// Tab moves focus instead of indenting, so keyboard users aren't stuck in the editor.
					'extraKeys'    => array(
						'Tab'       => false,
						'Shift-Tab' => false,
					),
				),
			)
		);

		// For "Add image", so a logo from the Media Library can go in the email.
		wp_enqueue_media();

		return array(
			'editor'       => $editor,
			'defaults'     => $this->defaults(),
			'previewDelay' => self::PREVIEW_DELAY,
		);
	}

	/**
	 * The settings button that sits next to "New payment".
	 */
	public function render_trigger(): void {
		$label = __( 'Payment email settings', 'ifthenpay-pay-button' );
		?>
		<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-btn--icon" data-iftp-email-open aria-haspopup="dialog" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>">
			<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/></svg>
		</button>
		<?php
	}

	/**
	 * The editor dialog. The fields start on what's saved, or the default when nothing is.
	 */
	public function render_dialog(): void {
		$template = $this->current();
		?>
		<dialog class="iftp-btn-dialog iftp-btn-dialog--wide" aria-labelledby="iftp-btn-email-title" aria-describedby="iftp-btn-email-desc" data-iftp-email-dialog>
			<form class="iftp-btn-dialog__form iftp-btn-email" data-iftp-email-form novalidate>
				<h2 id="iftp-btn-email-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Payment email', 'ifthenpay-pay-button' ); ?></h2>
				<p id="iftp-btn-email-desc" class="iftp-btn-dialog__text"><?php esc_html_e( 'What customers get from "Send payment email". Click a tag to drop a payment detail where your cursor is — it\'s filled in for each customer when the email goes out.', 'ifthenpay-pay-button' ); ?></p>

				<p class="iftp-btn-field__error" data-iftp-email-error role="alert" hidden></p>

				<div class="iftp-btn-email__editor">
					<div class="iftp-btn-email__compose">
						<div class="iftp-btn-field">
							<label class="iftp-btn-field__label" for="iftp-btn-email-subject"><?php esc_html_e( 'Subject', 'ifthenpay-pay-button' ); ?></label>
							<input type="text" class="iftp-btn-field__control" id="iftp-btn-email-subject" name="subject" maxlength="<?php echo esc_attr( (string) PaymentEmailTemplate::MAX_SUBJECT_LENGTH ); ?>" value="<?php echo esc_attr( $template['subject'] ); ?>" data-iftp-email-subject />
						</div>

						<div class="iftp-btn-email__tags" role="group" aria-labelledby="iftp-btn-email-tags-label">
							<span id="iftp-btn-email-tags-label" class="iftp-btn-email__tags-label"><?php esc_html_e( 'Insert a tag', 'ifthenpay-pay-button' ); ?></span>
							<ul class="iftp-btn-email__tags-list">
								<?php foreach ( PaymentEmailTemplate::tags() as $tag => $description ) : ?>
									<li class="iftp-btn-email__tags-item">
										<button type="button" class="iftp-btn-email__tag" data-iftp-email-tag="<?php echo esc_attr( $tag ); ?>" data-iftp-email-tag-help="<?php echo esc_attr( $description ); ?>" title="<?php echo esc_attr( $description ); ?>"><?php echo esc_html( $tag ); ?></button>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="iftp-btn-email__tags-help" data-iftp-email-tag-desc aria-live="polite"><?php esc_html_e( 'Hover or focus a tag to see what it becomes.', 'ifthenpay-pay-button' ); ?></p>
						</div>

						<div class="iftp-btn-field iftp-btn-email__body">
							<div class="iftp-btn-email__body-head">
								<label class="iftp-btn-field__label" for="iftp-btn-email-body"><?php esc_html_e( 'Body (HTML)', 'ifthenpay-pay-button' ); ?></label>
								<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-email-media hidden>
									<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/></svg>
									<?php esc_html_e( 'Add image', 'ifthenpay-pay-button' ); ?>
								</button>
							</div>
							<textarea class="iftp-btn-field__control iftp-btn-email__textarea" id="iftp-btn-email-body" name="body" rows="18" spellcheck="false" data-iftp-email-body><?php echo esc_textarea( $template['body'] ); ?></textarea>
							<p class="iftp-btn-field__help"><?php esc_html_e( 'Inline styles and tables work best in mail clients. Scripts, forms, embeds and event handlers are removed when you save.', 'ifthenpay-pay-button' ); ?></p>
						</div>
					</div>

					<div class="iftp-btn-email__preview">
						<p class="iftp-btn-email__preview-head">
							<span class="iftp-btn-email__preview-title"><?php esc_html_e( 'Preview', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-email__preview-hint"><?php esc_html_e( 'With sample data', 'ifthenpay-pay-button' ); ?></span>
						</p>
						<div class="iftp-btn-email__preview-frame" data-iftp-email-preview-wrap>
							<p class="iftp-btn-email__preview-subject">
								<span class="iftp-btn-email__preview-subject-label"><?php esc_html_e( 'Subject:', 'ifthenpay-pay-button' ); ?></span>
								<span data-iftp-email-preview-subject></span>
							</p>
							<?php // No allow-scripts: whatever the HTML holds, nothing in the preview can run. ?>
							<iframe class="iftp-btn-email__preview-iframe" sandbox="" title="<?php esc_attr_e( 'Email preview', 'ifthenpay-pay-button' ); ?>" width="600" height="480" data-iftp-email-preview></iframe>
						</div>
					</div>
				</div>

				<div class="iftp-btn-dialog__actions iftp-btn-dialog__actions--split">
					<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-email-reset><?php esc_html_e( 'Reset to default', 'ifthenpay-pay-button' ); ?></button>
					<span class="iftp-btn-dialog__actions-end">
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-email-cancel><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
						<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-email-submit><?php esc_html_e( 'Save', 'ifthenpay-pay-button' ); ?></button>
					</span>
				</div>
			</form>
		</dialog>
		<?php
	}

	/**
	 * AJAX: saves the template, after sanitizing it.
	 *
	 * Sends back what was stored, so the editor shows exactly what will go out.
	 */
	public function ajax_save(): void {
		$this->guard();

		$posted  = $this->posted();
		$subject = PaymentEmailTemplate::sanitize_subject( $posted['subject'] );
		$body    = PaymentEmailTemplate::sanitize_body( $posted['body'] );

		if ( '' === $subject ) {
			wp_send_json_error( array( 'message' => __( 'Enter a subject.', 'ifthenpay-pay-button' ) ), 400 );
		}

		if ( '' === $body ) {
			wp_send_json_error( array( 'message' => __( 'The email body is empty.', 'ifthenpay-pay-button' ) ), 400 );
		}

		if ( false === strpos( $body, PaymentEmailTemplate::TAG_PAYMENT_URL ) ) {
			wp_send_json_error(
				array(
					/* translators: %s: the {payment_url} tag */
					'message' => sprintf( __( 'Add the %s tag to the body (e.g. as a button\'s link), or your customer has no way to pay.', 'ifthenpay-pay-button' ), PaymentEmailTemplate::TAG_PAYMENT_URL ),
				),
				400
			);
		}

		$defaults = $this->defaults();

		// The default is stored as '', so it keeps following the site language.
		$this->settings->set_payment_email(
			$subject === $defaults['subject'] ? '' : $subject,
			$body === $defaults['body'] ? '' : $body
		);

		$lost = PaymentEmailTemplate::lost_markup( $posted['body'], $body );

		wp_send_json_success(
			array(
				'subject' => $subject,
				'body'    => $body,
				'message' => $lost
					? __( 'Payment email saved. Some HTML that isn\'t allowed in emails was removed — the editor now shows what will be sent.', 'ifthenpay-pay-button' )
					: __( 'Payment email saved.', 'ifthenpay-pay-button' ),
			)
		);
	}

	/**
	 * AJAX: renders the unsaved template with sample data, through the same path a real send takes.
	 */
	public function ajax_preview(): void {
		$this->guard();

		$posted = $this->posted();
		$email  = PaymentRequestEmail::render(
			array(
				'ref'            => 'IFTP_7Q2K9XW4MB',
				'amount'         => 12.5,
				// Inert: the preview frame is sandboxed, so this goes nowhere.
				'payment_url'    => home_url( '/#ifthenpay-payment-link' ),
				'customer_name'  => 'Ana Silva',
				'customer_email' => 'ana.silva@example.com',
			),
			array(
				array(
					'label' => __( 'Customer name', 'ifthenpay-pay-button' ),
					'value' => 'Ana Silva',
				),
				array(
					'label' => __( 'Customer email', 'ifthenpay-pay-button' ),
					'value' => 'ana.silva@example.com',
				),
			),
			// An emptied field previews as blank rather than falling back to the default.
			'' !== trim( $posted['subject'] ) ? $posted['subject'] : ' ',
			'' !== trim( $posted['body'] ) ? $posted['body'] : ' '
		);

		wp_send_json_success(
			array(
				'subject' => $email['subject'],
				'html'    => '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body marginwidth="0" marginheight="0">' . $email['body'] . '</body></html>',
			)
		);
	}

	/**
	 * The saved template, with the defaults filled in for whatever isn't customized.
	 *
	 * @return array{subject: string, body: string}
	 */
	private function current(): array {
		$defaults = $this->defaults();
		$subject  = $this->settings->get_payment_email_subject();
		$body     = $this->settings->get_payment_email_body();

		return array(
			'subject' => '' !== $subject ? $subject : $defaults['subject'],
			'body'    => '' !== $body ? $body : $defaults['body'],
		);
	}

	/**
	 * The default template in the site's language, the one customers read.
	 *
	 * @return array{subject: string, body: string}
	 */
	private function defaults(): array {
		$switched = switch_to_locale( get_locale() );

		$defaults = array(
			'subject' => PaymentEmailTemplate::default_subject(),
			'body'    => PaymentEmailTemplate::sanitize_body( PaymentEmailTemplate::default_body() ),
		);

		if ( $switched ) {
			restore_previous_locale();
		}

		return $defaults;
	}

	/**
	 * The posted subject and body, unsanitized; callers sanitize for their own use.
	 *
	 * @return array{subject: string, body: string}
	 */
	private function posted(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- guard() verified the nonce; the body is HTML, so PaymentEmailTemplate sanitizes it with its own kses allow-list instead.
		$subject = isset( $_POST['subject'] ) && is_string( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '';
		$body    = isset( $_POST['body'] ) && is_string( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';
		// phpcs:enable

		if ( strlen( $body ) > PaymentEmailTemplate::MAX_BODY_LENGTH ) {
			wp_send_json_error( array( 'message' => __( 'The email body is too long.', 'ifthenpay-pay-button' ) ), 400 );
		}

		return array(
			'subject' => $subject,
			'body'    => str_replace( "\r\n", "\n", $body ),
		);
	}

	/**
	 * Same nonce, capability and connection check as the rest of the Entries screens.
	 */
	private function guard(): void {
		check_ajax_referer( EntriesPage::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( Plugin::admin_cap() ) || ! $this->settings->is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ifthenpay-pay-button' ) ), 403 );
		}
	}
}
