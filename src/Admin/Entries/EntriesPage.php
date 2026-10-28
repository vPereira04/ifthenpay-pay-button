<?php
/**
 * Entries admin screen: the list, and the single entry page behind each row's View.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Payments" screen, built on the Forminator entries playbook: masthead, summary cards, status tabs, search,
 * filters, columns, export, bulk actions, cross-page selection and pagination. View opens the single entry page.
 *
 * Styled by assets/css/admin.css, the unified ifthenpay look, through the `iftp-btn-` BEM classes below.
 *
 * @phpstan-import-type Transaction from TransactionStore
 * @phpstan-import-type View from ViewState
 */
class EntriesPage {

	/**
	 * Button titles by ID, once loaded.
	 *
	 * @var array<int, string>|null
	 */
	private ?array $button_titles = null;


	public const PAGE_SLUG     = 'iftp-btn-payments';
	public const NONCE_ACTION  = 'iftp_btn_entries';
	public const VIEW_NONCE    = 'iftp_btn_view_entry';
	public const ENTRY_VAR     = 'entry';
	public const EXPORT_ACTION = 'iftp_btn_export';
	public const EXPORT_CSV    = 'csv';
	public const EXPORT_JSON   = 'json';

	public const HIDDEN_COLS_META = 'iftp_btn_entries_hidden_cols';
	public const COL_ORDER_META   = 'iftp_btn_entries_col_order';

	// Default order. Reference and the checkbox column always stay.
	public const HIDEABLE_COLUMNS = array( 'customer', 'button', 'method', 'amount', 'status', 'created' );

	// entries.js splits selections into chunks this size; EntriesController refuses anything past its own cap.
	public const BULK_CHUNK_SIZE  = 250;
	public const EMAIL_CHUNK_SIZE = 10;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * The list's view state.
	 *
	 * @var ViewState
	 */
	private ViewState $view;

	/**
	 * Shared cell rendering.
	 *
	 * @var EntryPresenter
	 */
	private EntryPresenter $presenter;

	/**
	 * The payment email editor behind the masthead's settings button.
	 *
	 * @var PaymentEmailSettings
	 */
	private PaymentEmailSettings $email_settings;

	/**
	 * The Performance section and the Revenue by method card.
	 *
	 * @var InsightsView|null
	 */
	private ?InsightsView $insights_view = null;

	/**
	 * The current admin's column order, read once per request.
	 *
	 * @var array<int, string>|null
	 */
	private ?array $column_order = null;

	/**
	 * Sets up the page.
	 *
	 * @param TransactionStore|null   $transactions Transactions storage.
	 * @param SettingsRepository|null $settings     Settings storage.
	 */
	public function __construct( ?TransactionStore $transactions = null, ?SettingsRepository $settings = null ) {
		$this->transactions   = $transactions ?? new TransactionStore();
		$this->settings       = $settings ?? new SettingsRepository();
		$this->view           = new ViewState( $this->transactions );
		$this->presenter      = new EntryPresenter( $this->settings );
		$this->email_settings = new PaymentEmailSettings( $this->settings );
	}

	/**
	 * Registers the asset hook and the email editor's endpoints. The menu entry comes from Menu.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		$this->email_settings->register();
	}

	/**
	 * The Performance section's view, built once per request.
	 */
	private function insights_view(): InsightsView {
		return $this->insights_view ??= new InsightsView( new Insights( $this->transactions ), $this->presenter );
	}

	/**
	 * The transactions storage, for EntriesController.
	 */
	public function transactions(): TransactionStore {
		return $this->transactions;
	}

	/**
	 * The list's view state, for EntriesController.
	 */
	public function view_state(): ViewState {
		return $this->view;
	}

	/**
	 * Shared cell rendering, for EntriesController and the single entry page.
	 */
	public function presenter(): EntryPresenter {
		return $this->presenter;
	}

	/**
	 * Adds the "Entries" submenu under the given parent.
	 *
	 * @param string $parent_slug The parent menu slug.
	 */
	public function add_page( string $parent_slug ): void {
		$hook = add_submenu_page(
			$parent_slug,
			__( 'Payments', 'ifthenpay-pay-button' ),
			__( 'Payments', 'ifthenpay-pay-button' ),
			Plugin::admin_cap(),
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		if ( is_string( $hook ) ) {
			add_action( 'load-' . $hook, array( $this->view, 'remember_per_page' ) );
		}
	}

	/**
	 * The single entry page for a ref. A view nonce rides along, like Contact Form 7's entry links.
	 *
	 * @param string $ref The transaction reference.
	 */
	public static function entry_url( string $ref ): string {
		return add_query_arg(
			array(
				'page'          => self::PAGE_SLUG,
				self::ENTRY_VAR => rawurlencode( $ref ),
				'_wpnonce'      => wp_create_nonce( self::VIEW_NONCE ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The list searched for one ref. No nonce, unlike entry_url(), so it can go in an email.
	 *
	 * @param string $ref The transaction reference.
	 */
	public static function search_url( string $ref ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				's'    => rawurlencode( $ref ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The bare list URL. entries-restore.js turns it back into the admin's last view.
	 */
	public static function list_url(): string {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), admin_url( 'admin.php' ) );
	}

	/**
	 * Whether the current user may use this screen: admin cap plus a connected Backoffice Key.
	 */
	public function can_access(): bool {
		return current_user_can( Plugin::admin_cap() ) && $this->settings->is_connected();
	}

	/**
	 * Whether this request asks for the single entry page.
	 */
	private function wants_single_entry(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the view; requested_entry() checks the nonce.
		return isset( $_GET[ self::ENTRY_VAR ] );
	}

	/**
	 * The row the single entry page asks for, or null when the ref, the nonce or the row isn't right.
	 *
	 * @return Transaction|null
	 */
	private function requested_entry(): ?array {
		if ( ! $this->wants_single_entry() || ! isset( $_GET['_wpnonce'] ) || false === wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::VIEW_NONCE ) ) {
			return null;
		}

		$ref = sanitize_text_field( wp_unslash( $_GET[ self::ENTRY_VAR ] ?? '' ) );

		return '' !== $ref ? $this->transactions->get( $ref ) : null;
	}

	/**
	 * Enqueues this screen's scripts and the shared stylesheet, only on this screen.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		$entries_js     = 'assets/js/entries.js';
		$restore_js     = 'assets/js/entries-restore.js';
		$email_settings = $this->email_settings->enqueue();
		$deps           = array( Plugin::METHOD_LOGOS_HANDLE, Plugin::UI_SCRIPT_HANDLE, Plugin::CHARTS_SCRIPT_HANDLE );

		if ( false !== $email_settings['editor'] ) {
			$deps[] = 'code-editor';
		}

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_script( 'iftp-btn-entries', IFTP_BTN_URL . $entries_js, $deps, Plugin::asset_version( $entries_js ), true );

		// Head, not footer, and the list only — it may redirect to the saved view before the default one paints.
		if ( ! $this->wants_single_entry() ) {
			wp_enqueue_script( 'iftp-btn-entries-restore', IFTP_BTN_URL . $restore_js, array(), Plugin::asset_version( $restore_js ), false );

			wp_localize_script(
				'iftp-btn-entries-restore',
				'iftpBtnEntriesStore',
				array(
					// Per site and per user, so a shared browser doesn't mix admins' views.
					'key'      => 'iftp_btn_entries:' . get_current_blog_id() . ':' . get_current_user_id(),
					'pageSlug' => self::PAGE_SLUG,
					// Per-page lives in user meta, so the saved view leaves it out.
					'view'     => array_diff_key( array_map( 'strval', ViewState::url_args( $this->view->current() ) ), array( 'per_page' => true ) ),
				)
			);
		}

		global $wp_locale;

		wp_localize_script(
			'iftp-btn-entries',
			'iftpBtnEntries',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::NONCE_ACTION ),
				'exportUrl'          => admin_url( 'admin-post.php' ),
				'exportAction'       => self::EXPORT_ACTION,
				'exportNonce'        => wp_create_nonce( self::EXPORT_ACTION ),
				'listUrl'            => self::list_url(),
				'currentFilters'     => $this->view->current(),
				'statusLabels'       => $this->presenter->status_labels(),
				'defaultColumnOrder' => self::HIDEABLE_COLUMNS,
				'bulkChunkSize'      => self::BULK_CHUNK_SIZE,
				'emailChunkSize'     => self::EMAIL_CHUNK_SIZE,
				'emailSettings'      => $email_settings,
				// Same separator as number_format_i18n(), decoded, since some locales store it as "&nbsp;" and JS writes textContent.
				'numberFormat'       => array(
					'thousands' => html_entity_decode( $wp_locale instanceof \WP_Locale ? (string) $wp_locale->number_format['thousands_sep'] : ',', ENT_QUOTES, 'UTF-8' ),
					'decimal'   => html_entity_decode( $wp_locale instanceof \WP_Locale ? (string) $wp_locale->number_format['decimal_point'] : '.', ENT_QUOTES, 'UTF-8' ),
				),
				'i18n'               => self::script_strings(),
			)
		);
	}

	/**
	 * Every string entries.js shows, translated here so the JS never hardcodes English.
	 *
	 * @return array<string, string>
	 */
	private static function script_strings(): array {
		return array(
			'confirmDeleteTitle'    => __( 'Delete selected entries?', 'ifthenpay-pay-button' ),
			'confirmDeleteOneTitle' => __( 'Delete this entry?', 'ifthenpay-pay-button' ),
			'confirmDeleteMessage'  => __( 'This removes the payment record for good. A customer who still has the link can no longer pay through it here.', 'ifthenpay-pay-button' ),
			/* translators: %s: number of paid entries in the selection */
			'deleteKeepsPaid'       => __( 'Paid entries are payment history, so the %s paid ones in your selection are kept.', 'ifthenpay-pay-button' ),
			'confirmDeleteButton'   => __( 'Delete', 'ifthenpay-pay-button' ),
			'confirmStatusTitle'    => __( 'Change status?', 'ifthenpay-pay-button' ),
			/* translators: %s: target status label, e.g. "Paid" */
			'confirmStatusMessage'  => __( 'Mark the selected entries as "%s".', 'ifthenpay-pay-button' ),
			/* translators: %s: target status label, e.g. "Paid" */
			'confirmStatusOne'      => __( 'Mark this entry as "%s".', 'ifthenpay-pay-button' ),
			'confirmStatusButton'   => __( 'Change status', 'ifthenpay-pay-button' ),
			'deleteError'           => __( 'Could not delete the selected entries. Please try again.', 'ifthenpay-pay-button' ),
			'updateStatusError'     => __( 'Could not update the selected entries. Please try again.', 'ifthenpay-pay-button' ),
			'deleteToastSuccess'    => __( 'Selected entries deleted.', 'ifthenpay-pay-button' ),
			'statusToastSuccess'    => __( 'Selected entries updated.', 'ifthenpay-pay-button' ),
			/* translators: 1: entries done so far, 2: entries selected */
			'bulkDeleting'          => __( 'Deleting… %1$s of %2$s', 'ifthenpay-pay-button' ),
			/* translators: 1: entries done so far, 2: entries selected */
			'bulkUpdating'          => __( 'Updating… %1$s of %2$s', 'ifthenpay-pay-button' ),
			/* translators: 1: entries done before the error, 2: entries selected */
			'bulkPartialError'      => __( 'Stopped after %1$s of %2$s entries. The rest are still selected — try again.', 'ifthenpay-pay-button' ),
			'bulkLeaveWarning'      => __( 'A bulk action is still running. Leave anyway?', 'ifthenpay-pay-button' ),
			/* translators: 1: number of selected entries, 2: total number of entries */
			'selectedOfTotal'       => __( '%1$s selected out of %2$s', 'ifthenpay-pay-button' ),
			/* translators: %s: number of selected entries */
			'bulkSelectedCount'     => __( '%s selected', 'ifthenpay-pay-button' ),
			'newEntryCreating'      => __( 'Creating…', 'ifthenpay-pay-button' ),
			'newEntryToastSuccess'  => __( 'Payment created.', 'ifthenpay-pay-button' ),
			'newEntryError'         => __( 'Could not create the payment. Please try again.', 'ifthenpay-pay-button' ),
			'copyLinkSuccess'       => __( 'Payment link copied.', 'ifthenpay-pay-button' ),
			'copyLinkError'         => __( 'Could not copy the link.', 'ifthenpay-pay-button' ),
			'copyRefSuccess'        => __( 'Reference copied.', 'ifthenpay-pay-button' ),
			'copyRefError'          => __( 'Could not copy the reference.', 'ifthenpay-pay-button' ),
			'confirmResendTitle'    => __( 'Send payment email?', 'ifthenpay-pay-button' ),
			/* translators: %s: customer email address */
			'confirmResendMessage'  => __( '%s will get an email with the payment button.', 'ifthenpay-pay-button' ),
			'confirmResendButton'   => __( 'Send email', 'ifthenpay-pay-button' ),
			'resendSending'         => __( 'Sending…', 'ifthenpay-pay-button' ),
			'resendError'           => __( 'Could not send the payment email. Please try again.', 'ifthenpay-pay-button' ),
			'bulkEmailTitle'        => __( 'Send payment emails?', 'ifthenpay-pay-button' ),
			/* translators: %s: number of selected entries */
			'bulkEmailMessage'      => __( 'The customers of the %s selected pending payments will each get an email with their payment button. Entries without a customer email are skipped.', 'ifthenpay-pay-button' ),
			'bulkEmailButton'       => __( 'Send emails', 'ifthenpay-pay-button' ),
			/* translators: 1: entries done so far, 2: entries selected */
			'bulkEmailing'          => __( 'Sending emails… %1$s of %2$s', 'ifthenpay-pay-button' ),
			/* translators: %s: number of emails sent */
			'bulkEmailSent'         => __( 'Payment emails sent: %s.', 'ifthenpay-pay-button' ),
			/* translators: %s: number of entries skipped */
			'bulkEmailSkipped'      => __( 'Skipped (no longer pending, or no customer email): %s.', 'ifthenpay-pay-button' ),
			/* translators: %s: number of emails that failed */
			'bulkEmailFailed'       => __( 'Failed: %s. They are still selected, so you can try again.', 'ifthenpay-pay-button' ),
			'columnsSaveError'      => __( 'Could not save your column choice.', 'ifthenpay-pay-button' ),
			'exportNothingSelected' => __( 'Select at least one entry to export.', 'ifthenpay-pay-button' ),
			/* translators: 1: column name, 2: new position, 3: number of movable columns */
			'columnMoved'           => __( '%1$s moved to position %2$s of %3$s.', 'ifthenpay-pay-button' ),
			'columnsReset'          => __( 'Columns reset to the default order, all shown.', 'ifthenpay-pay-button' ),
			'emailSaving'           => __( 'Saving…', 'ifthenpay-pay-button' ),
			'emailSaveError'        => __( 'Could not save the payment email. Please try again.', 'ifthenpay-pay-button' ),
			'emailPreviewError'     => __( 'Could not refresh the preview.', 'ifthenpay-pay-button' ),
			'emailResetDone'        => __( 'Default template restored. Save to keep it.', 'ifthenpay-pay-button' ),
			'emailMediaTitle'       => __( 'Choose an image for the email', 'ifthenpay-pay-button' ),
			'emailMediaButton'      => __( 'Insert image', 'ifthenpay-pay-button' ),
			'genericError'          => __( 'Something went wrong. Please try again.', 'ifthenpay-pay-button' ),
			'newEntryCreateLink'    => __( 'Create payment link', 'ifthenpay-pay-button' ),
			'newEntryRecord'        => __( 'Record payment', 'ifthenpay-pay-button' ),
			'newEntryAmountError'   => __( 'Enter an amount greater than zero.', 'ifthenpay-pay-button' ),
			'newEntryEmailError'    => __( 'Enter a valid email, or leave it blank.', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * Renders the screen: the single entry page when asked for and valid, otherwise the list.
	 */
	public function render(): void {
		if ( ! $this->can_access() ) {
			return;
		}

		if ( $this->wants_single_entry() ) {
			$record = $this->requested_entry();

			if ( null !== $record ) {
				( new SingleEntryView( $this->transactions, $this->presenter ) )->render( $record );
				$this->render_shared_ui();

				return;
			}

			// A stale or tampered link falls back to the list, with a word on why.
			wp_admin_notice( esc_html__( 'That entry link is no longer valid, or the entry was deleted.', 'ifthenpay-pay-button' ), array( 'type' => 'warning' ) );
		}

		$this->render_list();
	}

	/**
	 * The confirm dialog and the toast, which both pages share.
	 */
	public function render_shared_ui(): void {
		?>
		<dialog class="iftp-btn-dialog" role="alertdialog" aria-labelledby="iftp-btn-confirm-title" aria-describedby="iftp-btn-confirm-message" data-iftp-confirm>
			<h2 id="iftp-btn-confirm-title" class="iftp-btn-dialog__title" data-iftp-confirm-title><?php esc_html_e( 'Are you sure?', 'ifthenpay-pay-button' ); ?></h2>
			<p id="iftp-btn-confirm-message" class="iftp-btn-dialog__text" data-iftp-confirm-message><?php esc_html_e( 'This action cannot be undone.', 'ifthenpay-pay-button' ); ?></p>
			<div class="iftp-btn-dialog__actions">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-confirm-cancel><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-confirm-ok><?php esc_html_e( 'Confirm', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</dialog>

		<div class="iftp-btn-toast" data-iftp-toast role="status" aria-live="polite" hidden>
			<span class="iftp-btn-toast__message" data-iftp-toast-message></span>
		</div>
		<?php
	}

	/**
	 * Renders the list.
	 */
	private function render_list(): void {
		$filters     = $this->view->current();
		$view_args   = ViewState::url_args( $filters );
		$hidden_cols = $this->hidden_columns();
		$col_order   = $this->column_order();
		$chips       = $this->active_filter_chips( $filters );
		$shared      = ViewState::store_filters( $filters );
		$per_page    = $filters['per_page'];
		$is_custom   = ! in_array( $per_page, ViewState::PER_PAGE_OPTIONS, true );

		$result = $this->transactions->query(
			$shared + array(
				'status'   => $filters['status'],
				'orderby'  => $filters['orderby'],
				'order'    => $filters['order'],
				'per_page' => $per_page,
				'paged'    => $filters['paged'],
			)
		);
		?>
		<div id="iftp-btn-entries" class="wrap iftp-btn-entries iftp-btn-entries--no-js" data-iftp-entries data-iftp-hidden-cols="<?php echo esc_attr( implode( ' ', $hidden_cols ) ); ?>" data-iftp-col-order="<?php echo esc_attr( implode( ' ', $col_order ) ); ?>">
			<header class="iftp-btn-masthead">
				<div class="iftp-btn-masthead__brand">
					<img class="iftp-btn-masthead__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/logo-color.svg' ); ?>" alt="ifthenpay" width="94" height="24" />
					<span class="iftp-btn-masthead__divider" aria-hidden="true"></span>
					<div class="iftp-btn-masthead__text">
						<h1 id="iftp-btn-entries-title" class="iftp-btn-masthead__title"><?php esc_html_e( 'Payments', 'ifthenpay-pay-button' ); ?></h1>
					</div>
				</div>
				<div class="iftp-btn-masthead__actions">
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-new-entry-open aria-haspopup="dialog">
						<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 3v10M3 8h10" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
						<?php esc_html_e( 'New payment', 'ifthenpay-pay-button' ); ?>
					</button>
					<?php $this->email_settings->render_trigger(); ?>
				</div>
			</header>

			<hr class="wp-header-end" />

			<div class="iftp-btn-blocks">
			<?php $this->render_overview( $shared, $view_args ); ?>

			<div class="iftp-btn-card">
				<div class="iftp-btn-card__header">
					<div class="iftp-btn-toolbar">
						<nav class="iftp-btn-tabs" aria-label="<?php esc_attr_e( 'Filter by status', 'ifthenpay-pay-button' ); ?>">
							<ul class="iftp-btn-tabs__list">
								<?php // No counts here: the summary cards above already show them, and are filters too. ?>
								<?php foreach ( $this->presenter->status_labels( true ) as $slug => $label ) : ?>
									<?php $is_current = $slug === $filters['status']; ?>
									<li class="iftp-btn-tabs__item">
										<a class="iftp-btn-tabs__link<?php echo $is_current ? ' iftp-btn-tabs__link--current' : ''; ?>" href="
										<?php
										echo esc_url(
											$this->view->url(
												array(
													'status' => $slug,
													'paged'  => null,
												)
											)
										);
										?>
																	"<?php echo $is_current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						</nav>

						<?php $this->render_bulk_bar(); ?>

						<div class="iftp-btn-toolbar__tools">
							<div class="iftp-btn-search">
								<svg class="iftp-btn-search__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="7" cy="7" r="4.75" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m10.5 10.5 3 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
								<label class="screen-reader-text" for="iftp-btn-search"><?php esc_html_e( 'Search entries', 'ifthenpay-pay-button' ); ?></label>
								<input type="search" class="iftp-btn-search__input" id="iftp-btn-search" name="s" form="iftp-btn-filter-form" value="<?php echo esc_attr( $filters['s'] ); ?>" placeholder="<?php esc_attr_e( 'Search entries…', 'ifthenpay-pay-button' ); ?>" title="<?php esc_attr_e( 'Search by reference, customer or method', 'ifthenpay-pay-button' ); ?>" />
								<button type="submit" form="iftp-btn-filter-form" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-search__submit"><?php esc_html_e( 'Search', 'ifthenpay-pay-button' ); ?></button>
							</div>

							<?php // The tool buttons need JS, so they start hidden and entries.js reveals them. ?>
							<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-tool" data-iftp-filters-toggle aria-expanded="true" aria-controls="iftp-btn-filter-panel" hidden>
								<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2.5 3.25h11L9.25 8.5v4.25l-2.5-1.25v-3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
								<span class="iftp-btn-tool__label"><?php esc_html_e( 'Filters', 'ifthenpay-pay-button' ); ?></span>
								<?php if ( array() !== $chips ) : ?>
									<span class="iftp-btn-tool__count" aria-hidden="true"><?php echo esc_html( number_format_i18n( count( $chips ) ) ); ?></span>
									<span class="screen-reader-text">
										<?php
										/* translators: %s: number of active filters */
										echo esc_html( sprintf( _n( '(%s active)', '(%s active)', count( $chips ), 'ifthenpay-pay-button' ), number_format_i18n( count( $chips ) ) ) );
										?>
									</span>
								<?php endif; ?>
							</button>

							<div class="iftp-btn-popover-anchor">
								<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-tool" data-iftp-columns-trigger aria-expanded="false" aria-controls="iftp-btn-columns-menu" hidden>
									<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="2" y="2.75" width="12" height="10.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M6 2.75v10.5M10 2.75v10.5" stroke="currentColor" stroke-width="1.5"/></svg>
									<span class="iftp-btn-tool__label"><?php esc_html_e( 'Columns', 'ifthenpay-pay-button' ); ?></span>
								</button>
								<?php $this->render_columns_menu( $col_order, $hidden_cols ); ?>
								<p class="screen-reader-text" data-iftp-col-status role="status"></p>
							</div>

							<div class="iftp-btn-popover-anchor">
								<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-tool" data-iftp-export-trigger aria-expanded="false" aria-controls="iftp-btn-export-menu" hidden>
									<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M8 2.5v7.5M4.75 6.75 8 10l3.25-3.25M3 13.25h10" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<span class="iftp-btn-tool__label"><?php esc_html_e( 'Export', 'ifthenpay-pay-button' ); ?></span>
								</button>
								<?php $this->render_export_menu( $filters ); ?>
							</div>
						</div>
					</div>

					<?php $this->render_filter_panel( $filters, $view_args, $chips ); ?>
				</div>

				<div class="iftp-btn-card__stage">
					<div class="iftp-btn-table-wrap" role="region" aria-labelledby="iftp-btn-entries-title" tabindex="0">
						<table class="iftp-btn-table">
							<thead>
								<tr>
									<th class="iftp-btn-table__check" scope="col">
										<input type="checkbox" data-iftp-select-all aria-label="<?php esc_attr_e( 'Select all on this page', 'ifthenpay-pay-button' ); ?>" />
									</th>
									<th class="iftp-btn-table__head" data-col="ref" scope="col"><?php esc_html_e( 'Reference', 'ifthenpay-pay-button' ); ?></th>
									<?php
									foreach ( $col_order as $slug ) {
										$this->render_head_cell( $slug, $filters['orderby'], $filters['order'], $hidden_cols );
									}
									?>
								</tr>
							</thead>
							<tbody data-iftp-entries-body>
								<?php $this->print_rows( $result['items'] ); ?>
							</tbody>
						</table>
					</div>

					<?php // Shown only while busy. The colour mark, so it reads even before the styles exist. ?>
					<div class="iftp-btn-loader" data-iftp-loader aria-hidden="true" hidden>
						<span class="iftp-btn-mark iftp-btn-mark--busy">
							<img class="iftp-btn-mark__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/icon-normal.svg' ); ?>" alt="" width="28" height="28" />
						</span>
					</div>
				</div>

				<div class="iftp-btn-card__footer">
					<div class="iftp-btn-footer-status" aria-live="polite">
						<span class="iftp-btn-footer-status__range" data-iftp-total-count data-iftp-total="<?php echo esc_attr( (string) $result['total'] ); ?>"><?php echo esc_html( $this->range_label( $result['paged'], $per_page, $result['total'] ) ); ?></span>
						<span class="iftp-btn-footer-status__selection" data-iftp-selection-summary hidden>
							<span data-iftp-selection-text></span>
							<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-selection-cancel><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
						</span>
					</div>

					<div class="iftp-btn-card__footer-nav">
						<form class="iftp-btn-per-page" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" data-iftp-per-page-form>
							<?php $this->print_hidden_inputs( $view_args, array( 'per_page', 'paged' ) ); ?>
							<label class="iftp-btn-per-page__label" for="iftp-btn-per-page"><?php esc_html_e( 'Entries per page', 'ifthenpay-pay-button' ); ?></label>
							<select class="iftp-btn-per-page__select" id="iftp-btn-per-page" name="per_page" data-iftp-per-page>
								<?php foreach ( ViewState::PER_PAGE_OPTIONS as $option ) : ?>
									<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $per_page, $option ); ?>><?php echo esc_html( number_format_i18n( $option ) ); ?></option>
								<?php endforeach; ?>
								<option value="custom" <?php selected( $is_custom ); ?>><?php esc_html_e( 'Custom…', 'ifthenpay-pay-button' ); ?></option>
							</select>
							<?php // Visible for no-JS; entries.js hides it until "Custom…" is picked. ?>
							<span class="iftp-btn-per-page__custom" data-iftp-per-page-custom>
								<label class="screen-reader-text" for="iftp-btn-per-page-custom"><?php esc_html_e( 'Custom entries per page', 'ifthenpay-pay-button' ); ?></label>
								<input type="number" class="iftp-btn-per-page__input" id="iftp-btn-per-page-custom" name="per_page_custom" min="<?php echo esc_attr( (string) ViewState::PER_PAGE_MIN ); ?>" max="<?php echo esc_attr( (string) ViewState::PER_PAGE_MAX ); ?>" step="1" inputmode="numeric" value="<?php echo esc_attr( (string) $per_page ); ?>" />
								<span class="iftp-btn-per-page__hint"><?php echo esc_html( number_format_i18n( ViewState::PER_PAGE_MIN ) . '–' . number_format_i18n( ViewState::PER_PAGE_MAX ) ); ?></span>
							</span>
							<button type="submit" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-per-page__submit" data-iftp-per-page-submit><?php esc_html_e( 'Go', 'ifthenpay-pay-button' ); ?></button>
						</form>

						<div class="iftp-btn-pagination-wrap" data-iftp-pagination><?php $this->render_pagination( $result['paged'], $result['pages'], $view_args ); ?></div>
					</div>
				</div>
			</div>
			</div>

			<?php $this->render_new_entry_dialog(); ?>
			<?php $this->email_settings->render_dialog(); ?>
			<?php $this->render_shared_ui(); ?>

			<button type="button" class="iftp-btn-scroll-top" data-iftp-scroll-top aria-label="<?php esc_attr_e( 'Scroll to top', 'ifthenpay-pay-button' ); ?>" hidden>
				<svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path d="M4.5 11.25 9 6.75l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
		</div>
		<?php
	}

	/**
	 * What sits above the table: Performance, Revenue by method and the status cards.
	 *
	 * @param array<string, mixed> $shared    The view's store filters, for the status cards.
	 * @param array<string, mixed> $view_args url_args() of the view.
	 */
	private function render_overview( array $shared, array $view_args ): void {
		$view = $this->insights_view();

		$view->render();
		$view->render_methods_card();
		?>
		<section class="iftp-btn-summary" aria-labelledby="iftp-btn-summary-title">
			<h2 id="iftp-btn-summary-title" class="screen-reader-text"><?php esc_html_e( 'Totals by status', 'ifthenpay-pay-button' ); ?></h2>
			<div class="iftp-btn-summary__body" data-iftp-summary>
				<?php $this->print_summary( $this->transactions->status_summary( $shared ), $view_args ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * The bulk bar that takes the tabs' place while something is selected.
	 */
	private function render_bulk_bar(): void {
		?>
		<div class="iftp-btn-bulk" data-iftp-bulk-actions hidden>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--icon iftp-btn-bulk__clear" data-iftp-selection-cancel aria-label="<?php esc_attr_e( 'Clear selection', 'ifthenpay-pay-button' ); ?>">
				<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
			</button>
			<span class="iftp-btn-bulk__count" data-iftp-bulk-count></span>
			<label class="screen-reader-text" for="iftp-btn-bulk-select"><?php esc_html_e( 'Bulk actions', 'ifthenpay-pay-button' ); ?></label>
			<select class="iftp-btn-bulk__select" id="iftp-btn-bulk-select" data-iftp-bulk-select>
				<option value=""><?php esc_html_e( 'Bulk actions', 'ifthenpay-pay-button' ); ?></option>
				<?php foreach ( $this->presenter->status_labels() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>">
						<?php
						/* translators: %s: target status label, e.g. "Paid" */
						echo esc_html( sprintf( __( 'Mark as %s', 'ifthenpay-pay-button' ), $label ) );
						?>
					</option>
				<?php endforeach; ?>
				<?php // Shown by entries.js only while every selected entry is pending. ?>
				<option value="email" data-iftp-bulk-email hidden disabled><?php esc_html_e( 'Send payment email', 'ifthenpay-pay-button' ); ?></option>
				<option value="export-csv"><?php esc_html_e( 'Export selected as CSV', 'ifthenpay-pay-button' ); ?></option>
				<option value="export-json"><?php esc_html_e( 'Export selected as JSON', 'ifthenpay-pay-button' ); ?></option>
				<?php // Disabled by entries.js while every selected entry is paid. ?>
				<option value="delete" data-iftp-bulk-delete><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></option>
			</select>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-bulk-apply><?php esc_html_e( 'Apply', 'ifthenpay-pay-button' ); ?></button>
		</div>
		<?php
	}

	/**
	 * The filter panel, a plain GET form, plus the active-filter chips under it.
	 *
	 * @param array<string, mixed>                                       $filters   The current view.
	 * @param array<string, mixed>                                       $view_args url_args() of it.
	 * @param array<int, array{key: string, value: string, url: string}> $chips     From active_filter_chips().
	 */
	private function render_filter_panel( array $filters, array $view_args, array $chips ): void {
		?>
		<?php // Open for no-JS; entries.js closes it unless the admin left it open this session. ?>
		<div id="iftp-btn-filter-panel" class="iftp-btn-filters" data-iftp-filter-panel>
			<form id="iftp-btn-filter-form" class="iftp-btn-filters__form" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<?php $this->print_hidden_inputs( $view_args, array( 's', 'button', 'pay_method', 'amount_min', 'amount_max', 'date_from', 'date_to', 'paged' ) ); ?>

				<div class="iftp-btn-filters__fields">
					<div class="iftp-btn-filters__field iftp-btn-filters__field--button">
						<label class="iftp-btn-filters__label" for="iftp-btn-button-filter"><?php esc_html_e( 'Button', 'ifthenpay-pay-button' ); ?></label>
						<select class="iftp-btn-filters__control" id="iftp-btn-button-filter" name="button" data-iftp-filter-first>
							<option value=""><?php esc_html_e( 'All buttons', 'ifthenpay-pay-button' ); ?></option>
							<?php foreach ( $this->button_titles() as $button_id => $button_title ) : ?>
								<option value="<?php echo esc_attr( (string) $button_id ); ?>" <?php selected( (int) $filters['button'], $button_id ); ?>><?php echo esc_html( $button_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="iftp-btn-filters__field iftp-btn-filters__field--method">
						<label class="iftp-btn-filters__label" for="iftp-btn-method-filter"><?php esc_html_e( 'Method', 'ifthenpay-pay-button' ); ?></label>
						<select class="iftp-btn-filters__control" id="iftp-btn-method-filter" name="pay_method">
							<option value=""><?php esc_html_e( 'All methods', 'ifthenpay-pay-button' ); ?></option>
							<?php foreach ( $this->transactions->distinct_methods() as $method ) : ?>
								<option value="<?php echo esc_attr( $method ); ?>" <?php selected( $filters['pay_method'], $method ); ?>><?php echo esc_html( $method ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="iftp-btn-filters__field iftp-btn-filters__field--amount" role="group" aria-labelledby="iftp-btn-filter-amount-label">
						<span id="iftp-btn-filter-amount-label" class="iftp-btn-filters__label"><?php esc_html_e( 'Amount (€)', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-filters__range">
							<label class="screen-reader-text" for="iftp-btn-amount-min"><?php esc_html_e( 'Minimum amount', 'ifthenpay-pay-button' ); ?></label>
							<input type="number" class="iftp-btn-filters__control" id="iftp-btn-amount-min" name="amount_min" step="0.01" min="0" inputmode="decimal" value="<?php echo esc_attr( (string) $filters['amount_min'] ); ?>" placeholder="<?php esc_attr_e( 'Min', 'ifthenpay-pay-button' ); ?>" />
							<span class="iftp-btn-filters__range-sep" aria-hidden="true">&ndash;</span>
							<label class="screen-reader-text" for="iftp-btn-amount-max"><?php esc_html_e( 'Maximum amount', 'ifthenpay-pay-button' ); ?></label>
							<input type="number" class="iftp-btn-filters__control" id="iftp-btn-amount-max" name="amount_max" step="0.01" min="0" inputmode="decimal" value="<?php echo esc_attr( (string) $filters['amount_max'] ); ?>" placeholder="<?php esc_attr_e( 'Max', 'ifthenpay-pay-button' ); ?>" />
						</span>
					</div>

					<div class="iftp-btn-filters__field iftp-btn-filters__field--created" role="group" aria-labelledby="iftp-btn-filter-created-label">
						<span id="iftp-btn-filter-created-label" class="iftp-btn-filters__label"><?php esc_html_e( 'Created', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-filters__range">
							<label class="screen-reader-text" for="iftp-btn-date-from"><?php esc_html_e( 'From date', 'ifthenpay-pay-button' ); ?></label>
							<input type="date" class="iftp-btn-filters__control" id="iftp-btn-date-from" name="date_from" value="<?php echo esc_attr( (string) $filters['date_from'] ); ?>" data-iftp-date-from />
							<span class="iftp-btn-filters__range-sep" aria-hidden="true">&ndash;</span>
							<label class="screen-reader-text" for="iftp-btn-date-to"><?php esc_html_e( 'To date', 'ifthenpay-pay-button' ); ?></label>
							<input type="date" class="iftp-btn-filters__control" id="iftp-btn-date-to" name="date_to" value="<?php echo esc_attr( (string) $filters['date_to'] ); ?>" data-iftp-date-to />
						</span>
						<ul class="iftp-btn-presets" aria-label="<?php esc_attr_e( 'Quick date ranges', 'ifthenpay-pay-button' ); ?>">
							<?php foreach ( ViewState::date_presets() as $preset ) : ?>
								<?php $is_current = $preset['from'] === $filters['date_from'] && $preset['to'] === $filters['date_to']; ?>
								<li class="iftp-btn-presets__item">
									<a
										class="iftp-btn-presets__link<?php echo $is_current ? ' iftp-btn-presets__link--current' : ''; ?>"
										href="
										<?php
										echo esc_url(
											$this->view->url(
												array(
													'date_from' => $preset['from'],
													'date_to' => $preset['to'],
													'paged'   => null,
												)
											)
										);
										?>
												"
										data-iftp-date-preset
										data-iftp-preset-from="<?php echo esc_attr( $preset['from'] ); ?>"
										data-iftp-preset-to="<?php echo esc_attr( $preset['to'] ); ?>"
										<?php echo $is_current ? ' aria-current="true"' : ''; ?>
									><?php echo esc_html( $preset['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>efaweffewaaefawfefawfwfwffewafawef
					</div>
				</div>

				<div class="iftp-btn-filters__actions">
					<?php if ( array() !== $chips ) : ?>
						<a class="iftp-btn-btn iftp-btn-btn--link" href="<?php echo esc_url( $this->clear_filters_url() ); ?>"><?php esc_html_e( 'Clear filters', 'ifthenpay-pay-button' ); ?></a>
					<?php endif; ?>
					<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary"><?php esc_html_e( 'Apply filters', 'ifthenpay-pay-button' ); ?></button>
				</div>
			</form>
		</div>

		<?php if ( array() !== $chips ) : ?>
			<div class="iftp-btn-chips">
				<ul class="iftp-btn-chips__list" aria-label="<?php esc_attr_e( 'Active filters', 'ifthenpay-pay-button' ); ?>">
					<?php foreach ( $chips as $chip ) : ?>
						<li class="iftp-btn-chips__item">
							<a class="iftp-btn-chip" href="<?php echo esc_url( $chip['url'] ); ?>">
								<span class="screen-reader-text"><?php esc_html_e( 'Remove filter:', 'ifthenpay-pay-button' ); ?></span>
								<span class="iftp-btn-chip__key"><?php echo esc_html( $chip['key'] ); ?></span>
								<span class="iftp-btn-chip__value" title="<?php echo esc_attr( $chip['value'] ); ?>"><?php echo esc_html( $chip['value'] ); ?></span>
								<svg class="iftp-btn-chip__remove" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M3 3l6 6M9 3l-6 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="iftp-btn-btn iftp-btn-btn--link iftp-btn-chips__clear" href="<?php echo esc_url( $this->clear_filters_url() ); ?>"><?php esc_html_e( 'Clear filters', 'ifthenpay-pay-button' ); ?></a>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * The "New payment" dialog. Two steps in one dialog: the form, then what came out of it (the link to copy,
	 * whether it was emailed, a way into the entry). Pending goes through ifthenpay; any other status is recorded by hand.
	 */
	private function render_new_entry_dialog(): void {
		$links_ready = ( new PaymentLinks( $this->settings, null, $this->transactions ) )->is_ready();
		$choices     = array(
			TransactionStore::STATUS_PENDING => array(
				'icon' => 'M8 1.75v3M8 11.25v3M14.25 8h-3M4.75 8h-3',
				'text' => $links_ready ? __( 'Creates an ifthenpay link to pay', 'ifthenpay-pay-button' ) : __( 'Link waits until methods are enabled', 'ifthenpay-pay-button' ),
			),
			TransactionStore::STATUS_PAID    => array(
				'icon' => 'm3.5 8.5 3 3 6-7',
				'text' => __( 'Already paid outside ifthenpay', 'ifthenpay-pay-button' ),
			),
			TransactionStore::STATUS_FAILED  => array(
				'icon' => 'M4.5 4.5l7 7M11.5 4.5l-7 7',
				'text' => __( 'Record an attempt that failed', 'ifthenpay-pay-button' ),
			),
			TransactionStore::STATUS_EXPIRED => array(
				'icon' => 'M8 4.5V8l2.5 1.5M14 8A6 6 0 1 1 2 8a6 6 0 0 1 12 0Z',
				'text' => __( 'Record one that ran out', 'ifthenpay-pay-button' ),
			),
		);
		$labels      = $this->presenter->status_labels();
		?>
		<dialog class="iftp-btn-dialog iftp-btn-dialog--payment" aria-labelledby="iftp-btn-new-entry-title" aria-describedby="iftp-btn-new-entry-desc" data-iftp-new-entry-dialog>
			<form class="iftp-btn-np" data-iftp-new-entry-form novalidate>
				<header class="iftp-btn-np__header">
					<span class="iftp-btn-np__badge" aria-hidden="true">
						<svg width="20" height="20" viewBox="0 0 16 16" focusable="false"><rect x="1.75" y="3.25" width="12.5" height="9.5" rx="2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M1.75 6.25h12.5M4.5 10h2.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
					</span>
					<div class="iftp-btn-np__heading">
						<h2 id="iftp-btn-new-entry-title" class="iftp-btn-dialog__title"><?php esc_html_e( 'New payment', 'ifthenpay-pay-button' ); ?></h2>
						<p id="iftp-btn-new-entry-desc" class="iftp-btn-np__desc"><?php esc_html_e( 'Ask a customer to pay through ifthenpay, or record a payment taken elsewhere.', 'ifthenpay-pay-button' ); ?></p>
					</div>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--icon iftp-btn-np__close" data-iftp-dialog-close aria-label="<?php esc_attr_e( 'Close', 'ifthenpay-pay-button' ); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4.5 4.5l7 7M11.5 4.5l-7 7" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
					</button>
				</header>

				<p class="iftp-btn-field__error iftp-btn-np__error" data-iftp-new-entry-error role="alert" hidden></p>

				<div class="iftp-btn-np__amount">
					<label class="iftp-btn-np__amount-label" for="iftp-btn-ne-amount"><?php esc_html_e( 'Amount', 'ifthenpay-pay-button' ); ?> <span class="iftp-btn-field__required" aria-hidden="true">*</span></label>
					<span class="iftp-btn-np__amount-field">
						<input type="text" class="iftp-btn-np__amount-input" id="iftp-btn-ne-amount" name="amount" inputmode="decimal" autocomplete="off" placeholder="0,00" required aria-describedby="iftp-btn-ne-amount-hint" data-iftp-np-amount />
						<span class="iftp-btn-np__currency" aria-hidden="true">€</span>
					</span>
					<span class="screen-reader-text" id="iftp-btn-ne-amount-hint"><?php esc_html_e( 'In euros', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-np__chips" role="group" aria-label="<?php esc_attr_e( 'Quick amounts', 'ifthenpay-pay-button' ); ?>">
						<?php foreach ( array( 5, 10, 25, 50, 100 ) as $quick ) : ?>
							<button type="button" class="iftp-btn-np__chip" data-iftp-np-quick="<?php echo esc_attr( (string) $quick ); ?>"><?php echo esc_html( number_format_i18n( $quick ) . ' €' ); ?></button>
						<?php endforeach; ?>
					</span>
				</div>

				<fieldset class="iftp-btn-np__section">
					<legend class="iftp-btn-np__legend"><?php esc_html_e( 'Customer', 'ifthenpay-pay-button' ); ?></legend>
					<div class="iftp-btn-np__customer">
						<span class="iftp-btn-avatar iftp-btn-avatar--tone-0 iftp-btn-np__avatar" style="--iftp-avatar-size: 48px" aria-hidden="true" data-iftp-np-avatar>
							<svg class="iftp-btn-avatar__glyph" width="18" height="18" viewBox="0 0 16 16" focusable="false"><circle cx="8" cy="5.5" r="2.75" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M2.75 13.5c.75-2.5 2.75-3.75 5.25-3.75s4.5 1.25 5.25 3.75" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
							<span class="iftp-btn-avatar__initials" data-iftp-np-initials hidden></span>
						</span>
						<div class="iftp-btn-np__customer-fields">
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-ne-name"><?php esc_html_e( 'Name', 'ifthenpay-pay-button' ); ?></label>
								<input type="text" class="iftp-btn-field__control" id="iftp-btn-ne-name" name="customer_name" autocomplete="off" maxlength="191" placeholder="<?php esc_attr_e( 'Ana Silva', 'ifthenpay-pay-button' ); ?>" data-iftp-np-name />
							</div>
							<div class="iftp-btn-field">
								<label class="iftp-btn-field__label" for="iftp-btn-ne-email"><?php esc_html_e( 'Email', 'ifthenpay-pay-button' ); ?></label>
								<input type="email" class="iftp-btn-field__control" id="iftp-btn-ne-email" name="customer_email" autocomplete="off" maxlength="191" placeholder="<?php esc_attr_e( 'ana@example.com', 'ifthenpay-pay-button' ); ?>" data-iftp-np-email />
							</div>
						</div>
					</div>
				</fieldset>

				<fieldset class="iftp-btn-np__section">
					<legend class="iftp-btn-np__legend"><?php esc_html_e( 'Status', 'ifthenpay-pay-button' ); ?></legend>
					<div class="iftp-btn-choices">
						<?php foreach ( $choices as $status => $choice ) : ?>
							<label class="iftp-btn-choice iftp-btn-choice--<?php echo esc_attr( $status ); ?>">
								<input type="radio" class="iftp-btn-choice__input" name="status" value="<?php echo esc_attr( $status ); ?>" <?php checked( TransactionStore::STATUS_PENDING, $status ); ?> data-iftp-np-status />
								<span class="iftp-btn-choice__icon" aria-hidden="true">
									<svg width="16" height="16" viewBox="0 0 16 16" focusable="false"><path d="<?php echo esc_attr( $choice['icon'] ); ?>" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</span>
								<span class="iftp-btn-choice__title"><?php echo esc_html( $labels[ $status ] ?? $status ); ?></span>
								<span class="iftp-btn-choice__text"><?php echo esc_html( $choice['text'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<label class="iftp-btn-np__toggle" data-iftp-np-email-row>
					<input type="checkbox" class="iftp-btn-switch" name="send_email" value="1" data-iftp-np-send<?php echo $links_ready ? '' : ' disabled'; ?> />
					<span class="iftp-btn-np__toggle-text">
						<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'Email the payment link now', 'ifthenpay-pay-button' ); ?></span>
						<span class="iftp-btn-np__toggle-hint" data-iftp-np-send-hint><?php esc_html_e( 'Add the customer\'s email to send it.', 'ifthenpay-pay-button' ); ?></span>
					</span>
				</label>

				<footer class="iftp-btn-np__footer">
					<span class="iftp-btn-np__summary" data-iftp-np-summary aria-live="polite"></span>
					<span class="iftp-btn-dialog__actions-end">
						<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-dialog-close><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></button>
						<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-new-entry-submit><?php esc_html_e( 'Create payment link', 'ifthenpay-pay-button' ); ?></button>
					</span>
				</footer>
			</form>

			<div class="iftp-btn-np-done" data-iftp-np-done tabindex="-1" hidden>
				<span class="iftp-btn-np-done__mark" aria-hidden="true">
					<svg width="28" height="28" viewBox="0 0 16 16" focusable="false"><path d="m3.5 8.5 3 3 6-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</span>
				<h2 class="iftp-btn-np-done__title" data-iftp-np-done-title></h2>
				<p class="iftp-btn-np-done__amount" data-iftp-np-done-amount></p>
				<p class="iftp-btn-np-done__text" data-iftp-np-done-text></p>
				<div class="iftp-btn-np-done__link" data-iftp-np-link-row hidden>
					<label class="screen-reader-text" for="iftp-btn-np-link"><?php esc_html_e( 'Payment link', 'ifthenpay-pay-button' ); ?></label>
					<input type="text" class="iftp-btn-field__control iftp-btn-np-done__link-input" id="iftp-btn-np-link" readonly data-iftp-np-link />
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-np-copy><?php esc_html_e( 'Copy link', 'ifthenpay-pay-button' ); ?></button>
				</div>
				<div class="iftp-btn-np-done__actions">
					<a class="iftp-btn-btn iftp-btn-btn--secondary" href="#" data-iftp-np-open><?php esc_html_e( 'Open entry', 'ifthenpay-pay-button' ); ?></a>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-np-again><?php esc_html_e( 'Create another', 'ifthenpay-pay-button' ); ?></button>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-dialog-close><?php esc_html_e( 'Done', 'ifthenpay-pay-button' ); ?></button>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * The Columns popover: show/hide and reorder the hideable columns.
	 *
	 * @param array<int, string> $col_order   From column_order().
	 * @param array<int, string> $hidden_cols From hidden_columns().
	 */
	private function render_columns_menu( array $col_order, array $hidden_cols ): void {
		$labels = $this->column_labels();
		$last   = count( $col_order ) - 1;
		?>
		<div id="iftp-btn-columns-menu" class="iftp-btn-popover iftp-btn-columns" data-iftp-columns-menu role="group" aria-labelledby="iftp-btn-columns-title" hidden>
			<p id="iftp-btn-columns-title" class="iftp-btn-popover__title"><?php esc_html_e( 'Choose and order columns', 'ifthenpay-pay-button' ); ?></p>

			<p class="iftp-btn-columns__locked">
				<svg class="iftp-btn-columns__locked-icon" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false"><rect x="2.75" y="6.25" width="8.5" height="6" rx="1.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M4.75 6.25V4.5a2.25 2.25 0 0 1 4.5 0v1.75" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
				<span class="iftp-btn-columns__locked-label"><?php esc_html_e( 'Reference', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-columns__locked-hint"><?php esc_html_e( 'Always shown', 'ifthenpay-pay-button' ); ?></span>
			</p>

			<ul class="iftp-btn-columns__list" data-iftp-col-list>
				<?php foreach ( $col_order as $i => $slug ) : ?>
					<?php
					$label = $labels[ $slug ] ?? $slug;
					/* translators: %s: column name */
					$move_up = sprintf( __( 'Move %s up', 'ifthenpay-pay-button' ), $label );
					/* translators: %s: column name */
					$move_down = sprintf( __( 'Move %s down', 'ifthenpay-pay-button' ), $label );
					?>
					<li class="iftp-btn-columns__item" data-iftp-col-item="<?php echo esc_attr( $slug ); ?>">
						<span class="iftp-btn-columns__handle" data-iftp-col-drag draggable="true" aria-hidden="true">
							<svg width="10" height="16" viewBox="0 0 10 16" aria-hidden="true" focusable="false"><g fill="currentColor"><circle cx="3" cy="4" r="1.25"/><circle cx="7" cy="4" r="1.25"/><circle cx="3" cy="8" r="1.25"/><circle cx="7" cy="8" r="1.25"/><circle cx="3" cy="12" r="1.25"/><circle cx="7" cy="12" r="1.25"/></g></svg>
						</span>
						<label class="iftp-btn-columns__option">
							<input type="checkbox" data-iftp-col-toggle="<?php echo esc_attr( $slug ); ?>" <?php checked( ! in_array( $slug, $hidden_cols, true ) ); ?> />
							<span class="iftp-btn-columns__option-label"><?php echo esc_html( $label ); ?></span>
						</label>
						<span class="iftp-btn-columns__move">
							<button type="button" class="iftp-btn-columns__move-btn" data-iftp-col-move="up" aria-label="<?php echo esc_attr( $move_up ); ?>"<?php echo 0 === $i ? ' aria-disabled="true"' : ''; ?>>
								<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M3 7.5 6 4.5l3 3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</button>
							<button type="button" class="iftp-btn-columns__move-btn" data-iftp-col-move="down" aria-label="<?php echo esc_attr( $move_down ); ?>"<?php echo $last === $i ? ' aria-disabled="true"' : ''; ?>>
								<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M3 4.5 6 7.5l3-3" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="iftp-btn-popover__footer">
				<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-col-reset><?php esc_html_e( 'Reset columns', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * The Export popover: one download link per format, for the current filtered view.
	 *
	 * @param array<string, mixed> $view The current view.
	 */
	private function render_export_menu( array $view ): void {
		$formats = array(
			self::EXPORT_CSV  => array( __( 'CSV', 'ifthenpay-pay-button' ), __( 'For Excel or Google Sheets', 'ifthenpay-pay-button' ) ),
			self::EXPORT_JSON => array( __( 'JSON', 'ifthenpay-pay-button' ), __( 'For developers and integrations', 'ifthenpay-pay-button' ) ),
		);
		?>
		<div id="iftp-btn-export-menu" class="iftp-btn-popover iftp-btn-export" data-iftp-export-menu role="group" aria-labelledby="iftp-btn-export-title" hidden>
			<p id="iftp-btn-export-title" class="iftp-btn-popover__title"><?php esc_html_e( 'Export this view as', 'ifthenpay-pay-button' ); ?></p>
			<ul class="iftp-btn-export__list">
				<?php foreach ( $formats as $format => $text ) : ?>
					<li class="iftp-btn-export__item">
						<a class="iftp-btn-export__option" href="<?php echo esc_url( $this->export_url( $view, $format ) ); ?>" data-iftp-export-option>
							<span class="iftp-btn-export__label"><?php echo esc_html( $text[0] ); ?></span>
							<span class="iftp-btn-export__hint"><?php echo esc_html( $text[1] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Re-queries and renders the tbody, pagination and summary for a view, for every AJAX mutation's response.
	 *
	 * @param array<string, mixed> $view A view from ViewState::from_post().
	 * @return array{rowsHtml: string, paginationHtml: string, summaryHtml: string, paged: int, total: int, totalLabel: string}
	 */
	public function rendered_view( array $view ): array {
		$shared = ViewState::store_filters( $view );
		$result = $this->transactions->query(
			$shared + array(
				'status'   => $view['status'],
				'orderby'  => $view['orderby'],
				'order'    => $view['order'],
				'per_page' => $view['per_page'],
				'paged'    => $view['paged'],
			)
		);
		$base   = ViewState::url_args( array( 'paged' => $result['paged'] ) + $view );

		ob_start();
		$this->print_rows( $result['items'] );
		$rows = (string) ob_get_clean();

		ob_start();
		$this->render_pagination( $result['paged'], $result['pages'], $base );
		$pagination = (string) ob_get_clean();

		ob_start();
		$this->print_summary( $this->transactions->status_summary( $shared ), $base );
		$summary = (string) ob_get_clean();

		return array(
			'rowsHtml'       => $rows,
			'paginationHtml' => $pagination,
			'summaryHtml'    => $summary,
			'paged'          => $result['paged'],
			'total'          => $result['total'],
			'totalLabel'     => $this->range_label( $result['paged'], (int) $view['per_page'], $result['total'] ),
		);
	}

	/**
	 * Prints the tbody contents for a page of entries.
	 *
	 * @param array<int, array<string, mixed>> $rows The page of transaction rows.
	 * @phpstan-param array<int, Transaction> $rows
	 */
	private function print_rows( array $rows ): void {
		if ( array() === $rows ) {
			?>
			<tr class="iftp-btn-table__empty-row">
				<td colspan="<?php echo esc_attr( (string) ( 2 + count( self::HIDEABLE_COLUMNS ) ) ); ?>" class="iftp-btn-table__empty">
					<div class="iftp-btn-empty-state">
						<span class="iftp-btn-mark iftp-btn-empty-state__icon" aria-hidden="true">
							<img class="iftp-btn-mark__logo" src="<?php echo esc_url( IFTP_BTN_URL . 'assets/img/icon-normal.svg' ); ?>" alt="" width="28" height="28" />
						</span>
						<p class="iftp-btn-empty-state__text"><?php esc_html_e( 'No ifthenpay payments match this view.', 'ifthenpay-pay-button' ); ?></p>
					</div>
				</td>
			</tr>
			<?php
			return;
		}

		$hidden = $this->hidden_columns();
		$order  = $this->column_order();

		foreach ( $rows as $record ) {
			$this->render_row( $record, $order, $hidden );
		}
	}

	/**
	 * Renders one transaction row. Row actions are always visible, not hover-only, for touch and keyboard users.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @param array<int, string>   $order  The column order.
	 * @param array<int, string>   $hidden The hidden columns.
	 * @phpstan-param Transaction $record
	 */
	private function render_row( array $record, array $order, array $hidden ): void {
		$ref     = $record['ref'];
		$is_paid = TransactionStore::STATUS_PAID === $record['status'];
		?>
		<tr class="iftp-btn-table__row" data-iftp-row data-iftp-ref="<?php echo esc_attr( $ref ); ?>">
			<td class="iftp-btn-table__check">
				<input type="checkbox" data-iftp-row-check data-iftp-status="<?php echo esc_attr( $record['status'] ); ?>" value="<?php echo esc_attr( $ref ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: payment reference */ __( 'Select %s', 'ifthenpay-pay-button' ), $ref ) ); ?>" />
			</td>
			<td class="iftp-btn-table__cell iftp-btn-table__cell--ref" data-col="ref">
				<button type="button" class="iftp-btn-ref" data-iftp-copy-ref="<?php echo esc_attr( $ref ); ?>" title="<?php esc_attr_e( 'Copy reference', 'ifthenpay-pay-button' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: payment reference */ __( 'Copy reference %s', 'ifthenpay-pay-button' ), $ref ) ); ?>">
					<strong class="iftp-btn-ref__text"><?php echo esc_html( $ref ); ?></strong>
				</button>
				<span class="iftp-btn-row-actions">
					<a class="iftp-btn-row-actions__action" href="<?php echo esc_url( self::entry_url( $ref ) ); ?>"><?php esc_html_e( 'View', 'ifthenpay-pay-button' ); ?><span class="screen-reader-text"> <?php echo esc_html( $ref ); ?></span></a>
					<?php $this->render_link_action( $record ); ?>
					<?php // Paid rows are payment history, so no Delete; the server refuses them too. ?>
					<?php if ( ! $is_paid ) : ?>
						<span class="iftp-btn-row-actions__sep" aria-hidden="true">|</span>
						<button type="button" class="iftp-btn-btn iftp-btn-btn--link iftp-btn-row-actions__action iftp-btn-row-actions__action--delete" data-iftp-row-delete="<?php echo esc_attr( $ref ); ?>"><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></button>
					<?php endif; ?>
				</span>
			</td>
			<?php
			foreach ( $order as $slug ) {
				$this->render_cell( $slug, $record, in_array( $slug, $hidden, true ) );
			}
			?>
		</tr>
		<?php
	}

	/**
	 * The row's link action: "Copy link" while it can still be paid, otherwise a disabled button with the reason.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	public function render_link_action( array $record ): void {
		$payment_url = $record['payment_url'];
		$status      = $record['status'];

		if ( TransactionStore::STATUS_PENDING === $status && '' !== $payment_url ) {
			?>
			<span class="iftp-btn-row-actions__sep" aria-hidden="true">|</span>
			<button type="button" class="iftp-btn-btn iftp-btn-btn--link iftp-btn-row-actions__action" data-iftp-copy-link="<?php echo esc_attr( $payment_url ); ?>"><?php esc_html_e( 'Copy link', 'ifthenpay-pay-button' ); ?></button>
			<?php
			return;
		}

		if ( TransactionStore::STATUS_PENDING === $status ) {
			$label = __( 'No link yet', 'ifthenpay-pay-button' );
			$hint  = TransactionStore::MANUAL_GATEWAY_KEY === $record['gateway_key']
				? __( 'Recorded by hand, so there\'s no payment link.', 'ifthenpay-pay-button' )
				: __( 'No payment link was created yet. Sending the payment email creates one.', 'ifthenpay-pay-button' );
		} elseif ( TransactionStore::STATUS_EXPIRED === $status && '' !== $payment_url ) {
			$label = __( 'Link expired', 'ifthenpay-pay-button' );
			$hint  = __( 'This payment expired, so its link can no longer be paid.', 'ifthenpay-pay-button' );
		} else {
			return;
		}
		?>
		<span class="iftp-btn-row-actions__sep" aria-hidden="true">|</span>
		<button type="button" class="iftp-btn-btn iftp-btn-btn--link iftp-btn-row-actions__action" title="<?php echo esc_attr( $hint ); ?>" disabled><?php echo esc_html( $label ); ?></button>
		<?php
	}

	/**
	 * One hideable column's header cell. Hidden columns stay in the DOM with `hidden`, so a toggle needs no reload.
	 *
	 * @param string             $slug    One of HIDEABLE_COLUMNS.
	 * @param string             $orderby The view's orderby.
	 * @param string             $order   The view's order.
	 * @param array<int, string> $hidden  The hidden columns.
	 */
	private function render_head_cell( string $slug, string $orderby, string $order, array $hidden ): void {
		$sort_keys = array(
			'customer' => 'customer_name',
			'amount'   => 'amount',
			'status'   => 'status',
			'created'  => 'created_at',
		);
		$label     = $this->column_labels()[ $slug ] ?? $slug;
		$sort_key  = $sort_keys[ $slug ] ?? '';
		$classes   = 'iftp-btn-table__head iftp-btn-table__head--' . $slug;
		?>
		<th class="<?php echo esc_attr( $classes ); ?>" data-col="<?php echo esc_attr( $slug ); ?>" scope="col"<?php echo '' !== $sort_key ? ' aria-sort="' . esc_attr( $this->aria_sort( $sort_key, $orderby, $order ) ) . '"' : ''; ?><?php echo in_array( $slug, $hidden, true ) ? ' hidden' : ''; ?>>
			<?php
			if ( '' !== $sort_key ) {
				$this->print_sort_link( $sort_key, $label, $orderby, $order );
			} else {
				echo esc_html( $label );
			}
			?>
		</th>
		<?php
	}

	/**
	 * One hideable column's body cell.
	 *
	 * @param string               $slug      One of HIDEABLE_COLUMNS.
	 * @param array<string, mixed> $record    The transaction row.
	 * @param bool                 $is_hidden Whether the admin hid this column.
	 * @phpstan-param Transaction $record
	 */
	private function render_cell( string $slug, array $record, bool $is_hidden ): void {
		$label = $this->column_labels()[ $slug ] ?? $slug;

		printf( '<td class="%1$s" data-col="%2$s" data-label="%3$s"%4$s>', esc_attr( 'iftp-btn-table__cell iftp-btn-table__cell--' . $slug ), esc_attr( $slug ), esc_attr( $label ), $is_hidden ? ' hidden' : '' );

		switch ( $slug ) {
			case 'customer':
				$this->presenter->print_customer( $record['customer_name'], $record['customer_email'] );
				break;
			case 'button':
				$this->print_button_cell( $record );
				break;
			case 'method':
				$this->presenter->print_method( $record['pay_method'], $record['status'] );
				break;
			case 'amount':
				echo esc_html( $this->presenter->format_amount( $record['amount'] ) );
				break;
			case 'status':
				$this->presenter->print_status_badge( $record['status'] );
				break;
			case 'created':
				$this->presenter->print_time( $record['created_at'] );
				break;
		}

		echo '</td>';
	}

	/**
	 * Prints the totals summary, one card per status, each a link to that status (and back to All when it's the current one).
	 *
	 * @param array<string, array{count: int, amount: float}> $summary From TransactionStore::status_summary().
	 * @param array<string, mixed>                            $base    url_args() of the view.
	 */
	private function print_summary( array $summary, array $base ): void {
		$statuses = array( TransactionStore::STATUS_PAID, TransactionStore::STATUS_PENDING, TransactionStore::STATUS_FAILED, TransactionStore::STATUS_EXPIRED );
		?>
		<ul class="iftp-btn-summary__list">
			<?php foreach ( $statuses as $slug ) : ?>
				<?php
				$count      = (int) ( $summary[ $slug ]['count'] ?? 0 );
				$amount     = (float) ( $summary[ $slug ]['amount'] ?? 0 );
				$is_current = $slug === $base['status'];
				$classes    = 'iftp-btn-summary__item iftp-btn-summary__item--' . $slug . ( 0 === $count ? ' iftp-btn-summary__item--empty' : '' ) . ( $is_current ? ' iftp-btn-summary__item--current' : '' );
				$url        = $this->view->url(
					array(
						'status' => $is_current ? '' : $slug,
						'paged'  => null,
					),
					$base
				);
				?>
				<li class="<?php echo esc_attr( $classes ); ?>">
					<a class="iftp-btn-summary__link" href="<?php echo esc_url( $url ); ?>"<?php echo $is_current ? ' aria-current="true"' : ''; ?>>
						<span class="iftp-btn-summary__label"><?php $this->presenter->print_status_badge( $slug ); ?></span>
						<span class="iftp-btn-summary__amount"><?php echo esc_html( $this->presenter->format_amount( $amount ) ); ?></span>
						<span class="iftp-btn-summary__count">
							<?php
							/* translators: %s: number of entries */
							echo esc_html( sprintf( _n( '%s entry', '%s entries', $count, 'ifthenpay-pay-button' ), number_format_i18n( $count ) ) );
							?>
						</span>
						<?php if ( $is_current ) : ?>
							<span class="screen-reader-text"><?php esc_html_e( 'Selected. Activate to show all entries.', 'ifthenpay-pay-button' ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Renders the pagination, windowed around the current page. Each ellipsis is a page-jump box, its own GET form so it works without JS.
	 *
	 * @param int                  $paged The current page.
	 * @param int                  $pages The total number of pages.
	 * @param array<string, mixed> $base  url_args() of the view.
	 */
	private function render_pagination( int $paged, int $pages, array $base ): void {
		if ( $pages <= 1 ) {
			return;
		}

		$window_start = max( 1, $paged - 2 );
		$window_end   = min( $paged + 2, $pages );
		?>
		<nav class="iftp-btn-pagination" aria-label="<?php esc_attr_e( 'Entries pagination', 'ifthenpay-pay-button' ); ?>">
			<?php if ( $paged > 1 ) : ?>
				<a class="iftp-btn-pagination__step iftp-btn-pagination__step--prev" href="<?php echo esc_url( $this->view->url( array( 'paged' => $paged - 1 ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'ifthenpay-pay-button' ); ?>"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3.5 5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
			<?php endif; ?>

			<?php if ( $window_start > 1 ) : ?>
				<a class="iftp-btn-pagination__page" href="<?php echo esc_url( $this->view->url( array( 'paged' => 1 ), $base ) ); ?>">1</a>
				<?php if ( $window_start > 2 ) : ?>
					<?php $this->render_page_jump( $pages, $base ); ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php for ( $page = $window_start; $page <= $window_end; $page++ ) : ?>
				<?php if ( $page === $paged ) : ?>
					<span class="iftp-btn-pagination__page iftp-btn-pagination__page--current" aria-current="page"><?php echo esc_html( (string) $page ); ?></span>
				<?php else : ?>
					<a class="iftp-btn-pagination__page" href="<?php echo esc_url( $this->view->url( array( 'paged' => $page ), $base ) ); ?>"><?php echo esc_html( (string) $page ); ?></a>
				<?php endif; ?>
			<?php endfor; ?>

			<?php if ( $window_end < $pages ) : ?>
				<?php if ( $window_end < $pages - 1 ) : ?>
					<?php $this->render_page_jump( $pages, $base ); ?>
				<?php endif; ?>
				<a class="iftp-btn-pagination__page" href="<?php echo esc_url( $this->view->url( array( 'paged' => $pages ), $base ) ); ?>"><?php echo esc_html( (string) $pages ); ?></a>
			<?php endif; ?>

			<?php if ( $paged < $pages ) : ?>
				<a class="iftp-btn-pagination__step iftp-btn-pagination__step--next" href="<?php echo esc_url( $this->view->url( array( 'paged' => $paged + 1 ), $base ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'ifthenpay-pay-button' ); ?>"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M6 3.5 10.5 8 6 12.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * A page-jump box in place of a pagination ellipsis.
	 *
	 * @param int                  $pages The total number of pages.
	 * @param array<string, mixed> $base  url_args() of the view.
	 */
	private function render_page_jump( int $pages, array $base ): void {
		?>
		<form class="iftp-btn-pagination__jump" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<?php $this->print_hidden_inputs( $base, array( 'paged' ) ); ?>
			<input type="number" class="iftp-btn-pagination__jump-input" name="paged" min="1" max="<?php echo esc_attr( (string) $pages ); ?>" step="1" inputmode="numeric" required autocomplete="off" placeholder="&hellip;" aria-label="<?php esc_attr_e( 'Go to page', 'ifthenpay-pay-button' ); ?>" />
		</form>
		<?php
	}

	/**
	 * Hidden inputs that carry a view into a plain GET form.
	 *
	 * @param array<string, mixed> $args url_args() output.
	 * @param array<int, string>   $omit Keys the form sets itself.
	 */
	private function print_hidden_inputs( array $args, array $omit ): void {
		foreach ( array( 'page' => self::PAGE_SLUG ) + $args as $key => $value ) {
			if ( in_array( $key, $omit, true ) || '' === $value || null === $value ) {
				continue;
			}

			if ( ( 'per_page' === $key && ViewState::DEFAULT_PER_PAGE === (int) $value ) || ( 'paged' === $key && 1 === (int) $value ) ) {
				continue;
			}

			echo '<input type="hidden" name="' . esc_attr( (string) $key ) . '" value="' . esc_attr( (string) $value ) . '" />';
		}
	}

	/**
	 * Prints a sortable column header link.
	 *
	 * @param string $key             The column's orderby key.
	 * @param string $label           The column's label.
	 * @param string $current_orderby The view's orderby.
	 * @param string $current_order   The view's order.
	 */
	private function print_sort_link( string $key, string $label, string $current_orderby, string $current_order ): void {
		$is_sorted  = $key === $current_orderby;
		$next_order = ( $is_sorted && 'asc' === $current_order ) ? 'desc' : 'asc';
		$indicator  = $is_sorted ? ( 'asc' === $current_order ? '▲' : '▼' ) : '';
		$url        = $this->view->url(
			array(
				'orderby' => $key,
				'order'   => $next_order,
				'paged'   => null,
			)
		);

		printf(
			'<a class="%1$s" href="%2$s">%3$s <span class="iftp-btn-sort__icon" aria-hidden="true">%4$s</span></a>',
			esc_attr( 'iftp-btn-sort' . ( $is_sorted ? ' iftp-btn-sort--' . $current_order : '' ) ),
			esc_url( $url ),
			esc_html( $label ),
			esc_html( $indicator )
		);
	}

	/**
	 * The th's aria-sort value for a sortable column.
	 *
	 * @param string $key             The column's orderby key.
	 * @param string $current_orderby The view's orderby.
	 * @param string $current_order   The view's order.
	 */
	private function aria_sort( string $key, string $current_orderby, string $current_order ): string {
		if ( $key !== $current_orderby ) {
			return 'none';
		}

		return 'asc' === $current_order ? 'ascending' : 'descending';
	}

	/**
	 * The "1 – 50 out of 1,234" footer label, blank when there's nothing (the empty state says it).
	 *
	 * @param int $paged    The current page.
	 * @param int $per_page Rows per page.
	 * @param int $total    Matching rows.
	 */
	private function range_label( int $paged, int $per_page, int $total ): string {
		if ( $total <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: 1: first entry number shown, 2: last entry number shown, 3: total number of entries */
			__( '%1$s – %2$s out of %3$s', 'ifthenpay-pay-button' ),
			number_format_i18n( ( $paged - 1 ) * $per_page + 1 ),
			number_format_i18n( min( $paged * $per_page, $total ) ),
			number_format_i18n( $total )
		);
	}

	/**
	 * One chip per applied panel filter, each linking to the view without it.
	 *
	 * @param array<string, mixed> $view The current view.
	 * @return array<int, array{key: string, value: string, url: string}>
	 */
	private function active_filter_chips( array $view ): array {
		$chips = array();

		if ( $view['button'] > 0 ) {
			$chips[] = array(
				'key'   => __( 'Button:', 'ifthenpay-pay-button' ),
				'value' => $this->button_titles()[ $view['button'] ] ?? '#' . $view['button'],
				'url'   => $this->view->url(
					array(
						'button' => null,
						'paged'  => null,
					)
				),
			);
		}

		if ( '' !== $view['pay_method'] ) {
			$chips[] = array(
				'key'   => __( 'Method:', 'ifthenpay-pay-button' ),
				'value' => (string) $view['pay_method'],
				'url'   => $this->view->url(
					array(
						'pay_method' => null,
						'paged'      => null,
					)
				),
			);
		}

		$amount = $this->range_text(
			'' !== $view['amount_min'] ? $this->presenter->format_amount( (float) $view['amount_min'] ) : '',
			'' !== $view['amount_max'] ? $this->presenter->format_amount( (float) $view['amount_max'] ) : '',
			/* translators: %s: an amount */
			__( 'up to %s', 'ifthenpay-pay-button' )
		);

		if ( '' !== $amount ) {
			$chips[] = array(
				'key'   => __( 'Amount:', 'ifthenpay-pay-button' ),
				'value' => $amount,
				'url'   => $this->view->url(
					array(
						'amount_min' => null,
						'amount_max' => null,
						'paged'      => null,
					)
				),
			);
		}

		$created = '';

		// A range that matches a quick preset reads better as its label.
		foreach ( ViewState::date_presets() as $preset ) {
			if ( '' !== $view['date_from'] && $preset['from'] === $view['date_from'] && $preset['to'] === $view['date_to'] ) {
				$created = $preset['label'];
				break;
			}
		}

		if ( '' === $created ) {
			$date_format = (string) get_option( 'date_format' );
			$created     = $this->range_text(
				'' !== $view['date_from'] ? Dates::local( $date_format, ViewState::day_bound( (string) $view['date_from'], '12:00:00' ) ) : '',
				'' !== $view['date_to'] ? Dates::local( $date_format, ViewState::day_bound( (string) $view['date_to'], '12:00:00' ) ) : '',
				/* translators: %s: a date */
				__( 'until %s', 'ifthenpay-pay-button' )
			);
		}

		if ( '' !== $created ) {
			$chips[] = array(
				'key'   => __( 'Created:', 'ifthenpay-pay-button' ),
				'value' => $created,
				'url'   => $this->view->url(
					array(
						'date_from' => null,
						'date_to'   => null,
						'paged'     => null,
					)
				),
			);
		}

		return $chips;
	}

	/**
	 * A chip's range text: "A – B", "from A", or the given upper-only wording.
	 *
	 * @param string $low   Formatted lower bound, or ''.
	 * @param string $high  Formatted upper bound, or ''.
	 * @param string $up_to Translated upper-only format with one %s.
	 */
	private function range_text( string $low, string $high, string $up_to ): string {
		if ( '' !== $low && '' !== $high ) {
			/* translators: 1: lower bound, 2: upper bound */
			return sprintf( __( '%1$s – %2$s', 'ifthenpay-pay-button' ), $low, $high );
		}

		if ( '' !== $low ) {
			/* translators: %s: an amount or a date */
			return sprintf( __( 'from %s', 'ifthenpay-pay-button' ), $low );
		}

		return '' !== $high ? sprintf( $up_to, $high ) : '';
	}

	/**
	 * The "Clear filters" link: drops the panel filters, keeps search/status/sort/per-page.
	 */
	private function clear_filters_url(): string {
		return $this->view->url(
			array(
				'button'     => null,
				'pay_method' => null,
				'amount_min' => null,
				'amount_max' => null,
				'date_from'  => null,
				'date_to'    => null,
				'paged'      => null,
			)
		);
	}

	/**
	 * The export link for a view and format, without pagination.
	 *
	 * @param array<string, mixed> $view   The view.
	 * @param string               $format One of the EXPORT_* formats.
	 */
	private function export_url( array $view, string $format ): string {
		$args = ViewState::url_args( $view );

		unset( $args['per_page'], $args['paged'] );

		$args = array(
			'action'   => self::EXPORT_ACTION,
			'_wpnonce' => wp_create_nonce( self::EXPORT_ACTION ),
			'format'   => $format,
		) + $args;

		return add_query_arg( ViewState::query_values( $args ), admin_url( 'admin-post.php' ) );
	}

	/**
	 * The column labels, slug => label.
	 *
	 * @return array<string, string>
	 */
	public function column_labels(): array {
		return array(
			'customer' => __( 'Customer', 'ifthenpay-pay-button' ),
			'button'   => __( 'Button', 'ifthenpay-pay-button' ),
			'method'   => __( 'Method', 'ifthenpay-pay-button' ),
			'amount'   => __( 'Amount', 'ifthenpay-pay-button' ),
			'status'   => __( 'Status', 'ifthenpay-pay-button' ),
			'created'  => __( 'Created', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * Every button's title by ID, loaded once per request for the column and the filter.
	 *
	 * @return array<int, string>
	 */
	private function button_titles(): array {
		if ( null === $this->button_titles ) {
			$this->button_titles = array();

			foreach ( ( new ButtonRepository() )->all() as $button ) {
				$this->button_titles[ $button->id ] = $button->title;
			}
		}

		return $this->button_titles;
	}

	/**
	 * The Button cell: which button took the payment, and the reference another plugin passed along.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function print_button_cell( array $record ): void {
		if ( $record['button_id'] <= 0 ) {
			$this->presenter->print_empty_value( __( 'Created here', 'ifthenpay-pay-button' ) );

			return;
		}

		$title = $this->button_titles()[ $record['button_id'] ] ?? '';

		if ( '' === $title ) {
			$this->presenter->print_empty_value( __( 'Deleted button', 'ifthenpay-pay-button' ) );
		} else {
			printf( '<a class="iftp-btn-button-link" href="%1$s">%2$s</a>', esc_url( ButtonsPage::edit_url( $record['button_id'] ) ), esc_html( $title ) );
		}

		if ( '' !== $record['external_ref'] ) {
			printf( '<span class="iftp-btn-external-ref" title="%1$s">%2$s</span>', esc_attr__( 'Reference from the link', 'ifthenpay-pay-button' ), esc_html( $record['external_ref'] ) );
		}
	}

	/**
	 * The current admin's hidden columns, limited to HIDEABLE_COLUMNS.
	 *
	 * @return array<int, string>
	 */
	private function hidden_columns(): array {
		$stored = get_user_meta( get_current_user_id(), self::HIDDEN_COLS_META, true );

		return is_array( $stored ) ? array_values( array_intersect( self::HIDEABLE_COLUMNS, $stored ) ) : array();
	}

	/**
	 * The current admin's column order: every hideable slug, once each.
	 *
	 * @return array<int, string>
	 */
	private function column_order(): array {
		return $this->column_order ??= self::normalize_column_order( get_user_meta( get_current_user_id(), self::COL_ORDER_META, true ) );
	}

	/**
	 * Keeps known slugs in the given order and appends any missing ones.
	 *
	 * @param mixed $requested Stored meta or the sanitized POST.
	 * @return array<int, string>
	 */
	public static function normalize_column_order( $requested ): array {
		$order = is_array( $requested )
			? array_values( array_unique( array_intersect( array_filter( $requested, 'is_string' ), self::HIDEABLE_COLUMNS ) ) )
			: array();

		return array_merge( $order, array_values( array_diff( self::HIDEABLE_COLUMNS, $order ) ) );
	}
}
