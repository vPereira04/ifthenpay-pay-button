<?php
/**
 * Build or change a button by chatting.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Admin\AiPanel;
use Ifthenpay\PayButton\Admin\CheckoutSettingsPage;
use Ifthenpay\PayButton\Admin\SchemeControl;
use Ifthenpay\PayButton\Ai\AiController;
use Ifthenpay\PayButton\Ai\AiSettings;
use Ifthenpay\PayButton\Ai\Chats;
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

	// The studio URL flag that picks the chat to open.
	public const CHAT = 'chat';

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
	 * The studio for a button, or for a new one; on a given chat, or else the one used last.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 * @param string      $chat   The chat's id, or '' for the latest.
	 */
	public static function url( ?Button $button, string $chat = '' ): string {
		return '' !== $chat ? self::chat_url( Studio::target( $button ), $chat ) : add_query_arg( array( self::VAR => Studio::target( $button ) ), ButtonsPage::list_url() );
	}

	/**
	 * The chat the studio opens: the one in the address, if it's this button's, else its latest, else a new one that exists
	 * once its first message is sent.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	private function open_chat( ?Button $button ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks which chat to show.
		$wanted = isset( $_GET[ self::CHAT ] ) ? sanitize_key( wp_unslash( $_GET[ self::CHAT ] ) ) : '';
		$target = Studio::target( $button );

		foreach ( Chats::all() as $entry ) {
			if ( $entry['target'] === $target && $entry['id'] === $wanted ) {
				return $wanted;
			}
		}

		$latest = Chats::latest_for( $target );

		return '' !== $latest ? $latest : ( Chats::valid( $wanted ) ? $wanted : Chats::make_id() );
	}

	/**
	 * A chat's studio.
	 *
	 * @param string $target A button id, or Studio::NEW_TARGET.
	 * @param string $chat   The chat's id.
	 */
	public static function chat_url( string $target, string $chat ): string {
		return add_query_arg(
			array(
				self::VAR  => $target,
				self::CHAT => $chat,
			),
			ButtonsPage::list_url()
		);
	}

	/**
	 * The editor, opened on a chat's latest version.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 * @param string      $chat   The chat's id.
	 */
	public static function draft_url( ?Button $button, string $chat ): string {
		if ( Studio::is_site( $button ) ) {
			return add_query_arg( self::DRAFT_VAR, $chat, CheckoutSettingsPage::url() );
		}

		return add_query_arg( self::DRAFT_VAR, $chat, ButtonsPage::edit_url( null !== $button ? $button->id : 0 ) );
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
			'slug'     => null !== $button && ! Studio::is_site( $button ) ? $button->slug : '',
			'chat'     => $this->open_chat( $button ),
			'keepDays' => AiSettings::get()['keep_days'],
			// The editor's address (the Default Design's, for the site); the script adds the chat that built the version.
			'draftUrl' => Studio::is_site( $button ) ? CheckoutSettingsPage::url() : ButtonsPage::edit_url( null !== $button ? $button->id : 0 ),
			'model'    => null !== $summary ? $summary['label'] . ' · ' . ( $summary['models'][ $summary['model'] ] ?? $summary['model'] ) : '',
			// The pay link, minus the slug, for the preview's address bar.
			'payBase'  => (string) preg_replace( '#^https?://|x/?$#', '', ( new PayLinks() )->url( new Button( 0, '', 'x', true, array() ) ) ),
		);

		if ( null !== $summary ) {
			$thread         = ( new Studio( $this->connection ) )->thread( $button, $studio['chat'] );
			$studio['view'] = Studio::view( $thread, AiController::preview_url( Studio::target( $button ), $studio['chat'] ) ) + array( 'chats' => AiController::chats( $studio['chat'] ) );
		}

		if ( Studio::is_site( $button ) ) {
			// The chat's own opening line and ideas, since it isn't about one button.
			$studio['welcome'] = __( 'What should change in the Default Design? I can give the checkout, the pay page and the button a whole new look: colours, corners, fonts, background and effects. It applies to every button that follows the default.', 'ifthenpay-pay-button' );
			$studio['ideas']   = array(
				__( 'Give it a fresh, modern look', 'ifthenpay-pay-button' ),
				__( 'Make the pay page dark and elegant', 'ifthenpay-pay-button' ),
				__( 'Rounder corners and a softer button', 'ifthenpay-pay-button' ),
				__( 'Use a green accent', 'ifthenpay-pay-button' ),
			);
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
		$site    = Studio::is_site( $button );
		$title   = $site
			? __( 'Edit the Default Design with AI', 'ifthenpay-pay-button' )
			: ( null !== $button
				/* translators: %s: the button's title */
				? sprintf( __( 'Edit “%s” with AI', 'ifthenpay-pay-button' ), $button->title )
				: __( 'Build a button with AI', 'ifthenpay-pay-button' ) );
		$back      = $site ? CheckoutSettingsPage::url() : ( null !== $button ? ButtonsPage::edit_url( $button->id ) : ButtonsPage::list_url() );
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
					<?php echo $site ? esc_html__( 'Back to Default Design', 'ifthenpay-pay-button' ) : ( null !== $button ? esc_html__( 'Back to the editor', 'ifthenpay-pay-button' ) : esc_html__( 'All buttons', 'ifthenpay-pay-button' ) ); ?>
				</a>
			</p>

			<?php if ( ! $connected ) : ?>
				<section class="iftp-btn-studio__connect" aria-labelledby="iftp-btn-ai-connect-title">
					<?php $this->panel->render_form( true ); ?>
				</section>
			<?php else : ?>
				<div class="iftp-btn-studio__grid">
					<section class="iftp-btn-studio__chat" aria-label="<?php esc_attr_e( 'Chat', 'ifthenpay-pay-button' ); ?>">
						<?php // The chats: a rail on wide screens, a panel over the chat on narrower ones. ?>
						<aside class="iftp-btn-studio__history" aria-label="<?php esc_attr_e( 'Your chats', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-history>
							<div class="iftp-btn-studio__history-head">
								<h2 class="iftp-btn-studio__history-title"><?php esc_html_e( 'Chats', 'ifthenpay-pay-button' ); ?></h2>
								<button type="button" class="iftp-btn-studio__history-collapse" aria-expanded="true" aria-label="<?php esc_attr_e( 'Collapse the list of chats', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-collapse>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><path d="M9.5 4.5v15"/></svg>
								</button>
								<button type="button" class="iftp-btn-studio__history-close" aria-label="<?php esc_attr_e( 'Close the list of chats', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-history-toggle>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
								</button>
							</div>
							<button type="button" class="iftp-btn-studio__newchat" data-iftp-studio-new>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>
								<span class="iftp-btn-studio__newchat-text"><?php esc_html_e( 'New chat', 'ifthenpay-pay-button' ); ?></span>
							</button>
							<ul class="iftp-btn-studio__chats" data-iftp-studio-chats></ul>
							<p class="iftp-btn-studio__history-fine" data-iftp-studio-history-fine></p>
						</aside>

						<div class="iftp-btn-studio__convo">
							<div class="iftp-btn-studio__bar">
								<button type="button" class="iftp-btn-studio__chats-toggle" aria-expanded="false" data-iftp-studio-history-toggle>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
									<?php esc_html_e( 'Chats', 'ifthenpay-pay-button' ); ?>
								</button>
								<span class="iftp-btn-studio__bar-title" data-iftp-studio-chat-title></span>
							</div>
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
						</div>
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
								<?php // Light by default; the pick is kept in the browser and shared with every other preview. ?>
								<?php SchemeControl::render( 'iftp-btn-studio__scheme' ); ?>
								<?php // The same never-pays preview, in a tab of its own to try at full size. ?>
								<a class="iftp-btn-studio__open" href="#" target="_blank" rel="noopener" data-iftp-studio-open hidden>
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6M20 4l-9 9M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/></svg>
									<?php esc_html_e( 'Full page', 'ifthenpay-pay-button' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'ifthenpay-pay-button' ); ?></span>
								</a>
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
								<iframe class="iftp-btn-studio__iframe" title="<?php esc_attr_e( 'Demo page with the pay button', 'ifthenpay-pay-button' ); ?>" data-iftp-studio-iframe></iframe>
							</div>
							<div class="iftp-btn-studio__empty" data-iftp-studio-empty>
								<span class="iftp-btn-studio__empty-mark" aria-hidden="true"><?php echo AiPanel::sparkle(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG. ?></span>
								<p class="iftp-btn-studio__empty-title"><?php esc_html_e( 'Your button appears here', 'ifthenpay-pay-button' ); ?></p>
								<p class="iftp-btn-studio__empty-text"><?php esc_html_e( 'On a demo page, as visitors will see it. Click the button to try the form: everything works except paying.', 'ifthenpay-pay-button' ); ?></p>
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
								<a class="iftp-btn-btn iftp-btn-btn--primary" href="<?php echo esc_url( self::draft_url( $button, '' ) ); ?>" data-iftp-studio-draft><?php esc_html_e( 'Review and save', 'ifthenpay-pay-button' ); ?></a>
							</div>
							<p class="iftp-btn-studio__note"><?php echo null !== $button ? esc_html__( 'Nothing changes on your live button until you save it in the editor.', 'ifthenpay-pay-button' ) : esc_html__( 'Nothing is saved until you create it in the editor. It starts switched off, so you can check it first.', 'ifthenpay-pay-button' ); ?></p>
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

				<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-studio-delete-title" aria-describedby="iftp-btn-studio-delete-text" data-iftp-studio-delete-dialog>
					<h2 id="iftp-btn-studio-delete-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'Delete this chat?', 'ifthenpay-pay-button' ); ?></h2>
					<p id="iftp-btn-studio-delete-text" class="iftp-btn-dialog__text"><span data-iftp-studio-delete-name></span> <?php esc_html_e( 'is removed for good. Buttons you already saved are not touched.', 'ifthenpay-pay-button' ); ?></p>
					<div class="iftp-btn-dialog__actions">
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close autofocus><?php esc_html_e( 'Keep it', 'ifthenpay-pay-button' ); ?></button>
						<button type="button" class="iftp-btn-btn iftp-btn-btn--danger" data-iftp-studio-delete-confirm><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></button>
					</div>
				</dialog>
			<?php endif; ?>
			<?php ButtonsPage::print_shared_ui(); ?>
		</div>
		<?php
	}
}
