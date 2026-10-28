<?php
/**
 * Configuration screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Sync\GatewaySync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything that isn't design, in tabs. Gateway holds the standard settings contract: Connect (Backoffice Key
 * popup) → Disconnect, then the Gateway Key, the methods table with the default star, and the hosted page's
 * description and expiry, saved over AJAX. Messages, Texts, Emails, Pay links and AI are one admin-post form,
 * drawn by ConfigurationForm; Messages and Texts keep the live checkout preview beside them.
 *
 * Styled by assets/css/admin.css, the unified ifthenpay look, and buttons.css for the tabs and the preview.
 */
class ConfigurationPage {

	public const PAGE_SLUG = 'iftp-btn-settings';


	private const BACKFILL_LOCK = 'iftp_btn_backfill_lock';

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Methods table renderer.
	 *
	 * @var MethodsTable
	 */
	private MethodsTable $methods_table;

	/**
	 * Gateway catalog sync.
	 *
	 * @var GatewaySync
	 */
	private GatewaySync $sync;

	/**
	 * The admin-post tabs.
	 *
	 * @var ConfigurationForm
	 */
	private ConfigurationForm $form;

	/**
	 * This screen's hook suffix, once the menu registered it.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Sets up the page.
	 *
	 * @param SettingsRepository|null $settings      Settings storage.
	 * @param MethodsTable|null       $methods_table Methods table renderer.
	 * @param GatewaySync|null        $sync          Gateway catalog sync.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?MethodsTable $methods_table = null, ?GatewaySync $sync = null ) {
		$this->settings      = $settings ?? new SettingsRepository();
		$this->methods_table = $methods_table ?? new MethodsTable( $this->settings );
		$this->sync          = $sync ?? new GatewaySync( $this->settings );
		$this->form          = new ConfigurationForm( new CheckoutSettings(), $this->settings );
	}

	/**
	 * The screen's URL, open on a tab if one is given.
	 *
	 * @param string $tab A tab key, e.g. `messages` or `ai`.
	 */
	public static function url( string $tab = '' ): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) . ( '' !== $tab ? '#' . $tab : '' );
	}

	/**
	 * Registers the asset hook and the form's save.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->form->register();
	}

	/**
	 * Remembers this screen's hook suffix, so assets only load here.
	 *
	 * @param string $hook The hook suffix add_submenu_page() returned.
	 */
	public function set_hook( string $hook ): void {
		$this->hook = $hook;
	}

	/**
	 * Enqueues the screen's script, only on this screen.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		$settings_js = 'assets/js/settings.js';
		$buttons_js  = 'assets/js/buttons.js';
		$buttons_css = 'assets/css/buttons.css';

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		// The tabs, the merge tag chips and the checkout preview.
		wp_enqueue_style( 'iftp-btn-buttons', IFTP_BTN_URL . $buttons_css, array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( $buttons_css ) );
		wp_enqueue_style( ButtonRenderer::STYLE_HANDLE );
		wp_enqueue_script( 'iftp-btn-buttons', IFTP_BTN_URL . $buttons_js, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE ), Plugin::asset_version( $buttons_js ), true );
		wp_enqueue_script( 'iftp-btn-settings', IFTP_BTN_URL . $settings_js, array( Plugin::METHOD_LOGOS_HANDLE, Plugin::UI_SCRIPT_HANDLE ), Plugin::asset_version( $settings_js ), true );

		// The preview draws the site's look, which is set in Default Design.
		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnButtons',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => '',
				'lookPresets' => CheckoutLook::presets(),
				'lookWidths'  => CheckoutLook::WIDTHS,
				'siteLook'    => ButtonsPage::site_look_for_js(),
				'sample'      => CheckoutSettingsPage::sample_values(),
				'i18n'        => array(
					'copied'     => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed' => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					'linkPrompt' => __( 'Link address', 'ifthenpay-pay-button' ),
					'unsaved'    => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
				),
			)
		);

		if ( $this->settings->is_connected() ) {
			( new AiPanel() )->enqueue();
		}

		wp_localize_script(
			'iftp-btn-settings',
			'iftpBtnSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( SettingsController::NONCE_ACTION ),
				'i18n'    => array(
					'connect'     => __( 'Connect', 'ifthenpay-pay-button' ),
					'connecting'  => __( 'Connecting…', 'ifthenpay-pay-button' ),
					'invalidKey'  => __( 'Invalid Backoffice Key...', 'ifthenpay-pay-button' ),
					'disconnect'  => __( 'Disconnect', 'ifthenpay-pay-button' ),
					'requesting'  => __( 'Requesting…', 'ifthenpay-pay-button' ),
					'requested'   => __( 'Requested', 'ifthenpay-pay-button' ),
					'requestSent' => __( 'Activation request sent. ifthenpay will get back to you by email.', 'ifthenpay-pay-button' ),
					'loading'     => __( 'Loading the payment methods…', 'ifthenpay-pay-button' ),
					'saving'      => __( 'Saving…', 'ifthenpay-pay-button' ),
					'sending'     => __( 'Sending…', 'ifthenpay-pay-button' ),
					'saved'       => __( 'Settings saved.', 'ifthenpay-pay-button' ),
					'error'       => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
				),
			)
		);
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$connected = $this->settings->is_connected();

		// The lock keeps a failing API from being called on every load until the backfill lands.
		if ( $connected && $this->sync->needs_backfill() && false === get_transient( self::BACKFILL_LOCK ) ) {
			set_transient( self::BACKFILL_LOCK, 1, 15 * MINUTE_IN_SECONDS );
			$this->sync->sync();
		}

		$tabs = array(
			'gateway'  => __( 'Gateway', 'ifthenpay-pay-button' ),
			'messages' => __( 'Messages', 'ifthenpay-pay-button' ),
			'texts'    => __( 'Texts', 'ifthenpay-pay-button' ),
			'emails'   => __( 'Emails', 'ifthenpay-pay-button' ),
			'links'    => __( 'Pay links', 'ifthenpay-pay-button' ),
			'ai'       => __( 'AI', 'ifthenpay-pay-button' ),
		);
		$look = ( new CheckoutSettings() )->get_look();
		?>
		<div id="iftp-btn-settings" class="wrap iftp-btn-buttons iftp-btn-config" data-iftp-config>
			<?php ButtonsPage::print_header( __( 'Configuration', 'ifthenpay-pay-button' ) ); ?>
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

			<div class="iftp-btn-csettings__bar">
				<div class="iftp-btn-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Configuration', 'ifthenpay-pay-button' ); ?>" data-iftp-tabs hidden>
					<?php foreach ( $tabs as $key => $label ) : ?>
						<button type="button" class="iftp-btn-tabs__tab" role="tab" id="iftp-btn-tab-<?php echo esc_attr( $key ); ?>" aria-controls="iftp-btn-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'gateway' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'gateway' === $key ? '0' : '-1'; ?>" data-iftp-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="iftp-btn-editor__dirty" data-iftp-dirty hidden><?php esc_html_e( 'Unsaved changes', 'ifthenpay-pay-button' ); ?></p>
				<?php // The Gateway tab saves over AJAX with its own button; this one saves the other tabs. ?>
				<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary iftp-btn-config__save" form="<?php echo esc_attr( ConfigurationForm::FORM_ID ); ?>"><?php esc_html_e( 'Save Settings', 'ifthenpay-pay-button' ); ?></button>
			</div>

			<div class="iftp-btn-lookpage">
				<div class="iftp-btn-lookpage__controls">
					<div class="iftp-btn-tabpanel iftp-btn-config__gateway" role="tabpanel" id="iftp-btn-panel-gateway" aria-labelledby="iftp-btn-tab-gateway" data-iftp-panel="gateway" data-iftp-no-dirty>
						<?php $this->render_gateway( $connected ); ?>
					</div>
					<?php $this->form->render( $connected ); ?>
				</div>
				<aside class="iftp-btn-lookpage__aside" data-iftp-aside="look">
					<div class="iftp-btn-lookpage__sticky">
						<p class="iftp-btn-config__look-note">
							<?php esc_html_e( 'Drawn in your checkout\'s look.', 'ifthenpay-pay-button' ); ?>
							<a href="<?php echo esc_url( CheckoutSettingsPage::url() ); ?>"><?php esc_html_e( 'Change it in Default Design', 'ifthenpay-pay-button' ); ?></a>
						</p>
						<?php ( new LookControls( 'look' ) )->render_preview( $look, ( new CheckoutSettings() )->get_confirmation() ); ?>
					</div>
				</aside>
			</div>

			<?php
			if ( $connected ) {
				// Popups hold forms of their own, so they live outside the settings forms.
				( new AiPanel() )->render_dialogs();
				$this->render_disconnect_dialog();
			} else {
				$this->render_connect_dialog();
			}

			ButtonsPage::print_shared_ui();
			?>
		</div>
		<?php
	}

	/**
	 * The Gateway tab: the ifthenpay account, and once connected the gateway itself.
	 *
	 * @param bool $connected Whether a Backoffice Key is connected.
	 */
	private function render_gateway( bool $connected ): void {
		?>
		<section class="iftp-btn-panel iftp-btn-connection" aria-labelledby="iftp-btn-connection-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-connection-title" class="iftp-btn-panel__title"><?php esc_html_e( 'ifthenpay account', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Your Backoffice Key unlocks the Gateway Keys and payment methods on your ifthenpay contract.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<p class="iftp-btn-connection__status iftp-btn-connection__status--<?php echo $connected ? 'connected' : 'disconnected'; ?>">
					<span class="iftp-btn-connection__dot" aria-hidden="true"></span>
					<?php echo $connected ? esc_html__( 'Connected', 'ifthenpay-pay-button' ) : esc_html__( 'Not connected', 'ifthenpay-pay-button' ); ?>
				</p>
				<?php if ( $connected ) : ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-connection__action" data-iftp-disconnect-open aria-haspopup="dialog">
						<?php esc_html_e( 'Disconnect', 'ifthenpay-pay-button' ); ?>
					</button>
				<?php else : ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary iftp-btn-connection__action" data-iftp-connect-open aria-haspopup="dialog">
						<?php esc_html_e( 'Connect', 'ifthenpay-pay-button' ); ?>
					</button>
				<?php endif; ?>
				<p class="iftp-btn-connection__message" data-iftp-connection-message role="alert"></p>
			</div>
		</section>
		<?php
		if ( $connected ) {
			$this->render_configuration();
		}
	}

	/**
	 * The gateway, revealed once connected. Saved as one unit over AJAX.
	 */
	private function render_configuration(): void {
		$gateway_keys = $this->settings->get_gateway_keys();
		$current_key  = $this->settings->get_gateway_key();
		?>
		<form class="iftp-btn-settings__form" data-iftp-settings-form>
			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-gateway-title">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-gateway-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Gateway Key', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-panel__description" id="iftp-btn-gateway-key-help"><?php esc_html_e( 'The Gateway Key payments are created under. Switching it reloads its payment methods.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-panel__body">
					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-gateway-key"><?php esc_html_e( 'Gateway Key', 'ifthenpay-pay-button' ); ?></label>
						<select class="iftp-btn-field__control" id="iftp-btn-gateway-key" name="gateway_key" aria-describedby="iftp-btn-gateway-key-help" data-iftp-gateway-key>
							<?php foreach ( $gateway_keys as $key ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $current_key ); ?>><?php echo esc_html( $key ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</section>

			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-methods-title">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-methods-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Payment methods', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-panel__description"><?php esc_html_e( 'Choose which methods to offer, and star the one the hosted payment page opens on.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-panel__body iftp-btn-methods-wrap" data-iftp-methods>
					<?php $this->methods_table->output(); ?>
				</div>
			</section>

			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-hosted-title">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-hosted-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Hosted payment page', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-panel__description"><?php esc_html_e( 'What the payer reads on the ifthenpay page, and how long an unpaid link stays open.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-panel__body">
					<div class="iftp-btn-field" data-iftp-tag-scope>
						<label class="iftp-btn-field__label" for="iftp-btn-description"><?php esc_html_e( 'Description', 'ifthenpay-pay-button' ); ?></label>
						<input type="text" class="iftp-btn-field__control" id="iftp-btn-description" name="description" maxlength="200" value="<?php echo esc_attr( $this->settings->get_description() ); ?>" placeholder="<?php echo esc_attr( SettingsRepository::DEFAULT_DESCRIPTION ); ?>" aria-describedby="iftp-btn-description-help" data-iftp-tag-target />
						<p class="iftp-btn-field__help" id="iftp-btn-description-help"><?php esc_html_e( 'Shown on the ifthenpay page while paying. Tags fill in each payment\'s details, e.g. "{button} · {payment_id}". A button can set its own in its editor. Empty shows the site name.', 'ifthenpay-pay-button' ); ?></p>
						<?php ButtonsPage::print_tags( MergeTags::available( false ) ); ?>
					</div>
					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-expiry-days"><?php esc_html_e( 'Expiry days', 'ifthenpay-pay-button' ); ?></label>
						<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-expiry-days" name="expiry_days" min="1" max="365" step="1" inputmode="numeric" required value="<?php echo esc_attr( (string) $this->settings->get_expiry_days() ); ?>" aria-describedby="iftp-btn-expiry-days-help" />
						<p class="iftp-btn-field__help" id="iftp-btn-expiry-days-help"><?php esc_html_e( 'After this many days an unpaid payment is marked Expired, and any spot it held opens again. Every button uses it.', 'ifthenpay-pay-button' ); ?></p>
					</div>
				</div>
			</section>

			<div class="iftp-btn-settings__actions">
				<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-save><?php esc_html_e( 'Save Settings', 'ifthenpay-pay-button' ); ?></button>
				<span class="iftp-btn-save-status" data-iftp-save-status role="status"></span>
			</div>
		</form>
		<?php
	}

	/**
	 * The Backoffice Key popup. A native <dialog>, so it's a real modal (focus, Escape, top layer) with no styles at all.
	 */
	private function render_connect_dialog(): void {
		?>
		<dialog class="iftp-btn-dialog" aria-labelledby="iftp-btn-connect-title" aria-describedby="iftp-btn-connect-desc" data-iftp-connect-dialog>
			<form class="iftp-btn-dialog__form" data-iftp-connect-form novalidate>
				<h2 id="iftp-btn-connect-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Connect to ifthenpay', 'ifthenpay-pay-button' ); ?></h2>
				<p id="iftp-btn-connect-desc" class="iftp-btn-dialog__text"><?php esc_html_e( 'Paste the Backoffice Key from your ifthenpay account. It stays on this site and is never shown again.', 'ifthenpay-pay-button' ); ?></p>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-backoffice-key"><?php esc_html_e( 'Backoffice Key', 'ifthenpay-pay-button' ); ?></label>
					<input
						type="text"
						class="iftp-btn-field__control"
						id="iftp-btn-backoffice-key"
						name="backoffice_key"
						placeholder="<?php esc_attr_e( 'Insert your backoffice key here..', 'ifthenpay-pay-button' ); ?>"
						autocomplete="off"
						spellcheck="false"
						inputmode="numeric"
						maxlength="19"
						required
						aria-describedby="iftp-btn-backoffice-key-help iftp-btn-connect-error"
						data-iftp-backoffice-key
					/>
					<p id="iftp-btn-backoffice-key-help" class="iftp-btn-field__help"><?php esc_html_e( 'The key from your ifthenpay Backoffice account.', 'ifthenpay-pay-button' ); ?></p>
					<p id="iftp-btn-connect-error" class="iftp-btn-field__error" role="alert" data-iftp-connect-error></p>
				</div>

				<div class="iftp-btn-dialog__actions">
					<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
					<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-connect-submit><?php esc_html_e( 'Connect', 'ifthenpay-pay-button' ); ?></button>
				</div>
			</form>
		</dialog>
		<?php
	}

	/**
	 * The disconnect confirmation, in place of window.confirm().
	 */
	private function render_disconnect_dialog(): void {
		?>
		<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-disconnect-title" aria-describedby="iftp-btn-disconnect-desc" data-iftp-disconnect-dialog>
			<h2 id="iftp-btn-disconnect-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Disconnect ifthenpay?', 'ifthenpay-pay-button' ); ?></h2>
			<p id="iftp-btn-disconnect-desc" class="iftp-btn-dialog__text"><?php esc_html_e( 'This clears the Backoffice Key, Gateway Key and payment methods. Your entries stay, and you can connect again at any time.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<?php // Cancel comes first and takes focus, so Enter doesn't disconnect by accident. ?>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close autofocus><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--danger" data-iftp-disconnect-confirm><?php esc_html_e( 'Disconnect', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</dialog>
		<?php
	}
}
