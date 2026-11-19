<?php
/**
 * The small icons a form field can show beside its title.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field icons: the button icons plus a few made for form fields, all drawn in currentColor.
 */
class FieldIcons {

	/**
	 * The icons a field can wear, by name: the ones worth a question first, then the rest.
	 *
	 * @return array<string, string> Name => inner SVG paths.
	 */
	public static function paths(): array {
		$own    = array(
			'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
			'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6"/>',
			'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'home'     => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10M10 20v-6h4v6"/>',
			'building' => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
			'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
			'hash'     => '<path d="M5 9h15M4 15h15M10 3 8 21M16 3l-2 18"/>',
			'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'chat'     => '<path d="M4 5h16v11H9l-5 4z"/>',
			'note'     => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h7M9 17h5"/>',
		);
		$shared = array_intersect_key( ButtonDesign::icon_paths(), array_flip( array( 'calendar', 'card', 'cart', 'gift', 'heart', 'ticket', 'star', 'lock', 'paw', 'leaf', 'bolt', 'sparkles' ) ) );

		return $own + $shared;
	}

	/**
	 * The icon names, for validating a saved choice.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		return array_keys( self::paths() );
	}

	/**
	 * A field title's icon as inline SVG, or '' for none or an unknown name.
	 *
	 * @param string $icon One of names().
	 */
	public static function svg( string $icon ): string {
		$paths = self::paths();

		if ( ! isset( $paths[ $icon ] ) ) {
			return '';
		}

		return '<span class="iftp-btn-form__label-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">' . $paths[ $icon ] . '</svg></span>';
	}
}
