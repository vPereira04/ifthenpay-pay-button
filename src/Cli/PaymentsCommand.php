<?php
/**
 * WP-CLI: list, read and export payments, and write a paid payment's receipt.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Cli;

use Ifthenpay\PayButton\Pdf\ReceiptPdf;
use Ifthenpay\PayButton\Repository\TransactionStore;
use WP_CLI;
use WP_CLI\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the payments table the way the Payments screen does.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class PaymentsCommand {

	private const FIELDS = array( 'ref', 'status', 'amount', 'pay_method', 'customer_name', 'customer_email', 'button_id', 'external_ref', 'created_at' );

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the command.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?TransactionStore $transactions = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Lists payments, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : pending, paid, failed (cancelled too), cancelled or expired.
	 *
	 * [--button=<id>]
	 * : Only this button's payments.
	 *
	 * [--method=<method>]
	 * : Only this payment method.
	 *
	 * [--search=<text>]
	 * : Reference, your reference, method, name or email contains this.
	 *
	 * [--from=<date>]
	 * : Created on or after this day (YYYY-MM-DD, the site's time zone).
	 *
	 * [--to=<date>]
	 * : Created on or before this day.
	 *
	 * [--limit=<n>]
	 * : The most to list. Default 50; 0 for all.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated columns. Default: ref,status,amount,pay_method,customer_name,customer_email,created_at.
	 *
	 * [--format=<format>]
	 * : table, csv, json, yaml, count or ids. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp iftp-btn payments list --status=paid --from=2026-11-01
	 *     wp iftp-btn payments list --status=pending --format=ids
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function list( array $args, array $assoc_args ): void {
		unset( $args );

		$limit  = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 50;
		$rows   = array();
		$filter = $this->filters( $assoc_args );

		foreach ( $this->transactions->iterate( $filter ) as $record ) {
			$rows[] = $this->row( $record );

			if ( $limit > 0 && count( $rows ) >= $limit ) {
				break;
			}
		}

		$format = $assoc_args['format'] ?? 'table';

		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', array_column( $rows, 'ref' ) ) );

			return;
		}

		Utils\format_items( $format, $rows, isset( $assoc_args['fields'] ) ? $assoc_args['fields'] : 'ref,status,amount,pay_method,customer_name,customer_email,created_at' );
	}

	/**
	 * Shows one payment with all its details.
	 *
	 * ## OPTIONS
	 *
	 * <ref>
	 * : The payment's reference.
	 *
	 * [--format=<format>]
	 * : table, json or yaml. Default table.
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function get( array $args, array $assoc_args ): void {
		$record = $this->transactions->get( $args[0] );

		if ( null === $record ) {
			WP_CLI::error( 'No payment with that reference.' );
		}

		$data = $this->row( $record ) + array(
			'transaction_id' => $record['transaction_id'],
			'payment_url'    => $record['payment_url'],
			'source_url'     => $record['source_url'],
			'updated_at'     => gmdate( 'Y-m-d H:i:s', $record['updated_at'] ),
		);

		foreach ( $this->transactions->entry_data_pairs( $record ) as $pair ) {
			$data[ $pair['label'] ] = $pair['value'];
		}

		Utils\format_items( 'json' === ( $assoc_args['format'] ?? '' ) || 'yaml' === ( $assoc_args['format'] ?? '' ) ? $assoc_args['format'] : 'table', array( $data ), array_keys( $data ) );
	}

	/**
	 * Exports payments to a file or to the screen.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : As in list.
	 *
	 * [--button=<id>]
	 * : As in list.
	 *
	 * [--method=<method>]
	 * : As in list.
	 *
	 * [--search=<text>]
	 * : As in list.
	 *
	 * [--from=<date>]
	 * : As in list.
	 *
	 * [--to=<date>]
	 * : As in list.
	 *
	 * [--format=<format>]
	 * : csv or json. Default csv.
	 *
	 * [--file=<path>]
	 * : Write here instead of the screen.
	 *
	 * ## EXAMPLES
	 *
	 *     wp iftp-btn payments export --status=paid --from=2026-01-01 --file=paid-2026.csv
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function export( array $args, array $assoc_args ): void {
		unset( $args );

		$format = 'json' === ( $assoc_args['format'] ?? '' ) ? 'json' : 'csv';
		$target = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : 'php://stdout';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a CLI export to the file the admin names, or to stdout.
		$out = fopen( $target, 'w' );

		if ( false === $out ) {
			WP_CLI::error( 'Could not open ' . $target . ' for writing.' );
		}

		$count = 0;

		if ( 'csv' === $format ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see fopen() above.
			fwrite( $out, "\xEF\xBB\xBF" );
			fputcsv( $out, self::FIELDS, ',', '"', '\\' );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see fopen() above.
			fwrite( $out, "[\n" );
		}

		foreach ( $this->transactions->iterate( $this->filters( $assoc_args ) ) as $record ) {
			$row = $this->row( $record );

			if ( 'csv' === $format ) {
				// A cell that starts like a formula would run in Excel, so it gets a quote in front, as in the admin export.
				fputcsv( $out, array_map( static fn( string $cell ): string => 1 === preg_match( '/^[=+\-@\t\r]/', $cell ) ? "'" . $cell : $cell, array_map( 'strval', array_values( $row ) ) ), ',', '"', '\\' );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see fopen() above.
				fwrite( $out, ( $count > 0 ? ",\n" : '' ) . wp_json_encode( $row ) );
			}

			++$count;
		}

		if ( 'json' === $format ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see fopen() above.
			fwrite( $out, "\n]\n" );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen() above.
		fclose( $out );

		if ( 'php://stdout' !== $target ) {
			WP_CLI::success( sprintf( 'Exported %d payments to %s.', $count, $target ) );
		}
	}

	/**
	 * Writes a paid payment's receipt as a PDF.
	 *
	 * ## OPTIONS
	 *
	 * <ref>
	 * : The payment's reference.
	 *
	 * [--file=<path>]
	 * : Where to write it. Default: receipt-<ref>.pdf in the current folder.
	 *
	 * ## EXAMPLES
	 *
	 *     wp iftp-btn payments receipt IFTP_ABC123 --file=/tmp/receipt.pdf
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function receipt( array $args, array $assoc_args ): void {
		$record = $this->transactions->get( $args[0] );

		if ( null === $record ) {
			WP_CLI::error( 'No payment with that reference.' );
		}

		if ( ! ReceiptPdf::available( $record ) ) {
			WP_CLI::error( 'Only a paid payment has a receipt.' );
		}

		$file = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : ReceiptPdf::filename( $record );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a CLI command writing to the path the admin names.
		if ( false === file_put_contents( $file, ReceiptPdf::build( $record, $this->transactions->entry_data_pairs( $record ) ) ) ) {
			WP_CLI::error( 'Could not write ' . $file . '.' );
		}

		WP_CLI::success( 'Wrote ' . $file . '.' );
	}

	/**
	 * The list's filters from the command's flags.
	 *
	 * @param array<string, string> $assoc_args Flags.
	 * @return array<string, mixed>
	 */
	private function filters( array $assoc_args ): array {
		$filter = array();

		foreach ( array(
			'status' => 'status',
			'method' => 'pay_method',
			'search' => 'search',
		) as $flag => $key ) {
			if ( isset( $assoc_args[ $flag ] ) && '' !== $assoc_args[ $flag ] ) {
				$filter[ $key ] = (string) $assoc_args[ $flag ];
			}
		}

		if ( isset( $assoc_args['status'] ) && ! in_array( $assoc_args['status'], TransactionStore::ALL_STATUSES, true ) ) {
			WP_CLI::error( 'Unknown status. Use one of: ' . implode( ', ', TransactionStore::ALL_STATUSES ) . '.' );
		}

		if ( isset( $assoc_args['button'] ) ) {
			$filter['button_id'] = absint( $assoc_args['button'] );
		}

		foreach ( array(
			'from' => array( 'date_from', '00:00:00' ),
			'to'   => array( 'date_to', '23:59:59' ),
		) as $flag => $spec ) {
			if ( ! isset( $assoc_args[ $flag ] ) ) {
				continue;
			}

			$date = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $assoc_args[ $flag ] ) ? date_create_immutable( $assoc_args[ $flag ] . ' ' . $spec[1], wp_timezone() ) : false;

			if ( false === $date ) {
				WP_CLI::error( '--' . $flag . ' must look like 2026-11-25.' );
			}

			$filter[ $spec[0] ] = $date->getTimestamp();
		}

		return $filter;
	}

	/**
	 * One payment as the columns the list shows.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 * @return array<string, string>
	 */
	private function row( array $record ): array {
		return array(
			'ref'            => $record['ref'],
			'status'         => $record['status'],
			'amount'         => number_format( $record['amount'], 2, '.', '' ),
			'pay_method'     => $record['pay_method'],
			'customer_name'  => $record['customer_name'],
			'customer_email' => $record['customer_email'],
			'button_id'      => (string) $record['button_id'],
			'external_ref'   => $record['external_ref'],
			'created_at'     => gmdate( 'Y-m-d H:i:s', $record['created_at'] ),
		);
	}
}
