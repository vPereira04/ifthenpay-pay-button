<?php
/**
 * The PDF receipt for a paid payment.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Pdf;

use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One A4 receipt: the site, the amount, and every detail the payment has. It runs onto more pages when there are many.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
final class ReceiptPdf {

	private const MARGIN  = 48.0;
	private const LABEL_W = 140.0;
	private const BOTTOM  = 790.0;
	private const TOP     = 64.0;

	private const BRAND = array( 0, 96, 156 );
	private const INK   = array( 18, 26, 44 );
	private const MUTED = array( 102, 112, 138 );
	private const RULE  = array( 224, 228, 238 );

	/**
	 * Static-only helper.
	 */
	private function __construct() {
	}

	/**
	 * Whether a payment can have a receipt: only a paid one.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function available( array $record ): bool {
		return TransactionStore::STATUS_PAID === $record['status'];
	}

	/**
	 * The file name a receipt downloads as.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function filename( array $record ): string {
		return 'receipt-' . sanitize_file_name( $record['ref'] ) . '.pdf';
	}

	/**
	 * Builds the receipt, in the site's language.
	 *
	 * @param array<string, mixed>                            $record The paid payment.
	 * @param array<int, array{label: string, value: string}> $pairs  Its details.
	 * @phpstan-param Transaction $record
	 * @return string The PDF's bytes.
	 */
	public static function build( array $record, array $pairs ): string {
		$switched = switch_to_locale( get_locale() );
		$site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$when     = Dates::local( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $record['updated_at'] );
		$doc      = new PdfDocument();
		$right    = PdfDocument::WIDTH - self::MARGIN;

		$doc->add_page();
		$doc->rect( 0, 0, PdfDocument::WIDTH, 92, self::BRAND );
		$doc->text( self::MARGIN, 56, $site, 18, true, array( 255, 255, 255 ) );
		$doc->text( $right, 56, __( 'Receipt', 'ifthenpay-pay-button' ), 13, false, array( 255, 255, 255 ), true );

		$doc->text( self::MARGIN, 150, __( 'Payment received', 'ifthenpay-pay-button' ), 13, true, self::MUTED );
		$doc->text( self::MARGIN, 196, CheckoutService::format_money( $record['amount'] ), 36, true, self::INK );
		$doc->text( self::MARGIN, 220, $when, 11, false, self::MUTED );

		$rows = array_merge(
			array(
				array( __( 'Reference', 'ifthenpay-pay-button' ), $record['ref'] ),
				array( __( 'Method', 'ifthenpay-pay-button' ), $record['pay_method'] ),
				array( __( 'Date', 'ifthenpay-pay-button' ), $when ),
			),
			'' !== $record['external_ref'] ? array( array( __( 'Your reference', 'ifthenpay-pay-button' ), $record['external_ref'] ) ) : array(),
			array_map( static fn( array $pair ): array => array( $pair['label'], $pair['value'] ), $pairs )
		);

		/**
		 * Where the next row starts: down with every row, back up on a new page.
		 *
		 * @var float $y
		 */
		$y       = 262.0;
		$value_x = self::MARGIN + self::LABEL_W;
		$value_w = $right - $value_x;

		foreach ( $rows as $row ) {
			if ( '' === $row[1] ) {
				continue;
			}

			$value_lines = $doc->wrap( $row[1], 11, $value_w );
			$label_lines = $doc->wrap( $row[0], 10, self::LABEL_W - 12 );
			$height      = max( count( $value_lines ) * 15, count( $label_lines ) * 14 ) + 14;

			// A row that doesn't fit goes on a new page, whose first row starts under the top margin.
			if ( $y + $height > self::BOTTOM ) {
				$doc->add_page();
				$y = self::TOP;
			}

			$doc->rule( self::MARGIN, $right, $y, self::RULE );

			foreach ( $label_lines as $index => $line ) {
				$doc->text( self::MARGIN, $y + 19 + $index * 14, $line, 10, false, self::MUTED );
			}

			foreach ( $value_lines as $index => $line ) {
				$doc->text( $value_x, $y + 19 + $index * 15, $line, 11, false, self::INK );
			}

			$y += $height;
		}

		$doc->rule( self::MARGIN, $right, $y, self::RULE );
		$doc->text( self::MARGIN, min( max( $y + 40, 60.0 ), 815.0 ), __( 'Payment processed securely by ifthenpay.', 'ifthenpay-pay-button' ), 9, false, self::MUTED );
		$doc->text( $right, min( max( $y + 40, 60.0 ), 815.0 ), (string) preg_replace( '#^https?://#', '', home_url( '/' ) ), 9, false, self::MUTED, true );

		$bytes = $doc->output( sprintf( /* translators: %s: the payment's reference */ __( 'Receipt %s', 'ifthenpay-pay-button' ), $record['ref'] ) );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $bytes;
	}
}
