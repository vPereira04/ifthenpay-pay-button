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
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A till-style receipt: a narrow black-and-white strip in Courier, so it needs no colours to fit any site. It runs onto more pages when there are many details.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
final class ReceiptPdf {

	private const SIZE    = 11.0;
	private const COLS    = 38;
	private const LEADING = 16.5;
	private const TOP     = 64.0;
	private const BOTTOM  = 780.0;
	private const BAR_H   = 38.0;
	private const BLACK   = array( 0, 0, 0 );

	/**
	 * Code 39 patterns: nine elements per character, bar and space alternating, w = wide and n = narrow.
	 */
	private const CODE39 = array(
		'0' => 'nnnwwnwnn',
		'1' => 'wnnwnnnnw',
		'2' => 'nnwwnnnnw',
		'3' => 'wnwwnnnnn',
		'4' => 'nnnwwnnnw',
		'5' => 'wnnwwnnnn',
		'6' => 'nnwwwnnnn',
		'7' => 'nnnwnnwnw',
		'8' => 'wnnwnnwnn',
		'9' => 'nnwwnnwnn',
		'A' => 'wnnnnwnnw',
		'B' => 'nnwnnwnnw',
		'C' => 'wnwnnwnnn',
		'D' => 'nnnnwwnnw',
		'E' => 'wnnnwwnnn',
		'F' => 'nnwnwwnnn',
		'G' => 'nnnnnwwnw',
		'H' => 'wnnnnwwnn',
		'I' => 'nnwnnwwnn',
		'J' => 'nnnnwwwnn',
		'K' => 'wnnnnnnww',
		'L' => 'nnwnnnnww',
		'M' => 'wnwnnnnwn',
		'N' => 'nnnnwnnww',
		'O' => 'wnnnwnnwn',
		'P' => 'nnwnwnnwn',
		'Q' => 'nnnnnnwww',
		'R' => 'wnnnnnwwn',
		'S' => 'nnwnnnwwn',
		'T' => 'nnnnwnwwn',
		'U' => 'wwnnnnnnw',
		'V' => 'nwwnnnnnw',
		'W' => 'wwwnnnnnn',
		'X' => 'nwnnwnnnw',
		'Y' => 'wwnnwnnnn',
		'Z' => 'nwwnwnnnn',
		'-' => 'nwnnnnwnw',
		'.' => 'wwnnnnwnn',
		' ' => 'nwwnnnwnn',
		'*' => 'nwnnwnwnn',
	);

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
		return 'proof-of-payment-' . sanitize_file_name( $record['ref'] ) . '.pdf';
	}

	/**
	 * Builds the receipt, in the site's language.
	 *
	 * @param array<string, mixed>                                                                                                                              $record The paid payment.
	 * @param array<int, array{label: string, value: string}>                                                                                                   $pairs  Its details.
	 * @param array{logo: string, logo_id: int, name: string, details: string, title: string, message: string, barcode: bool, color: bool, accent: string}|null $look What the merchant set for their receipts; null reads the saved settings.
	 * @phpstan-param Transaction $record
	 * @return string The PDF's bytes.
	 */
	public static function build( array $record, array $pairs, ?array $look = null ): string {
		$look     = $look ?? ( new CheckoutSettings() )->get_receipt_look();
		$switched = switch_to_locale( get_locale() );
		$site     = '' !== trim( $look['name'] ) ? $look['name'] : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$when     = Dates::local( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $record['updated_at'] );
		$doc      = new PdfDocument();
		$char_w   = 600 * self::SIZE / 1000;
		$width    = self::COLS * $char_w;
		$left     = ( PdfDocument::WIDTH - $width ) / 2;
		$right    = $left + $width;
		$mid      = PdfDocument::WIDTH / 2;
		$dashes   = substr( str_repeat( '- ', self::COLS ), 0, self::COLS );
		$y        = self::TOP;
		$accent   = $look['color'] ? self::rgb( $look['accent'] ) : self::BLACK;

		$doc->add_page();

		// In colour, a band across the top; in black and white, nothing.
		if ( $look['color'] ) {
			$doc->rect( 0, 0, PdfDocument::WIDTH, 8, $accent );
		}

		// The logo, if they chose one, then the shop's name large and centred: with a logo, only a name they typed.
		$logo = 'none' !== $look['logo'] ? self::logo_pixels( $look ) : null;

		if ( null !== $logo ) {
			$scale  = min( 220 / $logo['width'], 64 / $logo['height'] );
			$logo_w = $logo['width'] * $scale;
			$logo_h = $logo['height'] * $scale;

			$doc->image( $logo['pixels'], $logo['width'], $logo['height'], $mid - $logo_w / 2, $y - 12, $logo_w, $logo_h, $logo['channels'] );
			$y += $logo_h + 14;
		}

		if ( null === $logo || '' !== trim( $look['name'] ) ) {
			foreach ( $doc->wrap( strtoupper( $site ), 17, $width, true, true ) as $line ) {
				$doc->text( $mid - $doc->width( $line, 17, true, true ) / 2, $y, $line, 17, true, self::BLACK, false, true );
				$y += 22;
			}
		}

		$home = (string) preg_replace( '#^https?://#', '', untrailingslashit( home_url( '/' ) ) );
		$doc->text( $mid - $doc->width( $home, self::SIZE, false, true ) / 2, $y, $home, self::SIZE, false, self::BLACK, false, true );
		$y += self::LEADING;

		// Their address, tax number and the like, a line each.
		foreach ( self::lines_of( $look['details'] ) as $detail ) {
			foreach ( $doc->wrap( $detail, self::SIZE, $width, false, true ) as $line ) {
				$doc->text( $mid - $doc->width( $line, self::SIZE, false, true ) / 2, $y, $line, self::SIZE, false, self::BLACK, false, true );
				$y += self::LEADING;
			}
		}

		$y    += self::LEADING;
		$title = strtoupper( '' !== trim( $look['title'] ) ? $look['title'] : __( 'Proof of payment', 'ifthenpay-pay-button' ) );
		$doc->text( $mid - $doc->width( $title, self::SIZE, true, true ) / 2, $y, $title, self::SIZE, true, $accent, false, true );
		$y += self::LEADING;
		$doc->text( $left, $y, $dashes, self::SIZE, false, self::BLACK, false, true );
		$y += self::LEADING;

		// Who, when, how: label left, value right, like the lines of a till.
		$head = array(
			array( __( 'Date', 'ifthenpay-pay-button' ), $when ),
			array( __( 'Method', 'ifthenpay-pay-button' ), $record['pay_method'] ),
			array( __( 'Payment ref.', 'ifthenpay-pay-button' ), $record['ref'] ),
			array( __( 'Name', 'ifthenpay-pay-button' ), $record['customer_name'] ),
			array( __( 'Email', 'ifthenpay-pay-button' ), $record['customer_email'] ),
			array( __( 'Your reference', 'ifthenpay-pay-button' ), $record['external_ref'] ),
		);

		$y = self::lines( $doc, $head, $y, $left, $right, false );
		$doc->text( $left, $y, $dashes, self::SIZE, false, self::BLACK, false, true );
		$y += self::LEADING;

		// What the payment was for: every detail it carries, minus the ones already printed above.
		$items = array_values(
			array_filter(
				$pairs,
				static fn( array $pair ): bool => $pair['value'] !== $record['customer_name'] && $pair['value'] !== $record['customer_email']
			)
		);

		if ( array() !== $items ) {
			$doc->text( $left, $y, strtoupper( __( 'Details', 'ifthenpay-pay-button' ) ), self::SIZE, true, self::BLACK, false, true );
			$y += self::LEADING;
			$y  = self::lines(
				$doc,
				array_map( static fn( array $pair ): array => array( $pair['label'], $pair['value'] ), $items ),
				$y,
				$left,
				$right,
				false
			);
			$doc->text( $left, $y, $dashes, self::SIZE, false, self::BLACK, false, true );
			$y += self::LEADING;
		}

		// Everything from here is kept together on one page, like the foot of a till roll.
		$barcode = $look['barcode'] ? self::barcode_units( $record['ref'] ) : null;
		$note    = array();

		foreach ( self::lines_of( $look['message'] ) as $part ) {
			$note = array_merge( $note, $doc->wrap( $part, self::SIZE, $width, false, true ) );
		}

		$need = ( count( $note ) + ( array() !== $note ? 1 : 0 ) ) * self::LEADING + 3 * self::LEADING + 30 + 6 * self::LEADING + ( null !== $barcode ? self::BAR_H + 3 * self::LEADING : 0 ) + 2 * self::LEADING;

		if ( $y + $need > self::BOTTOM ) {
			$doc->add_page();
			$y = self::TOP;
		}

		$total = strtoupper( __( 'Total', 'ifthenpay-pay-button' ) );
		$doc->text( $left, $y + 18, $total, 18, true, $accent, false, true );
		$doc->text( $right, $y + 18, CheckoutService::format_money( $record['amount'] ), 18, true, $accent, true, true );
		$y += 30;
		$doc->text( $left, $y, $dashes, self::SIZE, false, self::BLACK, false, true );
		$y += self::LEADING * 2;

		$paid = '*** ' . strtoupper( __( 'Paid', 'ifthenpay-pay-button' ) ) . ' ***';
		$doc->text( $mid - $doc->width( $paid, 14, true, true ) / 2, $y, $paid, 14, true, $accent, false, true );
		$y += self::LEADING * 2;

		// Their message, above the thank-you.
		foreach ( $note as $line ) {
			$doc->text( $mid - $doc->width( $line, self::SIZE, false, true ) / 2, $y, $line, self::SIZE, false, self::BLACK, false, true );
			$y += self::LEADING;
		}

		$y += array() !== $note ? self::LEADING : 0;

		foreach ( array( __( 'Thank you!', 'ifthenpay-pay-button' ), __( 'Payment processed securely by ifthenpay.', 'ifthenpay-pay-button' ) ) as $note ) {
			foreach ( $doc->wrap( $note, self::SIZE, $width, false, true ) as $line ) {
				$doc->text( $mid - $doc->width( $line, self::SIZE, false, true ) / 2, $y, $line, self::SIZE, false, self::BLACK, false, true );
				$y += self::LEADING;
			}
		}

		if ( null !== $barcode ) {
			$y += self::LEADING;
			self::draw_barcode( $doc, $barcode, $mid, $y, $width );
			$y    += self::BAR_H + self::LEADING;
			$label = strtoupper( $record['ref'] );
			$doc->text( $mid - $doc->width( $label, 9, false, true ) / 2, $y, $label, 9, false, self::BLACK, false, true );
		}

		// A keepsake only: nothing here is an invoice, so it says so, as small as the reference above.
		$y += self::LEADING * 1.5;

		foreach ( $doc->wrap( __( 'Proof of payment only. Not an invoice and not valid for tax purposes.', 'ifthenpay-pay-button' ), 9, $width, false, true ) as $line ) {
			$doc->text( $mid - $doc->width( $line, 9, false, true ) / 2, $y, $line, 9, false, self::BLACK, false, true );
			$y += 12;
		}

		$bytes = $doc->output( sprintf( /* translators: %s: the payment's reference */ __( 'Proof of payment %s', 'ifthenpay-pay-button' ), $record['ref'] ) );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $bytes;
	}

	/**
	 * Prints label/value lines: both on one line when they fit, else the label with its value wrapped under it.
	 *
	 * @param PdfDocument                    $doc   The document.
	 * @param array<int, array<int, string>> $rows  Label and value pairs; empty values are skipped.
	 * @param float                          $y     Baseline of the first line.
	 * @param float                          $left  Left edge of the strip.
	 * @param float                          $right Right edge of the strip.
	 * @param bool                           $bold  Whether to set them bold.
	 * @return float Where the next line goes.
	 */
	private static function lines( PdfDocument $doc, array $rows, float $y, float $left, float $right, bool $bold ): float {
		$width = $right - $left;

		foreach ( $rows as $row ) {
			if ( '' === $row[1] ) {
				continue;
			}

			$label = $row[0];
			$value = $row[1];
			$fits  = $doc->width( $label . '  ' . $value, self::SIZE, $bold, true ) <= $width && false === strpos( $value, "\n" );
			$lines = $fits ? array( $value ) : $doc->wrap( $value, self::SIZE, $width - 2 * 600 * self::SIZE / 1000, $bold, true );

			// A line that doesn't fit goes to a new page, which starts under the top margin.
			if ( $y + self::LEADING * ( count( $lines ) + ( $fits ? 0 : 1 ) ) > self::BOTTOM ) {
				$doc->add_page();
				$y = self::TOP;
			}

			if ( $fits ) {
				$doc->text( $left, $y, $label, self::SIZE, $bold, self::BLACK, false, true );
				$doc->text( $right, $y, $value, self::SIZE, $bold, self::BLACK, true, true );
				$y += self::LEADING;
				continue;
			}

			foreach ( $doc->wrap( $label, self::SIZE, $width, $bold, true ) as $line ) {
				$doc->text( $left, $y, $line, self::SIZE, $bold, self::BLACK, false, true );
				$y += self::LEADING;
			}

			foreach ( $lines as $line ) {
				$doc->text( $right, $y, $line, self::SIZE, $bold, self::BLACK, true, true );
				$y += self::LEADING;
			}
		}

		return $y;
	}

	/**
	 * A #rrggbb colour as red, green and blue.
	 *
	 * @param string $hex The colour.
	 * @return array<int, int>
	 */
	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		return 6 === strlen( $hex ) ? array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) ) : self::BLACK;
	}

	/**
	 * A text's non-empty lines, trimmed.
	 *
	 * @param string $text Text with line breaks.
	 * @return array<int, string>
	 */
	private static function lines_of( string $text ): array {
		$parts = preg_split( '/\R/', $text );

		return array_values( array_filter( array_map( 'trim', is_array( $parts ) ? $parts : array() ), static fn( string $line ): bool => '' !== $line ) );
	}

	/**
	 * The chosen logo as pixels the PDF can hold, grey or in colour, shrunk to a size a receipt needs; null when there is none to print.
	 *
	 * @param array{logo: string, logo_id: int, name: string, details: string, title: string, message: string, barcode: bool, color: bool, accent: string} $look The receipt settings.
	 * @return array{pixels: string, width: int, height: int, channels: int}|null
	 */
	private static function logo_pixels( array $look ): ?array {
		$id = 'image' === $look['logo'] ? $look['logo_id'] : (int) get_theme_mod( 'custom_logo' );
		$id = $id > 0 ? $id : ( 'site' === $look['logo'] ? (int) get_option( 'site_icon' ) : 0 );

		$path = $id > 0 ? get_attached_file( $id ) : false;
		$size = is_string( $path ) && is_readable( $path ) ? wp_getimagesize( $path ) : false;

		// A huge picture would use all the memory just to be shrunk, so I leave it out.
		if ( ! function_exists( 'imagecreatefromstring' ) || ! is_array( $size ) || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 16000000 ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file from the media library, not a remote URL.
		$bytes  = (string) file_get_contents( (string) $path );
		$source = '' !== $bytes ? @imagecreatefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an image GD can't read just means no logo.

		if ( false === $source ) {
			return null;
		}

		$scale  = min( 1, 480 / $size[0], 200 / $size[1] );
		$width  = max( 1, (int) round( $size[0] * $scale ) );
		$height = max( 1, (int) round( $size[1] * $scale ) );
		$canvas = imagecreatetruecolor( $width, $height );

		if ( false === $canvas ) {
			return null;
		}

		// On white, so a transparent logo doesn't print as a black box.
		imagefill( $canvas, 0, 0, (int) imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagecopyresampled( $canvas, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1] );

		$colour = $look['color'];
		$pixels = '';

		for ( $row = 0; $row < $height; $row++ ) {
			for ( $col = 0; $col < $width; $col++ ) {
				$pixel = imagecolorat( $canvas, $col, $row );
				$red   = ( $pixel >> 16 ) & 255;
				$green = ( $pixel >> 8 ) & 255;
				$blue  = $pixel & 255;

				$pixels .= $colour ? chr( $red ) . chr( $green ) . chr( $blue ) : chr( (int) round( 0.299 * $red + 0.587 * $green + 0.114 * $blue ) );
			}
		}

		return array(
			'pixels'   => $pixels,
			'width'    => $width,
			'height'   => $height,
			'channels' => $colour ? 3 : 1,
		);
	}

	/**
	 * The reference as Code 39 elements (bar and space widths, 1 = narrow, 2.5 = wide), or null when it can't be one.
	 *
	 * @param string $ref The payment's reference.
	 * @return array<int, float>|null
	 */
	private static function barcode_units( string $ref ): ?array {
		$text = '*' . strtoupper( str_replace( '_', '-', $ref ) ) . '*';

		if ( strlen( $text ) > 30 ) {
			return null;
		}

		$units = array();

		foreach ( str_split( $text ) as $index => $char ) {
			if ( ! isset( self::CODE39[ $char ] ) ) {
				return null;
			}

			if ( $index > 0 ) {
				$units[] = 1.0;
			}

			foreach ( str_split( self::CODE39[ $char ] ) as $element ) {
				$units[] = 'w' === $element ? 2.5 : 1.0;
			}
		}

		return $units;
	}

	/**
	 * Draws the bars of a Code 39 barcode, centred, as black rectangles.
	 *
	 * @param PdfDocument      $doc   The document.
	 * @param array<int,float> $units Element widths from barcode_units(); even positions are bars.
	 * @param float            $mid   Horizontal centre.
	 * @param float            $y     Top of the bars.
	 * @param float            $max   The widest it may be.
	 */
	private static function draw_barcode( PdfDocument $doc, array $units, float $mid, float $y, float $max ): void {
		$module = min( 1.2, $max / array_sum( $units ) );
		$x      = $mid - array_sum( $units ) * $module / 2;

		foreach ( $units as $index => $unit ) {
			$w = $unit * $module;

			if ( 0 === $index % 2 ) {
				$doc->rect( $x, $y, $w, self::BAR_H, self::BLACK );
			}

			$x += $w;
		}
	}
}
