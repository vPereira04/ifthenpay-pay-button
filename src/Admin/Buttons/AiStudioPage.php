<?php
/**
 * Build or change a button by chatting.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\AiPanel;
use Ifthenpay\PayButton\Ai\AiController;
use Ifthenpay\PayButton\Ai\Connection;
use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Bridge\PayLinks;
use Ifthenpay\PayButton\Buttons\Button;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The chat on the left, and on the right the real pay page for the latest version, in a frame that never takes
 * payments. "Review and save" opens the editor with that version filled in, still unsaved.
 */
class AiStudioPage {

	public const VAR = 'studio';

	// The editor URL flag that opens the chat's latest version.
	public const DRAFT_VAR = 'ai_draft';

	/**
	 * The AI connection.
	 *
	 * @var Connection
	 */
	private Connection $connection;

	/**
	 * The connect form and the assets.
	 *
	 * @var AiPanel
	 */
	private AiPanel $panel;

	/**
	 * Sets up the page.
	 *
	 * @param Connection|null $connection The AI connection.
	 */
	public function __construct( ?Connection $connection = null ) {
		$this->connection = $connection ?? new Connection();
		$this->panel      = new AiPanel( $this->connection );
	}

	/**
	 * The studio for a button, or for a new one.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public static function url( ?Button $button ): string {
		return add_query_arg( array( self::VAR => Studio::target( $button ) ), ButtonsPage::list_url() );
	}

	/**
	 * The editor, opened on the chat's latest version.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public static function draft_url( ?Button $button ): string {
		return add_query_arg( self::DRAFT_VAR, '1', ButtonsPage::edit_url( null !== $button ? $button->id : 0 ) );
	}

	/**
	 * Enqueues ai.js with the conversation to show.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public function enqueue( ?Button $button ): void {
		$summary = $this->connection->summary();
		$studio  = array(
			'target'   => Studio::target( $button ),
			'isNew'    => null === $button,
			'title'    => null !== $button ? $button->title : '',
			'slug'     => null !== $button ? $button->slug : '',
			'draftUrl' => self::draft_url( $button ),
			'model'    => null !== $summary ? $summary['label'] . ' · ' . ( $summary['models'][ $summary['model'] ] ?? $summary['model'] ) : '',
			// The pay link, minus the slug, for the preview's address bar.
			'payBase'  => (string) preg_replace( '#^https?://|x/?$#', '', ( new PayLinks() )->url( new Button( 0, '', 'x', true, array() ) ) ),
		);

		if ( null !== $summary ) {
			$studio['view'] = Studio::view( ( new Studio( $this->connection ) )->thread( $button ), AiController::preview_url( Studio::target( $button ) ) );
		}

		$this->panel->enqueue( $studio );
	}

	/**
	 * Renders the studio.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public function render( ?Button $button ): void {
		$summary = $this->connection->summary();
		$title   = null !== $button
			/* translators: %s: the button's title */
			? sprintf( __( 'Edit “%s” with AI', 'ifthenpay-pay-button' ), $button->title )
			: __( 'Build a button with AI', 'ifthenpay-pay-button' );
		$back      = null !== $button ? ButtonsPage::edit_url( $button->id ) : ButtonsPage::list_url();
		$actions   = '';
		$connected = null !== $summary;

		if ( $connected ) {
			$actions = '<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-studio-reset hidden>' . esc_html__( 'Start over', 'ifthenpay-pay-button' ) . '</button>';
		}
		?>
		<div class="wrap iftp-btn-buttons iftp-btn-studio<?php echo $connected ? '' : ' iftp-btn-studio--connect'; ?>" data-iftp-studio>
			<?php ButtonsPage::print_header( $title, $actions ); ?>

			<p class="iftp-btn-editor__back">
				<a href="<?php echo esc_url( $back ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3.5 5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php echo null !== $button ? esc_html__( 'Back to the editor', 'ifthenpay-pay-button' ) : esc_html__( 'All buttons', 'ifthenpay-pay-button' ); ?>
				</a>
			</p>

			<?php if ( ! $connected ) : ?>
				<section class="iftp-btn-studio__connect" aria-labelledby="iftp-btn-ai-connect-title">
					<?php $this->panel->render_form( true ); ?>
				</section>
			<?php else : ?>
				<div class="iftp-btn-studio__grid">
					<section class="iftp-btn-studio__chat" aria-label="<?php esc_attr_e( 'Chat', 'ifthenpay-pay-button' ); ?>">
						<div class="iftp-btn-studio__log" role="log" aria-live="polite" aria-relevant="additions" data-iftp-studio-log></div>
						<div class="iftp-btn-studio__ideas" role="group" aria-label="<?php esc_attr_e( 'Ideas to start from', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-ideas></div>
						<form class="iftp-btn-studio__composer" data-iftp-studio-form>
							<label class="screen-reader-text" for="iftp-btn-studio-input"><?php esc_html_e( 'Your message', 'ifthenpay-pay-button' ); ?></label>
							<textarea class="iftp-btn-studio__input" id="iftp-btn-studio-input" rows="1" maxlength="<?php echo esc_attr( (string) Studio::MAX_MESSAGE ); ?>" placeholder="<?php echo esc_attr( null !== $button ? __( 'What should change?', 'ifthenpay-pay-button' ) : __( 'Describe your button…', 'ifthenpay-pay-button' ) ); ?>" data-iftp-studio-input></textarea>
							<button type="submit" class="iftp-btn-studio__send" data-iftp-studio-send>
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 19V5M5.5 11.5 12 5l6.5 6.5"/></svg>
								<span class="screen-reader-text"><?php esc_html_e( 'Send', 'ifthenpay-pay-button' ); ?></span>
							</button>
						</form>
						<p class="iftp-btn-studio__fine">
							<span data-iftp-studio-model><?php echo esc_html( $summary['label'] . ' · ' . ( $summary['models'][ $summary['model'] ] ?? $summary['model'] ) ); ?></span>
							<?php esc_html_e( 'uses your own key. Enter sends, Shift+Enter starts a new line.', 'ifthenpay-pay-button' ); ?>
						</p>
					</section>

					<section class="iftp-btn-studio__stage" aria-label="<?php esc_attr_e( 'Preview', 'ifthenpay-pay-button' ); ?>">
						<div class="iftp-btn-studio__toolbar">
							<div class="iftp-btn-studio__views">
								<div class="iftp-btn-studio__devices" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'ifthenpay-pay-button' ); ?>">
									<button type="button" class="iftp-btn-studio__device" aria-pressed="true" data-iftp-studio-device="desktop">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
										<?php esc_html_e( 'Desktop', 'ifthenpay-pay-button' ); ?>
									</button>
									<button type="button" class="iftp-btn-studio__device" aria-pressed="false" data-iftp-studio-device="phone">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2"/></svg>
										<?php esc_html_e( 'Phone', 'ifthenpay-pay-button' ); ?>
									</button>
								</div>
								<?php // The same never-pays preview, in a tab of its own to try at full size. ?>
								<a class="iftp-btn-studio__open" href="#" target="_blank" rel="noopener" data-iftp-studio-open hidden>
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6M20 4l-9 9M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/></svg>
									<?php esc_html_e( 'Full page', 'ifthenpay-pay-button' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'ifthenpay-pay-button' ); ?></span>
								</a>
							</div>
							<div class="iftp-btn-studio__button" aria-label="<?php esc_attr_e( 'The button', 'ifthenpay-pay-button' ); ?>" role="img">
								<span class="iftp-btn-pay" data-iftp-studio-button></span>
							</div>
						</div>

						<div class="iftp-btn-studio__frame" data-device="desktop" data-iftp-studio-frame>
							<div class="iftp-btn-studio__shell" data-iftp-studio-shell hidden>
								<div class="iftp-btn-studio__chrome" aria-hidden="true">
									<span class="iftp-btn-studio__lights"><i></i><i></i><i></i></span>
									<span class="iftp-btn-studio__url">
										<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/></svg>
										<span data-iftp-studio-url></span>
									</span>
								</div>
								<iframe class="iftp-btn-studio__iframe" title="<?php esc_attr_e( 'Pay page preview', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-iframe></iframe>
							</div>
							<div class="iftp-btn-studio__empty" data-iftp-studio-empty>
								<span class="iftp-btn-studio__empty-mark" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
								<p class="iftp-btn-studio__empty-title"><?php esc_html_e( 'Your pay page appears here', 'ifthenpay-pay-button' ); ?></p>
								<p class="iftp-btn-studio__empty-text"><?php esc_html_e( 'Live, as payers will see it. Try the form in it: everything works except paying.', 'ifthenpay-pay-button' ); ?></p>
							</div>
							<?php // While the AI works: a card drawing itself, and the line the chat shows. ?>
							<div class="iftp-btn-studio__building" data-iftp-studio-building hidden>
								<span class="iftp-btn-studio__orb" aria-hidden="true"></span>
								<div class="iftp-btn-studio__ghost" aria-hidden="true">
									<span class="iftp-btn-studio__ghost-line iftp-btn-studio__ghost-line--small"></span>
									<span class="iftp-btn-studio__ghost-line iftp-btn-studio__ghost-line--title"></span>
									<span class="iftp-btn-studio__ghost-line iftp-btn-studio__ghost-line--text"></span>
									<span class="iftp-btn-studio__ghost-field"></span>
									<span class="iftp-btn-studio__ghost-field iftp-btn-studio__ghost-field--half"></span>
									<span class="iftp-btn-studio__ghost-field"></span>
									<span class="iftp-btn-studio__ghost-button"></span>
								</div>
								<p class="iftp-btn-studio__building-text" aria-hidden="true" data-iftp-studio-building-text></p>
								<span class="iftp-btn-studio__progress" aria-hidden="true" data-iftp-studio-progress></span>
							</div>
						</div>

						<div class="iftp-btn-studio__review" data-iftp-studio-review hidden>
							<div class="iftp-btn-studio__prices" data-iftp-studio-prices></div>
							<div class="iftp-btn-studio__review-actions">
								<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-studio-undo>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
									<?php esc_html_e( 'Undo', 'ifthenpay-pay-button' ); ?>
								</button>
								<a class="iftp-btn-btn iftp-btn-btn--primary" href="<?php echo esc_url( self::draft_url( $button ) ); ?>" data-iftp-studio-draft><?php esc_html_e( 'Review and save', 'ifthenpay-pay-button' ); ?></a>
							</div>
							<p class="iftp-btn-studio__note"><?php echo null !== $button ? esc_html__( 'Nothing changes on your live button until you save it in the editor.', 'ifthenpay-pay-button' ) : esc_html__( 'Nothing is saved until you create it in the editor. It goes live as soon as you do.', 'ifthenpay-pay-button' ); ?></p>
						</div>
					</section>
				</div>

				<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-studio-reset-title" aria-describedby="iftp-btn-studio-reset-text" data-iftp-studio-reset-dialog>
					<h2 id="iftp-btn-studio-reset-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Start over?', 'ifthenpay-pay-button' ); ?></h2>
					<p id="iftp-btn-studio-reset-text" class="iftp-btn-dialog__text"><?php echo null !== $button ? esc_html__( 'The chat and its changes are cleared, and you start again from the saved button.', 'ifthenpay-pay-button' ) : esc_html__( 'The chat and the button it built are cleared.', 'ifthenpay-pay-button' ); ?></p>
					<div class="iftp-btn-dialog__actions">
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close autofocus><?php esc_html_e( 'Keep chatting', 'ifthenpay-pay-button' ); ?></button>
						<button type="button" class="iftp-btn-btn iftp-btn-btn--danger" data-iftp-studio-reset-confirm><?php esc_html_e( 'Start over', 'ifthenpay-pay-button' ); ?></button>
					</div>
				</dialog>
			<?php endif; ?>
			<?php ButtonsPage::print_shared_ui(); ?>
		</div>
		<?php
	}
}
