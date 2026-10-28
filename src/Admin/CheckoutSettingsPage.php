<?php
/**
 * The Default Design screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's design, in two tabs, each with a live preview: how the checkout, the pay page and the popups look,
 * and how the default button looks. What they say lives in Configuration.
 */
class CheckoutSettingsPage {

	public const PAGE_SLUG   = 'iftp-btn-checkout';
	public const SAVE_ACTION = 'iftp_btn_save_checkout';

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $settings;

	/**
	 * The screen's hook suffix.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Sets up the screen.
	 *
	 * @param CheckoutSettings $settings Checkout settings.
	 */
	public function __construct( CheckoutSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks the save and the assets.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the submenu.
	 *
	 * @param string $parent_slug The parent menu slug.
	 */
	public function add_page( string $parent_slug ): void {
		$hook = add_submenu_page( $parent_slug, __( 'Default Design', 'ifthenpay-pay-button' ), __( 'Default Design', 'ifthenpay-pay-button' ), Plugin::admin_cap(), self::PAGE_SLUG, array( $this, 'render' ) );

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * The screen's URL, open on a tab if one is given.
	 *
	 * @param string $tab A tab key: `look` or `button`.
	 */
	public static function url( string $tab = '' ): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) ) . ( '' !== $tab ? '#' . $tab : '' );
	}

	/**
	 * Enqueues the shared admin assets and the checkout stylesheet the preview draws with, only here.
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
		wp_add_inline_style( 'iftp-btn-buttons', ButtonsPage::theme_button_css() . LookControls::swatch_css() );
		// The pay page's background image and video come from the Media Library.
		wp_enqueue_media();
		wp_enqueue_script( 'iftp-btn-buttons', IFTP_BTN_URL . $js, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE ), Plugin::asset_version( $js ), true );
		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnButtons',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => '',
				'lookPresets'  => CheckoutLook::presets(),
				'lookWidths'   => CheckoutLook::WIDTHS,
				'presets'      => ButtonDesign::presets(),
				'pill'         => ButtonDesign::PILL,
				'gradientTurn' => ButtonDesign::GRADIENT_TURN,
				'sample'       => self::sample_values(),
				'i18n'         => array(
					'copied'     => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed' => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					'pill'       => __( 'Pill', 'ifthenpay-pay-button' ),
					/* translators: %d: corner radius in pixels */
					'px'         => __( '%d px', 'ifthenpay-pay-button' ),
					'unsaved'    => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
					'pickImage'  => __( 'Pay page background', 'ifthenpay-pay-button' ),
					'useImage'   => __( 'Use this image', 'ifthenpay-pay-button' ),
					'pickVideo'  => __( 'Pay page video', 'ifthenpay-pay-button' ),
					'useVideo'   => __( 'Use this video', 'ifthenpay-pay-button' ),
				),
			)
		);
	}

	/**
	 * Made-up payment details for the previews' merge tags.
	 *
	 * @return array<string, string>
	 */
	public static function sample_values(): array {
		return array(
			'name'       => 'Ana Silva',
			'email'      => 'ana@example.com',
			'phone'      => '+351 912 345 678',
			'nif'        => '123456789',
			'note'       => __( 'Thank you!', 'ifthenpay-pay-button' ),
			'amount'     => '35,00 €',
			'quantity'   => '1',
			'payment_id' => 'BTN6F2A91C4',
			'order'      => 'ORDER-1042',
			'method'     => 'MB WAY',
			'button'     => __( 'Consultation', 'ifthenpay-pay-button' ),
			'site'       => (string) get_bloginfo( 'name' ),
			'date'       => Dates::local( (string) get_option( 'date_format' ) ),
		);
	}

	/**
	 * Saves the form.
	 */
	public function save(): void {
		check_admin_referer( self::SAVE_ACTION );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		$tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		// CheckoutLook::sanitize() cleans every key by its own rules, so here it's only unslashed.
		$look = isset( $_POST['look'] ) && is_array( $_POST['look'] ) ? map_deep( wp_unslash( $_POST['look'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Custom CSS is as powerful as unfiltered HTML, so only those who may post that can change it.
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$look['css'] = $this->settings->get_look()['css'];
		}

		$this->settings->set_look( CheckoutLook::sanitize( $look ) );

		// ButtonDesign::sanitize() cleans every key by its own rules, so here it's only unslashed.
		$this->settings->set_button_design( ButtonDesign::sanitize( isset( $_POST['button_design'] ) && is_array( $_POST['button_design'] ) ? map_deep( wp_unslash( $_POST['button_design'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Back to the tab they saved from.
		wp_safe_redirect( add_query_arg( 'iftp_notice', 'saved', self::url() ) . ( 'button' === $tab ? '#button' : '' ) );
		exit;
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$tabs = array(
			'look'   => __( 'Checkout & pay page', 'ifthenpay-pay-button' ),
			'button' => __( 'Button', 'ifthenpay-pay-button' ),
		);
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-checkout-settings">
			<?php ButtonsPage::print_header( __( 'Default Design', 'ifthenpay-pay-button' ) ); ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag from the redirect after a checked save.
			if ( isset( $_GET['iftp_notice'] ) ) {
				wp_admin_notice(
					esc_html__( 'Settings saved.', 'ifthenpay-pay-button' ),
					array(
						'type'        => 'success',
						'dismissible' => true,
					)
				);
			}
			?>

			<form class="iftp-btn-csettings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-iftp-csettings>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<input type="hidden" name="tab" value="look" data-iftp-tab-input />
				<?php wp_nonce_field( self::SAVE_ACTION ); ?>

				<div class="iftp-btn-csettings__bar">
					<div class="iftp-btn-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Default design', 'ifthenpay-pay-button' ); ?>" data-iftp-tabs hidden>
						<?php foreach ( $tabs as $key => $label ) : ?>
							<button type="button" class="iftp-btn-tabs__tab" role="tab" id="iftp-btn-tab-<?php echo esc_attr( $key ); ?>" aria-controls="iftp-btn-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'look' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'look' === $key ? '0' : '-1'; ?>" data-iftp-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<p class="iftp-btn-editor__dirty" data-iftp-dirty hidden><?php esc_html_e( 'Unsaved changes', 'ifthenpay-pay-button' ); ?></p>
					<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary"><?php esc_html_e( 'Save Settings', 'ifthenpay-pay-button' ); ?></button>
				</div>

				<div class="iftp-btn-lookpage">
					<div class="iftp-btn-lookpage__controls">
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-look" aria-labelledby="iftp-btn-tab-look" data-iftp-panel="look">
							<?php ( new LookControls( 'look' ) )->render_cards( $this->settings->get_look(), true, $this->settings->get_look() ); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-button" aria-labelledby="iftp-btn-tab-button" data-iftp-panel="button">
							<?php $this->render_button(); ?>
						</div>
					</div>
					<aside class="iftp-btn-lookpage__aside">
						<div class="iftp-btn-lookpage__sticky">
							<div data-iftp-aside="look">
								<?php ( new LookControls( 'look' ) )->render_preview( $this->settings->get_look(), $this->settings->get_confirmation() ); ?>
							</div>
							<section class="iftp-btn-preview" aria-labelledby="iftp-btn-preview-title" data-iftp-aside="button">
								<?php DesignControls::render_stage( $this->settings->get_button_design(), CheckoutTexts::get( 'pay_now' ), self::preview_logos() ); ?>
							</section>
						</div>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * The Button tab: the design every button set to "Default design" wears.
	 */
	private function render_button(): void {
		?>
		<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-default-design">
			<div class="iftp-btn-ecard__intro">
				<h2 class="iftp-btn-ecard__title" id="iftp-btn-default-design"><?php esc_html_e( 'Default button design', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-ecard__description"><?php esc_html_e( 'Every button set to Default design wears this, so many buttons across your site look the same and change together. A button can still have its own in its editor, under Design.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-ecard__body">
				<?php ( new DesignControls( 'button_design', false, $this->settings->get_button_design() ) )->render( $this->settings->get_button_design() ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * The payment logos under the Button tab's preview: the methods switched on in Configuration › Gateway.
	 */
	private static function preview_logos(): string {
		$html = '';

		foreach ( ( new SettingsRepository() )->get_methods() as $method ) {
			if ( $method['enabled'] && '' !== $method['account'] && '' !== (string) ( $method['logo'] ?? '' ) ) {
				$html .= '<img src="' . esc_url( $method['logo'] ?? '' ) . '"' . ButtonRenderer::dark_logo_attr( $method ) . ' alt="" height="18" loading="lazy" />';
			}
		}

		return $html;
	}
}
