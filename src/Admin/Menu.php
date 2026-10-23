<?php
/**
 * The "Pay Button" admin menu.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One top-level "Pay Button" menu with the ifthenpay glyph. Buttons first (it explains what to do before
 * connecting), then Payments once connected, then the two settings screens.
 */
class Menu {

	public const SLUG = 'iftp-btn';

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * The Buttons screen.
	 *
	 * @var ButtonsPage
	 */
	private ButtonsPage $buttons_page;

	/**
	 * The Payments screen.
	 *
	 * @var EntriesPage
	 */
	private EntriesPage $entries_page;

	/**
	 * The Gateway Configuration screen.
	 *
	 * @var GatewaySettingsPage
	 */
	private GatewaySettingsPage $settings_page;

	/**
	 * The Checkout screen.
	 *
	 * @var CheckoutSettingsPage
	 */
	private CheckoutSettingsPage $checkout_page;

	/**
	 * Sets up the menu.
	 *
	 * @param SettingsRepository   $settings      Settings storage.
	 * @param ButtonsPage          $buttons_page  The Buttons screen.
	 * @param EntriesPage          $entries_page  The Payments screen.
	 * @param GatewaySettingsPage  $settings_page The Gateway Configuration screen.
	 * @param CheckoutSettingsPage $checkout_page The Checkout screen.
	 */
	public function __construct( SettingsRepository $settings, ButtonsPage $buttons_page, EntriesPage $entries_page, GatewaySettingsPage $settings_page, CheckoutSettingsPage $checkout_page ) {
		$this->settings      = $settings;
		$this->buttons_page  = $buttons_page;
		$this->entries_page  = $entries_page;
		$this->settings_page = $settings_page;
		$this->checkout_page = $checkout_page;
	}

	/**
	 * Registers the menu, the menu icon and each screen's own hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_icon' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );

		$this->buttons_page->register();
		$this->settings_page->register();
		$this->entries_page->register();
		$this->checkout_page->register();
	}

	/**
	 * Adds the top-level menu and its submenus. Payments only exists once a Backoffice Key is connected, so WP refuses its URL before that.
	 */
	public function add_pages(): void {
		$buttons_hook = add_menu_page(
			__( 'Pay buttons', 'ifthenpay-pay-button' ),
			__( 'Pay Button', 'ifthenpay-pay-button' ),
			Plugin::admin_cap(),
			self::SLUG,
			array( $this->buttons_page, 'render' ),
			// 'none' leaves the icon slot empty for the font glyph in menu-icon.css.
			'none',
			58
		);

		add_submenu_page( self::SLUG, __( 'Pay buttons', 'ifthenpay-pay-button' ), __( 'Buttons', 'ifthenpay-pay-button' ), Plugin::admin_cap(), self::SLUG, array( $this->buttons_page, 'render' ) );

		$this->buttons_page->set_hook( $buttons_hook );

		if ( $this->settings->is_connected() ) {
			$this->entries_page->add_page( self::SLUG );
		}

		$settings_hook = add_submenu_page(
			self::SLUG,
			__( 'Gateway Configuration', 'ifthenpay-pay-button' ),
			__( 'Gateway Configuration', 'ifthenpay-pay-button' ),
			Plugin::admin_cap(),
			GatewaySettingsPage::PAGE_SLUG,
			array( $this->settings_page, 'render' )
		);

		if ( is_string( $settings_hook ) ) {
			$this->settings_page->set_hook( $settings_hook );
		}

		$this->checkout_page->add_page( self::SLUG );
	}

	/**
	 * Marks our screens on <body>, so admin.css can paint the whole page (canvas, dialogs, toast) without leaking elsewhere.
	 *
	 * @param string $classes Space-separated body classes.
	 */
	public function body_class( string $classes ): string {
		$screen = get_current_screen();

		if ( null !== $screen && false !== strpos( $screen->id, 'page_' . self::SLUG ) ) {
			$classes .= ' iftp-btn-screen iftp-btn-mode-' . $this->settings->get_display_mode();
		}

		return $classes;
	}

	/**
	 * The ifthenpay glyph for the menu, on every admin page since the menu shows on all of them.
	 */
	public function enqueue_menu_icon(): void {
		$css = 'assets/css/menu-icon.css';

		wp_enqueue_style( 'iftp-btn-menu-icon', IFTP_BTN_URL . $css, array(), Plugin::asset_version( $css ) );
	}
}
