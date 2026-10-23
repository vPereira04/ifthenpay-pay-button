<?php
/**
 * Gateway Configuration screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Sync\GatewaySync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The standard settings contract on one screen: Connect (Backoffice Key popup) → Disconnect, then the
 * Gateway Key, the methods table with the default star, and the hosted page's description and expiry.
 *
 * Styled by assets/css/admin.css, the unified ifthenpay look, through the `iftp-btn-` BEM classes below.
 */
class GatewaySettingsPage {

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
	}

	/**
	 * The screen's URL.
	 */
	public static function url(): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
	}

	/**
	 * Registers the asset hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
		// For the description's merge tag chips.
		wp_enqueue_style( 'iftp-btn-buttons', IFTP_BTN_URL . $buttons_css, array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( $buttons_css ) );
		wp_enqueue_script( 'iftp-btn-buttons', IFTP_BTN_URL . $buttons_js, array( Plugin::UI_SCRIPT_HANDLE ), Plugin::asset_version( $buttons_js ), true );
		wp_enqueue_script( 'iftp-btn-settings', IFTP_BTN_URL . $settings_js, array( Plugin::METHOD_LOGOS_HANDLE, Plugin::UI_SCRIPT_HANDLE ), Plugin::asset_version( $settings_js ), true );

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
		?>
		<div id="iftp-btn-settings" class="wrap iftp-btn-settings">
			<header class="iftp-btn-settings__header">
				<img class="iftp-btn-settings__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="94" height="24" />
				<div class="iftp-btn-settings__heading">
					<h1 class="iftp-btn-settings__title"><?php esc_html_e( 'Gateway Configuration', 'ifthenpay-pay-button' ); ?></h1>
					<p class="iftp-btn-settings__subtitle"><?php esc_html_e( 'Connect your ifthenpay account, then pick the Gateway Key and the payment methods your hosted payment page offers.', 'ifthenpay-pay-button' ); ?></p>
				</div>
			</header>
			<hr class="wp-header-end" />

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
				$this->render_disconnect_dialog();
			} else {
				$this->render_connect_dialog();
			}
			?>
		</div>
		<?php
	}

	/**
	 * The gateway configuration, revealed once connected. Saved as one unit over AJAX.
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

			<?php $this->render_summary_panel(); ?>
			<?php $this->render_display_panel(); ?>

			<div class="iftp-btn-settings__actions">
				<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-save><?php esc_html_e( 'Save Settings', 'ifthenpay-pay-button' ); ?></button>
				<span class="iftp-btn-save-status" data-iftp-save-status role="status"></span>
			</div>
		</form>
		<?php
	}

	/**
	 * How the screens look. Picking one previews it right away; Save keeps it.
	 */
	private function render_display_panel(): void {
		$current = $this->settings->get_display_mode();
		$modes   = array(
			'standard' => array( __( 'Standard', 'ifthenpay-pay-button' ), __( 'The full view: charts, trends and every detail.', 'ifthenpay-pay-button' ) ),
			'minimal'  => array( __( 'Minimal', 'ifthenpay-pay-button' ), __( 'Flat and calm: fewer shadows, no motion, a tighter layout.', 'ifthenpay-pay-button' ) ),
			'focus'    => array( __( 'Focus', 'ifthenpay-pay-button' ), __( 'Money first: revenue up front, paid in green, the rest steps back.', 'ifthenpay-pay-button' ) ),
		);
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-display-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-display-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Display', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'How these screens and the Dashboard widget look, for everyone on this site.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<fieldset class="iftp-btn-modes">
					<legend class="screen-reader-text"><?php esc_html_e( 'Display mode', 'ifthenpay-pay-button' ); ?></legend>
					<?php foreach ( $modes as $mode => $text ) : ?>
						<label class="iftp-btn-mode iftp-btn-mode--<?php echo esc_attr( $mode ); ?>">
							<input type="radio" class="iftp-btn-mode__input" name="display_mode" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $current, $mode ); ?> data-iftp-display-mode />
							<?php // A tiny drawing of the mode; decorative, the text says it. ?>
							<span class="iftp-btn-mode__preview" aria-hidden="true">
								<span class="iftp-btn-mode__bar"></span>
								<span class="iftp-btn-mode__chart"></span>
								<span class="iftp-btn-mode__tiles"><span></span><span></span><span></span></span>
							</span>
							<span class="iftp-btn-mode__title"><?php echo esc_html( $text[0] ); ?></span>
							<span class="iftp-btn-mode__text"><?php echo esc_html( $text[1] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<?php // Focus's own tools; shown while Focus is picked, and only used in Focus. ?>
				<div class="iftp-btn-focus-tools" data-iftp-focus-only<?php echo 'focus' === $current ? '' : ' hidden'; ?>>
					<p class="iftp-btn-focus-tools__title"><?php esc_html_e( 'Focus tools', 'ifthenpay-pay-button' ); ?></p>
					<div class="iftp-btn-focus-tools__fields">
						<div class="iftp-btn-field">
							<label class="iftp-btn-field__label" for="iftp-btn-monthly-goal"><?php esc_html_e( 'Monthly revenue goal (€)', 'ifthenpay-pay-button' ); ?></label>
							<input type="number" class="iftp-btn-field__control" id="iftp-btn-monthly-goal" name="monthly_goal" min="0" step="1" inputmode="decimal" value="<?php echo esc_attr( $this->settings->get_monthly_goal() > 0 ? (string) $this->settings->get_monthly_goal() : '' ); ?>" placeholder="<?php esc_attr_e( 'No goal', 'ifthenpay-pay-button' ); ?>" aria-describedby="iftp-btn-monthly-goal-help" />
							<p class="iftp-btn-field__help" id="iftp-btn-monthly-goal-help"><?php esc_html_e( 'Entries shows how close this month is, and the pace you need.', 'ifthenpay-pay-button' ); ?></p>
						</div>
						<div class="iftp-btn-field">
							<label class="iftp-btn-field__label" for="iftp-btn-remind-days"><?php esc_html_e( 'Automatic reminders', 'ifthenpay-pay-button' ); ?></label>
							<select class="iftp-btn-field__control" id="iftp-btn-remind-days" name="remind_days" aria-describedby="iftp-btn-remind-days-help">
								<?php foreach ( SettingsRepository::REMIND_DAY_CHOICES as $days ) : ?>
									<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( $this->settings->get_remind_days(), $days ); ?>>
										<?php
										echo esc_html(
											0 === $days
												? __( 'Off', 'ifthenpay-pay-button' )
												/* translators: %s: number of days */
												: sprintf( _n( 'After %s day unpaid', 'After %s days unpaid', $days, 'ifthenpay-pay-button' ), number_format_i18n( $days ) )
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="iftp-btn-field__help" id="iftp-btn-remind-days-help"><?php esc_html_e( 'The customer gets the payment email once more. One reminder per payment, never more.', 'ifthenpay-pay-button' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * The Monday summary email: on/off, where it goes, and a test send.
	 */
	private function render_summary_panel(): void {
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-summary-panel-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-summary-panel-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Weekly summary', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Every Monday at 08:00: last week\'s revenue and its change, the top methods and customers, and the money still waiting.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<label class="iftp-btn-np__toggle iftp-btn-summary-toggle">
					<input type="checkbox" class="iftp-btn-switch" name="summary_enabled" value="1" <?php checked( $this->settings->get_summary_enabled() ); ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Send the weekly summary', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'An email in the same look as these screens.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>
				<div class="iftp-btn-field iftp-btn-summary-to">
					<label class="iftp-btn-field__label" for="iftp-btn-summary-email"><?php esc_html_e( 'Send it to', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-inline">
						<input type="email" class="iftp-btn-field__control" id="iftp-btn-summary-email" name="summary_email" value="<?php echo esc_attr( $this->settings->get_summary_email() ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>" data-iftp-summary-email />
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-summary-test><?php esc_html_e( 'Send a test now', 'ifthenpay-pay-button' ); ?></button>
					</span>
				</div>
			</div>
		</section>
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
