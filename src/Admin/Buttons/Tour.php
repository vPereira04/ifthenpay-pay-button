<?php
/**
 * The guided tour of the Buttons screens.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Checkout\CheckoutLook;
use Ifthenpay\PayButton\Checkout\CheckoutTexts;
use Ifthenpay\PayButton\Frontend\StepBar;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A spotlight tour in four chapters that follows the merchant across the screens: the list, the editor (filled in
 * for them, never saved), the AI studio (a scripted prompt, never sent) and back to the list. The words are here;
 * the browser side (tour.js) draws it and runs the scripted demo steps named in "act".
 */
class Tour {

	public const VAR       = 'iftp_tour';
	public const SEEN_META = 'iftp_btn_tour_seen';
	public const SCRIPT    = 'iftp-btn-tour';

	/**
	 * Which chapter this screen shows: the studio, the editor, or the list (and its closing chapter).
	 *
	 * @param bool $studio Whether the studio is showing.
	 */
	public static function chapter_for_screen( bool $studio ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only picks which view to show.
		if ( $studio ) {
			return 'ai';
		}

		if ( isset( $_GET[ ButtonsPage::EDIT_VAR ] ) ) {
			return 'editor';
		}

		$asked = isset( $_GET[ self::VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::VAR ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'outro' === $asked ? 'outro' : 'list';
	}

	/**
	 * Loads the tour's script and hands it this screen's chapter.
	 *
	 * @param bool $studio    Whether the studio is showing.
	 * @param bool $connected Whether the ifthenpay account is connected; without it there is nothing to tour.
	 */
	public static function enqueue( bool $studio, bool $connected ): void {
		if ( ! $connected ) {
			return;
		}

		$js       = 'assets/js/tour.js';
		$chapter  = self::chapter_for_screen( $studio );
		$chapters = self::chapters();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides whether to start.
		$asked = isset( $_GET[ self::VAR ] );
		$seen  = '1' === get_user_meta( get_current_user_id(), self::SEEN_META, true );

		wp_enqueue_script( self::SCRIPT, IFTP_BTN_URL . $js, array( 'iftp-btn-buttons' ), Plugin::asset_version( $js ), true );
		wp_localize_script(
			self::SCRIPT,
			'iftpBtnTour',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( ButtonsPage::AJAX_NONCE ),
				'name'    => $chapter,
				'chapter' => $chapters[ $chapter ],
				// The list starts it the first time, or when asked for (replay); the other screens only when the tour led there.
				'auto'    => 'list' === $chapter ? ( $asked || ! $seen ) : $asked,
				'listUrl' => ButtonsPage::list_url(),
				'replay'  => ButtonsPage::list_url( array( self::VAR => 'list' ) ),
				'i18n'    => array(
					'next'  => __( 'Next', 'ifthenpay-pay-button' ),
					'back'  => __( 'Back', 'ifthenpay-pay-button' ),
					'skip'  => __( 'Skip', 'ifthenpay-pay-button' ),
					'label' => __( 'Guided tour', 'ifthenpay-pay-button' ),
					/* translators: 1: chapter name, 2: current step, 3: number of steps */
					'count' => __( '%1$s · %2$s of %3$s', 'ifthenpay-pay-button' ),
					'demo'  => array(
						'title'       => __( 'Consultation', 'ifthenpay-pay-button' ),
						'summary'     => __( 'One hour with our team', 'ifthenpay-pay-button' ),
						'amount'      => '49',
						'prompt'      => __( 'A ticket for our Saturday workshop, 25 euros, 30 spots. Ask for name and email.', 'ifthenpay-pay-button' ),
						'label'       => __( 'Book now', 'ifthenpay-pay-button' ),
						'working'     => __( 'Building your button…', 'ifthenpay-pay-button' ),
						'checkout'    => 'ai' === $chapter ? self::checkout_markup() : '',
						'prices'      => array( __( 'Ticket: 25.00 €', 'ifthenpay-pay-button' ) ),
						'checkPrices' => __( 'Check the prices:', 'ifthenpay-pay-button' ),
						'reply'       => __( 'Done. Here is your workshop ticket, ready to look over.', 'ifthenpay-pay-button' ),
						'changes'     => array(
							array( __( 'Title: Saturday workshop', 'ifthenpay-pay-button' ), false ),
							array( __( 'Amount: 25.00 €', 'ifthenpay-pay-button' ), true ),
							array( __( 'Spots: 30 in total', 'ifthenpay-pay-button' ), false ),
							array( __( 'Asks for name and email', 'ifthenpay-pay-button' ), false ),
						),
					),
				),
			)
		);
	}

	/**
	 * The merchant's own checkout, as the pay link opens it, filled with a sample workshop: the card over the
	 * dimmed site, in the site's look, using the same markup and classes as the checkout preview.
	 */
	private static function checkout_markup(): string {
		$look  = ( new CheckoutSettings() )->get_look();
		$style = '';

		foreach ( CheckoutLook::variables( $look ) as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}

		ob_start();
		?>
		<div class="iftp-btn-look-preview__stage" data-view="checkout" style="<?php echo esc_attr( $style ); ?>">
			<div class="iftp-btn-look-preview__pagebg <?php echo esc_attr( CheckoutLook::page_classes( $look ) ); ?>" aria-hidden="true"></div>
			<div class="iftp-btn-look iftp-btn-look-preview__frame<?php echo 'theme' === $look['font'] ? ' is-theme-font' : ''; ?>" style="<?php echo esc_attr( $style ); ?>" inert>
				<div class="iftp-btn-look-preview__sheet">
					<div class="iftp-btn-form iftp-btn-form--steps">
						<header class="iftp-btn-form__header">
							<span class="iftp-btn-form__site"><?php echo esc_html( CheckoutTexts::get( 'header' ) ); ?></span>
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
									<p class="iftp-btn-form__title"><?php esc_html_e( 'Saturday workshop', 'ifthenpay-pay-button' ); ?></p>
									<p class="iftp-btn-form__text"><?php esc_html_e( 'A hands-on morning with our team', 'ifthenpay-pay-button' ); ?></p>
									<p class="iftp-btn-form__amount"><?php echo esc_html( '25,00 €' ); ?></p>
								</div>
								<div class="iftp-btn-form__field iftp-btn-form__field--half">
									<span class="iftp-btn-form__label"><?php echo esc_html( CheckoutTexts::get( 'name' ) ); ?></span>
									<input class="iftp-btn-form__input" type="text" value="Ana Silva" readonly tabindex="-1" />
								</div>
								<div class="iftp-btn-form__field iftp-btn-form__field--half">
									<span class="iftp-btn-form__label"><?php echo esc_html( CheckoutTexts::get( 'email' ) ); ?></span>
									<input class="iftp-btn-form__input" type="text" value="" placeholder="you@example.com" readonly tabindex="-1" />
								</div>
								<div class="iftp-btn-form__field">
									<span class="iftp-btn-form__label"><?php esc_html_e( 'Tickets', 'ifthenpay-pay-button' ); ?></span>
									<div class="iftp-btn-form__choices">
										<label class="iftp-btn-form__choice"><input type="radio" class="iftp-btn-form__tick" checked tabindex="-1" /><span><?php esc_html_e( 'Standard', 'ifthenpay-pay-button' ); ?></span></label>
										<label class="iftp-btn-form__choice"><input type="radio" class="iftp-btn-form__tick" tabindex="-1" /><span><?php esc_html_e( 'With lunch', 'ifthenpay-pay-button' ); ?></span></label>
									</div>
								</div>
							</div>
						</div>
						<div class="iftp-btn-form__actions">
							<div class="iftp-btn-form__buttons">
								<span class="iftp-btn-form__submit"><?php echo esc_html( CheckoutTexts::get( 'next' ) ); ?></span>
							</div>
						</div>
						<footer class="iftp-btn-form__footer">
							<span class="iftp-btn-form__secure"><?php echo esc_html( CheckoutTexts::get( 'secure' ) ); ?></span>
						</footer>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * The chapters: what they are called, where "Next" on their last step goes, and their steps. A step with no
	 * target is a centred card; one whose target isn't on screen is left out; "tab" opens an editor tab first;
	 * "act" names a scripted demo.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function chapters(): array {
		$edit = add_query_arg( self::VAR, '1', ButtonsPage::edit_url( 0 ) );
		$ai   = add_query_arg( self::VAR, '1', AiStudioPage::url( null ) );
		$out  = ButtonsPage::list_url( array( self::VAR => 'outro' ) );

		return array(
			'list'   => array(
				'label' => __( 'A quick look', 'ifthenpay-pay-button' ),
				'cta'   => __( 'Show me', 'ifthenpay-pay-button' ),
				'next'  => $edit,
				'steps' => array(
					array(
						'target' => '',
						'title'  => __( 'Welcome to Pay buttons', 'ifthenpay-pay-button' ),
						'text'   => __( 'A pay button takes a payment anywhere: on a page, in an email or from a QR code. This tour walks you through making one. You only have to watch.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-tour="starters"]',
						'title'  => __( 'Start from a template', 'ifthenpay-pay-button' ),
						'text'   => __( 'Pick a Donation, Event ticket or Fixed price and change anything. It starts turned off, so nothing goes live until you say so.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '.iftp-btn-bsummary',
						'title'  => __( 'How you are doing', 'ifthenpay-pay-button' ),
						'text'   => __( 'What came in, how many who opened a checkout paid, and how many walked away.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-bcards] > .iftp-btn-bcard:not(.iftp-btn-bcard--new)',
						'title'  => __( 'Your buttons', 'ifthenpay-pay-button' ),
						'text'   => __( 'Each button shows its payments and has an on/off switch. Open one to get its block, shortcode, pay link and QR code.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-tour="new"]',
						'title'  => __( 'Let\'s make one', 'ifthenpay-pay-button' ),
						'text'   => __( 'New button opens the editor. Click Show me and I\'ll fill in a demo button for you.', 'ifthenpay-pay-button' ),
					),
				),
			),
			'editor' => array(
				'label' => __( 'The editor', 'ifthenpay-pay-button' ),
				'cta'   => __( 'Now the AI', 'ifthenpay-pay-button' ),
				'next'  => $ai,
				'steps' => array(
					array(
						'target' => '',
						'title'  => __( 'This is the editor', 'ifthenpay-pay-button' ),
						'text'   => __( 'Everything about a button lives here, in five tabs. I\'ll fill a demo one in as we go. It is only for show: nothing is saved.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-basics"]',
						'title'  => __( 'Name it', 'ifthenpay-pay-button' ),
						'text'   => __( 'A title and a short description. The button\'s pay link is made from the title.', 'ifthenpay-pay-button' ),
						'act'    => 'basics',
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-amount"]',
						'title'  => __( 'Set the price', 'ifthenpay-pay-button' ),
						'text'   => __( 'A fixed amount, or let the payer choose. Here it is 49 €.', 'ifthenpay-pay-button' ),
						'act'    => 'amount',
					),
					array(
						'target' => '.iftp-btn-preview',
						'title'  => __( 'See it as payers will', 'ifthenpay-pay-button' ),
						'text'   => __( 'The preview follows every change you make, so you never have to guess how it looks.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-fields"]',
						'tab'    => 'form',
						'title'  => __( 'Ask only what you need', 'ifthenpay-pay-button' ),
						'text'   => __( 'Choose which details the payer fills in. This tab also has your own questions, and a number of spots to sell.', 'ifthenpay-pay-button' ),
						'act'    => 'fields',
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-design"]',
						'tab'    => 'design',
						'view'   => 'button',
						'title'  => __( 'Style the button', 'ifthenpay-pay-button' ),
						'text'   => __( 'Pick a ready-made look or build your own: colour, shape, size, even a pulse to catch the eye. I\'ll try one and give it a label.', 'ifthenpay-pay-button' ),
						'act'    => 'designButton',
					),
					array(
						'target' => '.iftp-btn-preview',
						'tab'    => 'design',
						'view'   => 'button',
						'title'  => __( 'The button, live', 'ifthenpay-pay-button' ),
						'text'   => __( 'This is the button on your page, wearing the look I just picked.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-look"]',
						'tab'    => 'design',
						'view'   => 'checkout',
						'title'  => __( 'Design the checkout too', 'ifthenpay-pay-button' ),
						'text'   => __( 'What the button opens has its own design. Use the site\'s look, or give this button a look of its own.', 'ifthenpay-pay-button' ),
						'act'    => 'designCheckout',
					),
					array(
						'target' => '[data-iftp-look-section="page"]',
						'tab'    => 'design',
						'view'   => 'checkout',
						'title'  => __( 'Set the scene', 'ifthenpay-pay-button' ),
						'text'   => __( 'The page behind the checkout: a glow, a gradient, an image or even a video, with moving effects on top.', 'ifthenpay-pay-button' ),
						'act'    => 'designBackground',
					),
					array(
						'target' => '.iftp-btn-editor__lookpreview',
						'tab'    => 'design',
						'view'   => 'checkout',
						'title'  => __( 'Your checkout, as payers see it', 'ifthenpay-pay-button' ),
						'text'   => __( 'The card, the colours and the background together. Every change shows here the moment you make it.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-after"]',
						'tab'    => 'after',
						'title'  => __( 'After they pay', 'ifthenpay-pay-button' ),
						'text'   => __( 'Choose what happens next: a thank-you popup, one of your pages, or any address.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-invoice"]',
						'tab'    => 'connect',
						'title'  => __( 'Connect it', 'ifthenpay-pay-button' ),
						'text'   => __( 'Send every paid order to your invoicing app, or let a form from another plugin send its payer here.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '.iftp-btn-editor__save',
						'tab'    => 'basics',
						'title'  => __( 'Create it', 'ifthenpay-pay-button' ),
						'text'   => __( 'This is where you would press Create button. Nothing was saved in this tour, so there is nothing to clean up.', 'ifthenpay-pay-button' ),
					),
				),
			),
			'ai'     => array(
				'label' => __( 'Build with AI', 'ifthenpay-pay-button' ),
				'cta'   => __( 'Finish', 'ifthenpay-pay-button' ),
				'next'  => $out,
				'steps' => array(
					array(
						'target' => '.iftp-btn-studio__connect',
						'title'  => __( 'Connect your AI', 'ifthenpay-pay-button' ),
						'text'   => __( 'Paste your own Claude, OpenAI or Gemini key once. After that you can describe buttons in plain words.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '.iftp-btn-studio__chat',
						'title'  => __( 'Just say what you want', 'ifthenpay-pay-button' ),
						'text'   => __( 'No forms to fill. Describe the button the way you would to a colleague.', 'ifthenpay-pay-button' ),
						'act'    => 'reset',
					),
					array(
						'target' => '[data-iftp-studio-form]',
						'title'  => __( 'Like this', 'ifthenpay-pay-button' ),
						'text'   => __( 'I\'ll type an example for you. It is not sent anywhere.', 'ifthenpay-pay-button' ),
						'act'    => 'prompt',
					),
					array(
						'target' => '.iftp-btn-studio__stage',
						'title'  => __( 'It builds, you watch', 'ifthenpay-pay-button' ),
						'text'   => __( 'The AI sets up the form and the look, and the pay page appears here, live, as payers will see it.', 'ifthenpay-pay-button' ),
						'act'    => 'build',
					),
					array(
						'target' => '.iftp-btn-studio__stage',
						'title'  => __( 'Here is your checkout', 'ifthenpay-pay-button' ),
						'text'   => __( 'The AI presents what it made: the form, the price and the pay button, as payers will see them.', 'ifthenpay-pay-button' ),
						'act'    => 'checkout',
					),
					array(
						'target' => '.iftp-btn-studio__log',
						'title'  => __( 'It tells you what it did', 'ifthenpay-pay-button' ),
						'text'   => __( 'Every change is listed, and anything to do with money is marked so you always check it.', 'ifthenpay-pay-button' ),
						'act'    => 'checkout',
					),
					array(
						'target' => '[data-iftp-studio-review]',
						'title'  => __( 'Review and save', 'ifthenpay-pay-button' ),
						'text'   => __( 'Happy with it? Review it in the editor and save. Not quite? Undo, or keep chatting to tweak it.', 'ifthenpay-pay-button' ),
						'act'    => 'review',
					),
					array(
						'target' => '',
						'title'  => __( 'You stay in charge', 'ifthenpay-pay-button' ),
						'text'   => __( 'Nothing is saved until you press save in the editor, and the new button starts switched off so you can check it first.', 'ifthenpay-pay-button' ),
						'act'    => 'reset',
					),
				),
			),
			'outro'  => array(
				'label' => __( 'All set', 'ifthenpay-pay-button' ),
				'cta'   => __( 'Done', 'ifthenpay-pay-button' ),
				'next'  => '',
				'steps' => array(
					array(
						'target' => '[data-iftp-tour="help"]',
						'title'  => __( 'Need this again?', 'ifthenpay-pay-button' ),
						'text'   => __( 'Click the question mark any time to replay the tour or start from a template.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '',
						'title'  => __( 'You are all set', 'ifthenpay-pay-button' ),
						'text'   => __( 'Make a button from scratch, with a template, or with the AI. Nothing goes live until you turn it on.', 'ifthenpay-pay-button' ),
					),
				),
			),
		);
	}
}
