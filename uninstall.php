<?php
/**
 * Uninstall routine — removes every option, transient, cron hook and the custom transactions table.
 *
 * @package Ifthenpay\PayButton
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$iftp_btn_autoload = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $iftp_btn_autoload ) ) {
	require_once $iftp_btn_autoload;
}

// Everything here lives per site (options, transients, cron, the prefixed table), so on a network I run it on each one.
$iftp_btn_uninstall_site = static function (): void {
	global $wpdb;

	if ( class_exists( \Ifthenpay\PayButton\Repository\SettingsRepository::class ) ) {
		$settings = new \Ifthenpay\PayButton\Repository\SettingsRepository();
		$settings->delete_all();
		$settings->delete_anti_phishing_keys();
		$settings->delete_payment_email();
		$settings->delete_summary_settings();
	}

	// The sealed AI connection, under the name only this site's secret can work out.
	if ( class_exists( \Ifthenpay\PayButton\Ai\Vault::class ) ) {
		delete_option( \Ifthenpay\PayButton\Ai\Vault::name( 'connection' ) );
	}

	// Every transient with a dynamic name: AI chats, rate limits, activation cooldowns, checkout opens.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of transients with dynamic names; no core API lists them.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_iftp_btn_' ) . '%', $wpdb->esc_like( '_transient_timeout_iftp_btn_' ) . '%' ) );

	if ( class_exists( \Ifthenpay\PayButton\Repository\CheckoutSettings::class ) ) {
		( new \Ifthenpay\PayButton\Repository\CheckoutSettings() )->delete_all();
	}

	// The buttons are mine alone, so they go too; payments history lives in the table below.
	foreach ( get_posts(
		array(
			'post_type'   => 'iftp_btn_button',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $iftp_btn_button_id ) {
		wp_delete_post( (int) $iftp_btn_button_id, true );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time DDL cleanup on uninstall; custom plugin table with no WP Core API.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'iftp_btn_transactions' ) );

	delete_option( 'iftp_btn_db_version' );
	delete_transient( 'iftp_btn_callback_resync_lock' );
	delete_transient( 'iftp_btn_backfill_lock' );
	delete_transient( 'iftp_btn_methods_catalog' );
	delete_transient( 'iftp_btn_gateway_rows' );
	wp_clear_scheduled_hook( 'iftp_btn_expire_payments' );
	wp_clear_scheduled_hook( 'iftp_btn_weekly_summary' );
	// Retries carry arguments, which wp_clear_scheduled_hook() only matches one set at a time.
	wp_unschedule_hook( 'iftp_btn_retry_webhook' );
	wp_unschedule_hook( 'iftp_btn_retry_invoice_hook' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $iftp_btn_blog_id ) {
		switch_to_blog( (int) $iftp_btn_blog_id );
		$iftp_btn_uninstall_site();
		restore_current_blog();
	}
} else {
	$iftp_btn_uninstall_site();
}

// Per-user Entries choices, for every user. Usermeta is network-wide, so once is enough.
delete_metadata( 'user', 0, 'iftp_btn_entries_hidden_cols', '', true );
delete_metadata( 'user', 0, 'iftp_btn_entries_col_order', '', true );
delete_metadata( 'user', 0, 'iftp_btn_entries_per_page', '', true );
