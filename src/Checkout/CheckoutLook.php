<?php
/**
 * How the checkout and the outcome popups look, site-wide.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A handful of base choices (colours, corners, field style, width, backdrop) that become CSS variables over
 * checkout.css's tokens; the shades in between are mixed in CSS from these. Then the merchant's own CSS, last.
 *
 * @phpstan-type Look array{accent: string, accent_text: string, surface: string, ink: string, radius: int, field_radius: int, fields: string, font: string, width: string, backdrop: string, paid: string, pending: string, failed: string, popup_icon: string, popup_align: string, steps: string, logos: bool, secure: bool, pay_bg: string, pay_color: string, pay_image: int, pay_dim: int, pay_logo: bool, pay_back: bool, pay_grad: string, pay_color2: string, pay_fx: string, pay_fx_size: int, pay_fx_strength: int, pay_blur_type: string, pay_blur: int, pay_video_src: string, pay_video: int, pay_video_url: string, pay_video_blur: int, pay_video_loop: string, pay_video_start: int, pay_video_end: int, card_opacity: int, card_blur: int, dark: string, dark_surface: string, dark_ink: string, dark_accent: string, scene: bool, css: string}
 */
class CheckoutLook {

	public const FIELD_STYLES = array( 'outline', 'soft', 'underline' );
	public const FONTS        = array( 'system', 'theme' );
	public const WIDTHS       = array(
		'compact' => 400,
		'regular' => 440,
		'wide'    => 520,
	);
	public const BACKDROPS    = array( 'blur', 'dim', 'light' );
	public const POPUP_ICONS  = array( 'soft', 'solid' );
	public const POPUP_ALIGNS = array( 'center', 'left' );
	public const STEP_STYLES  = array( 'bars', 'labels', 'line', 'numbers', 'dots', 'ring', 'text' );
	public const PAY_BGS      = array( 'glow', 'plain', 'gradient', 'image', 'video' );
	public const PAY_GRADS    = array( 'soft', 'aurora', 'diagonal' );
	public const PAY_FX       = array( 'none', 'crystals', 'waves', 'bokeh', 'grain' );
	public const PAY_BLURS    = array( 'none', 'soft', 'frosted', 'tilt', 'vignette' );
	public const VIDEO_SRCS   = array( 'file', 'link' );
	public const VIDEO_TYPES  = array( 'video/mp4', 'video/webm' );
	public const VIDEO_LOOPS  = array( 'all', 'part' );
	public const DARK_MODES   = array( 'off', 'auto' );

	// The colours that change in dark mode; checkout.css swaps each for its --iftp-dark-* twin.
	public const DARK_VARS = array( '--iftp-brand', '--iftp-on-brand', '--iftp-surface', '--iftp-ink', '--iftp-card-bg', '--iftp-chip-bg', '--iftp-logo-filter', '--iftp-page-bg', '--iftp-page-glow', '--iftp-page-glow2', '--iftp-page-orb', '--iftp-page-orb2' );

	// The generated patterns repeat every this many facets (or wave spacings) each way.
	public const CRYSTAL_CELLS = 6;

	// Lines in one wave tile, and lights in one bokeh tile.
	private const WAVE_LINES   = 12;
	private const BOKEH_LIGHTS = 22;

	public const MAX_RADIUS       = 32;
	public const MAX_FIELD_RADIUS = 24;

	// Everything the checkout and popups print on, for the selectors below and the admin previews.
	public const SCOPE = '.iftp-btn-pay,.iftp-btn-sheet,.iftp-btn-page-body,.iftp-btn-look';

	/**
	 * Today's look: the ifthenpay one.
	 *
	 * @return Look
	 */
	public static function defaults(): array {
		return array(
			'accent'          => '#00609c',
			'accent_text'     => '',
			'surface'         => '#fdfdfe',
			'ink'             => '#121a2c',
			'radius'          => 20,
			'field_radius'    => 10,
			'fields'          => 'outline',
			'font'            => 'system',
			'width'           => 'regular',
			'backdrop'        => 'blur',
			'paid'            => '#0b6b35',
			'pending'         => '#8a5300',
			'failed'          => '#a3201f',
			'popup_icon'      => 'soft',
			'popup_align'     => 'center',
			'steps'           => 'bars',
			'logos'           => true,
			'secure'          => true,
			'pay_bg'          => 'glow',
			'pay_color'       => '',
			'pay_image'       => 0,
			'pay_dim'         => 35,
			'pay_logo'        => false,
			'pay_back'        => true,
			'pay_grad'        => 'soft',
			'pay_color2'      => '',
			'pay_fx'          => 'none',
			'pay_fx_size'     => 140,
			'pay_fx_strength' => 50,
			'pay_blur_type'   => 'none',
			'pay_blur'        => 12,
			'pay_video_src'   => 'file',
			'pay_video'       => 0,
			'pay_video_url'   => '',
			'pay_video_blur'  => 0,
			'pay_video_loop'  => 'all',
			'pay_video_start' => 0,
			'pay_video_end'   => 0,
			'card_opacity'    => 100,
			'card_blur'       => 16,
			'dark'            => 'off',
			'dark_surface'    => '#131a2a',
			'dark_ink'        => '#eef2f8',
			'dark_accent'     => '',
			'scene'           => true,
			'css'             => '',
		);
	}

	/**
	 * Ready-made looks for the Design tab. Each sets colours and shape; the merchant's CSS and footer stay.
	 *
	 * @return array<string, array<string, string|int>>
	 */
	public static function presets(): array {
		return array(
			'ifthenpay' => array(
				'label'        => __( 'ifthenpay', 'ifthenpay-pay-button' ),
				'accent'       => '#00609c',
				'surface'      => '#fdfdfe',
				'ink'          => '#121a2c',
				'radius'       => 20,
				'field_radius' => 10,
				'fields'       => 'outline',
			),
			'midnight'  => array(
				'label'        => __( 'Midnight', 'ifthenpay-pay-button' ),
				'accent'       => '#7cc4ff',
				'surface'      => '#141c2f',
				'ink'          => '#eef2f8',
				'radius'       => 22,
				'field_radius' => 12,
				'fields'       => 'soft',
			),
			'paper'     => array(
				'label'        => __( 'Paper', 'ifthenpay-pay-button' ),
				'accent'       => '#1f1f1f',
				'surface'      => '#fbfaf7',
				'ink'          => '#1f1f1f',
				'radius'       => 6,
				'field_radius' => 0,
				'fields'       => 'underline',
			),
			'meadow'    => array(
				'label'        => __( 'Meadow', 'ifthenpay-pay-button' ),
				'accent'       => '#0b6b35',
				'surface'      => '#fbfdfb',
				'ink'          => '#15241b',
				'radius'       => 28,
				'field_radius' => 16,
				'fields'       => 'soft',
			),
		);
	}

	/**
	 * The look a button's checkout, pay page and popups use: its own, or the site's.
	 *
	 * @param Button|null      $button   The button, if there is one.
	 * @param CheckoutSettings $settings Where the site's look is stored.
	 * @return Look
	 */
	public static function for_button( ?Button $button, CheckoutSettings $settings ): array {
		return null !== $button && Button::LOOK_OWN === $button->config['look_mode'] ? $button->config['look'] : $settings->get_look();
	}

	/**
	 * A button's own look as an inline style, so it beats the site's look printed in the stylesheet. '' when it uses the site's.
	 *
	 * @param Button|null $button The button.
	 */
	public static function inline_style( ?Button $button ): string {
		if ( null === $button || Button::LOOK_OWN !== $button->config['look_mode'] ) {
			return '';
		}

		$style = '';

		foreach ( self::variables( $button->config['look'] ) as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}

		return $style;
	}

	/**
	 * Cleans a look from anywhere, so every key is there and safe to print into CSS.
	 *
	 * @param mixed $input The raw look.
	 * @return Look
	 */
	public static function sanitize( $input ): array {
		$look  = self::defaults();
		$input = is_array( $input ) ? $input : array();

		foreach ( array( 'accent', 'surface', 'ink', 'paid', 'pending', 'failed' ) as $key ) {
			$color = is_string( $input[ $key ] ?? null ) ? ButtonDesign::hex( $input[ $key ] ) : '';

			if ( '' !== $color ) {
				$look[ $key ] = $color;
			}
		}

		$look['accent_text'] = empty( $input['accent_text_auto'] ) && is_string( $input['accent_text'] ?? null ) ? ButtonDesign::hex( $input['accent_text'] ) : '';

		$choice = static fn( string $key, array $allowed, string $fallback ): string => is_string( $input[ $key ] ?? null ) && in_array( $input[ $key ], $allowed, true ) ? $input[ $key ] : $fallback;

		$look['fields']      = $choice( 'fields', self::FIELD_STYLES, $look['fields'] );
		$look['font']        = $choice( 'font', self::FONTS, $look['font'] );
		$look['width']       = $choice( 'width', array_keys( self::WIDTHS ), $look['width'] );
		$look['backdrop']    = $choice( 'backdrop', self::BACKDROPS, $look['backdrop'] );
		$look['popup_icon']  = $choice( 'popup_icon', self::POPUP_ICONS, $look['popup_icon'] );
		$look['popup_align'] = $choice( 'popup_align', self::POPUP_ALIGNS, $look['popup_align'] );
		$look['steps']       = $choice( 'steps', self::STEP_STYLES, $look['steps'] );

		$look['radius']       = is_numeric( $input['radius'] ?? null ) ? max( 0, min( self::MAX_RADIUS, (int) $input['radius'] ) ) : $look['radius'];
		$look['field_radius'] = is_numeric( $input['field_radius'] ?? null ) ? max( 0, min( self::MAX_FIELD_RADIUS, (int) $input['field_radius'] ) ) : $look['field_radius'];

		$look['pay_bg']    = $choice( 'pay_bg', self::PAY_BGS, $look['pay_bg'] );
		$look['pay_color'] = empty( $input['pay_color_auto'] ) && is_string( $input['pay_color'] ?? null ) ? ButtonDesign::hex( $input['pay_color'] ) : '';
		$look['pay_image'] = is_numeric( $input['pay_image'] ?? null ) && wp_attachment_is_image( (int) $input['pay_image'] ) ? (int) $input['pay_image'] : 0;
		$look['pay_dim']   = is_numeric( $input['pay_dim'] ?? null ) ? max( 0, min( 80, (int) $input['pay_dim'] ) ) : $look['pay_dim'];

		$look['pay_grad']        = $choice( 'pay_grad', self::PAY_GRADS, $look['pay_grad'] );
		$look['pay_fx']          = $choice( 'pay_fx', self::PAY_FX, $look['pay_fx'] );
		$look['pay_blur_type']   = $choice( 'pay_blur_type', self::PAY_BLURS, $look['pay_blur_type'] );
		$look['pay_color2']      = empty( $input['pay_color2_auto'] ) && is_string( $input['pay_color2'] ?? null ) ? ButtonDesign::hex( $input['pay_color2'] ) : '';
		$look['pay_fx_size']     = is_numeric( $input['pay_fx_size'] ?? null ) ? max( 40, min( 400, (int) $input['pay_fx_size'] ) ) : $look['pay_fx_size'];
		$look['pay_fx_strength'] = is_numeric( $input['pay_fx_strength'] ?? null ) ? max( 0, min( 100, (int) $input['pay_fx_strength'] ) ) : $look['pay_fx_strength'];
		$look['pay_blur']        = is_numeric( $input['pay_blur'] ?? null ) ? max( 0, min( 40, (int) $input['pay_blur'] ) ) : $look['pay_blur'];

		$look['pay_video_src']  = $choice( 'pay_video_src', self::VIDEO_SRCS, $look['pay_video_src'] );
		$look['pay_video']      = is_numeric( $input['pay_video'] ?? null ) && self::is_video( (int) $input['pay_video'] ) ? (int) $input['pay_video'] : 0;
		$look['pay_video_url']  = is_string( $input['pay_video_url'] ?? null ) && '' !== self::embed_url( trim( $input['pay_video_url'] ) ) ? esc_url_raw( trim( $input['pay_video_url'] ) ) : '';
		$look['pay_video_blur'] = is_numeric( $input['pay_video_blur'] ?? null ) ? max( 0, min( 30, (int) $input['pay_video_blur'] ) ) : $look['pay_video_blur'];
		$look['pay_video_loop'] = $choice( 'pay_video_loop', self::VIDEO_LOOPS, $look['pay_video_loop'] );

		$look['pay_video_start'] = self::seconds( $input['pay_video_start'] ?? 0 );
		$look['pay_video_end']   = self::seconds( $input['pay_video_end'] ?? 0 );

		// An end at or before the start means "to the end of the video".
		if ( $look['pay_video_end'] <= $look['pay_video_start'] ) {
			$look['pay_video_end'] = 0;
		}

		$look['card_opacity'] = is_numeric( $input['card_opacity'] ?? null ) ? max( 0, min( 100, (int) $input['card_opacity'] ) ) : $look['card_opacity'];
		$look['card_blur']    = is_numeric( $input['card_blur'] ?? null ) ? max( 0, min( 40, (int) $input['card_blur'] ) ) : $look['card_blur'];

		$look['dark'] = $choice( 'dark', self::DARK_MODES, $look['dark'] );

		foreach ( array( 'dark_surface', 'dark_ink' ) as $key ) {
			$color = is_string( $input[ $key ] ?? null ) ? ButtonDesign::hex( $input[ $key ] ) : '';

			if ( '' !== $color ) {
				$look[ $key ] = $color;
			}
		}

		$look['dark_accent'] = empty( $input['dark_accent_auto'] ) && is_string( $input['dark_accent'] ?? null ) ? ButtonDesign::hex( $input['dark_accent'] ) : '';

		// An image background without an image is just the glow; so is a video one without a video.
		if ( ( 'image' === $look['pay_bg'] && 0 === $look['pay_image'] ) || ( 'video' === $look['pay_bg'] && null === self::video( $look ) ) ) {
			$look['pay_bg'] = 'glow';
		}

		foreach ( array( 'logos', 'secure', 'pay_logo', 'pay_back', 'scene' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$look[ $flag ] = ! empty( $input[ $flag ] );
			}
		}

		$look['css'] = is_string( $input['css'] ?? null ) ? self::clean_css( $input['css'] ) : '';

		return $look;
	}

	/**
	 * The look as CSS: the variables, then the merchant's own rules.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function css( array $look ): string {
		$vars = self::variables( $look );
		$css  = self::SCOPE . '{';

		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		$css .= '}';

		// The theme's font: a CSS-wide keyword in a variable would make the variable inherit, not the font.
		if ( 'theme' === $look['font'] ) {
			$css .= '.iftp-btn-sheet,.iftp-btn-form{font-family:inherit}';
		}

		return $css . ( '' !== $look['css'] ? "\n" . $look['css'] : '' );
	}

	/**
	 * The CSS variables for a look, by name. The admin preview sets the same ones live.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 * @return array<string, string>
	 */
	public static function variables( array $look ): array {
		$fields    = array(
			'outline'   => array( 'var(--iftp-chip-bg)', 'var(--iftp-line-strong)', '1px', $look['field_radius'] . 'px', '12px' ),
			'soft'      => array( 'var(--iftp-inset)', 'transparent', '1px', $look['field_radius'] . 'px', '12px' ),
			'underline' => array( 'transparent', 'var(--iftp-line-strong)', '0 0 1.5px', '0', '2px' ),
		);
		$field     = $fields[ $look['fields'] ];
		$backdrops = array(
			'blur'  => array( 'rgb(12 22 40 / 48%)', '3px' ),
			'dim'   => array( 'rgb(12 22 40 / 62%)', '0px' ),
			'light' => array( 'rgb(250 251 253 / 70%)', '8px' ),
		);
		$glass     = $look['card_opacity'] < 100;

		return self::palette( $look ) + array(
			'--iftp-paid'            => $look['paid'],
			'--iftp-pending'         => $look['pending'],
			'--iftp-failed'          => $look['failed'],
			'--iftp-radius'          => $look['radius'] . 'px',
			'--iftp-field-r'         => $field[3],
			'--iftp-field-bg'        => $field[0],
			'--iftp-field-bd'        => $field[1],
			'--iftp-field-bw'        => $field[2],
			'--iftp-field-px'        => $field[4],
			'--iftp-field-focus'     => 'underline' === $look['fields'] ? '0 1px 0 0 var(--iftp-brand)' : 'var(--iftp-ring)',
			'--iftp-sheet-w'         => self::WIDTHS[ $look['width'] ] . 'px',
			'--iftp-backdrop'        => $backdrops[ $look['backdrop'] ][0],
			'--iftp-backdrop-blur'   => $backdrops[ $look['backdrop'] ][1],
			'--iftp-result-align'    => 'left' === $look['popup_align'] ? 'start' : 'center',
			// A see-through card: frosted behind, a fine light edge, and no opaque scroll shading inside.
			'--iftp-card-filter'     => $glass && $look['card_blur'] > 0 ? 'blur(' . $look['card_blur'] . 'px) saturate(1.6)' : 'none',
			'--iftp-card-ring'       => $glass ? 'inset 0 0 0 1px rgb(255 255 255 / 22%)' : '0 0 #0000',
			'--iftp-body-bg'         => $glass ? 'transparent' : 'initial',
			'--iftp-page-image'      => self::page_image( $look ),
			'--iftp-page-dim'        => 'rgb(0 0 0 / ' . $look['pay_dim'] . '%)',
			'--iftp-page-video-blur' => $look['pay_video_blur'] . 'px',
		) + self::dark_palette( $look ) + self::page_effects( $look );
	}

	/**
	 * The colours mixed from the look's base ones: what dark mode swaps out.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 * @return array<string, string>
	 */
	private static function palette( array $look ): array {
		$second = '' !== $look['pay_color2'] ? $look['pay_color2'] : $look['accent'];
		$card   = min( 100, $look['card_opacity'] );

		return array(
			'--iftp-brand'       => $look['accent'],
			'--iftp-on-brand'    => '' !== $look['accent_text'] ? $look['accent_text'] : ButtonDesign::on_color( $look['accent'] ),
			'--iftp-surface'     => $look['surface'],
			'--iftp-ink'         => $look['ink'],
			'--iftp-card-bg'     => $card < 100 ? self::alpha( $look['surface'], $card ) : $look['surface'],
			// Fields and choices stay a little more solid than the card, so they still read as boxes.
			'--iftp-chip-bg'     => $card < 100 ? self::alpha( $look['surface'], min( 100, $card + 30 ) ) : $look['surface'],
			// The colour logo disappears on a dark background, so it turns white there.
			'--iftp-logo-filter' => '#ffffff' === ButtonDesign::on_color( $look['surface'] ) ? 'brightness(0) invert(1)' : 'none',
			'--iftp-page-bg'     => self::page_color( $look ),
			'--iftp-page-glow'   => self::alpha( $look['accent'], 16 ),
			'--iftp-page-glow2'  => self::alpha( $second, 16 ),
			'--iftp-page-orb'    => self::alpha( $look['accent'], 45 ),
			'--iftp-page-orb2'   => self::alpha( $second, 45 ),
		);
	}

	/**
	 * The dark mode colours as --iftp-dark-* variables, which checkout.css puts in place on a payer's dark device.
	 * The pay page's own colour is a light-mode pick, so in the dark it's mixed from the dark colours instead.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 * @return array<string, string>
	 */
	private static function dark_palette( array $look ): array {
		if ( 'auto' !== $look['dark'] ) {
			return array();
		}

		$dark = array(
			'surface'     => $look['dark_surface'],
			'ink'         => $look['dark_ink'],
			'accent'      => '' !== $look['dark_accent'] ? $look['dark_accent'] : $look['accent'],
			'accent_text' => '' !== $look['dark_accent'] ? '' : $look['accent_text'],
			'pay_color'   => '',
		) + $look;
		$vars = array();

		foreach ( self::palette( $dark ) as $name => $value ) {
			$vars[ '--iftp-dark-' . substr( $name, 7 ) ] = $value;
		}

		return $vars;
	}

	/**
	 * The class that lets a payer's dark device switch the look to its dark colours, or ''.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function dark_class( array $look ): string {
		return 'auto' === $look['dark'] ? 'iftp-btn-dark' : '';
	}

	/**
	 * The pay page's gradient, pattern and blur variables. buttons.js mirrors this for the preview.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 * @return array<string, string>
	 */
	private static function page_effects( array $look ): array {
		$strength = $look['pay_fx_strength'];
		$blur     = $look['pay_blur'] . 'px';
		$masks    = array(
			'tilt'     => 'linear-gradient(to bottom, #000 0%, transparent 32%, transparent 68%, #000 100%)',
			'vignette' => 'radial-gradient(ellipse at center, transparent 30%, #000 78%)',
		);

		return array(
			'--iftp-page-crystal'      => $look['pay_fx_size'] . 'px',
			'--iftp-page-pattern'      => self::pattern( $look ),
			'--iftp-page-pattern-tile' => ( self::CRYSTAL_CELLS * $look['pay_fx_size'] ) . 'px',
			'--iftp-page-fx-light'     => 'rgb(255 255 255 / ' . round( $strength * 0.5 ) . '%)',
			'--iftp-page-fx-dark'      => 'rgb(0 0 0 / ' . round( $strength * 0.28 ) . '%)',
			'--iftp-page-fx-opacity'   => (string) round( $strength / 100, 2 ),
			'--iftp-page-blur'         => $blur,
			'--iftp-page-blur-filter'  => 'frosted' === $look['pay_blur_type'] ? 'blur(' . $blur . ') saturate(1.6) brightness(1.06)' : 'blur(' . $blur . ')',
			'--iftp-page-blur-mask'    => $masks[ $look['pay_blur_type'] ] ?? 'none',
			'--iftp-page-frost'        => 'frosted' === $look['pay_blur_type'] ? 'rgb(255 255 255 / 16%)' : 'transparent',
		);
	}

	/**
	 * The generated pattern the look's effect draws, as a CSS url(), or none.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	private static function pattern( array $look ): string {
		switch ( $look['pay_fx'] ) {
			case 'crystals':
				return self::crystals( $look['pay_fx_size'], $look['pay_fx_strength'] );
			case 'waves':
				return self::waves( $look['pay_fx_size'], $look['pay_fx_strength'] );
			case 'bokeh':
				return self::bokeh( $look['pay_fx_size'], $look['pay_fx_strength'], $look['accent'], '' !== $look['pay_color2'] ? $look['pay_color2'] : $look['accent'] );
		}

		return 'none';
	}

	/**
	 * The crystal mesh as an SVG data URI: a jittered grid of points joined into triangles, so every facet
	 * shares its edges with its neighbours. Points on the tile's far edges repeat the near ones, so it tiles
	 * without seams. A fixed seed keeps it the same on every page; buttons.js draws the same mesh for the preview.
	 *
	 * @param int $size     The facet size in pixels.
	 * @param int $strength 0–100, how much light and shade the facets get.
	 */
	public static function crystals( int $size, int $strength ): string {
		$cells = self::CRYSTAL_CELLS;
		$tile  = $cells * $size;
		$seed  = 20261022;
		// Park–Miller: small enough to give the same numbers in PHP and JavaScript.
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$points = array();

		for ( $i = 0; $i < $cells; $i++ ) {
			for ( $j = 0; $j < $cells; $j++ ) {
				$points[ $i ][ $j ] = array( $i * $size + ( $random() - 0.5 ) * $size * 0.8, $j * $size + ( $random() - 0.5 ) * $size * 0.8 );
			}
		}

		$point = static function ( int $i, int $j ) use ( $points, $cells, $tile ): array {
			$base = $points[ $i % $cells ][ $j % $cells ];

			return array( $base[0] + ( $i >= $cells ? $tile : 0 ), $base[1] + ( $j >= $cells ? $tile : 0 ) );
		};

		$light  = $strength / 100;
		$shapes = '';

		for ( $i = 0; $i < $cells; $i++ ) {
			for ( $j = 0; $j < $cells; $j++ ) {
				$a = $point( $i, $j );
				$b = $point( $i + 1, $j );
				$c = $point( $i + 1, $j + 1 );
				$d = $point( $i, $j + 1 );

				$triangles = $random() < 0.5 ? array( array( $a, $b, $c ), array( $a, $c, $d ) ) : array( array( $a, $b, $d ), array( $b, $c, $d ) );

				foreach ( $triangles as $triangle ) {
					$shade = $random() * 2 - 1;
					$fill  = $shade > 0 ? '#fff' : '#000';
					$alpha = round( abs( $shade ) * $light * ( $shade > 0 ? 0.55 : 0.25 ), 3 );

					// A facet that crosses the tile's edge is drawn again on the other side, so the seams close.
					foreach ( self::tile_offsets( $triangle, $tile ) as $offset ) {
						$path = '';

						foreach ( $triangle as $at => $corner ) {
							$path .= ( 0 === $at ? 'M' : 'L' ) . round( $corner[0] + $offset[0], 1 ) . ' ' . round( $corner[1] + $offset[1], 1 );
						}

						$shapes .= sprintf( "<path d='%sZ' fill='%s' fill-opacity='%s'/>", $path, $fill, $alpha );
					}
				}
			}
		}

		$svg = sprintf(
			"<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'><g stroke='#fff' stroke-opacity='%2\$s' stroke-width='1' stroke-linejoin='round'>%3\$s</g></svg>",
			$tile,
			round( $light * 0.22, 3 ),
			$shapes
		);

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Where else a triangle has to be drawn so a tile repeats seamlessly: shifted a tile back from any edge it crosses.
	 *
	 * @param array<int, array{0: float, 1: float}> $triangle Its corners.
	 * @param int                                   $tile     The tile's size.
	 * @return array<int, array{0: int, 1: int}>
	 */
	private static function tile_offsets( array $triangle, int $tile ): array {
		$xs = array_column( $triangle, 0 );
		$ys = array_column( $triangle, 1 );
		$dx = array( 0 );
		$dy = array( 0 );

		if ( min( $xs ) < 0 ) {
			$dx[] = $tile;
		}

		if ( max( $xs ) > $tile ) {
			$dx[] = -$tile;
		}

		if ( min( $ys ) < 0 ) {
			$dy[] = $tile;
		}

		if ( max( $ys ) > $tile ) {
			$dy[] = -$tile;
		}

		$offsets = array();

		foreach ( $dx as $x ) {
			foreach ( $dy as $y ) {
				$offsets[] = array( $x, $y );
			}
		}

		return $offsets;
	}

	/**
	 * Waves as an SVG data URI: contour lines that drift closer and further apart, like silk or a relief map,
	 * with the bands between them lit and shaded. Each line's shape comes round again after a tile both ways,
	 * so it tiles without seams. buttons.js draws the same waves for the preview.
	 *
	 * @param int $size     A wave's spacing times two, in pixels.
	 * @param int $strength 0–100, how much light and shade the bands get.
	 */
	public static function waves( int $size, int $strength ): string {
		$tile   = self::CRYSTAL_CELLS * $size;
		$lines  = self::WAVE_LINES;
		$gap    = $tile / $lines;
		$steps  = 12;
		$light  = $strength / 100;
		$circle = 2 * M_PI;
		// The phases drift from line to line but meet again after a tile, so neighbours never cross.
		$y   = static function ( int $k, int $at ) use ( $gap, $steps, $lines, $circle ): float {
			$x = $at / $steps;

			return ( $k + 0.5 ) * $gap + 1.2 * $gap * ( sin( $circle * $x + 0.6 + 0.9 * sin( $circle * $k / $lines ) ) + 0.4 * sin( 2 * $circle * $x + 2.1 + 0.7 * cos( $circle * $k / $lines ) ) );
		};
		$num = static fn( float $value ): string => (string) self::js_round( $value, 10 );
		$x   = static fn( int $at ): float => $at * $tile / $steps;
		// A line as smooth curves (Catmull-Rom), from its left end or, backwards, from its right.
		$curve = static function ( int $k, bool $back, float $dy ) use ( $y, $x, $steps, $num ): string {
			$path = '';

			for ( $n = 0; $n < $steps; $n++ ) {
				$from = $back ? $steps - $n : $n;
				$to   = $back ? $from - 1 : $from + 1;
				$prev = $back ? $from + 1 : $from - 1;
				$next = $back ? $to - 1 : $to + 1;

				$path .= 'C' . $num( $x( $from ) + ( $x( $to ) - $x( $prev ) ) / 6 ) . ' ' . $num( $y( $k, $from ) + ( $y( $k, $to ) - $y( $k, $prev ) ) / 6 + $dy )
					. ' ' . $num( $x( $to ) - ( $x( $next ) - $x( $from ) ) / 6 ) . ' ' . $num( $y( $k, $to ) - ( $y( $k, $next ) - $y( $k, $from ) ) / 6 + $dy )
					. ' ' . $num( $x( $to ) ) . ' ' . $num( $y( $k, $to ) + $dy );
			}

			return $path;
		};
		// Where a line reaches, top and bottom, and so where else it has to be drawn for the seams to close.
		$shifts = static function ( int $top, int $low ) use ( $y, $steps, $tile ): array {
			$tops  = array();
			$lows  = array();
			$shift = array( 0 );

			for ( $at = 0; $at <= $steps; $at++ ) {
				$tops[] = $y( $top, $at );
				$lows[] = $y( $low, $at );
			}

			if ( min( $tops ) < 0 ) {
				$shift[] = $tile;
			}

			if ( max( $lows ) > $tile ) {
				$shift[] = -$tile;
			}

			return $shift;
		};
		$bands  = '';
		$edges  = '';

		for ( $k = 0; $k < $lines; $k++ ) {
			$shade = sin( $circle * 2 * ( $k + 0.5 ) / $lines );
			$fill  = $shade > 0 ? '#fff' : '#000';
			$alpha = self::js_round( abs( $shade ) * $light * ( $shade > 0 ? 0.5 : 0.3 ), 1000 );

			foreach ( $shifts( $k, $k + 1 ) as $dy ) {
				$bands .= sprintf( "<path d='M0 %s%sL%d %s%sZ' fill='%s' fill-opacity='%s'/>", $num( $y( $k, 0 ) + $dy ), $curve( $k, false, $dy ), $tile, $num( $y( $k + 1, $steps ) + $dy ), $curve( $k + 1, true, $dy ), $fill, $alpha );
			}

			foreach ( $shifts( $k, $k ) as $dy ) {
				$edges .= 'M0 ' . $num( $y( $k, 0 ) + $dy ) . $curve( $k, false, $dy );
			}
		}

		$svg = sprintf(
			"<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'>%2\$s<path d='%3\$s' fill='none' stroke='#fff' stroke-opacity='%4\$s' stroke-width='1.2'/></svg>",
			$tile,
			$bands,
			$edges,
			self::js_round( $light * 0.7, 1000 )
		);

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Bokeh as an SVG data URI: out-of-focus lights in the accent, the second colour and white, the big ones
	 * fainter, each with the brighter rim a real lens gives. Lights over the tile's edge are drawn again on the
	 * other side, so it tiles without seams. buttons.js draws the same lights for the preview.
	 *
	 * @param int    $size     A middling light's radius, in pixels.
	 * @param int    $strength 0–100, how bright the lights are.
	 * @param string $color    The accent.
	 * @param string $color2   The second colour.
	 */
	public static function bokeh( int $size, int $strength, string $color, string $color2 ): string {
		$tile = self::CRYSTAL_CELLS * $size;
		$seed = 20261023;
		// Park–Miller, as in crystals().
		$random = static function () use ( &$seed ): float {
			$seed = ( $seed * 16807 ) % 2147483647;

			return $seed / 2147483647;
		};
		$light  = $strength / 100;
		$defs   = '';
		$shapes = '';

		foreach ( array( $color, $color2, '#ffffff' ) as $at => $hex ) {
			$defs .= sprintf( "<radialGradient id='b%1\$d'><stop offset='0' stop-color='%2\$s' stop-opacity='.62'/><stop offset='.8' stop-color='%2\$s' stop-opacity='.5'/><stop offset='.93' stop-color='%2\$s' stop-opacity='.6'/><stop offset='1' stop-color='%2\$s' stop-opacity='0'/></radialGradient>", $at, $hex );
		}

		for ( $n = 0; $n < self::BOKEH_LIGHTS; $n++ ) {
			$x     = $random() * $tile;
			$y     = $random() * $tile;
			$grow  = $random();
			$pick  = $random();
			$r     = $size * ( 0.2 + $grow * 0.95 );
			$fill  = $pick < 0.42 ? 0 : 1;
			$fill  = $pick < 0.8 ? $fill : 2;
			$alpha = self::js_round( $light * ( 0.75 - $grow * 0.45 ), 1000 );

			foreach ( self::tile_offsets( array( array( $x - $r, $y - $r ), array( $x + $r, $y + $r ) ), $tile ) as $offset ) {
				$shapes .= sprintf( "<circle cx='%s' cy='%s' r='%s' fill='url(#b%d)' opacity='%s'/>", self::js_round( $x + $offset[0], 10 ), self::js_round( $y + $offset[1], 10 ), self::js_round( $r, 10 ), $fill, $alpha );
			}
		}

		$svg = sprintf( "<svg xmlns='http://www.w3.org/2000/svg' width='%1\$d' height='%1\$d' viewBox='0 0 %1\$d %1\$d'><defs>%2\$s</defs>%3\$s</svg>", $tile, $defs, $shapes );

		return 'url("data:image/svg+xml,' . str_replace( array( '<', '>', '#' ), array( '%3C', '%3E', '%23' ), $svg ) . '")';
	}

	/**
	 * Rounds like JavaScript's Math.round( value * scale ) / scale. PHP's round() nudges near-ties first, which
	 * would make the waves and bokeh differ from the preview's by a digit here and there.
	 *
	 * @param float $value The number.
	 * @param int   $scale 10 for one decimal, 1000 for three.
	 */
	private static function js_round( float $value, int $scale ): float {
		return floor( $value * $scale + 0.5 ) / $scale;
	}

	/**
	 * Whether an attachment is a video the pay page can loop: an MP4 or WebM from the Media Library.
	 *
	 * @param int $id The attachment ID.
	 */
	public static function is_video( int $id ): bool {
		return $id > 0 && in_array( (string) get_post_mime_type( $id ), self::VIDEO_TYPES, true );
	}

	/**
	 * A YouTube or Vimeo link as a muted, looping player with no controls, or '' when it's neither.
	 * buttons.js reads links the same way for the preview.
	 *
	 * @param string $url The link the merchant pasted.
	 */
	public static function embed_url( string $url ): string {
		if ( 1 === preg_match( '~^https?://(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $match[1] . '?autoplay=1&mute=1&loop=1&playlist=' . $match[1] . '&controls=0&playsinline=1&rel=0&disablekb=1&iv_load_policy=3&enablejsapi=1';
		}

		if ( 1 === preg_match( '~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?~', $url, $match ) ) {
			// Unlisted videos carry a hash, in the path or as ?h=.
			$hash = $match[2] ?? '';

			if ( '' === $hash && 1 === preg_match( '~[?&]h=([0-9a-f]+)~', $url, $query ) ) {
				$hash = $query[1];
			}

			return 'https://player.vimeo.com/video/' . $match[1] . '?background=1&autoplay=1&loop=1&muted=1&dnt=1' . ( '' !== $hash ? '&h=' . $hash : '' );
		}

		return '';
	}

	/**
	 * The look's background video: a file, or a YouTube or Vimeo player, and the part of it that loops (start and
	 * end in seconds, end 0 for the end of the video; both null for the whole video). Null when it has none.
	 *
	 * @param array<string, mixed> $look A look, sanitized or on its way.
	 * @phpstan-param Look $look
	 * @return array{kind: string, src: string, start: int|null, end: int|null}|null
	 */
	public static function video( array $look ): ?array {
		$part  = 'part' === $look['pay_video_loop'];
		$start = $part ? $look['pay_video_start'] : null;
		$end   = $part ? $look['pay_video_end'] : null;

		if ( 'link' === $look['pay_video_src'] ) {
			$embed = self::embed_url( $look['pay_video_url'] );

			return '' !== $embed ? array(
				'kind'  => 'embed',
				'src'   => null !== $start ? self::embed_from( $embed, $start ) : $embed,
				'start' => $start,
				'end'   => $end,
			) : null;
		}

		$url = $look['pay_video'] > 0 ? wp_get_attachment_url( $look['pay_video'] ) : false;

		return is_string( $url ) && '' !== $url ? array(
			'kind'  => 'file',
			'src'   => esc_url_raw( $url ),
			'start' => $start,
			'end'   => $end,
		) : null;
	}

	/**
	 * A player that starts at a moment, for video-loop.js to steer: YouTube's start, Vimeo's #t.
	 * buttons.js does the same for the preview.
	 *
	 * @param string $embed The player's address from embed_url().
	 * @param int    $start Where to start, in seconds.
	 */
	public static function embed_from( string $embed, int $start ): string {
		if ( false !== strpos( $embed, 'vimeo.com' ) ) {
			return $embed . '#t=' . $start . 's';
		}

		return $embed . '&start=' . $start;
	}

	/**
	 * A moment typed as 1:30, 1:02:03 or 90, in seconds. Anything else is 0.
	 *
	 * @param mixed $value The raw time.
	 */
	public static function seconds( $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^\s*(?:(\d+):)?(?:(\d+):)?(\d+)\s*$/', $value, $parts ) ) {
			return 0;
		}

		$numbers = array_filter( array( $parts[1], $parts[2], $parts[3] ), static fn( string $part ): bool => '' !== $part );
		$total   = 0;

		foreach ( $numbers as $number ) {
			$total = $total * 60 + (int) $number;
		}

		return min( $total, 86400 );
	}

	/**
	 * Seconds as the field shows them: 1:30, or 1:02:03 past the hour.
	 *
	 * @param int $seconds The time.
	 */
	public static function clock( int $seconds ): string {
		$hours = intdiv( $seconds, 3600 );
		$rest  = sprintf( '%02d', $seconds % 60 );

		return $hours > 0 ? sprintf( '%d:%02d:%s', $hours, intdiv( $seconds % 3600, 60 ), $rest ) : intdiv( $seconds, 60 ) . ':' . $rest;
	}

	/**
	 * The video a checkout or popup opened on the pay page's background plays, or null.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 * @return array{kind: string, src: string, start: int|null, end: int|null}|null
	 */
	public static function scene_video( array $look ): ?array {
		return $look['scene'] && 'video' === $look['pay_bg'] ? self::video( $look ) : null;
	}

	/**
	 * The background video layer. A lazy one waits for checkout.js to give it its source when the checkout
	 * opens, so a page with many buttons doesn't load a video for each. '' when the look has no video.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @param bool                 $lazy Whether to hold the source back until it's shown.
	 * @phpstan-param Look $look
	 */
	public static function video_markup( array $look, bool $lazy ): string {
		$video = 'video' === $look['pay_bg'] ? self::video( $look ) : null;

		if ( null === $video ) {
			return '';
		}

		$src = ( $lazy ? 'data-src' : 'src' ) . '="' . esc_url( $video['src'] ) . '"';

		// Looping part of it: video-loop.js keeps it between these.
		if ( null !== $video['start'] ) {
			$src .= sprintf( ' data-loop-start="%d" data-loop-end="%d"', $video['start'], (int) $video['end'] );
		}

		if ( 'embed' === $video['kind'] ) {
			$media = '<iframe class="iftp-btn-pagevideo__media iftp-btn-pagevideo__media--embed" ' . $src . ' title="" tabindex="-1" allow="autoplay; encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>';
		} else {
			$media = '<video class="iftp-btn-pagevideo__media" ' . $src . ' muted loop playsinline' . ( $lazy ? ' preload="none"' : ' autoplay preload="auto"' ) . '></video>';
		}

		// Waiting until video-loop.js sees a frame; checkout.css keeps the card back meanwhile.
		return '<div class="iftp-btn-pagevideo is-waiting" aria-hidden="true" data-iftp-video>' . $media . '</div>';
	}

	/**
	 * The classes that pick the pay page's background, gradient style, effect and blur.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function page_classes( array $look ): string {
		return sprintf( 'iftp-btn-pagebg--%1$s iftp-btn-pagegrad--%2$s iftp-btn-pagefx--%3$s iftp-btn-pageblur--%4$s', $look['pay_bg'], $look['pay_grad'], $look['pay_fx'], $look['pay_blur_type'] );
	}

	/**
	 * The classes that open a checkout or popup on the pay page's background, or '' when the look keeps the dimmed page.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function scene_classes( array $look ): string {
		return $look['scene'] ? 'iftp-btn-sheet--scene ' . self::page_classes( $look ) : '';
	}

	/**
	 * What checkout.js needs to draw a button's outcome popup in its own look; null when it uses the site's.
	 *
	 * @param Button|null $button The button.
	 * @return array{style: string, icon: string, font: string, scene: string, dark: bool, video: array{kind: string, src: string, start: int|null, end: int|null}|null}|null
	 */
	public static function popup_look( ?Button $button ): ?array {
		$style = self::inline_style( $button );

		if ( null === $button || '' === $style ) {
			return null;
		}

		return array(
			'style' => $style,
			'icon'  => $button->config['look']['popup_icon'],
			'font'  => $button->config['look']['font'],
			'scene' => self::scene_classes( $button->config['look'] ),
			'dark'  => '' !== self::dark_class( $button->config['look'] ),
			'video' => self::scene_video( $button->config['look'] ),
		);
	}

	/**
	 * The pay page's background colour: the one picked, or the checkout's background with a touch of its text.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function page_color( array $look ): string {
		if ( '' !== $look['pay_color'] ) {
			return $look['pay_color'];
		}

		$ink     = sscanf( $look['ink'], '#%02x%02x%02x' );
		$surface = sscanf( $look['surface'], '#%02x%02x%02x' );
		$mixed   = '#';

		foreach ( array( 0, 1, 2 ) as $at ) {
			$mixed .= sprintf( '%02x', (int) round( 0.06 * (int) $ink[ $at ] + 0.94 * (int) $surface[ $at ] ) );
		}

		return $mixed;
	}

	/**
	 * The pay page's background image as a CSS url(), or none.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	public static function page_image( array $look ): string {
		$url = $look['pay_image'] > 0 ? wp_get_attachment_image_url( $look['pay_image'], 'full' ) : false;

		// Quotes and backslashes can't come through esc_url_raw(), so the url("…") can't be broken out of.
		return is_string( $url ) && '' !== $url ? 'url("' . str_replace( array( '"', '\\' ), '', esc_url_raw( $url ) ) . '")' : 'none';
	}

	/**
	 * A colour at some opacity, as rgb().
	 *
	 * @param string $hex     A #rrggbb colour.
	 * @param int    $percent The opacity.
	 */
	private static function alpha( string $hex, int $percent ): string {
		$rgb = sscanf( $hex, '#%02x%02x%02x' );

		return sprintf( 'rgb(%d %d %d / %d%%)', (int) $rgb[0], (int) $rgb[1], (int) $rgb[2], $percent );
	}

	/**
	 * The merchant's CSS, made safe to print inside a <style>: no tags, so nothing can close it early.
	 *
	 * @param string $css The raw CSS.
	 */
	public static function clean_css( string $css ): string {
		$css = wp_strip_all_tags( $css );

		return trim( mb_substr( str_replace( array( '<', '\\3c', '\\3C' ), '', $css ), 0, 20000 ) );
	}
}
