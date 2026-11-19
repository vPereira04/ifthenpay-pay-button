<?php
/**
 * Base for tests that need the transactions table.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Integration;

use Ifthenpay\PayButton\Repository\TransactionStore;
use PHPUnit\Framework\TestCase;

/**
 * An empty table before each test, and a refusal to run anywhere but the test prefix.
 */
abstract class TransactionTestCase extends TestCase {

	/**
	 * The store under test.
	 *
	 * @var TransactionStore
	 */
	protected TransactionStore $store;

	/**
	 * Resets the state before each test.
	 */
	protected function setUp(): void {
		global $wpdb;

		if ( 'iftptest_' !== $wpdb->prefix ) {
			$this->fail( 'Not on the test table prefix, so I will not touch the database. Run through tests/bootstrap-integration.php.' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- the test table.
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', TransactionStore::table_name() ) );
		wp_cache_flush();

		$this->store = new TransactionStore();
	}

	/**
	 * A pending ifthenpay payment.
	 *
	 * @param float $amount The amount.
	 */
	protected function pending( float $amount = 10.0 ): string {
		$ref = $this->store->create_pending( 'Ana', 'ana@example.com', $amount, 'AAAA-000000' );

		$this->assertNotNull( $ref );

		return (string) $ref;
	}

	/**
	 * A pending payment from a button, holding spots.
	 *
	 * @param int $button_id  The button.
	 * @param int $hold_until Unix time its spots are held until; 0 for none.
	 * @param int $spots      How many spots it takes.
	 */
	protected function for_button( int $button_id, int $hold_until, int $spots = 1 ): string {
		$ref = $this->store->create_for_button(
			$button_id,
			20.0,
			'AAAA-000000',
			array(),
			'',
			'',
			array(
				'count'      => $spots,
				'options'    => array(),
				'hold_until' => $hold_until,
			)
		);

		$this->assertNotNull( $ref );

		return (string) $ref;
	}

	/**
	 * A row's status.
	 *
	 * @param string $ref The reference.
	 */
	protected function row_status( string $ref ): string {
		$row = $this->store->get( $ref );

		$this->assertNotNull( $row );

		return (string) $row['status'];
	}
}
