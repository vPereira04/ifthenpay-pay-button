<?php
/**
 * The Default Design screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\AiStudioPage;
use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\Advanced;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\CustomCode;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The site's defaults, in three tabs, each with a live preview: how the checkout, the pay page and the popups look,
 * how the default button looks, and what the popups say. A button can still set its own of each in its editor.
 *
 * @phpstan-import-type ButtonDesignConfig from ButtonDesign
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
	 * @param string $tab A tab key: `look`, `button` or `messages`.
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

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_style( 'iftp-btn-buttons' );
		wp_enqueue_style( ButtonRenderer::STYLE_HANDLE );
		// The Edit with AI button and the note over the AI's unsaved version.
		wp_enqueue_style( AiPanel::STYLE_HANDLE, Plugin::asset_url( 'assets/css/ai.css' ), array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( 'assets/css/ai.css' ) );
		wp_add_inline_style( 'iftp-btn-buttons', ButtonsPage::theme_button_css() . LookControls::swatch_css() );
		// The pay page's background image and video come from the Media Library.
		wp_enqueue_media();
		Plugin::enqueue_split_script( 'iftp-btn-buttons', 'assets/js/buttons', Plugin::BUTTONS_SCRIPT_PARTS, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE, Plugin::OPEN_FX_HANDLE ) );
		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnButtons',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => '',
				'lookPresets'  => CheckoutLook::presets(),
				'lookFonts'    => CheckoutLook::FONT_STACKS,
				'lookWidths'   => CheckoutLook::WIDTHS,
				'lookSkins'    => CheckoutLook::card_skins(),
				'presets'      => ButtonDesign::presets(),
				'pill'         => ButtonDesign::PILL,
				'gradientTurn' => ButtonDesign::GRADIENT_TURN,
				'advanced'     => Advanced::script_data( wp_create_nonce( ButtonsPage::AJAX_NONCE ) ),
				'sample'       => self::sample_values(),
				'i18n'         => array(
					'copied'     => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed' => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					'pill'       => __( 'Pill', 'ifthenpay-pay-button' ),
					/* translators: %d: where a colour sits on the spectrum bar, in percent */
					'stopAt'     => __( 'Colour at %d%', 'ifthenpay-pay-button' ),
					/* translators: %d: corner radius in pixels */
					'px'         => __( '%d px', 'ifthenpay-pay-button' ),
					'unsaved'    => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
					'pickImage'  => __( 'Pay page background', 'ifthenpay-pay-button' ),
					'useImage'   => __( 'Use this image', 'ifthenpay-pay-button' ),
					'pickVideo'  => __( 'Pay page video', 'ifthenpay-pay-button' ),
					'useVideo'   => __( 'Use this video', 'ifthenpay-pay-button' ),
					'codeOk'     => __( 'Looks good.', 'ifthenpay-pay-button' ),
					'codeLength' => __( 'Too long: 30,000 characters at most.', 'ifthenpay-pay-button' ),
					'codeOpen'   => __( 'A bracket or a quote is never closed.', 'ifthenpay-pay-button' ),
					'codeBanned' => __( 'Not allowed here: a closing script tag or an HTML comment opener, which would break the page.', 'ifthenpay-pay-button' ),
					/* translators: %s: the browser's description of the mistake */
					'codeSyntax' => __( 'JavaScript mistake: %s', 'ifthenpay-pay-button' ),
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

		$rejected = array();

		// Custom CSS is as powerful as unfiltered HTML, so only those who may post that can change it. A box that
		// fails its checks keeps the last good version, and the notice says why.
		if ( current_user_can( 'unfiltered_html' ) ) {
			$look['css'] = is_string( $look['css'] ?? null ) ? trim( $look['css'] ) : '';
			$error       = CustomCode::css_error( $look['css'] );

			if ( null !== $error ) {
				$rejected['checkout_css'] = $error;
			}
		}

		if ( ! current_user_can( 'unfiltered_html' ) || array() !== $rejected ) {
			$look['css'] = $this->settings->get_look()['css'];
		}

		$this->settings->set_look( CheckoutLook::sanitize( $look ) );

		// ButtonDesign::sanitize() cleans every key by its own rules, so here it's only unslashed.
		$this->settings->set_button_design( ButtonDesign::sanitize( isset( $_POST['button_design'] ) && is_array( $_POST['button_design'] ) ? map_deep( wp_unslash( $_POST['button_design'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$text     = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$type     = $text( 'paid_type' );
		$messages = array();
		$titles   = array();

		foreach ( CheckoutSettings::OUTCOMES as $outcome ) {
			$messages[ $outcome ] = isset( $_POST['message'][ $outcome ] ) && is_string( $_POST['message'][ $outcome ] ) ? wp_kses_post( wp_unslash( $_POST['message'][ $outcome ] ) ) : '';
			$titles[ $outcome ]   = isset( $_POST['title'][ $outcome ] ) && is_string( $_POST['title'][ $outcome ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['title'][ $outcome ] ) ), 0, 120 ) : '';
		}

		/**
		 * The loop above fills every outcome.
		 *
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $messages
		 * @var array{paid: string, pending: string, failed: string, cancelled: string} $titles
		 */
		$this->settings->set_confirmation(
			array(
				'paid_type'    => in_array( $type, CheckoutSettings::PAID_TYPES, true ) ? $type : 'popup',
				'paid_page'    => absint( $text( 'paid_page' ) ),
				'paid_url'     => esc_url_raw( $text( 'paid_url' ), array( 'http', 'https' ) ),
				'show_details' => isset( $_POST['show_details'] ),
				'messages'     => $messages,
				'titles'       => $titles,
			)
		);

		// Once the version the chat built is kept, the chat has no use left, so it goes.
		if ( isset( $_POST[ AiStudioPage::DRAFT_VAR ] ) && is_string( $_POST[ AiStudioPage::DRAFT_VAR ] ) ) {
			( new Studio() )->forget( sanitize_key( wp_unslash( $_POST[ AiStudioPage::DRAFT_VAR ] ) ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Back to the tab they saved from.
		$args = array( 'iftp_notice' => array() === $rejected ? 'saved' : 'code' );

		if ( array() !== $rejected ) {
			// Which boxes failed and why, as field:reason pairs; render() turns them into the notice.
			$args['iftp_code'] = implode( ',', array_map( static fn( string $field, string $error ): string => $field . ':' . $error, array_keys( $rejected ), $rejected ) );
		}

		wp_safe_redirect( add_query_arg( $args, self::url() ) . ( in_array( $tab, array( 'button', 'messages' ), true ) ? '#' . $tab : '' ) );
		exit;
	}

	/**
	 * What the redirect after a save says about custom code that was refused.
	 *
	 * @return list<string>
	 */
	private static function rejected_code(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, and only fixed field and reason names get through.
		$sent     = isset( $_GET['iftp_code'] ) && is_string( $_GET['iftp_code'] ) ? sanitize_text_field( wp_unslash( $_GET['iftp_code'] ) ) : '';
		$messages = array();

		foreach ( explode( ',', $sent ) as $pair ) {
			list( $field, $error ) = array_pad( explode( ':', $pair, 2 ), 2, '' );

			if ( in_array( $field, array_merge( CustomCode::FIELDS, array( 'checkout_css' ) ), true ) && in_array( $error, array( CustomCode::ERROR_LENGTH, CustomCode::ERROR_UNBALANCED, CustomCode::ERROR_FORBIDDEN ), true ) ) {
				$messages[] = CustomCode::message( $field, $error );
			}
		}

		return $messages;
	}

	/**
	 * Renders the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$tabs      = array(
			'look'     => __( 'Checkout & pay page', 'ifthenpay-pay-button' ),
			'button'   => __( 'Button', 'ifthenpay-pay-button' ),
			'messages' => __( 'Messages', 'ifthenpay-pay-button' ),
		);
		$connected = ( new SettingsRepository() )->is_connected();
		$draft     = $connected ? $this->ai_draft() : null;
		$look      = null !== $draft ? $draft->config['look'] : $this->settings->get_look();
		$design    = null !== $draft ? $draft->config['design'] : $this->settings->get_button_design();
		$actions   = $connected ? '<a class="iftp-btn-btn iftp-btn-btn--ai" href="' . esc_url( AiStudioPage::url( Studio::site_button() ) ) . '">' . AiPanel::sparkle() . esc_html__( 'Edit with AI', 'ifthenpay-pay-button' ) . '</a>' : '';
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-checkout-settings">
			<?php ButtonsPage::print_header( __( 'Default Design', 'ifthenpay-pay-button' ), $actions ); ?>
			<?php
			if ( null !== $draft ) {
				$this->render_draft_note( $this->ai_chat() );
			}
			?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag from the redirect after a checked save.
			if ( isset( $_GET['iftp_notice'] ) ) {
				$notice = is_string( $_GET['iftp_notice'] ) ? sanitize_key( wp_unslash( $_GET['iftp_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only compared, never printed.
				Toast::add( esc_html( 'code' === $notice ? __( 'The other settings were saved.', 'ifthenpay-pay-button' ) : __( 'Settings saved.', 'ifthenpay-pay-button' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only compared, never printed.

				foreach ( self::rejected_code() as $message ) {
					Toast::add( esc_html( $message ), 'error' );
				}
			}
			?>

			<form class="iftp-btn-csettings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-iftp-csettings<?php echo null !== $draft ? ' data-iftp-start-dirty' : ''; ?>>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<?php if ( null !== $draft ) : ?>
					<input type="hidden" name="<?php echo esc_attr( AiStudioPage::DRAFT_VAR ); ?>" value="<?php echo esc_attr( $this->ai_chat() ); ?>" />
				<?php endif; ?>
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
							<?php ( new LookControls( 'look' ) )->render_cards( $look, true, $this->settings->get_look() ); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-button" aria-labelledby="iftp-btn-tab-button" data-iftp-panel="button">
							<?php $this->render_button( $design ); ?>
						</div>
						<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-messages" aria-labelledby="iftp-btn-tab-messages" data-iftp-panel="messages">
							<p class="iftp-btn-texts__intro">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: link to Configuration › Texts */
										__( 'These are the popup and page after paying. The words on the form, its buttons and its error messages are in %s.', 'ifthenpay-pay-button' ),
										'<a href="' . esc_url( ConfigurationPage::url() . '#texts' ) . '">' . esc_html__( 'Configuration › Texts', 'ifthenpay-pay-button' ) . '</a>'
									),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</p>
							<?php MessagesFields::render( $this->settings->get_confirmation(), true ); ?>
						</div>
					</div>
					<aside class="iftp-btn-lookpage__aside">
						<div class="iftp-btn-lookpage__sticky">
							<div data-iftp-aside="look">
								<?php ( new LookControls( 'look' ) )->render_preview( $look, $this->settings->get_confirmation() ); ?>
							</div>
							<section class="iftp-btn-preview" aria-labelledby="iftp-btn-preview-title" data-iftp-aside="button">
								<?php DesignControls::render_stage( $design, CheckoutTexts::get( 'pay_now' ), self::preview_logos() ); ?>
							</section>
						</div>
					</aside>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * The chat the address asks to open the version of, if it made one.
	 */
	private function ai_chat(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which version to show; saving checks its own nonce.
		$asked = isset( $_GET[ AiStudioPage::DRAFT_VAR ] ) && is_string( $_GET[ AiStudioPage::DRAFT_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ AiStudioPage::DRAFT_VAR ] ) ) : '';

		return '' !== $asked ? ( new Studio() )->resolve( Studio::site_button(), $asked ) : '';
	}

	/**
	 * The stand-in holding the chat's latest version of the Default Design, or null when the chat made none.
	 */
	private function ai_draft(): ?Button {
		$chat = $this->ai_chat();

		return '' !== $chat ? ( new Studio() )->draft( Studio::site_button(), $chat ) : null;
	}

	/**
	 * Says the form holds the AI's version, which isn't saved until Save Settings.
	 *
	 * @param string $chat The chat that made it.
	 */
	private function render_draft_note( string $chat ): void {
		?>
		<section class="iftp-btn-ai-note" aria-labelledby="iftp-btn-ai-note-title">
			<span class="iftp-btn-ai-note__mark" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
			<div class="iftp-btn-ai-note__body">
				<h2 id="iftp-btn-ai-note-title" class="iftp-btn-ai-note__title"><?php esc_html_e( 'The AI\'s changes, not saved yet', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-ai-note__text"><?php esc_html_e( 'Look them over and save to put them live on every button that follows the Default Design. Leave without saving and the Default Design stays as it was.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<a class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-ai-note__back" href="<?php echo esc_url( AiStudioPage::url( Studio::site_button(), $chat ) ); ?>"><?php esc_html_e( 'Back to the chat', 'ifthenpay-pay-button' ); ?></a>
		</section>
		<?php
	}

	/**
	 * The Button tab: the design every button set to "Default design" wears.
	 *
	 * @param array<string, mixed> $design The design to show.
	 * @phpstan-param ButtonDesignConfig $design
	 */
	private function render_button( array $design ): void {
		?>
		<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-default-design">
			<div class="iftp-btn-ecard__intro">
				<h2 class="iftp-btn-ecard__title" id="iftp-btn-default-design"><?php esc_html_e( 'Default button design', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-ecard__description"><?php esc_html_e( 'Every button set to Default design wears this, so many buttons across your site look the same and change together. A button can still have its own in its editor, under Design.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-ecard__body">
				<?php ( new DesignControls( 'button_design', false, $this->settings->get_button_design() ) )->render( $design ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * The payment logos under the Button tab's preview: the default Gateway Key's methods.
	 */
	private static function preview_logos(): string {
		$html = '';

		$settings = new SettingsRepository();
		$offered  = array_column( $settings->get_enabled_methods(), 'entity' );

		foreach ( $settings->get_methods() as $method ) {
			if ( in_array( $method['entity'], $offered, true ) && '' !== (string) ( $method['logo'] ?? '' ) ) {
				$html .= '<img src="' . esc_url( $method['logo'] ?? '' ) . '"' . ButtonRenderer::dark_logo_attr( $method ) . ' alt="" height="18" loading="lazy" />';
			}
		}

		return $html;
	}
}
