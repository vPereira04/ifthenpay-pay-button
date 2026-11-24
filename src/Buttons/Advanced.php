<?php
/**
 * The Advanced button designs: drawn-in-code buttons that ship with the plugin, each with up to three colours to change.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Buttons;

use Ifthenpay\PayButton\Checkout\CustomCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every design is a CSS file and a JavaScript file in Advanced/templates, drawn the way a merchant's own code would be
 * and printed through the same scoped pipeline as "My own code". Every colour in them hangs off `--a1`, `--a2` and so on up
 * to `--a6` (a shade is the same variable made lighter or darker with relative colour syntax, never turned to another hue), so
 * changing the colours a design lists changes the whole drawing. Colours that are white, black or grey stay as they are.
 *
 * @phpstan-type AdvancedEntry array{name: string, label: string, colors: array<int, string>, roles?: array<int, string>}
 */
class Advanced {

	// How many colours a design can have at most.
	public const MAX_COLORS = 6;

	// The folder with the templates and the list.
	private const DIR = __DIR__ . '/Advanced';

	/**
	 * The designs by slug, in the order they are offered.
	 *
	 * @var array<string, AdvancedEntry>|null
	 */
	private static ?array $catalogue = null;

	/**
	 * The designs by slug.
	 *
	 * @return array<string, AdvancedEntry>
	 */
	public static function catalogue(): array {
		if ( null === self::$catalogue ) {
			$list            = require self::DIR . '/catalogue.php';
			self::$catalogue = is_array( $list ) ? $list : array();
		}

		return self::$catalogue;
	}

	/**
	 * Whether a slug is one of the designs.
	 *
	 * @param mixed $slug The slug to check.
	 * @phpstan-assert-if-true string $slug
	 */
	public static function has( $slug ): bool {
		return is_string( $slug ) && isset( self::catalogue()[ $slug ] );
	}

	/**
	 * The slug of the first design, which a merchant gets when they pick Advanced without choosing one.
	 */
	public static function first(): string {
		return (string) array_key_first( self::catalogue() );
	}

	/**
	 * The colours a design wears: the ones picked, and its own where none was.
	 *
	 * @param string             $slug   The design.
	 * @param array<int, string> $picked The colours picked, '' for the design's own.
	 * @return array<int, string> Hex colours.
	 */
	public static function colors( string $slug, array $picked ): array {
		$own    = self::catalogue()[ $slug ]['colors'] ?? array();
		$colors = array();

		foreach ( $own as $index => $default ) {
			$colors[ $index ] = '' !== ( $picked[ $index ] ?? '' ) ? (string) $picked[ $index ] : $default;
		}

		return $colors;
	}

	/**
	 * Cleans the colours sent for a design: a hex colour each, or '' for the design's own.
	 *
	 * @param mixed  $input The raw colours.
	 * @param string $slug  The design they are for.
	 * @return array<int, string> One entry per colour the design has.
	 */
	public static function sanitize_colors( $input, string $slug ): array {
		$input  = is_array( $input ) ? array_values( $input ) : array();
		$colors = array();

		foreach ( array_keys( self::catalogue()[ $slug ]['colors'] ?? array() ) as $index ) {
			$colors[ $index ] = is_string( $input[ $index ] ?? null ) ? ButtonDesign::hex( $input[ $index ] ) : '';
		}

		return $colors;
	}

	/**
	 * A design's CSS with its colours set, ready to go inside a button's scope.
	 *
	 * @param string             $slug   The design.
	 * @param array<int, string> $picked The colours picked.
	 */
	public static function css( string $slug, array $picked ): string {
		return self::variables( $slug, $picked ) . "\n" . self::read( $slug, 'css' );
	}

	/**
	 * A design's JavaScript, which works on the elements of `scope`.
	 *
	 * @param string $slug The design.
	 */
	public static function js( string $slug ): string {
		return self::read( $slug, 'js' );
	}

	/**
	 * The custom properties that carry a design's colours.
	 *
	 * @param string             $slug   The design.
	 * @param array<int, string> $picked The colours picked.
	 */
	public static function variables( string $slug, array $picked ): string {
		$css = '';

		foreach ( self::colors( $slug, $picked ) as $index => $color ) {
			$css .= '--a' . ( $index + 1 ) . ':' . $color . ';';
		}

		return $css;
	}

	/**
	 * Every design's CSS and JavaScript for the picker in the admin, where each lives under its own scope name so the tiles and
	 * the preview don't take over each other's.
	 *
	 * @return array<string, array{name: string, label: string, colors: array<int, string>, roles: array<int, string>, css: string, js: string}>
	 */
	public static function library(): array {
		$library = array();

		foreach ( self::catalogue() as $slug => $entry ) {
			$scope = self::scope_name( $slug );
			$css   = CustomCode::scope_css( $scope, self::css( $slug, array() ), 4, '{pointer-events:auto}' );

			$library[ $slug ] = array(
				'name'   => $entry['name'],
				'label'  => $entry['label'],
				'colors' => $entry['colors'],
				'roles'  => self::roles( $slug ),
				'css'    => $css,
				'js'     => self::js( $slug ),
			);
		}

		return $library;
	}

	/**
	 * The name a design's elements carry in the admin, where there is no button id to scope to.
	 *
	 * @param string $slug The design.
	 */
	public static function scope_name( string $slug ): string {
		return 'adv-' . $slug;
	}

	/**
	 * What the picker needs from the page: where to ask for the library and the labels of the colours.
	 *
	 * @param string $nonce The nonce the library's request carries.
	 * @return array{url: string, nonce: string, roles: array<int, string>}
	 */
	public static function script_data( string $nonce = '' ): array {
		return array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => $nonce,
			'roles' => self::generic_roles(),
		);
	}

	/**
	 * What each colour of a design is called: the design's own names where it gives them, the generic ones where not.
	 * The design's names are written out in the catalogue, so a translation file can carry them.
	 *
	 * @param string $slug The design.
	 * @return array<int, string> One name per colour the design has.
	 */
	public static function roles( string $slug ): array {
		$entry   = self::catalogue()[ $slug ] ?? null;
		$generic = self::generic_roles();
		$names   = array();

		foreach ( array_keys( $entry['colors'] ?? array() ) as $index ) {
			$own             = $entry['roles'][ $index ] ?? '';
			$names[ $index ] = '' !== $own ? $own : $generic[ $index ];
		}

		return $names;
	}

	/**
	 * The names for a colour a design doesn't name.
	 *
	 * @return array<int, string>
	 */
	private static function generic_roles(): array {
		return array(
			__( 'Main colour', 'ifthenpay-pay-button' ),
			__( 'Second colour', 'ifthenpay-pay-button' ),
			__( 'Accent colour', 'ifthenpay-pay-button' ),
			__( 'Fourth colour', 'ifthenpay-pay-button' ),
			__( 'Fifth colour', 'ifthenpay-pay-button' ),
			__( 'Sixth colour', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * One of a design's files.
	 *
	 * @param string $slug The design.
	 * @param string $kind `css` or `js`.
	 */
	private static function read( string $slug, string $kind ): string {
		if ( ! self::has( $slug ) ) {
			return '';
		}

		$text = file_get_contents( self::DIR . '/templates/' . $slug . '.' . $kind ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the plugin's own file.

		return is_string( $text ) ? $text : '';
	}
}
