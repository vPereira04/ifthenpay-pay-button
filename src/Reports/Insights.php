<?php
/**
 * Payment insights: revenue series, KPIs and their change against the previous period.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Reports;

use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the charts show, computed from one grouped query per period. Each period is compared with the
 * one right before it, of the same length: the last 7 days against the 7 before them, and so on.
 *
 * @phpstan-type Kpi array{key: string, label: string, value: float, display: string, delta: float|null, trend: string}
 * @phpstan-type Period array{key: string, label: string, compare: string, series: array{labels: array<int, string>, tooltips: array<int, string>, values: array<int, float>, averages: array<int, float>}, kpis: array<string, Kpi>, split: array<string, int>, methods: array<int, MethodShare>}
 * @phpstan-type MethodShare array{label: string, amount: float, count: int, share: float, kpi: Kpi}
 */
class Insights {

	public const PERIODS = array( '7d', '30d', '12m' );

	private const SECONDS_PER_DAY = 86400;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Built periods, per request.
	 *
	 * @var array<string, Period>
	 */
	private array $memo = array();

	/**
	 * Sets up the reports.
	 *
	 * @param TransactionStore|null $transactions Transactions storage.
	 */
	public function __construct( ?TransactionStore $transactions = null ) {
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Every period, keyed by its PERIODS key.
	 *
	 * @return array<string, Period>
	 */
	public function all(): array {
		$periods = array();

		foreach ( self::PERIODS as $key ) {
			$periods[ $key ] = $this->period( $key );
		}

		return $periods;
	}

	/**
	 * One period: its revenue series, KPIs with their change, and the status split.
	 *
	 * @param string $key One of PERIODS.
	 * @return Period
	 */
	public function period( string $key ): array {
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$offset = wp_timezone()->getOffset( new \DateTimeImmutable( 'now' ) );
		$today  = intdiv( time() + $offset, self::SECONDS_PER_DAY );

		if ( '12m' === $key ) {
			$buckets = $this->month_buckets( 24 );
		} else {
			$buckets = $this->day_buckets( $today, '7d' === $key ? 14 : 60 );
		}

		$first = $buckets[0]['from'];
		$last  = $buckets[ count( $buckets ) - 1 ]['to'];
		$rows  = $this->transactions->daily_totals( $first * self::SECONDS_PER_DAY - $offset, ( $last + 1 ) * self::SECONDS_PER_DAY - $offset, $offset );

		// The first half of the buckets is the previous period, the second half the current one.
		$half     = intdiv( count( $buckets ), 2 );
		$previous = array_slice( $buckets, 0, $half );
		$current  = array_slice( $buckets, $half );

		$now_from  = $current[0]['from'] * self::SECONDS_PER_DAY - $offset;
		$then_from = $first * self::SECONDS_PER_DAY - $offset;
		$now_to    = ( $last + 1 ) * self::SECONDS_PER_DAY - $offset;

		$now  = $this->totals( $current, $rows );
		$then = $this->totals( $previous, $rows );

		$this->memo[ $key ] = array(
			'key'     => $key,
			'label'   => self::period_label( $key ),
			'compare' => self::compare_label( $key ),
			'series'  => $this->series( $current, $rows, '12m' === $key ),
			'kpis'    => $this->kpis( $now, $then ),
			'split'   => $now['split'],
			'methods' => $this->methods( $now_from, $now_to, $then_from ),
		);

		return $this->memo[ $key ];
	}

	/**
	 * Paid revenue per payment method, biggest first: the top five, then the rest as "Other". Each with its
	 * share of the period's revenue and its change against the previous period.
	 *
	 * @param int $from      This period's start.
	 * @param int $to        This period's end.
	 * @param int $then_from The previous period's start (it ends where this one starts).
	 * @return array<int, MethodShare>
	 */
	private function methods( int $from, int $to, int $then_from ): array {
		$now   = $this->group_methods( $this->transactions->method_totals( $from, $to ) );
		$then  = $this->group_methods( $this->transactions->method_totals( $then_from, $from ) );
		$total = array_sum( array_column( $now, 'amount' ) );

		uasort( $now, static fn( array $a, array $b ): int => $b['amount'] <=> $a['amount'] );

		$top      = array_slice( $now, 0, 5, true );
		$top_then = array_intersect_key( $then, $top );

		// Everything past the top five folds into one "Other" row, so the bars never become a rainbow of tiny slivers.
		if ( count( $now ) > 5 ) {
			$rest              = array_slice( $now, 5, null, true );
			$top['other']      = array(
				'label'  => __( 'Other', 'ifthenpay-pay-button' ),
				'amount' => array_sum( array_column( $rest, 'amount' ) ),
				'count'  => array_sum( array_column( $rest, 'count' ) ),
			);
			$top_then['other'] = array(
				'label'  => '',
				'amount' => array_sum( array_column( array_diff_key( $then, $top_then ), 'amount' ) ),
				'count'  => 0,
			);
		}

		$shares = array();

		foreach ( $top as $method_key => $method ) {
			$shares[] = array(
				'label'  => $method['label'],
				'amount' => round( $method['amount'], 2 ),
				'count'  => $method['count'],
				'share'  => $total > 0 ? round( $method['amount'] / $total * 100, 1 ) : 0.0,
				'kpi'    => self::kpi( (string) $method_key, $method['label'], $method['amount'], $top_then[ $method_key ]['amount'] ?? 0.0, self::money( $method['amount'] ) ),
			);
		}

		return $shares;
	}

	/**
	 * Folds spellings of one method together ("MB WAY", "MBWAY", "mbway"), keeping the first name seen.
	 *
	 * @param array<int, array{method: string, count: int, amount: float}> $rows From TransactionStore::method_totals().
	 * @return array<string, array{label: string, amount: float, count: int}>
	 */
	private function group_methods( array $rows ): array {
		$grouped = array();

		foreach ( $rows as $row ) {
			$label = '' !== $row['method'] ? $row['method'] : __( 'Unknown', 'ifthenpay-pay-button' );
			$key   = (string) preg_replace( '/[^A-Z0-9]/', '', strtoupper( $label ) );

			$grouped[ $key ] = array(
				'label'  => $grouped[ $key ]['label'] ?? $label,
				'amount' => ( $grouped[ $key ]['amount'] ?? 0.0 ) + $row['amount'],
				'count'  => ( $grouped[ $key ]['count'] ?? 0 ) + $row['count'],
			);
		}

		return $grouped;
	}

	/**
	 * The last $count days as one-day buckets, oldest first.
	 *
	 * @param int $today The site's current day number.
	 * @param int $count How many days.
	 * @return array<int, array{from: int, to: int, start: int}>
	 */
	private function day_buckets( int $today, int $count ): array {
		$buckets = array();

		for ( $day = $today - $count + 1; $day <= $today; $day++ ) {
			$buckets[] = array(
				'from'  => $day,
				'to'    => $day,
				'start' => $day * self::SECONDS_PER_DAY,
			);
		}

		return $buckets;
	}

	/**
	 * The last $count calendar months (this one included) as day ranges, oldest first.
	 *
	 * @param int $count How many months.
	 * @return array<int, array{from: int, to: int, start: int}>
	 */
	private function month_buckets( int $count ): array {
		$month   = current_datetime()->modify( 'first day of this month' )->setTime( 0, 0 );
		$utc     = new \DateTimeZone( 'UTC' );
		$buckets = array();

		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$start = $month->modify( "-{$i} months" );
			$end   = $start->modify( 'last day of this month' );

			// Day numbers in the site's calendar, the same way daily_totals() counts them.
			$from = intdiv( ( new \DateTimeImmutable( $start->format( 'Y-m-d' ), $utc ) )->getTimestamp(), self::SECONDS_PER_DAY );
			$to   = intdiv( ( new \DateTimeImmutable( $end->format( 'Y-m-d' ), $utc ) )->getTimestamp(), self::SECONDS_PER_DAY );

			$buckets[] = array(
				'from'  => $from,
				'to'    => $to,
				'start' => $from * self::SECONDS_PER_DAY,
			);
		}

		return $buckets;
	}

	/**
	 * Paid revenue per bucket, with a short axis label and a long tooltip label each.
	 *
	 * @param array<int, array{from: int, to: int, start: int}>                      $buckets The current period's buckets.
	 * @param array<int, array{day: int, status: string, count: int, amount: float}> $rows    From daily_totals().
	 * @param bool                                                                   $monthly Whether the buckets are months.
	 * @return array{labels: array<int, string>, tooltips: array<int, string>, values: array<int, float>, averages: array<int, float>}
	 */
	private function series( array $buckets, array $rows, bool $monthly ): array {
		$series = array(
			'labels'   => array(),
			'tooltips' => array(),
			'values'   => array(),
			'averages' => array(),
		);

		foreach ( $buckets as $bucket ) {
			$revenue = 0.0;
			$paid    = 0;

			foreach ( $rows as $row ) {
				if ( TransactionStore::STATUS_PAID === $row['status'] && $row['day'] >= $bucket['from'] && $row['day'] <= $bucket['to'] ) {
					$revenue += $row['amount'];
					$paid    += $row['count'];
				}
			}

			// The bucket's start is a site-calendar midnight expressed as UTC, so I format it as UTC.
			$series['labels'][]   = Dates::day( $monthly ? 'M' : 'j M', $bucket['start'] );
			$series['tooltips'][] = Dates::day( $monthly ? 'F Y' : 'l, j F', $bucket['start'] );
			$series['values'][]   = round( $revenue, 2 );
			$series['averages'][] = $paid > 0 ? round( $revenue / $paid, 2 ) : 0.0;
		}

		return $series;
	}

	/**
	 * Revenue, paid count and the status split, over some buckets.
	 *
	 * @param array<int, array{from: int, to: int, start: int}>                      $buckets The buckets to add up.
	 * @param array<int, array{day: int, status: string, count: int, amount: float}> $rows    From daily_totals().
	 * @return array{revenue: float, paid: int, split: array<string, int>}
	 */
	private function totals( array $buckets, array $rows ): array {
		$first  = $buckets[0]['from'];
		$last   = $buckets[ count( $buckets ) - 1 ]['to'];
		$totals = array(
			'revenue' => 0.0,
			'paid'    => 0,
			'split'   => array(
				TransactionStore::STATUS_PAID    => 0,
				TransactionStore::STATUS_EXPIRED => 0,
				TransactionStore::STATUS_PENDING => 0,
				TransactionStore::STATUS_FAILED  => 0,
			),
		);

		foreach ( $rows as $row ) {
			if ( $row['day'] < $first || $row['day'] > $last ) {
				continue;
			}

			$status = TransactionStore::STATUS_CANCELLED === $row['status'] ? TransactionStore::STATUS_FAILED : $row['status'];

			if ( isset( $totals['split'][ $status ] ) ) {
				$totals['split'][ $status ] += $row['count'];
			}

			if ( TransactionStore::STATUS_PAID === $status ) {
				$totals['revenue'] += $row['amount'];
				$totals['paid']    += $row['count'];
			}
		}

		return $totals;
	}

	/**
	 * The three KPIs, each with its change against the previous period.
	 *
	 * @param array{revenue: float, paid: int, split: array<string, int>} $now  This period.
	 * @param array{revenue: float, paid: int, split: array<string, int>} $then The previous one.
	 * @return array<string, Kpi>
	 */
	private function kpis( array $now, array $then ): array {
		$average      = $now['paid'] > 0 ? $now['revenue'] / $now['paid'] : 0.0;
		$then_average = $then['paid'] > 0 ? $then['revenue'] / $then['paid'] : 0.0;

		return array(
			'revenue' => self::kpi( 'revenue', __( 'Revenue', 'ifthenpay-pay-button' ), $now['revenue'], $then['revenue'], self::money( $now['revenue'] ) ),
			'paid'    => self::kpi( 'paid', __( 'Paid payments', 'ifthenpay-pay-button' ), (float) $now['paid'], (float) $then['paid'], number_format_i18n( $now['paid'] ) ),
			'average' => self::kpi( 'average', __( 'Average payment', 'ifthenpay-pay-button' ), $average, $then_average, self::money( $average ) ),
		);
	}

	/**
	 * One KPI. The change is a percentage; null when there was nothing to compare with.
	 *
	 * @param string $key     The KPI's key.
	 * @param string $label   Its label.
	 * @param float  $value   This period's value.
	 * @param float  $before  The previous period's value.
	 * @param string $display The value, formatted.
	 * @return Kpi
	 */
	private static function kpi( string $key, string $label, float $value, float $before, string $display ): array {
		$delta = $before > 0 ? ( $value - $before ) / $before * 100 : null;

		if ( null === $delta ) {
			$trend = $value > 0 ? 'new' : 'flat';
		} elseif ( abs( $delta ) < 0.05 ) {
			$trend = 'flat';
		} else {
			$trend = $delta > 0 ? 'up' : 'down';
		}

		return array(
			'key'     => $key,
			'label'   => $label,
			'value'   => round( $value, 2 ),
			'display' => $display,
			'delta'   => null === $delta ? null : round( $delta, 1 ),
			'trend'   => $trend,
		);
	}

	/**
	 * An amount in the site's number format.
	 *
	 * @param float $amount The amount in euros.
	 */
	private static function money( float $amount ): string {
		return number_format_i18n( $amount, 2 ) . ' €';
	}

	/**
	 * A period's name, for its tab.
	 *
	 * @param string $key One of PERIODS.
	 */
	public static function period_label( string $key ): string {
		switch ( $key ) {
			case '7d':
				return __( '7 days', 'ifthenpay-pay-button' );
			case '30d':
				return __( '30 days', 'ifthenpay-pay-button' );
			default:
				return __( '12 months', 'ifthenpay-pay-button' );
		}
	}

	/**
	 * What a period's changes are measured against.
	 *
	 * @param string $key One of PERIODS.
	 */
	public static function compare_label( string $key ): string {
		switch ( $key ) {
			case '7d':
				return __( 'vs previous 7 days', 'ifthenpay-pay-button' );
			case '30d':
				return __( 'vs previous 30 days', 'ifthenpay-pay-button' );
			default:
				return __( 'vs previous 12 months', 'ifthenpay-pay-button' );
		}
	}
}
