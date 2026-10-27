<?php
/**
 * The look controls and their live preview, for the site's look and a button's own.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\PayPage;
use Ifthenpay\PayButton\Frontend\StepBar;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default Design › Checkout and a button's Checkout design tab draw the same cards and preview; only where the fields
 * post to differs (`look[…]` or `config[look][…]`). buttons.js reads them by their data-iftp-look keys.
 *
 * @phpstan-import-type Look from CheckoutLook
 * @phpstan-import-type Confirmation from CheckoutSettings
 */
class LookControls {

	/**
	 * What every field's name starts with.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Sets up the controls.
	 *
	 * @param string $prefix The field name prefix, e.g. `look` or `config[look]`.
	 */
	public function __construct( string $prefix ) {
		$this->prefix = $prefix;
	}

	/**
	 * Each start-from look's swatch, for the screen's stylesheet: one rule per look, keyed on its button. The
	 * swatch is a tiny pay page, so it takes the page variables CheckoutLook prints, with the pattern scaled down.
	 */
	public static function swatch_css(): string {
		$css = '';

		foreach ( self::swatch_looks() as $key => $look ) {
			$vars = CheckoutLook::variables( $look );
			$rule = '';

			foreach ( array( '--iftp-page-bg', '--iftp-page-glow', '--iftp-page-glow2', '--iftp-page-orb', '--iftp-page-orb2', '--iftp-page-crystal', '--iftp-page-pattern', '--iftp-page-pattern-tile', '--iftp-page-fx-opacity', '--iftp-card-bg', '--iftp-card-filter' ) as $name ) {
				$rule .= $name . ':' . $vars[ $name ] . ';';
			}

			$css .= sprintf(
				'.iftp-btn-looksets__item[data-iftp-look-preset="%1$s"] .iftp-btn-looksets__swatch{%2$s--i:%3$s;--a:%4$s;--r:%5$dpx}',
				sanitize_key( $key ),
				$rule,
				$look['ink'],
				$look['accent'],
				min( 10, $look['radius'] )
			);
		}

		return $css;
	}

	/**
	 * The start-from looks as whole looks, ready to draw as swatches.
	 *
	 * @return array<string, Look>
	 */
	private static function swatch_looks(): array {
		$looks = array();

		foreach ( CheckoutLook::presets() as $key => $preset ) {
			$look = CheckoutLook::sanitize( $preset );

			// A swatch is a few centimetres wide, so the pattern shrinks with it.
			$look['pay_fx_size'] = max( 16, (int) round( $look['pay_fx_size'] / 4 ) );
			$looks[ $key ]       = $look;
		}

		return $looks;
	}

	/**
	 * A field's name.
	 *
	 * @param string $key The look key.
	 */
	private function name( string $key ): string {
		return $this->prefix . '[' . $key . ']';
	}

	/**
	 * Every look control: start-from looks, colours, shape and type, pay page, popups and footer, and the
	 * merchant's CSS when it's the site's look.
	 *
	 * @param array<string, mixed> $look     The look to show.
	 * @param bool                 $with_css Whether to offer the Custom CSS box.
	 * @phpstan-param Look $look
	 */
	public function render_cards( array $look, bool $with_css ): void {
		$on     = ButtonDesign::on_color( $look['accent'] );
		$colors = array(
			'surface' => array( __( 'Background', 'ifthenpay-pay-button' ), __( 'The sheet, the pay page card and the popups.', 'ifthenpay-pay-button' ) ),
			'ink'     => array( __( 'Text', 'ifthenpay-pay-button' ), __( 'Lighter shades and lines are mixed from it.', 'ifthenpay-pay-button' ) ),
		);
		$status = array(
			'paid'    => __( 'Paid', 'ifthenpay-pay-button' ),
			'pending' => __( 'Pending', 'ifthenpay-pay-button' ),
			'failed'  => __( 'Failed and cancelled', 'ifthenpay-pay-button' ),
		);
		?>
				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-start">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-start"><?php esc_html_e( 'Start from a look', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'For the checkout, the pay page and the popups after paying: colours, shape and the background behind the card. Pick one, then tweak anything below.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<div class="iftp-btn-looksets" role="group" aria-labelledby="iftp-btn-look-start">
							<?php foreach ( CheckoutLook::presets() as $key => $preset ) : ?>
								<button type="button" class="iftp-btn-looksets__item" data-iftp-look-preset="<?php echo esc_attr( $key ); ?>">
									<span class="iftp-btn-looksets__swatch" aria-hidden="true">
										<span class="iftp-btn-looksets__bg <?php echo esc_attr( CheckoutLook::page_classes( CheckoutLook::sanitize( $preset ) ) ); ?>"></span>
										<span class="iftp-btn-looksets__card">
											<span class="iftp-btn-looksets__line"></span>
											<span class="iftp-btn-looksets__line iftp-btn-looksets__line--short"></span>
											<span class="iftp-btn-looksets__cta"></span>
										</span>
									</span>
									<span class="iftp-btn-looksets__name"><?php echo esc_html( (string) $preset['label'] ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-colors">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-colors"><?php esc_html_e( 'Colours', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'A dark background with light text works too; every shade follows.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<div class="iftp-btn-design__grid">
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-accent"><?php esc_html_e( 'Accent', 'ifthenpay-pay-button' ); ?></label>
								<?php $this->print_color( 'accent', $look['accent'] ); ?>
								<p class="iftp-btn-field__help"><?php esc_html_e( 'The pay button, the step bar, selected choices and focus.', 'ifthenpay-pay-button' ); ?></p>
							</div>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-accent_text"><?php esc_html_e( 'Text on the accent', 'ifthenpay-pay-button' ); ?></label>
								<span class="iftp-btn-color<?php echo '' === $look['accent_text'] ? ' is-auto' : ''; ?>" data-iftp-auto-color>
									<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( '' !== $look['accent_text'] ? $look['accent_text'] : $on ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-look-accent_text" />
									<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-look-accent_text" name="<?php echo esc_attr( $this->name( 'accent_text' ) ); ?>" value="<?php echo esc_attr( '' !== $look['accent_text'] ? $look['accent_text'] : $on ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-look="accent_text" />
								</span>
								<label class="iftp-btn-check">
									<input type="checkbox" name="<?php echo esc_attr( $this->name( 'accent_text_auto' ) ); ?>" value="1" <?php checked( '' === $look['accent_text'] ); ?> data-iftp-look="accent_text_auto" />
									<?php esc_html_e( 'Pick it for me (best contrast)', 'ifthenpay-pay-button' ); ?>
								</label>
							</div>
							<?php foreach ( $colors as $key => $labels ) : ?>
								<div class="iftp-btn-field">
									<label class="iftp-btn-field__label" for="iftp-btn-look-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $labels[0] ); ?></label>
									<?php $this->print_color( $key, $look[ $key ] ); ?>
									<p class="iftp-btn-field__help"><?php echo esc_html( $labels[1] ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
						<div class="iftp-btn-field">
							<span class="iftp-btn-field__label"><?php esc_html_e( 'Outcome colours', 'ifthenpay-pay-button' ); ?></span>
							<div class="iftp-btn-lookpage__status">
								<?php foreach ( $status as $key => $label ) : ?>
									<label class="iftp-btn-lookpage__status-item">
										<input type="color" class="iftp-btn-color__swatch" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $look[ $key ] ); ?>" data-iftp-look="<?php echo esc_attr( $key ); ?>" />
										<span><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="iftp-btn-design__grid">
							<?php
							$this->print_range( 'card_opacity', __( 'Checkout card opacity', 'ifthenpay-pay-button' ), $look['card_opacity'], 100, '%' );
							$this->print_range( 'card_blur', __( 'Glass blur', 'ifthenpay-pay-button' ), $look['card_blur'], 40 );
							?>
						</div>
						<p class="iftp-btn-field__help"><?php esc_html_e( 'Under 100%, the card turns to frosted glass and the background shows through. Lovely over an image, a video, Aurora or Bokeh.', 'ifthenpay-pay-button' ); ?></p>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-dark" data-iftp-look-section="dark">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-dark"><?php esc_html_e( 'Dark mode', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'Payers whose phone or computer is set to dark get these colours instead. Try both with the sun and moon above the preview.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<?php
						$this->print_choice(
							'dark',
							__( 'Dark mode', 'ifthenpay-pay-button' ),
							$look['dark'],
							array(
								'off'  => __( 'Off', 'ifthenpay-pay-button' ),
								'auto' => __( 'Follow the payer\'s device', 'ifthenpay-pay-button' ),
							)
						);
						?>
						<div class="iftp-btn-design__grid" data-iftp-when-dark="auto">
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-dark_surface"><?php esc_html_e( 'Dark background', 'ifthenpay-pay-button' ); ?></label>
								<?php $this->print_color( 'dark_surface', $look['dark_surface'] ); ?>
							</div>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-dark_ink"><?php esc_html_e( 'Dark text', 'ifthenpay-pay-button' ); ?></label>
								<?php $this->print_color( 'dark_ink', $look['dark_ink'] ); ?>
							</div>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-dark_accent"><?php esc_html_e( 'Dark accent', 'ifthenpay-pay-button' ); ?></label>
								<span class="iftp-btn-color<?php echo '' === $look['dark_accent'] ? ' is-auto' : ''; ?>" data-iftp-dark-accent>
									<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( '' !== $look['dark_accent'] ? $look['dark_accent'] : $look['accent'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-look-dark_accent" />
									<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-look-dark_accent" name="<?php echo esc_attr( $this->name( 'dark_accent' ) ); ?>" value="<?php echo esc_attr( '' !== $look['dark_accent'] ? $look['dark_accent'] : $look['accent'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-look="dark_accent" />
								</span>
								<label class="iftp-btn-check">
									<input type="checkbox" name="<?php echo esc_attr( $this->name( 'dark_accent_auto' ) ); ?>" value="1" <?php checked( '' === $look['dark_accent'] ); ?> data-iftp-look="dark_accent_auto" />
									<?php esc_html_e( 'Same as the light one', 'ifthenpay-pay-button' ); ?>
								</label>
							</div>
						</div>
						<p class="iftp-btn-field__help" data-iftp-when-dark="auto"><?php esc_html_e( 'The lighter shades, the lines and the pay page\'s background are mixed from these, as in the light look.', 'ifthenpay-pay-button' ); ?></p>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-shape">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-shape"><?php esc_html_e( 'Shape and type', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'Long forms scroll inside the checkout, so it never grows past the screen.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<div class="iftp-btn-design__grid">
							<?php $this->print_range( 'radius', __( 'Checkout corners', 'ifthenpay-pay-button' ), $look['radius'], CheckoutLook::MAX_RADIUS ); ?>
							<?php $this->print_range( 'field_radius', __( 'Field corners', 'ifthenpay-pay-button' ), $look['field_radius'], CheckoutLook::MAX_FIELD_RADIUS ); ?>
							<?php
							$this->print_choice(
								'fields',
								__( 'Fields', 'ifthenpay-pay-button' ),
								$look['fields'],
								array(
									'outline'   => __( 'Outlined', 'ifthenpay-pay-button' ),
									'soft'      => __( 'Filled', 'ifthenpay-pay-button' ),
									'underline' => __( 'Underlined', 'ifthenpay-pay-button' ),
								)
							);
							$this->print_choice(
								'width',
								__( 'Width', 'ifthenpay-pay-button' ),
								$look['width'],
								array(
									'compact' => __( 'Compact', 'ifthenpay-pay-button' ),
									'regular' => __( 'Regular', 'ifthenpay-pay-button' ),
									'wide'    => __( 'Wide', 'ifthenpay-pay-button' ),
								)
							);
							$this->print_choice(
								'font',
								__( 'Font', 'ifthenpay-pay-button' ),
								$look['font'],
								array(
									'system' => __( 'Clean system font', 'ifthenpay-pay-button' ),
									'theme'  => __( 'My theme\'s font', 'ifthenpay-pay-button' ),
								)
							);
							?>
							<div data-iftp-when-scene="">
								<?php
								$this->print_choice(
									'backdrop',
									__( 'Behind the checkout', 'ifthenpay-pay-button' ),
									$look['backdrop'],
									array(
										'blur'  => __( 'Blur', 'ifthenpay-pay-button' ),
										'dim'   => __( 'Dim', 'ifthenpay-pay-button' ),
										'light' => __( 'Frosted', 'ifthenpay-pay-button' ),
									)
								);
								?>
							</div>
						</div>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-steps">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-steps"><?php esc_html_e( 'Page progress', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'When a form has pages: how payers see how many there are, and how much of this one they\'ve answered.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<?php $this->print_step_styles( $look['steps'] ); ?>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-page" data-iftp-look-section="page">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-page"><?php esc_html_e( 'Pay page', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'The page every pay link opens: what sits behind the checkout card, and around it.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<?php
						$this->print_toggle( 'scene', __( 'Open the checkout on this background', 'ifthenpay-pay-button' ), __( 'A click on the pay button opens the checkout, and the popups after paying, over this background instead of the dimmed page.', 'ifthenpay-pay-button' ), $look['scene'] );
						$this->print_choice(
							'pay_bg',
							__( 'Background', 'ifthenpay-pay-button' ),
							$look['pay_bg'],
							array(
								'glow'     => __( 'Soft glow', 'ifthenpay-pay-button' ),
								'gradient' => __( 'Gradient', 'ifthenpay-pay-button' ),
								'plain'    => __( 'Plain', 'ifthenpay-pay-button' ),
								'image'    => __( 'Image', 'ifthenpay-pay-button' ),
								'video'    => __( 'Video', 'ifthenpay-pay-button' ),
							)
						);
						?>
						<div class="iftp-btn-design__grid">
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-pay_color"><?php esc_html_e( 'Background colour', 'ifthenpay-pay-button' ); ?></label>
								<span class="iftp-btn-color<?php echo '' === $look['pay_color'] ? ' is-auto' : ''; ?>" data-iftp-page-color>
									<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( CheckoutLook::page_color( $look ) ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-look-pay_color" />
									<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-look-pay_color" name="<?php echo esc_attr( $this->name( 'pay_color' ) ); ?>" value="<?php echo esc_attr( CheckoutLook::page_color( $look ) ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-look="pay_color" />
								</span>
								<label class="iftp-btn-check">
									<input type="checkbox" name="<?php echo esc_attr( $this->name( 'pay_color_auto' ) ); ?>" value="1" <?php checked( '' === $look['pay_color'] ); ?> data-iftp-look="pay_color_auto" />
									<?php esc_html_e( 'Match the checkout', 'ifthenpay-pay-button' ); ?>
								</label>
							</div>
						</div>
						<div class="iftp-btn-design__grid" data-iftp-when-bg="video">
							<?php
							$this->print_choice(
								'pay_video_src',
								__( 'Video from', 'ifthenpay-pay-button' ),
								$look['pay_video_src'],
								array(
									'file' => __( 'Media Library', 'ifthenpay-pay-button' ),
									'link' => __( 'YouTube or Vimeo', 'ifthenpay-pay-button' ),
								),
								true
							);
							?>
							<div class="iftp-btn-field iftp-btn-design__wide" data-iftp-when-vsrc="file">
								<span class="iftp-btn-field__label"><?php esc_html_e( 'Video file', 'ifthenpay-pay-button' ); ?></span>
								<?php $video = $look['pay_video'] > 0 ? wp_get_attachment_url( $look['pay_video'] ) : false; ?>
								<div class="iftp-btn-imagepick">
									<span class="iftp-btn-videopick__name" data-iftp-video-name data-empty="<?php esc_attr_e( 'No video yet', 'ifthenpay-pay-button' ); ?>"><?php echo is_string( $video ) ? esc_html( wp_basename( $video ) ) : esc_html__( 'No video yet', 'ifthenpay-pay-button' ); ?></span>
									<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-video-pick><?php esc_html_e( 'Choose video', 'ifthenpay-pay-button' ); ?></button>
									<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-video-clear<?php echo is_string( $video ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'ifthenpay-pay-button' ); ?></button>
									<input type="hidden" name="<?php echo esc_attr( $this->name( 'pay_video' ) ); ?>" value="<?php echo esc_attr( (string) $look['pay_video'] ); ?>" data-iftp-look="pay_video" data-url="<?php echo esc_attr( is_string( $video ) ? $video : '' ); ?>" />
								</div>
								<p class="iftp-btn-field__help"><?php esc_html_e( 'An MP4 or WebM. Keep it short and light, a few MB: it loops, muted, behind the card.', 'ifthenpay-pay-button' ); ?></p>
							</div>
							<div class="iftp-btn-field iftp-btn-design__wide" data-iftp-when-vsrc="link">
								<label class="iftp-btn-field__label" for="iftp-btn-look-pay_video_url"><?php esc_html_e( 'Video link', 'ifthenpay-pay-button' ); ?></label>
								<input type="url" class="iftp-btn-field__control" id="iftp-btn-look-pay_video_url" name="<?php echo esc_attr( $this->name( 'pay_video_url' ) ); ?>" value="<?php echo esc_attr( $look['pay_video_url'] ); ?>" placeholder="https://www.youtube.com/watch?v=…" data-iftp-look="pay_video_url" />
								<p class="iftp-btn-field__help"><?php esc_html_e( 'Plays muted, with no controls. It loads from YouTube or Vimeo, under their terms, and they see the payer\'s visit.', 'ifthenpay-pay-button' ); ?></p>
							</div>
							<?php
							$this->print_choice(
								'pay_video_loop',
								__( 'Loop', 'ifthenpay-pay-button' ),
								$look['pay_video_loop'],
								array(
									'all'  => __( 'The whole video', 'ifthenpay-pay-button' ),
									'part' => __( 'Part of it', 'ifthenpay-pay-button' ),
								),
								true
							);
							?>
							<div class="iftp-btn-field" data-iftp-when-loop="part">
								<label class="iftp-btn-field__label" for="iftp-btn-look-pay_video_start"><?php esc_html_e( 'From', 'ifthenpay-pay-button' ); ?></label>
								<input type="text" class="iftp-btn-field__control" id="iftp-btn-look-pay_video_start" name="<?php echo esc_attr( $this->name( 'pay_video_start' ) ); ?>" value="<?php echo esc_attr( CheckoutLook::clock( $look['pay_video_start'] ) ); ?>" placeholder="0:00" pattern="\s*(\d+:)?(\d+:)?\d+\s*" spellcheck="false" data-iftp-look="pay_video_start" />
							</div>
							<div class="iftp-btn-field" data-iftp-when-loop="part">
								<label class="iftp-btn-field__label" for="iftp-btn-look-pay_video_end"><?php esc_html_e( 'To', 'ifthenpay-pay-button' ); ?></label>
								<input type="text" class="iftp-btn-field__control" id="iftp-btn-look-pay_video_end" name="<?php echo esc_attr( $this->name( 'pay_video_end' ) ); ?>" value="<?php echo esc_attr( $look['pay_video_end'] > 0 ? CheckoutLook::clock( $look['pay_video_end'] ) : '' ); ?>" placeholder="<?php esc_attr_e( 'The end', 'ifthenpay-pay-button' ); ?>" pattern="\s*(\d+:)?(\d+:)?\d+\s*" spellcheck="false" data-iftp-look="pay_video_end" />
							</div>
							<p class="iftp-btn-field__help iftp-btn-design__wide" data-iftp-when-loop="part"><?php esc_html_e( 'Minutes and seconds, like 0:12 to 0:45. Leave To empty to play on to the end, then start again at From.', 'ifthenpay-pay-button' ); ?></p>
							<div class="iftp-btn-design__wide">
								<?php $this->print_range( 'pay_video_blur', __( 'Blur the video', 'ifthenpay-pay-button' ), $look['pay_video_blur'], 30 ); ?>
							</div>
						</div>
						<div class="iftp-btn-field" data-iftp-when-bg="image video">
							<span class="iftp-btn-field__label"><?php esc_html_e( 'Background image', 'ifthenpay-pay-button' ); ?></span>
							<?php $image = $look['pay_image'] > 0 ? wp_get_attachment_image_url( $look['pay_image'], 'medium' ) : false; ?>
							<div class="iftp-btn-imagepick">
								<span class="iftp-btn-imagepick__thumb" data-iftp-image-thumb<?php echo is_string( $image ) ? ' style="background-image: url(' . esc_url( $image ) . ')"' : ''; ?>></span>
								<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-image-pick><?php esc_html_e( 'Choose image', 'ifthenpay-pay-button' ); ?></button>
								<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-image-clear<?php echo is_string( $image ) ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'ifthenpay-pay-button' ); ?></button>
								<input type="hidden" name="<?php echo esc_attr( $this->name( 'pay_image' ) ); ?>" value="<?php echo esc_attr( (string) $look['pay_image'] ); ?>" data-iftp-look="pay_image" data-url="<?php echo esc_attr( (string) wp_get_attachment_image_url( $look['pay_image'], 'full' ) ); ?>" />
							</div>
							<p class="iftp-btn-field__help" data-iftp-when-bg="video"><?php esc_html_e( 'Optional. Shows while the video loads, and to payers who turned motion off on their device.', 'ifthenpay-pay-button' ); ?></p>
						</div>
						<div data-iftp-when-bg="image video">
							<?php $this->print_range( 'pay_dim', __( 'Darken it', 'ifthenpay-pay-button' ), $look['pay_dim'], 80, '%' ); ?>
						</div>

						<div class="iftp-btn-design__grid" data-iftp-when-bg="image">
							<?php
							$this->print_choice(
								'pay_blur_type',
								__( 'Blur the image', 'ifthenpay-pay-button' ),
								$look['pay_blur_type'],
								array(
									'none'     => __( 'None', 'ifthenpay-pay-button' ),
									'soft'     => __( 'Soft', 'ifthenpay-pay-button' ),
									'frosted'  => __( 'Frosted', 'ifthenpay-pay-button' ),
									'tilt'     => __( 'Tilt-shift', 'ifthenpay-pay-button' ),
									'vignette' => __( 'Vignette', 'ifthenpay-pay-button' ),
								),
								true
							);
							?>
							<div class="iftp-btn-design__wide" data-iftp-when-blur="soft frosted tilt vignette">
								<?php $this->print_range( 'pay_blur', __( 'Blur amount', 'ifthenpay-pay-button' ), $look['pay_blur'], 40 ); ?>
								<p class="iftp-btn-field__help"><?php esc_html_e( 'Frosted also lifts the colours. Tilt-shift keeps the middle sharp; Vignette keeps the centre sharp.', 'ifthenpay-pay-button' ); ?></p>
							</div>
						</div>

						<div class="iftp-btn-design__grid" data-iftp-when-bg="gradient">
							<?php
							$this->print_choice(
								'pay_grad',
								__( 'Gradient style', 'ifthenpay-pay-button' ),
								$look['pay_grad'],
								array(
									'soft'     => __( 'Soft', 'ifthenpay-pay-button' ),
									'aurora'   => __( 'Aurora', 'ifthenpay-pay-button' ),
									'diagonal' => __( 'Diagonal', 'ifthenpay-pay-button' ),
								)
							);
							?>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-look-pay_color2"><?php esc_html_e( 'Second colour', 'ifthenpay-pay-button' ); ?></label>
								<span class="iftp-btn-color<?php echo '' === $look['pay_color2'] ? ' is-auto' : ''; ?>" data-iftp-second-color>
									<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( '' !== $look['pay_color2'] ? $look['pay_color2'] : $look['accent'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-look-pay_color2" />
									<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-look-pay_color2" name="<?php echo esc_attr( $this->name( 'pay_color2' ) ); ?>" value="<?php echo esc_attr( '' !== $look['pay_color2'] ? $look['pay_color2'] : $look['accent'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-look="pay_color2" />
								</span>
								<label class="iftp-btn-check">
									<input type="checkbox" name="<?php echo esc_attr( $this->name( 'pay_color2_auto' ) ); ?>" value="1" <?php checked( '' === $look['pay_color2'] ); ?> data-iftp-look="pay_color2_auto" />
									<?php esc_html_e( 'Same as the accent', 'ifthenpay-pay-button' ); ?>
								</label>
							</div>
						</div>

						<div class="iftp-btn-design__grid" data-iftp-when-bg="glow gradient plain">
							<?php
							$this->print_choice(
								'pay_fx',
								__( 'Effect', 'ifthenpay-pay-button' ),
								$look['pay_fx'],
								array(
									'none'     => __( 'None', 'ifthenpay-pay-button' ),
									'crystals' => __( 'Crystals', 'ifthenpay-pay-button' ),
									'waves'    => __( 'Waves', 'ifthenpay-pay-button' ),
									'bokeh'    => __( 'Bokeh', 'ifthenpay-pay-button' ),
									'grain'    => __( 'Grain', 'ifthenpay-pay-button' ),
								),
								true
							);
							?>
							<div data-iftp-when-fx="crystals waves bokeh grain">
								<?php $this->print_range( 'pay_fx_size', __( 'Size', 'ifthenpay-pay-button' ), $look['pay_fx_size'], 400, 'px', 40 ); ?>
							</div>
							<div data-iftp-when-fx="crystals waves bokeh grain">
								<?php $this->print_range( 'pay_fx_strength', __( 'Strength', 'ifthenpay-pay-button' ), $look['pay_fx_strength'], 100, '%' ); ?>
							</div>
							<p class="iftp-btn-field__help iftp-btn-design__wide" data-iftp-when-fx="bokeh"><?php esc_html_e( 'The lights take the accent and the second colour from Gradient.', 'ifthenpay-pay-button' ); ?></p>
						</div>
						<?php
						$this->print_toggle( 'pay_logo', __( 'Your logo above the card', 'ifthenpay-pay-button' ), __( 'The theme\'s logo, else the site icon, else the site name. It links home.', 'ifthenpay-pay-button' ), $look['pay_logo'] );
						$this->print_toggle( 'pay_back', __( 'A link back to your site under the card', 'ifthenpay-pay-button' ), __( 'For payers who opened the link and want to look around.', 'ifthenpay-pay-button' ), $look['pay_back'] );
						?>
					</div>
				</section>

				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-popups">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-popups"><?php esc_html_e( 'Popups and footer', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'The paid, pending, failed and cancelled popups use the colours above. Their words are in Messages.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<div class="iftp-btn-design__grid">
							<?php
							$this->print_choice(
								'popup_icon',
								__( 'Popup icon', 'ifthenpay-pay-button' ),
								$look['popup_icon'],
								array(
									'soft'  => __( 'Soft', 'ifthenpay-pay-button' ),
									'solid' => __( 'Solid', 'ifthenpay-pay-button' ),
								)
							);
							$this->print_choice(
								'popup_align',
								__( 'Popup text', 'ifthenpay-pay-button' ),
								$look['popup_align'],
								array(
									'center' => __( 'Centred', 'ifthenpay-pay-button' ),
									'left'   => __( 'Left', 'ifthenpay-pay-button' ),
								)
							);
							?>
						</div>
						<?php
						$this->print_toggle( 'logos', __( 'Payment logos at the bottom of the checkout', 'ifthenpay-pay-button' ), __( 'The methods the button offers.', 'ifthenpay-pay-button' ), $look['logos'] );
						$this->print_toggle( 'secure', __( '"Secure payment by ifthenpay"', 'ifthenpay-pay-button' ), __( 'A small line that tells payers who handles the money.', 'ifthenpay-pay-button' ), $look['secure'] );
						?>
					</div>
				</section>

		<?php

		if ( $with_css ) {
			$this->render_css( $look );
		}
	}

	/**
	 * The Custom CSS box, site-wide only.
	 *
	 * @param array<string, mixed> $look The look.
	 * @phpstan-param Look $look
	 */
	private function render_css( array $look ): void {
		?>
				<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-look-css">
					<div class="iftp-btn-ecard__intro">
						<h2 class="iftp-btn-ecard__title" id="iftp-btn-look-css"><?php esc_html_e( 'Custom CSS', 'ifthenpay-pay-button' ); ?></h2>
						<p class="iftp-btn-ecard__description"><?php esc_html_e( 'Loaded after everything above, on every page with a button. The preview shows it once you save.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<div class="iftp-btn-ecard__body">
						<label class="screen-reader-text" for="iftp-btn-look-css-input"><?php esc_html_e( 'Custom CSS', 'ifthenpay-pay-button' ); ?></label>
						<textarea class="iftp-btn-field__control iftp-btn-code-input" id="iftp-btn-look-css-input" name="<?php echo esc_attr( $this->name( 'css' ) ); ?>" rows="8" spellcheck="false" placeholder=".iftp-btn-form__submit { letter-spacing: 0.04em; text-transform: uppercase; }"<?php echo current_user_can( 'unfiltered_html' ) ? '' : ' readonly'; ?>><?php echo esc_textarea( $look['css'] ); ?></textarea>
						<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
							<p class="iftp-btn-field__help"><?php esc_html_e( 'Only users who may post unfiltered HTML can change the custom CSS.', 'ifthenpay-pay-button' ); ?></p>
						<?php endif; ?>
						<details class="iftp-btn-details">
							<summary><?php esc_html_e( 'Classes you can style', 'ifthenpay-pay-button' ); ?></summary>
							<dl class="iftp-btn-classes">
								<?php
								foreach (
									array(
										'.iftp-btn-sheet'  => __( 'The checkout popup', 'ifthenpay-pay-button' ),
										'.iftp-btn-form'   => __( 'The checkout form, in the popup or on a pay page', 'ifthenpay-pay-button' ),
										'.iftp-btn-steps'  => __( 'The step bar', 'ifthenpay-pay-button' ),
										'.iftp-btn-form__input' => __( 'Every text box and dropdown', 'ifthenpay-pay-button' ),
										'.iftp-btn-form__choice' => __( 'Each option of a choice', 'ifthenpay-pay-button' ),
										'.iftp-btn-form__submit' => __( 'The pay and Next button', 'ifthenpay-pay-button' ),
										'.iftp-btn-result' => __( 'The outcome popups; add --paid, --pending, --failed or --cancelled', 'ifthenpay-pay-button' ),
										'.iftp-btn-page-body' => __( 'The pay page', 'ifthenpay-pay-button' ),
									) as $class => $what
								) :
									?>
									<dt><code><?php echo esc_html( $class ); ?></code></dt>
									<dd><?php echo esc_html( $what ); ?></dd>
								<?php endforeach; ?>
							</dl>
						</details>
					</div>
				</section>
		<?php
	}

	/**
	 * The live preview: a mock checkout and the four popups, drawn by the real checkout stylesheet.
	 *
	 * @param array<string, mixed> $look         The look to start the preview from.
	 * @param array<string, mixed> $confirmation The popup titles and messages.
	 * @phpstan-param Look $look
	 * @phpstan-param Confirmation $confirmation
	 */
	public function render_preview( array $look, array $confirmation ): void {
		$views = array(
			'checkout'  => __( 'Checkout', 'ifthenpay-pay-button' ),
			'page'      => __( 'Pay page', 'ifthenpay-pay-button' ),
			'paid'      => __( 'Paid', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Pending', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Failed', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'Cancelled', 'ifthenpay-pay-button' ),
		);
		$icons = array(
			'paid'      => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'pending'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
			'failed'    => '<path d="M7 7l10 10M17 7 7 17"/>',
			'cancelled' => '<path d="M7 7l10 10M17 7 7 17"/>',
		);
		$style = '';

		foreach ( CheckoutLook::variables( $look ) as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}
		?>
		<section class="iftp-btn-preview iftp-btn-look-preview" aria-labelledby="iftp-btn-look-preview-title">
			<div class="iftp-btn-preview__head">
				<h2 class="iftp-btn-preview__title" id="iftp-btn-look-preview-title"><?php esc_html_e( 'Preview', 'ifthenpay-pay-button' ); ?></h2>
				<span class="iftp-btn-stagebg" role="group" aria-label="<?php esc_attr_e( 'Preview as', 'ifthenpay-pay-button' ); ?>">
					<button type="button" class="iftp-btn-stagebg__option" data-iftp-look-scheme="light" aria-pressed="true" title="<?php esc_attr_e( 'Light mode', 'ifthenpay-pay-button' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Light mode', 'ifthenpay-pay-button' ); ?></span></button>
					<button type="button" class="iftp-btn-stagebg__option iftp-btn-stagebg__option--dark" data-iftp-look-scheme="dark" aria-pressed="false" title="<?php esc_attr_e( 'Dark mode', 'ifthenpay-pay-button' ); ?>"><span class="screen-reader-text"><?php esc_html_e( 'Dark mode', 'ifthenpay-pay-button' ); ?></span></button>
				</span>
			</div>
			<p class="iftp-btn-preview__note" data-iftp-look-dark-note hidden><?php esc_html_e( 'Dark mode is off, so payers on a dark device see the light look. Turn it on under Dark mode.', 'ifthenpay-pay-button' ); ?></p>
			<span class="iftp-btn-seg iftp-btn-look-preview__views" role="group" aria-label="<?php esc_attr_e( 'Show', 'ifthenpay-pay-button' ); ?>">
				<?php foreach ( $views as $view => $label ) : ?>
					<button type="button" class="iftp-btn-seg__button" data-iftp-look-view-pick="<?php echo esc_attr( $view ); ?>" aria-pressed="<?php echo 'checkout' === $view ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</span>
			<div class="iftp-btn-look-preview__stage" data-iftp-look-stage data-view="checkout" style="<?php echo esc_attr( $style ); ?>">
				<div class="iftp-btn-look-preview__pagebg <?php echo esc_attr( CheckoutLook::page_classes( $look ) ); ?>" data-iftp-look-pagebg aria-hidden="true"></div>
				<div class="iftp-btn-look iftp-btn-look-preview__frame<?php echo 'theme' === $look['font'] ? ' is-theme-font' : ''; ?>" style="<?php echo esc_attr( $style ); ?>" data-iftp-look-frame inert>
					<span class="iftp-btn-page__logo" data-iftp-look-chrome="pay_logo" hidden><?php echo PayPage::site_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in site_mark(). ?></span>
					<div class="iftp-btn-look-preview__sheet" data-iftp-look-view="checkout">
						<div class="iftp-btn-form iftp-btn-form--steps">
							<header class="iftp-btn-form__header">
								<span class="iftp-btn-form__site" data-iftp-text="header"><?php echo esc_html( CheckoutTexts::get( 'header' ) ); ?></span>
								<span class="iftp-btn-form__close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg></span>
							</header>
							<?php
							StepBar::render(
								array( CheckoutTexts::get( 'first_step' ), __( 'Your details', 'ifthenpay-pay-button' ), __( 'Confirm', 'ifthenpay-pay-button' ) ),
								$look['steps'],
								array(
									'texts' => true,
									'fill'  => 0.66,
								)
							);
							?>
							<div class="iftp-btn-form__body">
								<div class="iftp-btn-form__step">
									<div class="iftp-btn-form__summary">
										<p class="iftp-btn-form__title"><?php esc_html_e( 'Consultation', 'ifthenpay-pay-button' ); ?></p>
										<p class="iftp-btn-form__text"><?php esc_html_e( 'First visit, 60 minutes', 'ifthenpay-pay-button' ); ?></p>
										<p class="iftp-btn-form__amount">35,00 €</p>
									</div>
									<div class="iftp-btn-form__field iftp-btn-form__field--half">
										<span class="iftp-btn-form__label" data-iftp-text="name"><?php echo esc_html( CheckoutTexts::get( 'name' ) ); ?></span>
										<input class="iftp-btn-form__input" type="text" value="Ana Silva" readonly tabindex="-1" />
									</div>
									<div class="iftp-btn-form__field iftp-btn-form__field--half">
										<span class="iftp-btn-form__label" data-iftp-text="email"><?php echo esc_html( CheckoutTexts::get( 'email' ) ); ?></span>
										<input class="iftp-btn-form__input" type="text" value="" placeholder="you@example.com" readonly tabindex="-1" />
									</div>
									<div class="iftp-btn-form__field">
										<span class="iftp-btn-form__label"><?php esc_html_e( 'Session', 'ifthenpay-pay-button' ); ?></span>
										<div class="iftp-btn-form__choices">
											<label class="iftp-btn-form__choice"><input type="radio" class="iftp-btn-form__tick" checked tabindex="-1" /><span><?php esc_html_e( 'In person', 'ifthenpay-pay-button' ); ?></span></label>
											<label class="iftp-btn-form__choice"><input type="radio" class="iftp-btn-form__tick" tabindex="-1" /><span><?php esc_html_e( 'Online', 'ifthenpay-pay-button' ); ?></span></label>
										</div>
									</div>
									<label class="iftp-btn-form__agree"><input type="checkbox" class="iftp-btn-form__tick" checked tabindex="-1" /><span><?php esc_html_e( 'I agree to the terms', 'ifthenpay-pay-button' ); ?></span></label>
								</div>
							</div>
							<div class="iftp-btn-form__actions">
								<div class="iftp-btn-form__buttons">
									<span class="iftp-btn-form__submit" data-iftp-text="next"><?php echo esc_html( CheckoutTexts::get( 'next' ) ); ?></span>
								</div>
							</div>
							<footer class="iftp-btn-form__footer" data-iftp-look-footer<?php echo $look['logos'] || $look['secure'] ? '' : ' hidden'; ?>>
								<ul class="iftp-btn-form__methods" data-iftp-look-logos<?php echo $look['logos'] ? '' : ' hidden'; ?>>
									<?php $this->print_preview_logos(); ?>
								</ul>
								<span class="iftp-btn-form__secure" data-iftp-look-secure<?php echo $look['secure'] ? '' : ' hidden'; ?>><span data-iftp-text="secure"><?php echo esc_html( CheckoutTexts::get( 'secure' ) ); ?></span> <img src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="62" height="16" /></span>
							</footer>
						</div>
					</div>

					<p class="iftp-btn-page__back" data-iftp-look-chrome="pay_back" hidden><a href="#"><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></a></p>

					<?php foreach ( CheckoutSettings::OUTCOMES as $outcome ) : ?>
						<div class="iftp-btn-look-preview__sheet iftp-btn-result iftp-btn-result--<?php echo esc_attr( $outcome ); ?><?php echo 'solid' === $look['popup_icon'] ? ' iftp-btn-result--solid' : ''; ?>" data-iftp-look-view="<?php echo esc_attr( $outcome ); ?>" data-iftp-look-result hidden>
							<div class="iftp-btn-result__box">
								<span class="iftp-btn-result__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[ $outcome ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></svg></span>
								<p class="iftp-btn-result__title" data-iftp-look-title="<?php echo esc_attr( $outcome ); ?>"><?php echo esc_html( $confirmation['titles'][ $outcome ] ); ?></p>
								<div class="iftp-btn-result__message" data-iftp-look-message="<?php echo esc_attr( $outcome ); ?>"><?php echo wp_kses_post( $confirmation['messages'][ $outcome ] ); ?></div>
								<dl class="iftp-btn-result__details" data-iftp-look-details<?php echo $confirmation['show_details'] ? '' : ' hidden'; ?>>
									<dt data-iftp-text="amount"><?php echo esc_html( CheckoutTexts::get( 'amount' ) ); ?></dt><dd>35,00 €</dd>
									<dt data-iftp-text="reference"><?php echo esc_html( CheckoutTexts::get( 'reference' ) ); ?></dt><dd>BTN6F2A91C4</dd>
									<dt data-iftp-text="method"><?php echo esc_html( CheckoutTexts::get( 'method' ) ); ?></dt><dd>MB WAY</dd>
								</dl>
								<span class="iftp-btn-form__submit iftp-btn-result__close" data-iftp-text="close"><?php echo esc_html( CheckoutTexts::get( 'close' ) ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * The preview's method logos: the ones switched on in Gateway Configuration, as the checkout shows them.
	 */
	private function print_preview_logos(): void {
		$methods = array_filter(
			( new SettingsRepository() )->get_methods(),
			static function ( array $method ): bool {
				return $method['enabled'] && '' !== $method['account'] && '' !== (string) ( $method['logo'] ?? '' );
			}
		);

		// Not connected yet, so there are no logos to show: names stand in.
		if ( array() === $methods ) {
			foreach ( array( 'MB', 'MB WAY', 'VISA' ) as $name ) {
				echo '<li><span class="iftp-btn-look-preview__logo">' . esc_html( $name ) . '</span></li>';
			}

			return;
		}

		foreach ( $methods as $method ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dark_logo_attr() escapes.
			echo '<li><img src="' . esc_url( $method['logo'] ?? '' ) . '"' . ButtonRenderer::dark_logo_attr( $method ) . ' alt="' . esc_attr( '' !== $method['alias'] ? $method['alias'] : $method['entity'] ) . '" height="18" loading="lazy" /></li>';
		}
	}

	/**
	 * A swatch and hex field for one look colour.
	 *
	 * @param string $key   The look key.
	 * @param string $value The colour.
	 */
	private function print_color( string $key, string $value ): void {
		$id = 'iftp-btn-look-' . $key;
		?>
		<span class="iftp-btn-color">
			<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( $value ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="<?php echo esc_attr( $id ); ?>" />
			<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-look="<?php echo esc_attr( $key ); ?>" />
		</span>
		<?php
	}

	/**
	 * A slider: corners get a square and a pill at their ends; everything else, a small and a big dot.
	 *
	 * @param string $key   The look key.
	 * @param string $label The label.
	 * @param int    $value The value.
	 * @param int    $max   The largest value.
	 * @param string $unit  'px' or '%'.
	 * @param int    $min   The smallest value.
	 */
	private function print_range( string $key, string $label, int $value, int $max, string $unit = 'px', int $min = 0 ): void {
		$corners = in_array( $key, array( 'radius', 'field_radius' ), true );
		$id      = 'iftp-btn-look-' . $key;
		?>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?> <output class="iftp-btn-range__value" for="<?php echo esc_attr( $id ); ?>" data-iftp-range-out data-unit="<?php echo esc_attr( $unit ); ?>"><?php echo esc_html( '%' === $unit ? $value . '%' : $value . ' px' ); ?></output></label>
			<span class="iftp-btn-range">
				<span class="iftp-btn-range__end <?php echo $corners ? 'iftp-btn-range__end--square' : 'iftp-btn-range__end--less'; ?>" aria-hidden="true"></span>
				<input type="range" class="iftp-btn-range__input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $this->name( $key ) ); ?>" min="<?php echo esc_attr( (string) $min ); ?>" max="<?php echo esc_attr( (string) $max ); ?>" step="1" value="<?php echo esc_attr( (string) $value ); ?>" data-iftp-look="<?php echo esc_attr( $key ); ?>" />
				<span class="iftp-btn-range__end <?php echo $corners ? 'iftp-btn-range__end--pill' : 'iftp-btn-range__end--more'; ?>" aria-hidden="true"></span>
			</span>
		</div>
		<?php
	}

	/**
	 * A compact segmented choice.
	 *
	 * @param string                $key     The look key.
	 * @param string                $label   The legend.
	 * @param string                $value   The current value.
	 * @param array<string, string> $options Value => label.
	 * @param bool                  $wide    Whether it spans both columns.
	 */
	private function print_choice( string $key, string $label, string $value, array $options, bool $wide = false ): void {
		?>
		<fieldset class="iftp-btn-field<?php echo $wide ? ' iftp-btn-design__wide' : ''; ?>">
			<legend class="iftp-btn-field__label"><?php echo esc_html( $label ); ?></legend>
			<span class="iftp-btn-seg">
				<?php foreach ( $options as $option => $option_label ) : ?>
					<label class="iftp-btn-seg__option">
						<input type="radio" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="<?php echo esc_attr( $option ); ?>" <?php checked( $value, $option ); ?> data-iftp-look="<?php echo esc_attr( $key ); ?>" />
						<span><?php echo esc_html( $option_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</span>
		</fieldset>
		<?php
	}

	/**
	 * The step bar styles as cards, each drawn by the real step bar on page 2 of 3, half answered.
	 *
	 * @param string $value The current style.
	 */
	private function print_step_styles( string $value ): void {
		$styles = array(
			'bars'    => array( __( 'Bars', 'ifthenpay-pay-button' ), __( 'A bar per page', 'ifthenpay-pay-button' ) ),
			'labels'  => array( __( 'Named bars', 'ifthenpay-pay-button' ), __( 'Each page\'s title under it', 'ifthenpay-pay-button' ) ),
			'line'    => array( __( 'One line', 'ifthenpay-pay-button' ), __( 'The whole form, with a %', 'ifthenpay-pay-button' ) ),
			'numbers' => array( __( 'Numbers', 'ifthenpay-pay-button' ), __( 'Numbered circles that tick off', 'ifthenpay-pay-button' ) ),
			'dots'    => array( __( 'Dots', 'ifthenpay-pay-button' ), __( 'This page stretches and fills', 'ifthenpay-pay-button' ) ),
			'ring'    => array( __( 'Ring', 'ifthenpay-pay-button' ), __( 'A round meter, a slice a page', 'ifthenpay-pay-button' ) ),
			'text'    => array( __( 'Just words', 'ifthenpay-pay-button' ), __( 'The title and the step, no bar', 'ifthenpay-pay-button' ) ),
		);
		$titles = array(
			__( 'Plan', 'ifthenpay-pay-button' ),
			__( 'Details', 'ifthenpay-pay-button' ),
			__( 'Confirm', 'ifthenpay-pay-button' ),
		);
		?>
		<fieldset class="iftp-btn-stepstyles">
			<legend class="screen-reader-text"><?php esc_html_e( 'Page progress', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $styles as $style => $text ) : ?>
				<label class="iftp-btn-stepstyles__item">
					<input type="radio" class="iftp-btn-stepstyles__input" name="<?php echo esc_attr( $this->name( 'steps' ) ); ?>" value="<?php echo esc_attr( $style ); ?>" <?php checked( $value, $style ); ?> data-iftp-look="steps" />
					<span class="iftp-btn-stepstyles__sample iftp-btn-look iftp-btn-form" aria-hidden="true" data-iftp-look-sample>
						<?php
						StepBar::render(
							$titles,
							$style,
							array(
								'current' => 1,
								'fill'    => 0.5,
							)
						);
						?>
					</span>
					<span class="iftp-btn-stepstyles__name"><?php echo esc_html( $text[0] ); ?></span>
					<span class="iftp-btn-stepstyles__hint"><?php echo esc_html( $text[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * An on/off switch row.
	 *
	 * @param string $key   The look key.
	 * @param string $title The title.
	 * @param string $hint  The line under it.
	 * @param bool   $on    Whether it's on.
	 */
	private function print_toggle( string $key, string $title, string $hint, bool $on ): void {
		?>
		<label class="iftp-btn-np__toggle">
			<input type="hidden" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="0" />
			<input type="checkbox" class="iftp-btn-switch" name="<?php echo esc_attr( $this->name( $key ) ); ?>" value="1" <?php checked( $on ); ?> data-iftp-look="<?php echo esc_attr( $key ); ?>" />
			<span class="iftp-btn-np__toggle-text">
				<span class="iftp-btn-np__toggle-title"><?php echo esc_html( $title ); ?></span>
				<span class="iftp-btn-np__toggle-hint"><?php echo esc_html( $hint ); ?></span>
			</span>
		</label>
		<?php
	}
}
