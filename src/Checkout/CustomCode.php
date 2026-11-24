<?php
/**
 * The merchant's own CSS and JavaScript for the button and the checkout: what's allowed, and what's printed.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom code is as powerful as unfiltered HTML, so only users who may post that can change it (the screen
 * checks). Here it's only checked to be well formed and free of the things that load or run code from elsewhere.
 * assets/js/buttons/code.js runs the same checks while typing, so the two lists have to match.
 */
class CustomCode {

	public const MAX_LENGTH = 30000;

	public const FIELDS = array( 'button_css', 'button_js', 'page_css', 'page_js' );

	// What a rejected field reports.
	public const ERROR_LENGTH     = 'length';
	public const ERROR_UNBALANCED = 'unbalanced';
	public const ERROR_FORBIDDEN  = 'forbidden';
	public const ERROR_UNNESTABLE = 'unnestable';

	// What a scoped button's CSS can't carry: these at-rules don't nest inside a rule. @keyframes would be here too, but those are lifted out first.
	private const CSS_UNNESTABLE = '/@(?:import|charset|namespace|layer|page|counter-style)\b/i';

	// JS that would close the <script> early or open an HTML comment inside it.
	private const JS_FORBIDDEN = '/<\/script|<!--/i';

	/**
	 * Why a stylesheet can't be saved, or null when it can.
	 *
	 * @param string $css    The CSS.
	 * @param bool   $scoped Whether it's going inside a button's scope, where some at-rules can't go.
	 * @return string|null One of the ERROR_ constants.
	 */
	public static function css_error( string $css, bool $scoped = false ): ?string {
		if ( mb_strlen( $css ) > self::MAX_LENGTH ) {
			return self::ERROR_LENGTH;
		}

		// Comments and CSS escapes (\65 xpression) can't be allowed to hide a forbidden word.
		$plain = preg_replace( '#/\*.*?\*/#s', '', $css );
		$plain = preg_replace_callback(
			'/\\\\([0-9a-f]{1,6})\s?/i',
			static fn( array $found ): string => (string) mb_chr( (int) hexdec( $found[1] ) ),
			(string) $plain
		);
		$plain = preg_replace( '/\\\\(.)/s', '$1', (string) $plain );

		if ( $scoped && 1 === preg_match( self::CSS_UNNESTABLE, (string) $plain ) ) {
			return self::ERROR_UNNESTABLE;
		}

		return self::balanced( $css ) ? null : self::ERROR_UNBALANCED;
	}

	/**
	 * Why a script can't be saved, or null when it can. Whether it parses is checked in the browser as it's typed,
	 * since PHP can't read JavaScript.
	 *
	 * @param string $js The JavaScript.
	 * @return string|null One of the ERROR_ constants.
	 */
	public static function js_error( string $js ): ?string {
		if ( mb_strlen( $js ) > self::MAX_LENGTH ) {
			return self::ERROR_LENGTH;
		}

		return 1 === preg_match( self::JS_FORBIDDEN, $js ) ? self::ERROR_FORBIDDEN : null;
	}

	/**
	 * What a field's error says.
	 *
	 * @param string $field Which field: one of FIELDS, or 'checkout_css'.
	 * @param string $error One of the ERROR_ constants.
	 */
	public static function message( string $field, string $error ): string {
		$names = array(
			'button_css'   => __( 'The button\'s custom CSS', 'ifthenpay-pay-button' ),
			'button_js'    => __( 'The button\'s custom JavaScript', 'ifthenpay-pay-button' ),
			'page_css'     => __( 'The checkout and pay page\'s custom CSS', 'ifthenpay-pay-button' ),
			'page_js'      => __( 'The checkout and pay page\'s custom JavaScript', 'ifthenpay-pay-button' ),
			'checkout_css' => __( 'The site\'s custom CSS', 'ifthenpay-pay-button' ),
		);
		$why   = array(
			self::ERROR_LENGTH     => __( 'is too long (30,000 characters at most).', 'ifthenpay-pay-button' ),
			self::ERROR_UNBALANCED => __( 'has a bracket or a quote that is never closed.', 'ifthenpay-pay-button' ),
			self::ERROR_UNNESTABLE => __( 'uses @import, @layer or another at-rule that cannot sit inside one button\'s scope. Use @media or @supports, or the site\'s Custom CSS in Default Design.', 'ifthenpay-pay-button' ),
			self::ERROR_FORBIDDEN  => __( 'has a closing script tag or an HTML comment opener, which would break the page.', 'ifthenpay-pay-button' ),
		);

		/* translators: 1: which code field, 2: why it was refused */
		return sprintf( __( '%1$s %2$s It was not saved; the last good version is still live.', 'ifthenpay-pay-button' ), $names[ $field ] ?? $field, $why[ $error ] ?? '' );
	}

	/**
	 * Script text ready to store: one kind of line break, valid UTF-8 and nothing past the length cap.
	 *
	 * @param mixed $code The raw code.
	 */
	public static function clean( $code ): string {
		if ( ! is_string( $code ) ) {
			return '';
		}

		return trim( mb_substr( str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( $code ) ), 0, self::MAX_LENGTH ) );
	}

	/**
	 * A button's code, every key a string.
	 *
	 * @return array{button_css: string, button_js: string, page_css: string, page_js: string}
	 */
	public static function defaults(): array {
		return array(
			'button_css' => '',
			'button_js'  => '',
			'page_css'   => '',
			'page_js'    => '',
		);
	}

	/**
	 * Cleans a button's code from anywhere. A box that fails its checks is emptied here; the editor's save checks
	 * first, keeps the last good version and says why.
	 *
	 * @param mixed $input The raw code.
	 * @return array{button_css: string, button_js: string, page_css: string, page_js: string}
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$code  = self::defaults();

		foreach ( self::FIELDS as $field ) {
			$value = is_string( $input[ $field ] ?? null ) ? self::clean( $input[ $field ] ) : '';
			$value = str_ends_with( $field, '_css' ) ? CheckoutLook::clean_css( $value ) : $value;
			$error = str_ends_with( $field, '_css' ) ? self::css_error( $value, true ) : self::js_error( $value );

			$code[ $field ] = null === $error ? $value : '';
		}

		return $code;
	}

	/**
	 * The attribute a button's elements carry, which its CSS and JavaScript are scoped to.
	 *
	 * @param int|string $button_id The button, or a name for a design shown in the admin where there is no button.
	 */
	public static function scope_selector( $button_id ): string {
		return '[data-iftp-code="' . $button_id . '"]';
	}

	/**
	 * Lifts a button's @keyframes, @font-face and @property rules out of its CSS, which gets nested inside the button's scope where
	 * they can't go. Each animation is renamed with the button's id, so two buttons that both call theirs "spin" don't take over
	 * each other's.
	 *
	 * @param int|string $button_id The button (or the name of a design shown in the admin).
	 * @param string     $css       Its CSS.
	 * @return array{0: string, 1: string} The rules, ready for the top level, and the CSS without them.
	 */
	public static function lift_keyframes( $button_id, string $css ): array {
		$lifted = '';
		$names  = array();
		$offset = 0;

		while ( 1 === preg_match( '/@(?:(?:-webkit-)?keyframes\s+([\w-]+)|font-face|property\s+--[\w-]+)\s*\{/i', $css, $found, PREG_OFFSET_CAPTURE, $offset ) ) {
			$start = (int) $found[0][1];
			$depth = 0;
			$end   = strlen( $css );

			for ( $i = $start + strlen( $found[0][0] ) - 1, $length = strlen( $css ); $i < $length; $i++ ) {
				if ( '{' === $css[ $i ] ) {
					++$depth;
				} elseif ( '}' === $css[ $i ] && 0 === --$depth ) {
					$end = $i + 1;
					break;
				}
			}

			if ( isset( $found[1] ) && '' !== $found[1][0] ) {
				$names[ $found[1][0] ] = true;
			}

			$lifted .= "\n" . substr( $css, $start, $end - $start );
			$css     = substr( $css, 0, $start ) . substr( $css, $end );
			$offset  = $start;
		}

		foreach ( array_keys( $names ) as $name ) {
			$pattern = '/(?<![\w.#-])' . preg_quote( (string) $name, '/' ) . '(?![\w-])/';
			$renamed = $name . '-b' . $button_id;
			$lifted  = (string) preg_replace( $pattern, $renamed, $lifted );
			$css     = (string) preg_replace( $pattern, $renamed, $css );
		}

		return array( trim( $lifted ), $css );
	}

	/**
	 * A drawn design's CSS scoped to one name, for the admin: its animations lifted out and the rules made strong enough to beat
	 * the card or tile they sit in.
	 *
	 * @param string $name   What the elements carry in data-iftp-code.
	 * @param string $css    The CSS.
	 * @param int    $repeat How many times the attribute is repeated, for strength.
	 * @param string $extra  A rule added ahead of the design's own, e.g. `{pointer-events:auto}`; '' for none.
	 */
	public static function scope_css( string $name, string $css, int $repeat = 4, string $extra = '' ): string {
		$scope                = str_repeat( self::scope_selector( $name ), $repeat );
		list( $frames, $css ) = self::lift_keyframes( $name, $css );

		return ( '' !== $frames ? $frames . "\n" : '' ) . ( '' !== $extra ? $scope . $extra . "\n" : '' ) . $scope . '{' . $css . "\n}";
	}

	/**
	 * A script that only works on one button's elements: `scope` is the list of them.
	 *
	 * @param int|string $button_id The button.
	 * @param string     $label     Which box it came from, for the console.
	 * @param string     $js        Cleaned JavaScript.
	 */
	public static function scope_js( $button_id, string $label, string $js ): string {
		if ( '' === $js ) {
			return '';
		}

		return '(function(scope){try{' . $js . "\n" . '}catch(e){console.error("ifthenpay Pay Button, custom ' . $label . ' JavaScript:",e);}})(document.querySelectorAll(\'' . self::scope_selector( $button_id ) . '\'));';
	}

	/**
	 * Whether every bracket and quote opens and closes, ignoring comments and what's inside quotes.
	 *
	 * @param string $css The CSS.
	 */
	private static function balanced( string $css ): bool {
		$pairs = array(
			'}' => '{',
			')' => '(',
			']' => '[',
		);
		$stack = array();
		$quote = '';
		$chars = mb_str_split( $css );
		$total = count( $chars );

		for ( $i = 0; $i < $total; $i++ ) {
			$char = $chars[ $i ];

			if ( '\\' === $char ) {
				++$i;
				continue;
			}

			if ( '' !== $quote ) {
				if ( $char === $quote ) {
					$quote = '';
				} elseif ( "\n" === $char ) {
					return false;
				}
				continue;
			}

			if ( '/' === $char && '*' === ( $chars[ $i + 1 ] ?? '' ) ) {
				$end = mb_strpos( $css, '*/', $i + 2 );

				if ( false === $end ) {
					return false;
				}

				// Characters, not bytes: the array index and mb_strpos agree.
				$i = $end + 1;
				continue;
			}

			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
			} elseif ( in_array( $char, array( '{', '(', '[' ), true ) ) {
				$stack[] = $char;
			} elseif ( isset( $pairs[ $char ] ) && array_pop( $stack ) !== $pairs[ $char ] ) {
				return false;
			}
		}

		return '' === $quote && array() === $stack;
	}
}
