<?php
/**
 * How a pay button looks.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A button either takes the site's default design (Default Design › Button), the theme's own button look or a
 * custom one: style, colours, corners, border, size, shadow, a hover effect, an attention effect and letter case.
 * Icon, width and the method logos work with the theme's look and a custom one.
 * The custom look is a few CSS variables plus BEM modifiers, so checkout.css does the drawing and the admin
 * preview uses the exact same rules.
 *
 * @phpstan-type ButtonDesignConfig array{source: string, variant: string, color: string, color2: string, text: string, radius: int, border_width: int, border_style: string, border_color: string, size: string, full: bool, icon: string, icon_style: string, shadow: bool, effect: string, attention: string, upper: bool, logos: bool, still: bool, font: string, stops: array<int, array{color: string, pos: int, spread: int, blur: int}>, stops_shape: string, stops_angle: int, stops_flow: bool}
 */
class ButtonDesign {

	public const SOURCE_SITE   = 'site';
	public const SOURCE_THEME  = 'theme';
	public const SOURCE_CUSTOM = 'custom';
	// No styles from us at all: the merchant's own CSS and JavaScript (a button's `code`) do it.
	public const SOURCE_CODE = 'code';
	public const SOURCES     = array( self::SOURCE_SITE, self::SOURCE_THEME, self::SOURCE_CUSTOM, self::SOURCE_CODE );

	// What the site's default itself can be: it can't point at itself.
	public const OWN_SOURCES = array( self::SOURCE_THEME, self::SOURCE_CUSTOM );

	public const VARIANTS      = array( 'filled', 'outline', 'soft', 'aurora', 'split', 'halo', 'glass', '3d', 'neon', 'brutal', 'ghost', 'luxe', 'beam', 'clay', 'ticket', 'gloss', 'ring', 'stripe', 'cut', 'checker', 'hazard', 'scanline', 'marquee', 'pixel', 'blueprint', 'spectrum', 'glazed', 'frosting', 'pizza', 'football', 'basketball', 'running', 'coder', 'medic', 'music', 'pilot', 'school', 'cartoon' );
	public const SIZES         = array( 'sm', 'md', 'lg' );
	public const ICON_STYLES   = array( 'outline', 'filled', 'duotone' );
	public const ICONS         = array( 'none', 'lock', 'card', 'arrow', 'heart', 'cart', 'gift', 'ticket', 'calendar', 'star', 'sparkles', 'paw', 'leaf', 'bolt' );
	public const EFFECTS       = array( 'none', 'lift', 'grow', 'shine', 'glow', 'fill', 'nudge', 'gild', 'tilt' );
	public const ATTENTIONS    = array( 'none', 'pulse', 'ripple', 'heartbeat', 'wiggle', 'bounce', 'shimmer', 'breathe', 'orbit' );
	public const BORDER_STYLES = array( 'solid', 'dashed', 'dotted', 'double' );

	// The spectrum style: a gradient of the merchant's own colour stops.
	public const SPECTRUM_SHAPES  = array( 'linear', 'radial', 'conic' );
	public const SPECTRUM_MIN     = 2;
	public const SPECTRUM_MAX     = 8;
	public const SPECTRUM_RAINBOW = array(
		array(
			'color'  => '#ff3b30',
			'pos'    => 0,
			'spread' => 20,
			'blur'   => 100,
		),
		array(
			'color'  => '#ff9500',
			'pos'    => 20,
			'spread' => 20,
			'blur'   => 100,
		),
		array(
			'color'  => '#ffd60a',
			'pos'    => 40,
			'spread' => 20,
			'blur'   => 100,
		),
		array(
			'color'  => '#34c759',
			'pos'    => 60,
			'spread' => 20,
			'blur'   => 100,
		),
		array(
			'color'  => '#0a84ff',
			'pos'    => 80,
			'spread' => 20,
			'blur'   => 100,
		),
		array(
			'color'  => '#bf5af2',
			'pos'    => 100,
			'spread' => 20,
			'blur'   => 100,
		),
	);

	// The button's lettering: '' keeps the style's own (the theme's, or monospace for pixel), the rest ship with the plugin.
	public const FONTS = array( '', 'inter', 'outfit', 'grotesk', 'playfair', 'mono', 'pixelify', 'silkscreen', 'vt323', 'press-start', 'fredoka', 'pacifico', 'chewy' );

	// The border slider tops out here.
	public const BORDER_MAX = 6;

	// The styles drawn solid in the main colour, so their text goes black or white by contrast.
	private const SOLID_VARIANTS = array( 'filled', 'split', 'halo', 'glass', '3d', 'brutal', 'clay', 'ticket', 'gloss', 'ring', 'stripe', 'cut', 'checker', 'hazard', 'marquee', 'pixel', 'frosting', 'pizza', 'football', 'basketball', 'running', 'music', 'pilot', 'school', 'cartoon' );

	// How far round the colour wheel a gradient's own second colour sits from the first.
	public const GRADIENT_TURN = 40;

	// The food styles' second colour when none is picked: icing, frosting, tomato. A turned hue would turn cheese green.
	public const FOOD_SECOND = array(
		'glazed'   => '#ff8fb8',
		'frosting' => '#fff3ee',
		'pizza'    => '#c8321f',
	);

	// The corners slider tops out here, and the top step means a pill.
	public const PILL = 32;

	private const INK   = '#121a2c';
	private const WHITE = '#ffffff';

	/**
	 * The site's default before anyone sets it: the theme's look.
	 *
	 * @return ButtonDesignConfig
	 */
	public static function defaults(): array {
		return array(
			'source'       => self::SOURCE_THEME,
			'variant'      => 'filled',
			'color'        => '#00609c',
			'color2'       => '',
			'text'         => '',
			'radius'       => 10,
			'border_width' => 2,
			'border_style' => 'solid',
			'border_color' => '',
			'size'         => 'md',
			'full'         => false,
			'icon'         => 'none',
			'icon_style'   => 'outline',
			'shadow'       => false,
			'effect'       => 'none',
			'attention'    => 'none',
			'upper'        => false,
			'logos'        => true,
			'still'        => true,
			'font'         => '',
			'stops'        => self::SPECTRUM_RAINBOW,
			'stops_shape'  => 'linear',
			'stops_angle'  => 90,
			'stops_flow'   => false,
		);
	}

	/**
	 * Ready-made custom looks for the editor. Each one only sets the custom fields; the border and the attention
	 * effect are the defaults unless it says otherwise.
	 *
	 * @return array<string, array{label: string, variant: string, color: string, color2: string, text: string, radius: int, border_width: int, border_style: string, border_color: string, size: string, icon: string, shadow: bool, effect: string, attention: string, upper: bool, font: string, stops?: array<int, array{color: string, pos: int, spread: int, blur: int}>, stops_shape?: string, stops_angle?: int, stops_flow?: bool}>
	 */
	public static function presets(): array {
		$base = array(
			'border_width' => 2,
			'border_style' => 'solid',
			'border_color' => '',
			'attention'    => 'none',
		);

		$presets = array(
			'ifthenpay' => array(
				'label'   => __( 'ifthenpay', 'ifthenpay-pay-button' ),
				'variant' => 'filled',
				'color'   => '#00609c',
				'color2'  => '',
				'text'    => '',
				'radius'  => 10,
				'size'    => 'md',
				'icon'    => 'lock',
				'shadow'  => true,
				'effect'  => 'lift',
				'upper'   => false,
				'font'    => '',
			),
			'midnight'  => array(
				'label'   => __( 'Midnight', 'ifthenpay-pay-button' ),
				'variant' => 'filled',
				'color'   => '#121a2c',
				'color2'  => '',
				'text'    => '',
				'radius'  => self::PILL,
				'size'    => 'md',
				'icon'    => 'arrow',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => false,
				'font'    => '',
			),
			'outline'   => array(
				'label'   => __( 'Outline', 'ifthenpay-pay-button' ),
				'variant' => 'outline',
				'color'   => '#00609c',
				'color2'  => '',
				'text'    => '',
				'radius'  => 8,
				'size'    => 'md',
				'icon'    => 'none',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => false,
				'font'    => '',
			),
			'soft'      => array(
				'label'   => __( 'Soft', 'ifthenpay-pay-button' ),
				'variant' => 'soft',
				'color'   => '#0b6b35',
				'color2'  => '',
				'text'    => '',
				'radius'  => 12,
				'size'    => 'md',
				'icon'    => 'card',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => false,
				'font'    => '',
			),
			'ruby'      => array(
				'label'     => __( 'Ruby', 'ifthenpay-pay-button' ),
				'variant'   => 'filled',
				'color'     => '#c8363b',
				'color2'    => '',
				'text'      => '',
				'radius'    => self::PILL,
				'size'      => 'lg',
				'icon'      => 'heart',
				'shadow'    => true,
				'effect'    => 'none',
				'attention' => 'pulse',
				'upper'     => false,
				'font'      => '',
			),
			'sunset'    => array(
				'label'   => __( 'Sunset', 'ifthenpay-pay-button' ),
				'variant' => 'aurora',
				'color'   => '#ff5f6d',
				'color2'  => '#ffc371',
				'text'    => '',
				'radius'  => self::PILL,
				'size'    => 'lg',
				'icon'    => 'arrow',
				'shadow'  => true,
				'effect'  => 'shine',
				'upper'   => false,
				'font'    => '',
			),
			'glass'     => array(
				'label'   => __( 'Glass', 'ifthenpay-pay-button' ),
				'variant' => 'glass',
				'color'   => '#00609c',
				'color2'  => '',
				'text'    => '',
				'radius'  => 14,
				'size'    => 'md',
				'icon'    => 'lock',
				'shadow'  => false,
				'effect'  => 'lift',
				'upper'   => false,
				'font'    => '',
			),
			'candy'     => array(
				'label'     => __( 'Candy', 'ifthenpay-pay-button' ),
				'variant'   => '3d',
				'color'     => '#ff4d8d',
				'color2'    => '',
				'text'      => '',
				'radius'    => self::PILL,
				'size'      => 'lg',
				'icon'      => 'heart',
				'shadow'    => false,
				'effect'    => 'none',
				'attention' => 'bounce',
				'upper'     => false,
				'font'      => '',
			),
			'neon'      => array(
				'label'     => __( 'Neon', 'ifthenpay-pay-button' ),
				'variant'   => 'neon',
				'color'     => '#7c3aed',
				'color2'    => '',
				'text'      => '',
				'radius'    => 12,
				'size'      => 'md',
				'icon'      => 'arrow',
				'shadow'    => false,
				'effect'    => 'glow',
				'attention' => 'orbit',
				'upper'     => true,
				'font'      => '',
			),
			'brutal'    => array(
				'label'        => __( 'Brutal', 'ifthenpay-pay-button' ),
				'variant'      => 'brutal',
				'color'        => '#ffd23f',
				'color2'       => '',
				'text'         => '',
				'radius'       => 4,
				'border_width' => 3,
				'size'         => 'lg',
				'icon'         => 'arrow',
				'shadow'       => false,
				'effect'       => 'nudge',
				'upper'        => true,
				'font'         => '',
			),
			'split'     => array(
				'label'   => __( 'Split', 'ifthenpay-pay-button' ),
				'variant' => 'split',
				'color'   => '#0f766e',
				'color2'  => '#facc15',
				'text'    => '',
				'radius'  => 12,
				'size'    => 'lg',
				'icon'    => 'arrow',
				'shadow'  => true,
				'effect'  => 'nudge',
				'upper'   => false,
				'font'    => '',
			),
			'halo'      => array(
				'label'     => __( 'Halo', 'ifthenpay-pay-button' ),
				'variant'   => 'halo',
				'color'     => '#e4505b',
				'color2'    => '',
				'text'      => '',
				'radius'    => self::PILL,
				'size'      => 'lg',
				'icon'      => 'heart',
				'shadow'    => false,
				'effect'    => 'glow',
				'attention' => 'breathe',
				'upper'     => false,
				'font'      => '',
			),
			'premium'   => array(
				'label'     => __( 'Premium', 'ifthenpay-pay-button' ),
				'variant'   => 'luxe',
				'color'     => '#a87a1c',
				'color2'    => '#f1d78a',
				'text'      => '#1a1405',
				'radius'    => 0,
				'size'      => 'lg',
				'icon'      => 'none',
				'shadow'    => true,
				'effect'    => 'gild',
				'attention' => 'none',
				'upper'     => true,
				'font'      => '',
			),
			'beam'      => array(
				'label'   => __( 'Beam', 'ifthenpay-pay-button' ),
				'variant' => 'beam',
				'color'   => '#6366f1',
				'color2'  => '#22d3ee',
				'text'    => '',
				'radius'  => 14,
				'size'    => 'lg',
				'icon'    => 'arrow',
				'shadow'  => false,
				'effect'  => 'nudge',
				'upper'   => false,
				'font'    => '',
			),
			'clay'      => array(
				'label'   => __( 'Clay', 'ifthenpay-pay-button' ),
				'variant' => 'clay',
				'color'   => '#f59e8b',
				'color2'  => '',
				'text'    => '#4a1d12',
				'radius'  => 22,
				'size'    => 'lg',
				'icon'    => 'heart',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => false,
				'font'    => '',
			),
			'ticket'    => array(
				'label'   => __( 'Ticket', 'ifthenpay-pay-button' ),
				'variant' => 'ticket',
				'color'   => '#e4505b',
				'color2'  => '',
				'text'    => '',
				'radius'  => 8,
				'size'    => 'lg',
				'icon'    => 'ticket',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => true,
				'font'    => '',
			),
			'offset'    => array(
				'label'   => __( 'Offset', 'ifthenpay-pay-button' ),
				'variant' => 'ring',
				'color'   => '#2563eb',
				'color2'  => '',
				'text'    => '',
				'radius'  => 12,
				'size'    => 'md',
				'icon'    => 'arrow',
				'shadow'  => false,
				'effect'  => 'nudge',
				'upper'   => false,
				'font'    => '',
			),
			'barber'    => array(
				'label'   => __( 'Barber', 'ifthenpay-pay-button' ),
				'variant' => 'stripe',
				'color'   => '#f97316',
				'color2'  => '',
				'text'    => '',
				'radius'  => 10,
				'size'    => 'lg',
				'icon'    => 'bolt',
				'shadow'  => true,
				'effect'  => 'grow',
				'upper'   => true,
				'font'    => '',
			),
			'chamfer'   => array(
				'label'   => __( 'Chamfer', 'ifthenpay-pay-button' ),
				'variant' => 'cut',
				'color'   => '#0e7490',
				'color2'  => '#f59e0b',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'lg',
				'icon'    => 'bolt',
				'shadow'  => false,
				'effect'  => 'shine',
				'upper'   => true,
				'font'    => '',
			),
			'checker'   => array(
				'label'   => __( 'Checkerboard', 'ifthenpay-pay-button' ),
				'variant' => 'checker',
				'color'   => '#e11d48',
				'color2'  => '',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'lg',
				'icon'    => 'star',
				'shadow'  => false,
				'effect'  => 'grow',
				'upper'   => true,
				'font'    => '',
			),
			'tape'      => array(
				'label'   => __( 'Tape', 'ifthenpay-pay-button' ),
				'variant' => 'hazard',
				'color'   => '#f5c518',
				'color2'  => '',
				'text'    => '#121a2c',
				'radius'  => 0,
				'size'    => 'lg',
				'icon'    => 'bolt',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => true,
				'font'    => '',
			),
			'terminal'  => array(
				'label'   => __( 'Terminal', 'ifthenpay-pay-button' ),
				'variant' => 'scanline',
				'color'   => '#22d3ee',
				'color2'  => '',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'md',
				'icon'    => 'lock',
				'shadow'  => false,
				'effect'  => 'glow',
				'upper'   => true,
				'font'    => '',
			),
			'showtime'  => array(
				'label'   => __( 'Showtime', 'ifthenpay-pay-button' ),
				'variant' => 'marquee',
				'color'   => '#7c1d6f',
				'color2'  => '#ffd166',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'lg',
				'icon'    => 'ticket',
				'shadow'  => false,
				'effect'  => 'none',
				'upper'   => true,
				'font'    => '',
			),
			'arcade'    => array(
				'label'   => __( 'Arcade', 'ifthenpay-pay-button' ),
				'variant' => 'pixel',
				'color'   => '#16a34a',
				'color2'  => '',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'lg',
				'icon'    => 'heart',
				'shadow'  => false,
				'effect'  => 'nudge',
				'upper'   => true,
				'font'    => 'press-start',
			),
			'drafting'  => array(
				'label'   => __( 'Drafting', 'ifthenpay-pay-button' ),
				'variant' => 'blueprint',
				'color'   => '#1d4ed8',
				'color2'  => '',
				'text'    => '',
				'radius'  => 0,
				'size'    => 'md',
				'icon'    => 'arrow',
				'shadow'  => false,
				'effect'  => 'fill',
				'upper'   => false,
				'font'    => '',
			),
			'rainbow'   => array(
				'label'       => __( 'Rainbow', 'ifthenpay-pay-button' ),
				'variant'     => 'spectrum',
				'color'       => '#34c759',
				'color2'      => '',
				'text'        => '',
				'radius'      => self::PILL,
				'size'        => 'md',
				'icon'        => 'sparkles',
				'shadow'      => true,
				'effect'      => 'lift',
				'upper'       => false,
				'font'        => '',
				'stops'       => self::SPECTRUM_RAINBOW,
				'stops_shape' => 'linear',
				'stops_angle' => 90,
				'stops_flow'  => true,
			),
			'wheel'     => array(
				'label'       => __( 'Colour wheel', 'ifthenpay-pay-button' ),
				'variant'     => 'spectrum',
				'color'       => '#0a84ff',
				'color2'      => '',
				'text'        => '',
				'radius'      => 14,
				'size'        => 'md',
				'icon'        => 'arrow',
				'shadow'      => false,
				'effect'      => 'none',
				'upper'       => false,
				'font'        => '',
				'stops'       => array(
					array(
						'color'  => '#ff3b30',
						'pos'    => 0,
						'spread' => 34,
						'blur'   => 60,
					),
					array(
						'color'  => '#ffd60a',
						'pos'    => 33,
						'spread' => 34,
						'blur'   => 60,
					),
					array(
						'color'  => '#34c759',
						'pos'    => 66,
						'spread' => 34,
						'blur'   => 60,
					),
					array(
						'color'  => '#ff3b30',
						'pos'    => 100,
						'spread' => 34,
						'blur'   => 60,
					),
				),
				'stops_shape' => 'conic',
				'stops_angle' => 0,
				'stops_flow'  => false,
			),
		);

		foreach ( $presets as $key => $preset ) {
			$presets[ $key ] = $preset + $base;
		}

		/**
		 * Each one is whole once the base is merged in.
		 *
		 * @var array<string, array{label: string, variant: string, color: string, color2: string, text: string, radius: int, border_width: int, border_style: string, border_color: string, size: string, icon: string, shadow: bool, effect: string, attention: string, upper: bool, font: string, stops?: array<int, array{color: string, pos: int, spread: int, blur: int}>, stops_shape?: string, stops_angle?: int, stops_flow?: bool}> $presets
		 */
		return self::by_colour( $presets, 'color' );
	}

	/**
	 * Presets grouped by colour: ifthenpay first, then the neutrals dark to light, then round the colour wheel.
	 *
	 * @template T of array<string, mixed>
	 * @param array<string, T> $presets The presets, keyed by name.
	 * @param string           $field   The preset key that holds its main #rrggbb colour.
	 * @return array<string, T>
	 */
	public static function by_colour( array $presets, string $field ): array {
		$rank = array();

		foreach ( $presets as $key => $preset ) {
			[ $r, $g, $b ] = array_map( static fn( int $channel ): float => $channel / 255, self::rgb( (string) $preset[ $field ] ) );

			$max   = max( $r, $g, $b );
			$min   = min( $r, $g, $b );
			$delta = $max - $min;
			$light = ( $max + $min ) / 2;

			// Near-greys and ink-dark navies count as neutral.
			if ( $delta < 0.16 ) {
				$rank[ $key ] = array( 1, $light );
				continue;
			}

			if ( $max === $r ) {
				$hue = 60 * fmod( ( $g - $b ) / $delta + 6, 6 );
			} elseif ( $max === $g ) {
				$hue = 60 * ( ( $b - $r ) / $delta + 2 );
			} else {
				$hue = 60 * ( ( $r - $g ) / $delta + 4 );
			}

			// Shifted so the pinks sit next to the reds instead of wrapping to the far end.
			$rank[ $key ] = array( 2, fmod( $hue + 30, 360 ), $light );
		}

		uksort(
			$presets,
			static function ( string $a, string $b ) use ( $rank ): int {
				$first = ( 'ifthenpay' === $b ) <=> ( 'ifthenpay' === $a );
				return 0 !== $first ? $first : $rank[ $a ] <=> $rank[ $b ];
			}
		);

		return $presets;
	}

	/**
	 * The design a button shows: its own, or the site's default when it follows that.
	 *
	 * @param Button           $button   The button.
	 * @param CheckoutSettings $settings Where the site's default is stored.
	 * @return ButtonDesignConfig
	 */
	public static function for_button( Button $button, CheckoutSettings $settings ): array {
		return self::SOURCE_SITE === $button->config['design']['source'] ? $settings->get_button_design() : $button->config['design'];
	}

	/**
	 * Cleans a design from anywhere, so callers can trust every key and type.
	 *
	 * @param mixed $input The raw design.
	 * @return ButtonDesignConfig
	 */
	public static function sanitize( $input ): array {
		$design = self::defaults();
		$input  = is_array( $input ) ? $input : array();

		// Gradient is gone: those buttons become a still Aurora, the closest look to it.
		if ( 'gradient' === ( $input['variant'] ?? '' ) ) {
			$input['variant'] = 'aurora';
			$input['still']   = true;
		}

		$choice = static fn( string $key, array $allowed, string $fallback ): string => is_string( $input[ $key ] ?? null ) && in_array( $input[ $key ], $allowed, true ) ? $input[ $key ] : $fallback;

		$design['source']  = $choice( 'source', self::SOURCES, $design['source'] );
		$design['variant'] = $choice( 'variant', self::VARIANTS, $design['variant'] );
		$design['size']    = $choice( 'size', self::SIZES, $design['size'] );
		$design['icon']    = $choice( 'icon', self::ICONS, $design['icon'] );
		$design['icon_style'] = $choice( 'icon_style', self::ICON_STYLES, $design['icon_style'] );
		$design['effect']  = $choice( 'effect', self::EFFECTS, $design['effect'] );

		$design['font']         = $choice( 'font', self::FONTS, $design['font'] );
		$design['attention']    = $choice( 'attention', self::ATTENTIONS, $design['attention'] );
		$design['border_style'] = $choice( 'border_style', self::BORDER_STYLES, $design['border_style'] );

		// Pulse used to be a hover effect; it's an attention effect now.
		if ( 'pulse' === ( $input['effect'] ?? '' ) && ! isset( $input['attention'] ) ) {
			$design['attention'] = 'pulse';
		}

		$color = is_string( $input['color'] ?? null ) ? self::hex( $input['color'] ) : '';

		if ( '' !== $color ) {
			$design['color'] = $color;
		}

		// "Auto" text, second and border colours are stored as ''.
		$design['text']   = empty( $input['text_auto'] ) && is_string( $input['text'] ?? null ) ? self::hex( $input['text'] ) : '';
		$design['color2'] = empty( $input['color2_auto'] ) && is_string( $input['color2'] ?? null ) ? self::hex( $input['color2'] ) : '';
		$design['radius'] = is_numeric( $input['radius'] ?? null ) ? max( 0, min( self::PILL, (int) $input['radius'] ) ) : $design['radius'];

		$design['border_width'] = is_numeric( $input['border_width'] ?? null ) ? max( 0, min( self::BORDER_MAX, (int) $input['border_width'] ) ) : $design['border_width'];
		$design['border_color'] = empty( $input['border_color_auto'] ) && is_string( $input['border_color'] ?? null ) ? self::hex( $input['border_color'] ) : '';

		$design['stops_shape'] = $choice( 'stops_shape', self::SPECTRUM_SHAPES, $design['stops_shape'] );
		$design['stops_angle'] = is_numeric( $input['stops_angle'] ?? null ) ? max( 0, min( 360, (int) $input['stops_angle'] ) ) : $design['stops_angle'];
		$design['stops']       = self::sanitize_stops( $input['stops'] ?? null, $design['stops'] );

		foreach ( array( 'full', 'shadow', 'upper', 'logos', 'still', 'stops_flow' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$design[ $flag ] = ! empty( $input[ $flag ] );
			}
		}

		return $design;
	}

	/**
	 * The spectrum's colour stops, cleaned. Fewer than two good ones keeps the fallback.
	 *
	 * @param mixed                                                              $raw      The raw stops.
	 * @param array<int, array{color: string, pos: int, spread: int, blur: int}> $fallback What to keep when there aren't enough.
	 * @return array<int, array{color: string, pos: int, spread: int, blur: int}>
	 */
	private static function sanitize_stops( $raw, array $fallback ): array {
		if ( ! is_array( $raw ) ) {
			return $fallback;
		}

		$percent = static fn( $value, int $fallback ): int => is_numeric( $value ) ? max( 0, min( 100, (int) $value ) ) : $fallback;
		$stops   = array();

		foreach ( array_slice( array_values( $raw ), 0, self::SPECTRUM_MAX ) as $stop ) {
			$color = is_array( $stop ) && is_string( $stop['color'] ?? null ) ? self::hex( $stop['color'] ) : '';

			if ( '' === $color ) {
				continue;
			}

			$stops[] = array(
				'color'  => $color,
				'pos'    => $percent( $stop['pos'] ?? null, 0 ),
				'spread' => $percent( $stop['spread'] ?? null, 20 ),
				'blur'   => $percent( $stop['blur'] ?? null, 100 ),
			);
		}

		return count( $stops ) >= self::SPECTRUM_MIN ? $stops : $fallback;
	}

	/**
	 * The spectrum as a CSS gradient. Each stop is a colour that holds its place: `spread` is how wide it
	 * reaches round its position, and `blur` how much of that reach melts into its neighbours (0 is a hard
	 * edge). buttons design.js draws the same.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 */
	public static function spectrum_gradient( array $design ): string {
		$stops = self::sorted_stops( $design );
		$parts = array();
		$floor = 0.0;

		foreach ( $stops as $stop ) {
			$half  = $stop['spread'] / 2;
			$soft  = $stop['blur'] / 100 * $half;
			$start = $stop['pos'] - $half;
			$end   = $stop['pos'] + $half;
			// A side that runs off the button has no neighbour to melt into.
			$from = max( $floor, min( 100.0, max( 0.0, $start ) + ( $start < 0 ? 0 : $soft ) ) );
			$to   = max( $from, min( 100.0, $end - ( $end > 100 ? 0 : $soft ) ) );

			$parts[] = $stop['color'] . ' ' . round( $from, 2 ) . '%';

			if ( $to > $from ) {
				$parts[] = $stop['color'] . ' ' . round( $to, 2 ) . '%';
			}

			$floor = $to;
		}

		$list = implode( ',', $parts );

		if ( 'radial' === $design['stops_shape'] ) {
			return 'radial-gradient(ellipse at center,' . $list . ')';
		}

		if ( 'conic' === $design['stops_shape'] ) {
			return 'conic-gradient(from ' . (int) $design['stops_angle'] . 'deg at 50% 50%,' . $list . ')';
		}

		return 'linear-gradient(' . (int) $design['stops_angle'] . 'deg,' . $list . ')';
	}

	/**
	 * The stops from the start of the button to its end.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @return array<int, array{color: string, pos: int, spread: int, blur: int}>
	 */
	private static function sorted_stops( array $design ): array {
		$stops = array_values( $design['stops'] );

		usort( $stops, static fn( array $a, array $b ): int => $a['pos'] <=> $b['pos'] );

		return $stops;
	}

	/**
	 * Black or white by what reads on the most stops: the one whose worst contrast is best.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 */
	private static function spectrum_on_color( array $design ): string {
		$worst = array(
			self::INK   => 22.0,
			self::WHITE => 22.0,
		);

		foreach ( $design['stops'] as $stop ) {
			$lum = self::luminance( $stop['color'] );

			$worst[ self::INK ]   = min( $worst[ self::INK ], ( $lum + 0.05 ) / ( self::luminance( self::INK ) + 0.05 ) );
			$worst[ self::WHITE ] = min( $worst[ self::WHITE ], 1.05 / ( $lum + 0.05 ) );
		}

		return $worst[ self::INK ] >= $worst[ self::WHITE ] ? self::INK : self::WHITE;
	}

	/**
	 * The classes and inline style the <button> gets on the frontend.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @return array{class: string, style: string}
	 */
	public static function attributes( array $design ): array {
		$classes = array( 'iftp-btn-pay__button' );

		// Bare: not even the theme's classes, so nothing but the merchant's code styles it.
		if ( self::SOURCE_CODE === $design['source'] ) {
			return array(
				'class' => 'iftp-btn-pay__button iftp-btn-pay__button--code',
				'style' => '',
			);
		}

		if ( self::SOURCE_CUSTOM !== $design['source'] ) {
			// The theme's own button classes, so it looks like every other button on the site.
			array_unshift( $classes, 'wp-block-button__link', 'wp-element-button' );

			return array(
				'class' => implode( ' ', array_merge( $classes, self::shared_modifiers( $design ) ) ),
				'style' => '',
			);
		}

		$classes[] = 'iftp-btn-pay__button--custom';
		$classes[] = 'iftp-btn-pay__button--' . $design['variant'];
		$classes[] = 'iftp-btn-pay__button--' . $design['size'];

		if ( $design['shadow'] ) {
			$classes[] = 'iftp-btn-pay__button--shadow';
		}

		if ( 'none' !== $design['effect'] ) {
			$classes[] = 'iftp-btn-pay__button--fx-' . $design['effect'];
		}

		if ( 'none' !== $design['attention'] ) {
			$classes[] = 'iftp-btn-pay__button--cta-' . $design['attention'];
		}

		if ( '' !== $design['border_color'] ) {
			$classes[] = 'iftp-btn-pay__button--own-border';
		}

		if ( $design['upper'] ) {
			$classes[] = 'iftp-btn-pay__button--upper';
		}

		if ( $design['still'] ) {
			$classes[] = 'iftp-btn-pay__button--still';
		}

		// Only a straight gradient can slide along.
		if ( 'spectrum' === $design['variant'] && $design['stops_flow'] && 'linear' === $design['stops_shape'] ) {
			$classes[] = 'iftp-btn-pay__button--flow';
		}

		return array(
			'class' => implode( ' ', array_merge( $classes, self::shared_modifiers( $design ) ) ),
			'style' => self::style( $design ),
		);
	}

	/**
	 * The same look as a non-interactive <span>, for the admin list and the editor preview. The theme's look
	 * gets a stand-in class there, since wp-admin doesn't load the theme.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @param string               $label  The text, already including the amount if shown.
	 */
	public static function preview_markup( array $design, string $label ): string {
		$attributes = self::attributes( $design );
		$class      = in_array( $design['source'], array( self::SOURCE_CUSTOM, self::SOURCE_CODE ), true ) ? $attributes['class'] : 'iftp-btn-pay__button iftp-btn-pay__button--theme ' . implode( ' ', self::shared_modifiers( $design ) );

		return '<span class="' . esc_attr( trim( $class ) ) . '" style="' . esc_attr( $attributes['style'] ) . '">' . self::inner_html( $design, $label ) . '</span>';
	}

	/**
	 * What goes inside the button: the icon, if any, and the text.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @param string               $label  The text.
	 */
	public static function inner_html( array $design, string $label ): string {
		$split = 'split' === $design['variant'] && self::SOURCE_CUSTOM === $design['source'];
		// Split's end block always holds an icon, an arrow when none is picked.
		$icon = self::icon( $split && 'none' === $design['icon'] ? 'arrow' : (string) $design['icon'], (string) ( $design['icon_style'] ?? 'outline' ) );
		$text = '<span class="iftp-btn-pay__label">' . esc_html( $label ) . '</span>';

		if ( '' === $icon ) {
			return $text;
		}

		// The arrow points onwards, so it goes after the text; so does anything in split's end block.
		return $split || 'arrow' === $design['icon'] ? $text . $icon : $icon . $text;
	}

	/**
	 * The inner SVG paths of every icon, by name (24 by 24, drawn with a 2px stroke in currentColor).
	 *
	 * @return array<string, string>
	 */
	public static function icon_paths(): array {
		return array(
			'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/>',
			'card'     => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M3 10h18M7 15h3"/>',
			'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
			'cart'     => '<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2.4 11a1.5 1.5 0 0 0 1.5 1.2h7.8a1.5 1.5 0 0 0 1.4-1.1L20 8H6.2"/>',
			'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7M7.5 8a2.5 2.5 0 0 1 0-5C10 3 12 8 12 8s2-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
			'ticket'   => '<path d="M5 5h14a2 2 0 0 1 2 2v2.5a2.5 2.5 0 0 0 0 5V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2.5 2.5 0 0 0 0-5V7a2 2 0 0 1 2-2z"/><path d="M14 5v2.5M14 10.8v2.4M14 16.5V19"/>',
			'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M8 2.5v4M16 2.5v4M3 10h18"/>',
			'star'     => '<path d="m12 3 2.8 5.6 6.2.9-4.5 4.4 1 6.1-5.5-2.9-5.5 2.9 1-6.1L3 9.5l6.2-.9z"/>',
			'sparkles' => '<path d="m11 3 1.9 5.1L18 10l-5.1 1.9L11 17l-1.9-5.1L4 10l5.1-1.9z"/><path d="m19 15 .8 2.2 2.2.8-2.2.8L19 21l-.8-2.2-2.2-.8 2.2-.8z"/>',
			'paw'      => '<g><ellipse cx="4.2" cy="11" rx="2" ry="2.8" transform="rotate(-25 4.2 11)"/><ellipse cx="8.6" cy="5.4" rx="2.1" ry="3" transform="rotate(-10 8.6 5.4)"/><ellipse cx="15.4" cy="5.4" rx="2.1" ry="3" transform="rotate(10 15.4 5.4)"/><ellipse cx="19.8" cy="11" rx="2" ry="2.8" transform="rotate(25 19.8 11)"/><path d="M12 11.6c-3.3 0-6.6 3.4-6.6 6.6 0 2.2 1.6 3.6 3.6 3.6 1.2 0 2-.6 3-.6s1.8.6 3 .6c2 0 3.6-1.4 3.6-3.6 0-3.2-3.3-6.6-6.6-6.6z"/></g>',
			'leaf'     => '<path d="M5 20c0-9 5.5-15 15-15 0 10-6 15-14 15"/><path d="M5 20c2.5-4.5 6-7.5 10-9.5"/>',
			'bolt'     => '<path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12z"/>',
		);
	}

	/**
	 * An icon's inline SVG, drawn in currentColor. '' for none.
	 *
	 * @param string $icon  One of ICONS.
	 * @param string $style One of ICON_STYLES: outline, or filled and duotone, which the stylesheet draws.
	 */
	public static function icon( string $icon, string $style = 'outline' ): string {
		$paths = self::icon_paths();

		if ( ! isset( $paths[ $icon ] ) ) {
			return '';
		}

		// The arrow is only lines, so it has no filled or duotone.
		$class = 'iftp-btn-pay__icon' . ( 'arrow' !== $icon && in_array( $style, array( 'filled', 'duotone' ), true ) ? ' iftp-btn-icon--' . $style : '' );

		return '<svg class="' . $class . '" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $icon ] . '</svg>';
	}

	/**
	 * The text colour: the one picked, or black/white by contrast on a solid or gradient button (against the
	 * gradient's middle), or the main colour otherwise.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 */
	public static function text_color( array $design ): string {
		if ( '' !== $design['text'] ) {
			return (string) $design['text'];
		}

		if ( 'spectrum' === $design['variant'] ) {
			return self::spectrum_on_color( $design );
		}

		if ( in_array( $design['variant'], array( 'aurora', 'luxe' ), true ) ) {
			return self::on_color( self::mix( (string) $design['color'], self::second_color( $design ) ) );
		}

		// The glazed donut's label sits on its icing, the second colour.
		if ( 'glazed' === $design['variant'] ) {
			return self::on_color( self::second_color( $design ) );
		}

		// The beam's and the scanline's plates are dark whatever the colour, which only lights their edge.
		if ( in_array( $design['variant'], array( 'beam', 'scanline' ), true ) ) {
			return '#ffffff';
		}

		if ( ! in_array( $design['variant'], self::SOLID_VARIANTS, true ) ) {
			return (string) $design['color'];
		}

		return self::on_color( (string) $design['color'] );
	}

	/**
	 * The gradient's second colour: the one picked, or the first a little round the colour wheel.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 */
	public static function second_color( array $design ): string {
		if ( '' !== ( $design['color2'] ?? '' ) ) {
			return (string) $design['color2'];
		}

		return self::FOOD_SECOND[ $design['variant'] ?? '' ] ?? self::turn( (string) $design['color'], self::GRADIENT_TURN );
	}

	/**
	 * A colour turned round the colour wheel, keeping its lightness and saturation. buttons.js does the same.
	 *
	 * @param string $hex     A #rrggbb colour.
	 * @param int    $degrees How far to turn it.
	 */
	public static function turn( string $hex, int $degrees ): string {
		list( $r, $g, $b ) = array_map( static fn( int $channel ): float => $channel / 255, self::rgb( $hex ) );

		$max   = max( $r, $g, $b );
		$min   = min( $r, $g, $b );
		$light = ( $max + $min ) / 2;
		$range = $max - $min;

		// A grey has no hue to turn.
		if ( $range <= 0.0 ) {
			return $hex;
		}

		$sat = $light > 0.5 ? $range / ( 2 - $max - $min ) : $range / ( $max + $min );

		if ( $max === $r ) {
			$hue = ( $g - $b ) / $range + ( $g < $b ? 6 : 0 );
		} elseif ( $max === $g ) {
			$hue = ( $b - $r ) / $range + 2;
		} else {
			$hue = ( $r - $g ) / $range + 4;
		}

		$hue = fmod( $hue * 60 + $degrees, 360 ) / 360;
		$q   = $light < 0.5 ? $light * ( 1 + $sat ) : $light + $sat - $light * $sat;
		$p   = 2 * $light - $q;
		$to  = static function ( float $t ) use ( $p, $q ): float {
			if ( $t < 0 ) {
				++$t;
			} elseif ( $t > 1 ) {
				--$t;
			}

			if ( $t < 1 / 6 ) {
				return $p + ( $q - $p ) * 6 * $t;
			}

			if ( $t < 1 / 2 ) {
				return $q;
			}

			return $t < 2 / 3 ? $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6 : $p;
		};

		// floor( + 0.5 ) rounds like JavaScript, so the preview lands on the same colour.
		return sprintf( '#%02x%02x%02x', (int) floor( $to( $hue + 1 / 3 ) * 255 + 0.5 ), (int) floor( $to( $hue ) * 255 + 0.5 ), (int) floor( $to( $hue - 1 / 3 ) * 255 + 0.5 ) );
	}

	/**
	 * Halfway between two colours.
	 *
	 * @param string $one A #rrggbb colour.
	 * @param string $two Another.
	 */
	private static function mix( string $one, string $two ): string {
		$a = self::rgb( $one );
		$b = self::rgb( $two );

		return sprintf( '#%02x%02x%02x', (int) floor( ( $a[0] + $b[0] ) / 2 + 0.5 ), (int) floor( ( $a[1] + $b[1] ) / 2 + 0.5 ), (int) floor( ( $a[2] + $b[2] ) / 2 + 0.5 ) );
	}

	/**
	 * Black or white, whichever reads better on a colour.
	 *
	 * @param string $hex A #rrggbb colour.
	 */
	public static function on_color( string $hex ): string {
		// Above 0.179, black text out-contrasts white.
		return self::luminance( $hex ) > 0.179 ? self::INK : self::WHITE;
	}

	/**
	 * Width and anything else that works with the theme's look too.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 * @return array<int, string>
	 */
	private static function shared_modifiers( array $design ): array {
		return $design['full'] ? array( 'iftp-btn-pay__button--full' ) : array();
	}

	/**
	 * The CSS variables checkout.css draws the custom look from.
	 *
	 * @param array<string, mixed> $design A sanitized design.
	 */
	private static function style( array $design ): string {
		// The spectrum has no single colour; its glow and tints take the middle stop's.
		if ( 'spectrum' === $design['variant'] ) {
			$middle          = self::sorted_stops( $design );
			$design['color'] = $middle[ intdiv( count( $middle ), 2 ) ]['color'];
		}

		$rgb    = self::rgb( (string) $design['color'] );
		$radius = self::PILL <= $design['radius'] ? '999px' : (int) $design['radius'] . 'px';
		$style  = sprintf(
			'--iftp-pb-color:%1$s;--iftp-pb-color2:%5$s;--iftp-pb-text:%2$s;--iftp-pb-on:%6$s;--iftp-pb-radius:%3$s;--iftp-pb-bw:%7$dpx;--iftp-pb-bs:%8$s;--iftp-pb-tint:rgb(%4$s / 14%%);--iftp-pb-tint-strong:rgb(%4$s / 24%%);--iftp-pb-glass:rgb(%4$s / 62%%);--iftp-pb-glow:rgb(%4$s / 45%%)',
			$design['color'],
			self::text_color( $design ),
			$radius,
			implode( ' ', $rgb ),
			self::second_color( $design ),
			self::on_color( (string) $design['color'] ),
			(int) $design['border_width'],
			$design['border_style']
		);

		if ( 'spectrum' === $design['variant'] ) {
			$style .= ';--iftp-pb-spectrum:' . self::spectrum_gradient( $design );
		}

		// What reads on the second colour, for split's end block.
		$style .= ';--iftp-pb-on2:' . self::on_color( self::second_color( $design ) );

		if ( isset( CheckoutLook::FONT_STACKS[ $design['font'] ] ) ) {
			$style .= ';--iftp-pb-font:' . CheckoutLook::FONT_STACKS[ $design['font'] ];
		}

		return '' !== $design['border_color'] ? $style . ';--iftp-pb-bc:' . $design['border_color'] : $style;
	}

	/**
	 * A #rrggbb colour, lower-case, or '' when it isn't one. Short #rgb is expanded.
	 *
	 * @param string $value The raw colour.
	 */
	public static function hex( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( 1 === preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $short ) ) {
			$value = '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
		}

		return 1 === preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * A #rrggbb colour's channels.
	 *
	 * @param string $hex A #rrggbb colour.
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function rgb( string $hex ): array {
		return array( (int) hexdec( substr( $hex, 1, 2 ) ), (int) hexdec( substr( $hex, 3, 2 ) ), (int) hexdec( substr( $hex, 5, 2 ) ) );
	}

	/**
	 * WCAG relative luminance, 0 (black) to 1 (white).
	 *
	 * @param string $hex A #rrggbb colour.
	 */
	private static function luminance( string $hex ): float {
		$channels = array_map(
			static function ( int $channel ): float {
				$value = $channel / 255;

				return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
			},
			self::rgb( $hex )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}
}
