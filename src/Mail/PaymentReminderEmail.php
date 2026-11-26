<?php
/**
 * The reminder to a payer whose payment link is about to run out.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Mail;

use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One short email with the amount, when the link ends and a button to finish paying.
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
final class PaymentReminderEmail {

	/**
	 * Static-only helper.
	 */
	private function __construct() {
	}

	/**
	 * Mails the reminder, in the site's language.
	 *
	 * @param array<string, mixed> $record  The pending payment.
	 * @param int                  $ends_at When its link stops working.
	 * @phpstan-param Transaction $record
	 */
	public static function send( array $record, int $ends_at ): bool {
		$switched = switch_to_locale( get_locale() );
		$site     = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$headers  = array( 'Content-Type: text/html; charset=UTF-8' );
		$reply    = (string) get_option( 'admin_email' );

		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}

		$sent = wp_mail(
			$record['customer_email'],
			/* translators: %s: the site name */
			sprintf( __( 'Your payment to %s is still waiting', 'ifthenpay-pay-button' ), $site ),
			self::render( $record, $ends_at ),
			$headers
		);

		if ( $switched ) {
			restore_previous_locale();
		}

		return $sent;
	}

	/**
	 * The email body.
	 *
	 * @param array<string, mixed> $record  The pending payment.
	 * @param int                  $ends_at When its link stops working.
	 * @phpstan-param Transaction $record
	 */
	public static function render( array $record, int $ends_at ): string {
		$greeting = '' !== $record['customer_name']
			/* translators: %s: the payer's name */
			? sprintf( __( 'Hi %s,', 'ifthenpay-pay-button' ), $record['customer_name'] )
			: __( 'Hi,', 'ifthenpay-pay-button' );
		$ends = Dates::local( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $ends_at );

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
						<td style="font-size:22px;font-weight:700;padding-bottom:6px;"><?php esc_html_e( 'Your payment is still waiting', 'ifthenpay-pay-button' ); ?></td>
					</tr>
					<tr>
						<td style="font-size:15px;line-height:1.5;color:#2f3a50;padding-bottom:20px;"><?php echo esc_html( $greeting ); ?> <?php esc_html_e( 'you started a payment and it isn\'t finished yet. You can still complete it with the button below.', 'ifthenpay-pay-button' ); ?></td>
					</tr>
					<tr>
						<td style="font-size:34px;font-weight:750;letter-spacing:-0.02em;padding-bottom:22px;"><?php echo esc_html( CheckoutService::format_money( $record['amount'] ) ); ?></td>
					</tr>
					<tr>
						<td>
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f4f8;border-radius:12px;font-size:14px;">
								<tr>
									<td style="padding:9px 14px;color:#66708a;"><?php esc_html_e( 'Reference', 'ifthenpay-pay-button' ); ?></td>
									<td style="padding:9px 14px;font-weight:600;text-align:right;"><?php echo esc_html( $record['ref'] ); ?></td>
								</tr>
								<tr>
									<td style="padding:9px 14px;color:#66708a;"><?php esc_html_e( 'Pay before', 'ifthenpay-pay-button' ); ?></td>
									<td style="padding:9px 14px;font-weight:600;text-align:right;"><?php echo esc_html( $ends ); ?></td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td style="padding-top:22px;">
							<a href="<?php echo esc_url( $record['payment_url'] ); ?>" style="display:inline-block;background:#00609c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 20px;border-radius:10px;"><?php esc_html_e( 'Pay now', 'ifthenpay-pay-button' ); ?></a>
						</td>
					</tr>
					<tr>
						<td style="padding-top:26px;font-size:12px;color:#98a1b5;"><?php esc_html_e( 'If you already paid or no longer want to, you can ignore this email. Payment processed securely by ifthenpay.', 'ifthenpay-pay-button' ); ?></td>
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
