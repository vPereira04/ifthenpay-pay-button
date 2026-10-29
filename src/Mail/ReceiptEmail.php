<?php
/**
 * Paid receipts: one for the payer, one for the site owner.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Mail;

use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends both emails once ifthenpay confirms a payment. Only then: a payer coming back to the site proves nothing.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class ReceiptEmail {

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Checkout settings.
	 *
	 * @var CheckoutSettings
	 */
	private CheckoutSettings $settings;

	/**
	 * Sets up the sender.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 * @param CheckoutSettings|null $settings     Checkout settings.
	 */
	public function __construct( ?TransactionStore $transactions = null, ?CheckoutSettings $settings = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
		$this->settings     = $settings ?? new CheckoutSettings();
	}

	/**
	 * Hooks the paid event.
	 */
	public function register(): void {
		add_action( 'iftp_btn_payment_paid', array( $this, 'send' ) );
	}

	/**
	 * Sends whichever of the two emails is switched on.
	 *
	 * @param string $ref The transaction reference.
	 */
	public function send( string $ref ): void {
		$record = $this->transactions->get( $ref );

		if ( null === $record ) {
			return;
		}

		$switched = switch_to_locale( get_locale() );
		$pairs    = $this->transactions->entry_data_pairs( $record );
		$headers  = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( $this->settings->get_receipt_payer() && is_email( $record['customer_email'] ) ) {
			$this->mail_payer( $record, $pairs );
		}

		if ( $this->settings->get_notify_admin() ) {
			wp_mail(
				$this->settings->get_notify_email(),
				/* translators: 1: the amount, 2: the payer's name or email */
				sprintf( __( 'New payment: %1$s from %2$s', 'ifthenpay-pay-button' ), CheckoutService::format_money( $record['amount'] ), '' !== $record['customer_name'] ? $record['customer_name'] : ( '' !== $record['customer_email'] ? $record['customer_email'] : __( 'a payer', 'ifthenpay-pay-button' ) ) ),
				self::render( __( 'New payment', 'ifthenpay-pay-button' ), __( 'ifthenpay confirmed this payment.', 'ifthenpay-pay-button' ), $record, $pairs, EntriesPage::search_url( $record['ref'] ) ),
				$headers
			);
		}

		if ( $switched ) {
			restore_previous_locale();
		}
	}

	/**
	 * Whether a row is a paid payment with a valid customer email to send a receipt to.
	 *
	 * @param array<string, mixed> $record A transaction row.
	 * @phpstan-param Transaction $record
	 */
	public static function can_resend( array $record ): bool {
		return TransactionStore::STATUS_PAID === $record['status'] && false !== is_email( $record['customer_email'] );
	}

	/**
	 * Sends the payer's receipt again, on an admin's say-so. The "Receipt to the payer" setting doesn't apply here,
	 * and I never send the owner's notice a second time.
	 *
	 * @param array<string, mixed>|null $record The transaction row, or null when the ref is gone.
	 * @phpstan-param Transaction|null $record
	 * @return string PaymentEmailSender::SENT, INELIGIBLE or MAIL_FAILED.
	 */
	public function resend_to_payer( ?array $record ): string {
		if ( null === $record || ! self::can_resend( $record ) ) {
			return PaymentEmailSender::INELIGIBLE;
		}

		$switched = switch_to_locale( get_locale() );
		$sent     = $this->mail_payer( $record, $this->transactions->entry_data_pairs( $record ) );

		if ( $switched ) {
			restore_previous_locale();
		}

		return $sent ? PaymentEmailSender::SENT : PaymentEmailSender::MAIL_FAILED;
	}

	/**
	 * Mails the payer's receipt.
	 *
	 * @param array<string, mixed>                            $record The transaction row.
	 * @param array<int, array{label: string, value: string}> $pairs  The submitted details.
	 * @phpstan-param Transaction $record
	 */
	private function mail_payer( array $record, array $pairs ): bool {
		$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply   = (string) get_option( 'admin_email' );

		return wp_mail(
			$record['customer_email'],
			/* translators: %s: the site name */
			sprintf( __( 'Your payment to %s', 'ifthenpay-pay-button' ), $site ),
			self::render(
				__( 'Payment received', 'ifthenpay-pay-button' ),
				'' !== $record['customer_name']
					/* translators: %s: the payer's name */
					? sprintf( __( 'Hi %s, thank you. Your payment was received.', 'ifthenpay-pay-button' ), $record['customer_name'] )
					: __( 'Thank you. Your payment was received.', 'ifthenpay-pay-button' ),
				$record,
				$pairs,
				''
			),
			is_email( $reply ) ? array_merge( $headers, array( 'Reply-To: ' . $reply ) ) : $headers
		);
	}

	/**
	 * The email body: a card with the amount, the details table and, for the owner, a link to the payment.
	 *
	 * @param string                                          $heading The heading.
	 * @param string                                          $intro   The line under it.
	 * @param array<string, mixed>                            $record  The transaction row.
	 * @param array<int, array{label: string, value: string}> $pairs   The submitted details.
	 * @param string                                          $link    The admin link, or '' for the payer.
	 * @phpstan-param Transaction $record
	 */
	public static function render( string $heading, string $intro, array $record, array $pairs, string $link ): string {
		$rows = array_merge(
			array(
				array(
					'label' => __( 'Reference', 'ifthenpay-pay-button' ),
					'value' => $record['ref'],
				),
				array(
					'label' => __( 'Method', 'ifthenpay-pay-button' ),
					'value' => $record['pay_method'],
				),
				array(
					'label' => __( 'Date', 'ifthenpay-pay-button' ),
					'value' => Dates::local( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $record['updated_at'] ),
				),
			),
			'' !== $record['external_ref'] ? array(
				array(
					'label' => __( 'Your reference', 'ifthenpay-pay-button' ),
					'value' => $record['external_ref'],
				),
			) : array(),
			$pairs
		);

		ob_start();
		?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#eef1f6;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;padding:32px 12px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#121a2c;">
		<tr>
			<td align="center">
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fdfdfe;border-radius:18px;padding:32px 28px;">
					<tr>
						<td style="font-size:13px;font-weight:600;color:#66708a;padding-bottom:18px;"><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></td>
					</tr>
					<tr>
						<td style="font-size:22px;font-weight:700;padding-bottom:6px;"><?php echo esc_html( $heading ); ?></td>
					</tr>
					<tr>
						<td style="font-size:15px;line-height:1.5;color:#2f3a50;padding-bottom:20px;"><?php echo esc_html( $intro ); ?></td>
					</tr>
					<tr>
						<td style="font-size:34px;font-weight:750;letter-spacing:-0.02em;padding-bottom:22px;"><?php echo esc_html( CheckoutService::format_money( $record['amount'] ) ); ?></td>
					</tr>
					<tr>
						<td>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;border-radius:12px;font-size:14px;">
								<?php foreach ( $rows as $row ) : ?>
									<?php
									if ( '' === $row['value'] ) {
										continue;
									}
									?>
									<tr>
										<td style="padding:9px 14px;color:#66708a;vertical-align:top;"><?php echo esc_html( $row['label'] ); ?></td>
										<td style="padding:9px 14px;font-weight:600;text-align:right;word-break:break-word;"><?php echo nl2br( esc_html( $row['value'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</table>
						</td>
					</tr>
					<?php if ( '' !== $link ) : ?>
						<tr>
							<td style="padding-top:22px;">
								<a href="<?php echo esc_url( $link ); ?>" style="display:inline-block;background:#00609c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 20px;border-radius:10px;"><?php esc_html_e( 'Open the payment', 'ifthenpay-pay-button' ); ?></a>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<td style="padding-top:26px;font-size:12px;color:#98a1b5;"><?php esc_html_e( 'Payment processed securely by ifthenpay.', 'ifthenpay-pay-button' ); ?></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
