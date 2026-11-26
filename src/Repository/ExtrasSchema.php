<?php
/**
 * The tables for discount codes and payment reminders.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three small tables, versioned on their own so a site that already has the payments table gets them without a plugin version change.
 */
final class ExtrasSchema {

	private const VERSION        = '1';
	private const VERSION_OPTION = 'iftp_btn_extras_db';

	/**
	 * Static-only helper.
	 */
	private function __construct() {
	}

	/**
	 * The discount codes table name.
	 */
	public static function coupons_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'iftp_btn_coupons';
	}

	/**
	 * The table that says which payment used which code.
	 */
	public static function uses_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'iftp_btn_coupon_uses';
	}

	/**
	 * The table of payments that were already reminded.
	 */
	public static function reminders_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'iftp_btn_reminders';
	}

	/**
	 * Creates the tables; a no-op once the stored version is current.
	 */
	public static function maybe_create(): void {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::VERSION, '>=' ) ) {
			return;
		}

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$coupons         = self::coupons_table();
		$uses            = self::uses_table();
		$reminders       = self::reminders_table();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$coupons} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(40) NOT NULL,
            type VARCHAR(10) NOT NULL DEFAULT 'percent',
            value DECIMAL(10,2) NOT NULL DEFAULT 0,
            min_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            max_uses INT UNSIGNED NOT NULL DEFAULT 0,
            button_ids VARCHAR(500) NOT NULL DEFAULT '',
            starts_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code)
        ) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$uses} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            coupon_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ref VARCHAR(191) NOT NULL,
            discount DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY ref (ref),
            KEY coupon_id (coupon_id)
        ) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$reminders} (
            ref VARCHAR(191) NOT NULL,
            sent_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (ref)
        ) {$charset_collate};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Drops the tables and the version, for uninstall.
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( array( self::coupons_table(), self::uses_table(), self::reminders_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time DDL cleanup on uninstall; the plugin's own tables.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		delete_option( self::VERSION_OPTION );
	}
}
