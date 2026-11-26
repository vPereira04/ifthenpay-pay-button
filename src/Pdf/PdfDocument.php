<?php
/**
 * A tiny PDF writer: pages of text, rules and filled rectangles in Helvetica.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Pdf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Just enough PDF for a receipt, so the plugin ships no library. The two core fonts need no embedding. Text is
 * written in Windows-1252, which covers Portuguese and the other Western European languages; a character outside it
 * prints as "?". Positions are in points from the top left, which is the way a page is laid out.
 */
final class PdfDocument {

	public const WIDTH  = 595.28;
	public const HEIGHT = 841.89;

	// Helvetica advance widths for ASCII 32-126, in 1/1000 em, regular then bold.
	private const REGULAR = array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 );
	private const BOLD    = array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 );

	/**
	 * One content stream per page.
	 *
	 * @var array<int, string>
	 */
	private array $pages = array();

	/**
	 * Starts a new page; everything drawn after goes on it.
	 */
	public function add_page(): void {
		$this->pages[] = '';
	}

	/**
	 * How many pages there are.
	 */
	public function page_count(): int {
		return count( $this->pages );
	}

	/**
	 * Fills a rectangle.
	 *
	 * @param float           $x      Left.
	 * @param float           $y      Top.
	 * @param float           $width  Width.
	 * @param float           $height Height.
	 * @param array<int, int> $rgb    Red, green, blue, 0-255.
	 */
	public function rect( float $x, float $y, float $width, float $height, array $rgb ): void {
		$this->draw( sprintf( '%s rg %.2F %.2F %.2F %.2F re f', self::color( $rgb ), $x, self::HEIGHT - $y - $height, $width, $height ) );
	}

	/**
	 * Draws a horizontal rule.
	 *
	 * @param float           $x1    Left.
	 * @param float           $x2    Right.
	 * @param float           $y     Top.
	 * @param array<int, int> $rgb   Colour.
	 * @param float           $thick Thickness.
	 */
	public function rule( float $x1, float $x2, float $y, array $rgb, float $thick = 0.6 ): void {
		$this->draw( sprintf( '%s RG %.2F w %.2F %.2F m %.2F %.2F l S', self::color( $rgb ), $thick, $x1, self::HEIGHT - $y, $x2, self::HEIGHT - $y ) );
	}

	/**
	 * Writes one line of text; $y is its baseline.
	 *
	 * @param float           $x     Left, or the right edge when aligned right.
	 * @param float           $y     Baseline, from the top.
	 * @param string          $text  UTF-8 text, no line breaks.
	 * @param float           $size  Font size in points.
	 * @param bool            $bold  Whether it's bold.
	 * @param array<int, int> $rgb   Colour.
	 * @param bool            $right Whether $x is the right edge.
	 */
	public function text( float $x, float $y, string $text, float $size, bool $bold = false, array $rgb = array( 0, 0, 0 ), bool $right = false ): void {
		if ( '' === $text ) {
			return;
		}

		$x = $right ? $x - $this->width( $text, $size, $bold ) : $x;

		$this->draw( sprintf( 'BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', self::color( $rgb ), $bold ? 'F2' : 'F1', $size, $x, self::HEIGHT - $y, self::escape( self::latin( $text ) ) ) );
	}

	/**
	 * How wide a text is.
	 *
	 * @param string $text UTF-8 text.
	 * @param float  $size Font size.
	 * @param bool   $bold Whether it's bold.
	 */
	public function width( string $text, float $size, bool $bold = false ): float {
		$table = $bold ? self::BOLD : self::REGULAR;
		$total = 0;
		$bytes = self::latin( $text );

		for ( $i = 0, $n = strlen( $bytes ); $i < $n; $i++ ) {
			$code   = ord( $bytes[ $i ] );
			$total += $code >= 32 && $code <= 126 ? $table[ $code - 32 ] : 556;
		}

		return $total * $size / 1000;
	}

	/**
	 * Breaks a text into lines that fit a width, at spaces where it can and mid-word where a word alone is too long.
	 * A line break in the text stays one.
	 *
	 * @param string $text      UTF-8 text.
	 * @param float  $size      Font size.
	 * @param float  $max_width The widest a line may be.
	 * @param bool   $bold      Whether it's bold.
	 * @return array<int, string>
	 */
	public function wrap( string $text, float $size, float $max_width, bool $bold = false ): array {
		$lines = array();

		foreach ( self::split( '/\R/', $text ) as $paragraph ) {
			$line = '';

			foreach ( self::split( '/ +/', $paragraph ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;

				if ( $this->width( $try, $size, $bold ) <= $max_width ) {
					$line = $try;
					continue;
				}

				if ( '' !== $line ) {
					$lines[] = $line;
				}

				$line = $word;

				// A word wider than the line is cut where it no longer fits.
				while ( $this->width( $line, $size, $bold ) > $max_width && mb_strlen( $line ) > 1 ) {
					$cut = mb_strlen( $line ) - 1;

					while ( $cut > 1 && $this->width( mb_substr( $line, 0, $cut ), $size, $bold ) > $max_width ) {
						--$cut;
					}

					$lines[] = mb_substr( $line, 0, $cut );
					$line    = mb_substr( $line, $cut );
				}
			}

			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * The finished file's bytes.
	 *
	 * @param string $title The document title.
	 */
	public function output( string $title = '' ): string {
		if ( array() === $this->pages ) {
			$this->add_page();
		}

		// Objects: 1 catalog, 2 pages, 3 and 4 the fonts, 5 the info, then a content stream and a page for each page.
		$objects = array();
		$kids    = array();

		$objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$objects[5] = '<< /Title (' . self::escape( self::latin( $title ) ) . ') /Producer (ifthenpay Pay Button) >>';

		foreach ( $this->pages as $index => $content ) {
			$stream_id = 6 + $index * 2;
			$page_id   = $stream_id + 1;
			$kids[]    = $page_id . ' 0 R';

			$objects[ $stream_id ] = '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream";
			$objects[ $page_id ]   = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::WIDTH, self::HEIGHT, $stream_id );
		}

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[2] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
		ksort( $objects );

		$pdf     = "%PDF-1.4\n";
		$offsets = array();

		foreach ( $objects as $id => $body ) {
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= $id . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";

		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}

		return $pdf . 'trailer << /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R /Info 5 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";
	}

	/**
	 * Adds a drawing operator to the current page.
	 *
	 * @param string $operator PDF content stream text.
	 */
	private function draw( string $operator ): void {
		if ( array() === $this->pages ) {
			$this->add_page();
		}

		$last                  = count( $this->pages ) - 1;
		$this->pages[ $last ] .= $operator . "\n";
	}

	/**
	 * Splits a text with a pattern and always gives a list, even when the pattern fails.
	 *
	 * @param string $pattern The pattern.
	 * @param string $text    The text.
	 * @return array<int, string>
	 */
	private static function split( string $pattern, string $text ): array {
		$parts = preg_split( $pattern, $text );

		return false === $parts ? array( $text ) : $parts;
	}

	/**
	 * A colour as three 0-1 numbers.
	 *
	 * @param array<int, int> $rgb Red, green, blue, 0-255.
	 */
	private static function color( array $rgb ): string {
		return sprintf( '%.3F %.3F %.3F', ( $rgb[0] ?? 0 ) / 255, ( $rgb[1] ?? 0 ) / 255, ( $rgb[2] ?? 0 ) / 255 );
	}

	/**
	 * UTF-8 as the Windows-1252 bytes the fonts expect; what it has no letter for becomes "?".
	 *
	 * @param string $text UTF-8 text.
	 */
	private static function latin( string $text ): string {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
		}

		return (string) preg_replace( '/[^\x20-\x7E]/', '?', $text );
	}

	/**
	 * Escapes the characters that end or start a PDF string.
	 *
	 * @param string $text Windows-1252 text.
	 */
	private static function escape( string $text ): string {
		return str_replace( array( '\\', '(', ')', "\r", "\n" ), array( '\\\\', '\\(', '\\)', ' ', ' ' ), $text );
	}
}
