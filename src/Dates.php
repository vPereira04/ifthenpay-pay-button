<?php
/**
 * Date formatting in the site's timezone and language.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place the plugin turns a timestamp into words, so every screen, email and payload agrees.
 */
final class Dates {

	/**
	 * Formats a Unix timestamp in the site's timezone, with translated month and day names.
	 *
	 * @param string   $format    PHP date format.
	 * @param int|null $timestamp Unix timestamp; now when null.
	 */
	public static function local( string $format, ?int $timestamp = null ): string {
		$timestamp = $timestamp ?? time();
		$moment    = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );

		// date_i18n() only sees the wall clock, so it can't tell the two 1:30s apart when clocks go back. I write the zone parts in myself.
		$format = (string) preg_replace_callback(
			'/\\\\.|[cr]/',
			static fn( array $part ): string => array(
				'c' => 'Y-m-d\TH:i:sP',
				'r' => 'D, d M Y H:i:s O',
			)[ $part[0] ] ?? $part[0],
			$format
		);
		$format = (string) preg_replace_callback(
			'/\\\\.|[eIOPpTZU]/',
			static fn( array $part ): string => 1 === strlen( $part[0] ) ? (string) preg_replace( '/./', '\\\\$0', $moment->format( $part[0] ) ) : $part[0],
			$format
		);

		return (string) date_i18n( $format, $timestamp + $moment->getOffset() );
	}

	/**
	 * Formats a UTC midnight that stands for a calendar day (the chart buckets), with no timezone shift.
	 *
	 * @param string $format    PHP date format, date parts only.
	 * @param int    $timestamp Unix timestamp of the day's UTC midnight.
	 */
	public static function day( string $format, int $timestamp ): string {
		return (string) date_i18n( $format, $timestamp );
	}
}
