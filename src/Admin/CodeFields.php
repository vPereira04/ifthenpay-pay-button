<?php
/**
 * The boxes where a merchant types their own CSS or JavaScript.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One box, with the same checks as CustomCode while typing (assets/js/buttons/code.js) and the same read-only
 * rule for users who may not post unfiltered HTML.
 */
class CodeFields {

	/**
	 * A labelled code box.
	 *
	 * @param string $kind        'css' or 'js'.
	 * @param string $id          The textarea's id.
	 * @param string $name        Its field name.
	 * @param string $label       What it's for, read by screen readers.
	 * @param string $value       What's saved.
	 * @param string $placeholder A short example.
	 */
	public static function render( string $kind, string $id, string $name, string $label, string $value, string $placeholder ): void {
		// The button's CSS is open to anyone who can edit buttons, as it is in the save; the rest needs unfiltered HTML.
		$may = current_user_can( 'unfiltered_html' ) || 'config[code][button_css]' === $name;
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<textarea class="iftp-btn-field__control iftp-btn-code-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="8" spellcheck="false" autocomplete="off" autocapitalize="off" maxlength="30000" placeholder="<?php echo esc_attr( $placeholder ); ?>" data-iftp-codebox="<?php echo esc_attr( $kind ); ?>" aria-describedby="<?php echo esc_attr( $id ); ?>-state"<?php echo $may ? '' : ' readonly'; ?>><?php echo esc_textarea( $value ); ?></textarea>
		<p class="iftp-btn-code-state" id="<?php echo esc_attr( $id ); ?>-state" data-iftp-code-state role="status"></p>
		<?php if ( ! $may ) : ?>
			<p class="iftp-btn-field__help"><?php esc_html_e( 'Only users who may post unfiltered HTML can change custom code.', 'ifthenpay-pay-button' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * The CSS and JavaScript of a button set to "My own code", for its Design tab.
	 *
	 * @param array{button_css: string, button_js: string, page_css: string, page_js: string} $code What's saved.
	 */
	public static function render_button_code( array $code ): void {
		self::render_pair(
			'button',
			$code['button_css'],
			$code['button_js'],
			__( 'The button comes with no styles at all, so write them here. They only touch this button.', 'ifthenpay-pay-button' ),
			__( 'Start from .iftp-btn-pay__button (the button), .iftp-btn-pay__group and .iftp-btn-pay__logos. The preview beside the editor shows your code as you type.', 'ifthenpay-pay-button' ),
			".iftp-btn-pay__button {\n\tpadding: 12px 22px;\n\tborder-radius: 8px;\n\tbackground: #00609c;\n\tcolor: #fff;\n}",
			"scope.forEach( function ( wrapper ) {\n\t// wrapper is this button's element.\n} );"
		);
	}

	/**
	 * The CSS and JavaScript of a button whose checkout is set to "My own code".
	 *
	 * @param array{button_css: string, button_js: string, page_css: string, page_js: string} $code What's saved.
	 */
	public static function render_page_code( array $code ): void {
		self::render_pair(
			'page',
			$code['page_css'],
			$code['page_js'],
			__( 'The checkout popup and this button\'s pay page load none of our styles, so only this and a tiny base apply. The popups after paying keep the site\'s look.', 'ifthenpay-pay-button' ),
			__( 'Style .iftp-btn-sheet (the popup), .iftp-btn-form (the card), .iftp-btn-steps (page progress), .iftp-btn-form__input (fields), .iftp-btn-form__submit and .iftp-btn-page-body (the pay page background).', 'ifthenpay-pay-button' ),
			".iftp-btn-form {\n\tmax-width: 520px;\n\tmargin: 0 auto;\n\tpadding: 24px;\n\tborder-radius: 16px;\n\tbackground: #fff;\n}",
			"scope.forEach( function ( root ) {\n\t// root is the button's element, or the pay page.\n} );"
		);
	}

	/**
	 * A CSS box and a JavaScript box side by side in a card body.
	 *
	 * @param string $prefix  'button' or 'page', for the ids and field names.
	 * @param string $css     Saved CSS.
	 * @param string $js      Saved JavaScript.
	 * @param string $intro   One line on what the boxes do.
	 * @param string $classes The classes worth styling.
	 * @param string $css_eg  Placeholder CSS.
	 * @param string $js_eg   Placeholder JavaScript.
	 */
	private static function render_pair( string $prefix, string $css, string $js, string $intro, string $classes, string $css_eg, string $js_eg ): void {
		?>
		<div class="iftp-btn-codepair">
			<p class="iftp-btn-field__help"><?php echo esc_html( $intro ); ?></p>
			<div class="iftp-btn-field">
				<span class="iftp-btn-field__label"><?php esc_html_e( 'CSS', 'ifthenpay-pay-button' ); ?></span>
				<?php self::render( 'css', 'iftp-btn-code-' . $prefix . '-css', 'config[code][' . $prefix . '_css]', __( 'Custom CSS', 'ifthenpay-pay-button' ), $css, $css_eg ); ?>
				<p class="iftp-btn-field__help"><?php echo esc_html( $classes ); ?> <?php esc_html_e( 'Anything you write is scoped to this button. @keyframes, @font-face and @property work. Only @import and @layer can\'t be used.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-field">
				<span class="iftp-btn-field__label"><?php esc_html_e( 'JavaScript', 'ifthenpay-pay-button' ); ?></span>
				<?php self::render( 'js', 'iftp-btn-code-' . $prefix . '-js', 'config[code][' . $prefix . '_js]', __( 'Custom JavaScript', 'ifthenpay-pay-button' ), $js, $js_eg ); ?>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'Runs only on pages where this button is, in its own scope, after the checkout script. scope is the list of this button\'s elements. An error shows in the browser console and stops nothing else.', 'ifthenpay-pay-button' ); ?></p>
			</div>
		</div>
		<?php
	}
}
