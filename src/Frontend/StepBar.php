<?php
/**
 * The step bar of a checkout with pages.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One marker per page, the current page's title and where the payer is. Every style in
 * CheckoutLook::STEP_STYLES draws from this same markup, so a style is only a class: checkout.css
 * decides what shows. The checkout, the Design preview and the style picker all print it here.
 */
class StepBar {

	/**
	 * Prints the bar.
	 *
	 * @param array<int, string>   $titles Each page's title.
	 * @param string               $style  One of CheckoutLook::STEP_STYLES.
	 * @param array<string, mixed> $args   id: the form's id prefix; live: hooks for checkout.js; texts: hooks for
	 *                                     the Texts tab; current: the page on show; fill: how much of it is answered.
	 * @phpstan-param array{id?: string, live?: bool, texts?: bool, current?: int, fill?: float} $args
	 */
	public static function render( array $titles, string $style, array $args = array() ): void {
		$args    = $args + array(
			'id'      => '',
			'live'    => false,
			'texts'   => false,
			'current' => 0,
			'fill'    => 0.0,
		);
		$count   = count( $titles );
		$current = max( 0, min( $count - 1, $args['current'] ) );
		$style   = in_array( $style, CheckoutLook::STEP_STYLES, true ) ? $style : 'bars';
		$share   = ( $current + $args['fill'] ) / max( 1, $count );
		// Escaped before the tokens go in, since {step} becomes markup checkout.js counts up in.
		$step_of = strtr(
			esc_html( CheckoutTexts::get( 'step_of' ) ),
			array(
				'{step}'  => $args['live'] ? '<span data-iftp-btn-step-now>' . ( $current + 1 ) . '</span>' : (string) ( $current + 1 ),
				'{steps}' => (string) $count,
			)
		);
		?>
		<div class="iftp-btn-steps iftp-btn-steps--<?php echo esc_attr( $style ); ?>"<?php echo $args['live'] ? ' data-iftp-btn-steps' : ''; ?> style="--iftp-steps: <?php echo esc_attr( (string) $count ); ?>">
			<div class="iftp-btn-steps__head">
				<span class="iftp-btn-steps__title"<?php echo $args['live'] ? ' id="' . esc_attr( $args['id'] ) . '-step-title" data-iftp-btn-step-title aria-live="polite"' : ''; ?><?php echo $args['texts'] && 0 === $current ? ' data-iftp-text="first_step"' : ''; ?>><?php echo esc_html( $titles[ $current ] ); ?></span>
				<span class="iftp-btn-steps__count"<?php echo $args['texts'] ? ' data-iftp-text="step_of" data-step="' . esc_attr( (string) ( $current + 1 ) ) . '" data-steps="' . esc_attr( (string) $count ) . '"' : ''; ?>><?php echo wp_kses( $step_of, array( 'span' => array( 'data-iftp-btn-step-now' => true ) ) ); ?></span>
				<span class="iftp-btn-steps__pct" aria-hidden="true"<?php echo $args['live'] ? ' data-iftp-btn-step-pct' : ''; ?>><?php echo esc_html( round( $share * 100 ) . '%' ); ?></span>
			</div>
			<ol class="iftp-btn-steps__track">
				<?php foreach ( $titles as $index => $title ) : ?>
					<?php
					$state = $index < $current ? ' is-done' : ( $index === $current ? ' is-current' : '' );
					$vars  = '--iftp-i: ' . $index . ( $index === $current ? '; --iftp-fill: ' . round( $args['fill'], 3 ) : '' );
					?>
					<li class="iftp-btn-steps__bar<?php echo esc_attr( $state ); ?>"<?php echo $index === $current ? ' aria-current="step"' : ''; ?><?php echo $args['live'] ? ' data-iftp-btn-bar data-title="' . esc_attr( $title ) . '"' : ''; ?> style="<?php echo esc_attr( $vars ); ?>">
						<span class="iftp-btn-steps__seg"><span class="iftp-btn-steps__fill"></span></span>
						<span class="iftp-btn-steps__num" aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
						<span class="iftp-btn-steps__label"<?php echo $args['texts'] && 0 === $index ? ' data-iftp-text="first_step"' : ''; ?>><?php echo esc_html( $title ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}
}
