<?php
/**
 * The Light / System / Dark switch every preview shares.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three round buttons: sun, device, moon. scheme.js keeps the pick in the browser and marks the pressed one,
 * so it is the same in every preview and every tab. Light is pressed until the script says otherwise.
 */
class SchemeControl {

	/**
	 * Prints the control.
	 *
	 * @param string $class_name An extra class, to place it.
	 */
	public static function render( string $class_name = '' ): void {
		$modes = array(
			'light'  => array(
				__( 'Light mode', 'ifthenpay-pay-button' ),
				'<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>',
			),
			'system' => array(
				__( 'System (follows your device)', 'ifthenpay-pay-button' ),
				'<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8.5 20h7M12 16.5V20"/>',
			),
			'dark'   => array(
				__( 'Dark mode', 'ifthenpay-pay-button' ),
				'<path d="M20 14.2A8 8 0 0 1 9.8 4 8 8 0 1 0 20 14.2z"/>',
			),
		);
		?>
		<span class="iftp-btn-scheme<?php echo '' !== $class_name ? ' ' . esc_attr( $class_name ) : ''; ?>" role="group" aria-label="<?php esc_attr_e( 'Preview colour mode', 'ifthenpay-pay-button' ); ?>">
			<?php foreach ( $modes as $mode => $option ) : ?>
				<button type="button" class="iftp-btn-scheme__option" data-iftp-scheme-pick="<?php echo esc_attr( $mode ); ?>" aria-pressed="<?php echo 'light' === $mode ? 'true' : 'false'; ?>" title="<?php echo esc_attr( $option[0] ); ?>">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $option[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG paths. ?></svg>
					<span class="screen-reader-text"><?php echo esc_html( $option[0] ); ?></span>
				</button>
			<?php endforeach; ?>
		</span>
		<?php
	}
}
