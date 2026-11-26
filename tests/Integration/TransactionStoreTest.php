<?php
/**
 * Status transitions and spot holds of the transactions table.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Integration;

use Ifthenpay\PayButton\Repository\TransactionStore;

/**
 * The rules money depends on: paid is final unless an admin says otherwise, and holds expire on time.
 */
final class TransactionStoreTest extends TransactionTestCase {

	/**
	 * New payment starts pending.
	 */
	public function test_new_payment_starts_pending(): void {
		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( $this->pending() ) );
	}

	/**
	 * Mark paid works once.
	 */
	public function test_mark_paid_works_once(): void {
		$ref = $this->pending();

		$this->assertTrue( $this->store->mark_paid( $ref, 'MBWAY', 'REQ1' ) );
		$this->assertFalse( $this->store->mark_paid( $ref, 'MBWAY', 'REQ1' ), 'A second webhook must not count as a new payment.' );
		$this->assertSame( TransactionStore::STATUS_PAID, $this->row_status( $ref ) );
	}

	/**
	 * Mark paid records method and request.
	 */
	public function test_mark_paid_records_method_and_request(): void {
		$ref = $this->pending();

		$this->store->mark_paid( $ref, 'MULTIBANCO', 'REQ9' );
		$row = $this->store->get( $ref );

		$this->assertSame( 'MULTIBANCO', $row['pay_method'] );
		$this->assertSame( 'REQ9', $row['request_id'] );
	}

	/**
	 * Failed and cancelled never overwrite paid.
	 */
	public function test_failed_and_cancelled_never_overwrite_paid(): void {
		$ref = $this->pending();
		$this->store->mark_paid( $ref, 'MBWAY', 'REQ1' );

		$this->assertFalse( $this->store->mark_failed( $ref ) );
		$this->assertFalse( $this->store->mark_cancelled( $ref ) );
		$this->assertSame( TransactionStore::STATUS_PAID, $this->row_status( $ref ) );
	}

	/**
	 * A late webhook still pays an expired payment.
	 */
	public function test_a_late_webhook_still_pays_an_expired_payment(): void {
		$ref = $this->pending();
		$this->store->expire_pending_before( time() + 10 );

		$this->assertSame( TransactionStore::STATUS_EXPIRED, $this->row_status( $ref ) );
		$this->assertTrue( $this->store->mark_paid( $ref, 'MBWAY', 'REQ1' ) );
		$this->assertSame( TransactionStore::STATUS_PAID, $this->row_status( $ref ) );
	}

	/**
	 * A failed payment can still be paid.
	 */
	public function test_a_failed_payment_can_still_be_paid(): void {
		$ref = $this->pending();
		$this->store->mark_failed( $ref );

		$this->assertTrue( $this->store->mark_paid( $ref, 'CCARD', 'REQ1' ) );
	}

	/**
	 * Expiry leaves paid and manual rows alone.
	 */
	public function test_expiry_leaves_paid_and_manual_rows_alone(): void {
		$paid = $this->pending();
		$this->store->mark_paid( $paid, 'MBWAY', 'REQ1' );
		$manual = $this->store->create_manual( 'Rui', '', 5.0, TransactionStore::STATUS_PENDING );
		$open   = $this->pending();

		$this->assertSame( 1, $this->store->expire_pending_before( time() + 10 ) );
		$this->assertSame( TransactionStore::STATUS_PAID, $this->row_status( $paid ) );
		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( (string) $manual ) );
		$this->assertSame( TransactionStore::STATUS_EXPIRED, $this->row_status( $open ) );
	}

	/**
	 * Expiry spares a payment still holding a spot.
	 */
	public function test_expiry_spares_a_payment_still_holding_a_spot(): void {
		$holding = $this->for_button( 7, time() + 3600 );
		$lapsed  = $this->for_button( 7, time() - 5 );

		$this->store->expire_pending_before( time() + 10 );

		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( $holding ) );
		$this->assertSame( TransactionStore::STATUS_EXPIRED, $this->row_status( $lapsed ) );
	}

	/**
	 * Expire past hold only takes lapsed holds.
	 */
	public function test_expire_past_hold_only_takes_lapsed_holds(): void {
		$holding = $this->for_button( 7, time() + 3600 );
		$lapsed  = $this->for_button( 7, time() - 5 );
		$none    = $this->pending();

		$this->assertSame( 1, $this->store->expire_past_hold( time() ) );
		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( $holding ) );
		$this->assertSame( TransactionStore::STATUS_EXPIRED, $this->row_status( $lapsed ) );
		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( $none ), 'No hold means the day-count expiry owns it.' );
	}

	/**
	 * Admin can move a row off paid.
	 */
	public function test_admin_can_move_a_row_off_paid(): void {
		$ref = $this->pending();
		$this->store->mark_paid( $ref, 'MBWAY', 'REQ1' );

		$this->assertSame( 1, $this->store->admin_set_status_many( array( $ref ), TransactionStore::STATUS_PENDING ) );
		$this->assertSame( TransactionStore::STATUS_PENDING, $this->row_status( $ref ) );
	}

	/**
	 * Spots count paid and held but not lapsed or expired.
	 */
	public function test_spots_count_paid_and_held_but_not_lapsed_or_expired(): void {
		$paid = $this->for_button( 7, time() - 5, 2 );
		$this->store->mark_paid( $paid, 'MBWAY', 'REQ1' );
		$this->for_button( 7, time() + 3600, 1 );
		$this->for_button( 7, time() - 5, 4 );

		$taken = $this->store->spots_taken( array( 7 ) );

		$this->assertSame( 3, $taken[7]['total'] );
	}

	/**
	 * Releasing spots frees them at once.
	 */
	public function test_releasing_spots_frees_them_at_once(): void {
		$ref = $this->for_button( 7, time() + 3600, 2 );

		$this->assertSame( 2, $this->store->spots_taken( array( 7 ) )[7]['total'] );

		$this->store->release_spots( $ref );

		$this->assertArrayNotHasKey( 7, $this->store->spots_taken( array( 7 ) ) );
	}

	/**
	 * Refs are unique.
	 */
	public function test_refs_are_unique(): void {
		$refs = array();

		for ( $i = 0; $i < 20; $i++ ) {
			$refs[] = $this->pending();
		}

		$this->assertCount( 20, array_unique( $refs ) );
	}
}
