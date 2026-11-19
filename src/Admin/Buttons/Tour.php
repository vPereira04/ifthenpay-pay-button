<?php
/**
 * The guided tour of the Buttons screens.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\LookControls;
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

		wp_enqueue_script( self::SCRIPT, Plugin::asset_url( $js ), array( Plugin::BUTTONS_SCRIPT_LAST ), Plugin::asset_version( $js ), true );
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
					/* translators: 1: current step, 2: number of steps */
					'count' => __( '%1$s of %2$s', 'ifthenpay-pay-button' ),
					'hint'  => array(
						'click' => __( 'Your turn: click the highlighted part, or skip ahead', 'ifthenpay-pay-button' ),
						'fill'  => __( 'Your turn: type in the glowing field, or skip ahead', 'ifthenpay-pay-button' ),
						'send'  => __( 'Your turn: type your idea and press send, or skip ahead', 'ifthenpay-pay-button' ),
					),
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
							</div>
						</div>
						<div class="iftp-btn-form__actions">
							<div class="iftp-btn-form__buttons">
								<span class="iftp-btn-form__submit"><?php echo esc_html( CheckoutTexts::get( 'next' ) ); ?></span>
							</div>
						</div>
						<footer class="iftp-btn-form__footer">
							<ul class="iftp-btn-form__methods">
								<?php ( new LookControls( 'config[look]' ) )->print_preview_logos(); ?>
							</ul>
							<span class="iftp-btn-form__secure"><span><?php echo esc_html( CheckoutTexts::get( 'secure' ) ); ?></span> <img src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="62" height="16" /></span>
						</footer>
					</div>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * The chapters: where "Next" on their last step goes, and their steps. A step with no target is a centred card;
	 * one whose target isn't on screen is left out; "tab" opens an editor tab first; "act" names a scripted demo.
	 * "wait" makes the step interactive: 'click' goes on when "on" is clicked, 'fill' when the "on" field has a
	 * value, 'send' when the studio message is sent. The lit target is fully usable; "on" glows. Without "wait" the
	 * card's own Next button goes on.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function chapters(): array {
		$edit = add_query_arg( self::VAR, '1', ButtonsPage::edit_url( 0 ) );
		$ai   = add_query_arg( self::VAR, '1', AiStudioPage::url( null ) );
		$out  = ButtonsPage::list_url( array( self::VAR => 'outro' ) );

		return array(
			'list'   => array(
				'cta'   => __( 'Show me', 'ifthenpay-pay-button' ),
				'next'  => $edit,
				'steps' => array(
					array(
						'target' => '',
						'title'  => __( 'Make your first button', 'ifthenpay-pay-button' ),
						'text'   => __( 'A pay button takes a payment on a page, in an email or from a QR code. Let\'s make one, it takes a minute.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-tour="new"]',
						'wait'   => 'click',
						'on'     => '[data-iftp-tour="new"]',
						'title'  => __( 'Start here', 'ifthenpay-pay-button' ),
						'text'   => __( 'New button opens the editor.', 'ifthenpay-pay-button' ),
					),
				),
			),
			'editor' => array(
				'cta'   => __( 'Now the AI', 'ifthenpay-pay-button' ),
				'next'  => $ai,
				'steps' => array(
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-basics"]',
						'tab'    => 'basics',
						'wait'   => 'fill',
						'on'     => '[name="title"]',
						'title'  => __( 'Name it', 'ifthenpay-pay-button' ),
						'text'   => __( 'Give the button a title, like Consultation. Nothing you do in this tour is saved.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-amount"]',
						'tab'    => 'basics',
						'title'  => __( 'Set the price', 'ifthenpay-pay-button' ),
						'text'   => __( 'Type a fixed amount, or let the payer choose it. Try it, then press Next.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-tab="design"]',
						'wait'   => 'click',
						'on'     => '[data-iftp-tab="design"]',
						'title'  => __( 'Make it yours', 'ifthenpay-pay-button' ),
						'text'   => __( 'Open the Design tab.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[aria-labelledby="iftp-btn-ec-design"]',
						'tab'    => 'design',
						'view'   => 'button',
						'title'  => __( 'Pick a look', 'ifthenpay-pay-button' ),
						'text'   => __( 'Choose Custom to see the ready-made styles and pick one, or build your own. The checkout has its own design too, just below. Press Next when you are done.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '.iftp-btn-editor__save',
						'tab'    => 'basics',
						'title'  => __( 'Create it', 'ifthenpay-pay-button' ),
						'text'   => __( 'This is where you press Create button. Nothing was saved here, so there is nothing to clean up.', 'ifthenpay-pay-button' ),
					),
				),
			),
			'ai'     => array(
				'cta'   => __( 'Finish', 'ifthenpay-pay-button' ),
				'next'  => $out,
				'steps' => array(
					array(
						'target' => '[data-iftp-studio-form]',
						'act'    => 'prompt',
						'on'     => '[data-iftp-studio-input]',
						'wait'   => 'send',
						'title'  => __( 'Or just ask the AI', 'ifthenpay-pay-button' ),
						'text'   => __( 'Describe the button in plain words, then press send. In this tour nothing is really sent.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '.iftp-btn-studio__stage',
						'act'    => 'build',
						'title'  => __( 'It builds, you watch', 'ifthenpay-pay-button' ),
						'text'   => __( 'The form, the look and the pay page appear here, with a list of what changed. Anything about money is marked.', 'ifthenpay-pay-button' ),
					),
					array(
						'target' => '[data-iftp-studio-review]',
						'act'    => 'review',
						'title'  => __( 'You stay in charge', 'ifthenpay-pay-button' ),
						'text'   => __( 'Review it in the editor and save. The new button starts switched off, so nothing goes live by surprise.', 'ifthenpay-pay-button' ),
					),
				),
			),
			'outro'  => array(
				'cta'   => __( 'Done', 'ifthenpay-pay-button' ),
				'next'  => '',
				'steps' => array(
					array(
						'target' => '[data-iftp-tour="help"]',
						'title'  => __( 'Need this again?', 'ifthenpay-pay-button' ),
						'text'   => __( 'Click the question mark any time to replay the tour or start from a template.', 'ifthenpay-pay-button' ),
					),
				),
			),
		);
	}
}
