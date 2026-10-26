<?php
/**
 * Plugin Name: ifthenpay | Pay Button
 * Plugin URI: https://ifthenpay.com
 * Description: Take ifthenpay payments anywhere: pay buttons for any page, shareable payment links, and a link builder for plugins ifthenpay has no integration with.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: ifthenpay
 * Author URI: https://ifthenpay.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ifthenpay-pay-button
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IFTP_BTN_VERSION', '1.0.0' );
// The table follows the plugin version, so each update runs dbDelta once; its upgrade steps are safe to repeat.
define( 'IFTP_BTN_DB_VERSION', IFTP_BTN_VERSION );
define( 'IFTP_BTN_FILE', __FILE__ );
define( 'IFTP_BTN_PATH', plugin_dir_path( __FILE__ ) );
define( 'IFTP_BTN_URL', plugin_dir_url( __FILE__ ) );

$iftp_btn_autoload = IFTP_BTN_PATH . 'vendor/autoload.php';

if ( file_exists( $iftp_btn_autoload ) ) {
	require_once $iftp_btn_autoload;
}

require_once IFTP_BTN_PATH . 'functions.php';

// A closure, so I don't add a global function.
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( \Ifthenpay\PayButton\Plugin::class ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					wp_admin_notice( esc_html__( 'ifthenpay | Pay Button could not load its classes. Run "composer install" in the plugin folder.', 'ifthenpay-pay-button' ), array( 'type' => 'error' ) );
				},
				10,
				0
			);

			return;
		}

		\Ifthenpay\PayButton\Plugin::instance()->boot();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( ! class_exists( \Ifthenpay\PayButton\Plugin::class ) ) {
			return;
		}

		\Ifthenpay\PayButton\Repository\TransactionStore::maybe_create_table();
		\Ifthenpay\PayButton\Cron\ExpiredPaymentsCron::schedule();
		\Ifthenpay\PayButton\Cron\WeeklySummaryCron::schedule();

		// So the webhook and the pay links resolve right away instead of 404ing until the next flush.
		\Ifthenpay\PayButton\Plugin::flush_rewrites();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		if ( class_exists( \Ifthenpay\PayButton\Plugin::class ) ) {
			\Ifthenpay\PayButton\Cron\ExpiredPaymentsCron::unschedule();
			\Ifthenpay\PayButton\Cron\WeeklySummaryCron::unschedule();
		}

		flush_rewrite_rules();
	}
);
