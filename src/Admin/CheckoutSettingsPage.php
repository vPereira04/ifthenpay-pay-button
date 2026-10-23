<?php
/**
 * The Checkout settings screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything around a payment that isn't the gateway, in four tabs: how the checkout and popups look (with a
 * live preview), what the popups say (with merge tags), every other word the payer reads, and pay links and emails.
 */
class CheckoutSettingsPage {

	public const PAGE_SLUG   = 'iftp-btn-checkout';
	public const SAVE_ACTION = 'iftp_btn_save_checkout';

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $settings;

	/**
	 * The screen's hook suffix.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Sets up the screen.
	 *
	 * @param CheckoutSettings $settings Checkout settings.
	 */
	public function __construct( CheckoutSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks the save and the assets.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the submenu.
	 *
	 * @param string $parent_slug The parent menu slug.
	 */
	public function add_page( string $parent_slug ): void {
		$hook = add_submenu_page( $parent_slug, __( 'Checkout', 'ifthenpay-pay-button' ), __( 'Checkout', 'ifthenpay-pay-button' ), Plugin::admin_cap(), self::PAGE_SLUG, array( $this, 'render' ) );

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * The screen's URL.
	 */
	public static function url(): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
	}

	/**
	 * Enqueues the shared admin assets and the checkout stylesheet the preview draws with, only here.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		$js  = 'assets/js/buttons.js';
		$css = 'assets/css/buttons.css';

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_style( 'iftp-btn-buttons', IFTP_BTN_URL . $css, array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( $css ) );
		wp_enqueue_style( ButtonRenderer::STYLE_HANDLE );
		wp_add_inline_style( 'iftp-btn-buttons', LookControls::swatch_css() );
		// The pay page's background image and video come from the Media Library.
		wp_enqueue_media();
		wp_enqueue_script( 'iftp-btn-buttons', IFTP_BTN_URL . $js, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE ), Plugin::asset_version( $js ), true );
		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnButtons',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => '',
				'lookPresets' => CheckoutLook::presets(),
				'lookWidths'  => CheckoutLook::WIDTHS,
				'sample'      => self::sample_values(),
				'i18n'        => array(
					'copied'     => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed' => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					'linkPrompt' => __( 'Link address', 'ifthenpay-pay-button' ),
					'pill'       => __( 'Pill', 'ifthenpay-pay-button' ),
					/* translators: %d: corner radius in pixels */
					'px'         => __( '%d px', 'ifthenpay-pay-button' ),
					'unsaved'    => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
					'pickImage'  => __( 'Pay page background', 'ifthenpay-pay-button' ),
					'useImage'   => __( 'Use this image', 'ifthenpay-pay-button' ),
					'pickVideo'  => __( 'Pay page video', 'ifthenpay-pay-button' ),
					'useVideo'   => __( 'Use this video', 'ifthenpay-pay-button' ),
				),
			)
		);
	}

	/**
	 * Made-up payment details for the previews' merge tags.
	 *
	 * @return array<string, string>
	 */
	public static function sample_values(): array {
		return array(
			'name'       => 'Ana Silva',
			'email'      => 'ana@example.com',
			'phone'      => '+351 912 345 678',
			'nif'        => '123456789',
			'note'       => __( 'Thank you!', 'ifthenpay-pay-button' ),
			'amount'     => '35,00 €',
			'quantity'   => '1',
			'payment_id' => 'BTN6F2A91C4',
			'order'      => 'ORDER-1042',
			'method'     => 'MB WAY',
			'button'     => __( 'Consultation', 'ifthenpay-pay-button' ),
			'site'       => (string) get_bloginfo( 'name' ),
			'date'       => Dates::local( (string) get_option( 'date_format' ) ),
		);
	}

	/**
	 * Saves the form.
	 */
	public function save(): void {
		check_admin_referer( self::SAVE_ACTION );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		$text     = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$type     = $text( 'paid_type' );
		$messages = array();
		$titles   = array();

		foreach ( CheckoutSettings::OUTCOMES as $outcome ) {
			$messages[ $outcome ] = isset( $_POST['message'][ $outcome ] ) && is_string( $_POST['message'][ $outcome ] ) ? wp_kses_post( wp_unslash( $_POST['message'][ $outcome ] ) ) : '';
			$titles[ $outcome ]   = isset( $_POST['title'][ $outcome ] ) && is_string( $_POST['title'][ $outcome ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['title'][ $outcome ] ) ), 0, 120 ) : '';
		}

		/**
		 * The loop above fills every outcome.
		 *
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $messages
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $titles
		 */
		$this->settings->set_confirmation(
			array(
				'paid_type'    => in_array( $type, CheckoutSettings::PAID_TYPES, true ) ? $type : 'popup',
				'paid_page'    => absint( $text( 'paid_page' ) ),
				'paid_url'     => esc_url_raw( $text( 'paid_url' ), array( 'http', 'https' ) ),
				'show_details' => isset( $_POST['show_details'] ),
				'messages'     => $messages,
				'titles'       => $titles,
			)
		);

		// CheckoutLook::sanitize() cleans every key by its own rules, so here it's only unslashed.
		$look = isset( $_POST['look'] ) && is_array( $_POST['look'] ) ? map_deep( wp_unslash( $_POST['look'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Custom CSS is as powerful as unfiltered HTML, so only those who may post that can change it.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$look['css'] = $this->settings->get_look()['css'];
		}

		$this->settings->set_look( CheckoutLook::sanitize( $look ) );

		// CheckoutTexts::sanitize() cleans each text, so here it's only unslashed.
		$this->settings->set_texts( CheckoutTexts::sanitize( isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$this->settings->set_receipt_payer( isset( $_POST['receipt_payer'] ) );
		$this->settings->set_notify_admin( isset( $_POST['notify_admin'] ) );

		$notify = sanitize_email( $text( 'notify_email' ) );
		$this->settings->set_notify_email( is_email( $notify ) ? $notify : '' );

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

		// Back to the tab they saved from.
		wp_safe_redirect( add_query_arg( 'iftp_notice', 'saved', self::url() ) . ( in_array( $tab, array( 'messages', 'texts', 'links' ), true ) ? '#' . $tab : '' ) );
		exit;
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$tabs = array(
			'design'   => __( 'Design', 'ifthenpay-pay-button' ),
			'messages' => __( 'Messages', 'ifthenpay-pay-button' ),
			'texts'    => __( 'Texts', 'ifthenpay-pay-button' ),
			'links'    => __( 'Links & emails', 'ifthenpay-pay-button' ),
		);
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-checkout-settings">
			<?php ButtonsPage::print_header( __( 'Checkout', 'ifthenpay-pay-button' ), __( 'How the checkout and its popups look and every word they say, where pay links live, and who gets emailed.', 'ifthenpay-pay-button' ) ); ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag from the redirect after a checked save.
			if ( isset( $_GET['iftp_notice'] ) ) {
				wp_admin_notice(
					esc_html__( 'Settings saved.', 'ifthenpay-pay-button' ),
					array(
						'type'        => 'success',
						'dismissible' => true,
					)
				);
			}
			?>

			<form class="iftp-btn-csettings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-iftp-csettings>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<input type="hidden" name="tab" value="design" data-iftp-tab-input />
				<?php wp_nonce_field( self::SAVE_ACTION ); ?>

				<div class="iftp-btn-csettings__bar">
					<div class="iftp-btn-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Checkout settings', 'ifthenpay-pay-button' ); ?>" data-iftp-tabs hidden>
						<?php foreach ( $tabs as $key => $label ) : ?>
							<button type="button" class="iftp-btn-tabs__tab" role="tab" id="iftp-btn-tab-<?php echo esc_attr( $key ); ?>" aria-controls="iftp-btn-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'design' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'design' === $key ? '0' : '-1'; ?>" data-iftp-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<p class="iftp-btn-editor__dirty" data-iftp-dirty hidden><?php esc_html_e( 'Unsaved changes', 'ifthenpay-pay-button' ); ?></p>
					<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary"><?php esc_html_e( 'Save Settings', 'ifthenpay-pay-button' ); ?></button>
				</div>

				<div class="iftp-btn-lookpage">
					<div class="iftp-btn-lookpage__controls">
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-design" aria-labelledby="iftp-btn-tab-design" data-iftp-panel="design">
							<?php ( new LookControls( 'look' ) )->render_cards( $this->settings->get_look(), true ); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-messages" aria-labelledby="iftp-btn-tab-messages" data-iftp-panel="messages">
							<?php $this->render_messages(); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-texts" aria-labelledby="iftp-btn-tab-texts" data-iftp-panel="texts">
							<?php $this->render_texts(); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-links" aria-labelledby="iftp-btn-tab-links" data-iftp-panel="links">
							<?php $this->render_links(); ?>
						</div>
					</div>
					<aside class="iftp-btn-lookpage__aside">
						<div class="iftp-btn-lookpage__sticky">
							<?php ( new LookControls( 'look' ) )->render_preview( $this->settings->get_look(), $this->settings->get_confirmation() ); ?>
						</div>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * The Messages tab: a title and a message per outcome, with merge tags.
	 */
	private function render_messages(): void {
		$confirmation = $this->settings->get_confirmation();
		$titles       = array(
			'paid'      => __( 'Paid', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Pending', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Failed', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'Cancelled', 'ifthenpay-pay-button' ),
		);
		$hints        = array(
			'paid'      => __( 'ifthenpay confirmed the payment.', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Back from ifthenpay, but not confirmed yet: a Multibanco or Payshop reference waiting to be paid.', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Declined, or the link expired.', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'The payer cancelled on the ifthenpay page.', 'ifthenpay-pay-button' ),
		);
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-confirm-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-confirm-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Confirmation type', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Back from ifthenpay, the payer lands on the page they paid from and sees one of these. If it changes later (pending turns paid), the popup comes back even if they closed it. Click a tag to drop in that payment\'s detail.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-outcomes">
				<?php foreach ( $titles as $outcome => $outcome_title ) : ?>
					<fieldset class="iftp-btn-outcome iftp-btn-outcome--<?php echo esc_attr( $outcome ); ?>" data-iftp-tag-scope>
						<legend class="iftp-btn-outcome__title"><?php echo esc_html( $outcome_title ); ?></legend>
						<p class="iftp-btn-field__help"><?php echo esc_html( $hints[ $outcome ] ); ?></p>

						<?php if ( 'paid' === $outcome ) : ?>
							<div class="iftp-btn-segment iftp-btn-segment--compact" data-iftp-paid-segment>
								<?php
								foreach (
									array(
										'popup' => __( 'Popup message', 'ifthenpay-pay-button' ),
										'page'  => __( 'WordPress page', 'ifthenpay-pay-button' ),
										'url'   => __( 'Custom URL', 'ifthenpay-pay-button' ),
									) as $type => $type_label
								) :
									?>
									<label class="iftp-btn-segment__option">
										<input type="radio" name="paid_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( $confirmation['paid_type'], $type ); ?> data-iftp-paid-type />
										<span class="iftp-btn-segment__title"><?php echo esc_html( $type_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="iftp-btn-field" data-iftp-when-paid="page">
								<label class="iftp-btn-field__label" for="iftp-btn-paid-page"><?php esc_html_e( 'Page', 'ifthenpay-pay-button' ); ?></label>
								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'paid_page',
										'id'               => 'iftp-btn-paid-page',
										'class'            => 'iftp-btn-field__control',
										'selected'         => (int) $confirmation['paid_page'],
										'show_option_none' => esc_html__( 'Choose a page…', 'ifthenpay-pay-button' ),
										'option_none_value' => '0',
									)
								);
								?>
							</div>
							<div class="iftp-btn-field" data-iftp-when-paid="url">
								<label class="iftp-btn-field__label" for="iftp-btn-paid-url"><?php esc_html_e( 'Address', 'ifthenpay-pay-button' ); ?></label>
								<input type="url" class="iftp-btn-field__control" id="iftp-btn-paid-url" name="paid_url" value="<?php echo esc_attr( $confirmation['paid_url'] ); ?>" placeholder="https://" />
							</div>
						<?php endif; ?>

						<div class="iftp-btn-outcome__words" <?php echo 'paid' === $outcome ? 'data-iftp-when-paid="popup"' : ''; ?>>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-title-<?php echo esc_attr( $outcome ); ?>"><?php esc_html_e( 'Title', 'ifthenpay-pay-button' ); ?></label>
								<input type="text" class="iftp-btn-field__control" id="iftp-btn-title-<?php echo esc_attr( $outcome ); ?>" name="title[<?php echo esc_attr( $outcome ); ?>]" maxlength="120" value="<?php echo esc_attr( $confirmation['titles'][ $outcome ] ); ?>" placeholder="<?php echo esc_attr( CheckoutSettings::default_titles()[ $outcome ] ); ?>" data-iftp-tag-target data-iftp-outcome-title="<?php echo esc_attr( $outcome ); ?>" />
							</div>

							<div class="iftp-btn-msg" data-iftp-msg data-iftp-outcome-message="<?php echo esc_attr( $outcome ); ?>">
								<div class="iftp-btn-msg__bar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'ifthenpay-pay-button' ); ?>">
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'ifthenpay-pay-button' ); ?>"><strong>B</strong></button>
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'ifthenpay-pay-button' ); ?>"><em>I</em></button>
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="createLink" aria-label="<?php esc_attr_e( 'Link', 'ifthenpay-pay-button' ); ?>">
										<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6.5 9.5a3 3 0 0 0 4.2 0l2-2a3 3 0 0 0-4.2-4.2l-.7.7M9.5 6.5a3 3 0 0 0-4.2 0l-2 2a3 3 0 0 0 4.2 4.2l.7-.7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
									</button>
									<span class="iftp-btn-msg__views" role="group" aria-label="<?php esc_attr_e( 'Editor view', 'ifthenpay-pay-button' ); ?>">
										<button type="button" class="iftp-btn-msg__view" data-iftp-msg-view="normal" aria-pressed="true"><?php esc_html_e( 'Normal', 'ifthenpay-pay-button' ); ?></button>
										<button type="button" class="iftp-btn-msg__view" data-iftp-msg-view="raw" aria-pressed="false"><?php esc_html_e( 'Raw', 'ifthenpay-pay-button' ); ?></button>
									</span>
								</div>
								<div class="iftp-btn-msg__normal" contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?php echo esc_attr( $outcome_title ); ?>" data-iftp-msg-normal data-iftp-tag-target hidden></div>
								<label class="screen-reader-text" for="iftp-btn-msg-<?php echo esc_attr( $outcome ); ?>"><?php echo esc_html( $outcome_title ); ?></label>
								<textarea class="iftp-btn-field__control iftp-btn-msg__raw" id="iftp-btn-msg-<?php echo esc_attr( $outcome ); ?>" name="message[<?php echo esc_attr( $outcome ); ?>]" rows="3" data-iftp-msg-raw data-iftp-tag-target><?php echo esc_textarea( $confirmation['messages'][ $outcome ] ); ?></textarea>
							</div>

							<?php ButtonsPage::print_tags( MergeTags::available( true ) ); ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<label class="iftp-btn-check">
					<input type="checkbox" name="show_details" value="1" <?php checked( $confirmation['show_details'] ); ?> data-iftp-show-details />
					<?php esc_html_e( 'Show the payment details (amount, reference, method) under the message', 'ifthenpay-pay-button' ); ?>
				</label>
			</div>
		</section>
		<?php
	}

	/**
	 * The Texts tab: the original next to a box for the merchant's own, one card per place the payer reads them.
	 */
	private function render_texts(): void {
		$own      = $this->settings->get_texts();
		$defaults = CheckoutTexts::defaults();
		?>
		<p class="iftp-btn-texts__intro"><?php esc_html_e( 'Write any of these in your own words or language. Blank keeps the original, which follows the site\'s language. Keep words in {braces}: the payer sees the number, price or name there.', 'ifthenpay-pay-button' ); ?></p>
		<?php
		foreach ( CheckoutTexts::groups() as $group => $info ) :
			?>
			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-texts-<?php echo esc_attr( $group ); ?>" data-iftp-text-view="<?php echo esc_attr( $info['view'] ); ?>">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-texts-<?php echo esc_attr( $group ); ?>" class="iftp-btn-panel__title"><?php echo esc_html( $info['title'] ); ?></h2>
					<p class="iftp-btn-panel__description"><?php echo esc_html( $info['description'] ); ?></p>
				</div>
				<div class="iftp-btn-panel__body iftp-btn-texts">
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
			</section>
			<?php
		endforeach;
	}

	/**
	 * The Links & emails tab.
	 */
	private function render_links(): void {
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-links-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-links-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Pay links', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Every button has its own pay page. Changing the base breaks links you already shared.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-pay-base"><?php esc_html_e( 'Link base', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-affix">
						<span class="iftp-btn-affix__text"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', home_url( '/' ) ) ); ?></span>
						<input type="text" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-pay-base" name="pay_base" maxlength="40" pattern="[a-z0-9\-]+" value="<?php echo esc_attr( $this->settings->get_pay_base() ); ?>" />
						<span class="iftp-btn-affix__text">/<?php esc_html_e( 'button', 'ifthenpay-pay-button' ); ?>/</span>
					</span>
				</div>
				<label class="iftp-btn-check">
					<input type="checkbox" name="rotate_signing" value="1" />
					<?php esc_html_e( 'Make a new signing secret (every signed link made so far stops working)', 'ifthenpay-pay-button' ); ?>
				</label>
			</div>
		</section>

		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-emails-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-emails-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Emails', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Sent once ifthenpay confirms a payment, never before.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body iftp-btn-stack">
				<label class="iftp-btn-np__toggle">
					<input type="checkbox" class="iftp-btn-switch" name="receipt_payer" value="1" <?php checked( $this->settings->get_receipt_payer() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Receipt to the payer', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Only when the button asks for an email.', 'ifthenpay-pay-button' ); ?></span>
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
	}
}
