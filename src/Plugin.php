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
use Ifthenpay\PayButton\Admin\Buttons\DemoPage;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\ConfigurationPage;
use Ifthenpay\PayButton\Admin\DashboardWidget;
use Ifthenpay\PayButton\Admin\Entries\EntriesController;
use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\Menu;
use Ifthenpay\PayButton\Ai\AiController;
use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Api\ReturnHandler;
use Ifthenpay\PayButton\Api\Webhook\WebhookController;
use Ifthenpay\PayButton\Api\Webhook\WebhookRegistrar;
use Ifthenpay\PayButton\Bridge\InvoiceHook;
use Ifthenpay\PayButton\Bridge\OutgoingWebhook;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\RestController;
use Ifthenpay\PayButton\Cron\ExpiredPaymentsCron;
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
	public const VIDEO_LOOP_HANDLE    = 'iftp-btn-video-loop';
	public const OPEN_FX_HANDLE       = 'iftp-btn-open-fx';
	public const ADMIN_STYLE_HANDLE   = 'iftp-btn-admin';
	public const UI_SCRIPT_HANDLE     = 'iftp-btn-ui';
	public const SCHEME_HANDLE        = 'iftp-btn-scheme';
	public const CHARTS_SCRIPT_HANDLE = 'iftp-btn-charts';

	// The Buttons and Checkout screens' script: its parts in load order, and the handle of the last one.
	public const BUTTONS_SCRIPT_PARTS = array( 'core', 'design', 'spectrum', 'form-builder', 'preview', 'look', 'dialogs', 'code', 'list', 'editor', 'boot' );
	public const BUTTONS_SCRIPT_LAST  = 'iftp-btn-buttons-boot';

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

		add_filter( 'user_has_cap', array( self::class, 'grant_admin_cap' ) );
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

			( new Menu( $settings, new ButtonsPage( $buttons, $settings, $transactions ), $entries, new ConfigurationPage( $settings ), new CheckoutSettingsPage( new CheckoutSettings() ) ) )->register();
			( new SettingsController( $settings ) )->register();
			( new EntriesController( $entries, null, $settings ) )->register();
			( new ButtonsController( $buttons ) )->register();
			( new DemoPage( $buttons ) )->register();
			( new AiController( $buttons ) )->register();
			( new DashboardWidget( $settings ) )->register();

			add_filter( 'plugin_action_links_' . plugin_basename( IFTP_BTN_FILE ), array( $this, 'action_links' ) );
		}

		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			( new WebhookRegistrar() )->register();
			( new ExpiredPaymentsCron() )->register();
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
			'settings' => '<a href="' . esc_url( ConfigurationPage::url() ) . '">' . esc_html__( 'Settings', 'ifthenpay-pay-button' ) . '</a>',
		) + $links;
	}

	/**
	 * The capability every screen and endpoint here checks. My own, so a site can hand out just these screens.
	 */
	public static function admin_cap(): string {
		return 'iftp_btn_manage';
	}

	/**
	 * Gives the capability to anyone who manages options, so admins keep access without a role change.
	 *
	 * @param array<string, bool> $allcaps The user's capabilities.
	 * @return array<string, bool>
	 */
	public static function grant_admin_cap( array $allcaps ): array {
		if ( ! empty( $allcaps['manage_options'] ) ) {
			$allcaps[ self::admin_cap() ] = true;
		}

		return $allcaps;
	}

	/**
	 * The ?ver= for one of our assets. While debugging I use its mtime, so edits show without a version bump.
	 *
	 * @param string $relative Path from the plugin root, e.g. `assets/js/settings.js`.
	 */
	public static function asset_version( string $relative ): string {
		$debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$path  = IFTP_BTN_PATH . $relative;

		if ( $debug ) {
			return file_exists( $path ) ? (string) filemtime( $path ) : IFTP_BTN_VERSION;
		}

		// Otherwise the file's own hash from the build manifest, so an edited asset gets a new URL and a browser never
		// keeps showing the old one under the same plugin version.
		$hashes = self::asset_hashes();
		$source = preg_replace( '/\.min\.(css|js)$/', '.$1', $relative );
		$hash   = null !== $source && isset( $hashes[ $source ] ) ? $hashes[ $source ] : '';

		return '' !== $hash ? IFTP_BTN_VERSION . '-' . substr( $hash, 0, 8 ) : IFTP_BTN_VERSION;
	}

	/**
	 * The hash of every source asset, from the build manifest ./build-assets.sh writes.
	 *
	 * @return array<string, string>
	 */
	private static function asset_hashes(): array {
		static $hashes = null;

		if ( null === $hashes ) {
			$hashes = array();
			$file   = IFTP_BTN_PATH . 'assets/build-manifest.json';
			$json   = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in the plugin, not a remote request.

			if ( is_array( $json ) ) {
				foreach ( $json as $name => $hash ) {
					if ( is_string( $name ) && is_string( $hash ) ) {
						$hashes[ $name ] = $hash;
					}
				}
			}
		}

		return $hashes;
	}

	/**
	 * The path to load for an asset: its `.min` build unless debugging, when the readable source is used.
	 *
	 * @param string $relative Path from the plugin root, e.g. `assets/js/checkout.js`.
	 */
	public static function asset_path( string $relative ): string {
		if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return $relative;
		}

		$min = preg_replace( '/\.(css|js)$/', '.min.$1', $relative );

		return null !== $min && file_exists( IFTP_BTN_PATH . $min ) ? $min : $relative;
	}

	/**
	 * The URL to load for an asset: its `.min` build unless debugging.
	 *
	 * @param string $relative Path from the plugin root, e.g. `assets/js/settings.js`.
	 */
	public static function asset_url( string $relative ): string {
		return IFTP_BTN_URL . self::asset_path( $relative );
	}

	/**
	 * Registers a stylesheet that lives as several files in a folder, in the order they were written.
	 *
	 * Each part depends on the one before, and the last one takes $handle, so whatever is attached to $handle
	 * (inline CSS, a dependency) still lands after the whole sheet, as it did when this was one file.
	 *
	 * @param string   $handle Handle of the whole sheet.
	 * @param string   $folder Folder of the parts, from the plugin root, e.g. `assets/css/admin`.
	 * @param string[] $parts  File names without `.css`, in cascade order.
	 * @param string[] $deps   Handles the first part depends on.
	 */
	public static function register_split_style( string $handle, string $folder, array $parts, array $deps = array() ): void {
		$last = count( $parts ) - 1;

		foreach ( $parts as $i => $part ) {
			$relative = self::asset_path( $folder . '/' . $part . '.css' );
			$part_id  = $i === $last ? $handle : $handle . '-' . $part;

			wp_register_style( $part_id, IFTP_BTN_URL . $relative, $deps, self::asset_version( $relative ) );

			$deps = array( $part_id );
		}
	}

	/**
	 * Enqueues a script that lives as several files in a folder, loaded one after the other in the order given.
	 *
	 * The first part takes $handle, so data localized to $handle prints before any of it. Each next part depends
	 * on the one before, and they share one namespace object (see the parts' own headers).
	 *
	 * @param string   $handle Handle of the first part.
	 * @param string   $folder Folder of the parts, from the plugin root, e.g. `assets/js/buttons`.
	 * @param string[] $parts  File names without `.js`, in load order.
	 * @param string[] $deps   Handles the first part depends on.
	 */
	public static function enqueue_split_script( string $handle, string $folder, array $parts, array $deps = array() ): void {
		foreach ( $parts as $i => $part ) {
			$relative = self::asset_path( $folder . '/' . $part . '.js' );
			$part_id  = 0 === $i ? $handle : $handle . '-' . $part;

			wp_enqueue_script( $part_id, IFTP_BTN_URL . $relative, $deps, self::asset_version( $relative ), true );

			$deps = array( $part_id );
		}
	}

	/**
	 * Registers what the screens share: the dark-background logo swap and the video loop (the checkout uses them
	 * too), the stylesheets, the dropdowns and the charts.
	 */
	public function register_shared_assets(): void {
		$logos_js  = 'assets/js/method-logos.js';
		$loop_js   = 'assets/js/video-loop.js';
		$fx_js     = 'assets/js/open-fx.js';
		$ui_js     = 'assets/js/ui.js';
		$scheme_js = 'assets/js/scheme.js';
		$charts_js = 'assets/js/charts.js';

		$logos_js  = self::asset_path( $logos_js );
		$loop_js   = self::asset_path( $loop_js );
		$fx_js     = self::asset_path( $fx_js );
		$ui_js     = self::asset_path( $ui_js );
		$scheme_js = self::asset_path( $scheme_js );
		$charts_js = self::asset_path( $charts_js );

		wp_register_script( self::METHOD_LOGOS_HANDLE, IFTP_BTN_URL . $logos_js, array(), self::asset_version( $logos_js ), true );
		wp_register_script( self::VIDEO_LOOP_HANDLE, IFTP_BTN_URL . $loop_js, array(), self::asset_version( $loop_js ), true );
		wp_register_script( self::OPEN_FX_HANDLE, IFTP_BTN_URL . $fx_js, array(), self::asset_version( $fx_js ), true );
		self::register_split_style(
			self::ADMIN_STYLE_HANDLE,
			'assets/css/admin',
			array( 'base', 'gateway', 'entries-toolbar', 'entries-table', 'entry-single', 'performance', 'new-payment', 'dashboard-and-controls', 'responsive', 'glass' )
		);
		self::register_split_style(
			'iftp-btn-buttons',
			'assets/css/buttons',
			array( 'list', 'editor', 'design', 'form-builder', 'checkout-settings', 'responsive', 'glass' ),
			array( self::ADMIN_STYLE_HANDLE )
		);
		// The preview colour mode is tiny and every screen with a preview needs it, so the shared UI script brings it along.
		wp_register_script( self::SCHEME_HANDLE, IFTP_BTN_URL . $scheme_js, array(), self::asset_version( $scheme_js ), true );
		wp_register_script( self::UI_SCRIPT_HANDLE, IFTP_BTN_URL . $ui_js, array( self::SCHEME_HANDLE ), self::asset_version( $ui_js ), true );
		wp_register_script( self::CHARTS_SCRIPT_HANDLE, IFTP_BTN_URL . $charts_js, array(), self::asset_version( $charts_js ), true );
	}
}
