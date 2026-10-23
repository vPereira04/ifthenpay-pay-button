<?php
/**
 * The pay button block.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the block and renders it on the server, so the editor preview and the page are the same markup.
 * The editor script is plain JS on the wp.* globals, so there's no build step.
 */
class Block {

	public const EDITOR_HANDLE = 'iftp-btn-block-editor';

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Renders buttons.
	 *
	 * @var ButtonRenderer
	 */
	private ButtonRenderer $renderer;

	/**
	 * Sets up the block.
	 *
	 * @param ButtonRepository $buttons  Buttons storage.
	 * @param ButtonRenderer   $renderer Renders buttons.
	 */
	public function __construct( ButtonRepository $buttons, ButtonRenderer $renderer ) {
		$this->buttons  = $buttons;
		$this->renderer = $renderer;
	}

	/**
	 * Hooks the registration. After ButtonRenderer's, which registers the style the block uses.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Registers the editor script and the block.
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$js = 'blocks/pay-button/editor.js';

		wp_register_script( self::EDITOR_HANDLE, IFTP_BTN_URL . $js, array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ), Plugin::asset_version( $js ), true );
		wp_set_script_translations( self::EDITOR_HANDLE, 'ifthenpay-pay-button' );

		register_block_type( IFTP_BTN_PATH . 'blocks/pay-button', array( 'render_callback' => array( $this, 'render' ) ) );
	}

	/**
	 * The buttons list for the block's picker. Only in the editor, so the frontend never prints it.
	 */
	public function editor_data(): void {
		$options = array();

		foreach ( $this->buttons->all() as $button ) {
			$options[] = array(
				'value'  => $button->id,
				'label'  => $button->title,
				'active' => $button->active,
			);
		}

		wp_localize_script(
			self::EDITOR_HANDLE,
			'iftpBtnBlock',
			array(
				'buttons' => $options,
				'newUrl'  => current_user_can( Plugin::admin_cap() ) ? ButtonsPage::edit_url( 0 ) : '',
			)
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 */
	public function render( array $attributes ): string {
		$button = $this->buttons->find( absint( $attributes['buttonId'] ?? 0 ) );

		if ( null === $button ) {
			return '';
		}

		$styles = self::button_styles( $attributes );

		return $this->renderer->render_button(
			$button,
			array(
				'label'        => is_string( $attributes['label'] ?? null ) ? sanitize_text_field( $attributes['label'] ) : '',
				'wrapper'      => get_block_wrapper_attributes( array( 'class' => 'iftp-btn-pay wp-block-button' ) ),
				'button_class' => $styles['class'],
				'button_style' => $styles['style'],
			)
		);
	}

	/**
	 * The colour, border and padding the block sets, moved from the wrapper onto the <button> itself.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 * @return array{class: string, style: string}
	 */
	private static function button_styles( array $attributes ): array {
		$style   = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$classes = array();
		$presets = array(
			'backgroundColor' => array( 'has-background', 'has-%s-background-color' ),
			'textColor'       => array( 'has-text-color', 'has-%s-color' ),
			'gradient'        => array( 'has-background', 'has-%s-gradient-background' ),
		);

		foreach ( $presets as $attribute => $pair ) {
			if ( is_string( $attributes[ $attribute ] ?? null ) && '' !== $attributes[ $attribute ] ) {
				$classes[] = $pair[0];
				$classes[] = sprintf( $pair[1], sanitize_html_class( $attributes[ $attribute ] ) );
			}
		}

		if ( ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
		}

		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
		}

		$css = wp_style_engine_get_styles(
			array(
				'color'   => is_array( $style['color'] ?? null ) ? $style['color'] : array(),
				'border'  => is_array( $style['border'] ?? null ) ? $style['border'] : array(),
				'spacing' => array( 'padding' => $style['spacing']['padding'] ?? array() ),
			)
		);

		return array(
			'class' => implode( ' ', array_unique( $classes ) ),
			'style' => is_string( $css['css'] ?? null ) ? $css['css'] : '',
		);
	}
}
