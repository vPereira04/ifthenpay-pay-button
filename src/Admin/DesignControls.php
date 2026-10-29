<?php
/**
 * The button design controls and their preview, for the site's default and a button's own.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default Design › Button and a button's Design › The button draw the same controls and preview; only where the
 * fields post to differs (`button_design[…]` or `config[design][…]`), and only a button can follow the default.
 * buttons.js reads them by their data-iftp-design keys.
 *
 * @phpstan-import-type ButtonDesignConfig from ButtonDesign
 */
class DesignControls {

	// How many style and preset tiles show before "Show all".
	private const FEATURED_STYLES  = 8;
	private const FEATURED_PRESETS = 7;

	/**
	 * What every field's name starts with.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Whether "Default design" is one of the choices.
	 *
	 * @var bool
	 */
	private bool $with_site;

	/**
	 * The design saved as the site's default, offered first among the presets to come back to.
	 *
	 * @var array<string, mixed>
	 * @phpstan-var ButtonDesignConfig
	 */
	private array $saved;

	/**
	 * The design saved on the button being edited, offered after the default; empty when there is none.
	 *
	 * @var array<string, mixed>
	 */
	private array $own;

	/**
	 * Sets up the controls.
	 *
	 * @param string               $prefix    The field name prefix, e.g. `button_design` or `config[design]`.
	 * @param bool                 $with_site Whether to offer the site's default design.
	 * @param array<string, mixed> $saved     The design saved as the site's default.
	 * @param array<string, mixed> $own       The design saved on the button being edited, if it has its own.
	 * @phpstan-param ButtonDesignConfig $saved
	 */
	public function __construct( string $prefix, bool $with_site, array $saved, array $own = array() ) {
		$this->prefix    = $prefix;
		$this->with_site = $with_site;
		$this->saved     = $saved;
		$this->own       = $own;
	}

	/**
	 * A field's name.
	 *
	 * @param string $key The design key.
	 */
	private function name( string $key ): string {
		return $this->prefix . '[' . $key . ']';
	}

	/**
	 * Where the look comes from, the custom look's controls, and the extras that work with every look.
	 *
	 * @param array<string, mixed> $design The design to show.
	 * @phpstan-param ButtonDesignConfig $design
	 */
	public function render( array $design ): void {
		$custom     = ButtonDesign::SOURCE_CUSTOM === $design['source'];
		$own        = ButtonDesign::SOURCE_SITE !== $design['source'];
		$auto       = '' === $design['text'];
		$auto2      = '' === $design['color2'];
		$auto_bc    = '' === $design['border_color'];
		$sources    = array(
			ButtonDesign::SOURCE_SITE   => array( __( 'Default design', 'ifthenpay-pay-button' ), __( 'The same as every button set to it.', 'ifthenpay-pay-button' ) ),
			ButtonDesign::SOURCE_THEME  => array( __( 'Match my theme', 'ifthenpay-pay-button' ), __( 'Looks like the other buttons on your site.', 'ifthenpay-pay-button' ) ),
			ButtonDesign::SOURCE_CUSTOM => array( __( 'Custom', 'ifthenpay-pay-button' ), __( 'Your own colour, shape and size.', 'ifthenpay-pay-button' ) ),
		);
		$sources    = $this->with_site ? $sources : array_intersect_key( $sources, array_flip( ButtonDesign::OWN_SOURCES ) );
		$variants   = array(
			'filled'   => __( 'Filled', 'ifthenpay-pay-button' ),
			'outline'  => __( 'Outline', 'ifthenpay-pay-button' ),
			'soft'     => __( 'Soft', 'ifthenpay-pay-button' ),
			'gradient' => __( 'Gradient', 'ifthenpay-pay-button' ),
			'aurora'   => __( 'Aurora', 'ifthenpay-pay-button' ),
			'split'    => __( 'Split', 'ifthenpay-pay-button' ),
			'halo'     => __( 'Halo', 'ifthenpay-pay-button' ),
			'glass'    => __( 'Glass', 'ifthenpay-pay-button' ),
			'3d'       => __( '3D', 'ifthenpay-pay-button' ),
			'neon'     => __( 'Neon', 'ifthenpay-pay-button' ),
			'brutal'   => __( 'Brutal', 'ifthenpay-pay-button' ),
			'ghost'    => __( 'Ghost', 'ifthenpay-pay-button' ),
			'luxe'     => __( 'Luxe', 'ifthenpay-pay-button' ),
			'beam'     => __( 'Beam', 'ifthenpay-pay-button' ),
			'clay'     => __( 'Clay', 'ifthenpay-pay-button' ),
			'ticket'   => __( 'Ticket', 'ifthenpay-pay-button' ),
			'gloss'    => __( 'Gloss', 'ifthenpay-pay-button' ),
			'ring'     => __( 'Ring', 'ifthenpay-pay-button' ),
			'stripe'   => __( 'Stripe', 'ifthenpay-pay-button' ),
			'cut'      => __( 'Cut', 'ifthenpay-pay-button' ),
		);
		$effects    = array(
			'none'  => __( 'None', 'ifthenpay-pay-button' ),
			'lift'  => __( 'Lift', 'ifthenpay-pay-button' ),
			'grow'  => __( 'Grow', 'ifthenpay-pay-button' ),
			'shine' => __( 'Shine', 'ifthenpay-pay-button' ),
			'glow'  => __( 'Glow', 'ifthenpay-pay-button' ),
			'fill'  => __( 'Fill', 'ifthenpay-pay-button' ),
			'nudge' => __( 'Nudge', 'ifthenpay-pay-button' ),
			'gild'  => __( 'Gild', 'ifthenpay-pay-button' ),
			'tilt'  => __( 'Tilt', 'ifthenpay-pay-button' ),
		);
		$attentions = array(
			'none'      => __( 'None', 'ifthenpay-pay-button' ),
			'pulse'     => __( 'Pulse', 'ifthenpay-pay-button' ),
			'ripple'    => __( 'Ripple', 'ifthenpay-pay-button' ),
			'heartbeat' => __( 'Heartbeat', 'ifthenpay-pay-button' ),
			'wiggle'    => __( 'Wiggle', 'ifthenpay-pay-button' ),
			'bounce'    => __( 'Bounce', 'ifthenpay-pay-button' ),
			'shimmer'   => __( 'Shimmer', 'ifthenpay-pay-button' ),
			'breathe'   => __( 'Breathe', 'ifthenpay-pay-button' ),
			'orbit'     => __( 'Orbit', 'ifthenpay-pay-button' ),
		);
		$borders    = array(
			'solid'  => __( 'Solid', 'ifthenpay-pay-button' ),
			'dashed' => __( 'Dashed', 'ifthenpay-pay-button' ),
			'dotted' => __( 'Dotted', 'ifthenpay-pay-button' ),
			'double' => __( 'Double', 'ifthenpay-pay-button' ),
		);
		$sizes      = array(
			'sm' => __( 'Small', 'ifthenpay-pay-button' ),
			'md' => __( 'Medium', 'ifthenpay-pay-button' ),
			'lg' => __( 'Large', 'ifthenpay-pay-button' ),
		);
		$icons      = array(
			'none'     => __( 'None', 'ifthenpay-pay-button' ),
			'lock'     => __( 'Lock', 'ifthenpay-pay-button' ),
			'card'     => __( 'Card', 'ifthenpay-pay-button' ),
			'arrow'    => __( 'Arrow', 'ifthenpay-pay-button' ),
			'heart'    => __( 'Heart', 'ifthenpay-pay-button' ),
			'cart'     => __( 'Cart', 'ifthenpay-pay-button' ),
			'gift'     => __( 'Gift', 'ifthenpay-pay-button' ),
			'ticket'   => __( 'Ticket', 'ifthenpay-pay-button' ),
			'calendar' => __( 'Calendar', 'ifthenpay-pay-button' ),
			'star'     => __( 'Star', 'ifthenpay-pay-button' ),
			'sparkles' => __( 'Sparkles', 'ifthenpay-pay-button' ),
			'paw'      => __( 'Paw', 'ifthenpay-pay-button' ),
			'leaf'     => __( 'Leaf', 'ifthenpay-pay-button' ),
			'bolt'     => __( 'Bolt', 'ifthenpay-pay-button' ),
		);
		$widths     = array(
			'0' => __( 'Fit the text', 'ifthenpay-pay-button' ),
			'1' => __( 'Full width', 'ifthenpay-pay-button' ),
		);
		?>
		<fieldset class="iftp-btn-segment<?php echo $this->with_site ? '' : ' iftp-btn-segment--two'; ?>">
			<legend class="screen-reader-text"><?php esc_html_e( 'Where the look comes from', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $sources as $source => $labels ) : ?>
				<label class="iftp-btn-segment__option">
					<input type="radio" name="<?php echo esc_attr( $this->name( 'source' ) ); ?>" value="<?php echo esc_attr( $source ); ?>" <?php checked( $design['source'], $source ); ?> data-iftp-design="source" />
					<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
					<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<?php if ( $this->with_site ) : ?>
			<p class="iftp-btn-field__help" data-iftp-when-source="site"<?php echo $own ? ' hidden' : ''; ?>>
				<?php esc_html_e( 'Change it for every button that uses it at once:', 'ifthenpay-pay-button' ); ?>
				<a href="<?php echo esc_url( CheckoutSettingsPage::url( 'button' ) ); ?>"><?php esc_html_e( 'Default Design › Button', 'ifthenpay-pay-button' ); ?></a>
			</p>
		<?php endif; ?>

		<div class="iftp-btn-design" data-iftp-when-source="custom"<?php echo $custom ? '' : ' hidden'; ?>>
			<?php self::open_fold( 'design-start', __( 'Start from a preset', 'ifthenpay-pay-button' ), __( 'One click sets the style, colours and effects. Tweak anything after.', 'ifthenpay-pay-button' ), true ); ?>
			<div class="iftp-btn-field">
				<span class="screen-reader-text" id="iftp-btn-presets-label"><?php esc_html_e( 'Start from', 'ifthenpay-pay-button' ); ?></span>
				<div class="iftp-btn-looks is-folded" role="group" aria-labelledby="iftp-btn-presets-label" data-iftp-more-list>
					<button type="button" class="iftp-btn-looks__item" data-iftp-preset-saved="<?php echo esc_attr( (string) wp_json_encode( $this->saved ) ); ?>">
						<span class="iftp-btn-looks__stage" aria-hidden="true">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes.
							echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( $this->saved, __( 'Pay', 'ifthenpay-pay-button' ) ) . '</span>';
							?>
						</span>
						<span class="iftp-btn-looks__name"><?php esc_html_e( 'Default look', 'ifthenpay-pay-button' ); ?></span>
					</button>
					<?php if ( array() !== $this->own ) : ?>
					<button type="button" class="iftp-btn-looks__item" data-iftp-preset-saved="<?php echo esc_attr( (string) wp_json_encode( $this->own ) ); ?>">
						<span class="iftp-btn-looks__stage" aria-hidden="true">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes.
							echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( $this->own, __( 'Pay', 'ifthenpay-pay-button' ) ) . '</span>';
							?>
						</span>
						<span class="iftp-btn-looks__name"><?php esc_html_e( 'This button\'s look', 'ifthenpay-pay-button' ); ?></span>
					</button>
					<?php endif; ?>
					<?php $shown_presets = 0; ?>
					<?php foreach ( ButtonDesign::presets() as $key => $preset ) : ?>
						<?php
						// A button's own design isn't the ifthenpay one; that stays the site's.
						if ( $this->with_site && 'ifthenpay' === $key ) {
							continue;
						}

						++$shown_presets;
						?>
						<button type="button" class="iftp-btn-looks__item<?php echo $shown_presets > self::FEATURED_PRESETS ? ' iftp-btn-looks__item--more' : ''; ?>" data-iftp-preset="<?php echo esc_attr( $key ); ?>">
							<span class="iftp-btn-looks__stage" aria-hidden="true">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes.
								echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( array_merge( ButtonDesign::defaults(), $preset, array( 'source' => ButtonDesign::SOURCE_CUSTOM ) ), __( 'Pay', 'ifthenpay-pay-button' ) ) . '</span>';
								?>
							</span>
							<span class="iftp-btn-looks__name"><?php echo esc_html( $preset['label'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<?php $this->render_more_toggle( $shown_presets ); ?>
			</div>
			<?php self::close_fold(); ?>

			<?php self::open_fold( 'design-style', __( 'Style and colour', 'ifthenpay-pay-button' ), __( 'Filled, outline, gradient, glass and more, in your colours.', 'ifthenpay-pay-button' ), true ); ?>
			<div class="iftp-btn-design__grid">
				<?php $this->render_fx_picker( 'variant', __( 'Style', 'ifthenpay-pay-button' ), $variants, $design, '', self::FEATURED_STYLES ); ?>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-color"><?php esc_html_e( 'Colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color">
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( $design['color'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-color" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-color" name="<?php echo esc_attr( $this->name( 'color' ) ); ?>" value="<?php echo esc_attr( $design['color'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="color" />
					</span>
				</div>

				<div class="iftp-btn-field" data-iftp-when-variant="gradient aurora split luxe beam"<?php echo in_array( $design['variant'], array( 'gradient', 'aurora', 'split', 'luxe', 'beam' ), true ) ? '' : ' hidden'; ?>>
					<label class="iftp-btn-field__label" for="iftp-btn-color2"><?php esc_html_e( 'Second colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color<?php echo $auto2 ? ' is-auto' : ''; ?>" data-iftp-color2>
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( ButtonDesign::second_color( $design ) ); ?>" aria-label="<?php esc_attr_e( 'Pick the second colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-color2" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-color2" name="<?php echo esc_attr( $this->name( 'color2' ) ); ?>" value="<?php echo esc_attr( ButtonDesign::second_color( $design ) ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="color2" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="<?php echo esc_attr( $this->name( 'color2_auto' ) ); ?>" value="1" <?php checked( $auto2 ); ?> data-iftp-design="color2_auto" />
						<?php esc_html_e( 'Pick it for me (a neighbouring shade)', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-text-color"><?php esc_html_e( 'Text colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color<?php echo $auto ? ' is-auto' : ''; ?>" data-iftp-text-color>
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( ButtonDesign::text_color( $design ) ); ?>" aria-label="<?php esc_attr_e( 'Pick the text colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-text-color" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-text-color" name="<?php echo esc_attr( $this->name( 'text' ) ); ?>" value="<?php echo esc_attr( ButtonDesign::text_color( $design ) ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="text" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="<?php echo esc_attr( $this->name( 'text_auto' ) ); ?>" value="1" <?php checked( $auto ); ?> data-iftp-design="text_auto" />
						<?php esc_html_e( 'Pick it for me (best contrast)', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>

			</div>
			<?php self::close_fold(); ?>

			<?php self::open_fold( 'design-shape', __( 'Shape, size and border', 'ifthenpay-pay-button' ), __( 'Corners, size, an outline and a soft shadow.', 'ifthenpay-pay-button' ) ); ?>
			<div class="iftp-btn-design__grid">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-radius"><?php esc_html_e( 'Corners', 'ifthenpay-pay-button' ); ?> <output class="iftp-btn-range__value" for="iftp-btn-radius" data-iftp-radius-out></output></label>
					<span class="iftp-btn-range">
						<span class="iftp-btn-range__end iftp-btn-range__end--square" aria-hidden="true"></span>
						<input type="range" class="iftp-btn-range__input" id="iftp-btn-radius" name="<?php echo esc_attr( $this->name( 'radius' ) ); ?>" min="0" max="<?php echo esc_attr( (string) ButtonDesign::PILL ); ?>" step="1" value="<?php echo esc_attr( (string) $design['radius'] ); ?>" data-iftp-design="radius" />
						<span class="iftp-btn-range__end iftp-btn-range__end--pill" aria-hidden="true"></span>
					</span>
				</div>

				<fieldset class="iftp-btn-field">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Size', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg">
						<?php foreach ( $sizes as $size => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="<?php echo esc_attr( $this->name( 'size' ) ); ?>" value="<?php echo esc_attr( $size ); ?>" <?php checked( $design['size'], $size ); ?> data-iftp-design="size" />
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</span>
				</fieldset>
			</div>

			<div class="iftp-btn-design__grid">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-border-width"><?php esc_html_e( 'Border', 'ifthenpay-pay-button' ); ?> <output class="iftp-btn-range__value" for="iftp-btn-border-width" data-iftp-border-out></output></label>
					<span class="iftp-btn-range">
						<input type="range" class="iftp-btn-range__input" id="iftp-btn-border-width" name="<?php echo esc_attr( $this->name( 'border_width' ) ); ?>" min="0" max="<?php echo esc_attr( (string) ButtonDesign::BORDER_MAX ); ?>" step="1" value="<?php echo esc_attr( (string) $design['border_width'] ); ?>" data-iftp-design="border_width" />
					</span>
				</div>

				<fieldset class="iftp-btn-field">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Border line', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg">
						<?php foreach ( $borders as $border => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="<?php echo esc_attr( $this->name( 'border_style' ) ); ?>" value="<?php echo esc_attr( $border ); ?>" <?php checked( $design['border_style'], $border ); ?> data-iftp-design="border_style" />
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</span>
					<p class="iftp-btn-field__help"><?php esc_html_e( 'Double shows from 3 px.', 'ifthenpay-pay-button' ); ?></p>
				</fieldset>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-border-color"><?php esc_html_e( 'Border colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color<?php echo $auto_bc ? ' is-auto' : ''; ?>" data-iftp-border-color>
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( $auto_bc ? $design['color'] : $design['border_color'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the border colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-border-color" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-border-color" name="<?php echo esc_attr( $this->name( 'border_color' ) ); ?>" value="<?php echo esc_attr( $auto_bc ? $design['color'] : $design['border_color'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="border_color" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="<?php echo esc_attr( $this->name( 'border_color_auto' ) ); ?>" value="1" <?php checked( $auto_bc ); ?> data-iftp-design="border_color_auto" />
						<?php esc_html_e( 'Use the style\'s own', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>
			</div>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="<?php echo esc_attr( $this->name( 'shadow' ) ); ?>" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="<?php echo esc_attr( $this->name( 'shadow' ) ); ?>" value="1" <?php checked( $design['shadow'] ); ?> data-iftp-design="shadow" />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Soft shadow', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'A glow in the button\'s colour that lifts a little on hover.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>
			<?php self::close_fold(); ?>

			<?php self::open_fold( 'design-effects', __( 'Effects', 'ifthenpay-pay-button' ), __( 'What it does on hover, and a gentle move that catches the eye.', 'ifthenpay-pay-button' ) ); ?>
			<?php
			$this->render_fx_picker( 'effect', __( 'On hover', 'ifthenpay-pay-button' ), $effects, $design, __( 'What it does when the pointer is on it. Point at a sample to see it. Nudge moves the icon, so pick one below.', 'ifthenpay-pay-button' ) );
			$this->render_fx_picker( 'attention', __( 'Catch the eye', 'ifthenpay-pay-button' ), $attentions, $design, __( 'Keeps going on its own to draw attention, and rests while the button is hovered. Payers who turn motion off get a still button.', 'ifthenpay-pay-button' ) );
			?>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="<?php echo esc_attr( $this->name( 'upper' ) ); ?>" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="<?php echo esc_attr( $this->name( 'upper' ) ); ?>" value="1" <?php checked( $design['upper'] ); ?> data-iftp-design="upper" />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Capital letters', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'PAY NOW, a little spaced out.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>
			<?php self::close_fold(); ?>
		</div>

		<div class="iftp-btn-design iftp-btn-design__shared" data-iftp-when-source="theme custom"<?php echo $own ? '' : ' hidden'; ?>>
			<?php self::open_fold( 'design-extras', __( 'Icon, width and logos', 'ifthenpay-pay-button' ), __( 'These work with your theme\'s look too.', 'ifthenpay-pay-button' ) ); ?>
			<div class="iftp-btn-design__grid">
				<fieldset class="iftp-btn-field iftp-btn-design__wide">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Icon', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg iftp-btn-seg--icons">
						<?php foreach ( $icons as $icon => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="<?php echo esc_attr( $this->name( 'icon' ) ); ?>" value="<?php echo esc_attr( $icon ); ?>" <?php checked( $design['icon'], $icon ); ?> data-iftp-design="icon" />
								<span>
									<span class="iftp-btn-seg__icon" data-iftp-icon-svg="<?php echo esc_attr( $icon ); ?>"><?php echo ButtonDesign::icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
									<?php echo esc_html( $label ); ?>
								</span>
							</label>
						<?php endforeach; ?>
					</span>
				</fieldset>

				<fieldset class="iftp-btn-field iftp-btn-design__wide">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Width', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg">
						<?php foreach ( $widths as $width => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="<?php echo esc_attr( $this->name( 'full' ) ); ?>" value="<?php echo esc_attr( (string) $width ); ?>" <?php checked( $design['full'] ? '1' : '0', (string) $width ); ?> data-iftp-design="full" />
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</span>
				</fieldset>
			</div>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="<?php echo esc_attr( $this->name( 'logos' ) ); ?>" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="<?php echo esc_attr( $this->name( 'logos' ) ); ?>" value="1" <?php checked( $design['logos'] ); ?> data-iftp-design="logos" />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Show the payment logos under the button', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Payers see how they can pay before they click. Uses the methods this button offers.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>
			<?php self::close_fold(); ?>
		</div>
		<?php
	}

	/**
	 * Opens a group that folds away, so only the part being worked on takes room. buttons.js remembers which are open.
	 *
	 * @param string $id          Its key, for remembering.
	 * @param string $title       The heading.
	 * @param string $description The line under it.
	 * @param bool   $open        Whether it starts open.
	 */
	public static function open_fold( string $id, string $title, string $description, bool $open = false ): void {
		?>
		<details class="iftp-btn-fold" data-iftp-fold="<?php echo esc_attr( $id ); ?>"<?php echo $open ? ' open' : ''; ?>>
			<summary class="iftp-btn-fold__summary">
				<span class="iftp-btn-fold__title"><?php echo esc_html( $title ); ?></span>
				<span class="iftp-btn-fold__hint"><?php echo esc_html( $description ); ?></span>
			</summary>
			<div class="iftp-btn-fold__body">
		<?php
	}

	/**
	 * Closes a group.
	 */
	public static function close_fold(): void {
		?>
			</div>
		</details>
		<?php
	}

	/**
	 * The button under a long list of tiles that shows the rest. buttons.js flips it.
	 *
	 * @param int $total How many tiles there are in all.
	 */
	private function render_more_toggle( int $total ): void {
		?>
		<button type="button" class="iftp-btn-more" data-iftp-more aria-expanded="false">
			<span data-iftp-more-closed>
				<?php
				/* translators: %s: number of tiles */
				echo esc_html( sprintf( __( 'Show all %s', 'ifthenpay-pay-button' ), number_format_i18n( $total ) ) );
				?>
			</span>
			<span data-iftp-more-open hidden><?php esc_html_e( 'Show fewer', 'ifthenpay-pay-button' ); ?></span>
		</button>
		<?php
	}

	/**
	 * A row of tiles, each a radio with a small live sample of the button wearing that choice. buttons.js
	 * redraws the samples in the current colours.
	 *
	 * @param string                $key     The design key the radios set.
	 * @param string                $legend  The field's name.
	 * @param array<string, string> $options Value => label.
	 * @param array<string, mixed>  $design  The current design.
	 * @param string                $help    A line under the tiles.
	 * @param int                   $featured How many tiles show before "Show all"; 0 shows every one.
	 */
	private function render_fx_picker( string $key, string $legend, array $options, array $design, string $help = '', int $featured = 0 ): void {
		$help_id = 'iftp-btn-fx-' . $key . '-help';
		$shown   = array_slice( array_keys( $options ), 0, $featured );
		// Only the first few show at first, unless the one in use is further down.
		$folded = $featured > 0 && count( $options ) > $featured && ! in_array( $design[ $key ] ?? '', array_slice( array_keys( $options ), $featured ), true );
		?>
		<fieldset class="iftp-btn-field iftp-btn-design__wide"<?php echo '' !== $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>>
			<legend class="iftp-btn-field__label"><?php echo esc_html( $legend ); ?></legend>
			<span class="iftp-btn-fxpick<?php echo 'effect' === $key ? ' iftp-btn-fxpick--hover' : ''; ?><?php echo $folded ? ' is-folded' : ''; ?>" data-iftp-more-list>
				<?php foreach ( $options as $value => $label ) : ?>
					<?php
					// A calm sample: only the choice this row is about moves.
					$sample = array_merge(
						$design,
						array(
							'source'    => ButtonDesign::SOURCE_CUSTOM,
							'size'      => 'sm',
							'full'      => false,
							'effect'    => 'none',
							'attention' => 'none',
							$key        => $value,
						)
					);

					if ( 'nudge' === $value && 'none' === $sample['icon'] ) {
						$sample['icon'] = 'arrow';
					}
					?>
					<label class="iftp-btn-fxpick__item<?php echo $featured > 0 && ! in_array( $value, $shown, true ) ? ' iftp-btn-fxpick__item--more' : ''; ?>">
						<input type="radio" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $design[ $key ], $value ); ?> data-iftp-design="<?php echo esc_attr( $key ); ?>" />
						<span class="iftp-btn-fxpick__stage" aria-hidden="true" data-iftp-fx-sample="<?php echo esc_attr( $key ); ?>" data-iftp-fx-value="<?php echo esc_attr( $value ); ?>">
							<?php echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( $sample, __( 'Pay', 'ifthenpay-pay-button' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes. ?>
						</span>
						<span class="iftp-btn-fxpick__name"><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</span>
			<?php if ( $featured > 0 && count( $options ) > $featured ) : ?>
				<?php $this->render_more_toggle( count( $options ) ); ?>
			<?php endif; ?>
			<?php if ( '' !== $help ) : ?>
				<p class="iftp-btn-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * The preview's head and the button on a light or dark stage, with the payment logos under it.
	 *
	 * @param array<string, mixed> $design The design it wears.
	 * @param string               $label  The button's text.
	 * @param string               $logos  The logos' <img> tags, already escaped; buttons.js fills them in the editor.
	 * @phpstan-param ButtonDesignConfig $design
	 */
	public static function render_stage( array $design, string $label, string $logos = '' ): void {
		?>
		<div class="iftp-btn-preview__head">
			<h2 class="iftp-btn-preview__title" id="iftp-btn-preview-title"><?php esc_html_e( 'Preview', 'ifthenpay-pay-button' ); ?></h2>
			<span class="iftp-btn-stagebg" role="group" aria-label="<?php esc_attr_e( 'Preview background', 'ifthenpay-pay-button' ); ?>">
				<button type="button" class="iftp-btn-stagebg__option" data-iftp-stage-bg="light" aria-pressed="true"><span class="screen-reader-text"><?php esc_html_e( 'Light background', 'ifthenpay-pay-button' ); ?></span></button>
				<button type="button" class="iftp-btn-stagebg__option iftp-btn-stagebg__option--dark" data-iftp-stage-bg="dark" aria-pressed="false"><span class="screen-reader-text"><?php esc_html_e( 'Dark background', 'ifthenpay-pay-button' ); ?></span></button>
			</span>
		</div>
		<div class="iftp-btn-preview__stage" data-iftp-stage data-bg="light">
			<span class="iftp-btn-pay__group">
			<span class="iftp-btn-pay iftp-btn-preview__pay" data-iftp-preview-button>
				<?php echo ButtonDesign::preview_markup( $design, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes. ?>
			</span>
			<span class="iftp-btn-pay__logos" data-iftp-preview-logos<?php echo $design['logos'] && '' !== $logos ? '' : ' hidden'; ?>><span class="iftp-btn-pay__logos-label"><?php echo esc_html( CheckoutTexts::get( 'pay_with' ) ); ?></span><span class="iftp-btn-pay__logos-list" data-iftp-preview-logo-list><?php echo $logos; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?></span></span>
			</span>
		</div>
		<p class="iftp-btn-preview__note" data-iftp-theme-note<?php echo ButtonDesign::SOURCE_CUSTOM === $design['source'] ? ' hidden' : ''; ?>><?php esc_html_e( 'A close guess: on your pages it takes your theme\'s exact button style.', 'ifthenpay-pay-button' ); ?></p>
		<?php
	}
}
