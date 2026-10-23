<?php
/**
 * The [ifthenpay_button] shortcode.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Buttons\ButtonRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For classic themes, page builders and widgets: `[ifthenpay_button id="12" label="Book now"]`.
 */
class Shortcode {

	public const TAG = 'ifthenpay_button';

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
	 * Sets up the shortcode.
	 *
	 * @param ButtonRepository $buttons  Buttons storage.
	 * @param ButtonRenderer   $renderer Renders buttons.
	 */
	public function __construct( ButtonRepository $buttons, ButtonRenderer $renderer ) {
		$this->buttons  = $buttons;
		$this->renderer = $renderer;
	}

	/**
	 * Registers the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array<string, string>|string $atts The shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'    => '0',
				'label' => '',
				'align' => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$button = $this->buttons->find( absint( $atts['id'] ) );

		if ( null === $button ) {
			return '';
		}

		$align = in_array( $atts['align'], array( 'left', 'center', 'right' ), true ) ? ' iftp-btn-pay--align-' . $atts['align'] : '';

		return $this->renderer->render_button(
			$button,
			array(
				'label'   => sanitize_text_field( $atts['label'] ),
				'wrapper' => 'class="iftp-btn-pay wp-block-button' . esc_attr( $align ) . '"',
			)
		);
	}
}
