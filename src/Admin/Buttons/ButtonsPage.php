<?php
/**
 * The Buttons screen: the list and the editor.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\AiPanel;
use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\GatewaySettingsPage;
use Ifthenpay\PayButton\Admin\LookControls;
use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\Shortcode;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Reports\CheckoutOpens;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a merchant makes buttons. The list shows how each one is doing and how to place it; the editor is one
 * form, saved with a plain POST, with a live preview and everything needed to share it beside it.
 */
class ButtonsPage {

	public const PAGE_SLUG = 'iftp-btn';
	public const EDIT_VAR  = 'button';

	public const SAVE_ACTION      = 'iftp_btn_save_button';
	public const DUPLICATE_ACTION = 'iftp_btn_duplicate_button';
	public const DELETE_ACTION    = 'iftp_btn_delete_button';
	public const AJAX_NONCE       = 'iftp_btn_buttons';

	// From this share of spots taken, a card reads "Almost full".
	private const ALMOST_FULL = 0.8;

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Gateway settings.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * The screen's hook suffix, once the menu adds it.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Checkout settings, for the default button design.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout_settings;

	/**
	 * Sets up the screen.
	 *
	 * @param ButtonRepository   $buttons      Buttons storage.
	 * @param SettingsRepository $settings     Gateway settings.
	 * @param TransactionStore   $transactions Transactions storage.
	 */
	public function __construct( ButtonRepository $buttons, SettingsRepository $settings, TransactionStore $transactions ) {
		$this->buttons           = $buttons;
		$this->settings          = $settings;
		$this->transactions      = $transactions;
		$this->checkout_settings = new CheckoutSettings();
	}

	/**
	 * Hooks the assets.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Remembers the hook suffix, so the assets only load here.
	 *
	 * @param string $hook The hook suffix add_menu_page() returned.
	 */
	public function set_hook( string $hook ): void {
		$this->hook = $hook;
	}

	/**
	 * The list, or one button's editor (0 for a new one).
	 *
	 * @param int $id The button's post ID, or 0 for a new one.
	 */
	public static function edit_url( int $id ): string {
		return add_query_arg(
			array(
				'page'         => self::PAGE_SLUG,
				self::EDIT_VAR => $id > 0 ? (string) $id : 'new',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The list's URL.
	 *
	 * @param array<string, string> $args Extra query args, e.g. a notice.
	 */
	public static function list_url( array $args = array() ): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ) + $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Enqueues the screen's script and both stylesheets (the frontend one drives the live preview).
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		$js  = 'assets/js/buttons.js';
		$css = 'assets/css/buttons.css';

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_style( 'iftp-btn-buttons', IFTP_BTN_URL . $css, array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( $css ) );
		wp_enqueue_style( ButtonRenderer::STYLE_HANDLE );
		wp_add_inline_style( 'iftp-btn-buttons', self::theme_button_css() . LookControls::swatch_css() );
		// A button's own pay page background image and video come from the Media Library.
		wp_enqueue_media();
		wp_enqueue_script( 'iftp-btn-buttons', IFTP_BTN_URL . $js, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE ), Plugin::asset_version( $js ), true );

		$studio = $this->requested_studio();

		if ( false !== $studio ) {
			( new AiStudioPage() )->enqueue( $studio );
		} else {
			// The list's and the editor's AI cards wear the studio's look.
			wp_enqueue_style( AiPanel::STYLE_HANDLE, IFTP_BTN_URL . 'assets/css/ai.css', array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( 'assets/css/ai.css' ) );
		}

		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnButtons',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::AJAX_NONCE ),
				'presets'      => ButtonDesign::presets(),
				'pill'         => ButtonDesign::PILL,
				'gradientTurn' => ButtonDesign::GRADIENT_TURN,
				'lookPresets'  => CheckoutLook::presets(),
				'lookWidths'   => CheckoutLook::WIDTHS,
				'siteLook'     => self::site_look_for_js(),
				'siteDesign'   => $this->checkout_settings->get_button_design(),
				'sample'       => CheckoutSettingsPage::sample_values(),
				'i18n'         => array(
					'copied'      => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed'  => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					/* translators: %s: the button's title */
					'deleteNamed' => __( 'Delete “%s”?', 'ifthenpay-pay-button' ),
					'sending'     => __( 'Sending…', 'ifthenpay-pay-button' ),
					'error'       => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
					'payNow'      => CheckoutTexts::get( 'pay_now' ),
					'between'     => CheckoutTexts::get( 'between' ),
					/* translators: 1: the button text, 2: the amount */
					'withAmount'  => __( '%1$s · %2$s', 'ifthenpay-pay-button' ),
					'from'        => CheckoutTexts::get( 'from' ),
					'spots'       => array(
						'spots'      => __( 'Spots', 'ifthenpay-pay-button' ),
						'price'      => __( 'Price', 'ifthenpay-pay-button' ),
						'unlimited'  => __( 'No limit', 'ifthenpay-pay-button' ),
						'samePrice'  => __( 'Button amount', 'ifthenpay-pay-button' ),
						/* translators: %d: how many spots the options have together */
						'sum'        => __( 'The options add up to %d spots.', 'ifthenpay-pay-button' ),
						'shareTotal' => __( 'Options with no number of their own share the total spots.', 'ifthenpay-pay-button' ),
						'noLimit'    => __( 'Options with no number of their own have no limit.', 'ifthenpay-pay-button' ),
						'noField'    => __( 'No choice field in the checkout form yet', 'ifthenpay-pay-button' ),
						'needField'  => __( 'Add a Dropdown, Single choice or Multiple choice field to the checkout form, then pick it here.', 'ifthenpay-pay-button' ),
						'needTotal'  => __( 'Enter how many spots there are.', 'ifthenpay-pay-button' ),
					),
					'turnedOn'    => __( 'Button turned on. It takes payments again.', 'ifthenpay-pay-button' ),
					'turnedOff'   => __( 'Button turned off. It shows disabled and its pay page is gone.', 'ifthenpay-pay-button' ),
					'pill'        => __( 'Pill', 'ifthenpay-pay-button' ),
					/* translators: %d: corner radius in pixels */
					'px'          => __( '%d px', 'ifthenpay-pay-button' ),
					'unsaved'     => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
					'pickImage'   => __( 'Pay page background', 'ifthenpay-pay-button' ),
					'useImage'    => __( 'Use this image', 'ifthenpay-pay-button' ),
					'pickVideo'   => __( 'Pay page video', 'ifthenpay-pay-button' ),
					'useVideo'    => __( 'Use this video', 'ifthenpay-pay-button' ),
					'fb'          => array(
						'required'      => __( 'Required', 'ifthenpay-pay-button' ),
						'optional'      => __( 'Optional', 'ifthenpay-pay-button' ),
						'optionalWord'  => CheckoutTexts::get( 'optional' ),
						'half'          => __( 'Half width', 'ifthenpay-pay-button' ),
						'label'         => __( 'Label', 'ifthenpay-pay-button' ),
						'heading'       => __( 'Heading', 'ifthenpay-pay-button' ),
						'type'          => __( 'Type', 'ifthenpay-pay-button' ),
						'tag'           => __( 'Merge tag', 'ifthenpay-pay-button' ),
						'placeholder'   => __( 'Placeholder', 'ifthenpay-pay-button' ),
						'firstOption'   => __( 'Text before a choice', 'ifthenpay-pay-button' ),
						'options'       => __( 'Options', 'ifthenpay-pay-button' ),
						'optionsHint'   => __( 'One per line', 'ifthenpay-pay-button' ),
						'option'        => __( 'Option', 'ifthenpay-pay-button' ),
						'help'          => __( 'Help text', 'ifthenpay-pay-button' ),
						'text'          => __( 'Text', 'ifthenpay-pay-button' ),
						'mustAnswer'    => __( 'The payer must answer', 'ifthenpay-pay-button' ),
						'full'          => __( 'Full width', 'ifthenpay-pay-button' ),
						'halfWidth'     => __( 'Half', 'ifthenpay-pay-button' ),
						/* translators: %d: the page number */
						'page'          => __( 'Page %d', 'ifthenpay-pay-button' ),
						'pageTitle'     => __( 'Page title', 'ifthenpay-pay-button' ),
						'firstStep'     => CheckoutTexts::get( 'first_step' ),
						'amount'        => __( 'Amount', 'ifthenpay-pay-button' ),
						'amountHint'    => __( 'Shown when the payer types the amount', 'ifthenpay-pay-button' ),
						'details'       => __( 'Your details', 'ifthenpay-pay-button' ),
						'detailsHint'   => __( 'Name, email and the rest, set in Payer details', 'ifthenpay-pay-button' ),
						'up'            => __( 'Move up', 'ifthenpay-pay-button' ),
						'down'          => __( 'Move down', 'ifthenpay-pay-button' ),
						'remove'        => __( 'Remove', 'ifthenpay-pay-button' ),
						'choose'        => CheckoutTexts::get( 'choose' ),
						'stepOf'        => CheckoutTexts::get( 'step_of' ),
						'spotsRequired' => __( 'Spots are split by this field, so the payer has to answer it.', 'ifthenpay-pay-button' ),
						'showWhen'      => __( 'Show this', 'ifthenpay-pay-button' ),
						'always'        => __( 'Always', 'ifthenpay-pay-button' ),
						/* translators: %s: the label of an earlier choice field, e.g. "Day" */
						'whenField'     => __( 'Only when “%s”…', 'ifthenpay-pay-button' ),
						'whenIs'        => __( 'is one of', 'ifthenpay-pay-button' ),
						'whenNot'       => __( 'is none of', 'ifthenpay-pay-button' ),
						'ticked'        => __( 'Ticked', 'ifthenpay-pay-button' ),
						'whenPick'      => __( 'Tick at least one answer, or this stays shown for everyone.', 'ifthenpay-pay-button' ),
						'whenNone'      => __( 'Add a Dropdown, Single choice, Multiple choice or Agreement above this one to show it only for some answers.', 'ifthenpay-pay-button' ),
						'whenSpots'     => __( 'Spots are split by this field, so it always shows.', 'ifthenpay-pay-button' ),
						'conditional'   => __( 'Conditional', 'ifthenpay-pay-button' ),
					),
				),
			)
		);
	}

	/**
	 * The site's look for the editor's preview, when a button goes back to using it.
	 *
	 * @return array<string, mixed>
	 */
	private static function site_look_for_js(): array {
		$look  = ( new CheckoutSettings() )->get_look();
		$url   = $look['pay_image'] > 0 ? wp_get_attachment_image_url( $look['pay_image'], 'full' ) : false;
		$video = $look['pay_video'] > 0 ? wp_get_attachment_url( $look['pay_video'] ) : false;

		unset( $look['css'] );

		return $look + array(
			'pay_image_url'      => is_string( $url ) ? $url : '',
			'pay_video_file_url' => is_string( $video ) ? $video : '',
		);
	}

	/**
	 * The theme's button colours and corners as CSS variables, so the admin's "theme look" stand-in comes close.
	 * Block themes and core's defaults both say it in global styles; anything I can't read safely is left out.
	 */
	public static function theme_button_css(): string {
		$styles = function_exists( 'wp_get_global_styles' ) ? wp_get_global_styles( array( 'elements', 'button' ), array( 'transforms' => array( 'resolve-variables' ) ) ) : array();
		$styles = is_array( $styles ) ? $styles : array();
		$map    = array(
			'--iftp-theme-bg'     => $styles['color']['gradient'] ?? $styles['color']['background'] ?? '',
			'--iftp-theme-text'   => $styles['color']['text'] ?? '',
			'--iftp-theme-radius' => $styles['border']['radius'] ?? '',
			'--iftp-theme-weight' => $styles['typography']['fontWeight'] ?? '',
			'--iftp-theme-case'   => $styles['typography']['textTransform'] ?? '',
		);
		$css    = '';

		foreach ( $map as $property => $value ) {
			// Only plain values: a leftover var() would point at a preset wp-admin doesn't define.
			if ( is_string( $value ) && '' !== $value && false === strpos( $value, 'var(' ) && 1 === preg_match( '/^[#\w\s().,%-]+$/', $value ) ) {
				$css .= $property . ':' . $value . ';';
			}
		}

		return '' !== $css ? 'body.iftp-btn-screen{' . $css . '}' : '';
	}

	/**
	 * Renders the list or the editor.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$studio = $this->requested_studio();

		if ( false !== $studio ) {
			( new AiStudioPage() )->render( $studio );

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which view to show.
		$requested = isset( $_GET[ self::EDIT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::EDIT_VAR ] ) ) : '';

		if ( '' === $requested ) {
			$this->render_list();

			return;
		}

		$button = 'new' === $requested ? null : $this->buttons->find( absint( $requested ) );

		if ( 'new' !== $requested && null === $button ) {
			$this->render_list();

			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which version to show; saving checks its own nonce.
		$draft = isset( $_GET[ AiStudioPage::DRAFT_VAR ] ) && $this->settings->is_connected() ? ( new Studio() )->draft( $button ) : null;

		( new ButtonEditor( $this->settings, new PayLinks(), $this->checkout_settings ) )->render( $button, $draft );
	}

	/**
	 * The button the AI studio was asked for: a saved one, null for a new one, or false when it's not the studio.
	 *
	 * @return Button|null|false
	 */
	private function requested_studio() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which view to show.
		$target = isset( $_GET[ AiStudioPage::VAR ] ) ? sanitize_key( wp_unslash( $_GET[ AiStudioPage::VAR ] ) ) : '';

		// Buttons need the ifthenpay connection, so the studio does too.
		if ( '' === $target || ! $this->settings->is_connected() ) {
			return false;
		}

		if ( Studio::NEW_TARGET === $target ) {
			return null;
		}

		return $this->buttons->find( absint( $target ) ) ?? false;
	}

	/**
	 * The header every view shares.
	 *
	 * @param string $title   The heading.
	 * @param string $actions Markup for the right-hand actions, already escaped.
	 */
	public static function print_header( string $title, string $actions = '' ): void {
		?>
		<header class="iftp-btn-masthead">
			<div class="iftp-btn-masthead__brand">
				<img class="iftp-btn-masthead__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="94" height="24" />
				<span class="iftp-btn-masthead__divider" aria-hidden="true"></span>
				<div>
					<h1 class="iftp-btn-masthead__title"><?php echo esc_html( $title ); ?></h1>
				</div>
			</div>
			<?php if ( '' !== $actions ) : ?>
				<div class="iftp-btn-masthead__actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers escape it. ?></div>
			<?php endif; ?>
		</header>
		<hr class="wp-header-end" />
		<?php
	}

	/**
	 * The notice a save, copy or delete redirect asks for.
	 */
	public static function print_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display-only flag the redirect after a checked action set.
		$notice   = isset( $_GET['iftp_notice'] ) ? sanitize_key( wp_unslash( $_GET['iftp_notice'] ) ) : '';
		$messages = array(
			'saved'      => array( 'success', __( 'Button saved.', 'ifthenpay-pay-button' ) ),
			'default'    => array( 'success', __( 'Button saved, and its design is now the default for every button set to Default design.', 'ifthenpay-pay-button' ) ),
			'created'    => array( 'success', __( 'Button created. Place it with the block, the shortcode or its link.', 'ifthenpay-pay-button' ) ),
			'duplicated' => array( 'success', __( 'Button copied. The copy is off until you turn it on.', 'ifthenpay-pay-button' ) ),
			'deleted'    => array( 'success', __( 'Button deleted. Its payments are still in Payments.', 'ifthenpay-pay-button' ) ),
			'error'      => array( 'error', __( 'That did not work. Please try again.', 'ifthenpay-pay-button' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			wp_admin_notice(
				esc_html( $messages[ $notice ][1] ),
				array(
					'type'        => $messages[ $notice ][0],
					'dismissible' => true,
				)
			);
		}
	}

	/**
	 * The list: totals, a search and filter, then one card per button showing how it looks and how it's doing.
	 */
	private function render_list(): void {
		$buttons   = $this->buttons->all();
		$ids       = array_map( static fn( Button $button ): int => $button->id, $buttons );
		$stats     = $this->transactions->button_stats( $ids );
		$taken     = $this->transactions->spots_taken( array_map( static fn( Button $button ): int => $button->id, array_filter( $buttons, array( Spots::class, 'limited' ) ) ) );
		$opens     = CheckoutOpens::counts( $ids );
		$paid      = $this->transactions->paid_since( array_map( static fn( array $count ): int => $count['since'], $opens ) );
		$connected = $this->settings->is_connected();
		$links     = new PayLinks();
		$order     = array_flip( $ids );

		// Most collected first, which is what the sort starts on; the date order stays on each card for Newest.
		usort( $buttons, static fn( Button $a, Button $b ): int => ( $stats[ $b->id ]['paid_amount'] ?? 0.0 ) <=> ( $stats[ $a->id ]['paid_amount'] ?? 0.0 ) );

		$new = $connected ? '<a class="iftp-btn-btn iftp-btn-btn--ai" href="' . esc_url( AiStudioPage::url( null ) ) . '">' . AiPanel::sparkle() . esc_html__( 'Build with AI', 'ifthenpay-pay-button' ) . '</a><a class="iftp-btn-btn iftp-btn-btn--primary" href="' . esc_url( self::edit_url( 0 ) ) . '">' . self::icon( 'plus' ) . esc_html__( 'New button', 'ifthenpay-pay-button' ) . '</a>' : '';
		?>
		<div class="wrap iftp-btn-buttons" data-iftp-buttons>
			<?php self::print_header( __( 'Pay buttons', 'ifthenpay-pay-button' ), $new ); ?>
			<?php self::print_notice(); ?>

			<?php if ( ! $connected ) : ?>
				<section class="iftp-btn-empty iftp-btn-empty--connect">
					<span class="iftp-btn-mark iftp-btn-empty__mark" aria-hidden="true"><img class="iftp-btn-mark__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/icon-normal.svg' ); ?>" alt="" width="28" height="28" /></span>
					<h2 class="iftp-btn-empty__title"><?php esc_html_e( 'Connect your ifthenpay account first', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-empty__text"><?php esc_html_e( 'Buttons take payments through your ifthenpay Gateway Key. Connect it once, pick the payment methods, and come back here.', 'ifthenpay-pay-button' ); ?></p>
					<a class="iftp-btn-btn iftp-btn-btn--primary" href="<?php echo esc_url( GatewaySettingsPage::url() ); ?>"><?php esc_html_e( 'Connect ifthenpay', 'ifthenpay-pay-button' ); ?></a>
				</section>
			<?php elseif ( array() === $buttons ) : ?>
				<section class="iftp-btn-empty">
					<div class="iftp-btn-empty__demo" aria-hidden="true">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes.
						echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( array_merge( ButtonDesign::defaults(), ButtonDesign::presets()['ifthenpay'], array( 'source' => ButtonDesign::SOURCE_CUSTOM ) ), __( 'Pay now', 'ifthenpay-pay-button' ) ) . '</span>';
						?>
					</div>
					<h2 class="iftp-btn-empty__title"><?php esc_html_e( 'Make your first pay button', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-empty__text"><?php esc_html_e( 'Set an amount (or let the payer choose), pick what to ask for and how it looks, and you get three ways to use it:', 'ifthenpay-pay-button' ); ?></p>
					<ul class="iftp-btn-empty__ways">
						<li><?php echo self::icon( 'code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span><strong><?php esc_html_e( 'A block or shortcode', 'ifthenpay-pay-button' ); ?></strong> <?php esc_html_e( 'on any page or post.', 'ifthenpay-pay-button' ); ?></span></li>
						<li><?php echo self::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span><strong><?php esc_html_e( 'A pay link', 'ifthenpay-pay-button' ); ?></strong> <?php esc_html_e( 'for email, social posts or a QR code.', 'ifthenpay-pay-button' ); ?></span></li>
						<li><?php echo self::icon( 'plug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><span><strong><?php esc_html_e( 'A link for other plugins', 'ifthenpay-pay-button' ); ?></strong> <?php esc_html_e( 'so a form ifthenpay has no integration with can send its payer here.', 'ifthenpay-pay-button' ); ?></span></li>
					</ul>
					<div class="iftp-btn-empty__actions">
						<a class="iftp-btn-btn iftp-btn-btn--primary" href="<?php echo esc_url( self::edit_url( 0 ) ); ?>"><?php esc_html_e( 'Create a button', 'ifthenpay-pay-button' ); ?></a>
						<a class="iftp-btn-btn iftp-btn-btn--ai" href="<?php echo esc_url( AiStudioPage::url( null ) ); ?>"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Or describe it to the AI', 'ifthenpay-pay-button' ); ?></a>
					</div>
				</section>
			<?php else : ?>
				<?php $this->render_summary( $stats, $opens, $paid ); ?>
				<?php $this->render_toolbar( $buttons, $taken ); ?>
				<ul class="iftp-btn-bcards" data-iftp-bcards>
					<li class="iftp-btn-bcards__head" aria-hidden="true">
						<span class="iftp-btn-bcards__col iftp-btn-bcards__col--button"><?php esc_html_e( 'Button', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-bcards__col iftp-btn-bcards__col--stats">
							<span><?php esc_html_e( 'Collected', 'ifthenpay-pay-button' ); ?></span>
							<span><?php esc_html_e( 'Paid', 'ifthenpay-pay-button' ); ?></span>
							<span><?php esc_html_e( 'Didn\'t pay', 'ifthenpay-pay-button' ); ?></span>
						</span>
						<span class="iftp-btn-bcards__col iftp-btn-bcards__col--spots"><?php esc_html_e( 'Spots', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-bcards__col iftp-btn-bcards__col--on"><?php esc_html_e( 'On', 'ifthenpay-pay-button' ); ?></span>
					</li>
					<?php foreach ( $buttons as $button ) : ?>
						<?php $this->render_card( $button, $stats[ $button->id ] ?? null, $taken[ $button->id ]['total'] ?? 0, $opens[ $button->id ]['opens'] ?? 0, $opens[ $button->id ]['since'] ?? 0, $paid[ $button->id ] ?? 0, $links, $order[ $button->id ] ); ?>
					<?php endforeach; ?>
					<li class="iftp-btn-bcard iftp-btn-bcard--new" data-iftp-bcard-new>
						<a class="iftp-btn-bcard__new" href="<?php echo esc_url( self::edit_url( 0 ) ); ?>">
							<span class="iftp-btn-bcard__new-icon" aria-hidden="true"><?php echo self::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span class="iftp-btn-bcard__new-title"><?php esc_html_e( 'New button', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-bcard__new-text"><?php esc_html_e( 'Set an amount, pick a look, place it anywhere.', 'ifthenpay-pay-button' ); ?></span>
						</a>
					</li>
					<li class="iftp-btn-bcard iftp-btn-bcard--new iftp-btn-bcard--ai" data-iftp-bcard-new>
						<a class="iftp-btn-bcard__new" href="<?php echo esc_url( AiStudioPage::url( null ) ); ?>">
							<span class="iftp-btn-bcard__new-icon" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span class="iftp-btn-bcard__new-title"><?php esc_html_e( 'Build with AI', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-bcard__new-text"><?php esc_html_e( 'Describe it in a sentence; watch the form and the look appear.', 'ifthenpay-pay-button' ); ?></span>
						</a>
					</li>
				</ul>
				<p class="iftp-btn-bnone" data-iftp-bnone hidden><?php esc_html_e( 'No buttons match.', 'ifthenpay-pay-button' ); ?></p>
			<?php endif; ?>
			<?php self::print_shared_ui(); ?>
		</div>
		<?php
	}

	/**
	 * The all-time totals across every button: what came in, how many who opened a checkout paid, how many
	 * walked away, and the average payment. Money tiles carry the last 30 days as a line and their change
	 * against the 30 before; the checkout tiles have no daily history, so they show their share as a bar.
	 *
	 * @param array<int, array{paid_count: int, paid_amount: float, count: int}> $stats Payment counts by button.
	 * @param array<int, array{opens: int, since: int}>                          $opens Checkout opens by button.
	 * @param array<int, int>                                                    $paid  Paid payments since each began counting opens.
	 */
	private function render_summary( array $stats, array $opens, array $paid ): void {
		$total     = (float) array_sum( array_column( $stats, 'paid_amount' ) );
		$count     = (int) array_sum( array_column( $stats, 'paid_count' ) );
		$opened    = (int) array_sum( array_column( $opens, 'opens' ) );
		$converted = min( $opened, (int) array_sum( $paid ) );
		$recent    = ( new Insights( $this->transactions ) )->period( '30d' );
		$average   = $count > 0 ? $total / $count : 0.0;

		$tiles = array(
			array(
				'icon'  => 'coins',
				'label' => __( 'Collected', 'ifthenpay-pay-button' ),
				'value' => CheckoutService::format_money( $total ),
				/* translators: %s: how many payments were paid */
				'sub'   => sprintf( _n( 'from %s paid payment', 'from %s paid payments', $count, 'ifthenpay-pay-button' ), number_format_i18n( $count ) ),
				'delta' => $recent['kpis']['revenue']['delta'],
				'spark' => $recent['series']['values'],
				'share' => null,
				'tone'  => '',
			),
			array(
				'icon'  => 'check',
				'label' => __( 'Conversion (paid ÷ opened)', 'ifthenpay-pay-button' ),
				'value' => $opened > 0 ? self::percent( $converted / $opened ) : '—',
				'sub'   => $opened > 0
					/* translators: 1: paid payments, 2: how many times a checkout was opened */
					? sprintf( _n( '%1$s of %2$s checkout opened', '%1$s of %2$s checkouts opened', $opened, 'ifthenpay-pay-button' ), number_format_i18n( $converted ), number_format_i18n( $opened ) )
					: __( 'counts from the next checkout opened', 'ifthenpay-pay-button' ),
				'delta' => null,
				'spark' => null,
				'share' => $opened > 0 ? $converted / $opened : null,
				'tone'  => '',
			),
			array(
				'icon'  => 'exit',
				'label' => __( 'Abandoned checkouts', 'ifthenpay-pay-button' ),
				'value' => number_format_i18n( $opened - $converted ),
				'sub'   => $opened > 0
					/* translators: %s: a percentage, e.g. 20% */
					? sprintf( __( '%s of checkouts opened', 'ifthenpay-pay-button' ), self::percent( ( $opened - $converted ) / $opened ) )
					: __( 'counts from the next checkout opened', 'ifthenpay-pay-button' ),
				'delta' => null,
				'spark' => null,
				'share' => $opened > 0 ? ( $opened - $converted ) / $opened : null,
				'tone'  => 'bad',
			),
			array(
				'icon'  => 'receipt',
				'label' => __( 'Average payment', 'ifthenpay-pay-button' ),
				'value' => $count > 0 ? CheckoutService::format_money( $average ) : '—',
				/* translators: %s: how many payments were paid */
				'sub'   => sprintf( _n( 'across %s paid payment', 'across %s paid payments', $count, 'ifthenpay-pay-button' ), number_format_i18n( $count ) ),
				'delta' => $recent['kpis']['average']['delta'],
				'spark' => $recent['series']['averages'],
				'share' => null,
				'tone'  => '',
			),
		);
		?>
		<dl class="iftp-btn-bsummary">
			<?php foreach ( $tiles as $tile ) : ?>
				<div class="iftp-btn-bsummary__tile<?php echo '' !== $tile['tone'] ? ' iftp-btn-bsummary__tile--' . esc_attr( $tile['tone'] ) : ''; ?>">
					<span class="iftp-btn-bsummary__icon" aria-hidden="true"><?php echo self::icon( $tile['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
					<dt class="iftp-btn-bsummary__label"><?php echo esc_html( $tile['label'] ); ?></dt>
					<dd class="iftp-btn-bsummary__value"><?php echo esc_html( $tile['value'] ); ?></dd>
					<dd class="iftp-btn-bsummary__trend">
						<?php if ( null !== $tile['delta'] ) : ?>
							<span class="iftp-btn-bsummary__delta iftp-btn-bsummary__delta--<?php echo $tile['delta'] < 0 ? 'down' : 'up'; ?>" data-iftp-tip="<?php esc_attr_e( 'Last 30 days against the 30 before', 'ifthenpay-pay-button' ); ?>">
								<?php
								/* translators: 1: ▲ or ▼, 2: a percentage change, e.g. 12% */
								echo esc_html( sprintf( __( '%1$s %2$s', 'ifthenpay-pay-button' ), $tile['delta'] < 0 ? '▼' : '▲', self::percent( abs( $tile['delta'] ) / 100 ) ) );
								?>
							</span>
							<span class="iftp-btn-bsummary__sub"><?php esc_html_e( 'last 30 days', 'ifthenpay-pay-button' ); ?></span>
						<?php else : ?>
							<span class="iftp-btn-bsummary__sub"><?php echo esc_html( $tile['sub'] ); ?></span>
						<?php endif; ?>
					</dd>
					<dd class="iftp-btn-bsummary__spark">
						<?php if ( null !== $tile['spark'] ) : ?>
							<?php self::print_sparkline( $tile['spark'] ); ?>
						<?php else : ?>
							<span class="iftp-btn-bsummary__meter" aria-hidden="true"><svg class="iftp-btn-bsummary__fill" width="<?php echo esc_attr( (string) (int) round( ( $tile['share'] ?? 0.0 ) * 100 ) ); ?>%" focusable="false"></svg></span>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<?php
	}

	/**
	 * A small area line of the last 30 days. Decorative: the tile's value and change say it in words.
	 *
	 * @param array<int, float> $values One value per day.
	 */
	private static function print_sparkline( array $values ): void {
		$count  = count( $values );
		$high   = max( 0.01, array() !== $values ? (float) max( $values ) : 0.0 );
		$width  = 300;
		$height = 34;
		$points = array();

		foreach ( $values as $index => $value ) {
			$x        = $count > 1 ? $index / ( $count - 1 ) * $width : 0;
			$y        = $height - 2 - ( $value / $high ) * ( $height - 6 );
			$points[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$line = 'M' . implode( ' L', $points );
		?>
		<svg class="iftp-btn-spark" viewBox="0 0 <?php echo esc_attr( $width . ' ' . $height ); ?>" preserveAspectRatio="none" aria-hidden="true" focusable="false">
			<path class="iftp-btn-spark__area" d="<?php echo esc_attr( $line . ' L' . $width . ',' . $height . ' L0,' . $height . ' Z' ); ?>"/>
			<path class="iftp-btn-spark__line" d="<?php echo esc_attr( $line ); ?>"/>
		</svg>
		<?php
	}

	/**
	 * A share as a whole percentage in the site's number format.
	 *
	 * @param float $share 0 to 1.
	 */
	private static function percent( float $share ): string {
		/* translators: %s: a whole number */
		return sprintf( __( '%s%%', 'ifthenpay-pay-button' ), number_format_i18n( round( $share * 100 ) ) );
	}

	/**
	 * Search by title, the amount type, the sort, All / On / Off / Sold out, and grid or list. All of it runs
	 * in the browser; the list is never long enough to page.
	 *
	 * @param array<int, Button>                                         $buttons Every button.
	 * @param array<int, array{total: int, options: array<string, int>}> $taken   Spots taken by button.
	 */
	private function render_toolbar( array $buttons, array $taken ): void {
		$live    = count( array_filter( $buttons, static fn( Button $button ): bool => $button->active ) );
		$sold    = count( array_filter( $buttons, static fn( Button $button ): bool => self::sold_out( $button, $taken[ $button->id ]['total'] ?? 0 ) ) );
		$filters = array(
			'all'  => array( __( 'All', 'ifthenpay-pay-button' ), count( $buttons ) ),
			'on'   => array( __( 'On', 'ifthenpay-pay-button' ), $live ),
			'off'  => array( __( 'Off', 'ifthenpay-pay-button' ), count( $buttons ) - $live ),
			'sold' => array( __( 'Sold out', 'ifthenpay-pay-button' ), $sold ),
		);
		$sorts   = array(
			'collected' => __( 'Most collected', 'ifthenpay-pay-button' ),
			'paid'      => __( 'Most paid', 'ifthenpay-pay-button' ),
			'newest'    => __( 'Newest', 'ifthenpay-pay-button' ),
			'oldest'    => __( 'Oldest', 'ifthenpay-pay-button' ),
			'title'     => __( 'Name (A–Z)', 'ifthenpay-pay-button' ),
		);
		$views   = array(
			'grid' => __( 'Grid view', 'ifthenpay-pay-button' ),
			'list' => __( 'List view', 'ifthenpay-pay-button' ),
		);
		?>
		<div class="iftp-btn-btoolbar" data-iftp-btoolbar hidden>
			<label class="iftp-btn-bsearch">
				<span class="screen-reader-text"><?php esc_html_e( 'Search buttons', 'ifthenpay-pay-button' ); ?></span>
				<?php echo self::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?>
				<input type="search" class="iftp-btn-bsearch__input" placeholder="<?php esc_attr_e( 'Search buttons', 'ifthenpay-pay-button' ); ?>" data-iftp-bsearch />
			</label>
			<label class="iftp-btn-btoolbar__select">
				<span class="screen-reader-text"><?php esc_html_e( 'Amount type', 'ifthenpay-pay-button' ); ?></span>
				<select class="iftp-btn-bselect" data-iftp-btype>
					<option value=""><?php esc_html_e( 'All types', 'ifthenpay-pay-button' ); ?></option>
					<option value="<?php echo esc_attr( Button::MODE_FIXED ); ?>"><?php esc_html_e( 'Fixed', 'ifthenpay-pay-button' ); ?></option>
					<option value="<?php echo esc_attr( Button::MODE_PAYER ); ?>"><?php esc_html_e( 'Payer chooses', 'ifthenpay-pay-button' ); ?></option>
					<option value="<?php echo esc_attr( Button::MODE_LINK ); ?>"><?php esc_html_e( 'From the link', 'ifthenpay-pay-button' ); ?></option>
				</select>
			</label>
			<label class="iftp-btn-btoolbar__select">
				<span class="screen-reader-text"><?php esc_html_e( 'Sort by', 'ifthenpay-pay-button' ); ?></span>
				<select class="iftp-btn-bselect" data-iftp-bsort>
					<?php foreach ( $sorts as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="iftp-btn-bfilter" role="group" aria-label="<?php esc_attr_e( 'Show', 'ifthenpay-pay-button' ); ?>">
				<?php foreach ( $filters as $key => $filter ) : ?>
					<button type="button" class="iftp-btn-bfilter__option" data-iftp-bfilter="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'all' === $key ? 'true' : 'false'; ?>">
						<?php echo esc_html( $filter[0] ); ?>
						<span class="iftp-btn-bfilter__count" data-iftp-bfilter-count="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( number_format_i18n( $filter[1] ) ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="iftp-btn-bfilter iftp-btn-bview" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ifthenpay-pay-button' ); ?>">
				<?php foreach ( $views as $key => $label ) : ?>
					<button type="button" class="iftp-btn-bfilter__option" data-iftp-bview="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'grid' === $key ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-iftp-tip="<?php echo esc_attr( $label ); ?>"><?php echo self::icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Whether a button that sells a set number of spots has none left.
	 *
	 * @param Button $button The button.
	 * @param int    $taken  Spots taken, paid or held.
	 */
	private static function sold_out( Button $button, int $taken ): bool {
		$limit = Spots::capacity( $button );

		return $limit > 0 && $taken >= $limit;
	}

	/**
	 * One button's card: its look on a small stage, what it charges, how it's doing, and quick actions.
	 *
	 * @param Button                                                      $button The button.
	 * @param array{paid_count: int, paid_amount: float, count: int}|null $stats  Its payment counts, if it has any.
	 * @param int                                                         $taken  Spots taken, paid or held.
	 * @param int                                                         $opens  Times its checkout was opened.
	 * @param int                                                         $since  When it started counting opens, 0 before the first.
	 * @param int                                                         $paid   Paid payments since opens began counting.
	 * @param PayLinks                                                    $links  Builds its pay link.
	 * @param int                                                         $order  Its place newest first, for the Newest sort.
	 */
	private function render_card( Button $button, ?array $stats, int $taken, int $opens, int $since, int $paid, PayLinks $links, int $order ): void {
		$shortcode  = sprintf( '[%1$s id="%2$d"]', Shortcode::TAG, $button->id );
		$pay_link   = $links->url( $button );
		$edit       = self::edit_url( $button->id );
		$paid_count = null !== $stats ? $stats['paid_count'] : 0;
		$limit      = Spots::capacity( $button );
		$abandon    = CheckoutOpens::abandon_rate( $opens, $paid );
		$share      = $limit > 0 ? min( 1.0, $taken / $limit ) : 0.0;
		$sold_out   = self::sold_out( $button, $taken );
		$looks      = array(
			ButtonDesign::SOURCE_SITE   => __( 'Default design', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_THEME  => __( 'Theme look', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_CUSTOM => __( 'Custom look', 'ifthenpay-pay-button' ),
		);
		$payments   = add_query_arg(
			array(
				'page'   => EntriesPage::PAGE_SLUG,
				'button' => (string) $button->id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<li class="iftp-btn-bcard<?php echo $button->active ? '' : ' iftp-btn-bcard--off'; ?>" data-iftp-bcard data-title="<?php echo esc_attr( strtolower( remove_accents( $button->title ) ) ); ?>" data-active="<?php echo $button->active ? '1' : '0'; ?>" data-mode="<?php echo esc_attr( $button->mode() ); ?>" data-sold="<?php echo $sold_out ? '1' : '0'; ?>" data-collected="<?php echo esc_attr( (string) ( null !== $stats ? $stats['paid_amount'] : 0 ) ); ?>" data-paid="<?php echo esc_attr( (string) $paid_count ); ?>" data-order="<?php echo esc_attr( (string) $order ); ?>">
			<a class="iftp-btn-bcard__stage" href="<?php echo esc_url( $edit ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes.
				echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( ButtonDesign::for_button( $button, $this->checkout_settings ), $button->display_label() ) . '</span>';
				?>
				<span class="iftp-btn-bcard__look"><?php echo esc_html( $looks[ $button->config['design']['source'] ] ?? $looks[ ButtonDesign::SOURCE_THEME ] ); ?></span>
				<?php if ( $sold_out ) : ?>
					<span class="iftp-btn-bcard__badge iftp-btn-bcard__badge--sold"><?php esc_html_e( 'Sold out', 'ifthenpay-pay-button' ); ?></span>
				<?php elseif ( $limit > 0 && $share >= self::ALMOST_FULL ) : ?>
					<span class="iftp-btn-bcard__badge iftp-btn-bcard__badge--almost"><?php esc_html_e( 'Almost full', 'ifthenpay-pay-button' ); ?></span>
				<?php endif; ?>
			</a>

			<div class="iftp-btn-bcard__body">
				<div class="iftp-btn-bcard__top">
					<div class="iftp-btn-bcard__heading">
						<h2 class="iftp-btn-bcard__title"><a href="<?php echo esc_url( $edit ); ?>" title="<?php echo esc_attr( $button->title ); ?>"><?php echo esc_html( $button->title ); ?></a></h2>
						<p class="iftp-btn-bcard__amount">
							<span class="iftp-btn-bcard__mode iftp-btn-bcard__mode--<?php echo esc_attr( $button->mode() ); ?>"><?php echo esc_html( self::mode_label( $button ) ); ?></span>
							<span><?php echo esc_html( self::amount_detail( $button ) ); ?></span>
							<?php if ( $sold_out ) : ?>
								<span class="iftp-btn-bcard__state iftp-btn-bcard__badge--sold"><?php esc_html_e( 'Sold out', 'ifthenpay-pay-button' ); ?></span>
							<?php elseif ( $limit > 0 && $share >= self::ALMOST_FULL ) : ?>
								<span class="iftp-btn-bcard__state iftp-btn-bcard__badge--almost"><?php esc_html_e( 'Almost full', 'ifthenpay-pay-button' ); ?></span>
							<?php endif; ?>
						</p>
					</div>
					<label class="iftp-btn-bcard__toggle">
						<span class="screen-reader-text">
							<?php
							/* translators: %s: the button's title */
							echo esc_html( sprintf( __( 'Take payments with %s', 'ifthenpay-pay-button' ), $button->title ) );
							?>
						</span>
						<input type="checkbox" class="iftp-btn-switch" data-iftp-toggle-button="<?php echo esc_attr( (string) $button->id ); ?>" <?php checked( $button->active ); ?> />
					</label>
				</div>

				<dl class="iftp-btn-bcard__stats">
					<div>
						<dt><?php esc_html_e( 'Collected', 'ifthenpay-pay-button' ); ?></dt>
						<dd><?php echo esc_html( CheckoutService::format_money( null !== $stats ? $stats['paid_amount'] : 0.0 ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Paid', 'ifthenpay-pay-button' ); ?></dt>
						<dd><a href="<?php echo esc_url( $payments ); ?>"><?php echo esc_html( number_format_i18n( $paid_count ) ); ?></a></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Didn\'t pay', 'ifthenpay-pay-button' ); ?></dt>
						<?php
						// Paid is all-time but this only counts from the first open, so the card says from when.
						$since_day   = $since > 0 ? Dates::local( Dates::local( 'Y' ) === Dates::local( 'Y', $since ) ? 'j M' : 'j M Y', $since ) : '';
						$abandon_tip = $opens > 0
							/* translators: 1: a date, e.g. 23 Oct, 2: checkouts opened, 3: of those, how many were paid */
							? sprintf( _n( 'Since %1$s: %2$s checkout opened, %3$s paid.', 'Since %1$s: %2$s checkouts opened, %3$s paid.', $opens, 'ifthenpay-pay-button' ), $since_day, number_format_i18n( $opens ), number_format_i18n( min( $opens, $paid ) ) )
							: __( 'Nobody has opened its checkout since this started counting.', 'ifthenpay-pay-button' );

						if ( null === $abandon && $opens > 0 ) {
							/* translators: %s: how many opens it takes, e.g. 10 */
							$abandon_tip .= ' ' . sprintf( __( 'The rate shows from %s opens.', 'ifthenpay-pay-button' ), number_format_i18n( CheckoutOpens::MIN_OPENS ) );
						}
						?>
						<dd data-iftp-tip="<?php echo esc_attr( $abandon_tip ); ?>"><?php echo esc_html( null !== $abandon ? self::percent( $abandon ) : '—' ); ?><span class="screen-reader-text"> (<?php echo esc_html( $abandon_tip ); ?>)</span></dd>
						<?php if ( '' !== $since_day ) : ?>
							<dd class="iftp-btn-bcard__since">
								<?php
								/* translators: %s: a date, e.g. 23 Oct */
								echo esc_html( sprintf( __( 'since %s', 'ifthenpay-pay-button' ), $since_day ) );
								?>
							</dd>
						<?php endif; ?>
					</div>
				</dl>
				<?php if ( $limit > 0 ) : ?>
					<div class="iftp-btn-bcard__spots<?php echo $sold_out ? ' iftp-btn-bcard__spots--sold' : ( $share >= self::ALMOST_FULL ? ' iftp-btn-bcard__spots--almost' : '' ); ?>">
						<span class="iftp-btn-bcard__spots-head">
							<span>
								<?php
								/* translators: %s: a percentage, e.g. 80% */
								echo esc_html( sprintf( __( '%s of spots taken', 'ifthenpay-pay-button' ), self::percent( $share ) ) );
								?>
							</span>
							<span class="iftp-btn-bcard__spots-count">
								<?php
								/* translators: 1: spots taken, paid or waiting for payment, 2: how many there are */
								echo esc_html( sprintf( __( '%1$s of %2$s', 'ifthenpay-pay-button' ), number_format_i18n( $taken ), number_format_i18n( $limit ) ) );
								?>
							</span>
						</span>
						<span class="iftp-btn-bcard__meter" aria-hidden="true"><svg class="iftp-btn-bcard__fill" width="<?php echo esc_attr( (string) (int) round( $share * 100 ) ); ?>%" focusable="false"></svg></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="iftp-btn-bcard__foot">
				<a class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-bcard__edit" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'ifthenpay-pay-button' ); ?></a>
				<span class="iftp-btn-bcard__tools">
					<button type="button" class="iftp-btn-tool" data-iftp-copy-text="<?php echo esc_attr( $shortcode ); ?>" data-iftp-copy-done="<?php esc_attr_e( 'Shortcode copied.', 'ifthenpay-pay-button' ); ?>" data-iftp-tip="<?php esc_attr_e( 'Copy shortcode', 'ifthenpay-pay-button' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: the shortcode */ __( 'Copy shortcode %s', 'ifthenpay-pay-button' ), $shortcode ) ); ?>"><?php echo self::icon( 'code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
					<button type="button" class="iftp-btn-tool" data-iftp-copy-text="<?php echo esc_attr( $pay_link ); ?>" data-iftp-copy-done="<?php esc_attr_e( 'Pay link copied.', 'ifthenpay-pay-button' ); ?>" data-iftp-tip="<?php esc_attr_e( 'Copy pay link', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Copy pay link', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
					<a class="iftp-btn-tool" href="<?php echo esc_url( $pay_link ); ?>" target="_blank" rel="noopener" data-iftp-tip="<?php esc_attr_e( 'Open pay page', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Open pay page (opens in a new tab)', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
					<a class="iftp-btn-tool" href="<?php echo esc_url( self::action_url( self::DUPLICATE_ACTION, $button->id ) ); ?>" data-iftp-tip="<?php esc_attr_e( 'Duplicate', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
					<a class="iftp-btn-tool iftp-btn-tool--danger" href="<?php echo esc_url( self::action_url( self::DELETE_ACTION, $button->id ) ); ?>" data-iftp-confirm-delete="<?php echo esc_attr( $button->title ); ?>" data-iftp-tip="<?php esc_attr_e( 'Delete', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
				</span>
			</div>
		</li>
		<?php
	}

	/**
	 * The delete confirmation and the toast, for the list and the editor.
	 */
	public static function print_shared_ui(): void {
		?>
		<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-delete-title" aria-describedby="iftp-btn-delete-text" data-iftp-delete-dialog>
			<h2 id="iftp-btn-delete-title" class="iftp-btn-dialog__title" data-iftp-delete-title><?php esc_html_e( 'Delete this button?', 'ifthenpay-pay-button' ); ?></h2>
			<p id="iftp-btn-delete-text" class="iftp-btn-dialog__text"><?php esc_html_e( 'Its payments stay in Payments, but every block, shortcode and link that uses it stops working. This cannot be undone.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-delete-cancel><?php esc_html_e( 'Keep it', 'ifthenpay-pay-button' ); ?></button>
				<a class="iftp-btn-btn iftp-btn-btn--danger" href="#" data-iftp-delete-ok><?php esc_html_e( 'Delete button', 'ifthenpay-pay-button' ); ?></a>
			</div>
		</dialog>

		<div class="iftp-btn-toast" data-iftp-toast role="status" aria-live="polite" hidden>
			<span class="iftp-btn-toast__message" data-iftp-toast-message></span>
		</div>
		<?php
	}

	/**
	 * Merge tag chips; a click drops the tag into the field last focused in the same scope.
	 *
	 * @param array<string, string> $tags Tag => what it holds, from MergeTags::available().
	 */
	public static function print_tags( array $tags ): void {
		?>
		<div class="iftp-btn-tags" role="group" aria-label="<?php esc_attr_e( 'Insert a tag', 'ifthenpay-pay-button' ); ?>">
			<span class="iftp-btn-tags__label"><?php esc_html_e( 'Tags', 'ifthenpay-pay-button' ); ?></span>
			<?php foreach ( $tags as $tag => $meaning ) : ?>
				<button type="button" class="iftp-btn-tags__chip" data-iftp-tag="<?php echo esc_attr( $tag ); ?>" data-iftp-tip="<?php echo esc_attr( $meaning ); ?>"><?php echo esc_html( $tag ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * A small line icon, drawn in currentColor.
	 *
	 * @param string $name The icon.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'plus'     => '<path d="M12 5v14M5 12h14"/>',
			'code'     => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
			'link'     => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/>',
			'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
			'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2.5"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
			'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
			'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
			'plug'     => '<path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0zM12 17v4"/>',
			'bolt'     => '<path d="M13 3 5 13.5h6L10 21l8-10.5h-6z"/>',
			'coins'    => '<ellipse cx="9" cy="7" rx="5.5" ry="2.5"/><path d="M3.5 7v4c0 1.4 2.5 2.5 5.5 2.5s5.5-1.1 5.5-2.5V7"/><path d="M9.5 16.4c.9 1 3 1.6 5 1.6 3 0 5.5-1.1 5.5-2.5v-4c0-1.2-1.7-2.2-4-2.4"/>',
			'check'    => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.2 2.4 2.3 4.6-4.8"/>',
			'exit'     => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M9 16l-4-4 4-4M5 12h10"/>',
			'seat'     => '<path d="M7 11V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v5"/><path d="M5 11h14v4H5zM7 15v5M17 15v5"/>',
			'receipt'  => '<path d="M6 3.5h12v17l-3-1.8-3 1.8-3-1.8-3 1.8z"/><path d="M9 8h6M9 11.5h6M9 15h3.5"/>',
			'grid'     => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/>',
			'list'     => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.75" cy="6" r=".9"/><circle cx="4.75" cy="12" r=".9"/><circle cx="4.75" cy="18" r=".9"/>',
		);

		return isset( $paths[ $name ] ) ? '<svg class="iftp-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>' : '';
	}

	/**
	 * A read-only value with a Copy button.
	 *
	 * @param string $label The label.
	 * @param string $value The value to copy.
	 * @param string $id    A unique id.
	 */
	public static function print_copy_field( string $label, string $value, string $id ): void {
		?>
		<div class="iftp-btn-copy">
			<label class="iftp-btn-copy__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<span class="iftp-btn-copy__row">
				<input type="text" class="iftp-btn-copy__value" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>" readonly data-iftp-copy-source />
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-copy__button" data-iftp-copy="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Copy', 'ifthenpay-pay-button' ); ?></button>
			</span>
		</div>
		<?php
	}

	/**
	 * A signed admin-post URL for a one-click action on a button.
	 *
	 * @param string $action One of the *_ACTION constants.
	 * @param int    $id     The button's post ID.
	 */
	public static function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'       => $action,
					self::EDIT_VAR => (string) $id,
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $id
		);
	}

	/**
	 * The amount mode, as a short chip.
	 *
	 * @param Button $button The button.
	 */
	public static function mode_label( Button $button ): string {
		$labels = array(
			Button::MODE_FIXED => __( 'Fixed', 'ifthenpay-pay-button' ),
			Button::MODE_PAYER => __( 'Payer chooses', 'ifthenpay-pay-button' ),
			Button::MODE_LINK  => __( 'From the link', 'ifthenpay-pay-button' ),
		);

		return $labels[ $button->mode() ] ?? $labels[ Button::MODE_FIXED ];
	}

	/**
	 * The amount, or the range a payer or a link can pick from.
	 *
	 * @param Button $button The button.
	 */
	public static function amount_detail( Button $button ): string {
		if ( Button::MODE_FIXED === $button->mode() ) {
			return CheckoutService::format_money( $button->config['amount'] );
		}

		/* translators: 1: smallest amount, 2: largest amount */
		return sprintf( __( '%1$s – %2$s', 'ifthenpay-pay-button' ), CheckoutService::format_money( $button->config['min'] ), CheckoutService::format_money( $button->config['max'] ) );
	}
}
