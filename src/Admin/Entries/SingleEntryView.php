<?php
/**
 * The single entry page.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One entry on its own page, Contact Form 7 style: header, hero band, journey strip, transaction and details cards,
 * customer and timestamps aside, plus the row actions (copy link, payment email, status, delete).
 *
 * @phpstan-import-type Transaction from TransactionStore
 */
class SingleEntryView {

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Shared cell rendering.
	 *
	 * @var EntryPresenter
	 */
	private EntryPresenter $presenter;

	/**
	 * Sets up the view.
	 *
	 * @param TransactionStore $transactions Transactions storage.
	 * @param EntryPresenter   $presenter    Shared cell rendering.
	 */
	public function __construct( TransactionStore $transactions, EntryPresenter $presenter ) {
		$this->transactions = $transactions;
		$this->presenter    = $presenter;
	}

	/**
	 * Renders the page.
	 *
	 * @param array<string, mixed> $record The transaction row, with its snapshot.
	 * @phpstan-param Transaction $record
	 */
	public function render( array $record ): void {
		$ref         = $record['ref'];
		$status      = EntryPresenter::list_status( $record['status'] );
		$is_paid     = TransactionStore::STATUS_PAID === $record['status'];
		$is_manual   = TransactionStore::MANUAL_GATEWAY_KEY === $record['gateway_key'];
		$source      = $is_manual ? __( 'Ad Hoc', 'ifthenpay-pay-button' ) : __( 'ifthenpay', 'ifthenpay-pay-button' );
		$payment_url = $record['payment_url'];
		?>
		<div id="iftp-btn-entry" class="wrap iftp-btn-entry iftp-btn-entry--<?php echo esc_attr( $status ); ?>" data-iftp-entry data-iftp-ref="<?php echo esc_attr( $ref ); ?>">
			<header class="iftp-btn-entry__header">
				<a class="iftp-btn-entry__back" href="<?php echo esc_url( EntriesPage::list_url() ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M10 3.5 5.5 8l4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php esc_html_e( 'Back to payments', 'ifthenpay-pay-button' ); ?>
				</a>
				<h1 class="iftp-btn-entry__title">
					<?php esc_html_e( 'Entry', 'ifthenpay-pay-button' ); ?>
					<span class="iftp-btn-entry__chip"><?php echo esc_html( $ref ); ?></span>
				</h1>
				<?php // Paid entries are payment history, so no Delete; the server refuses them too. ?>
				<?php if ( ! $is_paid ) : ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--danger iftp-btn-entry__delete" data-iftp-entry-delete="<?php echo esc_attr( $ref ); ?>"><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></button>
				<?php endif; ?>
			</header>
			<hr class="wp-header-end" />

			<section class="iftp-btn-hero iftp-btn-hero--<?php echo esc_attr( $status ); ?>" aria-labelledby="iftp-btn-hero-amount">
				<p class="iftp-btn-hero__eyebrow">
					<?php
					/* translators: 1: payment reference, 2: where it came from, e.g. "Ad Hoc" */
					echo esc_html( sprintf( __( 'Entry %1$s · %2$s', 'ifthenpay-pay-button' ), $ref, $source ) );
					?>
				</p>
				<p class="iftp-btn-hero__amount" id="iftp-btn-hero-amount"><?php echo esc_html( $this->presenter->format_amount( $record['amount'] ) ); ?></p>
				<p class="iftp-btn-hero__status"><?php $this->presenter->print_status_badge( $record['status'] ); ?></p>
				<dl class="iftp-btn-hero__meta">
					<div class="iftp-btn-hero__meta-row">
						<dt class="iftp-btn-hero__meta-label"><?php esc_html_e( 'Method', 'ifthenpay-pay-button' ); ?></dt>
						<dd class="iftp-btn-hero__meta-value"><?php $this->presenter->print_method( $record['pay_method'], $record['status'] ); ?></dd>
					</div>
					<div class="iftp-btn-hero__meta-row">
						<dt class="iftp-btn-hero__meta-label"><?php esc_html_e( 'Customer', 'ifthenpay-pay-button' ); ?></dt>
						<dd class="iftp-btn-hero__meta-value"><?php $this->presenter->print_customer( $record['customer_name'], $record['customer_email'] ); ?></dd>
					</div>
				</dl>
			</section>

			<?php $this->render_journey( $record ); ?>

			<div class="iftp-btn-entry__layout">
				<div class="iftp-btn-entry__main">
					<section class="iftp-btn-entry-card" aria-labelledby="iftp-btn-card-transaction">
						<h2 class="iftp-btn-entry-card__title" id="iftp-btn-card-transaction"><?php esc_html_e( 'Transaction', 'ifthenpay-pay-button' ); ?></h2>
						<dl class="iftp-btn-entry-card__list">
							<div class="iftp-btn-entry-card__row">
								<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Reference', 'ifthenpay-pay-button' ); ?></dt>
								<dd class="iftp-btn-entry-card__value">
									<code class="iftp-btn-entry-card__code"><?php echo esc_html( $ref ); ?></code>
									<button type="button" class="iftp-btn-btn iftp-btn-btn--link" data-iftp-copy-ref="<?php echo esc_attr( $ref ); ?>"><?php esc_html_e( 'Copy', 'ifthenpay-pay-button' ); ?><span class="screen-reader-text"> <?php esc_html_e( 'reference', 'ifthenpay-pay-button' ); ?></span></button>
								</dd>
							</div>
							<?php
							$this->print_detail_row( __( 'Request ID', 'ifthenpay-pay-button' ), $record['request_id'], __( 'No request ID', 'ifthenpay-pay-button' ) );
							$this->print_detail_row( __( 'Transaction ID', 'ifthenpay-pay-button' ), $record['transaction_id'], __( 'No transaction ID', 'ifthenpay-pay-button' ) );
							$this->print_detail_row( __( 'External reference', 'ifthenpay-pay-button' ), $record['external_ref'], __( 'None in the link', 'ifthenpay-pay-button' ) );
							$this->print_button_row( $record );
							?>
							<div class="iftp-btn-entry-card__row iftp-btn-entry-card__row--wide">
								<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Payment link', 'ifthenpay-pay-button' ); ?></dt>
								<dd class="iftp-btn-entry-card__value">
									<?php if ( '' !== $payment_url ) : ?>
										<a class="iftp-btn-entry-card__link" href="<?php echo esc_url( $payment_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $payment_url ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ifthenpay-pay-button' ); ?></span></a>
									<?php else : ?>
										<?php $this->presenter->print_empty_value( __( 'No payment link', 'ifthenpay-pay-button' ) ); ?>
									<?php endif; ?>
								</dd>
							</div>
						</dl>
					</section>

					<?php $this->render_details_card( $record ); ?>
				</div>

				<aside class="iftp-btn-entry__aside" aria-label="<?php esc_attr_e( 'Entry summary', 'ifthenpay-pay-button' ); ?>">
					<?php $this->render_actions_card( $record ); ?>

					<section class="iftp-btn-entry-card" aria-labelledby="iftp-btn-card-customer">
						<h2 class="iftp-btn-entry-card__title" id="iftp-btn-card-customer"><?php esc_html_e( 'Customer', 'ifthenpay-pay-button' ); ?></h2>
						<div class="iftp-btn-entry-card__avatar"><?php $this->presenter->print_avatar( $record['customer_name'], $record['customer_email'], 56 ); ?></div>
						<dl class="iftp-btn-entry-card__list">
							<?php $this->print_detail_row( __( 'Name', 'ifthenpay-pay-button' ), $record['customer_name'], __( 'No name', 'ifthenpay-pay-button' ) ); ?>
							<div class="iftp-btn-entry-card__row">
								<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Email', 'ifthenpay-pay-button' ); ?></dt>
								<dd class="iftp-btn-entry-card__value">
									<?php
									if ( '' !== $record['customer_email'] ) {
										$this->presenter->print_email( $record['customer_email'], 'iftp-btn-entry-card__email' );
									} else {
										$this->presenter->print_empty_value( __( 'No email', 'ifthenpay-pay-button' ) );
									}
									?>
								</dd>
							</div>
						</dl>
					</section>

					<section class="iftp-btn-entry-card" aria-labelledby="iftp-btn-card-timestamps">
						<h2 class="iftp-btn-entry-card__title" id="iftp-btn-card-timestamps"><?php esc_html_e( 'Timestamps', 'ifthenpay-pay-button' ); ?></h2>
						<dl class="iftp-btn-entry-card__list">
							<div class="iftp-btn-entry-card__row">
								<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Created', 'ifthenpay-pay-button' ); ?></dt>
								<dd class="iftp-btn-entry-card__value"><?php $this->presenter->print_time( $record['created_at'], false ); ?></dd>
							</div>
							<div class="iftp-btn-entry-card__row">
								<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Last updated', 'ifthenpay-pay-button' ); ?></dt>
								<dd class="iftp-btn-entry-card__value"><?php $this->presenter->print_time( $record['updated_at'], false ); ?></dd>
							</div>
						</dl>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Created → Link generated → Status updated. Steps not reached yet say so, not just through colour.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function render_journey( array $record ): void {
		$resolved = TransactionStore::STATUS_PENDING !== $record['status'];
		$steps    = array(
			array(
				'key'     => 'created',
				'label'   => __( 'Created', 'ifthenpay-pay-button' ),
				'reached' => true,
				'time'    => $record['created_at'],
			),
			array(
				'key'     => 'link',
				'label'   => __( 'Link generated', 'ifthenpay-pay-button' ),
				'reached' => '' !== $record['payment_url'],
				'time'    => 0,
			),
			array(
				'key'     => 'status',
				'label'   => $resolved ? $this->presenter->status_label( $record['status'] ) : __( 'Status updated', 'ifthenpay-pay-button' ),
				'reached' => $resolved,
				'time'    => $resolved ? $record['updated_at'] : 0,
			),
		);
		?>
		<ol class="iftp-btn-journey" aria-label="<?php esc_attr_e( 'Payment progress', 'ifthenpay-pay-button' ); ?>">
			<?php foreach ( $steps as $step ) : ?>
				<li class="iftp-btn-journey__step iftp-btn-journey__step--<?php echo esc_attr( $step['key'] ); ?> iftp-btn-journey__step--<?php echo $step['reached'] ? 'reached' : 'upcoming'; ?>"<?php echo $step['reached'] ? '' : ' aria-disabled="true"'; ?>>
					<span class="iftp-btn-journey__marker" aria-hidden="true"><?php echo $step['reached'] ? '&#10003;' : '&#9675;'; ?></span>
					<span class="iftp-btn-journey__label"><?php echo esc_html( $step['label'] ); ?></span>
					<span class="screen-reader-text"><?php echo $step['reached'] ? esc_html__( '(done)', 'ifthenpay-pay-button' ) : esc_html__( '(not yet)', 'ifthenpay-pay-button' ); ?></span>
					<?php if ( $step['time'] > 0 ) : ?>
						<span class="iftp-btn-journey__time"><?php $this->presenter->print_time( $step['time'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * The details the payment was created with, as a description list.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function render_details_card( array $record ): void {
		$pairs = $this->transactions->entry_data_pairs( $record );
		?>
		<section class="iftp-btn-entry-card" aria-labelledby="iftp-btn-card-details">
			<h2 class="iftp-btn-entry-card__title" id="iftp-btn-card-details"><?php esc_html_e( 'Submitted data', 'ifthenpay-pay-button' ); ?></h2>
			<?php if ( array() === $pairs ) : ?>
				<p class="iftp-btn-entry-card__empty"><?php esc_html_e( 'Nothing was submitted with this payment.', 'ifthenpay-pay-button' ); ?></p>
			<?php else : ?>
				<dl class="iftp-btn-entry-card__list">
					<?php foreach ( $pairs as $pair ) : ?>
						<div class="iftp-btn-entry-card__row">
							<dt class="iftp-btn-entry-card__label"><?php echo esc_html( $pair['label'] ); ?></dt>
							<dd class="iftp-btn-entry-card__value"><?php echo esc_html( $pair['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * The list's row actions, carried over: copy link, payment email, and a status change.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function render_actions_card( array $record ): void {
		$is_pending = TransactionStore::STATUS_PENDING === $record['status'];
		$can_email  = EntriesController::can_email( $record );
		?>
		<section class="iftp-btn-entry-card iftp-btn-entry-card--actions" aria-labelledby="iftp-btn-card-actions">
			<h2 class="iftp-btn-entry-card__title" id="iftp-btn-card-actions"><?php esc_html_e( 'Actions', 'ifthenpay-pay-button' ); ?></h2>

			<?php if ( $is_pending && '' !== $record['payment_url'] ) : ?>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-entry-card__action" data-iftp-copy-link="<?php echo esc_attr( $record['payment_url'] ); ?>"><?php esc_html_e( 'Copy payment link', 'ifthenpay-pay-button' ); ?></button>
			<?php endif; ?>

			<?php if ( $can_email ) : ?>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-entry-card__action" data-iftp-resend-email="<?php echo esc_attr( $record['ref'] ); ?>" data-iftp-resend-to="<?php echo esc_attr( $record['customer_email'] ); ?>">
					<svg class="iftp-btn-btn__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="1.75" y="3.25" width="12.5" height="9.5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="m2.5 4.5 5.5 4 5.5-4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
					<span data-iftp-resend-label><?php esc_html_e( 'Send payment email', 'ifthenpay-pay-button' ); ?></span>
				</button>
				<p class="iftp-btn-entry-card__hint">
					<?php
					/* translators: %s: customer email address */
					echo esc_html( sprintf( __( 'Sends the payment button to %s', 'ifthenpay-pay-button' ), $record['customer_email'] ) );
					?>
				</p>
			<?php endif; ?>

			<div class="iftp-btn-entry-card__status">
				<label class="iftp-btn-field__label" for="iftp-btn-entry-status"><?php esc_html_e( 'Change status', 'ifthenpay-pay-button' ); ?></label>
				<select class="iftp-btn-field__control" id="iftp-btn-entry-status" data-iftp-entry-status-select>
					<?php foreach ( $this->presenter->status_labels() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( EntryPresenter::list_status( $record['status'] ), $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary" data-iftp-entry-status-apply data-iftp-current-status="<?php echo esc_attr( EntryPresenter::list_status( $record['status'] ) ); ?>"><?php esc_html_e( 'Update', 'ifthenpay-pay-button' ); ?></button>
			</div>
		</section>
		<?php
	}

	/**
	 * One label/value row, with the empty-value dash when there's nothing.
	 *
	 * @param string $label   The row's label.
	 * @param string $value   The value, or ''.
	 * @param string $missing What's missing, for screen readers.
	 */
	private function print_detail_row( string $label, string $value, string $missing ): void {
		?>
		<div class="iftp-btn-entry-card__row">
			<dt class="iftp-btn-entry-card__label"><?php echo esc_html( $label ); ?></dt>
			<dd class="iftp-btn-entry-card__value">
				<?php
				if ( '' !== $value ) {
					echo esc_html( $value );
				} else {
					$this->presenter->print_empty_value( $missing );
				}
				?>
			</dd>
		</div>
		<?php
	}
	/**
	 * The button the payment came from, and the page it started on.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function print_button_row( array $record ): void {
		if ( $record['button_id'] <= 0 ) {
			return;
		}

		$button = ( new ButtonRepository() )->find( $record['button_id'] );
		?>
		<div class="iftp-btn-entry-card__row">
			<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Button', 'ifthenpay-pay-button' ); ?></dt>
			<dd class="iftp-btn-entry-card__value">
				<?php if ( null !== $button ) : ?>
					<a href="<?php echo esc_url( ButtonsPage::edit_url( $button->id ) ); ?>"><?php echo esc_html( $button->title ); ?></a>
				<?php else : ?>
					<?php $this->presenter->print_empty_value( __( 'Deleted button', 'ifthenpay-pay-button' ) ); ?>
				<?php endif; ?>
			</dd>
		</div>
		<div class="iftp-btn-entry-card__row iftp-btn-entry-card__row--wide">
			<dt class="iftp-btn-entry-card__label"><?php esc_html_e( 'Started on', 'ifthenpay-pay-button' ); ?></dt>
			<dd class="iftp-btn-entry-card__value">
				<?php if ( '' !== $record['source_url'] ) : ?>
					<a class="iftp-btn-entry-card__link" href="<?php echo esc_url( $record['source_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $record['source_url'] ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'ifthenpay-pay-button' ); ?></span></a>
				<?php else : ?>
					<?php $this->presenter->print_empty_value( __( 'Unknown page', 'ifthenpay-pay-button' ) ); ?>
				<?php endif; ?>
			</dd>
		</div>
		<?php
	}
}
