<?php
/**
 * Bootstrap for the integration suite: loads the site's WordPress without its plugins, then points the plugin at its own table.
 *
 * The prefix is swapped before anything touches the database, so the tests never see the real payments. The
 * DB-version option is read as 0 and never written, so the table is created here without touching the site's options.
 * Run it where WordPress and its database are reachable, e.g. `docker exec iftp-app` (see the plugin's composer scripts).
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

$iftp_wp_load = false !== getenv( 'IFTP_WP_LOAD' ) ? (string) getenv( 'IFTP_WP_LOAD' ) : '/var/www/html/wp-load.php';

if ( ! is_readable( $iftp_wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found at {$iftp_wp_load}. Set IFTP_WP_LOAD, or run inside the WordPress container.\n" );
	exit( 1 );
}

// A plugin folder that doesn't exist, so WordPress loads no plugins: the code under test is this checkout's, not
// whatever copy the site has installed. The plugin's own autoloader and constants come after WordPress is up.
define( 'WP_PLUGIN_DIR', '/nonexistent-plugins-for-tests' );

require_once $iftp_wp_load;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'IFTP_BTN_VERSION' ) ) {
	define( 'IFTP_BTN_VERSION', '0.0.0-test' );
	define( 'IFTP_BTN_DB_VERSION', IFTP_BTN_VERSION );
}

global $wpdb;

$wpdb->set_prefix( 'iftptest_' );

add_filter( 'pre_option_iftp_btn_db_version', static fn() => '0' );
add_filter( 'pre_update_option_iftp_btn_db_version', static fn( $new, $old ) => $old, 10, 2 );

\Ifthenpay\PayButton\Repository\TransactionStore::maybe_create_table();

// The tests' base class has no autoloader behind it, and PHPUnit loads the test files in alphabetical order.
require_once __DIR__ . '/Integration/TransactionTestCase.php';

register_shutdown_function(
	static function (): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- the test table, named by the plugin itself.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', \Ifthenpay\PayButton\Repository\TransactionStore::table_name() ) );
	}
);
