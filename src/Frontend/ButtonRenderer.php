<?php
/**
 * Pay button and checkout markup, and the frontend assets.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Api\ReturnHandler;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\RestController;
use Ifthenpay\PayButton\Checkout\SpotCounter;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints buttons and their checkout forms. Each button carries its own form in a native <dialog>, so a page
 * with three buttons has three small forms and no shared state to keep in sync.
 *
 * @phpstan-import-type Method from SettingsRepository
 * @phpstan-import-type FormItem from FormFields
 * @phpstan-import-type FormStep from FormFields
 * @phpstan-import-type SpotsLeft from SpotCounter
 * @phpstan-type OptionState array{price: string, left: int|null, badge: string, sold_out: bool}
 * @phpstan-type SpotContext array{field: string, left: SpotsLeft, max: int, options: array<string, OptionState>}
 */
class ButtonRenderer {

	public const SCRIPT_HANDLE = 'iftp-btn-checkout';
	public const STYLE_HANDLE  = 'iftp-btn-checkout';

	/**
	 * Gateway settings.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout_settings;

	/**
	 * How many forms this request printed, so each gets unique IDs.
	 *
	 * @var int
	 */
	private static int $instances = 0;

	/**
	 * Sets up the renderer.
	 *
	 * @param SettingsRepository|null $settings          Gateway settings.
	 * @param CheckoutSettings|null   $checkout_settings Checkout settings.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?CheckoutSettings $checkout_settings = null ) {
		$this->settings          = $settings ?? new SettingsRepository();
		$this->checkout_settings = $checkout_settings ?? new CheckoutSettings();
	}

	/**
	 * Registers the assets everywhere, and enqueues them when a payer comes back carrying a result.
	 */
	public function register(): void {
		// On init, not wp_enqueue_scripts: the block's style and the editor preview need the handles too.
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_for_result' ), 20 );
	}

	/**
	 * Registers the checkout script and stylesheet.
	 */
	public function register_assets(): void {
		$js  = 'assets/js/checkout.js';
		$css = 'assets/css/checkout.css';

		wp_register_style( self::STYLE_HANDLE, IFTP_BTN_URL . $css, array(), Plugin::asset_version( $css ) );
		// The site's checkout look, and the merchant's own CSS after it.
		wp_add_inline_style( self::STYLE_HANDLE, CheckoutLook::css( $this->checkout_settings->get_look() ) );
		wp_register_script( self::SCRIPT_HANDLE, IFTP_BTN_URL . $js, array( Plugin::METHOD_LOGOS_HANDLE ), Plugin::asset_version( $js ), true );
		wp_localize_script( self::SCRIPT_HANDLE, 'iftpBtnCheckout', $this->script_data() );
	}

	/**
	 * The outcome popup has to show on whatever page the payer lands on, even one with no button.
	 */
	public function maybe_enqueue_for_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to load the script; the status route checks the signature.
		if ( isset( $_GET[ ReturnHandler::RESULT_VAR ] ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueues the checkout assets.
	 */
	public static function enqueue(): void {
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * What checkout.js needs: the routes, the outcome messages and its strings.
	 *
	 * @return array<string, mixed>
	 */
	private function script_data(): array {
		global $wp_locale;

		$confirmation = $this->checkout_settings->get_confirmation();

		return array(
			'checkoutUrl' => esc_url_raw( rest_url( RestController::NAMESPACE . '/checkout' ) ),
			'statusUrl'   => esc_url_raw( rest_url( RestController::NAMESPACE . '/payments/' ) ),
			'opensUrl'    => esc_url_raw( rest_url( RestController::NAMESPACE . '/opens' ) ),
			// The merchant trying their own buttons shouldn't count as payers who walked away.
			'countOpens'  => ! current_user_can( Plugin::admin_cap() ),
			'resultVar'   => ReturnHandler::RESULT_VAR,
			'resultSig'   => ReturnHandler::RESULT_SIG,
			'messages'    => array_map( 'wp_kses_post', $confirmation['messages'] ),
			'showDetails' => $confirmation['show_details'],
			'popupIcon'   => $this->checkout_settings->get_look()['popup_icon'],
			'scene'       => CheckoutLook::scene_classes( $this->checkout_settings->get_look() ),
			'dark'        => '' !== CheckoutLook::dark_class( $this->checkout_settings->get_look() ),
			'sceneVideo'  => CheckoutLook::scene_video( $this->checkout_settings->get_look() ),
			// So "Checking your payment" already wears the button's own look, not the site's for a second.
			'resultLook'  => $this->returning_look(),
			'logo'        => esc_url_raw( IFTP_BTN_URL . 'assets/img/logo-color.svg' ),
			// So the live total reads like the server's number_format_i18n().
			'number'      => array(
				'decimal'   => isset( $wp_locale ) ? $wp_locale->number_format['decimal_point'] : '.',
				'thousands' => isset( $wp_locale ) ? $wp_locale->number_format['thousands_sep'] : ',',
			),
			'i18n'        => array(
				'starting'    => CheckoutTexts::get( 'starting' ),
				'failed'      => CheckoutTexts::get( 'failed' ),
				'checking'    => CheckoutTexts::get( 'checking' ),
				'close'       => CheckoutTexts::get( 'close' ),
				'amount'      => CheckoutTexts::get( 'amount' ),
				'reference'   => CheckoutTexts::get( 'reference' ),
				'method'      => CheckoutTexts::get( 'method' ),
				'redirecting' => CheckoutTexts::get( 'redirecting' ),
				'chooseOne'   => CheckoutTexts::get( 'choose_one' ),
				'titles'      => $confirmation['titles'],
			),
		);
	}

	/**
	 * The own look of the button a returning payer paid with, or null for the site's look.
	 *
	 * @return array{style: string, icon: string, font: string, scene: string, dark: bool, video: array{kind: string, src: string}|null}|null
	 */
	private function returning_look(): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, and the signature authenticates it.
		if ( ! isset( $_GET[ ReturnHandler::RESULT_VAR ], $_GET[ ReturnHandler::RESULT_SIG ] ) ) {
			return null;
		}

		$ref       = sanitize_text_field( wp_unslash( $_GET[ ReturnHandler::RESULT_VAR ] ) );
		$signature = sanitize_text_field( wp_unslash( $_GET[ ReturnHandler::RESULT_SIG ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! hash_equals( ReturnHandler::result_signature( $ref ), $signature ) ) {
			return null;
		}

		$record = ( new TransactionStore() )->get( $ref );

		return null !== $record && $record['button_id'] > 0 ? CheckoutLook::popup_look( ( new ButtonRepository() )->find( $record['button_id'] ) ) : null;
	}

	/**
	 * A button that opens its checkout, ready for a block, a shortcode or a widget.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $args   Optional: label, button_class, button_style, wrapper (attributes string for the wrapper).
	 */
	public function render_button( Button $button, array $args = array() ): string {
		self::enqueue();

		$available = ( new CheckoutService( $this->settings ) )->availability( $button );
		$design    = $button->config['design'];
		$label     = $button->display_label( is_string( $args['label'] ?? null ) ? $args['label'] : '' );
		$look      = ButtonDesign::attributes( $design );
		$inner     = ButtonDesign::inner_html( $design, $label );
		$wrapper   = is_string( $args['wrapper'] ?? null ) ? $args['wrapper'] : 'class="iftp-btn-pay wp-block-button"';
		$classes   = trim( $look['class'] . ' ' . ( is_string( $args['button_class'] ?? null ) ? $args['button_class'] : '' ) );
		// The block's own colours and padding come last, so they win over the button's design.
		$css   = trim( $look['style'] . ( is_string( $args['button_style'] ?? null ) && '' !== $args['button_style'] ? ';' . $args['button_style'] : '' ), ';' );
		$style = '' !== $css ? ' style="' . esc_attr( $css ) . '"' : '';
		$logos = $design['logos'] ? $this->logo_strip( $button ) : '';

		if ( is_wp_error( $available ) ) {
			// Admins see why it's off; payers just see a disabled button, which says so when it's sold out.
			$hint = current_user_can( Plugin::admin_cap() ) ? '<span class="iftp-btn-pay__admin-hint">' . esc_html( $available->get_error_message() ) . '</span>' : '';

			if ( 'iftp_btn_sold_out' === $available->get_error_code() ) {
				$inner = ButtonDesign::inner_html( $design, CheckoutTexts::get( 'sold_out' ) );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $wrapper comes from get_block_wrapper_attributes() or the literal above; $inner and $logos are escaped where they're built.
			return '<div ' . $wrapper . '><button type="button" class="' . esc_attr( $classes ) . '"' . $style . ' disabled>' . $inner . '</button>' . $hint . '</div>';
		}

		$dialog_id = 'iftp-btn-checkout-' . ( ++self::$instances );

		ob_start();
		?>
		<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above. ?>>
			<button type="button" class="<?php echo esc_attr( $classes ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above. ?> aria-haspopup="dialog" data-iftp-btn-open="<?php echo esc_attr( $dialog_id ); ?>"><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ButtonDesign::inner_html() escapes the text; the icon is a fixed SVG. ?></button>
			<?php echo $logos; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in logo_strip(). ?>
			<?php
			$own   = CheckoutLook::inline_style( $button );
			$look  = CheckoutLook::for_button( $button, $this->checkout_settings );
			$scene = trim( CheckoutLook::scene_classes( $look ) . ' ' . CheckoutLook::dark_class( $look ) );
			?>
			<dialog class="iftp-btn-sheet<?php echo '' !== $own ? ' iftp-btn-font--' . esc_attr( $button->config['look']['font'] ) : ''; ?><?php echo '' !== $scene ? ' ' . esc_attr( $scene ) : ''; ?>" id="<?php echo esc_attr( $dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>-title"<?php echo '' !== $own ? ' style="' . esc_attr( $own ) . '"' : ''; ?>>
				<?php
				if ( $look['scene'] ) {
					echo CheckoutLook::video_markup( $look, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in video_markup().
				}

				$this->print_form( $button, $dialog_id, array(), true );
				?>
			</dialog>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The offered methods' logos in a row under the button, so payers see how they can pay before they click.
	 *
	 * @param Button $button The button.
	 */
	private function logo_strip( Button $button ): string {
		$logos = $this->offered_logos( $button );

		if ( array() === $logos ) {
			return '';
		}

		$html = '';

		foreach ( $logos as $method ) {
			$html .= '<img src="' . esc_url( $method['logo'] ?? '' ) . '"' . self::dark_logo_attr( $method ) . ' alt="' . esc_attr( '' !== $method['alias'] ? $method['alias'] : $method['entity'] ) . '" height="16" loading="lazy" />';
		}

		return '<span class="iftp-btn-pay__logos">' . $html . '</span>';
	}

	/**
	 * The checkout form for one button: a fixed header (and step bar), a body that scrolls on its own, and
	 * fixed actions. Page breaks in the button's form split the body into steps; checkout.js walks them.
	 *
	 * @param Button               $button  The button.
	 * @param string               $id      Unique id prefix for this form.
	 * @param array<string, mixed> $prefill What a pay link carries, from PayLinks::read_query().
	 * @param bool                 $closable Whether it sits in a dialog with a close button.
	 */
	public function print_form( Button $button, string $id, array $prefill, bool $closable ): void {
		$fields    = is_array( $prefill['fields'] ?? null ) ? $prefill['fields'] : array();
		$amount    = is_string( $prefill['amount'] ?? null ) ? $prefill['amount'] : '';
		$reference = is_string( $prefill['reference'] ?? null ) ? $prefill['reference'] : '';
		$signed    = is_string( $prefill['sig'] ?? null ) && '' !== $prefill['sig'];
		$mode      = $button->mode();
		$locked    = Button::MODE_FIXED === $mode || Button::MODE_LINK === $mode || ( $signed && '' !== $amount );
		$shown     = Button::MODE_FIXED === $mode && ! ( $signed && '' !== $amount ) ? number_format( $button->config['amount'], 2, '.', '' ) : $amount;
		$site      = CheckoutTexts::get( 'header' );
		$steps     = $button->steps();
		$count     = count( $steps );
		$look      = CheckoutLook::for_button( $button, $this->checkout_settings );
		// A fixed amount is per spot, so the total follows the option and the quantity.
		$per_spot = Button::MODE_FIXED === $mode && ! ( $signed && '' !== $amount );
		$spots    = $this->spot_context( $button, $per_spot );
		$range    = Spots::price_range( $button );
		$total    = $per_spot && $range[0] < $range[1]
			? CheckoutTexts::get( 'from', array( 'price' => CheckoutService::format_money( $range[0] ) ) )
			: CheckoutService::format_money( (float) $shown );
		$context  = array(
			'id'      => $id,
			'fields'  => $fields,
			'amount'  => $amount,
			'locked'  => $locked,
			'shown'   => $shown,
			'spots'   => $spots,
			'visible' => FormFields::visibility( $button->config['form'], FormFields::answers( $button->config['form'], $fields ) ),
		);
		?>
		<form class="iftp-btn-form<?php echo $count > 1 ? ' iftp-btn-form--steps' : ''; ?>" method="post" novalidate data-iftp-btn-form data-button="<?php echo esc_attr( (string) $button->id ); ?>" data-skip="<?php echo esc_attr( $button->config['skip_form'] ? '1' : '0' ); ?>"<?php echo $per_spot ? ' data-unit="' . esc_attr( number_format( $button->config['amount'], 2, '.', '' ) ) . '"' : ''; ?>>
			<header class="iftp-btn-form__header">
				<span class="iftp-btn-form__site"><?php echo esc_html( $site ); ?></span>
				<?php if ( $closable ) : ?>
					<button type="button" class="iftp-btn-form__close" data-iftp-btn-close aria-label="<?php echo esc_attr( CheckoutTexts::get( 'close' ) ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>
					</button>
				<?php endif; ?>
			</header>

			<?php if ( $count > 1 ) : ?>
				<?php self::print_step_bar( $steps, $id ); ?>
			<?php endif; ?>

			<div class="iftp-btn-form__body" data-iftp-btn-body>
				<?php foreach ( $steps as $index => $step ) : ?>
					<div class="iftp-btn-form__step" data-iftp-btn-step="<?php echo esc_attr( (string) $index ); ?>"<?php echo $count > 1 ? ' role="group" aria-label="' . esc_attr( $step['title'] ) . '"' : ''; ?><?php echo $index > 0 ? ' hidden' : ''; ?>>
						<?php if ( 0 === $index ) : ?>
							<div class="iftp-btn-form__summary">
								<h2 class="iftp-btn-form__title" id="<?php echo esc_attr( $id ); ?>-title"><?php echo esc_html( $button->title ); ?></h2>
								<?php if ( '' !== $button->config['summary'] ) : ?>
									<p class="iftp-btn-form__text"><?php echo esc_html( $button->config['summary'] ); ?></p>
								<?php endif; ?>
								<?php if ( $locked && '' !== $shown ) : ?>
									<p class="iftp-btn-form__amount" data-iftp-btn-total aria-live="polite"><?php echo esc_html( $total ); ?></p>
								<?php endif; ?>
								<?php $this->print_left( $button, $spots ); ?>
							</div>
						<?php endif; ?>
						<?php
						foreach ( $step['items'] as $item ) {
							$this->print_item( $button, $item, $context );
						}
						?>
					</div>
				<?php endforeach; ?>

				<?php if ( $locked ) : ?>
					<input type="hidden" name="amount" value="<?php echo esc_attr( $shown ); ?>" />
				<?php endif; ?>
				<input type="hidden" name="reference" value="<?php echo esc_attr( $reference ); ?>" />
				<?php if ( $signed ) : ?>
					<input type="hidden" name="expires" value="<?php echo esc_attr( (string) ( $prefill['expires'] ?? '' ) ); ?>" />
					<input type="hidden" name="sig" value="<?php echo esc_attr( (string) $prefill['sig'] ); ?>" />
				<?php endif; ?>

				<label class="iftp-btn-form__trap" aria-hidden="true"><?php esc_html_e( 'Website', 'ifthenpay-pay-button' ); ?><input type="text" name="<?php echo esc_attr( CheckoutService::HONEYPOT ); ?>" tabindex="-1" autocomplete="off" /></label>
			</div>

			<div class="iftp-btn-form__actions">
				<p class="iftp-btn-form__error" role="alert" hidden data-iftp-btn-error></p>
				<div class="iftp-btn-form__buttons">
					<?php if ( $count > 1 ) : ?>
						<button type="button" class="iftp-btn-form__back" data-iftp-btn-back hidden>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>
							<span class="iftp-btn-form__back-text"><?php echo esc_html( CheckoutTexts::get( 'back' ) ); ?></span>
						</button>
					<?php endif; ?>
					<button type="submit" class="iftp-btn-form__submit" data-iftp-btn-submit data-pay="<?php echo esc_attr( CheckoutTexts::get( 'pay' ) ); ?>" data-next="<?php echo esc_attr( CheckoutTexts::get( 'next' ) ); ?>">
						<span class="iftp-btn-form__submit-text"><?php echo esc_html( CheckoutTexts::get( $count > 1 ? 'next' : 'pay' ) ); ?></span>
						<span class="iftp-btn-form__spinner" aria-hidden="true"></span>
					</button>
				</div>
			</div>

			<?php if ( $look['logos'] || $look['secure'] ) : ?>
				<footer class="iftp-btn-form__footer">
					<?php
					if ( $look['logos'] ) {
						$this->print_method_logos( $button );
					}
					?>
					<?php if ( $look['secure'] ) : ?>
						<span class="iftp-btn-form__secure">
							<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
							<?php echo esc_html( CheckoutTexts::get( 'secure' ) ); ?>
							<img src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="62" height="16" />
						</span>
					<?php endif; ?>
				</footer>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * The step bar: one segment per step, the current step's title and where the payer is.
	 *
	 * @param array<int, array{title: string, items: array<int, array<string, mixed>>}> $steps The steps.
	 * @param string                                                                    $id    The form's id prefix.
	 * @phpstan-param array<int, FormStep> $steps
	 */
	private static function print_step_bar( array $steps, string $id ): void {
		$count = count( $steps );
		?>
		<div class="iftp-btn-steps" data-iftp-btn-steps style="--iftp-steps: <?php echo esc_attr( (string) $count ); ?>">
			<div class="iftp-btn-steps__head">
				<span class="iftp-btn-steps__title" id="<?php echo esc_attr( $id ); ?>-step-title" data-iftp-btn-step-title aria-live="polite"><?php echo esc_html( $steps[0]['title'] ); ?></span>
				<span class="iftp-btn-steps__count">
					<?php
					// Escaped before the tokens go in, since {step} becomes markup checkout.js counts up in.
					$step_of = strtr(
						esc_html( CheckoutTexts::get( 'step_of' ) ),
						array(
							'{step}'  => '<span data-iftp-btn-step-now>1</span>',
							'{steps}' => esc_html( (string) $count ),
						)
					);
					echo wp_kses( $step_of, array( 'span' => array( 'data-iftp-btn-step-now' => true ) ) );
					?>
				</span>
			</div>
			<ol class="iftp-btn-steps__track">
				<?php foreach ( $steps as $index => $step ) : ?>
					<li class="iftp-btn-steps__bar<?php echo 0 === $index ? ' is-current' : ''; ?>" data-iftp-btn-bar data-title="<?php echo esc_attr( $step['title'] ); ?>"><span class="iftp-btn-steps__fill"></span><span class="iftp-btn-steps__sr"><?php echo esc_html( $step['title'] ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * One item of the button's form: the amount block, the payer details, or one of the merchant's own items.
	 *
	 * @param Button               $button  The button.
	 * @param array<string, mixed> $item    The item.
	 * @param array<string, mixed> $context The form's id, prefill and amount state.
	 * @phpstan-param FormItem $item
	 * @phpstan-param array{id: string, fields: array<string, mixed>, amount: string, locked: bool, shown: string, spots: SpotContext, visible: array<string, bool>} $context
	 */
	private function print_item( Button $button, array $item, array $context ): void {
		$id   = $context['id'];
		$when = self::when_attributes( $item, $context['visible'][ $item['id'] ] ?? true );

		if ( FormFields::ANCHOR_AMOUNT === $item['type'] ) {
			if ( ! $context['locked'] ) {
				$this->print_amount( $button, $id, $context['amount'] );
			}

			if ( $button->config['spots']['quantity'] ) {
				self::print_quantity( $id, $context['spots']['max'] );
			}

			return;
		}

		if ( FormFields::ANCHOR_DETAILS === $item['type'] ) {
			$this->print_details( $button, $id, $context['fields'] );

			return;
		}

		if ( FormFields::TYPE_CONTENT === $item['type'] ) {
			?>
			<div class="iftp-btn-form__content"<?php echo $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in when_attributes(). ?>>
				<p class="iftp-btn-form__heading"><?php echo esc_html( $item['label'] ); ?></p>
				<?php if ( '' !== $item['help'] ) : ?>
					<p class="iftp-btn-form__help"><?php echo nl2br( esc_html( $item['help'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped, then line breaks. ?></p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		$this->print_custom( $item, $id, $context['fields'], $context['spots']['field'] === $item['id'] ? $context['spots'] : null, $when );
	}

	/**
	 * The rule checkout.js shows or hides an item by, and hidden when the answers so far don't meet it.
	 *
	 * @param array<string, mixed> $item  The item.
	 * @param bool                 $shown Whether it shows for the prefilled answers.
	 * @phpstan-param FormItem $item
	 */
	private static function when_attributes( array $item, bool $shown ): string {
		if ( '' === $item['when']['field'] ) {
			return '';
		}

		$rule = array(
			'field'  => FormFields::input_name( $item['when']['field'] ),
			'op'     => $item['when']['op'],
			'values' => $item['when']['values'],
		);

		return ' data-iftp-btn-when="' . esc_attr( (string) wp_json_encode( $rule ) ) . '"' . ( $shown ? '' : ' hidden' );
	}

	/**
	 * What the form needs to know about the button's spots.
	 *
	 * @param Button $button   The button.
	 * @param bool   $per_spot Whether the amount is per spot, so options show their price.
	 * @return SpotContext
	 */
	private function spot_context( Button $button, bool $per_spot ): array {
		$spots   = $button->config['spots'];
		$left    = ( new SpotCounter() )->left( $button );
		$options = array();

		if ( Spots::MODE_OPTIONS === $spots['mode'] ) {
			foreach ( $spots['options'] as $option ) {
				$options[ $option['label'] ] = self::option_state( $button, $option['label'], $left['options'][ $option['label'] ] ?? null, $per_spot );
			}
		}

		return array(
			'field'   => Spots::MODE_OPTIONS === $spots['mode'] ? $spots['field'] : '',
			'left'    => $left,
			'max'     => null !== $left['total'] ? max( 1, min( $spots['max_each'], $left['total'] ) ) : $spots['max_each'],
			'options' => $options,
		);
	}

	/**
	 * "Only 4 spots left" under the title, when a shared number is running low.
	 *
	 * @param Button               $button The button.
	 * @param array<string, mixed> $spots  From spot_context().
	 * @phpstan-param SpotContext $spots
	 */
	private function print_left( Button $button, array $spots ): void {
		$left   = $spots['left']['total'];
		$config = $button->config['spots'];

		if ( Spots::MODE_TOTAL !== $config['mode'] || ! $config['show_left'] || null === $left || $left > $config['left_below'] ) {
			return;
		}

		echo '<p class="iftp-btn-form__left">' . esc_html( CheckoutTexts::get( 'spots_left', array( 'count' => $left ) ) ) . '</p>';
	}

	/**
	 * How many spots the payer takes, with − and + either side.
	 *
	 * @param string $id  The form's id prefix.
	 * @param int    $max The most they can take right now.
	 */
	private static function print_quantity( string $id, int $max ): void {
		?>
		<div class="iftp-btn-form__field iftp-btn-form__field--quantity">
			<label class="iftp-btn-form__label" for="<?php echo esc_attr( $id ); ?>-quantity"><?php echo esc_html( CheckoutTexts::get( 'quantity' ) ); ?></label>
			<span class="iftp-btn-form__stepper">
				<button type="button" class="iftp-btn-form__stepper-button" data-iftp-btn-qty="-1" aria-label="<?php echo esc_attr( CheckoutTexts::get( 'one_less' ) ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>-quantity">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 12h12"/></svg>
				</button>
				<input class="iftp-btn-form__input" type="number" inputmode="numeric" id="<?php echo esc_attr( $id ); ?>-quantity" name="quantity" value="1" min="1" max="<?php echo esc_attr( (string) $max ); ?>" step="1" required data-iftp-btn-quantity data-max-each="<?php echo esc_attr( (string) $max ); ?>" />
				<button type="button" class="iftp-btn-form__stepper-button" data-iftp-btn-qty="1" aria-label="<?php echo esc_attr( CheckoutTexts::get( 'one_more' ) ); ?>" aria-controls="<?php echo esc_attr( $id ); ?>-quantity">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 12h12M12 6v12"/></svg>
				</button>
			</span>
		</div>
		<?php
	}

	/**
	 * What one option of the spots field carries: its price, what's left, and the badge for it.
	 *
	 * @param Button   $button   The button.
	 * @param string   $option   The option's label.
	 * @param int|null $left     Spots left on it, or null for no limit.
	 * @param bool     $per_spot Whether the amount is per spot, so it shows its price.
	 * @return OptionState
	 */
	private static function option_state( Button $button, string $option, ?int $left, bool $per_spot ): array {
		$spots = $button->config['spots'];
		$badge = '';

		if ( 0 === $left ) {
			$badge = CheckoutTexts::get( 'sold_out' );
		} elseif ( null !== $left && $spots['show_left'] && $left <= $spots['left_below'] ) {
			$badge = CheckoutTexts::get( 'option_left', array( 'count' => $left ) );
		}

		return array(
			'price'    => $per_spot ? number_format( Spots::unit_price( $button, $option ), 2, '.', '' ) : '',
			'left'     => $left,
			'badge'    => $badge,
			'sold_out' => 0 === $left,
		);
	}

	/**
	 * The amount the payer types, with the suggested amounts.
	 *
	 * @param Button $button The button.
	 * @param string $id     The form's id prefix.
	 * @param string $amount A prefilled amount, or ''.
	 */
	private function print_amount( Button $button, string $id, string $amount ): void {
		?>
		<div class="iftp-btn-form__field iftp-btn-form__field--amount">
			<label class="iftp-btn-form__label" for="<?php echo esc_attr( $id ); ?>-amount"><?php echo esc_html( CheckoutTexts::get( 'amount' ) ); ?></label>
			<?php if ( array() !== $button->config['presets'] ) : ?>
				<div class="iftp-btn-form__presets" role="group" aria-label="<?php echo esc_attr( CheckoutTexts::get( 'suggested' ) ); ?>">
					<?php foreach ( $button->config['presets'] as $preset ) : ?>
						<button type="button" class="iftp-btn-form__preset" data-iftp-btn-preset="<?php echo esc_attr( number_format( $preset, 2, '.', '' ) ); ?>" aria-pressed="false"><?php echo esc_html( CheckoutService::format_money( $preset ) ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<span class="iftp-btn-form__money">
				<input class="iftp-btn-form__input" type="text" inputmode="decimal" autocomplete="off" id="<?php echo esc_attr( $id ); ?>-amount" name="amount" value="<?php echo esc_attr( $amount ); ?>" required aria-describedby="<?php echo esc_attr( $id ); ?>-amount-help" />
				<span class="iftp-btn-form__currency" aria-hidden="true">€</span>
			</span>
			<span class="iftp-btn-form__help" id="<?php echo esc_attr( $id ); ?>-amount-help">
				<?php
				echo esc_html(
					CheckoutTexts::get(
						'between',
						array(
							'min' => CheckoutService::format_money( $button->config['min'] ),
							'max' => CheckoutService::format_money( $button->config['max'] ),
						)
					)
				);
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * The built-in payer fields the Payer details card switches on.
	 *
	 * @param Button               $button The button.
	 * @param string               $id     The form's id prefix.
	 * @param array<string, mixed> $fields Prefilled values.
	 */
	private function print_details( Button $button, string $id, array $fields ): void {
		$labels = CheckoutService::field_labels( $button );

		foreach ( $button->visible_fields() as $field => $required ) :
			$field_id = $id . '-' . $field;
			$value    = is_string( $fields[ $field ] ?? null ) ? $fields[ $field ] : '';
			?>
			<div class="iftp-btn-form__field">
				<label class="iftp-btn-form__label" for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $labels[ $field ] ); ?>
					<?php if ( ! $required ) : ?>
						<span class="iftp-btn-form__optional"><?php echo esc_html( CheckoutTexts::get( 'optional' ) ); ?></span>
					<?php endif; ?>
				</label>
				<?php if ( 'note' === $field ) : ?>
					<textarea class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="note" rows="2" maxlength="500"<?php echo $required ? ' required' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
				<?php else : ?>
					<input class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $required ? ' required' : ''; ?> <?php echo self::input_attributes( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal attributes. ?> />
				<?php endif; ?>
			</div>
			<?php
		endforeach;
	}

	/**
	 * One of the merchant's own fields.
	 *
	 * @param array<string, mixed>      $item   The field.
	 * @param string                    $id     The form's id prefix.
	 * @param array<string, mixed>      $fields Prefilled values.
	 * @param array<string, mixed>|null $spots  The spots, when this is the field they're split by.
	 * @param string                    $when   Its show/hide rule's attributes, from when_attributes().
	 * @phpstan-param FormItem $item
	 * @phpstan-param SpotContext|null $spots
	 */
	private function print_custom( array $item, string $id, array $fields, ?array $spots = null, string $when = '' ): void {
		$name     = FormFields::input_name( $item['id'] );
		$field_id = $id . '-' . $name;
		$value    = is_string( $fields[ $name ] ?? null ) ? $fields[ $name ] : '';
		$required = $item['required'] ? ' required' : '';
		$help_id  = '' !== $item['help'] ? $field_id . '-help' : '';
		$describe = '' !== $help_id ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
		$classes  = 'iftp-btn-form__field iftp-btn-form__field--' . $item['type'] . ( 'half' === $item['width'] ? ' iftp-btn-form__field--half' : '' );
		$optional = $item['required'] ? '' : ' <span class="iftp-btn-form__optional">' . esc_html( CheckoutTexts::get( 'optional' ) ) . '</span>';
		$choices  = in_array( $item['type'], array( 'radio', 'checkboxes' ), true );

		if ( 'checkbox' === $item['type'] ) {
			?>
			<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in when_attributes(). ?>>
				<label class="iftp-btn-form__agree">
					<input type="checkbox" class="iftp-btn-form__tick" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1"<?php echo $required . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?> <?php checked( '' !== $value && '0' !== $value ); ?> />
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</label>
				<?php self::print_help( $item['help'], $help_id ); ?>
			</div>
			<?php
			return;
		}

		if ( $choices ) {
			$picked = 'checkboxes' === $item['type'] ? array_map( 'trim', explode( ',', $value ) ) : array( $value );
			?>
			<fieldset class="<?php echo esc_attr( $classes ); ?>"<?php echo $item['required'] && 'checkboxes' === $item['type'] ? ' data-iftp-btn-required-group' : ''; ?><?php echo $describe . $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above and in when_attributes(). ?>>
				<legend class="iftp-btn-form__label"><?php echo esc_html( $item['label'] ) . $optional; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $optional is escaped where it's built. ?></legend>
				<div class="iftp-btn-form__choices">
					<?php foreach ( $item['options'] as $index => $option ) : ?>
						<?php $state = $spots['options'][ $option ] ?? null; ?>
						<label class="iftp-btn-form__choice<?php echo null !== $state && $state['sold_out'] ? ' is-sold-out' : ''; ?>">
							<?php // A sold-out first option can't carry the group's required, so the spots field puts it on every option. ?>
							<input type="<?php echo 'radio' === $item['type'] ? 'radio' : 'checkbox'; ?>" class="iftp-btn-form__tick" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $option ); ?>"<?php echo 'radio' === $item['type'] && ( 0 === $index || null !== $spots ) ? $required : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal. ?><?php echo self::option_attributes( $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in option_attributes(). ?> <?php checked( null !== $state && $state['sold_out'] ? false : in_array( $option, $picked, true ) ); ?> />
							<span class="iftp-btn-form__choice-text"><?php echo esc_html( $option ); ?></span>
							<?php if ( null !== $state && ( '' !== $state['price'] || '' !== $state['badge'] ) ) : ?>
								<span class="iftp-btn-form__choice-meta">
									<?php if ( '' !== $state['badge'] ) : ?>
										<span class="iftp-btn-form__badge<?php echo $state['sold_out'] ? ' iftp-btn-form__badge--out' : ''; ?>"><?php echo esc_html( $state['badge'] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $state['price'] ) : ?>
										<span class="iftp-btn-form__choice-price"><?php echo esc_html( CheckoutService::format_money( (float) $state['price'] ) ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php self::print_help( $item['help'], $help_id ); ?>
			</fieldset>
			<?php
			return;
		}

		$placeholder = '' !== $item['placeholder'] ? ' placeholder="' . esc_attr( $item['placeholder'] ) . '"' : '';
		$types       = array(
			'text'   => 'type="text" maxlength="191"',
			'email'  => 'type="email" inputmode="email" autocomplete="email" maxlength="191"',
			'tel'    => 'type="tel" inputmode="tel" autocomplete="tel" maxlength="30"',
			'number' => 'type="text" inputmode="decimal" maxlength="30" pattern="-?[0-9]+([.,][0-9]+)?"',
			'date'   => 'type="date"',
		);
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in when_attributes(). ?>>
			<label class="iftp-btn-form__label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $item['label'] ) . $optional; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $optional is escaped where it's built. ?></label>
			<?php if ( 'textarea' === $item['type'] ) : ?>
				<textarea class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3" maxlength="1000"<?php echo $required . $placeholder . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php elseif ( 'select' === $item['type'] ) : ?>
				<span class="iftp-btn-form__select">
					<select class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?>>
						<option value=""><?php echo esc_html( '' !== $item['placeholder'] ? $item['placeholder'] : CheckoutTexts::get( 'choose' ) ); ?></option>
						<?php foreach ( $item['options'] as $option ) : ?>
							<?php $state = $spots['options'][ $option ] ?? null; ?>
							<option value="<?php echo esc_attr( $option ); ?>"<?php echo self::option_attributes( $state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in option_attributes(). ?> <?php selected( null !== $state && $state['sold_out'] ? '' : $value, $option ); ?>><?php echo esc_html( self::option_text( $option, $state ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			<?php else : ?>
				<input class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $types[ $item['type'] ] ?? 'type="text"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal attributes. ?><?php echo $required . $placeholder . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?> />
			<?php endif; ?>
			<?php self::print_help( $item['help'], $help_id ); ?>
		</div>
		<?php
	}

	/**
	 * A spots option's data for checkout.js, and disabled when it's gone.
	 *
	 * @param array<string, mixed>|null $state From option_state(), or null for an ordinary option.
	 * @phpstan-param OptionState|null $state
	 */
	private static function option_attributes( ?array $state ): string {
		if ( null === $state ) {
			return '';
		}

		$attributes = '';

		if ( '' !== $state['price'] ) {
			$attributes .= ' data-price="' . esc_attr( $state['price'] ) . '"';
		}

		if ( null !== $state['left'] ) {
			$attributes .= ' data-left="' . esc_attr( (string) $state['left'] ) . '"';
		}

		return $attributes . ( $state['sold_out'] ? ' disabled' : '' );
	}

	/**
	 * A dropdown option's text, with its price and badge, since an <option> can't hold markup.
	 *
	 * @param string                    $option The option's label.
	 * @param array<string, mixed>|null $state  From option_state(), or null.
	 * @phpstan-param OptionState|null $state
	 */
	private static function option_text( string $option, ?array $state ): string {
		if ( null === $state ) {
			return $option;
		}

		$text = '' !== $state['price'] ? sprintf( '%1$s · %2$s', $option, CheckoutService::format_money( (float) $state['price'] ) ) : $option;

		return '' !== $state['badge'] ? sprintf( '%1$s (%2$s)', $text, $state['badge'] ) : $text;
	}

	/**
	 * A field's help line, if it has one.
	 *
	 * @param string $help The text.
	 * @param string $id   Its id, for aria-describedby.
	 */
	private static function print_help( string $help, string $id ): void {
		if ( '' !== $help ) {
			echo '<span class="iftp-btn-form__help" id="' . esc_attr( $id ) . '">' . esc_html( $help ) . '</span>';
		}
	}

	/**
	 * The logos of the methods this button offers, so the payer knows what's coming.
	 *
	 * @param Button $button The button.
	 */
	private function print_method_logos( Button $button ): void {
		$logos = $this->offered_logos( $button );

		if ( array() === $logos ) {
			return;
		}
		?>
		<ul class="iftp-btn-form__methods" aria-label="<?php echo esc_attr( CheckoutTexts::get( 'methods' ) ); ?>">
			<?php foreach ( $logos as $method ) : ?>
				<li><img src="<?php echo esc_url( $method['logo'] ?? '' ); ?>"<?php echo self::dark_logo_attr( $method ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in dark_logo_attr(). ?> alt="<?php echo esc_attr( '' !== $method['alias'] ? $method['alias'] : $method['entity'] ); ?>" height="18" loading="lazy" /></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * The ` data-logo-dark="…"` method-logos.js swaps to on a dark background, or '' when ifthenpay has no dark logo.
	 *
	 * @param array<string, mixed> $method A method from the catalog.
	 */
	public static function dark_logo_attr( array $method ): string {
		$dark = (string) ( $method['logo_dark'] ?? '' );

		return '' !== $dark ? ' data-logo-dark="' . esc_url( $dark ) . '"' : '';
	}

	/**
	 * The methods this button offers that have a logo.
	 *
	 * @param Button $button The button.
	 * @return array<int, Method>
	 */
	private function offered_logos( Button $button ): array {
		$offered = array_column( ( new PaymentLinks( $this->settings ) )->button_methods( $button ), 'entity' );
		$logos   = array();

		foreach ( $this->settings->get_methods() as $method ) {
			if ( in_array( $method['entity'], $offered, true ) && '' !== (string) ( $method['logo'] ?? '' ) ) {
				$logos[] = $method;
			}
		}

		return $logos;
	}

	/**
	 * The input type and autocomplete hints for a field.
	 *
	 * @param string $field One of Button::FIELDS but note.
	 */
	private static function input_attributes( string $field ): string {
		$attributes = array(
			'name'  => 'type="text" autocomplete="name" maxlength="191"',
			'email' => 'type="email" autocomplete="email" inputmode="email" maxlength="191"',
			'phone' => 'type="tel" autocomplete="tel" inputmode="tel" maxlength="30"',
			'nif'   => 'type="text" autocomplete="off" inputmode="numeric" maxlength="20"',
		);

		return $attributes[ $field ] ?? 'type="text"';
	}

	/**
	 * The pay link for a button, for anything that wants to show it.
	 *
	 * @param Button $button The button.
	 */
	public static function pay_link( Button $button ): string {
		return ( new PayLinks() )->url( $button );
	}
}
