<?php
/**
 * One button's editor.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\AiPanel;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\GatewaySettingsPage;
use Ifthenpay\PayButton\Admin\LookControls;
use Ifthenpay\PayButton\Ai\ButtonSchema;
use Ifthenpay\PayButton\Bridge\InvoiceHook;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\FormFields;
use Ifthenpay\PayButton\Buttons\Spots;
use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\Shortcode;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The editor: tabs for what the button charges and asks, how it looks, what happens after, and how other
 * plugins reach it; the live preview and the ways to share it on the right. "Connect another plugin" is the bridge for forms ifthenpay doesn't integrate with.
 */
class ButtonEditor {

	/**
	 * Gateway settings.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Pay links.
	 *
	 * @var PayLinks
	 */
	private PayLinks $links;

	/**
	 * Checkout settings, for the site's look and the popup words.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $checkout_settings;

	/**
	 * Sets up the editor.
	 *
	 * @param SettingsRepository    $settings          Gateway settings.
	 * @param PayLinks              $links             Pay links.
	 * @param CheckoutSettings|null $checkout_settings Checkout settings.
	 */
	public function __construct( SettingsRepository $settings, PayLinks $links, ?CheckoutSettings $checkout_settings = null ) {
		$this->settings          = $settings;
		$this->links             = $links;
		$this->checkout_settings = $checkout_settings ?? new CheckoutSettings();
	}

	/**
	 * Renders the editor for a button, or for a new one.
	 *
	 * @param Button|null $button The button, or null for a new one.
	 * @param Button|null $draft  The AI chat's latest version, shown unsaved in its place.
	 */
	public function render( ?Button $button, ?Button $draft = null ): void {
		$is_new  = null === $button;
		$saved   = $button;
		$button  = $draft ?? $button ?? new Button( 0, '', '', true, Button::defaults() );
		$config  = $button->config;
		$title   = null === $saved ? __( 'New pay button', 'ifthenpay-pay-button' ) : $saved->title;
		$actions = '<a class="iftp-btn-btn iftp-btn-btn--ai" href="' . esc_url( AiStudioPage::url( $saved ) ) . '">' . AiPanel::sparkle() . ( $is_new ? esc_html__( 'Build with AI', 'ifthenpay-pay-button' ) : esc_html__( 'Edit with AI', 'ifthenpay-pay-button' ) ) . '</a>';
		$actions = $is_new ? $actions : $actions . '<a class="iftp-btn-btn iftp-btn-btn--secondary" href="' . esc_url( $this->links->url( $button ) ) . '" target="_blank" rel="noopener">' . ButtonsPage::icon( 'external' ) . esc_html__( 'Open pay page', 'ifthenpay-pay-button' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'ifthenpay-pay-button' ) . '</span></a>';
		$tabs    = array(
			'checkout' => __( 'Checkout', 'ifthenpay-pay-button' ),
			'look'     => __( 'Checkout design', 'ifthenpay-pay-button' ),
			'design'   => __( 'Button design', 'ifthenpay-pay-button' ),
			'after'    => __( 'After payment', 'ifthenpay-pay-button' ),
			'connect'  => __( 'Connect', 'ifthenpay-pay-button' ),
		);
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-editor-wrap">
			<?php ButtonsPage::print_header( $title, $actions ); ?>
			<?php ButtonsPage::print_notice(); ?>

			<p class="iftp-btn-editor__back">
				<a href="<?php echo esc_url( ButtonsPage::list_url() ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3.5 5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php esc_html_e( 'All buttons', 'ifthenpay-pay-button' ); ?>
				</a>
			</p>

			<?php
			if ( null !== $draft ) {
				$this->render_draft_note( $saved, $draft );
			}
			?>

			<form class="iftp-btn-editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-iftp-editor<?php echo null !== $draft ? ' data-iftp-start-dirty' : ''; ?>>
				<input type="hidden" name="action" value="<?php echo esc_attr( ButtonsPage::SAVE_ACTION ); ?>" />
				<?php if ( null !== $draft ) : ?>
					<input type="hidden" name="<?php echo esc_attr( AiStudioPage::DRAFT_VAR ); ?>" value="1" />
				<?php endif; ?>
				<input type="hidden" name="<?php echo esc_attr( ButtonsPage::EDIT_VAR ); ?>" value="<?php echo esc_attr( (string) $button->id ); ?>" />
				<input type="hidden" name="tab" value="checkout" data-iftp-tab-input />
				<?php wp_nonce_field( ButtonsPage::SAVE_ACTION . '_' . $button->id ); ?>

				<div class="iftp-btn-editor__main">
					<div class="iftp-btn-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Button settings', 'ifthenpay-pay-button' ); ?>" data-iftp-tabs hidden>
						<?php foreach ( $tabs as $key => $label ) : ?>
							<button type="button" class="iftp-btn-tabs__tab" role="tab" id="iftp-btn-tab-<?php echo esc_attr( $key ); ?>" aria-controls="iftp-btn-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 'checkout' === $key ? 'true' : 'false'; ?>" tabindex="<?php echo 'checkout' === $key ? '0' : '-1'; ?>" data-iftp-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>

					<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-checkout" aria-labelledby="iftp-btn-tab-checkout" data-iftp-panel="checkout">
						<?php
						$this->render_basics( $button );
						$this->render_amount( $button, $is_new );
						$this->render_fields( $config );
						$this->render_form( $button );
						$this->render_spots( $config );
						$this->render_methods( $config );
						?>
					</div>
					<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-look" aria-labelledby="iftp-btn-tab-look" data-iftp-panel="look">
						<?php $this->render_look( $button ); ?>
					</div>
					<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-design" aria-labelledby="iftp-btn-tab-design" data-iftp-panel="design">
						<?php $this->render_design( $button ); ?>
					</div>
					<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-after" aria-labelledby="iftp-btn-tab-after" data-iftp-panel="after">
						<?php
						$this->render_after( $config );
						$this->render_invoice( $button, $is_new );
						?>
					</div>
					<div class="iftp-btn-tabpanel" role="tabpanel" id="iftp-btn-panel-connect" aria-labelledby="iftp-btn-tab-connect" data-iftp-panel="connect">
						<?php $this->render_bridge( $button, $is_new ); ?>
					</div>
				</div>

				<aside class="iftp-btn-editor__aside">
					<div class="iftp-btn-editor__sticky">
						<div class="iftp-btn-editor__save">
							<p class="iftp-btn-editor__dirty" data-iftp-dirty hidden><?php esc_html_e( 'Unsaved changes', 'ifthenpay-pay-button' ); ?></p>
							<div class="iftp-btn-editor__save-row">
								<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary"><?php echo $is_new ? esc_html__( 'Create button', 'ifthenpay-pay-button' ) : esc_html__( 'Save button', 'ifthenpay-pay-button' ); ?></button>
								<?php if ( ! $is_new ) : ?>
									<a class="iftp-btn-btn iftp-btn-btn--link iftp-btn-bcard__delete" href="<?php echo esc_url( ButtonsPage::action_url( ButtonsPage::DELETE_ACTION, $button->id ) ); ?>" data-iftp-confirm-delete="<?php echo esc_attr( $button->title ); ?>"><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
						<?php $this->render_preview( $button ); ?>
						<div class="iftp-btn-editor__lookpreview">
							<?php ( new LookControls( 'config[look]' ) )->render_preview( CheckoutLook::for_button( $button, $this->checkout_settings ), $this->checkout_settings->get_confirmation() ); ?>
						</div>
						<?php
						if ( ! $is_new ) {
							$this->render_share( $button );
						}
						?>
					</div>
				</aside>
			</form>
			<?php ButtonsPage::print_shared_ui(); ?>
		</div>
		<?php
	}

	/**
	 * Above an AI-built version: it isn't saved, every price it set wants a look, and the chat is one click back.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @param Button      $draft The AI's version.
	 */
	private function render_draft_note( ?Button $saved, Button $draft ): void {
		?>
		<section class="iftp-btn-ai-note" aria-labelledby="iftp-btn-ai-note-title">
			<span class="iftp-btn-ai-note__mark" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
			<div class="iftp-btn-ai-note__body">
				<h2 id="iftp-btn-ai-note-title" class="iftp-btn-ai-note__title"><?php echo null !== $saved ? esc_html__( 'The AI\'s changes, not saved yet', 'ifthenpay-pay-button' ) : esc_html__( 'Built with AI, not saved yet', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-ai-note__text">
					<?php
					echo null !== $saved
						? esc_html__( 'Look it over and save to put it live. Leave without saving and your button stays as it was.', 'ifthenpay-pay-button' )
						: esc_html__( 'Look it over, then create it. It takes payments as soon as it\'s created, so check the prices first.', 'ifthenpay-pay-button' );
					?>
				</p>
				<p class="iftp-btn-ai-note__label"><?php esc_html_e( 'Prices the AI set', 'ifthenpay-pay-button' ); ?></p>
				<ul class="iftp-btn-ai-note__prices">
					<?php foreach ( ButtonSchema::prices( $draft->config ) as $price ) : ?>
						<li><?php echo esc_html( $price ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<a class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-ai-note__back" href="<?php echo esc_url( AiStudioPage::url( $saved ) ); ?>"><?php esc_html_e( 'Back to the chat', 'ifthenpay-pay-button' ); ?></a>
		</section>
		<?php
	}

	/**
	 * Opens a card.
	 *
	 * @param string $id          The heading's id.
	 * @param string $title       The heading.
	 * @param string $description The line under it.
	 */
	private static function open_card( string $id, string $title, string $description ): void {
		?>
		<section class="iftp-btn-ecard" aria-labelledby="<?php echo esc_attr( $id ); ?>">
			<div class="iftp-btn-ecard__intro">
				<h2 class="iftp-btn-ecard__title" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h2>
				<p class="iftp-btn-ecard__description"><?php echo esc_html( $description ); ?></p>
			</div>
			<div class="iftp-btn-ecard__body">
		<?php
	}

	/**
	 * Closes a card.
	 */
	private static function close_card(): void {
		?>
			</div>
		</section>
		<?php
	}

	/**
	 * Title, summary, link slug and the on/off switch.
	 *
	 * @param Button $button The button.
	 */
	private function render_basics( Button $button ): void {
		$prefix = (string) preg_replace( '#[^/]+/?$#', '', $this->links->url( new Button( 0, '', 'x', true, array() ) ) );

		self::open_card( 'iftp-btn-ec-basics', __( 'Basics', 'ifthenpay-pay-button' ), __( 'What the payer sees first, and the address of its pay page.', 'ifthenpay-pay-button' ) );
		?>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="iftp-btn-title"><?php esc_html_e( 'Title', 'ifthenpay-pay-button' ); ?></label>
			<input type="text" class="iftp-btn-field__control" id="iftp-btn-title" name="title" maxlength="120" required value="<?php echo esc_attr( $button->title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Consultation, Donation, Booking deposit', 'ifthenpay-pay-button' ); ?>" data-iftp-preview="title" />
		</div>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="iftp-btn-summary"><?php esc_html_e( 'Description', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></label>
			<input type="text" class="iftp-btn-field__control" id="iftp-btn-summary" name="config[summary]" maxlength="200" value="<?php echo esc_attr( $button->config['summary'] ); ?>" aria-describedby="iftp-btn-summary-help" data-iftp-preview="summary" />
			<p class="iftp-btn-field__help" id="iftp-btn-summary-help"><?php esc_html_e( 'Shown under the title in the checkout.', 'ifthenpay-pay-button' ); ?></p>
		</div>
		<div class="iftp-btn-field" data-iftp-tag-scope>
			<label class="iftp-btn-field__label" for="iftp-btn-pay-description"><?php esc_html_e( 'Hosted payment page description', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></label>
			<input type="text" class="iftp-btn-field__control" id="iftp-btn-pay-description" name="config[pay_description]" maxlength="200" value="<?php echo esc_attr( $button->config['pay_description'] ); ?>" placeholder="<?php echo esc_attr( $this->settings->get_description() ); ?>" aria-describedby="iftp-btn-pay-description-help" data-iftp-tag-target />
			<p class="iftp-btn-field__help" id="iftp-btn-pay-description-help">
				<?php esc_html_e( 'Only for this button. Empty uses the one in Gateway Configuration.', 'ifthenpay-pay-button' ); ?>
				<a href="<?php echo esc_url( GatewaySettingsPage::url() ); ?>"><?php esc_html_e( 'Edit it there', 'ifthenpay-pay-button' ); ?></a>
			</p>
			<?php ButtonsPage::print_tags( MergeTags::available( false ) ); ?>
		</div>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="iftp-btn-slug"><?php esc_html_e( 'Pay link', 'ifthenpay-pay-button' ); ?></label>
			<span class="iftp-btn-affix">
				<span class="iftp-btn-affix__text" title="<?php echo esc_attr( $prefix ); ?>"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', $prefix ) ); ?></span>
				<input type="text" class="iftp-btn-field__control" id="iftp-btn-slug" name="slug" maxlength="80" value="<?php echo esc_attr( $button->slug ); ?>" placeholder="<?php esc_attr_e( 'from the title', 'ifthenpay-pay-button' ); ?>" pattern="[a-z0-9\-]*" />
			</span>
		</div>
		<label class="iftp-btn-np__toggle">
			<input type="hidden" name="active" value="0" />
			<input type="checkbox" class="iftp-btn-switch" name="active" value="1" <?php checked( $button->active ); ?> />
			<span class="iftp-btn-np__toggle-text">
				<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Take payments', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'When off, the button shows disabled and its pay page is not found.', 'ifthenpay-pay-button' ); ?></span>
			</span>
		</label>
		<?php
		self::close_card();
	}

	/**
	 * The Checkout design tab: the site's look, or one of the button's own for its checkout, pay page and popups.
	 *
	 * @param Button $button The button.
	 */
	private function render_look( Button $button ): void {
		$own = Button::LOOK_OWN === $button->config['look_mode'];
		// Until the button has its own, the controls start from the site's look.
		$look  = $own ? $button->config['look'] : $this->checkout_settings->get_look();
		$modes = array(
			Button::LOOK_SITE => array( __( 'The site\'s look', 'ifthenpay-pay-button' ), __( 'Same as every other button, from Default Checkout › Design.', 'ifthenpay-pay-button' ) ),
			Button::LOOK_OWN  => array( __( 'Its own look', 'ifthenpay-pay-button' ), __( 'Colours, shape, pay page and popups just for this button.', 'ifthenpay-pay-button' ) ),
		);

		self::open_card( 'iftp-btn-ec-look', __( 'Checkout design', 'ifthenpay-pay-button' ), __( 'How this button\'s checkout, its pay page and the popups after paying look. Their words stay in Default Checkout › Messages.', 'ifthenpay-pay-button' ) );
		?>
		<fieldset class="iftp-btn-segment iftp-btn-segment--two">
			<legend class="screen-reader-text"><?php esc_html_e( 'Whose look it uses', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $modes as $mode => $labels ) : ?>
				<label class="iftp-btn-segment__option">
					<input type="radio" name="config[look_mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $button->config['look_mode'], $mode ); ?> data-iftp-look-mode />
					<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
					<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="iftp-btn-field__help" data-iftp-when-lookmode="site">
			<?php esc_html_e( 'Change the site\'s look for every button at once:', 'ifthenpay-pay-button' ); ?>
			<a href="<?php echo esc_url( CheckoutSettingsPage::url() ); ?>"><?php esc_html_e( 'Default Checkout › Design', 'ifthenpay-pay-button' ); ?></a>
		</p>
		<?php
		self::close_card();
		?>
		<div class="iftp-btn-tabpanel__group" data-iftp-when-lookmode="own"<?php echo $own ? '' : ' hidden'; ?>>
			<?php ( new LookControls( 'config[look]' ) )->render_cards( $look, false ); ?>
		</div>
		<?php
	}

	/**
	 * The button's look: its text, the theme's style or a custom one, and the extras that work with both.
	 *
	 * @param Button $button The button.
	 */
	private function render_design( Button $button ): void {
		$design     = $button->config['design'];
		$custom     = ButtonDesign::SOURCE_CUSTOM === $design['source'];
		$auto       = '' === $design['text'];
		$auto2      = '' === $design['color2'];
		$auto_bc    = '' === $design['border_color'];
		$sources    = array(
			ButtonDesign::SOURCE_THEME  => array( __( 'Match my theme', 'ifthenpay-pay-button' ), __( 'Looks like the other buttons on your site.', 'ifthenpay-pay-button' ) ),
			ButtonDesign::SOURCE_CUSTOM => array( __( 'Custom', 'ifthenpay-pay-button' ), __( 'Your own colour, shape and size.', 'ifthenpay-pay-button' ) ),
		);
		$variants   = array(
			'filled'   => __( 'Filled', 'ifthenpay-pay-button' ),
			'outline'  => __( 'Outline', 'ifthenpay-pay-button' ),
			'soft'     => __( 'Soft', 'ifthenpay-pay-button' ),
			'gradient' => __( 'Gradient', 'ifthenpay-pay-button' ),
			'glass'    => __( 'Glass', 'ifthenpay-pay-button' ),
			'3d'       => __( '3D', 'ifthenpay-pay-button' ),
			'neon'     => __( 'Neon', 'ifthenpay-pay-button' ),
			'brutal'   => __( 'Brutal', 'ifthenpay-pay-button' ),
			'ghost'    => __( 'Ghost', 'ifthenpay-pay-button' ),
		);
		$effects    = array(
			'none'  => __( 'None', 'ifthenpay-pay-button' ),
			'lift'  => __( 'Lift', 'ifthenpay-pay-button' ),
			'grow'  => __( 'Grow', 'ifthenpay-pay-button' ),
			'shine' => __( 'Shine', 'ifthenpay-pay-button' ),
			'glow'  => __( 'Glow', 'ifthenpay-pay-button' ),
			'fill'  => __( 'Fill', 'ifthenpay-pay-button' ),
			'nudge' => __( 'Nudge', 'ifthenpay-pay-button' ),
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
			'none'  => __( 'None', 'ifthenpay-pay-button' ),
			'lock'  => __( 'Lock', 'ifthenpay-pay-button' ),
			'card'  => __( 'Card', 'ifthenpay-pay-button' ),
			'arrow' => __( 'Arrow', 'ifthenpay-pay-button' ),
			'heart' => __( 'Heart', 'ifthenpay-pay-button' ),
		);
		$widths     = array(
			'0' => __( 'Fit the text', 'ifthenpay-pay-button' ),
			'1' => __( 'Full width', 'ifthenpay-pay-button' ),
		);

		self::open_card( 'iftp-btn-ec-design', __( 'Button design', 'ifthenpay-pay-button' ), __( 'How the button looks on your pages. A block\'s own colour settings still win over these.', 'ifthenpay-pay-button' ) );
		?>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="iftp-btn-label"><?php esc_html_e( 'Button text', 'ifthenpay-pay-button' ); ?></label>
			<input type="text" class="iftp-btn-field__control" id="iftp-btn-label" name="config[label]" maxlength="60" value="<?php echo esc_attr( $button->config['label'] ); ?>" placeholder="<?php echo esc_attr( CheckoutTexts::get( 'pay_now' ) ); ?>" aria-describedby="iftp-btn-label-help" data-iftp-preview="label" />
			<p class="iftp-btn-field__help" id="iftp-btn-label-help"><?php esc_html_e( 'A fixed amount is added after it when "Show the amount on the button" is on.', 'ifthenpay-pay-button' ); ?></p>
		</div>

		<fieldset class="iftp-btn-segment iftp-btn-segment--two">
			<legend class="screen-reader-text"><?php esc_html_e( 'Where the look comes from', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $sources as $source => $labels ) : ?>
				<label class="iftp-btn-segment__option">
					<input type="radio" name="config[design][source]" value="<?php echo esc_attr( $source ); ?>" <?php checked( $design['source'], $source ); ?> data-iftp-design="source" />
					<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
					<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="iftp-btn-design" data-iftp-when-source="custom"<?php echo $custom ? '' : ' hidden'; ?>>
			<div class="iftp-btn-field">
				<span class="iftp-btn-field__label" id="iftp-btn-presets-label"><?php esc_html_e( 'Start from', 'ifthenpay-pay-button' ); ?></span>
				<div class="iftp-btn-looks" role="group" aria-labelledby="iftp-btn-presets-label">
					<?php foreach ( ButtonDesign::presets() as $key => $preset ) : ?>
						<button type="button" class="iftp-btn-looks__item" data-iftp-preset="<?php echo esc_attr( $key ); ?>">
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
			</div>

			<div class="iftp-btn-design__grid">
				<?php self::render_fx_picker( 'variant', __( 'Style', 'ifthenpay-pay-button' ), $variants, $design ); ?>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-color"><?php esc_html_e( 'Colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color">
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( $design['color'] ); ?>" aria-label="<?php esc_attr_e( 'Pick the colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-color" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-color" name="config[design][color]" value="<?php echo esc_attr( $design['color'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="color" />
					</span>
				</div>

				<div class="iftp-btn-field" data-iftp-when-variant="gradient"<?php echo 'gradient' === $design['variant'] ? '' : ' hidden'; ?>>
					<label class="iftp-btn-field__label" for="iftp-btn-color2"><?php esc_html_e( 'Second colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color<?php echo $auto2 ? ' is-auto' : ''; ?>" data-iftp-color2>
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( ButtonDesign::second_color( $design ) ); ?>" aria-label="<?php esc_attr_e( 'Pick the second colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-color2" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-color2" name="config[design][color2]" value="<?php echo esc_attr( ButtonDesign::second_color( $design ) ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="color2" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="config[design][color2_auto]" value="1" <?php checked( $auto2 ); ?> data-iftp-design="color2_auto" />
						<?php esc_html_e( 'Pick it for me (a neighbouring shade)', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-text-color"><?php esc_html_e( 'Text colour', 'ifthenpay-pay-button' ); ?></label>
					<span class="iftp-btn-color<?php echo $auto ? ' is-auto' : ''; ?>" data-iftp-text-color>
						<input type="color" class="iftp-btn-color__swatch" value="<?php echo esc_attr( ButtonDesign::text_color( $design ) ); ?>" aria-label="<?php esc_attr_e( 'Pick the text colour', 'ifthenpay-pay-button' ); ?>" data-iftp-color-for="iftp-btn-text-color" />
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-text-color" name="config[design][text]" value="<?php echo esc_attr( ButtonDesign::text_color( $design ) ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="text" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="config[design][text_auto]" value="1" <?php checked( $auto ); ?> data-iftp-design="text_auto" />
						<?php esc_html_e( 'Pick it for me (best contrast)', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>

				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-radius"><?php esc_html_e( 'Corners', 'ifthenpay-pay-button' ); ?> <output class="iftp-btn-range__value" for="iftp-btn-radius" data-iftp-radius-out></output></label>
					<span class="iftp-btn-range">
						<span class="iftp-btn-range__end iftp-btn-range__end--square" aria-hidden="true"></span>
						<input type="range" class="iftp-btn-range__input" id="iftp-btn-radius" name="config[design][radius]" min="0" max="<?php echo esc_attr( (string) ButtonDesign::PILL ); ?>" step="1" value="<?php echo esc_attr( (string) $design['radius'] ); ?>" data-iftp-design="radius" />
						<span class="iftp-btn-range__end iftp-btn-range__end--pill" aria-hidden="true"></span>
					</span>
				</div>

				<fieldset class="iftp-btn-field">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Size', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg">
						<?php foreach ( $sizes as $size => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="config[design][size]" value="<?php echo esc_attr( $size ); ?>" <?php checked( $design['size'], $size ); ?> data-iftp-design="size" />
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
						<input type="range" class="iftp-btn-range__input" id="iftp-btn-border-width" name="config[design][border_width]" min="0" max="<?php echo esc_attr( (string) ButtonDesign::BORDER_MAX ); ?>" step="1" value="<?php echo esc_attr( (string) $design['border_width'] ); ?>" data-iftp-design="border_width" />
					</span>
				</div>

				<fieldset class="iftp-btn-field">
					<legend class="iftp-btn-field__label"><?php esc_html_e( 'Border line', 'ifthenpay-pay-button' ); ?></legend>
					<span class="iftp-btn-seg">
						<?php foreach ( $borders as $border => $label ) : ?>
							<label class="iftp-btn-seg__option">
								<input type="radio" name="config[design][border_style]" value="<?php echo esc_attr( $border ); ?>" <?php checked( $design['border_style'], $border ); ?> data-iftp-design="border_style" />
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
						<input type="text" class="iftp-btn-field__control iftp-btn-color__hex" id="iftp-btn-border-color" name="config[design][border_color]" value="<?php echo esc_attr( $auto_bc ? $design['color'] : $design['border_color'] ); ?>" maxlength="7" spellcheck="false" pattern="#[0-9a-fA-F]{6}" data-iftp-design="border_color" />
					</span>
					<label class="iftp-btn-check">
						<input type="checkbox" name="config[design][border_color_auto]" value="1" <?php checked( $auto_bc ); ?> data-iftp-design="border_color_auto" />
						<?php esc_html_e( 'Use the style\'s own', 'ifthenpay-pay-button' ); ?>
					</label>
				</div>
			</div>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="config[design][shadow]" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="config[design][shadow]" value="1" <?php checked( $design['shadow'] ); ?> data-iftp-design="shadow" />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Soft shadow', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'A glow in the button\'s colour that lifts a little on hover.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>

			<?php
			self::render_fx_picker( 'effect', __( 'On hover', 'ifthenpay-pay-button' ), $effects, $design, __( 'What it does when the pointer is on it. Point at a sample to see it. Nudge moves the icon, so pick one below.', 'ifthenpay-pay-button' ) );
			self::render_fx_picker( 'attention', __( 'Catch the eye', 'ifthenpay-pay-button' ), $attentions, $design, __( 'Keeps going on its own to draw attention, and rests while the button is hovered. Payers who turn motion off get a still button.', 'ifthenpay-pay-button' ) );
			?>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="config[design][upper]" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="config[design][upper]" value="1" <?php checked( $design['upper'] ); ?> data-iftp-design="upper" />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Capital letters', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'PAY NOW, a little spaced out.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>
		</div>

		<div class="iftp-btn-design__grid">
			<fieldset class="iftp-btn-field iftp-btn-design__wide">
				<legend class="iftp-btn-field__label"><?php esc_html_e( 'Icon', 'ifthenpay-pay-button' ); ?></legend>
				<span class="iftp-btn-seg iftp-btn-seg--icons">
					<?php foreach ( $icons as $icon => $label ) : ?>
						<label class="iftp-btn-seg__option">
							<input type="radio" name="config[design][icon]" value="<?php echo esc_attr( $icon ); ?>" <?php checked( $design['icon'], $icon ); ?> data-iftp-design="icon" />
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
							<input type="radio" name="config[design][full]" value="<?php echo esc_attr( (string) $width ); ?>" <?php checked( $design['full'] ? '1' : '0', (string) $width ); ?> data-iftp-design="full" />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</span>
			</fieldset>
		</div>

		<label class="iftp-btn-np__toggle">
			<input type="hidden" name="config[design][logos]" value="0" />
			<input type="checkbox" class="iftp-btn-switch" name="config[design][logos]" value="1" <?php checked( $design['logos'] ); ?> data-iftp-design="logos" />
			<span class="iftp-btn-np__toggle-text">
				<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Show the payment logos under the button', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Payers see how they can pay before they click. Uses the methods this button offers.', 'ifthenpay-pay-button' ); ?></span>
			</span>
		</label>
		<?php
		self::close_card();
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
	 */
	private static function render_fx_picker( string $key, string $legend, array $options, array $design, string $help = '' ): void {
		$help_id = 'iftp-btn-fx-' . $key . '-help';
		?>
		<fieldset class="iftp-btn-field iftp-btn-design__wide"<?php echo '' !== $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>>
			<legend class="iftp-btn-field__label"><?php echo esc_html( $legend ); ?></legend>
			<span class="iftp-btn-fxpick<?php echo 'effect' === $key ? ' iftp-btn-fxpick--hover' : ''; ?>">
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
					<label class="iftp-btn-fxpick__item">
						<input type="radio" name="config[design][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $design[ $key ], $value ); ?> data-iftp-design="<?php echo esc_attr( $key ); ?>" />
						<span class="iftp-btn-fxpick__stage" aria-hidden="true" data-iftp-fx-sample="<?php echo esc_attr( $key ); ?>" data-iftp-fx-value="<?php echo esc_attr( $value ); ?>">
							<?php echo '<span class="iftp-btn-pay">' . ButtonDesign::preview_markup( $sample, __( 'Pay', 'ifthenpay-pay-button' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes. ?>
						</span>
						<span class="iftp-btn-fxpick__name"><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</span>
			<?php if ( '' !== $help ) : ?>
				<p class="iftp-btn-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * How the amount is set: fixed, chosen by the payer, or carried by the link.
	 *
	 * @param Button $button The button.
	 * @param bool   $is_new Whether it's not saved yet.
	 */
	private function render_amount( Button $button, bool $is_new ): void {
		$config = $button->config;
		$modes  = array(
			Button::MODE_FIXED => array( __( 'Fixed', 'ifthenpay-pay-button' ), __( 'Everyone pays the same.', 'ifthenpay-pay-button' ) ),
			Button::MODE_PAYER => array( __( 'Payer chooses', 'ifthenpay-pay-button' ), __( 'Donations, tips, top-ups.', 'ifthenpay-pay-button' ) ),
			Button::MODE_LINK  => array( __( 'From the link', 'ifthenpay-pay-button' ), __( 'Another plugin sends the total.', 'ifthenpay-pay-button' ) ),
		);

		self::open_card( 'iftp-btn-ec-amount', __( 'Amount', 'ifthenpay-pay-button' ), __( 'In euros. A signed link can always set its own amount.', 'ifthenpay-pay-button' ) );
		?>
		<fieldset class="iftp-btn-segment" data-iftp-mode>
			<legend class="screen-reader-text"><?php esc_html_e( 'How the amount is set', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $modes as $mode => $labels ) : ?>
				<label class="iftp-btn-segment__option">
					<input type="radio" name="config[amount_mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $config['amount_mode'], $mode ); ?> />
					<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
					<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="iftp-btn-editor__pair" data-iftp-when-mode="fixed">
			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-amount"><?php esc_html_e( 'Amount (€)', 'ifthenpay-pay-button' ); ?></label>
				<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-amount" name="config[amount]" min="<?php echo esc_attr( (string) Button::MIN_AMOUNT ); ?>" step="0.01" inputmode="decimal" value="<?php echo esc_attr( number_format( (float) $config['amount'], 2, '.', '' ) ); ?>" data-iftp-preview="amount" />
			</div>
			<label class="iftp-btn-check">
				<input type="hidden" name="config[show_amount]" value="0" />
				<input type="checkbox" name="config[show_amount]" value="1" <?php checked( $config['show_amount'] ); ?> data-iftp-preview="show_amount" />
				<?php esc_html_e( 'Show the amount on the button', 'ifthenpay-pay-button' ); ?>
			</label>
		</div>

		<div class="iftp-btn-editor__pair" data-iftp-when-mode="payer link">
			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-min"><?php esc_html_e( 'Smallest (€)', 'ifthenpay-pay-button' ); ?></label>
				<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-min" name="config[min]" min="<?php echo esc_attr( (string) Button::MIN_AMOUNT ); ?>" step="0.01" inputmode="decimal" value="<?php echo esc_attr( number_format( (float) $config['min'], 2, '.', '' ) ); ?>" data-iftp-preview="min" />
			</div>
			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-max"><?php esc_html_e( 'Largest (€)', 'ifthenpay-pay-button' ); ?></label>
				<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-max" name="config[max]" min="<?php echo esc_attr( (string) Button::MIN_AMOUNT ); ?>" step="0.01" inputmode="decimal" value="<?php echo esc_attr( number_format( (float) $config['max'], 2, '.', '' ) ); ?>" data-iftp-preview="max" />
			</div>
		</div>

		<div class="iftp-btn-amount-link" data-iftp-when-mode="link">
			<?php if ( $is_new ) : ?>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'Save the button first; the link for the other plugin appears here.', 'ifthenpay-pay-button' ); ?></p>
			<?php else : ?>
				<?php ButtonsPage::print_copy_field( __( 'Link for the other plugin', 'ifthenpay-pay-button' ), $this->bridge_link( $button ), 'iftp-btn-amount-link-out' ); ?>
				<p class="iftp-btn-field__help">
					<?php esc_html_e( 'Paste it into the other plugin\'s "redirect after submit" and swap each {tag} for that plugin\'s own merge tag.', 'ifthenpay-pay-button' ); ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-goto-tab="connect"><?php esc_html_e( 'Choose the fields in Connect', 'ifthenpay-pay-button' ); ?></button>
				</p>
			<?php endif; ?>
		</div>

		<div class="iftp-btn-field" data-iftp-when-mode="payer">
			<label class="iftp-btn-field__label" for="iftp-btn-presets"><?php esc_html_e( 'Suggested amounts', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></label>
			<input type="text" class="iftp-btn-field__control" id="iftp-btn-presets" name="config[presets]" value="<?php echo esc_attr( implode( ', ', array_map( static fn( float $preset ): string => number_format( $preset, 2, '.', '' ), $config['presets'] ) ) ); ?>" placeholder="5, 10, 25" aria-describedby="iftp-btn-presets-help" data-iftp-preview="presets" />
			<p class="iftp-btn-field__help" id="iftp-btn-presets-help">
				<?php
				/* translators: %d: how many suggestions are allowed */
				echo esc_html( sprintf( __( 'Up to %d, separated by commas. The payer taps one or types their own.', 'ifthenpay-pay-button' ), Button::MAX_PRESETS ) );
				?>
			</p>
		</div>
		<?php
		self::close_card();
	}

	/**
	 * What the checkout asks for: each field off, optional or required.
	 *
	 * @param array<string, mixed> $config The button's config.
	 */
	private function render_fields( array $config ): void {
		$preview = new Button( 0, '', '', true, $config );
		$labels  = CheckoutService::field_labels( $preview );
		$states  = array(
			Button::FIELD_OFF      => __( 'Off', 'ifthenpay-pay-button' ),
			Button::FIELD_OPTIONAL => __( 'Optional', 'ifthenpay-pay-button' ),
			Button::FIELD_REQUIRED => __( 'Required', 'ifthenpay-pay-button' ),
		);

		self::open_card( 'iftp-btn-ec-fields', __( 'Payer details', 'ifthenpay-pay-button' ), __( 'Ask only what you need. With an email, the payer gets a receipt.', 'ifthenpay-pay-button' ) );
		?>
		<div class="iftp-btn-fields-list">
			<?php foreach ( Button::FIELDS as $field ) : ?>
				<div class="iftp-btn-fields-list__row">
					<div class="iftp-btn-fields-list__name">
						<?php if ( 'note' === $field ) : ?>
							<label class="screen-reader-text" for="iftp-btn-note-label"><?php esc_html_e( 'Label for the free-text field', 'ifthenpay-pay-button' ); ?></label>
							<input type="text" class="iftp-btn-field__control iftp-btn-fields-list__label" id="iftp-btn-note-label" name="config[note_label]" maxlength="60" value="<?php echo esc_attr( $config['note_label'] ); ?>" placeholder="<?php echo esc_attr( CheckoutTexts::get( 'note' ) ); ?>" data-iftp-preview="note_label" />
						<?php else : ?>
							<?php echo esc_html( $labels[ $field ] ); ?>
						<?php endif; ?>
					</div>
					<div class="iftp-btn-fields-list__value">
						<fieldset class="iftp-btn-pills">
							<legend class="screen-reader-text"><?php echo esc_html( $labels[ $field ] ); ?></legend>
							<?php foreach ( $states as $state => $state_label ) : ?>
								<label class="iftp-btn-pills__option">
									<input type="radio" name="config[fields][<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $state ); ?>" <?php checked( $config['fields'][ $field ] ?? Button::FIELD_OFF, $state ); ?> data-iftp-preview="field" data-iftp-field="<?php echo esc_attr( $field ); ?>" />
									<span><?php echo esc_html( $state_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		self::close_card();
	}

	/**
	 * The checkout form: the merchant's own fields, text blocks and page breaks, around the amount and the
	 * payer details. buttons.js draws and edits the list; it's submitted as JSON in one hidden field.
	 *
	 * @param Button $button The button.
	 */
	private function render_form( Button $button ): void {
		$types = FormFields::type_labels();
		$adds  = array_diff_key( $types, array( FormFields::TYPE_PAGE => true ) );

		self::open_card( 'iftp-btn-ec-form', __( 'Checkout form', 'ifthenpay-pay-button' ), __( 'Ask your own questions and split a long checkout into pages. Amount and Your details move like any other item.', 'ifthenpay-pay-button' ) );
		?>
		<div class="iftp-btn-fb" data-iftp-fb data-types="<?php echo esc_attr( (string) wp_json_encode( $types ) ); ?>">
			<input type="hidden" name="config[form]" value="<?php echo esc_attr( (string) wp_json_encode( $button->config['form'] ) ); ?>" data-iftp-fb-input />

			<div class="iftp-btn-field iftp-btn-fb__first" data-iftp-fb-first hidden>
				<label class="iftp-btn-field__label" for="iftp-btn-first-step"><?php esc_html_e( 'First page title', 'ifthenpay-pay-button' ); ?></label>
				<input type="text" class="iftp-btn-field__control" id="iftp-btn-first-step" name="config[first_step]" maxlength="60" value="<?php echo esc_attr( $button->config['first_step'] ); ?>" placeholder="<?php echo esc_attr( CheckoutTexts::get( 'first_step' ) ); ?>" data-iftp-preview="first_step" />
			</div>

			<ol class="iftp-btn-fb__list" data-iftp-fb-list></ol>

			<div class="iftp-btn-fb__add">
				<span class="iftp-btn-fb__add-label"><?php esc_html_e( 'Add', 'ifthenpay-pay-button' ); ?></span>
				<?php foreach ( $adds as $type => $label ) : ?>
					<button type="button" class="iftp-btn-fb__chip" data-iftp-fb-add="<?php echo esc_attr( $type ); ?>"><span class="iftp-btn-fb__chip-icon" data-iftp-fb-icon="<?php echo esc_attr( $type ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
				<button type="button" class="iftp-btn-fb__chip iftp-btn-fb__chip--page" data-iftp-fb-add="<?php echo esc_attr( FormFields::TYPE_PAGE ); ?>"><span class="iftp-btn-fb__chip-icon" data-iftp-fb-icon="page" aria-hidden="true"></span><?php echo esc_html( $types[ FormFields::TYPE_PAGE ] ); ?></button>
			</div>
			<p class="iftp-btn-field__help"><?php esc_html_e( 'Each field\'s answer shows in Payments, goes out with the webhook, and has a tag like {field:company} for the description and the popups. A pay link can fill one in with f_company=….', 'ifthenpay-pay-button' ); ?></p>
		</div>
		<?php
		self::close_card();
	}

	/**
	 * How many people can pay: no limit, one shared number, or spots and prices per option of a choice field.
	 * buttons.js lists the choice fields and draws the options table from the form builder as it changes.
	 *
	 * @param array<string, mixed> $config The button's config.
	 */
	private function render_spots( array $config ): void {
		$spots = $config['spots'];
		$days  = $this->settings->get_expiry_days();
		$modes = array(
			Spots::MODE_NONE    => array( __( 'No limit', 'ifthenpay-pay-button' ), __( 'Anyone can pay, as often as they like.', 'ifthenpay-pay-button' ) ),
			Spots::MODE_TOTAL   => array( __( 'Limited spots', 'ifthenpay-pay-button' ), __( 'One number for everyone.', 'ifthenpay-pay-button' ) ),
			Spots::MODE_OPTIONS => array( __( 'Spots per option', 'ifthenpay-pay-button' ), __( 'Spots and prices for each choice, e.g. each date.', 'ifthenpay-pay-button' ) ),
		);

		self::open_card( 'iftp-btn-ec-spots', __( 'Spots', 'ifthenpay-pay-button' ), __( 'Sell a fixed number of places, in total or per option, all from one button.', 'ifthenpay-pay-button' ) );
		?>
		<fieldset class="iftp-btn-segment">
			<legend class="screen-reader-text"><?php esc_html_e( 'How many people can pay', 'ifthenpay-pay-button' ); ?></legend>
			<?php foreach ( $modes as $mode => $labels ) : ?>
				<label class="iftp-btn-segment__option">
					<input type="radio" name="config[spots][mode]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( $spots['mode'], $mode ); ?> />
					<span class="iftp-btn-segment__title"><?php echo esc_html( $labels[0] ); ?></span>
					<span class="iftp-btn-segment__hint"><?php echo esc_html( $labels[1] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<div class="iftp-btn-spots" data-iftp-when-spots="options" data-iftp-spots data-saved="<?php echo esc_attr( (string) wp_json_encode( $spots['options'] ) ); ?>">
			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-spots-field"><?php esc_html_e( 'Split by', 'ifthenpay-pay-button' ); ?></label>
				<select class="iftp-btn-field__control" id="iftp-btn-spots-field" name="config[spots][field]" data-iftp-spots-field data-saved="<?php echo esc_attr( $spots['field'] ); ?>" aria-describedby="iftp-btn-spots-field-help"></select>
				<p class="iftp-btn-field__help" id="iftp-btn-spots-field-help"><?php esc_html_e( 'A Dropdown, Single choice or Multiple choice field from the checkout form. The payer always has to pick, so it is required. With Multiple choice, each ticked option takes its own spot and adds its own price.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<p class="iftp-btn-spots__empty" data-iftp-spots-empty hidden><?php esc_html_e( 'Add a Dropdown, Single choice or Multiple choice field to the checkout form above, with one option per date, session or ticket type. It shows up here straight away.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-spots__grid" data-iftp-spots-grid>
				<?php // Each input carries "Option · Spots" as its own label, so this header row is only for sighted users. ?>
				<div class="iftp-btn-spots__row iftp-btn-spots__row--head" aria-hidden="true">
					<span><?php esc_html_e( 'Option', 'ifthenpay-pay-button' ); ?></span>
					<span><?php esc_html_e( 'Spots', 'ifthenpay-pay-button' ); ?></span>
					<span data-iftp-spots-price><?php esc_html_e( 'Price (€)', 'ifthenpay-pay-button' ); ?></span>
				</div>
				<div data-iftp-spots-rows></div>
			</div>
			<p class="iftp-btn-field__help" data-iftp-spots-sum></p>
		</div>

		<div class="iftp-btn-field" data-iftp-when-spots="total options">
			<label class="iftp-btn-field__label" for="iftp-btn-spots-total">
				<span data-iftp-when-spots="total"><?php esc_html_e( 'Spots', 'ifthenpay-pay-button' ); ?></span>
				<span data-iftp-when-spots="options"><?php esc_html_e( 'Total spots', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></span>
			</label>
			<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-spots-total" name="config[spots][total]" min="0" max="<?php echo esc_attr( (string) Spots::MAX_SPOTS ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( $spots['total'] > 0 ? (string) $spots['total'] : '' ); ?>" aria-describedby="iftp-btn-spots-total-help" />
			<p class="iftp-btn-field__help" id="iftp-btn-spots-total-help">
				<span data-iftp-when-spots="total"><?php esc_html_e( 'Once they are all taken, the button shows Sold out.', 'ifthenpay-pay-button' ); ?></span>
				<span data-iftp-when-spots="options"><?php esc_html_e( 'A cap across every option, e.g. the size of the venue. Leave it empty to just add the options up.', 'ifthenpay-pay-button' ); ?></span>
			</p>
		</div>

		<label class="iftp-btn-np__toggle">
			<input type="hidden" name="config[spots][quantity]" value="0" />
			<input type="checkbox" class="iftp-btn-switch" name="config[spots][quantity]" value="1" <?php checked( $spots['quantity'] ); ?> />
			<span class="iftp-btn-np__toggle-text">
				<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Payer picks how many', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Each one takes a spot, and a fixed amount is charged for each.', 'ifthenpay-pay-button' ); ?></span>
			</span>
		</label>
		<div class="iftp-btn-field" data-iftp-when-quantity="1">
			<label class="iftp-btn-field__label" for="iftp-btn-spots-each"><?php esc_html_e( 'Most in one payment', 'ifthenpay-pay-button' ); ?></label>
			<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-spots-each" name="config[spots][max_each]" min="1" max="<?php echo esc_attr( (string) Spots::MAX_EACH ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( (string) $spots['max_each'] ); ?>" />
		</div>

		<div class="iftp-btn-spots__left" data-iftp-when-spots="total options">
			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="config[spots][show_left]" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="config[spots][show_left]" value="1" <?php checked( $spots['show_left'] ); ?> />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Say when few are left', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'The checkout shows “Only 4 spots left”. Sold-out options always show.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>
			<div class="iftp-btn-field" data-iftp-when-left="1">
				<label class="iftp-btn-field__label" for="iftp-btn-spots-below"><?php esc_html_e( 'From this many left', 'ifthenpay-pay-button' ); ?></label>
				<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-spots-below" name="config[spots][left_below]" min="1" max="<?php echo esc_attr( (string) Spots::MAX_SPOTS ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( (string) $spots['left_below'] ); ?>" />
			</div>
			<p class="iftp-btn-field__help">
				<?php
				/* translators: %d: how many days a payment link stays open */
				echo esc_html( sprintf( _n( 'A spot is taken as soon as the payer goes to ifthenpay. If they never pay, it opens again when their link expires, after %d day (Gateway Configuration › Expiry days).', 'A spot is taken as soon as the payer goes to ifthenpay. If they never pay, it opens again when their link expires, after %d days (Gateway Configuration › Expiry days).', $days, 'ifthenpay-pay-button' ), $days ) );
				?>
			</p>
		</div>
		<?php
		self::close_card();
	}

	/**
	 * Which of the site's enabled methods this button offers.
	 *
	 * @param array<string, mixed> $config The button's config.
	 */
	private function render_methods( array $config ): void {
		$methods = array_values( array_filter( $this->settings->get_methods(), static fn( array $method ): bool => ! empty( $method['enabled'] ) && '' !== $method['account'] ) );

		self::open_card( 'iftp-btn-ec-methods', __( 'Payment methods', 'ifthenpay-pay-button' ), __( 'Leave all unticked to offer every method switched on in Gateway Configuration.', 'ifthenpay-pay-button' ) );

		if ( array() === $methods ) {
			echo '<p class="iftp-btn-field__help">' . esc_html__( 'No payment method is switched on yet.', 'ifthenpay-pay-button' ) . ' <a href="' . esc_url( GatewaySettingsPage::url() ) . '">' . esc_html__( 'Open Gateway Configuration', 'ifthenpay-pay-button' ) . '</a></p>';
			self::close_card();

			return;
		}
		?>
		<ul class="iftp-btn-method-picks">
			<?php foreach ( $methods as $method ) : ?>
				<?php $entity = strtoupper( (string) $method['entity'] ); ?>
				<li>
					<label class="iftp-btn-method-pick">
						<input type="checkbox" name="config[methods][]" value="<?php echo esc_attr( $entity ); ?>" <?php checked( in_array( $entity, $config['methods'], true ) ); ?> data-iftp-preview="method" />
						<?php if ( '' !== (string) ( $method['logo'] ?? '' ) ) : ?>
							<img src="<?php echo esc_url( $method['logo'] ?? '' ); ?>"<?php echo ButtonRenderer::dark_logo_attr( $method ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in dark_logo_attr(). ?> alt="" height="18" data-iftp-method-logo />
						<?php endif; ?>
						<span><?php echo esc_html( '' !== $method['alias'] ? $method['alias'] : $entity ); ?></span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		self::close_card();
	}

	/**
	 * Where a paid payer goes.
	 *
	 * @param array<string, mixed> $config The button's config.
	 */
	private function render_after( array $config ): void {
		self::open_card( 'iftp-btn-ec-after', __( 'After payment', 'ifthenpay-pay-button' ), __( 'Failed, cancelled and pending outcomes always show the popup set in Default Checkout.', 'ifthenpay-pay-button' ) );
		?>
		<div class="iftp-btn-field">
			<label class="iftp-btn-field__label" for="iftp-btn-paid-redirect"><?php esc_html_e( 'When paid', 'ifthenpay-pay-button' ); ?></label>
			<select class="iftp-btn-field__control" id="iftp-btn-paid-redirect" name="config[paid_redirect]" data-iftp-paid-type>
				<option value="" <?php selected( $config['paid_redirect'], '' ); ?>><?php esc_html_e( 'Use the Default Checkout setting', 'ifthenpay-pay-button' ); ?></option>
				<option value="popup" <?php selected( $config['paid_redirect'], 'popup' ); ?>><?php esc_html_e( 'Show the thank-you popup', 'ifthenpay-pay-button' ); ?></option>
				<option value="page" <?php selected( $config['paid_redirect'], 'page' ); ?>><?php esc_html_e( 'Go to a page on this site', 'ifthenpay-pay-button' ); ?></option>
				<option value="url" <?php selected( $config['paid_redirect'], 'url' ); ?>><?php esc_html_e( 'Go to an address', 'ifthenpay-pay-button' ); ?></option>
			</select>
			<p class="iftp-btn-field__help"><a href="<?php echo esc_url( CheckoutSettingsPage::url() ); ?>"><?php esc_html_e( 'Edit the popup messages in Default Checkout', 'ifthenpay-pay-button' ); ?></a></p>
		</div>
		<div class="iftp-btn-field" data-iftp-when-paid="page">
			<label class="iftp-btn-field__label" for="iftp-btn-paid-page"><?php esc_html_e( 'Page', 'ifthenpay-pay-button' ); ?></label>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'config[paid_page]',
					'id'                => 'iftp-btn-paid-page',
					'class'             => 'iftp-btn-field__control',
					'selected'          => (int) $config['paid_page'],
					'show_option_none'  => esc_html__( 'Choose a page…', 'ifthenpay-pay-button' ),
					'option_none_value' => '0',
				)
			);
			?>
		</div>
		<div class="iftp-btn-field" data-iftp-when-paid="url">
			<label class="iftp-btn-field__label" for="iftp-btn-paid-url"><?php esc_html_e( 'Address', 'ifthenpay-pay-button' ); ?></label>
			<input type="url" class="iftp-btn-field__control" id="iftp-btn-paid-url" name="config[paid_url]" value="<?php echo esc_attr( $config['paid_url'] ); ?>" placeholder="https://" />
		</div>
		<?php
		self::close_card();
	}

	/**
	 * The request to an invoicing app on every paid payment, written the way that app's API wants it.
	 *
	 * @param Button $button The button.
	 * @param bool   $is_new Whether it's not saved yet.
	 */
	private function render_invoice( Button $button, bool $is_new ): void {
		$hook = $button->config['invoice_hook'];
		$log  = $is_new ? array() : InvoiceHook::log( $button->id );
		$tags = MergeTags::available( true ) + InvoiceHook::extra_tags();

		self::open_card( 'iftp-btn-ec-invoice', __( 'Send to your invoicing app', 'ifthenpay-pay-button' ), __( 'The moment a payment is paid, its details go straight to InvoiceXpress, Moloni, Vendus, TOConline or any app with an API, so nobody copies them by hand.', 'ifthenpay-pay-button' ) );
		?>
		<label class="iftp-btn-np__toggle">
			<input type="hidden" name="config[invoice_hook][enabled]" value="0" />
			<input type="checkbox" class="iftp-btn-switch" name="config[invoice_hook][enabled]" value="1" <?php checked( $hook['enabled'] ); ?> />
			<span class="iftp-btn-np__toggle-text">
				<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Send each paid payment', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Only paid ones. If the app is down, I try again after 5 minutes, 30 minutes and 3 hours.', 'ifthenpay-pay-button' ); ?></span>
			</span>
		</label>

		<div class="iftp-btn-invoice" data-iftp-when-invoice="1" data-iftp-invoice>
			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-invoice-url"><?php esc_html_e( 'Address', 'ifthenpay-pay-button' ); ?></label>
				<input type="url" class="iftp-btn-field__control" id="iftp-btn-invoice-url" name="config[invoice_hook][url]" value="<?php echo esc_attr( $hook['url'] ); ?>" placeholder="https://" spellcheck="false" aria-describedby="iftp-btn-invoice-url-help" data-iftp-invoice-key="url" />
				<p class="iftp-btn-field__help" id="iftp-btn-invoice-url-help"><?php esc_html_e( 'Where the app\'s API creates an invoice or invoice-receipt; its API docs give it. Some apps take the API key in the address, others in a header.', 'ifthenpay-pay-button' ); ?></p>
			</div>

			<div class="iftp-btn-design__grid">
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-invoice-method"><?php esc_html_e( 'Method', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control" id="iftp-btn-invoice-method" name="config[invoice_hook][method]" data-iftp-invoice-key="method">
						<?php foreach ( InvoiceHook::METHODS as $method ) : ?>
							<option value="<?php echo esc_attr( $method ); ?>" <?php selected( $hook['method'], $method ); ?>><?php echo esc_html( $method ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="iftp-btn-field">
					<label class="iftp-btn-field__label" for="iftp-btn-invoice-format"><?php esc_html_e( 'Body', 'ifthenpay-pay-button' ); ?></label>
					<select class="iftp-btn-field__control" id="iftp-btn-invoice-format" name="config[invoice_hook][format]" data-iftp-invoice-key="format">
						<option value="json" <?php selected( $hook['format'], 'json' ); ?>><?php esc_html_e( 'JSON', 'ifthenpay-pay-button' ); ?></option>
						<option value="form" <?php selected( $hook['format'], 'form' ); ?>><?php esc_html_e( 'Form fields (name=value)', 'ifthenpay-pay-button' ); ?></option>
					</select>
				</div>
			</div>

			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-invoice-headers"><?php esc_html_e( 'Headers', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></label>
				<textarea class="iftp-btn-field__control iftp-btn-code-input" id="iftp-btn-invoice-headers" name="config[invoice_hook][headers]" rows="2" spellcheck="false" placeholder="Authorization: Bearer YOUR_API_KEY" aria-describedby="iftp-btn-invoice-headers-help" data-iftp-invoice-key="headers"><?php echo esc_textarea( $hook['headers'] ); ?></textarea>
				<p class="iftp-btn-field__help" id="iftp-btn-invoice-headers-help"><?php esc_html_e( 'One per line, as Name: value. The content type follows the body you picked.', 'ifthenpay-pay-button' ); ?></p>
			</div>

			<div class="iftp-btn-field" data-iftp-tag-scope>
				<label class="iftp-btn-field__label" for="iftp-btn-invoice-body"><?php esc_html_e( 'What to send', 'ifthenpay-pay-button' ); ?></label>
				<textarea class="iftp-btn-field__control iftp-btn-code-input" id="iftp-btn-invoice-body" name="config[invoice_hook][body]" rows="16" spellcheck="false" aria-describedby="iftp-btn-invoice-body-help" data-iftp-tag-target data-iftp-invoice-key="body"><?php echo esc_textarea( $hook['body'] ); ?></textarea>
				<p class="iftp-btn-field__help" id="iftp-btn-invoice-body-help"><?php esc_html_e( 'Shape it the way the app\'s API asks. Tags fill in each payment: put text tags inside quotes ("{name}"); {amount_raw}, {unit_price} and {quantity} are plain numbers. For form fields, write one name=value per line.', 'ifthenpay-pay-button' ); ?></p>
				<?php ButtonsPage::print_tags( $tags ); ?>
			</div>

			<div class="iftp-btn-invoice__test">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-test-invoice="<?php echo esc_attr( (string) $button->id ); ?>" <?php disabled( $is_new ); ?>><?php esc_html_e( 'Send a test', 'ifthenpay-pay-button' ); ?></button>
				<p class="iftp-btn-field__help" role="status" data-iftp-invoice-result><?php echo $is_new ? esc_html__( 'Save the button first to send a test.', 'ifthenpay-pay-button' ) : esc_html__( 'Sends what\'s above, saved or not, with this button\'s latest paid payment (or a made-up one). It may create a real invoice, so use the app\'s test mode if it has one.', 'ifthenpay-pay-button' ); ?></p>
				<pre class="iftp-btn-code" data-iftp-invoice-reply hidden></pre>
			</div>

			<?php if ( array() !== $log ) : ?>
				<div class="iftp-btn-invoice__log">
					<h3 class="iftp-btn-invoice__log-title"><?php esc_html_e( 'Last sent', 'ifthenpay-pay-button' ); ?></h3>
					<ul class="iftp-btn-invoice__log-list">
						<?php foreach ( $log as $entry ) : ?>
							<li class="iftp-btn-invoice__log-item<?php echo $entry['ok'] ? ' is-ok' : ' is-error'; ?>">
								<span class="iftp-btn-invoice__log-dot" aria-hidden="true"></span>
								<span class="iftp-btn-invoice__log-ref"><?php echo esc_html( $entry['ref'] ); ?></span>
								<span class="iftp-btn-invoice__log-code"><?php echo esc_html( $entry['code'] > 0 ? (string) $entry['code'] : __( 'Not sent', 'ifthenpay-pay-button' ) ); ?></span>
								<span class="iftp-btn-invoice__log-message"><?php echo esc_html( $entry['message'] ); ?></span>
								<time class="iftp-btn-invoice__log-time" datetime="<?php echo esc_attr( gmdate( 'c', $entry['time'] ) ); ?>"><?php echo esc_html( Dates::local( (string) get_option( 'date_format' ) . ' H:i', $entry['time'] ) ); ?></time>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<p class="iftp-btn-field__help iftp-btn-invoice__alt">
			<?php esc_html_e( 'Prefer Zapier, Make or n8n? The webhook in the Connect tab sends every payment to them as it is. Both can be on.', 'ifthenpay-pay-button' ); ?>
		</p>
		<?php
		self::close_card();
	}

	/**
	 * What the link builder can carry: param => [label, starting merge tag].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function bridge_params(): array {
		return array(
			PayLinks::PARAM_AMOUNT    => array( __( 'Amount', 'ifthenpay-pay-button' ), '{total}' ),
			'payer_name'              => array( __( 'Name', 'ifthenpay-pay-button' ), '{name}' ),
			'email'                   => array( __( 'Email', 'ifthenpay-pay-button' ), '{email}' ),
			'phone'                   => array( __( 'Phone', 'ifthenpay-pay-button' ), '{phone}' ),
			'nif'                     => array( __( 'Tax number', 'ifthenpay-pay-button' ), '{nif}' ),
			'note'                    => array( __( 'Note', 'ifthenpay-pay-button' ), '{message}' ),
			PayLinks::PARAM_REFERENCE => array( __( 'Reference', 'ifthenpay-pay-button' ), '{entry_id}' ),
		);
	}

	/**
	 * The params ticked to start with.
	 *
	 * @param Button $button The button.
	 * @return string[]
	 */
	private static function bridge_preselected( Button $button ): array {
		// A fixed amount ignores the link's, so I only tick Amount when the button takes one.
		return Button::MODE_FIXED === $button->mode() ? array( 'payer_name', 'email', PayLinks::PARAM_REFERENCE ) : array( PayLinks::PARAM_AMOUNT, 'payer_name', 'email', PayLinks::PARAM_REFERENCE );
	}

	/**
	 * The redirect link with the starting tags, so it's right before buttons.js takes over.
	 *
	 * @param Button $button The button.
	 */
	private function bridge_link( Button $button ): string {
		$base  = $this->links->url( $button );
		$parts = array();

		foreach ( self::bridge_params() as $param => $labels ) {
			if ( in_array( $param, self::bridge_preselected( $button ), true ) ) {
				// The tag stays raw, like in buttons.js, so the other plugin still finds it.
				$parts[] = $param . '=' . $labels[1];
			}
		}

		return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . implode( '&', $parts );
	}

	/**
	 * The bridge: a link template for another plugin's redirect, and a webhook back to it.
	 *
	 * @param Button $button The button.
	 * @param bool   $is_new Whether it's not saved yet.
	 */
	private function render_bridge( Button $button, bool $is_new ): void {
		$params      = self::bridge_params();
		$base        = $is_new ? '' : $this->links->url( $button );
		$preselected = self::bridge_preselected( $button );

		self::open_card( 'iftp-btn-ec-bridge', __( 'Connect another plugin', 'ifthenpay-pay-button' ), __( 'For a form or booking plugin ifthenpay has no integration with: it sends the payer here after submit, and hears back when they pay.', 'ifthenpay-pay-button' ) );
		?>
		<ol class="iftp-btn-bridge__steps">
			<li><?php esc_html_e( 'In the other plugin, set "redirect after submit" to the link below.', 'ifthenpay-pay-button' ); ?></li>
			<li><?php esc_html_e( 'Swap each {tag} for that plugin\'s own merge tag for the field. Leave out any you don\'t need.', 'ifthenpay-pay-button' ); ?></li>
			<li><?php esc_html_e( 'The reference comes back in Payments, the receipt and the webhook, so you can match the payment to its entry.', 'ifthenpay-pay-button' ); ?></li>
		</ol>

		<?php if ( $is_new ) : ?>
			<p class="iftp-btn-field__help"><?php esc_html_e( 'Save the button first; its link builder appears here.', 'ifthenpay-pay-button' ); ?></p>
		<?php else : ?>
			<div class="iftp-btn-builder" data-iftp-builder data-base="<?php echo esc_attr( $base ); ?>">
				<?php foreach ( $params as $param => $param_labels ) : ?>
					<div class="iftp-btn-builder__row">
						<label class="iftp-btn-builder__check">
							<input type="checkbox" data-iftp-builder-use="<?php echo esc_attr( $param ); ?>" <?php checked( in_array( $param, $preselected, true ) ); ?> />
							<span><?php echo esc_html( $param_labels[0] ); ?></span>
							<code><?php echo esc_html( $param ); ?></code>
						</label>
						<label class="screen-reader-text" for="iftp-btn-tag-<?php echo esc_attr( $param ); ?>">
							<?php
							/* translators: %s: a field, e.g. "Email" */
							echo esc_html( sprintf( __( 'Merge tag for %s', 'ifthenpay-pay-button' ), $param_labels[0] ) );
							?>
						</label>
						<input type="text" class="iftp-btn-field__control iftp-btn-builder__tag" id="iftp-btn-tag-<?php echo esc_attr( $param ); ?>" value="<?php echo esc_attr( $param_labels[1] ); ?>" spellcheck="false" data-iftp-builder-tag="<?php echo esc_attr( $param ); ?>" />
					</div>
				<?php endforeach; ?>
				<?php ButtonsPage::print_copy_field( __( 'Redirect link', 'ifthenpay-pay-button' ), $this->bridge_link( $button ), 'iftp-btn-builder-out' ); ?>
				<p class="iftp-btn-field__help" data-iftp-builder-warning hidden><?php esc_html_e( 'This button\'s amount is fixed, so the amount in the link is ignored. Switch Amount to "From the link" to take the form\'s total.', 'ifthenpay-pay-button' ); ?></p>
			</div>

			<label class="iftp-btn-np__toggle">
				<input type="hidden" name="config[skip_form]" value="0" />
				<input type="checkbox" class="iftp-btn-switch" name="config[skip_form]" value="1" <?php checked( $button->config['skip_form'] ); ?> />
				<span class="iftp-btn-np__toggle-text">
					<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Go straight to ifthenpay', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'When the link already fills in everything required, the payer skips the form.', 'ifthenpay-pay-button' ); ?></span>
				</span>
			</label>

			<div class="iftp-btn-field">
				<label class="iftp-btn-field__label" for="iftp-btn-webhook-url"><?php esc_html_e( 'Webhook', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__optional"><?php esc_html_e( '(optional)', 'ifthenpay-pay-button' ); ?></span></label>
				<span class="iftp-btn-inline">
					<input type="url" class="iftp-btn-field__control" id="iftp-btn-webhook-url" name="config[webhook_url]" value="<?php echo esc_attr( $button->config['webhook_url'] ); ?>" placeholder="https://hooks.zapier.com/…" aria-describedby="iftp-btn-webhook-help" />
					<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-test-webhook="<?php echo esc_attr( (string) $button->id ); ?>" <?php disabled( '' === $button->config['webhook_url'] ); ?>><?php esc_html_e( 'Send a test', 'ifthenpay-pay-button' ); ?></button>
				</span>
				<p class="iftp-btn-field__help" id="iftp-btn-webhook-help"><?php esc_html_e( 'We POST a JSON event here when a payment is paid, fails or is cancelled. Zapier, Make and n8n take it as it is.', 'ifthenpay-pay-button' ); ?></p>
				<p class="iftp-btn-field__help" data-iftp-test-result role="status"></p>
			</div>
			<input type="hidden" name="config[webhook_secret]" value="<?php echo esc_attr( $button->config['webhook_secret'] ); ?>" />
			<details class="iftp-btn-details">
				<summary><?php esc_html_e( 'Verify the webhook signature', 'ifthenpay-pay-button' ); ?></summary>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'Each request carries X-Iftp-Signature: t={timestamp},v1={signature}. The signature is the HMAC-SHA256 of "{timestamp}.{body}" with this secret.', 'ifthenpay-pay-button' ); ?></p>
				<?php ButtonsPage::print_copy_field( __( 'Signing secret', 'ifthenpay-pay-button' ), $button->config['webhook_secret'], 'iftp-btn-webhook-secret' ); ?>
				<label class="iftp-btn-check">
					<input type="checkbox" name="rotate_secret" value="1" />
					<?php esc_html_e( 'Make a new secret when I save (the old one stops working)', 'ifthenpay-pay-button' ); ?>
				</label>
			</details>
			<details class="iftp-btn-details">
				<summary><?php esc_html_e( 'For developers: signed links', 'ifthenpay-pay-button' ); ?></summary>
				<p class="iftp-btn-field__help"><?php esc_html_e( 'A signed link locks its amount and reference, so nobody can edit them in the address bar. Build one in PHP:', 'ifthenpay-pay-button' ); ?></p>
				<pre class="iftp-btn-code"><code><?php echo esc_html( "iftp_btn_payment_url( {$button->id}, array(\n\t'amount'    => 49.90,\n\t'reference' => 'ORDER-1042',\n\t'email'     => 'ana@example.com',\n\t'expires'   => time() + DAY_IN_SECONDS,\n) );" ); ?></code></pre>
			</details>
		<?php endif; ?>
		<?php
		self::close_card();
	}

	/**
	 * The live preview: the button on a light or dark stage, and the checkout it opens under it.
	 *
	 * @param Button $button The button.
	 */
	private function render_preview( Button $button ): void {
		$design = $button->config['design'];
		?>
		<section class="iftp-btn-preview" aria-labelledby="iftp-btn-preview-title">
			<div class="iftp-btn-preview__head">
				<h2 class="iftp-btn-preview__title" id="iftp-btn-preview-title"><?php esc_html_e( 'Preview', 'ifthenpay-pay-button' ); ?></h2>
				<span class="iftp-btn-stagebg" role="group" aria-label="<?php esc_attr_e( 'Preview background', 'ifthenpay-pay-button' ); ?>">
					<button type="button" class="iftp-btn-stagebg__option" data-iftp-stage-bg="light" aria-pressed="true"><span class="screen-reader-text"><?php esc_html_e( 'Light background', 'ifthenpay-pay-button' ); ?></span></button>
					<button type="button" class="iftp-btn-stagebg__option iftp-btn-stagebg__option--dark" data-iftp-stage-bg="dark" aria-pressed="false"><span class="screen-reader-text"><?php esc_html_e( 'Dark background', 'ifthenpay-pay-button' ); ?></span></button>
				</span>
			</div>
			<div class="iftp-btn-preview__stage" data-iftp-stage data-bg="light">
				<span class="iftp-btn-pay iftp-btn-preview__pay" data-iftp-preview-button>
					<?php echo ButtonDesign::preview_markup( $design, $button->display_label() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview_markup() escapes. ?>
				</span>
				<span class="iftp-btn-pay__logos" data-iftp-preview-logos hidden></span>
			</div>
			<p class="iftp-btn-preview__note" data-iftp-theme-note<?php echo ButtonDesign::SOURCE_CUSTOM === $design['source'] ? ' hidden' : ''; ?>><?php esc_html_e( 'A close guess: on your pages it takes your theme\'s exact button style.', 'ifthenpay-pay-button' ); ?></p>
			<?php $own = CheckoutLook::inline_style( $button ); ?>
			<div class="iftp-btn-preview__sheet iftp-btn-look" aria-hidden="true" data-iftp-look-mirror<?php echo '' !== $own ? ' style="' . esc_attr( $own ) . '"' : ''; ?>>
				<div class="iftp-btn-form iftp-btn-form--preview">
					<div class="iftp-btn-steps" data-iftp-preview-steps hidden>
						<div class="iftp-btn-steps__head">
							<span class="iftp-btn-steps__title" data-iftp-preview-step-title></span>
							<span class="iftp-btn-steps__count" data-iftp-preview-step-count></span>
							<span class="iftp-btn-steps__pct" data-iftp-preview-step-pct></span>
						</div>
						<ol class="iftp-btn-steps__track" data-iftp-preview-track></ol>
					</div>
					<div class="iftp-btn-form__body">
						<div class="iftp-btn-form__step" data-iftp-preview-step>
							<div class="iftp-btn-form__summary">
								<p class="iftp-btn-form__title" data-iftp-preview-title><?php echo esc_html( '' !== $button->title ? $button->title : __( 'Your title', 'ifthenpay-pay-button' ) ); ?></p>
								<p class="iftp-btn-form__text" data-iftp-preview-summary<?php echo '' === $button->config['summary'] ? ' hidden' : ''; ?>><?php echo esc_html( $button->config['summary'] ); ?></p>
								<p class="iftp-btn-form__amount" data-iftp-preview-amount></p>
							</div>
						</div>
					</div>
					<div class="iftp-btn-form__actions">
						<div class="iftp-btn-form__buttons">
							<span class="iftp-btn-form__submit" data-iftp-preview-submit data-pay="<?php echo esc_attr( CheckoutTexts::get( 'pay' ) ); ?>" data-next="<?php echo esc_attr( CheckoutTexts::get( 'next' ) ); ?>"><?php echo esc_html( CheckoutTexts::get( 'pay' ) ); ?></span>
						</div>
					</div>
					<footer class="iftp-btn-form__footer">
						<ul class="iftp-btn-form__methods" data-iftp-preview-methods></ul>
					</footer>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Every way to place a saved button.
	 *
	 * @param Button $button The button.
	 */
	private function render_share( Button $button ): void {
		?>
		<section class="iftp-btn-placeit" aria-labelledby="iftp-btn-placeit-title">
			<h2 class="iftp-btn-preview__title" id="iftp-btn-placeit-title"><?php esc_html_e( 'Place it', 'ifthenpay-pay-button' ); ?></h2>
			<?php ButtonsPage::print_copy_field( __( 'Shortcode', 'ifthenpay-pay-button' ), sprintf( '[%1$s id="%2$d"]', Shortcode::TAG, $button->id ), 'iftp-btn-placeit-sc' ); ?>
			<?php ButtonsPage::print_copy_field( __( 'Pay link', 'ifthenpay-pay-button' ), $this->links->url( $button ), 'iftp-btn-placeit-link' ); ?>
			<p class="iftp-btn-field__help"><?php esc_html_e( 'In the block editor, add the "ifthenpay Pay Button" block and pick this button.', 'ifthenpay-pay-button' ); ?></p>
		</section>
		<?php
	}
}
