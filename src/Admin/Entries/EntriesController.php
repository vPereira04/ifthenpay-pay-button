<?php
/**
 * Entries AJAX actions and export.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Api\PaymentLinks;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Mail\PaymentEmailSender;
use Ifthenpay\PayButton\Mail\ReceiptEmail;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every mutation on the Entries screens. Each one answers with the re-rendered view, so the list never reloads.
 * Refs always travel as one JSON field, since PHP drops input vars past `max_input_vars`.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class EntriesController {

	// entries.js sends at most EntriesPage::BULK_CHUNK_SIZE per request; anything past this is refused.
	private const BULK_MAX_REFS = 1000;

	// A request stops taking new emails after this many seconds, so it stays well inside max_execution_time.
	private const EMAIL_TIME_BUDGET = 15;

	private const EMAIL_SENT        = PaymentEmailSender::SENT;
	private const EMAIL_INELIGIBLE  = PaymentEmailSender::INELIGIBLE;
	private const EMAIL_NO_LINK     = PaymentEmailSender::NO_LINK;
	private const EMAIL_MAIL_FAILED = PaymentEmailSender::MAIL_FAILED;

	/**
	 * The Entries screen, for the re-rendered view.
	 *
	 * @var EntriesPage
	 */
	private EntriesPage $page;

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
	 * Pay by Link creation.
	 *
	 * @var PaymentLinks
	 */
	private PaymentLinks $links;

	/**
	 * Sets up the controller.
	 *
	 * @param EntriesPage             $page     The Entries screen.
	 * @param PaymentLinks|null       $links    Pay by Link creation.
	 * @param SettingsRepository|null $settings Settings storage.
	 */
	public function __construct( EntriesPage $page, ?PaymentLinks $links = null, ?SettingsRepository $settings = null ) {
		$this->page         = $page;
		$this->transactions = $page->transactions();
		$this->settings     = $settings ?? new SettingsRepository();
		$this->links        = $links ?? new PaymentLinks( $this->settings, null, $this->transactions );
	}

	/**
	 * Registers the AJAX and export hooks.
	 */
	public function register(): void {
		add_action( 'wp_ajax_iftp_btn_delete_entries', array( $this, 'delete_entries' ) );
		add_action( 'wp_ajax_iftp_btn_update_status', array( $this, 'update_status' ) );
		add_action( 'wp_ajax_iftp_btn_create_entry', array( $this, 'create_entry' ) );
		add_action( 'wp_ajax_iftp_btn_save_columns', array( $this, 'save_columns' ) );
		add_action( 'wp_ajax_iftp_btn_resend_payment_email', array( $this, 'resend_payment_email' ) );
		add_action( 'wp_ajax_iftp_btn_send_payment_emails', array( $this, 'send_payment_emails' ) );
		add_action( 'wp_ajax_iftp_btn_resend_receipts', array( $this, 'resend_receipts' ) );
		add_action( 'admin_post_' . EntriesPage::EXPORT_ACTION, array( $this, 'export' ) );
	}

	/**
	 * Whether a row is a pending ifthenpay payment with a valid customer email to send its link to.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function can_email( array $record ): bool {
		return PaymentEmailSender::can_email( $record );
	}


	/**
	 * Deletes the given refs, except paid ones.
	 */
	public function delete_entries(): void {
		$this->guard();

		$deleted = $this->transactions->delete_unpaid( $this->bulk_refs() );

		wp_send_json_success( array( 'deleted' => $deleted ) + $this->page->rendered_view( $this->page->view_state()->from_post() ) );
	}

	/**
	 * Bulk-sets a status. An explicit admin override, so unlike the webhook it may move a row off "paid".
	 */
	public function update_status(): void {
		$this->guard();

		$status = $this->post_text( 'status' );

		if ( ! in_array( $status, ViewState::LIST_STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown status.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$updated = $this->transactions->admin_set_status_many( $this->bulk_refs(), $status );

		wp_send_json_success(
			array(
				'updated' => $updated,
				'status'  => $status,
			) + $this->page->rendered_view( $this->page->view_state()->from_post() )
		);
	}

	/**
	 * "New payment". Pending goes through ifthenpay and gets a link right away; any other status is recorded by hand.
	 * The view comes under `view_*` keys so it can't clash with the new payment's own fields.
	 */
	public function create_entry(): void {
		$this->guard();

		$amount = round( (float) str_replace( ',', '.', $this->post_text( 'amount', '0' ) ), 2 );

		if ( $amount <= 0 || $amount > 99999999 ) {
			wp_send_json_error( array( 'message' => __( 'Enter an amount greater than zero.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$customer_email = $this->post_text( 'customer_email' );

		if ( '' !== $customer_email && false === is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid email, or leave it blank.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$status = $this->post_text( 'status' );

		if ( ! in_array( $status, ViewState::LIST_STATUSES, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown status.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$customer_name = $this->post_text( 'customer_name' );
		$is_pending    = TransactionStore::STATUS_PENDING === $status;
		$ref           = $is_pending
			? $this->transactions->create_pending( $customer_name, $customer_email, $amount, $this->settings->get_gateway_key() )
			: $this->transactions->create_manual( $customer_name, $customer_email, $amount, $status );
		$record        = null !== $ref ? $this->transactions->get( $ref ) : null;

		if ( null === $ref || null === $record ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the payment. Please try again.', 'ifthenpay-pay-button' ) ), 500 );
		}

		$payment_url = $is_pending ? $this->links->create_link_for( $record ) : '';
		$emailed     = false;

		// "Email the payment link now": only once there's a link, and through the same path as "Send payment email".
		if ( '' !== $payment_url && '' !== $this->post_text( 'send_email' ) ) {
			$emailed = self::EMAIL_SENT === $this->send_payment_email( $this->transactions->get( $ref ) );
		}

		wp_send_json_success(
			array(
				'ref'         => $ref,
				'entryUrl'    => EntriesPage::entry_url( $ref ),
				'paymentUrl'  => $payment_url,
				'emailed'     => $emailed,
				'amountLabel' => $this->page->presenter()->format_amount( $amount ),
				'doneTitle'   => $this->done_title( $is_pending, $payment_url ),
				'doneText'    => $this->done_text( $is_pending, $payment_url, $emailed, $customer_email, '' !== $this->post_text( 'send_email' ) ),
				'message'     => __( 'Payment created.', 'ifthenpay-pay-button' ),
			) + $this->page->rendered_view( $this->page->view_state()->from_post( 'view_' ) )
		);
	}

	/**
	 * The success screen's heading.
	 *
	 * @param bool   $is_pending  Whether it went through ifthenpay.
	 * @param string $payment_url The link, or '' when ifthenpay gave none.
	 */
	private function done_title( bool $is_pending, string $payment_url ): string {
		if ( ! $is_pending ) {
			return __( 'Payment recorded', 'ifthenpay-pay-button' );
		}

		return '' !== $payment_url ? __( 'Payment link ready', 'ifthenpay-pay-button' ) : __( 'Payment created', 'ifthenpay-pay-button' );
	}

	/**
	 * The success screen's line under the amount.
	 *
	 * @param bool   $is_pending  Whether it went through ifthenpay.
	 * @param string $payment_url The link, or ''.
	 * @param bool   $emailed     Whether the email went out.
	 * @param string $email       The customer's email, or ''.
	 * @param bool   $asked       Whether the admin asked for the email.
	 */
	private function done_text( bool $is_pending, string $payment_url, bool $emailed, string $email, bool $asked ): string {
		if ( ! $is_pending ) {
			return __( 'It\'s in your entries, filed as "Ad Hoc".', 'ifthenpay-pay-button' );
		}

		if ( '' === $payment_url ) {
			return __( 'ifthenpay didn\'t return a link yet: check the enabled payment methods. Sending the payment email from the entry retries it.', 'ifthenpay-pay-button' );
		}

		if ( $emailed ) {
			/* translators: %s: customer email address */
			return sprintf( __( 'Emailed to %s. You can also share the link yourself:', 'ifthenpay-pay-button' ), $email );
		}

		if ( $asked ) {
			return __( 'The email couldn\'t be sent, so share the link yourself:', 'ifthenpay-pay-button' );
		}

		return __( 'Share it with your customer:', 'ifthenpay-pay-button' );
	}

	/**
	 * Remembers which columns the current admin hid, and their order.
	 */
	public function save_columns(): void {
		$this->guard();

		$hidden = array_values( array_intersect( EntriesPage::HIDEABLE_COLUMNS, $this->post_keys( 'hidden' ) ) );
		$order  = EntriesPage::normalize_column_order( $this->post_keys( 'order' ) );

		update_user_meta( get_current_user_id(), EntriesPage::HIDDEN_COLS_META, $hidden );
		update_user_meta( get_current_user_id(), EntriesPage::COL_ORDER_META, $order );

		wp_send_json_success(
			array(
				'hidden' => $hidden,
				'order'  => $order,
			)
		);
	}

	/**
	 * Emails one pending payment's link to its customer.
	 */
	public function resend_payment_email(): void {
		$this->guard();

		$record  = $this->transactions->get( $this->post_text( 'ref' ) );
		$outcome = $this->send_payment_email( $record );

		if ( self::EMAIL_INELIGIBLE === $outcome || null === $record ) {
			wp_send_json_error( array( 'message' => __( 'This payment is no longer pending, or has no customer email to send to.', 'ifthenpay-pay-button' ) ), 409 );
		}

		if ( self::EMAIL_NO_LINK === $outcome ) {
			wp_send_json_error( array( 'message' => __( 'Could not create a payment link. Check the ifthenpay connection and the enabled methods.', 'ifthenpay-pay-button' ) ), 502 );
		}

		if ( self::EMAIL_MAIL_FAILED === $outcome ) {
			wp_send_json_error( array( 'message' => __( 'Could not send the payment email. Check that this site can send mail.', 'ifthenpay-pay-button' ) ), 500 );
		}

		wp_send_json_success(
			array(
				/* translators: %s: customer email address */
				'message' => sprintf( __( 'Payment email sent to %s.', 'ifthenpay-pay-button' ), $record['customer_email'] ),
			)
		);
	}

	/**
	 * The bulk "Send payment email", for one chunk of refs. I stop taking refs once the time budget is spent;
	 * entries.js sends what I didn't get to again. Only the last chunk asks for the re-rendered view.
	 */
	public function send_payment_emails(): void {
		$this->guard();

		$refs    = $this->bulk_refs();
		$started = microtime( true );
		$result  = array(
			'sent'    => array(),
			'skipped' => array(),
			'failed'  => array(),
		);

		$this->transactions->prime( $refs );

		foreach ( $refs as $ref ) {
			if ( microtime( true ) - $started > self::EMAIL_TIME_BUDGET ) {
				break;
			}

			$outcome = $this->send_payment_email( $this->transactions->get( $ref ) );

			if ( self::EMAIL_SENT === $outcome ) {
				$result['sent'][] = $ref;
			} elseif ( self::EMAIL_INELIGIBLE === $outcome ) {
				$result['skipped'][] = $ref;
			} else {
				$result['failed'][] = $ref;
			}
		}

		if ( '' !== $this->post_text( 'render' ) ) {
			$result += $this->page->rendered_view( $this->page->view_state()->from_post() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * The bulk "Resend receipt", for one chunk of refs, answering like send_payment_emails(). It only ever mails the payer.
	 */
	public function resend_receipts(): void {
		$this->guard();

		$refs     = $this->bulk_refs();
		$started  = microtime( true );
		$receipts = new ReceiptEmail( $this->transactions );
		$result   = array(
			'sent'    => array(),
			'skipped' => array(),
			'failed'  => array(),
		);

		$this->transactions->prime( $refs );

		foreach ( $refs as $ref ) {
			if ( microtime( true ) - $started > self::EMAIL_TIME_BUDGET ) {
				break;
			}

			$outcome = $receipts->resend_to_payer( $this->transactions->get( $ref ) );

			if ( self::EMAIL_SENT === $outcome ) {
				$result['sent'][] = $ref;
			} elseif ( self::EMAIL_INELIGIBLE === $outcome ) {
				$result['skipped'][] = $ref;
			} else {
				$result['failed'][] = $ref;
			}
		}

		if ( '' !== $this->post_text( 'render' ) ) {
			$result += $this->page->rendered_view( $this->page->view_state()->from_post() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Admin-post: streams the current view (or just the selected refs) as a CSV, XLS or JSON download.
	 */
	public function export(): void {
		check_admin_referer( EntriesPage::EXPORT_ACTION );

		if ( ! $this->page->can_access() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() verified the nonce; from_source() sanitizes every key.
		$view = $this->page->view_state()->from_source( $_REQUEST );
		$refs = $this->refs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() verified the nonce.
		$format = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : '';
		$format = in_array( $format, array( EntriesPage::EXPORT_JSON, EntriesPage::EXPORT_XLS, EntriesPage::EXPORT_ACCOUNTING ), true ) ? $format : EntriesPage::EXPORT_CSV;
		$args   = ViewState::store_filters( $view ) + array(
			'status'  => $view['status'],
			'orderby' => $view['orderby'],
			'order'   => $view['order'],
		);

		// A selection exports exactly those rows, whatever the filters say.
		if ( array() !== $refs ) {
			$args = array(
				'refs'    => $refs,
				'orderby' => $view['orderby'],
				'order'   => $view['order'],
			);
		}

		// Accounting is paid only, and the one format that needs the NIF out of each snapshot.
		if ( EntriesPage::EXPORT_ACCOUNTING === $format ) {
			$args['status']        = TransactionStore::STATUS_PAID;
			$args['with_snapshot'] = true;
		}

		nocache_headers();
		$types = array(
			EntriesPage::EXPORT_ACCOUNTING => 'text/csv',
			EntriesPage::EXPORT_JSON       => 'application/json',
			EntriesPage::EXPORT_XLS        => 'application/vnd.ms-excel',
			EntriesPage::EXPORT_CSV        => 'text/csv',
		);

		header( 'Content-Type: ' . $types[ $format ] . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ifthenpay-' . ( EntriesPage::EXPORT_ACCOUNTING === $format ? 'accounting' : 'entries' ) . '-' . Dates::local( 'Y-m-d' ) . '.' . ( EntriesPage::EXPORT_ACCOUNTING === $format ? 'csv' : $format ) . '"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming to the response body; WP_Filesystem has no stream API.
		$out = fopen( 'php://output', 'w' );

		if ( false === $out ) {
			exit;
		}

		if ( EntriesPage::EXPORT_JSON === $format ) {
			$this->stream_json( $out, $args );
		} elseif ( EntriesPage::EXPORT_XLS === $format ) {
			$this->stream_xls( $out, $args );
		} elseif ( EntriesPage::EXPORT_ACCOUNTING === $format ) {
			$this->stream_accounting( $out, $args );
		} else {
			$this->stream_csv( $out, $args );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see fopen() above.
		fclose( $out );
		exit;
	}

	/**
	 * Emails a pending payment its link, through the one sender every payment email uses.
	 *
	 * @param array<string, mixed>|null $record The transaction row, or null when the ref is gone.
	 * @phpstan-param Transaction|null $record
	 * @return string One of the EMAIL_* outcomes.
	 */
	private function send_payment_email( ?array $record ): string {
		return ( new PaymentEmailSender( $this->settings, $this->transactions, $this->links ) )->send( $record );
	}

	/**
	 * Writes the matching rows as CSV: a BOM so Excel reads UTF-8, and every cell guarded against formula injection.
	 *
	 * @param resource             $out  The open php://output handle.
	 * @param array<string, mixed> $args TransactionStore::iterate() filters.
	 */
	private function stream_csv( $out, array $args ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, "\xEF\xBB\xBF" );

		$this->put_csv_row( $out, $this->export_headers() );

		foreach ( $this->transactions->iterate( $args ) as $record ) {
			$this->put_csv_row( $out, $this->export_cells( $record ) );
		}
	}

	/**
	 * Writes the paid rows as an accountant's CSV: semicolons, decimal commas and d/m/Y dates, the way Portuguese
	 * Excel opens it, with the NIF the payer gave and the button the payment was for.
	 *
	 * @param resource             $out  The open php://output handle.
	 * @param array<string, mixed> $args TransactionStore::iterate() filters.
	 */
	private function stream_accounting( $out, array $args ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, "\xEF\xBB\xBF" );

		$this->put_csv_row(
			$out,
			array(
				__( 'Date paid', 'ifthenpay-pay-button' ),
				__( 'Reference', 'ifthenpay-pay-button' ),
				__( 'Description', 'ifthenpay-pay-button' ),
				__( 'Customer name', 'ifthenpay-pay-button' ),
				__( 'Tax number (NIF)', 'ifthenpay-pay-button' ),
				__( 'Customer email', 'ifthenpay-pay-button' ),
				__( 'Method', 'ifthenpay-pay-button' ),
				__( 'Amount (EUR)', 'ifthenpay-pay-button' ),
				__( 'Transaction ID', 'ifthenpay-pay-button' ),
			),
			';'
		);

		$titles = $this->page->button_titles();

		foreach ( $this->transactions->iterate( $args ) as $record ) {
			$nif = TransactionStore::decode_snapshot( $record['field_snapshot'] )['nif'] ?? '';
			$nif = is_array( $nif ) && isset( $nif['value'] ) && is_scalar( $nif['value'] ) ? trim( (string) $nif['value'] ) : '';

			$this->put_csv_row(
				$out,
				array(
					// The paid date is when the status last moved, which for a paid row is the confirmation.
					Dates::local( 'd/m/Y', $record['updated_at'] ),
					$record['ref'],
					$titles[ $record['button_id'] ] ?? '',
					$record['customer_name'],
					$nif,
					$record['customer_email'],
					$record['pay_method'],
					number_format( $record['amount'], 2, ',', '' ),
					$record['transaction_id'],
				),
				';'
			);
		}
	}

	/**
	 * Writes the matching rows as an Excel 2003 XML spreadsheet, which Excel opens as a .xls with no library.
	 *
	 * @param resource             $out  The open php://output handle.
	 * @param array<string, mixed> $args TransactionStore::iterate() filters.
	 */
	private function stream_xls( $out, array $args ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<?mso-application progid=\"Excel.Sheet\"?>\n" . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="h"><Font ss:Bold="1"/></Style></Styles><Worksheet ss:Name="' . esc_attr( __( 'Entries', 'ifthenpay-pay-button' ) ) . '"><Table>' );

		$this->put_xls_row( $out, $this->export_headers(), 'h' );

		foreach ( $this->transactions->iterate( $args ) as $record ) {
			$this->put_xls_row( $out, $this->export_cells( $record ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, '</Table></Worksheet></Workbook>' );
	}

	/**
	 * Writes one spreadsheet row. Every cell is typed String, so Excel never evaluates a would-be formula.
	 *
	 * @param resource           $out   The open php://output handle.
	 * @param array<int, string> $cells The row's cells, in column order.
	 * @param string             $style Optional style ID.
	 */
	private function put_xls_row( $out, array $cells, string $style = '' ): void {
		$xml = '<Row>';

		foreach ( $cells as $cell ) {
			// XML 1.0 can't carry control characters, so I drop them rather than corrupt the file.
			$clean = (string) preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $cell );
			$xml  .= '<Cell' . ( '' !== $style ? ' ss:StyleID="' . $style . '"' : '' ) . '><Data ss:Type="String">' . esc_xml( $clean ) . '</Data></Cell>';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, $xml . '</Row>' );
	}

	/**
	 * The CSV and XLS column headings.
	 *
	 * @return array<int, string>
	 */
	private function export_headers(): array {
		return array(
			__( 'Reference', 'ifthenpay-pay-button' ),
			__( 'Customer name', 'ifthenpay-pay-button' ),
			__( 'Customer email', 'ifthenpay-pay-button' ),
			__( 'Button ID', 'ifthenpay-pay-button' ),
			__( 'External reference', 'ifthenpay-pay-button' ),
			__( 'Method', 'ifthenpay-pay-button' ),
			__( 'Amount', 'ifthenpay-pay-button' ),
			__( 'Status', 'ifthenpay-pay-button' ),
			__( 'Created', 'ifthenpay-pay-button' ),
			__( 'Last updated', 'ifthenpay-pay-button' ),
			__( 'Request ID', 'ifthenpay-pay-button' ),
			__( 'Transaction ID', 'ifthenpay-pay-button' ),
			__( 'Payment link', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * One transaction as the CSV and XLS cells, in header order.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 * @return array<int, string>
	 */
	private function export_cells( array $record ): array {
		return array(
			$record['ref'],
			$record['customer_name'],
			$record['customer_email'],
			$record['button_id'] > 0 ? (string) $record['button_id'] : '',
			$record['external_ref'],
			$record['pay_method'],
			number_format( $record['amount'], 2, '.', '' ),
			$this->page->presenter()->status_label( $record['status'] ),
			Dates::local( 'Y-m-d H:i:s', $record['created_at'] ),
			Dates::local( 'Y-m-d H:i:s', $record['updated_at'] ),
			$record['request_id'],
			$record['transaction_id'],
			$record['payment_url'],
		);
	}

	/**
	 * Writes the matching rows as a JSON array, one object per line, with stable English keys and raw values.
	 *
	 * @param resource             $out  The open php://output handle.
	 * @param array<string, mixed> $args TransactionStore::iterate() filters.
	 */
	private function stream_json( $out, array $args ): void {
		$first     = true;
		$presenter = $this->page->presenter();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, '[' );

		foreach ( $this->transactions->iterate( $args ) as $record ) {
			$json = wp_json_encode(
				array(
					'reference'      => $record['ref'],
					'customer_name'  => $record['customer_name'],
					'customer_email' => $record['customer_email'],
					'button_id'      => $record['button_id'],
					'external_ref'   => $record['external_ref'],
					'method'         => $record['pay_method'],
					'amount'         => round( $record['amount'], 2 ),
					'status'         => $record['status'],
					'status_label'   => $presenter->status_label( $record['status'] ),
					'created_at'     => Dates::local( 'c', $record['created_at'] ),
					'updated_at'     => Dates::local( 'c', $record['updated_at'] ),
					'request_id'     => $record['request_id'],
					'transaction_id' => $record['transaction_id'],
					'payment_url'    => $record['payment_url'],
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
			);

			if ( false === $json ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
			fwrite( $out, ( $first ? '' : ',' ) . "\n  " . $json );
			$first = false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- see export()'s fopen().
		fwrite( $out, ( $first ? '' : "\n" ) . "]\n" );
	}

	/**
	 * Writes one CSV line. A leading quote makes Excel/Sheets treat a would-be formula as text.
	 *
	 * @param resource           $out   The open php://output handle.
	 * @param array<int, string> $cells     The row's cells, in column order.
	 * @param string             $separator The field separator.
	 */
	private function put_csv_row( $out, array $cells, string $separator = ',' ): void {
		$safe = array_map(
			static fn( string $cell ): string => ( '' !== $cell && false !== strpos( "=+-@\t\r", $cell[0] ) ) ? "'" . $cell : $cell,
			$cells
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- see export()'s fopen().
		fputcsv( $out, $safe, $separator, '"', '' );
	}

	/**
	 * The selected refs, sent as one JSON-encoded `refs` field.
	 *
	 * @return array<int, string>
	 */
	private function refs(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- callers verify the nonce; each decoded ref is sanitized below, since sanitizing the JSON could corrupt it.
		$raw  = isset( $_POST['refs'] ) && is_string( $_POST['refs'] ) ? wp_unslash( $_POST['refs'] ) : '';
		$refs = json_decode( $raw, true );

		if ( ! is_array( $refs ) ) {
			return array();
		}

		$refs = array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $refs, 'is_scalar' ) ) );

		return array_values( array_unique( array_filter( $refs, static fn( string $ref ): bool => '' !== $ref ) ) );
	}

	/**
	 * The bulk actions' refs, refusing an oversized batch.
	 *
	 * @return array<int, string>
	 */
	private function bulk_refs(): array {
		$refs = $this->refs();

		if ( count( $refs ) > self::BULK_MAX_REFS ) {
			wp_send_json_error(
				array(
					/* translators: %s: maximum entries per request */
					'message' => sprintf( __( 'Too many entries in one request (the limit is %s).', 'ifthenpay-pay-button' ), number_format_i18n( self::BULK_MAX_REFS ) ),
				),
				400
			);
		}

		return $refs;
	}

	/**
	 * A single-line POST value.
	 *
	 * @param string $key      The POST key.
	 * @param string $fallback Returned when the key is missing or not a string.
	 */
	private function post_text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- every handler runs guard() first.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $fallback;
	}

	/**
	 * A POST array of keys, e.g. `hidden[]`.
	 *
	 * @param string $key The POST key.
	 * @return array<int, string>
	 */
	private function post_keys( string $key ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every handler runs guard() first; each value goes through sanitize_key() right here.
		$raw = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();

		return array_values( array_map( 'sanitize_key', array_filter( $raw, 'is_string' ) ) );
	}

	/**
	 * Verifies the nonce, the admin capability and the connection.
	 */
	private function guard(): void {
		check_ajax_referer( EntriesPage::NONCE_ACTION, 'nonce' );

		if ( ! $this->page->can_access() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'ifthenpay-pay-button' ) ), 403 );
		}
	}
}
