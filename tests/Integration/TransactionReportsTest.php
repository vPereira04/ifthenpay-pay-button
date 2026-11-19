<?php
/**
 * The aggregate reads.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Integration;

use Ifthenpay\PayButton\Reports\TransactionReports;

/**
 * Totals only count what they should, and a write drops the cached figures.
 */
final class TransactionReportsTest extends TransactionTestCase {

	/**
	 * The reports under test.
	 *
	 * @var TransactionReports
	 */
	private TransactionReports $reports;

	/**
	 * Resets the state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reports = new TransactionReports( $this->store );
	}

	/**
	 * The window around "now", for the date-ranged reports.
	 *
	 * @return array{int, int}
	 */
	private function window(): array {
		return array( time() - 60, time() + 60 );
	}

	/**
	 * Waiting sums only pending payments.
	 */
	public function test_waiting_sums_only_pending_payments(): void {
		$this->pending( 10.0 );
		$this->pending( 5.5 );
		$paid = $this->pending( 100.0 );
		$this->store->mark_paid( $paid, 'MBWAY', 'REQ1' );

		$this->assertSame(
			array(
				'count'  => 2,
				'amount' => 15.5,
			),
			$this->reports->waiting()
		);
	}

	/**
	 * Waiting updates after a write.
	 */
	public function test_waiting_updates_after_a_write(): void {
		$ref = $this->pending( 10.0 );

		$this->assertSame( 1, $this->reports->waiting()['count'] );

		$this->store->mark_paid( $ref, 'MBWAY', 'REQ1' );

		$this->assertSame( 0, $this->reports->waiting()['count'], 'The cached figure must not outlive the write.' );
	}

	/**
	 * Method totals group paid payments by method.
	 */
	public function test_method_totals_group_paid_payments_by_method(): void {
		$one = $this->pending( 10.0 );
		$two = $this->pending( 20.0 );
		$tre = $this->pending( 30.0 );
		$this->store->mark_paid( $one, 'MBWAY', 'R1' );
		$this->store->mark_paid( $two, 'MBWAY', 'R2' );
		$this->store->mark_paid( $tre, 'CCARD', 'R3' );
		$this->pending( 99.0 );

		list( $from, $to ) = $this->window();
		$by_method         = array();

		foreach ( $this->reports->method_totals( $from, $to ) as $row ) {
			$by_method[ $row['method'] ] = array( $row['count'], $row['amount'] );
		}

		$this->assertSame(
			array(
				'MBWAY' => array( 2, 30.0 ),
				'CCARD' => array( 1, 30.0 ),
			),
			$by_method
		);
	}

	/**
	 * Method totals respect the window.
	 */
	public function test_method_totals_respect_the_window(): void {
		$ref = $this->pending( 10.0 );
		$this->store->mark_paid( $ref, 'MBWAY', 'R1' );

		$this->assertSame( array(), $this->reports->method_totals( time() + 100, time() + 200 ) );
	}

	/**
	 * Top customers rank by amount and skip anonymous rows.
	 */
	public function test_top_customers_rank_by_amount_and_skip_anonymous_rows(): void {
		$a1 = $this->store->create_pending( 'Ana', 'ana@example.com', 10.0, 'AAAA-000000' );
		$a2 = $this->store->create_pending( 'Ana', 'ana@example.com', 15.0, 'AAAA-000000' );
		$b1 = $this->store->create_pending( 'Bia', 'bia@example.com', 40.0, 'AAAA-000000' );
		$c1 = $this->store->create_pending( '', '', 500.0, 'AAAA-000000' );

		foreach ( array( $a1, $a2, $b1, $c1 ) as $i => $ref ) {
			$this->store->mark_paid( (string) $ref, 'MBWAY', 'R' . $i );
		}

		list( $from, $to ) = $this->window();
		$top               = $this->reports->top_customers( $from, $to, 5 );

		$this->assertCount( 2, $top );
		$this->assertSame( 'bia@example.com', $top[0]['email'] );
		$this->assertSame( 40.0, $top[0]['amount'] );
		$this->assertSame( 'ana@example.com', $top[1]['email'] );
		$this->assertSame( 2, $top[1]['count'] );
		$this->assertSame( 25.0, $top[1]['amount'] );
	}

	/**
	 * Top customers honour the limit.
	 */
	public function test_top_customers_honour_the_limit(): void {
		foreach ( array( 'a', 'b', 'c' ) as $i => $name ) {
			$ref = $this->store->create_pending( $name, $name . '@example.com', 10.0 + $i, 'AAAA-000000' );
			$this->store->mark_paid( (string) $ref, 'MBWAY', 'R' . $i );
		}

		list( $from, $to ) = $this->window();

		$this->assertCount( 2, $this->reports->top_customers( $from, $to, 2 ) );
	}

	/**
	 * Button stats count paid and all.
	 */
	public function test_button_stats_count_paid_and_all(): void {
		$paid = $this->for_button( 7, 0 );
		$this->for_button( 7, 0 );
		$this->store->mark_paid( $paid, 'MBWAY', 'R1' );

		$this->assertSame(
			array(
				7 => array(
					'paid_count'  => 1,
					'paid_amount' => 20.0,
					'count'       => 2,
				),
			),
			$this->reports->button_stats( array( 7, 8 ) )
		);
	}

	/**
	 * Paid since counts from each buttons own moment.
	 */
	public function test_paid_since_counts_from_each_buttons_own_moment(): void {
		$ref = $this->for_button( 7, 0 );
		$this->store->mark_paid( $ref, 'MBWAY', 'R1' );

		$this->assertSame( array( 7 => 1 ), $this->reports->paid_since( array( 7 => time() - 60 ) ) );
		$this->assertSame( array(), $this->reports->paid_since( array( 7 => time() + 60 ) ) );
	}

	/**
	 * Daily totals split by status.
	 */
	public function test_daily_totals_split_by_status(): void {
		$paid = $this->pending( 10.0 );
		$this->store->mark_paid( $paid, 'MBWAY', 'R1' );
		$this->pending( 4.0 );

		list( $from, $to ) = $this->window();
		$by_status         = array();

		foreach ( $this->reports->daily_totals( $from, $to, 0 ) as $row ) {
			$by_status[ $row['status'] ] = $row['amount'];
		}

		$this->assertSame(
			array(
				'paid'    => 10.0,
				'pending' => 4.0,
			),
			$by_status
		);
	}
}
