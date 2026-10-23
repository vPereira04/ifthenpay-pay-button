<?php
/**
 * Plugin bootstrap.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsController;
use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\DashboardWidget;
use Ifthenpay\PayButton\Admin\Entries\EntriesController;
use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\GatewaySettingsPage;
use Ifthenpay\PayButton\Admin\Menu;
use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Api\ReturnHandler;
use Ifthenpay\PayButton\Api\Webhook\WebhookController;
use Ifthenpay\PayButton\Api\Webhook\WebhookRegistrar;
use Ifthenpay\PayButton\Bridge\InvoiceHook;
use Ifthenpay\PayButton\Bridge\OutgoingWebhook;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\RestController;
use Ifthenpay\PayButton\Cron\ExpiredPaymentsCron;
use Ifthenpay\PayButton\Cron\ReminderCron;
use Ifthenpay\PayButton\Cron\WeeklySummaryCron;
use Ifthenpay\PayButton\Frontend\Block;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\PayPage;
use Ifthenpay\PayButton\Frontend\Shortcode;
use Ifthenpay\PayButton\Mail\ReceiptEmail;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires every piece together once, on `plugins_loaded`.
 */
class Plugin {

	public const METHOD_LOGOS_HANDLE  = 'iftp-btn-method-logos';
	public const ADMIN_STYLE_HANDLE   = 'iftp-btn-admin';
	public const UI_SCRIPT_HANDLE     = 'iftp-btn-ui';
	public const CHARTS_SCRIPT_HANDLE = 'iftp-btn-charts';

	/**
	 * The singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * The singleton instance, created on first access.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Singleton — use instance() instead.
	 */
	private function __construct() {
	}

	/**
	 * Registers every hook, AJAX action and admin screen.
	 */
	public function boot(): void {
		// Activation creates the table too; this catches schema upgrades on update.
		TransactionStore::maybe_create_table();

		add_action( 'init', array( ButtonRepository::class, 'register_post_type' ) );

		$buttons      = new ButtonRepository();
		$settings     = new SettingsRepository();
		$transactions = new TransactionStore();
		$renderer     = new ButtonRenderer( $settings );

		// ifthenpay calls back and sends the payer back on the frontend, and payers use the buttons there, so these are always on.
		( new WebhookController( $transactions ) )->register();
		( new ReturnHandler( $transactions ) )->register();
		( new RestController( $buttons, $transactions ) )->register();
		( new PayPage( $buttons, $renderer ) )->register();
		( new Shortcode( $buttons, $renderer ) )->register();
		( new Block( $buttons, $renderer ) )->register();
		( new OutgoingWebhook( $buttons, $transactions ) )->register();
		( new InvoiceHook( $buttons, $transactions ) )->register();
		( new ReceiptEmail( $transactions ) )->register();
		( new Privacy( $transactions ) )->register();
		$renderer->register();

		if ( is_admin() ) {
			$entries = new EntriesPage( $transactions, $settings );

			( new Menu( $settings, new ButtonsPage( $buttons, $settings, $transactions ), $entries, new GatewaySettingsPage( $settings ), new CheckoutSettingsPage( new CheckoutSettings() ) ) )->register();
			( new SettingsController( $settings ) )->register();
			( new EntriesController( $entries, null, $settings ) )->register();
			( new ButtonsController( $buttons ) )->register();
			( new DashboardWidget( $settings ) )->register();

			add_filter( 'plugin_action_links_' . plugin_basename( IFTP_BTN_FILE ), array( $this, 'action_links' ) );
		}

		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			( new WebhookRegistrar() )->register();
			( new ExpiredPaymentsCron() )->register();
			( new ReminderCron() )->register();
			( new WeeklySummaryCron() )->register();
		}

		// On init, so the checkout on the front end gets the logo swap too.
		add_action( 'init', array( $this, 'register_shared_assets' ) );
	}

	/**
	 * Registers every rewrite rule this plugin adds, then flushes. For activation and a changed pay link base.
	 */
	public static function flush_rewrites(): void {
		WebhookController::add_rewrite_rule();
		( new PayPage( new ButtonRepository(), new ButtonRenderer() ) )->add_rewrite_rule();
		flush_rewrite_rules();
	}

	/**
	 * Adds "Buttons" and "Settings" to the plugin's row on the Plugins screen.
	 *
	 * @param array<string, string> $links The row's links.
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		return array(
			'buttons'  => '<a href="' . esc_url( ButtonsPage::list_url() ) . '">' . esc_html__( 'Buttons', 'ifthenpay-pay-button' ) . '</a>',
			'settings' => '<a href="' . esc_url( GatewaySettingsPage::url() ) . '">' . esc_html__( 'Settings', 'ifthenpay-pay-button' ) . '</a>',
		) + $links;
	}

	/**
	 * The capability every screen and endpoint here checks. No host plugin, so it's core's.
	 */
	public static function admin_cap(): string {
		return 'manage_options';
	}

	/**
	 * The ?ver= for one of our assets. While debugging I use its mtime, so edits show without a version bump.
	 *
	 * @param string $relative Path from the plugin root, e.g. `assets/js/settings.js`.
	 */
	public static function asset_version( string $relative ): string {
		$debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$path  = IFTP_BTN_PATH . $relative;

		return $debug && file_exists( $path ) ? (string) filemtime( $path ) : IFTP_BTN_VERSION;
	}

	/**
	 * Registers what the screens share: the dark-background logo swap (the checkout uses it too), the stylesheet, the dropdowns and the charts.
	 */
	public function register_shared_assets(): void {
		$logos_js  = 'assets/js/method-logos.js';
		$admin_css = 'assets/css/admin.css';
		$ui_js     = 'assets/js/ui.js';
		$charts_js = 'assets/js/charts.js';

		wp_register_script( self::METHOD_LOGOS_HANDLE, IFTP_BTN_URL . $logos_js, array(), self::asset_version( $logos_js ), true );
		wp_register_style( self::ADMIN_STYLE_HANDLE, IFTP_BTN_URL . $admin_css, array(), self::asset_version( $admin_css ) );
		wp_register_script( self::UI_SCRIPT_HANDLE, IFTP_BTN_URL . $ui_js, array(), self::asset_version( $ui_js ), true );
		wp_register_script( self::CHARTS_SCRIPT_HANDLE, IFTP_BTN_URL . $charts_js, array(), self::asset_version( $charts_js ), true );
	}
}
