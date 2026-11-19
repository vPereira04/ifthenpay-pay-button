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
 * @phpstan-type Look array{accent: string, accent_text: string, surface: string, ink: string, radius: int, field_radius: int, fields: string, frame: string, card_skin: string, card_skin_strength: int, font: string, width: string, backdrop: string, anim: string, paid: string, pending: string, failed: string, popup_icon: string, popup_align: string, steps: string, logos: bool, secure: bool, pay_bg: string, pay_color: string, pay_image: int, pay_dim: int, pay_logo: bool, pay_mark: string, pay_mark_id: int, pay_mark_place: string, pay_mark_size: int, pay_back: bool, pay_grad: string, pay_color2: string, pay_fx: string, pay_fx_size: int, pay_fx_strength: int, pay_fx_still: bool, pay_blur_type: string, pay_blur: int, pay_video_src: string, pay_video: int, pay_video_url: string, pay_video_blur: int, pay_video_loop: string, pay_video_start: int, pay_video_end: int, card_opacity: int, card_blur: int, dark: string, dark_surface: string, dark_ink: string, dark_accent: string, scene: bool, css: string}
 */
class CheckoutLook {

	public const FIELD_STYLES = array( 'outline', 'soft', 'underline', 'pixel', 'well', 'stamp', 'marker', 'doily' );
	public const FRAMES       = array( 'none', 'hairline', 'gilded', 'pixel', 'matte', 'glow', 'long', 'awning' );
	public const CARD_SKINS   = array( 'none', 'dots', 'notebook', 'graph', 'polka', 'corner', 'pizzeria' );
	public const MARK_SRCS    = array( 'site', 'image' );
	public const MARK_PLACES  = array( 'above', 'inside' );
	public const FONTS        = array( 'system', 'theme', 'inter', 'outfit', 'grotesk', 'playfair', 'mono', 'pixelify', 'silkscreen', 'vt323', 'press-start', 'fredoka', 'pacifico', 'chewy' );

	// The bundled fonts' stacks (assets/fonts, declared in checkout.css). 'system' and 'theme' aren't here.
	public const FONT_STACKS  = array(
		'inter'       => '"Iftp Inter", -apple-system, "Segoe UI", sans-serif',
		'outfit'      => '"Iftp Outfit", -apple-system, "Segoe UI", sans-serif',
		'grotesk'     => '"Iftp Space Grotesk", -apple-system, "Segoe UI", sans-serif',
		'playfair'    => '"Iftp Playfair Display", Georgia, "Times New Roman", serif',
		'mono'        => '"Iftp Space Mono", ui-monospace, Menlo, Consolas, monospace',
		'pixelify'    => '"Iftp Pixelify Sans", ui-monospace, Menlo, Consolas, monospace',
		'silkscreen'  => '"Iftp Silkscreen", ui-monospace, Menlo, Consolas, monospace',
		'vt323'       => '"Iftp VT323", ui-monospace, Menlo, Consolas, monospace',
		'press-start' => '"Iftp Press Start 2P", ui-monospace, Menlo, Consolas, monospace',
		'fredoka'     => '"Iftp Fredoka", "Segoe UI Rounded", -apple-system, "Segoe UI", sans-serif',
		'pacifico'    => '"Iftp Pacifico", "Brush Script MT", cursive',
		'chewy'       => '"Iftp Chewy", "Comic Sans MS", cursive',
	);
	public const WIDTHS       = array(
		'compact' => 400,
		'regular' => 440,
		'wide'    => 520,
	);
	public const BACKDROPS    = array( 'blur', 'dim', 'light' );
	public const POPUP_ICONS  = array( 'soft', 'solid' );
	public const POPUP_ALIGNS = array( 'center', 'left' );
	public const STEP_STYLES  = array( 'bars', 'labels', 'line', 'numbers', 'dots', 'ring', 'text', 'chevrons', 'stairs', 'pills', 'counter', 'gauge', 'pixel', 'donuts', 'pizza' );
	public const PAY_BGS      = array( 'glow', 'plain', 'gradient', 'image', 'video' );
	public const PAY_GRADS    = array( 'soft', 'aurora', 'diagonal' );
	public const PAY_FX       = array( 'none', 'crystals', 'waves', 'bokeh', 'grain', 'dots', 'grid', 'stripes', 'rings', 'liquid', 'mesh', 'tide', 'gilt', 'rays', 'spotlight', 'starlight', 'satin', 'confetti', 'hexagons', 'diamonds', 'scales', 'chevrons', 'bubbles', 'fireflies', 'pixels', 'sprinkles', 'pizza', 'gingham', 'pitch', 'court', 'track', 'code', 'diner', 'ecg', 'waveform', 'sky' );
	public const ANIMS        = array( 'grow', 'rise', 'slide', 'zoom', 'blur', 'drawer', 'voxel', 'waves', 'shutter', 'curtain', 'iris', 'ripple', 'pixelate', 'shatter', 'stripes', 'confetti', 'none' );
	public const PAY_BLURS    = array( 'none', 'soft', 'frosted', 'tilt', 'vignette' );
	public const VIDEO_SRCS   = array( 'file', 'link' );
	public const VIDEO_TYPES  = array( 'video/mp4', 'video/webm' );
	public const VIDEO_LOOPS  = array( 'all', 'part' );
	public const DARK_MODES   = array( 'off', 'auto' );

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
			'accent'             => '#00609c',
			'accent_text'        => '',
			'surface'            => '#fdfdfe',
			'ink'                => '#121a2c',
			'radius'             => 14,
			'field_radius'       => 6,
			'fields'             => 'outline',
			'frame'              => 'none',
			'card_skin'          => 'none',
			'card_skin_strength' => 50,
			'font'               => 'system',
			'width'              => 'regular',
			'backdrop'           => 'blur',
			'anim'               => 'grow',
			'paid'               => '#0b6b35',
			'pending'            => '#8a5300',
			'failed'             => '#a3201f',
			'popup_icon'         => 'soft',
			'popup_align'        => 'center',
			'steps'              => 'bars',
			'logos'              => true,
			'secure'             => true,
			'pay_bg'             => 'glow',
			'pay_color'          => '',
			'pay_image'          => 0,
			'pay_dim'            => 35,
			'pay_logo'           => false,
			'pay_mark'           => 'site',
			'pay_mark_id'        => 0,
			'pay_mark_place'     => 'above',
			'pay_mark_size'      => 160,
			'pay_back'           => true,
			'pay_grad'           => 'soft',
			'pay_color2'         => '',
			'pay_fx'             => 'none',
			'pay_fx_size'        => 140,
			'pay_fx_strength'    => 50,
			'pay_fx_still'       => true,
			'pay_blur_type'      => 'none',
			'pay_blur'           => 12,
			'pay_video_src'      => 'file',
			'pay_video'          => 0,
			'pay_video_url'      => '',
			'pay_video_blur'     => 0,
			'pay_video_loop'     => 'all',
			'pay_video_start'    => 0,
			'pay_video_end'      => 0,
			'card_opacity'       => 100,
			'card_blur'          => 16,
			'dark'               => 'off',
			'dark_surface'       => '#131a2a',
			'dark_ink'           => '#eef2f8',
			'dark_accent'        => '',
			'scene'              => true,
			'css'                => '',
		);
	}

	/**
	 * Ready-made looks for the Checkout tab. Each sets the colours, the shape and the pay page's background (the
	 * page colour, gradient, effect and how see-through the card is); the merchant's CSS, footer, popups and any
	 * image or video they picked stay. A blank page colour or second colour means "auto".
	 *
	 * @return array<string, array<string, string|int|bool>>
	 */
	public static function presets(): array {
		$base = array(
			'frame'           => 'none',
			'font'            => 'system',
			'pay_bg'          => 'glow',
			'pay_grad'        => 'soft',
			'pay_color'       => '',
			'pay_color2'      => '',
			'pay_fx'          => 'none',
			'pay_fx_size'     => 140,
			'pay_fx_strength' => 50,
			'pay_fx_still'    => true,
			'card_opacity'    => 100,
			'card_blur'       => 16,
		);

		$presets = array(
			'ifthenpay'  => array(
				'label'        => __( 'ifthenpay', 'ifthenpay-pay-button' ),
				'accent'       => '#00609c',
				'surface'      => '#fdfdfe',
				'ink'          => '#121a2c',
				'radius'       => 14,
				'field_radius' => 6,
				'fields'       => 'outline',
				'pay_color2'   => '#00a7e2',
			),
			'midnight'   => array(
				'label'           => __( 'Midnight', 'ifthenpay-pay-button' ),
				'accent'          => '#7cc4ff',
				'surface'         => '#141c2f',
				'ink'             => '#eef2f8',
				'radius'          => 22,
				'field_radius'    => 12,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#0a1020',
				'pay_color2'      => '#6d5dfc',
				'pay_fx'          => 'bokeh',
				'pay_fx_size'     => 120,
				'pay_fx_strength' => 40,
				'card_opacity'    => 84,
				'card_blur'       => 18,
			),
			'paper'      => array(
				'label'           => __( 'Paper', 'ifthenpay-pay-button' ),
				'accent'          => '#1f1f1f',
				'surface'         => '#fbfaf7',
				'ink'             => '#1f1f1f',
				'radius'          => 6,
				'field_radius'    => 0,
				'fields'          => 'underline',
				'pay_bg'          => 'plain',
				'pay_color'       => '#ece8de',
				'pay_fx'          => 'grain',
				'pay_fx_size'     => 160,
				'pay_fx_strength' => 45,
			),
			'meadow'     => array(
				'label'           => __( 'Meadow', 'ifthenpay-pay-button' ),
				'accent'          => '#0b6b35',
				'surface'         => '#fbfdfb',
				'ink'             => '#15241b',
				'radius'          => 28,
				'field_radius'    => 16,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_color'       => '#eef6ec',
				'pay_color2'      => '#9bcf5f',
				'pay_fx'          => 'waves',
				'pay_fx_size'     => 170,
				'pay_fx_strength' => 35,
			),
			'ocean'      => array(
				'label'           => __( 'Ocean', 'ifthenpay-pay-button' ),
				'accent'          => '#0077b6',
				'surface'         => '#f7fbfd',
				'ink'             => '#0b2233',
				'radius'          => 18,
				'field_radius'    => 10,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#e4f2f9',
				'pay_color2'      => '#00b4d8',
				'pay_fx'          => 'waves',
				'pay_fx_size'     => 190,
				'pay_fx_strength' => 45,
				'card_opacity'    => 88,
				'card_blur'       => 16,
			),
			'sunset'     => array(
				'label'        => __( 'Sunset', 'ifthenpay-pay-button' ),
				'accent'       => '#e4505b',
				'surface'      => '#fffaf6',
				'ink'          => '#2a1a1f',
				'radius'       => 24,
				'field_radius' => 12,
				'fields'       => 'soft',
				'pay_bg'       => 'gradient',
				'pay_grad'     => 'diagonal',
				'pay_color'    => '#fff0e6',
				'pay_color2'   => '#ffb347',
				'card_opacity' => 92,
				'card_blur'    => 14,
			),
			'prism'      => array(
				'label'           => __( 'Prism', 'ifthenpay-pay-button' ),
				'accent'          => '#5b5bd6',
				'surface'         => '#ffffff',
				'ink'             => '#1b1b3a',
				'radius'          => 24,
				'field_radius'    => 14,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#eef0ff',
				'pay_color2'      => '#c084fc',
				'pay_fx'          => 'crystals',
				'pay_fx_size'     => 140,
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 72,
				'card_blur'       => 20,
			),
			'blossom'    => array(
				'label'           => __( 'Blossom', 'ifthenpay-pay-button' ),
				'accent'          => '#c93f7d',
				'surface'         => '#fffafc',
				'ink'             => '#2d1622',
				'radius'          => 28,
				'field_radius'    => 16,
				'fields'          => 'soft',
				'pay_color'       => '#fcedf3',
				'pay_color2'      => '#f7a8c4',
				'pay_fx'          => 'bokeh',
				'pay_fx_size'     => 110,
				'pay_fx_strength' => 55,
				'card_opacity'    => 90,
				'card_blur'       => 14,
			),
			'neon'       => array(
				'label'           => __( 'Neon', 'ifthenpay-pay-button' ),
				'accent'          => '#22d3ee',
				'surface'         => '#0f1424',
				'ink'             => '#e6f6ff',
				'radius'          => 16,
				'field_radius'    => 10,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'diagonal',
				'pay_color'       => '#070b16',
				'pay_color2'      => '#a855f7',
				'pay_fx'          => 'crystals',
				'pay_fx_size'     => 120,
				'pay_fx_strength' => 35,
				'card_opacity'    => 80,
				'card_blur'       => 18,
			),
			'noir'       => array(
				'label'           => __( 'Noir', 'ifthenpay-pay-button' ),
				'accent'          => '#e8b931',
				'surface'         => '#131313',
				'ink'             => '#f4f1ea',
				'radius'          => 8,
				'field_radius'    => 4,
				'fields'          => 'underline',
				'pay_bg'          => 'plain',
				'pay_color'       => '#060606',
				'pay_fx'          => 'grain',
				'pay_fx_size'     => 140,
				'pay_fx_strength' => 30,
			),
			'latte'      => array(
				'label'           => __( 'Latte', 'ifthenpay-pay-button' ),
				'accent'          => '#8b5e34',
				'surface'         => '#fffdf9',
				'ink'             => '#2b2118',
				'radius'          => 14,
				'field_radius'    => 8,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_color'       => '#f3ebe0',
				'pay_color2'      => '#d9b38c',
				'pay_fx'          => 'grain',
				'pay_fx_size'     => 180,
				'pay_fx_strength' => 30,
			),
			'pixel'      => array(
				'label'           => __( 'Pixel', 'ifthenpay-pay-button' ),
				'accent'          => '#22c55e',
				'accent_text'     => '#07130b',
				'surface'         => '#1b1340',
				'ink'             => '#f5f1ff',
				'radius'          => 0,
				'field_radius'    => 0,
				'fields'          => 'pixel',
				'frame'           => 'pixel',
				'font'            => 'pixelify',
				'steps'           => 'pixel',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'diagonal',
				'pay_color'       => '#0f0a24',
				'pay_color2'      => '#ff3d81',
				'pay_fx'          => 'pixels',
				'pay_fx_size'     => 120,
				'pay_fx_strength' => 60,
				'pay_fx_still'    => false,
				'card_opacity'    => 100,
			),
			'terminal'   => array(
				'label'           => __( 'Terminal', 'ifthenpay-pay-button' ),
				'accent'          => '#4ade80',
				'accent_text'     => '#04130a',
				'surface'         => '#0a120d',
				'ink'             => '#c8f7d6',
				'radius'          => 2,
				'field_radius'    => 0,
				'fields'          => 'underline',
				'frame'           => 'hairline',
				'font'            => 'vt323',
				'steps'           => 'counter',
				'pay_bg'          => 'plain',
				'pay_color'       => '#040806',
				'pay_color2'      => '#4ade80',
				'pay_fx'          => 'grid',
				'pay_fx_size'     => 100,
				'pay_fx_strength' => 45,
				'pay_fx_still'    => false,
				'card_opacity'    => 100,
			),
			'brutal'     => array(
				'label'           => __( 'Brutal', 'ifthenpay-pay-button' ),
				'accent'          => '#ffd60a',
				'accent_text'     => '#111111',
				'surface'         => '#fffdf5',
				'ink'             => '#111111',
				'radius'          => 0,
				'field_radius'    => 0,
				'fields'          => 'pixel',
				'frame'           => 'pixel',
				'font'            => 'grotesk',
				'steps'           => 'labels',
				'pay_bg'          => 'plain',
				'pay_color'       => '#ff7ab8',
				'pay_color2'      => '#111111',
				'pay_fx'          => 'stripes',
				'pay_fx_size'     => 90,
				'pay_fx_strength' => 40,
				'pay_fx_still'    => true,
				'card_opacity'    => 100,
			),
			'editorial'  => array(
				'label'           => __( 'Editorial', 'ifthenpay-pay-button' ),
				'accent'          => '#7a1f2b',
				'surface'         => '#fbf7ef',
				'ink'             => '#231a14',
				'radius'          => 4,
				'field_radius'    => 0,
				'fields'          => 'underline',
				'frame'           => 'hairline',
				'font'            => 'playfair',
				'steps'           => 'labels',
				'pay_bg'          => 'plain',
				'pay_color'       => '#efe6d6',
				'pay_color2'      => '#b08d57',
				'pay_fx'          => 'grain',
				'pay_fx_size'     => 180,
				'pay_fx_strength' => 30,
				'card_opacity'    => 100,
			),
			'typewriter' => array(
				'label'           => __( 'Typewriter', 'ifthenpay-pay-button' ),
				'accent'          => '#1f2937',
				'surface'         => '#f6f0e1',
				'ink'             => '#1f2937',
				'radius'          => 3,
				'field_radius'    => 2,
				'fields'          => 'outline',
				'frame'           => 'hairline',
				'font'            => 'mono',
				'steps'           => 'line',
				'pay_bg'          => 'plain',
				'pay_color'       => '#e4dac2',
				'pay_color2'      => '#8a6d3b',
				'pay_fx'          => 'dots',
				'pay_fx_size'     => 110,
				'pay_fx_strength' => 35,
				'card_opacity'    => 100,
			),
			'premium'    => array(
				'label'           => __( 'Premium', 'ifthenpay-pay-button' ),
				'accent'          => '#c9a24a',
				'accent_text'     => '#17130a',
				'surface'         => '#16140f',
				'ink'             => '#f4eddc',
				'radius'          => 6,
				'field_radius'    => 0,
				'fields'          => 'underline',
				'frame'           => 'gilded',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'diagonal',
				'pay_color'       => '#0b0a08',
				'pay_color2'      => '#b8923a',
				'pay_fx'          => 'rays',
				'pay_fx_size'     => 120,
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 92,
				'card_blur'       => 18,
			),
			'emerald'    => array(
				'label'           => __( 'Emerald', 'ifthenpay-pay-button' ),
				'accent'          => '#3ddc97',
				'surface'         => '#0f1f1a',
				'ink'             => '#e7f6ee',
				'radius'          => 18,
				'field_radius'    => 10,
				'fields'          => 'soft',
				'frame'           => 'hairline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#06130f',
				'pay_color2'      => '#1fa37a',
				'pay_fx'          => 'starlight',
				'pay_fx_size'     => 130,
				'pay_fx_strength' => 60,
				'pay_fx_still'    => false,
				'card_opacity'    => 86,
				'card_blur'       => 18,
			),
			'rose'       => array(
				'label'           => __( 'Rosé', 'ifthenpay-pay-button' ),
				'accent'          => '#b5546b',
				'surface'         => '#fffbfa',
				'ink'             => '#3a1f27',
				'radius'          => 26,
				'field_radius'    => 14,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'soft',
				'pay_color'       => '#f7e6e4',
				'pay_color2'      => '#e8b4a0',
				'pay_fx'          => 'satin',
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 90,
				'card_blur'       => 16,
			),
			'slate'      => array(
				'label'           => __( 'Slate', 'ifthenpay-pay-button' ),
				'accent'          => '#9ab4ff',
				'surface'         => '#1b1f2a',
				'ink'             => '#edf0f7',
				'radius'          => 12,
				'field_radius'    => 8,
				'fields'          => 'outline',
				'pay_bg'          => 'plain',
				'pay_color'       => '#0d1017',
				'pay_color2'      => '#6f8cff',
				'pay_fx'          => 'spotlight',
				'pay_fx_strength' => 45,
				'pay_fx_still'    => false,
				'card_opacity'    => 94,
				'card_blur'       => 16,
			),
			'sunrise'    => array(
				'label'           => __( 'Sunrise', 'ifthenpay-pay-button' ),
				'accent'          => '#d9480f',
				'surface'         => '#fffcf7',
				'ink'             => '#2b1a10',
				'radius'          => 22,
				'field_radius'    => 12,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'diagonal',
				'pay_color'       => '#fff1dc',
				'pay_color2'      => '#ffb84d',
				'pay_fx'          => 'rays',
				'pay_fx_strength' => 50,
				'pay_fx_still'    => false,
				'card_opacity'    => 92,
				'card_blur'       => 14,
			),
			'lavender'   => array(
				'label'           => __( 'Lavender', 'ifthenpay-pay-button' ),
				'accent'          => '#7c5cc4',
				'surface'         => '#fdfcff',
				'ink'             => '#25193d',
				'radius'          => 28,
				'field_radius'    => 16,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#efe9fb',
				'pay_color2'      => '#f0a6ca',
				'pay_fx'          => 'mesh',
				'pay_fx_size'     => 160,
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 80,
				'card_blur'       => 20,
			),
			'honeycomb'  => array(
				'label'           => __( 'Honeycomb', 'ifthenpay-pay-button' ),
				'accent'          => '#b86e00',
				'surface'         => '#fffdf7',
				'ink'             => '#2a1d05',
				'radius'          => 16,
				'field_radius'    => 8,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'soft',
				'pay_color'       => '#fdf3d8',
				'pay_color2'      => '#f5b942',
				'pay_fx'          => 'hexagons',
				'pay_fx_size'     => 150,
				'pay_fx_strength' => 60,
				'pay_fx_still'    => false,
				'card_opacity'    => 94,
				'card_blur'       => 12,
			),
			'harlequin'  => array(
				'label'           => __( 'Harlequin', 'ifthenpay-pay-button' ),
				'accent'          => '#a3123f',
				'surface'         => '#fffafb',
				'ink'             => '#2b0d18',
				'radius'          => 10,
				'field_radius'    => 6,
				'fields'          => 'underline',
				'frame'           => 'hairline',
				'pay_bg'          => 'plain',
				'pay_color'       => '#f6e4e8',
				'pay_fx'          => 'diamonds',
				'pay_fx_size'     => 130,
				'pay_fx_strength' => 55,
				'card_opacity'    => 96,
				'card_blur'       => 10,
			),
			'koi'        => array(
				'label'           => __( 'Koi', 'ifthenpay-pay-button' ),
				'accent'          => '#0f766e',
				'surface'         => '#0c1d24',
				'ink'             => '#e6f4f2',
				'radius'          => 20,
				'field_radius'    => 12,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#05141a',
				'pay_color2'      => '#f0803c',
				'pay_fx'          => 'scales',
				'pay_fx_size'     => 150,
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 86,
				'card_blur'       => 18,
			),
			'arrow'      => array(
				'label'           => __( 'Arrow', 'ifthenpay-pay-button' ),
				'accent'          => '#2563eb',
				'surface'         => '#ffffff',
				'ink'             => '#0f1a33',
				'radius'          => 12,
				'field_radius'    => 8,
				'fields'          => 'outline',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'diagonal',
				'pay_color'       => '#e8eefc',
				'pay_color2'      => '#7aa2ff',
				'pay_fx'          => 'chevrons',
				'pay_fx_size'     => 160,
				'pay_fx_strength' => 55,
				'pay_fx_still'    => false,
				'card_opacity'    => 92,
				'card_blur'       => 14,
			),
			'soda'       => array(
				'label'           => __( 'Soda', 'ifthenpay-pay-button' ),
				'accent'          => '#0891b2',
				'surface'         => '#fbfeff',
				'ink'             => '#0a2630',
				'radius'          => 30,
				'field_radius'    => 18,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#e2f6fb',
				'pay_color2'      => '#5eead4',
				'pay_fx'          => 'bubbles',
				'pay_fx_size'     => 140,
				'pay_fx_strength' => 65,
				'pay_fx_still'    => false,
				'card_opacity'    => 82,
				'card_blur'       => 18,
			),
			'twilight'   => array(
				'label'           => __( 'Twilight', 'ifthenpay-pay-button' ),
				'accent'          => '#f2c14e',
				'accent_text'     => '#1c1405',
				'surface'         => '#141a2e',
				'ink'             => '#f1efe6',
				'radius'          => 22,
				'field_radius'    => 12,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#070b1a',
				'pay_color2'      => '#f2c14e',
				'pay_fx'          => 'fireflies',
				'pay_fx_size'     => 130,
				'pay_fx_strength' => 70,
				'pay_fx_still'    => false,
				'card_opacity'    => 84,
				'card_blur'       => 18,
			),
			'lava'       => array(
				'label'           => __( 'Lava', 'ifthenpay-pay-button' ),
				'accent'          => '#ea580c',
				'surface'         => '#fffaf5',
				'ink'             => '#2a1208',
				'radius'          => 26,
				'field_radius'    => 14,
				'fields'          => 'soft',
				'pay_bg'          => 'gradient',
				'pay_grad'        => 'aurora',
				'pay_color'       => '#fff1e2',
				'pay_color2'      => '#f43f5e',
				'pay_fx'          => 'liquid',
				'pay_fx_size'     => 160,
				'pay_fx_strength' => 60,
				'pay_fx_still'    => false,
				'card_opacity'    => 88,
				'card_blur'       => 16,
			),
		);

		return ButtonDesign::by_colour( array_map( static fn( array $preset ): array => $preset + $base, $presets ), 'accent' );
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
	 * The class that tells the checkout popup how to open and close.
	 *
	 * @param Look $look The look.
	 */
	public static function anim_class( array $look ): string {
		return 'iftp-btn-anim--' . $look['anim'];
	}

	/**
	 * What each opening is called, in the order they're offered.
	 *
	 * @return array<string, string>
	 */
	public static function anim_options(): array {
		return array(
			'grow'     => __( 'Grow from the button', 'ifthenpay-pay-button' ),
			'rise'     => __( 'Fade and rise', 'ifthenpay-pay-button' ),
			'slide'    => __( 'Slide up', 'ifthenpay-pay-button' ),
			'zoom'     => __( 'Zoom', 'ifthenpay-pay-button' ),
			'blur'     => __( 'Blur focus', 'ifthenpay-pay-button' ),
			'drawer'   => __( 'Side drawer', 'ifthenpay-pay-button' ),
			'voxel'    => __( 'Voxel (breaking blocks)', 'ifthenpay-pay-button' ),
			'waves'    => __( 'Waves from the sides', 'ifthenpay-pay-button' ),
			'shutter'  => __( 'Shutter (flipping blinds)', 'ifthenpay-pay-button' ),
			'curtain'  => __( 'Curtain (parts from the middle)', 'ifthenpay-pay-button' ),
			'iris'     => __( 'Iris (a circle opens)', 'ifthenpay-pay-button' ),
			'ripple'   => __( 'Ripple (rings spread out)', 'ifthenpay-pay-button' ),
			'pixelate' => __( 'Pixel dissolve', 'ifthenpay-pay-button' ),
			'shatter'  => __( 'Shatter (glass shards fall)', 'ifthenpay-pay-button' ),
			'stripes'  => __( 'Stripes (bars sweep across)', 'ifthenpay-pay-button' ),
			'confetti' => __( 'Confetti burst', 'ifthenpay-pay-button' ),
			'none'     => __( 'None', 'ifthenpay-pay-button' ),
		);
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

		$look['fields']             = $choice( 'fields', self::FIELD_STYLES, $look['fields'] );
		$look['frame']              = $choice( 'frame', self::FRAMES, $look['frame'] );
		$look['card_skin']          = $choice( 'card_skin', self::CARD_SKINS, $look['card_skin'] );
		$look['card_skin_strength'] = is_numeric( $input['card_skin_strength'] ?? null ) ? max( 0, min( 100, (int) $input['card_skin_strength'] ) ) : $look['card_skin_strength'];
		$look['font']               = $choice( 'font', self::FONTS, $look['font'] );
		$look['width']              = $choice( 'width', array_keys( self::WIDTHS ), $look['width'] );
		$look['backdrop']           = $choice( 'backdrop', self::BACKDROPS, $look['backdrop'] );
		$look['anim']               = $choice( 'anim', self::ANIMS, $look['anim'] );
		$look['popup_icon']         = $choice( 'popup_icon', self::POPUP_ICONS, $look['popup_icon'] );
		$look['popup_align']        = $choice( 'popup_align', self::POPUP_ALIGNS, $look['popup_align'] );
		$look['steps']              = $choice( 'steps', self::STEP_STYLES, $look['steps'] );

		$look['radius']       = is_numeric( $input['radius'] ?? null ) ? max( 0, min( self::MAX_RADIUS, (int) $input['radius'] ) ) : $look['radius'];
		$look['field_radius'] = is_numeric( $input['field_radius'] ?? null ) ? max( 0, min( self::MAX_FIELD_RADIUS, (int) $input['field_radius'] ) ) : $look['field_radius'];

		$look['pay_bg']    = $choice( 'pay_bg', self::PAY_BGS, $look['pay_bg'] );
		$look['pay_color'] = empty( $input['pay_color_auto'] ) && is_string( $input['pay_color'] ?? null ) ? ButtonDesign::hex( $input['pay_color'] ) : '';
		$look['pay_image'] = is_numeric( $input['pay_image'] ?? null ) && wp_attachment_is_image( (int) $input['pay_image'] ) ? (int) $input['pay_image'] : 0;
		$look['pay_dim']   = is_numeric( $input['pay_dim'] ?? null ) ? max( 0, min( 80, (int) $input['pay_dim'] ) ) : $look['pay_dim'];

		// The picture above or in the card: the site's logo, or an image from the media library (no image means the logo).
		$look['pay_mark']       = $choice( 'pay_mark', self::MARK_SRCS, $look['pay_mark'] );
		$look['pay_mark_id']    = is_numeric( $input['pay_mark_id'] ?? null ) && wp_attachment_is_image( (int) $input['pay_mark_id'] ) ? (int) $input['pay_mark_id'] : 0;
		$look['pay_mark_place'] = $choice( 'pay_mark_place', self::MARK_PLACES, $look['pay_mark_place'] );
		$look['pay_mark_size']  = is_numeric( $input['pay_mark_size'] ?? null ) ? max( 40, min( 480, (int) $input['pay_mark_size'] ) ) : $look['pay_mark_size'];

		if ( 'image' === $look['pay_mark'] && 0 === $look['pay_mark_id'] ) {
			$look['pay_mark'] = 'site';
		}

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

		foreach ( array( 'logos', 'secure', 'pay_logo', 'pay_back', 'pay_fx_still', 'scene' ) as $flag ) {
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
			'pixel'     => array( 'var(--iftp-chip-bg)', 'var(--iftp-ink)', '3px', '0', '12px' ),
			'well'      => array( 'var(--iftp-inset)', 'transparent', '1px', $look['field_radius'] . 'px', '14px', 'solid' ),
			'stamp'     => array( 'transparent', 'var(--iftp-line-strong)', '2px', $look['field_radius'] . 'px', '12px', 'dashed' ),
			'marker'    => array( 'var(--iftp-inset)', 'var(--iftp-brand)', '0 0 0 4px', '0 8px 8px 0', '14px' ),
			'doily'     => array( 'radial-gradient(ellipse at 50% 50%, color-mix(in srgb, var(--iftp-brand) 9%, var(--iftp-chip-bg)), var(--iftp-chip-bg) 78%)', 'color-mix(in srgb, var(--iftp-brand) 20%, var(--iftp-line-strong))', '1px', max( 10, $look['field_radius'] ) . 'px', '18px' ),
		);
		$field     = $fields[ $look['fields'] ];
		$backdrops = array(
			'blur'  => array( 'rgb(12 22 40 / 48%)', '3px' ),
			'dim'   => array( 'rgb(12 22 40 / 62%)', '0px' ),
			'light' => array( 'rgb(250 251 253 / 70%)', '8px' ),
		);
		$glass     = $look['card_opacity'] < 100;
		// The card's edge: a fine line in the second colour, and for gilded a glint along the top and a glow round it.
		$edges = array(
			'none'     => array(),
			'hairline' => array( 'inset 0 0 0 1px color-mix(in srgb, var(--iftp-second) 65%, transparent)' ),
			'gilded'   => array(
				'inset 0 0 0 1px color-mix(in srgb, var(--iftp-second) 70%, transparent)',
				'inset 0 1px 0 0 rgb(255 255 255 / 28%)',
				'0 0 0 1px color-mix(in srgb, var(--iftp-second) 28%, transparent)',
				'0 22px 60px -22px color-mix(in srgb, var(--iftp-second) 55%, transparent)',
			),
			// A hard ink edge and a stepped drop shadow: no blur anywhere.
			'pixel'    => array( 'inset 0 0 0 4px var(--iftp-ink)', '8px 8px 0 0 color-mix(in srgb, var(--iftp-ink) 35%, transparent)' ),
			// A picture-frame mat: a tinted band just inside the edge, closed by a fine line.
			'matte'    => array(
				'inset 0 0 0 8px color-mix(in srgb, var(--iftp-brand) 9%, transparent)',
				'inset 0 0 0 9px color-mix(in srgb, var(--iftp-brand) 35%, transparent)',
			),
			// A lit edge in the brand colour with a soft halo.
			'glow'     => array(
				'inset 0 0 0 1px color-mix(in srgb, var(--iftp-brand) 80%, transparent)',
				'0 0 0 1px color-mix(in srgb, var(--iftp-brand) 40%, transparent)',
				'0 0 26px 2px color-mix(in srgb, var(--iftp-brand) 45%, transparent)',
			),
			// The awning is drawn by a layer over the card's top (card_awning()); here only a fine line.
			'awning'   => array( 'inset 0 0 0 1px color-mix(in srgb, var(--iftp-brand) 28%, transparent)' ),
			// A flat long shadow running off the lower right, in four solid steps.
			'long'     => array(
				'3px 3px 0 0 %1$s',
				'6px 6px 0 0 %1$s',
				'9px 9px 0 0 %1$s',
				'12px 12px 0 0 %1$s',
			),
		);
		$edges['long'] = array_map( static fn( string $shadow ): string => sprintf( $shadow, 'color-mix(in srgb, var(--iftp-brand) 35%, var(--iftp-surface))' ), $edges['long'] );
		// The button wears the same choices: the sharp pixel bevel, the fields' own touch, then the card edge's.
		$sharp         = 'pixel' === $look['frame'] || 'pixel' === $look['fields'];
		$field_bevels  = array(
			'well'   => 'inset 0 -4px 0 0 rgb(0 0 0 / 24%), inset 0 2px 0 0 rgb(255 255 255 / 22%)',
			'marker' => 'inset 6px 0 0 0 rgb(0 0 0 / 28%)',
			'stamp'  => 'inset 0 0 0 3px var(--iftp-surface)',
			'doily'  => 'inset 0 0 0 3px var(--iftp-brand), inset 0 0 0 4px color-mix(in srgb, var(--iftp-on-brand) 45%, transparent)',
		);
		$edge_bevels   = array(
			'matte' => 'inset 0 0 0 2px color-mix(in srgb, var(--iftp-on-brand) 35%, transparent)',
			'glow'  => '0 0 18px -2px color-mix(in srgb, var(--iftp-brand) 60%, transparent)',
			'long'  => '3px 3px 0 0 color-mix(in srgb, var(--iftp-brand) 35%, var(--iftp-surface))',
		);
		$submit_bevel  = array_filter(
			array(
				$sharp ? 'inset -4px -4px 0 rgb(0 0 0 / 26%), inset 4px 4px 0 rgb(255 255 255 / 28%)' : ( $field_bevels[ $look['fields'] ] ?? '' ),
				$edge_bevels[ $look['frame'] ] ?? '',
			)
		);
		$submit_radius = 'marker' === $look['fields'] ? '0 8px 8px 0' : 'calc(var(--iftp-field-r) + 2px)';
		$ring          = array_merge( $edges[ $look['frame'] ], $glass && 'none' === $look['frame'] ? array( 'inset 0 0 0 1px rgb(255 255 255 / 22%)' ) : array() );

		$font = isset( self::FONT_STACKS[ $look['font'] ] ) ? array( '--iftp-font' => self::FONT_STACKS[ $look['font'] ] ) : array();

		return $font + self::palette( $look ) + array(
			'--iftp-paid'            => $look['paid'],
			'--iftp-pending'         => $look['pending'],
			'--iftp-failed'          => $look['failed'],
			'--iftp-radius'          => $look['radius'] . 'px',
			'--iftp-field-r'         => $field[3],
			'--iftp-field-bg'        => $field[0],
			'--iftp-field-bd'        => $field[1],
			'--iftp-field-bw'        => $field[2],
			'--iftp-field-bs'        => $field[5] ?? 'solid',
			'--iftp-field-shadow'    => 'well' === $look['fields'] ? 'inset 0 2px 5px color-mix(in srgb, var(--iftp-ink) 22%, transparent)' : '0 0 0 0 transparent',
			'--iftp-field-outline'   => 'doily' === $look['fields'] ? '1px dotted color-mix(in srgb, var(--iftp-brand) 75%, transparent)' : 'none',
			'--iftp-field-outline-o' => 'doily' === $look['fields'] ? '-5px' : '0px',
			'--iftp-field-px'        => $field[4],
			'--iftp-field-focus'     => self::field_focus( $look['fields'] ),
			'--iftp-submit-r'        => $sharp ? '0' : $submit_radius,
			'--iftp-submit-bevel'    => array() !== $submit_bevel ? implode( ', ', $submit_bevel ) : 'none',
			'--iftp-submit-bd'       => 'stamp' === $look['fields'] ? '2px dashed var(--iftp-brand)' : '0',
			'--iftp-submit-clip'     => 'stamp' === $look['fields'] ? 'padding-box' : 'border-box',
			'--iftp-sheet-w'         => self::WIDTHS[ $look['width'] ] . 'px',
			'--iftp-backdrop'        => $backdrops[ $look['backdrop'] ][0],
			'--iftp-backdrop-blur'   => $backdrops[ $look['backdrop'] ][1],
			'--iftp-result-align'    => 'left' === $look['popup_align'] ? 'start' : 'center',
			// A see-through card: frosted behind, a fine light edge, and no opaque scroll shading inside.
			'--iftp-card-filter'     => $glass && $look['card_blur'] > 0 ? 'blur(' . $look['card_blur'] . 'px) saturate(1.6)' : 'none',
			'--iftp-card-ring'       => array() !== $ring ? implode( ', ', $ring ) : '0 0 #0000',
			'--iftp-body-bg'         => $glass ? 'transparent' : 'initial',
			'--iftp-mark-size'       => $look['pay_mark_size'] . 'px',
			'--iftp-skin-a'          => (string) ( $look['card_skin_strength'] / 100 ),
			'--iftp-page-image'      => self::page_image( $look ),
			'--iftp-page-dim'        => 'rgb(0 0 0 / ' . $look['pay_dim'] . '%)',
			'--iftp-page-video-blur' => $look['pay_video_blur'] . 'px',
		) + self::card_awning( $look['frame'] ) + self::dark_palette( $look ) + self::page_effects( $look );
	}

	/**
	 * What a focused field wears: underline a line under it, pixel a hard offset block, the rest the soft ring.
	 *
	 * @param string $fields One of FIELD_STYLES.
	 */
	private static function field_focus( string $fields ): string {
		$focus = array(
			'underline' => '0 1px 0 0 var(--iftp-brand)',
			'pixel'     => '4px 4px 0 0 var(--iftp-brand)',
			'well'      => 'inset 0 2px 5px color-mix(in srgb, var(--iftp-ink) 22%, transparent), var(--iftp-ring)',
		);

		return $focus[ $fields ] ?? 'var(--iftp-ring)';
	}

	/**
	 * The card designs: flat, still backgrounds for the card itself, drawn in CSS from the accent and the text colour so
	 * they follow the look (and its dark colours). 'bg' is a list of background layers. look.js reads this same
	 * table for the live preview.
	 *
	 * @return array<string, array{bg: string}>
	 */
	public static function card_skins(): array {
		$ink   = 'var(--iftp-ink)';
		$brand = 'var(--iftp-brand)';
		// Every colour is scaled by --iftp-skin-a (the strength slider, 0 to 1), so the merchant sets how loud a design is.
		$tint = static fn( string $color, int $full ): string => sprintf( 'color-mix(in srgb, %1$s calc(var(--iftp-skin-a, 0.5) * %2$d%%), transparent)', $color, $full );

		return array(
			'none'     => array(
				'bg' => '',
			),
			// Fine dots, like a dotted notebook page.
			'dots'     => array(
				'bg' => 'radial-gradient(' . $tint( $ink, 40 ) . ' 0.9px, transparent 1.3px) 0 0 / 12px 12px',
			),
			// Ruled lines and a rose margin.
			'notebook' => array(
				'bg' => 'linear-gradient(90deg, transparent 38px, ' . $tint( '#e25656', 140 ) . ' 38px 39px, transparent 39px), repeating-linear-gradient(transparent 0 29px, ' . $tint( $brand, 36 ) . ' 29px 30px)',
			),
			// Graph paper: a fine grid with a stronger line every fifth square.
			'graph'    => array(
				'bg' => 'linear-gradient(' . $tint( $brand, 30 ) . ' 1px, transparent 1px) 0 0 / 100px 100px, linear-gradient(90deg, ' . $tint( $brand, 30 ) . ' 1px, transparent 1px) 0 0 / 100px 100px, linear-gradient(' . $tint( $brand, 14 ) . ' 1px, transparent 1px) 0 0 / 20px 20px, linear-gradient(90deg, ' . $tint( $brand, 14 ) . ' 1px, transparent 1px) 0 0 / 20px 20px',
			),
			// Small soft dots in two offset rows.
			'polka'    => array(
				'bg' => 'radial-gradient(' . $tint( $brand, 22 ) . ' 4px, transparent 4.5px) 0 0 / 28px 28px, radial-gradient(' . $tint( $brand, 22 ) . ' 4px, transparent 4.5px) 14px 14px / 28px 28px',
			),
			// A soft glow from the top corner and a fainter one from the bottom.
			'corner'   => array(
				'bg' => 'radial-gradient(circle at 100% 0, ' . $tint( $brand, 48 ) . ', transparent 55%), radial-gradient(circle at 0 100%, ' . $tint( $brand, 24 ) . ', transparent 45%)',
			),
			// A tomato-red edge along the top over a faint cardboard weave, like a pizza box.
			'pizzeria' => array(
				'bg' => 'linear-gradient(' . $tint( '#c8321f', 190 ) . ', ' . $tint( '#c8321f', 190 ) . ') 0 0 / 100% 4px no-repeat, repeating-linear-gradient(135deg, transparent 0 11px, ' . $tint( '#b07c3e', 30 ) . ' 11px 12px)',
			),
		);
	}

	/**
	 * A card design's background layers as CSS, each followed by a comma, or '' for the plain card.
	 *
	 * @param string $skin One of CARD_SKINS.
	 */
	public static function skin_layers( string $skin ): string {
		$bg = self::card_skins()[ $skin ]['bg'] ?? '';

		return '' === $bg ? '' : $bg . ', ';
	}

	/**
	 * The awning edge: eight bold stripes across the top with a scalloped valance hanging under them, drawn as a layer
	 * over the card (outside the content, which drops clear of it). The admin preview builds the same in look.js.
	 *
	 * @param string $frame One of FRAMES.
	 * @return array<string, string>
	 */
	private static function card_awning( string $frame ): array {
		if ( 'awning' !== $frame ) {
			return array(
				'--iftp-edge-on'  => 'none',
				'--iftp-edge-pos' => 'static',
				'--iftp-edge-h'   => '0px',
				'--iftp-edge-bg'  => 'none',
				'--iftp-edge-fx'  => 'none',
				'--iftp-card-pad' => '0px',
			);
		}

		$white = 'color-mix(in srgb, var(--iftp-brand) 6%, #fff)';

		return array(
			'--iftp-edge-on'  => '""',
			'--iftp-edge-pos' => 'relative',
			'--iftp-edge-h'   => '36px',
			'--iftp-edge-bg'  => implode(
				', ',
				array(
					'radial-gradient(ellipse 25% 100% at 25% 0, var(--iftp-brand) 97%, transparent) 0 23px / 25% 13px repeat-x',
					"radial-gradient(ellipse 25% 100% at 75% 0, $white 97%, transparent) 0 23px / 25% 13px repeat-x",
					'linear-gradient(rgb(255 255 255 / 32%), rgb(255 255 255 / 0%) 55%) 0 0 / 100% 24px no-repeat',
					"repeating-linear-gradient(90deg, var(--iftp-brand) 0 12.5%, $white 12.5% 25%) 0 0 / 100% 24px no-repeat",
				)
			),
			'--iftp-edge-fx'  => 'drop-shadow(0 3px 3px rgb(0 0 0 / 22%))',
			'--iftp-card-pad' => '32px',
		);
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
			'--iftp-card-bg'     => self::skin_layers( $look['card_skin'] ) . ( $card < 100 ? self::alpha( $look['surface'], $card ) : $look['surface'] ),
			// Fields and choices stay a little more solid than the card, so they still read as boxes.
			'--iftp-chip-bg'     => $card < 100 ? self::alpha( $look['surface'], min( 100, $card + 30 ) ) : $look['surface'],
			// The colour logo disappears on a dark background, so it turns white there.
			'--iftp-logo-filter' => '#ffffff' === ButtonDesign::on_color( $look['surface'] ) ? 'brightness(0) invert(1)' : 'none',
			'--iftp-page-bg'     => self::page_color( $look ),
			'--iftp-page-glow'   => self::alpha( $look['accent'], 16 ),
			'--iftp-page-glow2'  => self::alpha( $second, 16 ),
			'--iftp-page-orb'    => self::alpha( $look['accent'], 45 ),
			'--iftp-page-orb2'   => self::alpha( $second, 45 ),
			'--iftp-second'      => $second,
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
			'--iftp-page-crystal'       => $look['pay_fx_size'] . 'px',
			'--iftp-page-pattern'       => self::pattern( $look ),
			'--iftp-page-pattern-still' => self::moving( $look, true ),
			'--iftp-page-pattern-tile'  => ( Backgrounds::CRYSTAL_CELLS * $look['pay_fx_size'] ) . 'px',
			'--iftp-page-fx-light'      => 'rgb(255 255 255 / ' . round( $strength * 0.5 ) . '%)',
			'--iftp-page-fx-dark'       => 'rgb(0 0 0 / ' . round( $strength * 0.28 ) . '%)',
			'--iftp-page-fx-opacity'    => (string) round( $strength / 100, 2 ),
			'--iftp-page-blur'          => $blur,
			'--iftp-page-blur-filter'   => 'frosted' === $look['pay_blur_type'] ? 'blur(' . $blur . ') saturate(1.6) brightness(1.06)' : 'blur(' . $blur . ')',
			'--iftp-page-blur-mask'     => $masks[ $look['pay_blur_type'] ] ?? 'none',
			'--iftp-page-frost'         => 'frosted' === $look['pay_blur_type'] ? 'rgb(255 255 255 / 16%)' : 'transparent',
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
				return Backgrounds::crystals( $look['pay_fx_size'], $look['pay_fx_strength'] );
			case 'waves':
				return Backgrounds::waves( $look['pay_fx_size'], $look['pay_fx_strength'] );
			case 'bokeh':
				return Backgrounds::bokeh( $look['pay_fx_size'], $look['pay_fx_strength'], $look['accent'], self::second_color( $look ) );
		}

		return self::moving( $look, false );
	}

	/**
	 * The moving effect the look draws, as a CSS url(), or none when its effect doesn't move.
	 *
	 * @param array<string, mixed> $look  A sanitized look.
	 * @param bool                 $still Its still version.
	 * @phpstan-param Look $look
	 */
	private static function moving( array $look, bool $still ): string {
		switch ( $look['pay_fx'] ) {
			case 'liquid':
				return Backgrounds::liquid( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
			case 'mesh':
				return Backgrounds::mesh( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
			case 'tide':
				return Backgrounds::tide( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
			case 'confetti':
				return Backgrounds::confetti( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
			case 'bubbles':
				return Backgrounds::bubbles( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
			case 'fireflies':
				return Backgrounds::fireflies( $look['pay_fx_size'], $look['accent'], self::second_color( $look ), $still );
		}

		return 'none';
	}

	/**
	 * The gradient's second colour, or the accent when it follows it.
	 *
	 * @param array<string, mixed> $look A sanitized look.
	 * @phpstan-param Look $look
	 */
	private static function second_color( array $look ): string {
		return '' !== $look['pay_color2'] ? $look['pay_color2'] : $look['accent'];
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
		return sprintf( 'iftp-btn-pagebg--%1$s iftp-btn-pagegrad--%2$s iftp-btn-pagefx--%3$s iftp-btn-pageblur--%4$s', $look['pay_bg'], $look['pay_grad'], $look['pay_fx'], $look['pay_blur_type'] ) . ( $look['pay_fx_still'] ? ' iftp-btn-pagestill' : '' );
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
