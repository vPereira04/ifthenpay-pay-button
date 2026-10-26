<?php
/**
 * The weekly summary email for the site's admin.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Mail;

use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The last 7 days at a glance: revenue and its change, payments, average, the top methods and customers,
 * and the money still waiting. Same look as the admin screens and the payment email; inline styles only.
 *
 * @phpstan-import-type Kpi from Insights
 */
class WeeklySummaryEmail {

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the email.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?TransactionStore $transactions = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Builds and sends it, in the site's language.
	 *
	 * @param string $to The recipient.
	 */
	public function send( string $to ): bool {
		if ( false === is_email( $to ) ) {
			return false;
		}

		$switched = switch_to_locale( get_locale() );
		$email    = $this->render();

		if ( $switched ) {
			restore_previous_locale();
		}

		return wp_mail( $to, $email['subject'], $email['body'], array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * The subject and HTML body.
	 *
	 * @return array{subject: string, body: string}
	 */
	public function render(): array {
		$period    = ( new Insights( $this->transactions ) )->period( '7d' );
		$revenue   = $period['kpis']['revenue'];
		$waiting   = $this->transactions->waiting();
		$customers = $this->transactions->top_customers( time() - 7 * DAY_IN_SECONDS, time() + 1, 3 );
		$site      = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

		/* translators: 1: site name, 2: revenue, e.g. "1.234,56 €", 3: change, e.g. "▲ 12.4%" */
		$subject = sprintf( __( '%1$s · your ifthenpay week: %2$s (%3$s)', 'ifthenpay-pay-button' ), $site, $revenue['display'], self::delta_text( $revenue ) );

		$body = '<div style="margin:0;padding:40px 12px;background-color:#eef1f6;font-family:-apple-system,BlinkMacSystemFont,&apos;Segoe UI&apos;,Inter,Helvetica,Arial,sans-serif;color:#2f3a50">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;margin:0 auto;border-collapse:separate">'
			. '<tr><td align="center" style="padding:0 0 22px"><img src="https://ifthenpay.com/docs/en/assets/images/ifthenpay.png" alt="ifthenpay" width="112" style="display:block;max-width:100%;height:auto;border:0"></td></tr>'
			. '<tr><td style="background-color:#fafbfd;border:1px solid #e2e6ee;border-top:4px solid #00609c;border-radius:20px;padding:32px">'
			. '<p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.7px;text-transform:uppercase;color:#66708a">' . esc_html__( 'Your week', 'ifthenpay-pay-button' ) . ' · ' . esc_html( $site ) . '</p>'
			. '<p style="margin:10px 0 2px;font-size:13px;color:#66708a">' . esc_html__( 'Revenue, last 7 days', 'ifthenpay-pay-button' ) . '</p>'
			. '<p style="margin:0;font-size:44px;line-height:1.1;font-weight:800;letter-spacing:-1.5px;color:#121a2c">' . esc_html( $revenue['display'] ) . '</p>'
			. '<p style="margin:10px 0 26px">' . self::delta_pill( $revenue ) . ' <span style="font-size:12px;color:#98a1b5">' . esc_html( $period['compare'] ) . '</span></p>'
			. $this->kpi_row( $period['kpis'] )
			. $this->methods_block( $period['methods'] )
			. $this->customers_block( $customers )
			. $this->waiting_block( $waiting['count'], $waiting['amount'] )
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:28px 0 0;border-collapse:separate"><tr><td align="center" bgcolor="#00609c" style="background-color:#00609c;border-radius:12px">'
			. '<a href="' . esc_url( EntriesPage::list_url() ) . '" style="display:block;padding:15px 24px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px">' . esc_html__( 'Open your entries', 'ifthenpay-pay-button' ) . ' &rarr;</a>'
			. '</td></tr></table>'
			. '</td></tr>'
			. '<tr><td style="padding:22px 8px 0;font-size:12px;line-height:1.8;color:#98a1b5;text-align:center">'
			. esc_html__( 'You get this every Monday because the weekly summary is on in ifthenpay → Gateway Configuration.', 'ifthenpay-pay-button' )
			. '</td></tr>'
			. '</table></div>';

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Paid payments and the average payment, side by side.
	 *
	 * @param array<string, array<string, mixed>> $kpis The period's KPIs.
	 */
	private function kpi_row( array $kpis ): string {
		$cells = '';

		foreach ( array( 'paid', 'average' ) as $key ) {
			$kpi    = $kpis[ $key ];
			$cells .= '<td width="50%" style="padding:16px;background-color:#f2f4f8;border-radius:14px;vertical-align:top">'
				. '<p style="margin:0;font-size:12px;color:#66708a">' . esc_html( (string) $kpi['label'] ) . '</p>'
				. '<p style="margin:4px 0 8px;font-size:22px;font-weight:800;color:#121a2c">' . esc_html( (string) $kpi['display'] ) . '</p>'
				. self::delta_pill( $kpi )
				. '</td>';

			if ( 'paid' === $key ) {
				$cells .= '<td width="12" style="font-size:0">&nbsp;</td>';
			}
		}

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:separate"><tr>' . $cells . '</tr></table>';
	}

	/**
	 * The top three methods with a bar each (one colour, the share of revenue as its length).
	 *
	 * @param array<int, array<string, mixed>> $methods The period's methods.
	 */
	private function methods_block( array $methods ): string {
		if ( array() === $methods ) {
			return '';
		}

		$rows = '';

		foreach ( array_slice( $methods, 0, 3 ) as $method ) {
			$share = max( 2, (int) round( (float) $method['share'] ) );
			$rows .= '<tr><td style="padding:8px 0 2px;font-size:14px;font-weight:600;color:#121a2c">' . esc_html( (string) $method['label'] ) . '</td>'
				. '<td align="right" style="padding:8px 0 2px;font-size:14px;font-weight:700;color:#121a2c">' . esc_html( (string) $method['kpi']['display'] ) . ' <span style="font-weight:500;color:#98a1b5">· ' . esc_html( number_format_i18n( (float) $method['share'], 1 ) ) . '%</span></td></tr>'
				. '<tr><td colspan="2" style="padding:0 0 6px"><table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse"><tr>'
				. '<td width="' . $share . '%" style="height:8px;background-color:#00609c;border-radius:4px;font-size:0;line-height:0">&nbsp;</td>'
				. '<td style="height:8px;background-color:#e6ebf2;border-radius:4px;font-size:0;line-height:0">&nbsp;</td>'
				. '</tr></table></td></tr>';
		}

		return self::section( __( 'Revenue by method', 'ifthenpay-pay-button' ), '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>' );
	}

	/**
	 * The customers who paid the most this week.
	 *
	 * @param array<int, array{name: string, email: string, count: int, amount: float}> $customers Top customers.
	 */
	private function customers_block( array $customers ): string {
		if ( array() === $customers ) {
			return '';
		}

		$rows = '';

		foreach ( $customers as $i => $customer ) {
			$border = $i > 0 ? 'border-top:1px solid #e2e6ee;' : '';
			$name   = '' !== $customer['name'] ? $customer['name'] : $customer['email'];
			/* translators: %s: number of payments */
			$count = sprintf( _n( '%s payment', '%s payments', $customer['count'], 'ifthenpay-pay-button' ), number_format_i18n( $customer['count'] ) );
			$rows .= '<tr><td style="padding:10px 0;' . $border . 'font-size:14px;color:#121a2c;font-weight:600">' . esc_html( $name ) . '<br><span style="font-size:12px;font-weight:400;color:#98a1b5">' . esc_html( $count ) . '</span></td>'
				. '<td align="right" style="padding:10px 0;' . $border . 'font-size:14px;font-weight:700;color:#0b6b35">' . esc_html( number_format_i18n( $customer['amount'], 2 ) . ' €' ) . '</td></tr>';
		}

		return self::section( __( 'Top customers', 'ifthenpay-pay-button' ), '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>' );
	}

	/**
	 * The money still waiting, in the pending amber.
	 *
	 * @param int   $count  Pending payments.
	 * @param float $amount Their total.
	 */
	private function waiting_block( int $count, float $amount ): string {
		if ( 0 === $count ) {
			return '';
		}

		/* translators: %s: number of payments */
		$text = sprintf( _n( '%s payment is still waiting to be paid.', '%s payments are still waiting to be paid.', $count, 'ifthenpay-pay-button' ), number_format_i18n( $count ) );

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:24px 0 0;border-collapse:separate"><tr><td style="padding:16px 18px;background-color:#fff4de;border-radius:14px">'
			. '<p style="margin:0;font-size:12px;font-weight:700;letter-spacing:0.7px;text-transform:uppercase;color:#8a5300">' . esc_html__( 'Money waiting', 'ifthenpay-pay-button' ) . '</p>'
			. '<p style="margin:4px 0 2px;font-size:24px;font-weight:800;color:#8a5300">' . esc_html( number_format_i18n( $amount, 2 ) . ' €' ) . '</p>'
			. '<p style="margin:0;font-size:13px;color:#8a5300">' . esc_html( $text ) . '</p>'
			. '</td></tr></table>';
	}

	/**
	 * A titled block.
	 *
	 * @param string $title   The section title.
	 * @param string $content Its HTML, already escaped.
	 */
	private static function section( string $title, string $content ): string {
		return '<p style="margin:26px 0 8px;font-size:12px;font-weight:700;letter-spacing:0.7px;text-transform:uppercase;color:#66708a">' . esc_html( $title ) . '</p>' . $content;
	}

	/**
	 * "▲ 12.4%" / "▼ 3.1%" / "New", for the subject line.
	 *
	 * @param array<string, mixed> $kpi A KPI.
	 */
	private static function delta_text( array $kpi ): string {
		if ( 'new' === $kpi['trend'] ) {
			return __( 'New', 'ifthenpay-pay-button' );
		}

		$arrow = array(
			'up'   => '▲',
			'down' => '▼',
		)[ $kpi['trend'] ] ?? '•';

		return $arrow . ' ' . number_format_i18n( abs( (float) $kpi['delta'] ), 1 ) . '%';
	}

	/**
	 * The change as a coloured pill: green up, red down, grey flat.
	 *
	 * @param array<string, mixed> $kpi A KPI.
	 */
	private static function delta_pill( array $kpi ): string {
		$tones = array(
			'up'   => array( '#e4f6ea', '#0b6b35' ),
			'down' => array( '#fdeaea', '#a3201f' ),
			'new'  => array( '#e6f1f9', '#003e63' ),
		);
		$tone  = $tones[ $kpi['trend'] ] ?? array( '#eceff3', '#4f5869' );

		return '<span style="display:inline-block;padding:3px 9px;border-radius:999px;background-color:' . $tone[0] . ';color:' . $tone[1] . ';font-size:12px;font-weight:700">' . esc_html( self::delta_text( $kpi ) ) . '</span>';
	}
}
