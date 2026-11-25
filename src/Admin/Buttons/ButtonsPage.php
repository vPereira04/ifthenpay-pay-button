<?php
/**
 * The Buttons screen: the list and the editor.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\AiPanel;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\ConfigurationPage;
use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\LookControls;
use Ifthenpay\PayButton\Admin\Menu;
use Ifthenpay\PayButton\Admin\Toast;
use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Advanced;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Buttons\FieldIcons;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Buttons\Templates;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\CustomCode;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\Shortcode;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Reports\CheckoutOpens;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Reports\TransactionReports;
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
	public const RESTORE_ACTION   = 'iftp_btn_restore_button';
	public const TEMPLATE_ACTION  = 'iftp_btn_start_template';
	public const SKIP_ACTION      = 'iftp_btn_skip_welcome';
	public const AJAX_NONCE       = 'iftp_btn_buttons';

	// User meta: set once they skip the welcome screen.
	public const WELCOME_SKIPPED_META = 'iftp_btn_welcome_skipped';

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
	 * The aggregate reads for the list's stats.
	 *
	 * @var TransactionReports
	 */
	private TransactionReports $reports;

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
	 * The Advanced designs already printed on the list, by slug, so each is carried once.
	 *
	 * @var array<string, bool>
	 */
	private array $designs = array();

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
		$this->reports           = new TransactionReports( $transactions );
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

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_style( 'iftp-btn-buttons' );
		wp_enqueue_style( ButtonRenderer::STYLE_HANDLE );
		wp_add_inline_style( 'iftp-btn-buttons', self::theme_button_css() . LookControls::swatch_css() );
		// A button's own pay page background image and video come from the Media Library.
		wp_enqueue_media();
		// MIT-licensed QR encoder, bundled: the code is drawn in the browser, nothing is sent anywhere.
		wp_enqueue_script( 'iftp-btn-qrcode', IFTP_BTN_URL . 'assets/js/vendor/qrcode.js', array(), '1.4.4', true );
		Plugin::enqueue_split_script( 'iftp-btn-buttons', 'assets/js/buttons', Plugin::BUTTONS_SCRIPT_PARTS, array( Plugin::UI_SCRIPT_HANDLE, Plugin::METHOD_LOGOS_HANDLE, Plugin::VIDEO_LOOP_HANDLE, Plugin::OPEN_FX_HANDLE, 'iftp-btn-qrcode' ) );

		$studio = $this->requested_studio();

		Tour::enqueue( false !== $studio, $this->settings->is_connected() );

		if ( false !== $studio ) {
			( new AiStudioPage() )->enqueue( $studio );
		} else {
			// The list's and the editor's AI cards wear the studio's look.
			wp_enqueue_style( AiPanel::STYLE_HANDLE, Plugin::asset_url( 'assets/css/ai.css' ), array( Plugin::ADMIN_STYLE_HANDLE ), Plugin::asset_version( 'assets/css/ai.css' ) );
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
				'lookFonts'    => CheckoutLook::FONT_STACKS,
				'lookWidths'   => CheckoutLook::WIDTHS,
				'lookSkins'    => CheckoutLook::card_skins(),
				'siteLook'     => self::site_look_for_js(),
				'siteDesign'   => $this->checkout_settings->get_button_design(),
				'advanced'     => Advanced::script_data( wp_create_nonce( self::AJAX_NONCE ) ),
				'sample'       => CheckoutSettingsPage::sample_values(),
				'i18n'         => array(
					'codeOk'      => __( 'Looks good.', 'ifthenpay-pay-button' ),
					'codeLength'  => __( 'Too long: 30,000 characters at most.', 'ifthenpay-pay-button' ),
					'codeOpen'    => __( 'A bracket or a quote is never closed.', 'ifthenpay-pay-button' ),
					'codeBanned'  => __( 'Not allowed here: a closing script tag or an HTML comment opener, which would break the page.', 'ifthenpay-pay-button' ),
					'codeNest'    => __( '@import, @layer and similar at-rules cannot sit inside one button\'s scope. Use @media or @supports.', 'ifthenpay-pay-button' ),
					/* translators: %s: the browser's description of the mistake */
					'codeSyntax'  => __( 'JavaScript mistake: %s', 'ifthenpay-pay-button' ),
					'copied'      => __( 'Copied.', 'ifthenpay-pay-button' ),
					'copyFailed'  => __( 'Could not copy. Select the text and copy it yourself.', 'ifthenpay-pay-button' ),
					/* translators: %s: the button's title */
					'deleteNamed' => __( 'Delete “%s”?', 'ifthenpay-pay-button' ),
					'qrFailed'    => __( 'Could not make the QR code. Copy the pay link instead.', 'ifthenpay-pay-button' ),
					'sending'     => __( 'Sending…', 'ifthenpay-pay-button' ),
					'error'       => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
					'payNow'      => CheckoutTexts::get( 'pay_now' ),
					'between'     => CheckoutTexts::get( 'between' ),
					/* translators: 1: the button text, 2: the amount */
					'withAmount'  => __( '%1$s · %2$s', 'ifthenpay-pay-button' ),
					'from'        => CheckoutTexts::get( 'from' ),
					'spots'       => array(
						'spots'       => __( 'Spots', 'ifthenpay-pay-button' ),
						'price'       => __( 'Price', 'ifthenpay-pay-button' ),
						'unlimited'   => __( 'No limit', 'ifthenpay-pay-button' ),
						'samePrice'   => __( 'Button amount', 'ifthenpay-pay-button' ),
						/* translators: %d: how many spots the options have together */
						'sum'         => __( 'The options add up to %d spots.', 'ifthenpay-pay-button' ),
						'shareTotal'  => __( 'Options with no number of their own share the total spots.', 'ifthenpay-pay-button' ),
						'noLimit'     => __( 'Options with no number of their own have no limit.', 'ifthenpay-pay-button' ),
						'noField'     => __( 'No choice field in the checkout form yet', 'ifthenpay-pay-button' ),
						'needField'   => __( 'Add a Dropdown, Single choice or Multiple choice field to the checkout form, then pick it here.', 'ifthenpay-pay-button' ),
						'needTotal'   => __( 'Enter how many spots there are.', 'ifthenpay-pay-button' ),
						'option'      => __( 'Option', 'ifthenpay-pay-button' ),
						'priceEur'    => __( 'Price (€)', 'ifthenpay-pay-button' ),
						'noPrice'     => __( 'Free', 'ifthenpay-pay-button' ),
						'pickField'   => __( 'Choose a field', 'ifthenpay-pay-button' ),
						'removeField' => __( 'Remove this field', 'ifthenpay-pay-button' ),
					),
					'batchNone'   => __( 'Pick at least one button and an action.', 'ifthenpay-pay-button' ),
					'batchCount'  => array(
						'one'   => __( '1 selected', 'ifthenpay-pay-button' ),
						/* translators: %s: how many buttons are picked */
						'other' => __( '%s selected', 'ifthenpay-pay-button' ),
						'none'  => __( 'None selected', 'ifthenpay-pay-button' ),
					),
					/* translators: %s: how many buttons */
					'batchDelete' => __( 'Move %s buttons to the trash?', 'ifthenpay-pay-button' ),
					'turnedOn'    => __( 'Button turned on. It takes payments again.', 'ifthenpay-pay-button' ),
					'turnedOff'   => __( 'Button turned off. It shows disabled and its pay page is gone.', 'ifthenpay-pay-button' ),
					'active'      => __( 'Active', 'ifthenpay-pay-button' ),
					'inactive'    => __( 'Inactive', 'ifthenpay-pay-button' ),
					'pill'        => __( 'Pill', 'ifthenpay-pay-button' ),
					/* translators: %d: where a colour sits on the spectrum bar, in percent */
					'stopAt'      => __( 'Colour at %d%', 'ifthenpay-pay-button' ),
					/* translators: %d: corner radius in pixels */
					'px'          => __( '%d px', 'ifthenpay-pay-button' ),
					'unsaved'     => __( 'You have unsaved changes. Leave anyway?', 'ifthenpay-pay-button' ),
					'pickImage'   => __( 'Pay page background', 'ifthenpay-pay-button' ),
					'useImage'    => __( 'Use this image', 'ifthenpay-pay-button' ),
					'pickVideo'   => __( 'Pay page video', 'ifthenpay-pay-button' ),
					'useVideo'    => __( 'Use this video', 'ifthenpay-pay-button' ),
					'fb'          => array(
						'required'        => __( 'Required', 'ifthenpay-pay-button' ),
						'optional'        => __( 'Optional', 'ifthenpay-pay-button' ),
						'optionalWord'    => CheckoutTexts::get( 'optional' ),
						'half'            => __( 'Half width', 'ifthenpay-pay-button' ),
						'label'           => __( 'Label', 'ifthenpay-pay-button' ),
						'heading'         => __( 'Heading', 'ifthenpay-pay-button' ),
						'type'            => __( 'Type', 'ifthenpay-pay-button' ),
						'tag'             => __( 'Merge tag', 'ifthenpay-pay-button' ),
						/* translators: 1 and 2: example tags in braces, written as {first_name} and {first_name|there} */
						'liveHint'        => sprintf( __( 'Show what the payer typed in another field: write its tag in braces, like %1$s. For when it is still empty, add a fallback: %2$s. Works in this text, in field labels and in help texts.', 'ifthenpay-pay-button' ), '{first_name}', '{first_name|there}' ),
						'placeholder'     => __( 'Placeholder', 'ifthenpay-pay-button' ),
						'icon'            => __( 'Icon before the title', 'ifthenpay-pay-button' ),
						'noIcon'          => __( 'No icon', 'ifthenpay-pay-button' ),
						'icons'           => FieldIcons::paths(),
						'firstOption'     => __( 'Text before a choice', 'ifthenpay-pay-button' ),
						'options'         => __( 'Options', 'ifthenpay-pay-button' ),
						'optionsHint'     => __( 'One per line', 'ifthenpay-pay-button' ),
						'option'          => __( 'Option', 'ifthenpay-pay-button' ),
						'pictures'        => __( 'Option pictures', 'ifthenpay-pay-button' ),
						'pictureShape'    => __( 'Picture shape', 'ifthenpay-pay-button' ),
						'shapes'          => array(
							'auto'      => __( 'Auto', 'ifthenpay-pay-button' ),
							'landscape' => __( 'Landscape', 'ifthenpay-pay-button' ),
							'square'    => __( 'Square', 'ifthenpay-pay-button' ),
							'portrait'  => __( 'Portrait', 'ifthenpay-pay-button' ),
						),
						'layoutLabel'     => __( 'Layout', 'ifthenpay-pay-button' ),
						'layouts'         => array(
							'grid'   => __( 'Grid', 'ifthenpay-pay-button' ),
							'scroll' => __( 'Scrolling row', 'ifthenpay-pay-button' ),
						),
						'colsLabel'       => __( 'Per line', 'ifthenpay-pay-button' ),
						'colsAuto'        => __( 'Auto', 'ifthenpay-pay-button' ),
						'colsHint'        => __( 'Auto fits as many as the size allows. On small screens the count comes down by itself.', 'ifthenpay-pay-button' ),
						'sizeLabel'       => __( 'Card size', 'ifthenpay-pay-button' ),
						'sizes'           => array(
							'small'  => __( 'Small', 'ifthenpay-pay-button' ),
							'medium' => __( 'Medium', 'ifthenpay-pay-button' ),
							'large'  => __( 'Large', 'ifthenpay-pay-button' ),
						),
						'sizeHint'        => __( 'Used when Per line is Auto.', 'ifthenpay-pay-button' ),
						'shapeHint'       => __( 'Auto shows the pictures whole when they all share one shape, like book covers, and crops to landscape when they differ.', 'ifthenpay-pay-button' ),
						'showPictures'    => __( 'Show the options as picture cards', 'ifthenpay-pay-button' ),
						'picturesHint'    => __( 'Every option becomes a card. One without a picture gets a coloured tile with its first letter.', 'ifthenpay-pay-button' ),
						'addPicture'      => __( 'Add a picture', 'ifthenpay-pay-button' ),
						'changePicture'   => __( 'Change the picture', 'ifthenpay-pay-button' ),
						'removePicture'   => __( 'Remove', 'ifthenpay-pay-button' ),
						'pictureTitle'    => __( 'Option picture', 'ifthenpay-pay-button' ),
						'usePicture'      => __( 'Use this picture', 'ifthenpay-pay-button' ),
						'help'            => __( 'Help text', 'ifthenpay-pay-button' ),
						'text'            => __( 'Text', 'ifthenpay-pay-button' ),
						'mustAnswer'      => __( 'The payer must answer', 'ifthenpay-pay-button' ),
						'full'            => __( 'Full width', 'ifthenpay-pay-button' ),
						'halfWidth'       => __( 'Half', 'ifthenpay-pay-button' ),
						/* translators: %d: the page number */
						'page'            => __( 'Page %d', 'ifthenpay-pay-button' ),
						'pageTitle'       => __( 'Page title', 'ifthenpay-pay-button' ),
						'firstStep'       => CheckoutTexts::get( 'first_step' ),
						'amount'          => __( 'Amount', 'ifthenpay-pay-button' ),
						'amountHint'      => __( 'Shown when the payer types the amount', 'ifthenpay-pay-button' ),
						'details'         => __( 'Your details', 'ifthenpay-pay-button' ),
						'detailsHint'     => __( 'Name, email and the rest, set in Payer details', 'ifthenpay-pay-button' ),
						'up'              => __( 'Move up', 'ifthenpay-pay-button' ),
						'down'            => __( 'Move down', 'ifthenpay-pay-button' ),
						'remove'          => __( 'Remove', 'ifthenpay-pay-button' ),
						'choose'          => CheckoutTexts::get( 'choose' ),
						'stepOf'          => CheckoutTexts::get( 'step_of' ),
						'spotsRequired'   => __( 'Spots are split by this field, so the payer has to answer it.', 'ifthenpay-pay-button' ),
						'showWhen'        => __( 'Show this', 'ifthenpay-pay-button' ),
						'always'          => __( 'Always', 'ifthenpay-pay-button' ),
						/* translators: %s: the label of an earlier choice field, e.g. "Day" */
						'whenField'       => __( 'Only when “%s”…', 'ifthenpay-pay-button' ),
						'whenIs'          => __( 'is one of', 'ifthenpay-pay-button' ),
						'whenNot'         => __( 'is none of', 'ifthenpay-pay-button' ),
						'opGt'            => __( 'is more than', 'ifthenpay-pay-button' ),
						'opLt'            => __( 'is less than', 'ifthenpay-pay-button' ),
						'opFilled'        => __( 'is filled in', 'ifthenpay-pay-button' ),
						'opEmpty'         => __( 'is left empty', 'ifthenpay-pay-button' ),
						'whenNumber'      => __( 'Number', 'ifthenpay-pay-button' ),
						'opContains'      => __( 'contains', 'ifthenpay-pay-button' ),
						'opEquals'        => __( 'is exactly', 'ifthenpay-pay-button' ),
						'opNotEquals'     => __( 'is not', 'ifthenpay-pay-button' ),
						'whenText'        => __( 'Text', 'ifthenpay-pay-button' ),
						'whenTypeText'    => __( 'Type the text to look for, or this stays shown for everyone.', 'ifthenpay-pay-button' ),
						'whenType'        => __( 'Type the number to compare with, or this stays shown for everyone.', 'ifthenpay-pay-button' ),
						'showPage'        => __( 'Show this page', 'ifthenpay-pay-button' ),
						'pageRules'       => __( 'Show this page only for some answers', 'ifthenpay-pay-button' ),
						'whenPageAnchors' => __( 'This page holds the amount or the payer details, so it always shows.', 'ifthenpay-pay-button' ),
						'whenPageSpots'   => __( 'Spots are split by a field on this page, so the page always shows.', 'ifthenpay-pay-button' ),
						'rules'           => __( 'Limits', 'ifthenpay-pay-button' ),
						'noLimit'         => __( 'No limit', 'ifthenpay-pay-button' ),
						'ruleMin'         => __( 'Smallest number', 'ifthenpay-pay-button' ),
						'ruleMax'         => __( 'Largest number', 'ifthenpay-pay-button' ),
						'ruleWhole'       => __( 'Whole numbers only', 'ifthenpay-pay-button' ),
						'ruleMinLen'      => __( 'Fewest characters', 'ifthenpay-pay-button' ),
						'ruleMaxLen'      => __( 'Most characters', 'ifthenpay-pay-button' ),
						'ruleNumberHint'  => __( 'The payer can\'t continue with a number outside these. Leave a box empty for no limit, e.g. a largest of 10 for the number of guests.', 'ifthenpay-pay-button' ),
						'ruleTextHint'    => __( 'Leave a box empty, or 0, for no limit.', 'ifthenpay-pay-button' ),
						'ticked'          => __( 'Ticked', 'ifthenpay-pay-button' ),
						'whenPick'        => __( 'Tick at least one answer, or this stays shown for everyone.', 'ifthenpay-pay-button' ),
						'whenNone'        => __( 'Add a field above this one to show it only for some answers.', 'ifthenpay-pay-button' ),
						'whenSpots'       => __( 'Spots are split by this field, so it always shows.', 'ifthenpay-pay-button' ),
						'conditional'     => __( 'Conditional', 'ifthenpay-pay-button' ),
					),
				),
			)
		);

		// The editor's Gateway tab: swapping the methods when the Gateway Key changes, and asking for a method's activation.
		wp_localize_script(
			'iftp-btn-buttons',
			'iftpBtnGateway',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( SettingsController::NONCE_ACTION ),
				'i18n'    => array(
					'loading'     => __( 'Loading the payment methods…', 'ifthenpay-pay-button' ),
					'error'       => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
					'requesting'  => __( 'Requesting…', 'ifthenpay-pay-button' ),
					'requested'   => __( 'Requested', 'ifthenpay-pay-button' ),
					'requestSent' => __( 'Activation request sent. ifthenpay will get back to you by email.', 'ifthenpay-pay-button' ),
				),
			)
		);
	}

	/**
	 * The site's look for the previews: the editor's, when a button goes back to using it, and Configuration's.
	 *
	 * @return array<string, mixed>
	 */
	public static function site_look_for_js(): array {
		return self::look_for_js( ( new CheckoutSettings() )->get_look() );
	}

	/**
	 * A look for the previews, with its image and video as URLs.
	 *
	 * @param array<string, mixed> $look The look.
	 * @return array<string, mixed>
	 */
	public static function look_for_js( array $look ): array {
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
		$asked  = isset( $_GET[ AiStudioPage::DRAFT_VAR ] ) && $this->settings->is_connected() ? sanitize_key( wp_unslash( $_GET[ AiStudioPage::DRAFT_VAR ] ) ) : null;
		$studio = new Studio();
		$chat   = null !== $asked ? $studio->resolve( $button, $asked ) : '';
		$draft  = null !== $asked ? $studio->draft( $button, $chat ) : null;

		( new ButtonEditor( $this->settings, new PayLinks(), $this->checkout_settings ) )->render( $button, $draft, $chat );
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

		if ( Studio::SITE_TARGET === $target ) {
			return Studio::site_button();
		}

		return $this->buttons->find( absint( $target ) ) ?? false;
	}

	/**
	 * The sidebar-icon button that folds and unfolds WordPress's own left menu. It stays hidden until ui.js finds the menu's
	 * collapse button to drive, so it never shows where it can't work.
	 */
	public static function menu_toggle(): string {
		$hide = __( 'Hide the WordPress menu', 'ifthenpay-pay-button' );
		$show = __( 'Show the WordPress menu', 'ifthenpay-pay-button' );

		return '<button type="button" class="iftp-btn-menutoggle" data-iftp-menu-toggle data-hide="' . esc_attr( $hide ) . '" data-show="' . esc_attr( $show ) . '" aria-controls="adminmenuwrap" aria-expanded="true" aria-label="' . esc_attr( $hide ) . '" title="' . esc_attr( $hide ) . '" hidden>'
			. '<svg class="iftp-btn-menutoggle__open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16" /><rect x="3" y="4" width="6" height="16" rx="2" fill="currentColor" stroke="none"/></svg>'
			. '<svg class="iftp-btn-menutoggle__closed" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16"/></svg>'
			. '</button>';
	}

	/**
	 * The burger button and the four screens it lists, so the plugin stays reachable with WordPress's own menu folded away. The button stays hidden until ui.js sees the menu folded.
	 * The list is a popover, which sits in the top layer and so isn't clipped by the masthead.
	 */
	public static function nav_menu(): string {
		$label = __( 'Pay Button menu', 'ifthenpay-pay-button' );
		$items = array(
			Menu::SLUG                      => __( 'Buttons', 'ifthenpay-pay-button' ),
			EntriesPage::PAGE_SLUG          => __( 'Payments', 'ifthenpay-pay-button' ),
			ConfigurationPage::PAGE_SLUG    => __( 'Configuration', 'ifthenpay-pay-button' ),
			CheckoutSettingsPage::PAGE_SLUG => __( 'Default Design', 'ifthenpay-pay-button' ),
		);

		// Payments only exists once a Backoffice Key is connected.
		if ( ! ( new SettingsRepository() )->is_connected() ) {
			unset( $items[ EntriesPage::PAGE_SLUG ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only marks which link is current.
		$current = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$html = '<button type="button" class="iftp-btn-navtoggle" data-iftp-nav-toggle popovertarget="iftp-btn-nav" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '" hidden>'
			. '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 7h16M4 12h16M4 17h16"/></svg>'
			. '</button>'
			. '<nav id="iftp-btn-nav" class="iftp-btn-navmenu" popover aria-label="' . esc_attr( $label ) . '">';

		foreach ( $items as $slug => $text ) {
			$url   = add_query_arg( array( 'page' => $slug ), admin_url( 'admin.php' ) );
			$html .= '<a class="iftp-btn-navmenu__link" href="' . esc_url( $url ) . '"' . ( $slug === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $text ) . '</a>';
		}

		return $html . '</nav>';
	}

	/**
	 * The masthead's menu controls: the burger list and the button that folds WordPress's menu.
	 */
	public static function menu_controls(): string {
		return self::nav_menu() . self::menu_toggle();
	}

	/**
	 * The header every view shares.
	 *
	 * @param string $title   The heading.
	 * @param string $actions Markup for the right-hand actions, already escaped.
	 * @param string $beside  Markup that sits next to the title, already escaped.
	 */
	public static function print_header( string $title, string $actions = '', string $beside = '' ): void {
		?>
		<header class="iftp-btn-masthead">
			<div class="iftp-btn-masthead__brand">
				<?php echo self::menu_controls(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup, escaped in nav_menu() and menu_toggle(). ?>
				<a class="iftp-btn-masthead__home" href="<?php echo esc_url( self::list_url() ); ?>" title="<?php esc_attr_e( 'Pay buttons', 'ifthenpay-pay-button' ); ?>">
					<img class="iftp-btn-masthead__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="<?php esc_attr_e( 'ifthenpay, back to Pay buttons', 'ifthenpay-pay-button' ); ?>" width="94" height="24" />
				</a>
				<span class="iftp-btn-masthead__divider" aria-hidden="true"></span>
				<div>
					<h1 class="iftp-btn-masthead__title"><?php echo esc_html( $title ); ?></h1>
				</div>
				<?php echo $beside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers escape it. ?>
			</div>
			<?php if ( '' !== $actions ) : ?>
				<div class="iftp-btn-masthead__actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers escape it. ?></div>
			<?php endif; ?>
		</header>
		<hr class="wp-header-end" />
		<?php
	}

	/**
	 * What a save says about custom code that was refused.
	 *
	 * @return list<string>
	 */
	private static function rejected_code(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, and only fixed field and reason names get through.
		$sent     = isset( $_GET['iftp_code'] ) && is_string( $_GET['iftp_code'] ) ? sanitize_text_field( wp_unslash( $_GET['iftp_code'] ) ) : '';
		$messages = array();
		$reasons  = array( CustomCode::ERROR_LENGTH, CustomCode::ERROR_UNBALANCED, CustomCode::ERROR_FORBIDDEN, CustomCode::ERROR_UNNESTABLE );

		foreach ( explode( ',', $sent ) as $pair ) {
			list( $field, $error ) = array_pad( explode( ':', $pair, 2 ), 2, '' );

			if ( in_array( $field, CustomCode::FIELDS, true ) && in_array( $error, $reasons, true ) ) {
				$messages[] = CustomCode::message( $field, $error );
			}
		}

		return $messages;
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
			'started'    => array( 'success', __( 'Here is your starter button. Change anything, then turn it on and save.', 'ifthenpay-pay-button' ) ),
			'created'    => array( 'success', __( 'Button created. Place it with the block, the shortcode or its link.', 'ifthenpay-pay-button' ) ),
			'duplicated' => array( 'success', __( 'Button copied. The copy is off until you turn it on.', 'ifthenpay-pay-button' ) ),
			'deleted'    => array( 'success', __( 'Button moved to the trash. Its payments are still in Payments.', 'ifthenpay-pay-button' ) ),
			'restored'   => array( 'success', __( 'Button restored.', 'ifthenpay-pay-button' ) ),
			'error'      => array( 'error', __( 'That did not work. Please try again.', 'ifthenpay-pay-button' ) ),
		);

		if ( 'batch' === $notice ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags the redirect after a checked action set.
			$op = isset( $_GET['iftp_op'] ) ? sanitize_key( wp_unslash( $_GET['iftp_op'] ) ) : '';
			$n  = isset( $_GET['iftp_n'] ) ? absint( wp_unslash( $_GET['iftp_n'] ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$batch = array(
				/* translators: %s: how many buttons */
				'activate'   => _n( '%s button turned on.', '%s buttons turned on.', $n, 'ifthenpay-pay-button' ),
				/* translators: %s: how many buttons */
				'deactivate' => _n( '%s button turned off.', '%s buttons turned off.', $n, 'ifthenpay-pay-button' ),
				/* translators: %s: how many buttons */
				'duplicate'  => _n( '%s button copied. The copy is off until you turn it on.', '%s buttons copied. The copies are off until you turn them on.', $n, 'ifthenpay-pay-button' ),
				/* translators: %s: how many buttons */
				'delete'     => _n( '%s button moved to the trash. Its payments are still in Payments.', '%s buttons moved to the trash. Their payments are still in Payments.', $n, 'ifthenpay-pay-button' ),
			);

			if ( $n > 0 && isset( $batch[ $op ] ) ) {
				Toast::add( esc_html( sprintf( $batch[ $op ], number_format_i18n( $n ) ) ) );
			}

			return;
		}

		if ( isset( $messages[ $notice ] ) ) {
			$text = esc_html( $messages[ $notice ][1] );

			// A delete only trashed the button, so the notice offers Undo.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, the restore link carries its own nonce.
			$restore = 'deleted' === $notice && isset( $_GET['iftp_restore'] ) ? absint( wp_unslash( $_GET['iftp_restore'] ) ) : 0;

			if ( $restore > 0 ) {
				$text .= ' <a href="' . esc_url( self::action_url( self::RESTORE_ACTION, $restore ) ) . '"><strong>' . esc_html__( 'Undo', 'ifthenpay-pay-button' ) . '</strong></a>';
			}

			Toast::add( $text, $messages[ $notice ][0] );

			foreach ( self::rejected_code() as $problem ) {
				Toast::add( esc_html( $problem ), 'error' );
			}
		}
	}

	/**
	 * The list: totals, a search and filter, then one card per button showing how it looks and how it's doing.
	 */
	private function render_list(): void {
		$buttons   = $this->buttons->all();
		$ids       = array_map( static fn( Button $button ): int => $button->id, $buttons );
		$stats     = $this->reports->button_stats( $ids );
		$taken     = $this->transactions->spots_taken( array_map( static fn( Button $button ): int => $button->id, array_filter( $buttons, array( Spots::class, 'limited' ) ) ) );
		$opens     = CheckoutOpens::counts( $ids );
		$paid      = $this->reports->paid_since( array_map( static fn( array $count ): int => $count['since'], $opens ) );
		$connected = $this->settings->is_connected();
		$links     = new PayLinks();
		$order     = array_flip( $ids );
		$skipped   = '1' === get_user_meta( get_current_user_id(), self::WELCOME_SKIPPED_META, true );

		$new  = $connected ? '<a class="iftp-btn-btn iftp-btn-btn--ai" data-iftp-tour="ai" href="' . esc_url( AiStudioPage::url( null ) ) . '">' . AiPanel::sparkle() . esc_html__( 'Build with AI', 'ifthenpay-pay-button' ) . '</a><a class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-tour="new" href="' . esc_url( self::edit_url( 0 ) ) . '">' . self::icon( 'plus' ) . esc_html__( 'New button', 'ifthenpay-pay-button' ) . '</a>' : '';
		$help = $connected ? '<button type="button" class="iftp-btn-help" data-iftp-help-open data-iftp-tour="help" aria-haspopup="dialog" aria-label="' . esc_attr__( 'Help: start from a template', 'ifthenpay-pay-button' ) . '" title="' . esc_attr__( 'Help: start from a template', 'ifthenpay-pay-button' ) . '">?</button>' : '';
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-glass" data-iftp-buttons>
			<?php self::print_header( __( 'Pay buttons', 'ifthenpay-pay-button' ), $new, $help ); ?>
			<?php self::print_notice(); ?>

			<?php if ( ! $connected ) : ?>
				<section class="iftp-btn-empty iftp-btn-empty--connect">
					<span class="iftp-btn-mark iftp-btn-empty__mark" aria-hidden="true"><img class="iftp-btn-mark__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/icon-normal.svg' ); ?>" alt="" width="28" height="28" /></span>
					<h2 class="iftp-btn-empty__title"><?php esc_html_e( 'Connect your ifthenpay account first', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-empty__text"><?php esc_html_e( 'Buttons take payments through your ifthenpay Gateway Key. Connect it once, pick the payment methods, and come back here.', 'ifthenpay-pay-button' ); ?></p>
					<a class="iftp-btn-btn iftp-btn-btn--primary" href="<?php echo esc_url( ConfigurationPage::url() ); ?>"><?php esc_html_e( 'Connect ifthenpay', 'ifthenpay-pay-button' ); ?></a>
				</section>
			<?php elseif ( array() === $buttons && ! $skipped ) : ?>
				<?php $this->render_welcome(); ?>
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
				<?php self::print_batch_bar(); ?>
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
					<?php
					// Each design's script runs once over every card that wears it, now that they are all in the page.
					foreach ( array_keys( $this->designs ) as $slug ) {
						echo '<script>' . CustomCode::scope_js( Advanced::scope_name( $slug ), 'button', Advanced::js( $slug ) ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own file.
					}
					?>
					<li class="iftp-btn-bcard iftp-btn-bcard--new" data-iftp-bcard-new>
						<a class="iftp-btn-bcard__half iftp-btn-bcard__half--new" href="<?php echo esc_url( self::edit_url( 0 ) ); ?>">
							<span class="iftp-btn-bcard__new-icon" aria-hidden="true"><?php echo self::icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span class="iftp-btn-bcard__new-title"><?php esc_html_e( 'New button', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-bcard__new-text"><?php esc_html_e( 'Set an amount, pick a look, place it anywhere.', 'ifthenpay-pay-button' ); ?></span>
						</a>
						<span class="iftp-btn-bcard__or" aria-hidden="true"><?php esc_html_e( 'or', 'ifthenpay-pay-button' ); ?></span>
						<a class="iftp-btn-bcard__half iftp-btn-bcard__half--ai" href="<?php echo esc_url( AiStudioPage::url( null ) ); ?>">
							<span class="iftp-btn-bcard__new-icon" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
							<span class="iftp-btn-bcard__new-title"><?php esc_html_e( 'Build with AI', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-bcard__new-text"><?php esc_html_e( 'Describe it in a sentence; watch the form and the look appear.', 'ifthenpay-pay-button' ); ?></span>
						</a>
					</li>
				</ul>
				<nav class="iftp-btn-bpager" data-iftp-bpager aria-label="<?php esc_attr_e( 'Pages', 'ifthenpay-pay-button' ); ?>" hidden>
					<button type="button" class="iftp-btn-btn" data-iftp-bpage="prev"><?php esc_html_e( 'Previous', 'ifthenpay-pay-button' ); ?></button>
					<span class="iftp-btn-bpager__status" data-iftp-bpager-status data-template="<?php /* translators: 1: current page, 2: number of pages */ echo esc_attr_x( 'Page %1$s of %2$s', 'pager', 'ifthenpay-pay-button' ); ?>"></span>
					<button type="button" class="iftp-btn-btn" data-iftp-bpage="next"><?php esc_html_e( 'Next', 'ifthenpay-pay-button' ); ?></button>
				</nav>
				<p class="iftp-btn-bnone" data-iftp-bnone hidden><?php esc_html_e( 'No buttons match.', 'ifthenpay-pay-button' ); ?></p>
			<?php endif; ?>
			<?php
			if ( $connected ) {
				$this->render_help_dialog();
			}
			self::print_shared_ui();
			?>
		</div>
		<?php
	}

	/**
	 * The first-time welcome: three starters that make a button in one click, or skip to the plain empty list.
	 */
	private function render_welcome(): void {
		?>
		<section class="iftp-btn-empty iftp-btn-welcome">
			<span class="iftp-btn-mark iftp-btn-empty__mark" aria-hidden="true"><img class="iftp-btn-mark__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/icon-normal.svg' ); ?>" alt="" width="28" height="28" /></span>
			<h2 class="iftp-btn-empty__title"><?php esc_html_e( 'Welcome! Create your first button', 'ifthenpay-pay-button' ); ?></h2>
			<p class="iftp-btn-empty__text"><?php esc_html_e( 'Start from one of these and change anything you like in the editor. Nothing goes live until you turn it on.', 'ifthenpay-pay-button' ); ?></p>
			<?php self::print_starters(); ?>
			<div class="iftp-btn-empty__actions">
				<?php self::print_ai_link(); ?>
				<a class="iftp-btn-btn iftp-btn-btn--link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', self::SKIP_ACTION, admin_url( 'admin-post.php' ) ), self::SKIP_ACTION ) ); ?>"><?php esc_html_e( 'Skip', 'ifthenpay-pay-button' ); ?></a>
			</div>
		</section>
		<?php
	}

	/**
	 * The help popup, opened from the "?" beside the title: the same starters, for anyone to use again.
	 */
	private function render_help_dialog(): void {
		?>
		<dialog class="iftp-btn-dialog iftp-btn-helpdialog" aria-labelledby="iftp-btn-help-title" data-iftp-help-dialog>
			<h2 id="iftp-btn-help-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Need a hand?', 'ifthenpay-pay-button' ); ?></h2>
			<button type="button" class="iftp-btn-helpdialog__tour" data-iftp-tour-start>
				<span class="iftp-btn-welcome__icon" aria-hidden="true"><?php echo self::icon( 'bolt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
				<span><strong><?php esc_html_e( 'Take the tour again', 'ifthenpay-pay-button' ); ?></strong> <?php esc_html_e( 'A quick walk through this screen, step by step.', 'ifthenpay-pay-button' ); ?></span>
			</button>
			<p class="iftp-btn-dialog__text"><?php esc_html_e( 'Or start from a template and change anything you like in the editor. It starts turned off, so nothing goes live until you turn it on.', 'ifthenpay-pay-button' ); ?></p>
			<?php self::print_starters(); ?>
			<div class="iftp-btn-dialog__actions">
				<?php self::print_ai_link(); ?>
				<button type="button" class="iftp-btn-btn" data-iftp-help-close><?php esc_html_e( 'Close', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</dialog>
		<?php
	}

	/**
	 * The starter cards, each a signed link that makes the button and opens it in the editor.
	 */
	private static function print_starters(): void {
		$starters = array(
			Templates::DONATION => array( 'heart', __( 'Donation', 'ifthenpay-pay-button' ), __( 'The payer picks the amount, with quick suggestions.', 'ifthenpay-pay-button' ) ),
			Templates::EVENT    => array( 'ticket', __( 'Event ticket', 'ifthenpay-pay-button' ), __( 'A fixed price, a quantity and a limited number of spots.', 'ifthenpay-pay-button' ) ),
			Templates::FIXED    => array( 'tag', __( 'Fixed price', 'ifthenpay-pay-button' ), __( 'One product or service at one price.', 'ifthenpay-pay-button' ) ),
		);
		?>
		<ul class="iftp-btn-welcome__starters" data-iftp-tour="starters">
			<?php foreach ( $starters as $key => $starter ) : ?>
				<?php
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action'   => self::TEMPLATE_ACTION,
							'template' => $key,
						),
						admin_url( 'admin-post.php' )
					),
					self::TEMPLATE_ACTION
				);
				?>
				<li>
					<a class="iftp-btn-welcome__starter" href="<?php echo esc_url( $url ); ?>">
						<span class="iftp-btn-welcome__icon" aria-hidden="true"><?php echo self::icon( $starter[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
						<strong><?php echo esc_html( $starter[1] ); ?></strong>
						<span><?php echo esc_html( $starter[2] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * The "describe it to the AI" link.
	 */
	private static function print_ai_link(): void {
		?>
		<a class="iftp-btn-btn iftp-btn-btn--ai" href="<?php echo esc_url( AiStudioPage::url( null ) ); ?>"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Or describe it to the AI', 'ifthenpay-pay-button' ); ?></a>
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
		$recent    = ( new Insights( $this->reports ) )->period( '30d' );
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
			'newest'    => __( 'Newest', 'ifthenpay-pay-button' ),
			'edited'    => __( 'Recently edited', 'ifthenpay-pay-button' ),
			'oldest'    => __( 'Oldest', 'ifthenpay-pay-button' ),
			'collected' => __( 'Most collected', 'ifthenpay-pay-button' ),
			'paid'      => __( 'Most paid', 'ifthenpay-pay-button' ),
			'title'     => __( 'Name (A–Z)', 'ifthenpay-pay-button' ),
		);
		$sizes   = array( 20, 40, 60 );
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
				<span class="screen-reader-text"><?php esc_html_e( 'Look', 'ifthenpay-pay-button' ); ?></span>
				<select class="iftp-btn-bselect" data-iftp-blook>
					<option value=""><?php esc_html_e( 'All looks', 'ifthenpay-pay-button' ); ?></option>
					<option value="look:site"><?php esc_html_e( 'Default design', 'ifthenpay-pay-button' ); ?></option>
					<option value="look:theme"><?php esc_html_e( 'Theme look', 'ifthenpay-pay-button' ); ?></option>
					<option value="look:custom"><?php esc_html_e( 'Custom look', 'ifthenpay-pay-button' ); ?></option>
					<option value="look:advanced"><?php esc_html_e( 'Advanced design', 'ifthenpay-pay-button' ); ?></option>
					<option value="look:code"><?php esc_html_e( 'Drawn in code', 'ifthenpay-pay-button' ); ?></option>
					<option value="checkout:own"><?php esc_html_e( 'Custom checkout look', 'ifthenpay-pay-button' ); ?></option>
					<option value="checkout:code"><?php esc_html_e( 'Drawn checkout page', 'ifthenpay-pay-button' ); ?></option>
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
			<div class="iftp-btn-bsize">
				<label class="iftp-btn-btoolbar__select">
					<span class="screen-reader-text"><?php esc_html_e( 'Cards per page', 'ifthenpay-pay-button' ); ?></span>
					<select class="iftp-btn-bselect" data-iftp-bsize>
						<?php foreach ( $sizes as $size ) : ?>
							<option value="<?php echo esc_attr( (string) $size ); ?>"><?php /* translators: %s: number of cards on a page */ echo esc_html( sprintf( __( '%s per page', 'ifthenpay-pay-button' ), number_format_i18n( $size ) ) ); ?></option>
						<?php endforeach; ?>
						<option value="custom"><?php esc_html_e( 'Custom', 'ifthenpay-pay-button' ); ?></option>
					</select>
				</label>
				<input type="number" class="iftp-btn-bsize__custom" min="2" max="200" step="1" value="20" data-iftp-bsize-custom aria-label="<?php esc_attr_e( 'Custom cards per page', 'ifthenpay-pay-button' ); ?>" hidden />
			</div>
			<div class="iftp-btn-bfilter" role="group" aria-label="<?php esc_attr_e( 'Show', 'ifthenpay-pay-button' ); ?>">
				<?php foreach ( $filters as $key => $filter ) : ?>
					<button type="button" class="iftp-btn-bfilter__option" data-iftp-bfilter="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'all' === $key ? 'true' : 'false'; ?>">
						<?php echo esc_html( $filter[0] ); ?>
						<span class="iftp-btn-bfilter__count" data-iftp-bfilter-count="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( number_format_i18n( $filter[1] ) ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-bbatch-toggle" data-iftp-bbatch-toggle aria-pressed="false"><?php echo self::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?><?php esc_html_e( 'Select', 'ifthenpay-pay-button' ); ?></button>
			<div class="iftp-btn-bfilter iftp-btn-bview" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ifthenpay-pay-button' ); ?>">
				<?php foreach ( $views as $key => $label ) : ?>
					<button type="button" class="iftp-btn-bfilter__option" data-iftp-bview="<?php echo esc_attr( $key ); ?>" aria-pressed="<?php echo 'grid' === $key ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-iftp-tip="<?php echo esc_attr( $label ); ?>"><?php echo self::icon( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The bar that appears in select mode: how many are picked, an action, and Apply.
	 */
	private static function print_batch_bar(): void {
		?>
		<div class="iftp-btn-bbatch" data-iftp-bbatch role="region" aria-label="<?php esc_attr_e( 'Batch actions', 'ifthenpay-pay-button' ); ?>" hidden>
			<span class="iftp-btn-bbatch__count" data-iftp-bbatch-count aria-live="polite"></span>
			<button type="button" class="iftp-btn-bbatch__link" data-iftp-bbatch-all><?php esc_html_e( 'Select all shown', 'ifthenpay-pay-button' ); ?></button>
			<button type="button" class="iftp-btn-bbatch__link" data-iftp-bbatch-clear><?php esc_html_e( 'Clear', 'ifthenpay-pay-button' ); ?></button>
			<span class="iftp-btn-bbatch__spacer"></span>
			<label class="iftp-btn-btoolbar__select">
				<span class="screen-reader-text"><?php esc_html_e( 'Batch action', 'ifthenpay-pay-button' ); ?></span>
				<select class="iftp-btn-bselect" data-iftp-bbatch-op>
					<option value=""><?php esc_html_e( 'Batch action', 'ifthenpay-pay-button' ); ?></option>
					<option value="activate"><?php esc_html_e( 'Turn on', 'ifthenpay-pay-button' ); ?></option>
					<option value="deactivate"><?php esc_html_e( 'Turn off', 'ifthenpay-pay-button' ); ?></option>
					<option value="duplicate"><?php esc_html_e( 'Duplicate', 'ifthenpay-pay-button' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Move to trash', 'ifthenpay-pay-button' ); ?></option>
				</select>
			</label>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-bbatch-apply disabled><?php esc_html_e( 'Apply', 'ifthenpay-pay-button' ); ?></button>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-bbatch-done><?php esc_html_e( 'Done', 'ifthenpay-pay-button' ); ?></button>
		</div>
		<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-batch-title" data-iftp-bbatch-dialog>
			<h2 id="iftp-btn-batch-title" class="iftp-btn-dialog__title" data-iftp-bbatch-title></h2>
			<p class="iftp-btn-dialog__text"><?php esc_html_e( 'Their payments stay in Payments, but every block, shortcode and link that uses them stops working. They go to the trash.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-bbatch-cancel><?php esc_html_e( 'Keep them', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--danger" data-iftp-bbatch-confirm><?php esc_html_e( 'Move to trash', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</dialog>
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
		$shortcode      = sprintf( '[%1$s id="%2$d"]', Shortcode::TAG, $button->id );
		$pay_link       = $links->url( $button );
		$edit           = self::edit_url( $button->id );
		$paid_count     = null !== $stats ? $stats['paid_count'] : 0;
		$limit          = Spots::capacity( $button );
		$abandon        = CheckoutOpens::abandon_rate( $opens, $paid );
		$share          = $limit > 0 ? min( 1.0, $taken / $limit ) : 0.0;
		$sold_out       = self::sold_out( $button, $taken );
		$looks          = array(
			ButtonDesign::SOURCE_SITE     => __( 'Default design', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_THEME    => __( 'Theme look', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_CUSTOM   => __( 'Custom look', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_ADVANCED => __( 'Advanced design', 'ifthenpay-pay-button' ),
			ButtonDesign::SOURCE_CODE     => __( 'Drawn in code', 'ifthenpay-pay-button' ),
		);
		$checkout_looks = array(
			Button::LOOK_OWN  => __( 'Custom checkout look', 'ifthenpay-pay-button' ),
			Button::LOOK_CODE => __( 'Drawn checkout page', 'ifthenpay-pay-button' ),
		);
		$payments       = add_query_arg(
			array(
				'page'   => EntriesPage::PAGE_SLUG,
				'button' => (string) $button->id,
			),
			admin_url( 'admin.php' )
		);
		?>
		<li class="iftp-btn-bcard<?php echo $button->active ? '' : ' iftp-btn-bcard--off'; ?>" data-iftp-bcard data-id="<?php echo esc_attr( (string) $button->id ); ?>" data-title="<?php echo esc_attr( strtolower( remove_accents( $button->title ) ) ); ?>" data-active="<?php echo $button->active ? '1' : '0'; ?>" data-mode="<?php echo esc_attr( $button->mode() ); ?>" data-look="<?php echo esc_attr( (string) $button->config['design']['source'] ); ?>" data-checkout="<?php echo esc_attr( (string) $button->config['look_mode'] ); ?>" data-search="<?php echo esc_attr( strtolower( remove_accents( $button->title . ' ' . ( $looks[ $button->config['design']['source'] ] ?? '' ) . ( ButtonDesign::SOURCE_ADVANCED === $button->config['design']['source'] ? ' ' . ( Advanced::catalogue()[ $button->config['design']['advanced'] ]['name'] ?? '' ) : '' ) . ( Button::LOOK_SITE !== $button->config['look_mode'] ? ' ' . $checkout_looks[ $button->config['look_mode'] ] : '' ) ) ) ); ?>" data-sold="<?php echo $sold_out ? '1' : '0'; ?>" data-collected="<?php echo esc_attr( (string) ( null !== $stats ? $stats['paid_amount'] : 0 ) ); ?>" data-paid="<?php echo esc_attr( (string) $paid_count ); ?>" data-order="<?php echo esc_attr( (string) $order ); ?>" data-edited="<?php echo esc_attr( (string) get_post_modified_time( 'U', true, $button->id ) ); ?>">
			<a class="iftp-btn-bcard__stage" href="<?php echo esc_url( $edit ); ?>" tabindex="-1" aria-hidden="true">
				<?php
				// A drawn button brings its own CSS, so its card shows the real design. The CSS was cleaned when it was saved (no tags can get in).
				$design = ButtonDesign::for_button( $button, $this->checkout_settings );

				// An Advanced design is the same code for every button that wears it, so the page carries it once, under the design's name, and each card only sets its colours.
				if ( ButtonDesign::SOURCE_ADVANCED === $design['source'] && Advanced::has( $design['advanced'] ) ) {
					if ( ! isset( $this->designs[ $design['advanced'] ] ) ) {
						$this->designs[ $design['advanced'] ] = true;
						echo '<style>' . CustomCode::scope_css( Advanced::scope_name( $design['advanced'] ), Advanced::css( $design['advanced'], array() ), 4, '{pointer-events:auto}' ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own file.
					}

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes; the name is a slug from our list and the colours are hex.
					echo '<span class="iftp-btn-pay" data-iftp-code="' . esc_attr( Advanced::scope_name( $design['advanced'] ) ) . '" style="' . esc_attr( Advanced::variables( $design['advanced'], $design['advanced_colors'] ) . Advanced::zoom( $design['advanced_size'] ) ) . '">' . ButtonDesign::preview_markup( $design, $button->display_label() ) . '</span>';
				} else {
					// A drawn button brings its own CSS, and its script too, so the card plays what the button plays.
					$drawn    = ButtonRenderer::preview_css( $button, $this->checkout_settings );
					$drawn_js = ButtonRenderer::preview_js( $button, $this->checkout_settings );
					echo '' !== $drawn ? '<style>' . $drawn . '</style>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CustomCode strips tags and checks the CSS on save.
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes; the attribute is an integer.
					echo '<span class="iftp-btn-pay"' . ( '' !== $drawn ? ' data-iftp-code="' . (int) $button->id . '"' : '' ) . '>' . ButtonDesign::preview_markup( $design, $button->display_label() ) . '</span>';
					echo '' !== $drawn_js ? '<script>' . $drawn_js . '</script>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saved only by users who may post unfiltered HTML, and checked on save (no closing script tag).
				}
				?>
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
							<span class="iftp-btn-bcard__look"><?php echo esc_html( $looks[ $button->config['design']['source'] ] ?? $looks[ ButtonDesign::SOURCE_THEME ] ); ?></span>
							<?php if ( $sold_out ) : ?>
								<span class="iftp-btn-bcard__state iftp-btn-bcard__badge--sold"><?php esc_html_e( 'Sold out', 'ifthenpay-pay-button' ); ?></span>
							<?php elseif ( $limit > 0 && $share >= self::ALMOST_FULL ) : ?>
								<span class="iftp-btn-bcard__state iftp-btn-bcard__badge--almost"><?php esc_html_e( 'Almost full', 'ifthenpay-pay-button' ); ?></span>
							<?php endif; ?>
						</p>
					</div>
					<span class="iftp-btn-bcard__status"><?php echo esc_html( $button->active ? __( 'Active', 'ifthenpay-pay-button' ) : __( 'Inactive', 'ifthenpay-pay-button' ) ); ?></span>
					<label class="iftp-btn-bcard__toggle">
						<span class="screen-reader-text">
							<?php
							/* translators: %s: the button's title */
							echo esc_html( sprintf( __( 'Take payments with %s', 'ifthenpay-pay-button' ), $button->title ) );
							?>
						</span>
						<input type="checkbox" class="iftp-btn-bcard__check" data-iftp-toggle-button="<?php echo esc_attr( (string) $button->id ); ?>" <?php checked( $button->active ); ?> />
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
				<a class="iftp-btn-btn iftp-btn-btn--primary iftp-btn-bcard__edit" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'ifthenpay-pay-button' ); ?></a>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--primary iftp-btn-bcard__activate" data-iftp-activate-button><?php esc_html_e( 'Activate button', 'ifthenpay-pay-button' ); ?></button>
				<span class="iftp-btn-bcard__tools">
					<button type="button" class="iftp-btn-tool iftp-btn-tool--live" data-iftp-copy-text="<?php echo esc_attr( $shortcode ); ?>" data-iftp-copy-done="<?php esc_attr_e( 'Shortcode copied.', 'ifthenpay-pay-button' ); ?>" data-iftp-tip="<?php esc_attr_e( 'Copy shortcode', 'ifthenpay-pay-button' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: the shortcode */ __( 'Copy shortcode %s', 'ifthenpay-pay-button' ), $shortcode ) ); ?>"><?php echo self::icon( 'code' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
					<button type="button" class="iftp-btn-tool iftp-btn-tool--live" data-iftp-copy-text="<?php echo esc_attr( $pay_link ); ?>" data-iftp-copy-done="<?php esc_attr_e( 'Pay link copied.', 'ifthenpay-pay-button' ); ?>" data-iftp-tip="<?php esc_attr_e( 'Copy pay link', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Copy pay link', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
					<button type="button" class="iftp-btn-tool iftp-btn-tool--live" data-iftp-qr="<?php echo esc_attr( $pay_link ); ?>" data-iftp-qr-name="<?php echo esc_attr( $button->title ); ?>" data-iftp-tip="<?php esc_attr_e( 'QR code', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'QR code for the pay link', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'qr' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></button>
					<a class="iftp-btn-tool iftp-btn-tool--live" href="<?php echo esc_url( DemoPage::url( $button->id ) ); ?>" target="_blank" rel="noopener" data-iftp-tip="<?php esc_attr_e( 'Preview', 'ifthenpay-pay-button' ); ?>" aria-label="<?php esc_attr_e( 'Preview (opens in a new tab)', 'ifthenpay-pay-button' ); ?>"><?php echo self::icon( 'eye' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></a>
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
			<p id="iftp-btn-delete-text" class="iftp-btn-dialog__text"><?php esc_html_e( 'Its payments stay in Payments, but every block, shortcode and link that uses it stops working. It goes to the trash, so you can undo it right after.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-delete-cancel><?php esc_html_e( 'Keep it', 'ifthenpay-pay-button' ); ?></button>
				<a class="iftp-btn-btn iftp-btn-btn--danger" href="#" data-iftp-delete-ok><?php esc_html_e( 'Move to trash', 'ifthenpay-pay-button' ); ?></a>
			</div>
		</dialog>

		<dialog class="iftp-btn-dialog iftp-btn-qr" aria-labelledby="iftp-btn-qr-title" data-iftp-qr-dialog>
			<h2 id="iftp-btn-qr-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'QR code for the pay link', 'ifthenpay-pay-button' ); ?></h2>
			<div class="iftp-btn-qr__code" data-iftp-qr-preview role="img" aria-label="<?php esc_attr_e( 'QR code', 'ifthenpay-pay-button' ); ?>"></div>
			<p class="iftp-btn-qr__link" data-iftp-qr-link></p>
			<p class="iftp-btn-dialog__text"><?php esc_html_e( 'Print it or put it on a flyer: scanning it opens the pay page. It always points to this button, so it keeps working if you change the button.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-qr-close><?php esc_html_e( 'Close', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-qr-download="png"><?php esc_html_e( 'Download PNG', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-qr-download="svg"><?php esc_html_e( 'Download SVG', 'ifthenpay-pay-button' ); ?></button>
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
			'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
			'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
			'qr'       => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2.5v2.5H14zM18 14h2M14 19v1M17.5 18v2M20 18v2"/>',
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
			'heart'    => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
			'ticket'   => '<path d="M4 8a2 2 0 0 0 0 8v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2a2 2 0 0 1 0-8V6a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1z"/><path d="M14 5v14" stroke-dasharray="2 2"/>',
			'tag'      => '<path d="M4 4h8l8 8-8 8-8-8z"/><circle cx="8.5" cy="8.5" r="1"/>',
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
