<?php
/**
 * A cheap look at a drawn button's CSS for the mistake smaller models make most: art parked on top of the label.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * It reads only plain numbers in em, rem and px and skips anything it can't work out, so it never complains about a
 * layout it doesn't understand. A complaint is sent back to the model once; it never blocks a drawing.
 */
class Layout {

	// How far into the label a motif may reach before it counts as sitting on it, in em.
	private const SLACK = 0.25;

	/**
	 * What is wrong with where the button's motifs sit, in words for the model, or null.
	 *
	 * @param string $css The button's CSS.
	 */
	public static function clash( string $css ): ?string {
		$rules = self::rules( $css );
		$base  = $rules['base'];

		// A set width leaves free room, so a motif at the edge is not on the label.
		if ( isset( $base['width'] ) || isset( $base['min-width'] ) || array() === $rules['art'] ) {
			return null;
		}

		$pad  = self::padding( $base );
		$vars = $rules['vars'];

		foreach ( $rules['art'] as $name => $art ) {
			$width = self::em( $art['width'] ?? '', $vars );

			if ( null === $width || $width > 4 || 'absolute' !== ( $art['position'] ?? '' ) ) {
				continue;
			}

			foreach ( array( 'left', 'right' ) as $side ) {
				$offset = self::em( $art[ $side ] ?? '', $vars );

				if ( null !== $offset && $offset + $width > $pad[ $side ] + self::SLACK ) {
					$need = round( $offset + $width + 0.6, 1 );

					return sprintf(
						'The %1$s motif reaches %2$sem from the %3$s edge (%4$sem offset plus %5$sem wide) but the button\'s %3$s padding is only %6$sem, so the motif sits on top of the label. Set padding-%3$s to at least %7$sem, or move the motif outward, or make it smaller, and keep the label readable.',
						$name,
						round( $offset + $width, 2 ),
						$side,
						round( $offset, 2 ),
						round( $width, 2 ),
						round( $pad[ $side ], 2 ),
						$need
					);
				}
			}
		}

		return null;
	}

	/**
	 * The declarations of the button itself, of its two pseudo-elements and its custom properties.
	 *
	 * @param string $css The CSS.
	 * @return array{base: array<string, string>, art: array<string, array<string, string>>, vars: array<string, string>}
	 */
	private static function rules( string $css ): array {
		$out = array(
			'base' => array(),
			'art'  => array(),
			'vars' => array(),
		);
		$css = (string) preg_replace( '~/\*.*?\*/~s', '', $css );

		if ( 1 > (int) preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $found, PREG_SET_ORDER ) ) {
			return $out;
		}

		foreach ( $found as $rule ) {
			$declarations = self::declarations( $rule[2] );

			foreach ( explode( ',', $rule[1] ) as $selector ) {
				$selector = trim( $selector );

				if ( '.iftp-btn-pay__button' === $selector ) {
					$out['base'] = array_merge( $out['base'], $declarations );
				} elseif ( 1 === preg_match( '/^\.iftp-btn-pay__button::?(before|after)$/', $selector, $pseudo ) ) {
					$out['art'][ '::' . $pseudo[1] ] = array_merge( $out['art'][ '::' . $pseudo[1] ] ?? array(), $declarations );
				}
			}
		}

		foreach ( $out['base'] as $property => $value ) {
			if ( str_starts_with( $property, '--' ) ) {
				$out['vars'][ $property ] = $value;
			}
		}

		return $out;
	}

	/**
	 * A rule's body as property => value, the last of a repeated property winning.
	 *
	 * @param string $body Between the braces.
	 * @return array<string, string>
	 */
	private static function declarations( string $body ): array {
		$out = array();

		foreach ( explode( ';', $body ) as $line ) {
			$parts = explode( ':', $line, 2 );

			if ( 2 === count( $parts ) ) {
				$out[ strtolower( trim( $parts[0] ) ) ] = trim( preg_replace( '/\s*!important$/i', '', $parts[1] ) ?? '' );
			}
		}

		return $out;
	}

	/**
	 * The padding on each side in em; a padding that can't be read counts as enough.
	 *
	 * @param array<string, string> $base The button's declarations.
	 * @return array{left: float, right: float}
	 */
	private static function padding( array $base ): array {
		$pad    = array(
			'left'  => PHP_FLOAT_MAX,
			'right' => PHP_FLOAT_MAX,
		);
		$values = isset( $base['padding'] ) ? preg_split( '/\s+/', $base['padding'] ) : false;

		if ( is_array( $values ) && count( $values ) <= 4 ) {
			$em = array_map( fn( string $value ): ?float => self::em( $value, array() ), $values );

			if ( ! in_array( null, $em, true ) ) {
				// One to four values: all; vertical and horizontal; top, horizontal, bottom; top, right, bottom, left.
				$right = $em[1] ?? $em[0];
				$pad   = array(
					'left'  => $em[3] ?? $right,
					'right' => $right,
				);
			}
		}

		foreach ( array( 'left', 'right' ) as $side ) {
			$own = self::em( $base[ 'padding-' . $side ] ?? '', array() );

			if ( null !== $own ) {
				$pad[ $side ] = $own;
			}
		}

		return $pad;
	}

	/**
	 * A length in em; rem counts as em and 16 px as 1 em. One variable is looked up. Anything else is null.
	 *
	 * @param string                $value A CSS length.
	 * @param array<string, string> $vars  The button's custom properties.
	 */
	private static function em( string $value, array $vars ): ?float {
		if ( 1 === preg_match( '/^var\(\s*(--[\w-]+)\s*\)$/', $value, $variable ) ) {
			$value = $vars[ $variable[1] ] ?? '';
		}

		if ( 1 !== preg_match( '/^(-?\d*\.?\d+)(em|rem|px)?$/', $value, $number ) ) {
			return null;
		}

		$size = (float) $number[1];

		// A bare 0 is the only unitless length.
		if ( ! isset( $number[2] ) ) {
			return 0.0 === $size ? 0.0 : null;
		}

		return 'px' === $number[2] ? $size / 16 : $size;
	}
}
