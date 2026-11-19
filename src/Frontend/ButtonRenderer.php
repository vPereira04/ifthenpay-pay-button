<?php
/**
 * Pay button and checkout markup, and the frontend assets.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Frontend;

use Ifthenpay\PayButton\Admin\SchemeControl;
use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Api\ReturnHandler;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Buttons\FieldIcons;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\CustomCode;
use Ifthenpay\PayButton\Checkout\RestController;
use Ifthenpay\PayButton\Checkout\SpotCounter;
use Ifthenpay\PayButton\Checkout\TestMode;
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
 * @phpstan-import-type FormCondition from FormFields
 * @phpstan-import-type FormRules from FormFields
 * @phpstan-import-type FormStep from FormFields
 * @phpstan-import-type SpotsLeft from SpotCounter
 * @phpstan-type OptionState array{price: string, left: int|null, badge: string, sold_out: bool}
 * @phpstan-type SpotContext array{field: string, left: SpotsLeft, max: int, options: array<string, OptionState>, extras: array<string, OptionState>}
 */
class ButtonRenderer {

	public const SCRIPT_HANDLE     = 'iftp-btn-checkout';
	public const STYLE_HANDLE      = 'iftp-btn-checkout';
	public const DROPDOWN_HANDLE   = 'iftp-btn-checkout-dropdown';
	public const DATEPICKER_HANDLE = 'iftp-btn-checkout-datepicker';
	// What a pay page of a button set to "My own code" loads instead of the checkout stylesheet.
	public const BASE_HANDLE = 'iftp-btn-checkout-base';

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
	 * Whether the stylesheet carries the page backgrounds and scenes.
	 *
	 * @var bool
	 */
	private bool $scenes = false;

	/**
	 * Whether the look CSS and the script data were added to the handles yet.
	 *
	 * @var array{style: bool, script: bool}
	 */
	private array $attached = array(
		'style'  => false,
		'script' => false,
	);

	/**
	 * The CSS and JavaScript of the buttons printed on this page, by button, each already scoped to its button.
	 *
	 * @var array<int, array{css: string, js: string, bare: bool}>
	 */
	private array $code = array();

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
		// I only build the look CSS and the script data on requests that actually print them.
		add_filter( 'print_styles_array', array( $this, 'attach_look' ) );
		add_filter( 'print_scripts_array', array( $this, 'attach_script_data' ) );
	}

	/**
	 * Registers the checkout script and stylesheet.
	 */
	public function register_assets(): void {
		$js = Plugin::asset_path( 'assets/js/checkout.js' );

		$this->register_sheet();
		wp_register_style( self::BASE_HANDLE, IFTP_BTN_URL . Plugin::asset_path( 'assets/css/checkout/base.css' ), array(), Plugin::asset_version( 'assets/css/checkout/base.css' ) );
		$dropdown_js = Plugin::asset_path( 'assets/js/checkout-dropdown.js' );
		wp_register_script( self::DROPDOWN_HANDLE, IFTP_BTN_URL . $dropdown_js, array(), Plugin::asset_version( $dropdown_js ), true );
		$datepicker_js = Plugin::asset_path( 'assets/js/checkout-datepicker.js' );
		wp_register_script( self::DATEPICKER_HANDLE, IFTP_BTN_URL . $datepicker_js, array(), Plugin::asset_version( $datepicker_js ), true );
		wp_register_script( self::SCRIPT_HANDLE, IFTP_BTN_URL . $js, array( self::DROPDOWN_HANDLE, self::DATEPICKER_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE, Plugin::OPEN_FX_HANDLE ), Plugin::asset_version( $js ), true );
	}

	/**
	 * Registers the checkout stylesheet. The page backgrounds and scenes (pay-page.css) are a third of it, so the
	 * frontend only gets them where a scene or the pay page needs them; the admin previews always do.
	 */
	private function register_sheet(): void {
		$parts = array( 'tokens', 'button-looks', 'button-effects', 'dialog-and-form', 'step-bar', 'fields-and-actions', 'pay-page', 'outcome-and-responsive' );

		if ( ! $this->scenes && ! is_admin() ) {
			$parts = array_values( array_diff( $parts, array( 'pay-page' ) ) );
		}

		Plugin::register_split_style( self::STYLE_HANDLE, 'assets/css/checkout', $parts );
	}

	/**
	 * Makes the stylesheet carry the page backgrounds too, for a scene, the pay page or a result popup of unknown look.
	 * Does nothing once the stylesheet has printed.
	 */
	public function with_scenes(): void {
		if ( $this->scenes || is_admin() || wp_style_is( self::STYLE_HANDLE, 'done' ) ) {
			return;
		}

		$this->scenes = true;

		foreach ( array( 'tokens', 'button-looks', 'button-effects', 'dialog-and-form', 'step-bar', 'fields-and-actions', 'pay-page', 'outcome-and-responsive' ) as $part ) {
			wp_deregister_style( 'outcome-and-responsive' === $part ? self::STYLE_HANDLE : self::STYLE_HANDLE . '-' . $part );
		}

		$this->register_sheet();
	}

	/**
	 * Adds the site's checkout look, then every button's own CSS after it, once the stylesheet is about to print.
	 *
	 * @param mixed $handles The styles about to print.
	 * @return mixed
	 */
	public function attach_look( $handles ) {
		if ( $this->attached['style'] || ! is_array( $handles ) ) {
			return $handles;
		}

		// The stylesheet, or the tiny base a pay page set to "My own code" loads instead.
		$handle = in_array( self::STYLE_HANDLE, $handles, true ) ? self::STYLE_HANDLE : ( in_array( self::BASE_HANDLE, $handles, true ) ? self::BASE_HANDLE : '' );

		if ( '' === $handle ) {
			return $handles;
		}

		$this->attached['style'] = true;
		$css                     = CheckoutLook::css( $this->checkout_settings->get_look() );

		wp_add_inline_style( $handle, $css . $this->code_css() );

		return $handles;
	}

	/**
	 * The CSS of every button taken in so far: the bare checkout's reset, then each button's own. The demo page, which has a renderer
	 * of its own that the style filter doesn't know, adds it itself.
	 */
	public function code_css(): string {
		$css = '';

		// A bare checkout drops the plugin's rules from the popup, which sits in the page next to other buttons' styled ones.
		if ( array() !== array_filter( array_column( $this->code, 'bare' ) ) ) {
			$bare = str_repeat( '[data-iftp-bare]', 5 );
			$css .= "\n" . $bare . ' .iftp-btn-sheet,' . $bare . ' .iftp-btn-sheet *{all:revert}' . "\n" . $bare . ' .iftp-btn-sheet [hidden]{display:none!important}';
		}

		foreach ( $this->code as $own ) {
			$css .= '' !== $own['css'] ? "\n" . $own['css'] : '';
		}

		return $css;
	}

	/**
	 * The scripts of every button taken in so far, each in its own scope.
	 */
	public function code_js(): string {
		return implode( "\n", array_column( $this->code, 'js' ) );
	}

	/**
	 * Takes in a button's own CSS and JavaScript, for the button that's about to print.
	 *
	 * @param Button $button The button.
	 * @return string The attributes its wrapper (or the pay page's <html>) carries, so the code finds it; '' when it has none.
	 */
	public function register_code( Button $button ): string {
		$design_code = ButtonDesign::SOURCE_CODE === $button->config['design']['source'];
		$page_code   = Button::LOOK_CODE === $button->config['look_mode'];

		if ( ! $design_code && ! $page_code ) {
			return '';
		}

		if ( ! isset( $this->code[ $button->id ] ) ) {
			$code  = $button->config['code'];
			$css   = ( $design_code ? $code['button_css'] : '' ) . ( $page_code ? "\n" . $code['page_css'] : '' );
			$js    = ( $design_code ? CustomCode::scope_js( $button->id, 'button', $code['button_js'] ) : '' ) . ( $page_code ? CustomCode::scope_js( $button->id, 'page', $code['page_js'] ) : '' );
			$scope = CustomCode::scope_selector( $button->id );

			// @keyframes can't sit inside the scope's braces, so they go ahead of it at the top level.
			list( $frames, $css ) = CustomCode::lift_keyframes( $button->id, $css );

			// Six times the attribute, so this beats the reset above and every rule of the plugin's own.
			$this->code[ $button->id ] = array(
				'css'  => ( '' !== $frames ? $frames . "\n" : '' ) . ( '' !== trim( $css ) ? ( $page_code ? str_repeat( $scope, 6 ) : $scope ) . '{' . $css . "\n}" : '' ),
				'js'   => $js,
				'bare' => $page_code,
			);
		}

		return ' data-iftp-code="' . (int) $button->id . '"' . ( $page_code ? ' data-iftp-bare' : '' );
	}

	/**
	 * A drawn button's CSS for its card on the Buttons screen, where the preview is a plain span: scoped like on the site, strong
	 * enough to beat the card's own sizing rules, and with pointer events back so hovering it plays its hover. Only CSS: a drawn
	 * button's script isn't run in a list, so it shows the CSS-only version, which is why drawn designs always carry one.
	 *
	 * @param Button $button The button.
	 * @return string The stylesheet, or '' for a button that isn't drawn.
	 */
	public static function preview_css( Button $button ): string {
		if ( ButtonDesign::SOURCE_CODE !== $button->config['design']['source'] || '' === trim( $button->config['code']['button_css'] ) ) {
			return '';
		}

		$scope                = CustomCode::scope_selector( $button->id );
		list( $frames, $css ) = CustomCode::lift_keyframes( $button->id, $button->config['code']['button_css'] );

		return ( '' !== $frames ? $frames . "\n" : '' ) . str_repeat( $scope, 4 ) . '{pointer-events:auto}' . "\n" . str_repeat( $scope, 4 ) . '{' . $css . "\n}";
	}

	/**
	 * A drawn button's script for its card on the Buttons screen, in the same scope as on the site, so the card plays what the
	 * button plays: its own CSS-only look is only the fallback. It is the code the editor's own demo frame already runs on this
	 * origin, and only a user who may post unfiltered HTML could save it.
	 *
	 * @param Button $button The button.
	 * @return string The script, or '' for a button that isn't drawn or has none.
	 */
	public static function preview_js( Button $button ): string {
		return ButtonDesign::SOURCE_CODE === $button->config['design']['source'] ? CustomCode::scope_js( $button->id, 'button', $button->config['code']['button_js'] ) : '';
	}

	/**
	 * Adds what checkout.js needs once the script is about to print.
	 *
	 * @param mixed $handles The scripts about to print.
	 * @return mixed
	 */
	public function attach_script_data( $handles ) {
		if ( ! $this->attached['script'] && is_array( $handles ) && in_array( self::SCRIPT_HANDLE, $handles, true ) ) {
			$this->attached['script'] = true;
			wp_localize_script( self::SCRIPT_HANDLE, 'iftpBtnCheckout', $this->script_data() );

			// Each button's own scripts run after checkout.js, in their own scope, only for their button.
			$own = $this->code_js();

			if ( '' !== trim( $own ) ) {
				wp_add_inline_script( self::SCRIPT_HANDLE, $own );
			}
		}

		return $handles;
	}

	/**
	 * The outcome popup has to show on whatever page the payer lands on, even one with no button.
	 */
	public function maybe_enqueue_for_result(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to load the script; the status route checks the signature.
		if ( isset( $_GET[ ReturnHandler::RESULT_VAR ] ) ) {
			// The popup that shows the result takes the look of a button I can't tell from here.
			$this->with_scenes();
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
		$look         = $this->checkout_settings->get_look();

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
			'popupIcon'   => $look['popup_icon'],
			'scene'       => CheckoutLook::scene_classes( $look ),
			'dark'        => '' !== CheckoutLook::dark_class( $look ),
			'sceneVideo'  => CheckoutLook::scene_video( $look ),
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
				'close'       => CheckoutTexts::get( 'close' ),
				'number'      => array(
					'whole' => CheckoutTexts::get( 'number_whole' ),
					'min'   => CheckoutTexts::get( 'number_min' ),
					'max'   => CheckoutTexts::get( 'number_max' ),
				),
				'titles'      => $confirmation['titles'],
			),
		);
	}

	/**
	 * The own look of the button a returning payer paid with, or null for the site's look.
	 *
	 * @return array{style: string, icon: string, font: string, scene: string, dark: bool, video: array{kind: string, src: string, start: int|null, end: int|null}|null}|null
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
	 * @param array<string, mixed> $args   Optional: label, button_class, button_style, wrapper (attributes string for the wrapper), demo (true on the demo page: it opens the checkout whatever the button's state; a saved button takes a payment marked as a test, an unsaved one none).
	 */
	public function render_button( Button $button, array $args = array() ): string {
		self::enqueue();

		$demo      = ! empty( $args['demo'] );
		$available = $demo ? true : ( new CheckoutService( $this->settings ) )->availability( $button );
		$design    = ButtonDesign::for_button( $button, $this->checkout_settings );
		$label     = $button->display_label( is_string( $args['label'] ?? null ) ? $args['label'] : '' );
		$look      = ButtonDesign::attributes( $design );
		$inner     = ButtonDesign::inner_html( $design, $label );
		$wrapper   = ( is_string( $args['wrapper'] ?? null ) ? $args['wrapper'] : 'class="iftp-btn-pay wp-block-button"' ) . $this->register_code( $button );
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
		// A saved button on the demo page takes a real payment, marked as a test; an unsaved one can't, as the checkout reads the saved button.
		$test = $demo && $button->id > 0 && current_user_can( Plugin::admin_cap() ) ? TestMode::token( $button->id ) : '';

		ob_start();
		?>
		<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- see above. ?>>
			<?php echo '' !== $logos ? '<span class="iftp-btn-pay__group">' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup. ?>
			<button type="button" class="<?php echo esc_attr( $classes ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above. ?> aria-haspopup="dialog" data-iftp-btn-open="<?php echo esc_attr( $dialog_id ); ?>"><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ButtonDesign::inner_html() escapes the text; the icon is a fixed SVG. ?></button>
			<?php echo '' !== $logos ? $logos . '</span>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in logo_strip(); the span closes the group. ?>
			<?php
			$own   = CheckoutLook::inline_style( $button );
			$look  = CheckoutLook::for_button( $button, $this->checkout_settings );
			$scene = trim( CheckoutLook::scene_classes( $look ) . ' ' . CheckoutLook::dark_class( $look ) );

			if ( $look['scene'] || $this->checkout_settings->get_look()['scene'] ) {
				$this->with_scenes();
			}
			?>
			<dialog class="iftp-btn-sheet <?php echo esc_attr( CheckoutLook::anim_class( $look ) ); ?><?php echo '' !== $own ? ' iftp-btn-font--' . esc_attr( $button->config['look']['font'] ) : ''; ?><?php echo '' !== $scene ? ' ' . esc_attr( $scene ) : ''; ?>" id="<?php echo esc_attr( $dialog_id ); ?>" aria-labelledby="<?php echo esc_attr( $dialog_id ); ?>-title"<?php echo '' !== $own ? ' style="' . esc_attr( $own ) . '"' : ''; ?>>
				<?php
				if ( $look['scene'] ) {
					echo CheckoutLook::video_markup( $look, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in video_markup().
				}

				$this->print_form( $button, $dialog_id, '' !== $test ? array( TestMode::FIELD => $test ) : array(), true, $demo && '' === $test, $demo ? __( 'This button isn\'t saved yet, so the demo can\'t take a payment. Save it to try a test payment.', 'ifthenpay-pay-button' ) : '', $demo );
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
			$html .= '<img src="' . esc_url( $method['logo'] ?? '' ) . '"' . self::dark_logo_attr( $method ) . ' alt="' . esc_attr( '' !== $method['alias'] ? $method['alias'] : $method['entity'] ) . '" height="14" loading="lazy" />';
		}

		// The button's own colour, so the caption can take it on hover; a theme button has none and gets the checkout brand.
		$accent = preg_match( '/--iftp-pb-color:(#[0-9a-f]{3,8})(?:;|$)/i', ButtonDesign::attributes( ButtonDesign::for_button( $button, $this->checkout_settings ) )['style'], $found ) ? ' style="--iftp-pb-color:' . esc_attr( $found[1] ) . '"' : '';

		return '<span class="iftp-btn-pay__logos"' . $accent . '><span class="iftp-btn-pay__logos-label">' . esc_html( CheckoutTexts::get( 'pay_with' ) ) . '</span><span class="iftp-btn-pay__logos-list">' . $html . '</span></span>';
	}

	/**
	 * The checkout form for one button: a fixed header (and step bar), a body that scrolls on its own, and
	 * fixed actions. Page breaks in the button's form split the body into steps; checkout.js walks them.
	 *
	 * @param Button               $button  The button.
	 * @param string               $id      Unique id prefix for this form.
	 * @param array<string, mixed> $prefill What a pay link carries, from PayLinks::read_query().
	 * @param bool                 $closable Whether it sits in a dialog with a close button.
	 * @param bool                 $preview  Whether it's a preview (the AI studio's, or the demo page): checkout.js shows a note instead of paying.
	 * @param string               $note     That note, when the default doesn't fit.
	 * @param bool                 $scheme   Whether the header carries the Light / System / Dark switch: only the demo page, never a payer's checkout.
	 */
	public function print_form( Button $button, string $id, array $prefill, bool $closable, bool $preview = false, string $note = '', bool $scheme = false ): void {
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
			'priced'  => $per_spot,
			'visible' => FormFields::visibility( $button->config['form'], FormFields::answers( $button->config['form'], $fields ) ),
		);
		?>
		<form class="iftp-btn-form<?php echo $count > 1 ? ' iftp-btn-form--steps' : ''; ?><?php echo in_array( $button->config['field_icon_style'] ?? '', array( 'filled', 'duotone' ), true ) ? ' iftp-btn-form--icons-' . esc_attr( $button->config['field_icon_style'] ) : ''; ?>" method="post" novalidate data-iftp-btn-form data-button="<?php echo esc_attr( (string) $button->id ); ?>" data-skip="<?php echo esc_attr( $button->config['skip_form'] ? '1' : '0' ); ?>"<?php echo $per_spot ? ' data-unit="' . esc_attr( number_format( $button->config['amount'], 2, '.', '' ) ) . '"' : ''; ?><?php echo $preview ? ' data-preview="' . esc_attr( '' !== $note ? $note : __( 'This is a preview, so it doesn\'t take payments. Save the button to try it for real.', 'ifthenpay-pay-button' ) ) . '"' : ''; ?>>
			<?php if ( ! $closable && $look['pay_logo'] && 'inside' === $look['pay_mark_place'] ) : ?>
				<div class="iftp-btn-form__mark"><?php echo PayPage::site_mark( $look ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in site_mark(). ?></div>
			<?php endif; ?>
			<header class="iftp-btn-form__header<?php echo $scheme ? ' iftp-btn-form__header--tools' : ''; ?>">
				<span class="iftp-btn-form__site"><?php echo esc_html( $site ); ?></span>
				<?php
				if ( $scheme ) {
					SchemeControl::render( 'iftp-btn-form__scheme' );
				}
				?>
				<?php if ( $closable ) : ?>
					<button type="button" class="iftp-btn-form__close" data-iftp-btn-close aria-label="<?php echo esc_attr( CheckoutTexts::get( 'close' ) ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>
					</button>
				<?php endif; ?>
			</header>

			<?php if ( $count > 1 ) : ?>
				<?php
				StepBar::render(
					array_column( $steps, 'title' ),
					$look['steps'],
					array(
						'id'   => $id,
						'live' => true,
					)
				);
				?>
			<?php endif; ?>

			<div class="iftp-btn-form__body" data-iftp-btn-body>
				<?php foreach ( $steps as $index => $step ) : ?>
					<div class="iftp-btn-form__step" data-iftp-btn-step="<?php echo esc_attr( (string) $index ); ?>"<?php echo $count > 1 ? ' role="group" aria-label="' . esc_attr( $step['title'] ) . '"' : ''; ?><?php echo $index > 0 ? ' hidden' : ''; ?><?php echo self::step_attributes( $step, $context['visible'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in step_attributes(). ?>>
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
				<?php if ( is_string( $prefill[ TestMode::FIELD ] ?? null ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( TestMode::FIELD ); ?>" value="<?php echo esc_attr( $prefill[ TestMode::FIELD ] ); ?>" />
				<?php endif; ?>
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
	 * One item of the button's form: the amount block, the payer details, or one of the merchant's own items.
	 *
	 * @param Button               $button  The button.
	 * @param array<string, mixed> $item    The item.
	 * @param array<string, mixed> $context The form's id, prefill and amount state.
	 * @phpstan-param FormItem $item
	 * @phpstan-param array{id: string, fields: array<string, mixed>, amount: string, locked: bool, shown: string, priced: bool, spots: SpotContext, visible: array<string, bool>} $context
	 */
	private function print_item( Button $button, array $item, array $context ): void {
		$id   = $context['id'];
		$tags = LiveTags::ids( $button->config['form'] );
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
				<p class="iftp-btn-form__heading"><?php echo LiveTags::html( $item['label'], $tags ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html(). ?></p>
				<?php if ( '' !== $item['help'] ) : ?>
					<p class="iftp-btn-form__help"><?php echo nl2br( LiveTags::html( $item['help'], $tags ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html(), then line breaks. ?></p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		// Only a fixed amount adds up; the other modes charge what the payer typed.
		$item['prices'] = $context['priced'] ? $item['prices'] : array();

		// What's left on this field's own options, by label.
		$prefix = FormFields::spot_key( $item['id'], '' );
		$own    = array();

		foreach ( $context['spots']['extras'] as $key => $state ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				$own[ substr( $key, strlen( $prefix ) ) ] = $state;
			}
		}

		$this->print_custom( $item, $id, $context['fields'], $context['spots']['field'] === $item['id'] ? $context['spots'] : null, $when, $own, $tags );
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

		return ' data-iftp-btn-when="' . esc_attr( self::rule_json( $item['when'] ) ) . '"' . ( $shown ? '' : ' hidden' );
	}

	/**
	 * A page's own rule, and a mark when the answers so far skip it. The step is shown or hidden by checkout.js as the payer
	 * moves through, so it isn't hidden here, and a skipped one is left out of the walk.
	 *
	 * @param array<string, mixed> $step    The step.
	 * @phpstan-param FormStep $step
	 * @param array<string, bool>  $visible Which items show for the prefilled answers.
	 */
	private static function step_attributes( array $step, array $visible ): string {
		if ( '' === $step['when']['field'] ) {
			return '';
		}

		return ' data-iftp-btn-step-when="' . esc_attr( self::rule_json( $step['when'] ) ) . '"' . ( ( $visible[ $step['id'] ] ?? true ) ? '' : ' data-iftp-btn-skip="1"' );
	}

	/**
	 * A condition as checkout.js reads it, its field as the input's name.
	 *
	 * @param array<string, mixed> $when The condition.
	 * @phpstan-param FormCondition $when
	 */
	private static function rule_json( array $when ): string {
		return (string) wp_json_encode(
			array(
				'field'  => FormFields::input_name( $when['field'] ),
				'op'     => $when['op'],
				'values' => $when['values'],
			)
		);
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

		// The options of the other fields that have spots, by what they're counted under.
		$extras = array();

		foreach ( array_keys( Spots::extra_limits( $button ) ) as $key ) {
			$extras[ $key ] = self::option_state( $button, '', $left['extras'][ $key ] ?? null, false );
		}

		return array(
			'field'   => Spots::MODE_OPTIONS === $spots['mode'] ? $spots['field'] : '',
			'left'    => $left,
			'max'     => null !== $left['total'] ? max( 1, min( $spots['max_each'], $left['total'] ) ) : $spots['max_each'],
			'options' => $options,
			'extras'  => $extras,
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
					<?php echo FieldIcons::svg( $button->config['field_icons'][ $field ] ?? '' ) . esc_html( $labels[ $field ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in svg() and here. ?>
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
	 * @param array<string, mixed>      $own    What's left on this field's own options with spots, by label.
	 * @param array<int, string>        $tags   The ids a live tag can name.
	 * @phpstan-param FormItem $item
	 * @phpstan-param SpotContext|null $spots
	 * @phpstan-param array<string, OptionState> $own
	 */
	private function print_custom( array $item, string $id, array $fields, ?array $spots = null, string $when = '', array $own = array(), array $tags = array() ): void {
		// The main Spots field's options carry their own prices; only the other fields add theirs.
		$item['prices'] = null === $spots ? $item['prices'] : array();

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
					<span><?php echo FormFields::linked_label( $item['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped piece by piece in linked_label(). ?></span>
				</label>
				<?php self::print_help( $item['help'], $help_id, $tags ); ?>
			</div>
			<?php
			return;
		}

		if ( $choices ) {
			$picked = 'checkboxes' === $item['type'] ? array_map( 'trim', explode( ',', $value ) ) : array( $value );
			$images = FormFields::option_images( $item );
			// With pictures on, every option is a card; one without a picture gets a tile.
			$cards = $item['pictures'] && in_array( $item['type'], FormFields::IMAGE_TYPES, true );
			$shape = FormFields::picture_shape( $item, $images );
			// The cards' layout: a grid or a sideways row, how many to a line (0 fits as many as the size allows) and how big.
			$cols_in_line = $cards ? (int) $item['picture_cols'] : 0;
			$layout       = $cards ? ' iftp-btn-form__choices--' . $item['picture_layout'] . ' iftp-btn-form__choices--size-' . $item['picture_size']
				. ( $cols_in_line > 0 ? ' iftp-btn-form__choices--cols-' . $cols_in_line : '' )
				// A row with more cards than fit lets the next one peek in, so it's clear it scrolls.
				. ( 'scroll' === $item['picture_layout'] && count( $item['options'] ) > max( 1, $cols_in_line ) ? ' iftp-btn-form__choices--peek' : '' ) : '';
			?>
			<fieldset class="<?php echo esc_attr( $classes ); ?>"<?php echo $item['required'] && 'checkboxes' === $item['type'] ? ' data-iftp-btn-required-group' : ''; ?><?php echo $describe . $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above and in when_attributes(). ?>>
				<legend class="iftp-btn-form__label"><?php echo FieldIcons::svg( $item['icon'] ) . LiveTags::html( $item['label'], $tags ) . $optional; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $optional is escaped where it's built. ?></legend>
				<div class="iftp-btn-form__choices<?php echo $cards ? ' iftp-btn-form__choices--cards' : ''; ?><?php echo esc_attr( $layout ); ?><?php echo $cards && $shape['whole'] ? ' iftp-btn-form__choices--whole' : ''; ?>"<?php echo $cards ? ' style="--iftp-pic-ratio:' . esc_attr( (string) $shape['ratio'] ) . '"' : ''; ?>>
					<?php foreach ( $item['options'] as $index => $option ) : ?>
						<?php $state = $spots['options'][ $option ] ?? $own[ $option ] ?? null; ?>
						<label class="iftp-btn-form__choice<?php echo $cards ? ' iftp-btn-form__choice--card' : ''; ?><?php echo null !== $state && $state['sold_out'] ? ' is-sold-out' : ''; ?>">
							<?php // A sold-out first option can't carry the group's required, so the spots field puts it on every option. ?>
							<input type="<?php echo 'radio' === $item['type'] ? 'radio' : 'checkbox'; ?>" class="iftp-btn-form__tick" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $option ); ?>"<?php echo 'radio' === $item['type'] && ( 0 === $index || null !== $spots || array() !== $own ) ? $required : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal. ?><?php echo self::option_attributes( $state, $item['prices'][ $option ] ?? 0.0, ! isset( $spots['options'][ $option ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in option_attributes(). ?> <?php checked( null !== $state && $state['sold_out'] ? false : in_array( $option, $picked, true ) ); ?> />
							<?php if ( $cards ) : ?>
								<span class="iftp-btn-form__choice-media<?php echo isset( $images[ $option ] ) ? '' : ' is-empty'; ?>">
									<?php if ( isset( $images[ $option ] ) ) : ?>
										<?php // A magnifier in the corner opens the picture larger; a span, since a button can't live inside a label. checkout.js wires it. ?>
										<span class="iftp-btn-form__zoom" role="button" tabindex="0" data-iftp-zoom="<?php echo esc_url( $images[ $option ]['large'] ); ?>" data-iftp-zoom-alt="<?php echo esc_attr( '' !== $images[ $option ]['alt'] ? $images[ $option ]['alt'] : $option ); ?>" aria-label="<?php /* translators: %s: an option's name */ echo esc_attr( sprintf( __( 'Enlarge the picture of %s', 'ifthenpay-pay-button' ), $option ) ); ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m20 20-4.9-4.9M10.5 8v5M8 10.5h5"/></svg></span>
										<img src="<?php echo esc_url( $images[ $option ]['url'] ); ?>" alt="<?php echo esc_attr( '' !== $images[ $option ]['alt'] ? $images[ $option ]['alt'] : $option ); ?>" width="<?php echo esc_attr( (string) $images[ $option ]['width'] ); ?>" height="<?php echo esc_attr( (string) $images[ $option ]['height'] ); ?>" loading="lazy" decoding="async" />
									<?php else : ?>
										<span class="iftp-btn-form__choice-initial" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $option, 0, 1 ) ) ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
							<span class="iftp-btn-form__choice-text"><?php echo esc_html( $option ); ?></span>
							<?php $extra = $item['prices'][ $option ] ?? 0.0; ?>
							<?php if ( ( null !== $state && ( '' !== $state['price'] || '' !== $state['badge'] ) ) || $extra > 0 ) : ?>
								<span class="iftp-btn-form__choice-meta">
									<?php if ( null !== $state && '' !== $state['badge'] ) : ?>
										<span class="iftp-btn-form__badge<?php echo $state['sold_out'] ? ' iftp-btn-form__badge--out' : ''; ?>"><?php echo esc_html( $state['badge'] ); ?></span>
									<?php endif; ?>
									<?php if ( null !== $state && '' !== $state['price'] ) : ?>
										<span class="iftp-btn-form__choice-price"><?php echo esc_html( CheckoutService::format_money( (float) $state['price'] ) ); ?></span>
									<?php elseif ( $extra > 0 ) : ?>
										<span class="iftp-btn-form__choice-price"><?php echo esc_html( '+' . CheckoutService::format_money( $extra ) ); ?></span>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<?php self::print_help( $item['help'], $help_id, $tags ); ?>
			</fieldset>
			<?php
			return;
		}

		$placeholder = '' !== $item['placeholder'] ? ' placeholder="' . esc_attr( $item['placeholder'] ) . '"' : '';
		$rules       = $item['rules'];
		$types       = array(
			'text'   => 'type="text"' . self::length_attributes( $rules, 191 ),
			'email'  => 'type="email" inputmode="email" autocomplete="email" maxlength="191"',
			'tel'    => 'type="tel" inputmode="tel" autocomplete="tel"' . self::length_attributes( $rules, 30 ),
			'number' => 'type="text" inputmode="' . ( $rules['whole'] ? 'numeric' : 'decimal' ) . '" maxlength="30" pattern="-?[0-9]+([.,][0-9]+)?"' . self::number_attributes( $item ),
			'date'   => 'type="date"' . self::date_attributes( $item['placeholder'] ),
		);
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $when; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in when_attributes(). ?>>
			<label class="iftp-btn-form__label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo FieldIcons::svg( $item['icon'] ) . LiveTags::html( $item['label'], $tags ) . $optional; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $optional is escaped where it's built. ?></label>
			<?php if ( 'textarea' === $item['type'] ) : ?>
				<textarea class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="3"<?php echo self::length_attributes( $rules, 1000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- numbers only. ?><?php echo $required . $placeholder . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php elseif ( 'select' === $item['type'] ) : ?>
				<span class="iftp-btn-form__select">
					<select class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?>>
						<option value=""><?php echo esc_html( '' !== $item['placeholder'] ? $item['placeholder'] : CheckoutTexts::get( 'choose' ) ); ?></option>
						<?php foreach ( $item['options'] as $option ) : ?>
							<?php
							$state = $spots['options'][ $option ] ?? $own[ $option ] ?? null;
							$parts = self::option_parts( $state, $item['prices'][ $option ] ?? 0.0 );
							?>
							<option value="<?php echo esc_attr( $option ); ?>" data-label="<?php echo esc_attr( $option ); ?>" data-meta="<?php echo esc_attr( $parts['price'] ); ?>" data-badge="<?php echo esc_attr( $parts['badge'] ); ?>"<?php echo self::option_attributes( $state, $item['prices'][ $option ] ?? 0.0, ! isset( $spots['options'][ $option ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in option_attributes(). ?> <?php selected( null !== $state && $state['sold_out'] ? '' : $value, $option ); ?>><?php echo esc_html( self::option_text( $option, $state, $item['prices'][ $option ] ?? 0.0 ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			<?php else : ?>
				<input class="iftp-btn-form__input" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo $types[ $item['type'] ] ?? 'type="text"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal attributes. ?><?php echo $required . $placeholder . $describe; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal, and escaped above. ?> />
			<?php endif; ?>
			<?php self::print_help( $item['help'], $help_id, $tags ); ?>
		</div>
		<?php
	}

	/**
	 * A spots option's data for checkout.js, and disabled when it's gone.
	 *
	 * @param array<string, mixed>|null $state From option_state(), or null for an ordinary option.
	 * @param float                     $extra What an option adds to the amount.
	 * @param bool                      $other Whether it's on a field other than the main Spots field.
	 * @phpstan-param OptionState|null $state
	 */
	private static function option_attributes( ?array $state, float $extra = 0.0, bool $other = false ): string {
		$attributes = $extra > 0 ? ' data-extra="' . esc_attr( number_format( $extra, 2, '.', '' ) ) . '"' : '';

		if ( null === $state ) {
			return $attributes;
		}

		if ( '' !== $state['price'] ) {
			$attributes .= ' data-price="' . esc_attr( $state['price'] ) . '"';
		}

		if ( null !== $state['left'] ) {
			// The other fields' spots are counted apart from the main Spots field's, so checkout.js reads them separately.
			$attributes .= ' data-' . ( $other ? 'extra-left' : 'left' ) . '="' . esc_attr( (string) $state['left'] ) . '"';
		}

		return $attributes . ( $state['sold_out'] ? ' disabled' : '' );
	}

	/**
	 * The order the site writes a date in, from its date format: "month,day,year" for "F j, Y", "day,month,year" for "d/m/Y".
	 */
	private static function date_order(): string {
		$format = (string) get_option( 'date_format' );
		$order  = array();
		$length = strlen( $format );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $format[ $i ];

			if ( '\\' === $char ) {
				++$i;
				continue;
			}

			$part = 'year';

			if ( false !== strpos( 'dj', $char ) ) {
				$part = 'day';
			} elseif ( false !== strpos( 'mnMF', $char ) ) {
				$part = 'month';
			} elseif ( false === strpos( 'yY', $char ) ) {
				continue;
			}

			if ( ! in_array( $part, $order, true ) ) {
				$order[] = $part;
			}
		}

		return 3 === count( $order ) ? implode( ',', $order ) : 'month,day,year';
	}

	/**
	 * A text field's shortest and longest, as the browser's minlength and maxlength. The longest never goes past what the server keeps.
	 *
	 * @param array<string, mixed> $rules The field's limits.
	 * @phpstan-param FormRules $rules
	 * @param int                  $cap   The most the field takes with no limit set.
	 */
	private static function length_attributes( array $rules, int $cap ): string {
		$max = $rules['max_len'] > 0 ? min( $rules['max_len'], $cap ) : $cap;

		return ( $rules['min_len'] > 0 ? ' minlength="' . (int) $rules['min_len'] . '"' : '' ) . ' maxlength="' . (int) $max . '"';
	}

	/**
	 * A Number's limits for checkout.js, which holds the payer to them with the field's own label in the message.
	 *
	 * @param array<string, mixed> $item The field.
	 * @phpstan-param FormItem $item
	 */
	private static function number_attributes( array $item ): string {
		$rules = $item['rules'];

		if ( '' === $rules['min'] && '' === $rules['max'] && ! $rules['whole'] ) {
			return '';
		}

		return ' data-iftp-btn-number data-label="' . esc_attr( $item['label'] ) . '"'
			. ( '' !== $rules['min'] ? ' data-min="' . esc_attr( $rules['min'] ) . '"' : '' )
			. ( '' !== $rules['max'] ? ' data-max="' . esc_attr( $rules['max'] ) . '"' : '' )
			. ( $rules['whole'] ? ' data-whole="1"' : '' );
	}

	/**
	 * What the date picker (checkout-datepicker.js) needs on the input: its words, and the day the week starts on.
	 *
	 * @param string $placeholder The field's own placeholder, if it has one.
	 */
	private static function date_attributes( string $placeholder ): string {
		$words = array(
			'placeholder' => '' !== $placeholder ? $placeholder : __( 'Type or pick a date', 'ifthenpay-pay-button' ),
			'pick'        => __( 'Choose a date', 'ifthenpay-pay-button' ),
			'today'       => __( 'Today', 'ifthenpay-pay-button' ),
			'clear'       => __( 'Clear', 'ifthenpay-pay-button' ),
			'prev-month'  => __( 'Previous month', 'ifthenpay-pay-button' ),
			'next-month'  => __( 'Next month', 'ifthenpay-pay-button' ),
			'prev-year'   => __( 'Previous year', 'ifthenpay-pay-button' ),
			'next-year'   => __( 'Next year', 'ifthenpay-pay-button' ),
			'prev-years'  => __( 'Previous years', 'ifthenpay-pay-button' ),
			'next-years'  => __( 'Next years', 'ifthenpay-pay-button' ),
			'week-start'  => (string) (int) get_option( 'start_of_week', 0 ),
			'order'       => self::date_order(),
			'mm'          => __( 'MM', 'ifthenpay-pay-button' ),
			'dd'          => __( 'DD', 'ifthenpay-pay-button' ),
			'yyyy'        => __( 'YYYY', 'ifthenpay-pay-button' ),
		);
		$html  = '';

		foreach ( $words as $name => $text ) {
			$html .= ' data-' . $name . '="' . esc_attr( $text ) . '"';
		}

		return $html;
	}

	/**
	 * A dropdown option's text, with its price and badge, since an <option> can't hold markup.
	 *
	 * @param string                    $option The option's label.
	 * @param array<string, mixed>|null $state  From option_state(), or null.
	 * @param float                     $extra  What it adds to the amount when it has no price of its own.
	 * @phpstan-param OptionState|null $state
	 */
	private static function option_text( string $option, ?array $state, float $extra = 0.0 ): string {
		$parts = self::option_parts( $state, $extra );
		$text  = '' !== $parts['price'] ? sprintf( '%1$s · %2$s', $option, $parts['price'] ) : $option;

		return '' !== $parts['badge'] ? sprintf( '%1$s (%2$s)', $text, $parts['badge'] ) : $text;
	}

	/**
	 * The price and badge of a dropdown option, apart, so the dropdown's list can draw them as markup.
	 *
	 * @param array<string, mixed>|null $state From option_state(), or null.
	 * @param float                     $extra What it adds to the amount when it has no price of its own.
	 * @return array{price: string, badge: string}
	 * @phpstan-param OptionState|null $state
	 */
	private static function option_parts( ?array $state, float $extra = 0.0 ): array {
		return array(
			'price' => null !== $state && '' !== $state['price'] ? CheckoutService::format_money( (float) $state['price'] ) : ( $extra > 0 ? '+' . CheckoutService::format_money( $extra ) : '' ),
			'badge' => null !== $state ? $state['badge'] : '',
		);
	}

	/**
	 * A field's help line, if it has one.
	 *
	 * @param string $help The text.
	 * @param string $id   Its id, for aria-describedby.
	 * @param array<int, string> $tags The ids a live tag can name.
	 */
	private static function print_help( string $help, string $id, array $tags = array() ): void {
		if ( '' !== $help ) {
			echo '<span class="iftp-btn-form__help" id="' . esc_attr( $id ) . '">' . LiveTags::html( $help, $tags ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
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
		$links   = new PaymentLinks( $this->settings );
		$offered = array_column( $links->button_methods( $button ), 'entity' );
		$logos   = array();

		foreach ( $this->settings->get_methods( $links->gateway_key_for( $button ) ) as $method ) {
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
