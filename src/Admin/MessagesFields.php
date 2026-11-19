<?php
/**
 * The popup messages fields: a title and a message per outcome, with merge tags.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Checkout\MergeTags;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The words payers read in the popup after paying. On Default Design they're the site's, with where a paid payer
 * goes and whether the details show; in a button's editor they're that button's own, laid over the site's.
 *
 * @phpstan-import-type Confirmation from CheckoutSettings
 */
class MessagesFields {

	/**
	 * Prints the fields.
	 *
	 * @param array<string, mixed> $confirmation What the fields start with: the site's, or the site's under the button's own.
	 * @param bool                 $site         True on Default Design, false in a button's editor.
	 * @phpstan-param Confirmation $confirmation
	 */
	public static function render( array $confirmation, bool $site ): void {
		$titles = array(
			'paid'      => __( 'Paid', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Pending', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Failed', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'Cancelled', 'ifthenpay-pay-button' ),
		);
		$hints  = array(
			'paid'      => __( 'ifthenpay confirmed the payment.', 'ifthenpay-pay-button' ),
			'pending'   => __( 'Back from ifthenpay, but not confirmed yet: a Multibanco or Payshop reference waiting to be paid.', 'ifthenpay-pay-button' ),
			'failed'    => __( 'Declined, or the link expired.', 'ifthenpay-pay-button' ),
			'cancelled' => __( 'The payer cancelled on the ifthenpay page.', 'ifthenpay-pay-button' ),
		);
		?>
		<section class="iftp-btn-ecard" aria-labelledby="iftp-btn-confirm-title">
			<div class="iftp-btn-ecard__intro">
				<h2 id="iftp-btn-confirm-title" class="iftp-btn-ecard__title"><?php echo $site ? esc_html__( 'Messages', 'ifthenpay-pay-button' ) : esc_html__( 'Popup messages', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-ecard__description">
					<?php
					echo $site
						? esc_html__( 'Back from ifthenpay, the payer lands on the page they paid from and sees one of these. If it changes later (pending turns paid), the popup comes back even if they closed it. Click a tag to drop in that payment\'s detail. A button can set its own in its editor, under Configuration.', 'ifthenpay-pay-button' )
						: esc_html__( 'What this button\'s payers read in the popup after paying. Words you leave as they are follow Default Design › Messages. Click a tag to drop in that payment\'s detail.', 'ifthenpay-pay-button' );
					?>
				</p>
			</div>
			<div class="iftp-btn-ecard__body iftp-btn-outcomes">
				<?php // One outcome at a time; the preview follows the pick. ?>
				<span class="iftp-btn-seg iftp-btn-outcomes__pick" role="group" aria-label="<?php esc_attr_e( 'Outcome', 'ifthenpay-pay-button' ); ?>">
					<?php foreach ( $titles as $outcome => $outcome_title ) : ?>
						<button type="button" class="iftp-btn-seg__button iftp-btn-outcomes__option iftp-btn-outcome--<?php echo esc_attr( $outcome ); ?>" data-iftp-outcome-pick="<?php echo esc_attr( $outcome ); ?>" aria-pressed="<?php echo 'paid' === $outcome ? 'true' : 'false'; ?>"><?php echo esc_html( $outcome_title ); ?></button>
					<?php endforeach; ?>
				</span>
				<?php foreach ( $titles as $outcome => $outcome_title ) : ?>
					<fieldset class="iftp-btn-outcome iftp-btn-outcome--<?php echo esc_attr( $outcome ); ?>" data-iftp-tag-scope data-iftp-outcome="<?php echo esc_attr( $outcome ); ?>"<?php echo 'paid' === $outcome ? '' : ' hidden'; ?>>
						<legend class="iftp-btn-outcome__title"><?php echo esc_html( $outcome_title ); ?></legend>
						<p class="iftp-btn-field__help"><?php echo esc_html( $hints[ $outcome ] ); ?></p>

						<?php if ( $site && 'paid' === $outcome ) : ?>
							<div class="iftp-btn-segment iftp-btn-segment--compact" data-iftp-paid-segment>
								<?php
								foreach (
									array(
										'popup' => __( 'Popup message', 'ifthenpay-pay-button' ),
										'page'  => __( 'WordPress page', 'ifthenpay-pay-button' ),
										'url'   => __( 'Custom URL', 'ifthenpay-pay-button' ),
									) as $type => $type_label
								) :
									?>
									<label class="iftp-btn-segment__option">
										<input type="radio" name="paid_type" value="<?php echo esc_attr( $type ); ?>" <?php checked( $confirmation['paid_type'], $type ); ?> data-iftp-paid-type />
										<span class="iftp-btn-segment__title"><?php echo esc_html( $type_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<div class="iftp-btn-field" data-iftp-when-paid="page">
								<label class="iftp-btn-field__label" for="iftp-btn-paid-page"><?php esc_html_e( 'Page', 'ifthenpay-pay-button' ); ?></label>
								<?php
								wp_dropdown_pages(
									array(
										'name'             => 'paid_page',
										'id'               => 'iftp-btn-paid-page',
										'class'            => 'iftp-btn-field__control',
										'selected'         => (int) $confirmation['paid_page'],
										'show_option_none' => esc_html__( 'Choose a page…', 'ifthenpay-pay-button' ),
										'option_none_value' => '0',
									)
								);
								?>
							</div>
							<div class="iftp-btn-field" data-iftp-when-paid="url">
								<label class="iftp-btn-field__label" for="iftp-btn-paid-url"><?php esc_html_e( 'Address', 'ifthenpay-pay-button' ); ?></label>
								<input type="url" class="iftp-btn-field__control" id="iftp-btn-paid-url" name="paid_url" value="<?php echo esc_attr( $confirmation['paid_url'] ); ?>" placeholder="https://" />
							</div>
						<?php endif; ?>

						<div class="iftp-btn-outcome__words" <?php echo $site && 'paid' === $outcome ? 'data-iftp-when-paid="popup"' : ''; ?>>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-title-<?php echo esc_attr( $outcome ); ?>"><?php esc_html_e( 'Title', 'ifthenpay-pay-button' ); ?></label>
								<input type="text" class="iftp-btn-field__control" id="iftp-btn-title-<?php echo esc_attr( $outcome ); ?>" name="<?php echo esc_attr( sprintf( $site ? 'title[%s]' : 'config[confirmation][titles][%s]', $outcome ) ); ?>" maxlength="120" value="<?php echo esc_attr( $confirmation['titles'][ $outcome ] ); ?>" placeholder="<?php echo esc_attr( CheckoutSettings::default_titles()[ $outcome ] ); ?>" data-iftp-tag-target data-iftp-outcome-title="<?php echo esc_attr( $outcome ); ?>" />
							</div>

							<div class="iftp-btn-msg" data-iftp-msg data-iftp-outcome-message="<?php echo esc_attr( $outcome ); ?>">
								<div class="iftp-btn-msg__bar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'ifthenpay-pay-button' ); ?>">
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="bold" aria-label="<?php esc_attr_e( 'Bold', 'ifthenpay-pay-button' ); ?>"><strong>B</strong></button>
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="italic" aria-label="<?php esc_attr_e( 'Italic', 'ifthenpay-pay-button' ); ?>"><em>I</em></button>
									<button type="button" class="iftp-btn-msg__tool" data-iftp-msg-cmd="createLink" aria-label="<?php esc_attr_e( 'Link', 'ifthenpay-pay-button' ); ?>">
										<svg width="14" height="14" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6.5 9.5a3 3 0 0 0 4.2 0l2-2a3 3 0 0 0-4.2-4.2l-.7.7M9.5 6.5a3 3 0 0 0-4.2 0l-2 2a3 3 0 0 0 4.2 4.2l.7-.7" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
									</button>
									<span class="iftp-btn-msg__views" role="group" aria-label="<?php esc_attr_e( 'Editor view', 'ifthenpay-pay-button' ); ?>">
										<button type="button" class="iftp-btn-msg__view" data-iftp-msg-view="normal" aria-pressed="true"><?php esc_html_e( 'Normal', 'ifthenpay-pay-button' ); ?></button>
										<button type="button" class="iftp-btn-msg__view" data-iftp-msg-view="raw" aria-pressed="false"><?php esc_html_e( 'Raw', 'ifthenpay-pay-button' ); ?></button>
									</span>
								</div>
								<div class="iftp-btn-msg__normal" contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?php echo esc_attr( $outcome_title ); ?>" data-iftp-msg-normal data-iftp-tag-target hidden></div>
								<label class="screen-reader-text" for="iftp-btn-msg-<?php echo esc_attr( $outcome ); ?>"><?php echo esc_html( $outcome_title ); ?></label>
								<textarea class="iftp-btn-field__control iftp-btn-msg__raw" id="iftp-btn-msg-<?php echo esc_attr( $outcome ); ?>" name="<?php echo esc_attr( sprintf( $site ? 'message[%s]' : 'config[confirmation][messages][%s]', $outcome ) ); ?>" rows="3" data-iftp-msg-raw data-iftp-tag-target><?php echo esc_textarea( $confirmation['messages'][ $outcome ] ); ?></textarea>
							</div>

							<?php ButtonsPage::print_tags( MergeTags::available( true ) ); ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<?php if ( $site ) : ?>
				<label class="iftp-btn-check">
					<input type="checkbox" name="show_details" value="1" <?php checked( $confirmation['show_details'] ); ?> data-iftp-show-details />
					<?php esc_html_e( 'Show the payment details (amount, reference, method) under the message', 'ifthenpay-pay-button' ); ?>
				</label>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
