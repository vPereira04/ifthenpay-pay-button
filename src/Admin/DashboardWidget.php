<?php
/**
 * The ifthenpay widget on the WordPress Dashboard.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Entries\EntriesPage;
use Ifthenpay\PayButton\Admin\Entries\EntryPresenter;
use Ifthenpay\PayButton\Admin\Entries\InsightsView;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The last 30 days at a glance: revenue with its trend, payments by status, and the latest payments.
 */
class DashboardWidget {

	public const ID = 'iftp_btn_overview';

	private const RECENT = 5;

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Transactions storage.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the widget.
	 *
	 * @param SettingsRepository|null $settings     Settings storage.
	 * @param TransactionStore|null   $transactions Transactions storage.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?TransactionStore $transactions = null ) {
		$this->settings     = $settings ?? new SettingsRepository();
		$this->transactions = $transactions ?? new TransactionStore();
	}

	/**
	 * Registers the widget and its assets.
	 */
	public function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Whether this admin gets the widget: the screens' capability, and something connected to show.
	 */
	private function applies(): bool {
		return current_user_can( Plugin::admin_cap() ) && $this->settings->is_connected();
	}

	/**
	 * Adds the widget to the Dashboard.
	 */
	public function add(): void {
		if ( $this->applies() ) {
			wp_add_dashboard_widget( self::ID, __( 'ifthenpay', 'ifthenpay-pay-button' ), array( $this, 'render' ) );
		}
	}

	/**
	 * The stylesheet and charts, on the Dashboard only.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( 'index.php' !== $hook || ! $this->applies() ) {
			return;
		}

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_script( Plugin::CHARTS_SCRIPT_HANDLE );
	}

	/**
	 * Renders the widget.
	 */
	public function render(): void {
		$presenter = new EntryPresenter( $this->settings );
		$period    = ( new Insights( $this->transactions ) )->period( '30d' );
		$revenue   = $period['kpis']['revenue'];
		$split     = $period['split'];
		$started   = array_sum( $split );
		$labels    = $presenter->status_labels();
		$recent    = $this->transactions->query(
			array(
				'per_page' => self::RECENT,
				'paged'    => 1,
			)
		)['items'];
		?>
		<div class="iftp-btn-widget">
			<div class="iftp-btn-widget__hero">
				<span class="iftp-btn-widget__label"><?php esc_html_e( 'Revenue · last 30 days', 'ifthenpay-pay-button' ); ?></span>
				<span class="iftp-btn-widget__value"><?php echo esc_html( $revenue['display'] ); ?></span>
				<span class="iftp-btn-widget__delta">
					<?php $presenter->print_delta( $revenue, $period['compare'] ); ?>
					<span class="iftp-btn-widget__compare" aria-hidden="true"><?php echo esc_html( $period['compare'] ); ?></span>
				</span>
			</div>

			<div class="iftp-btn-chart iftp-btn-widget__chart" data-iftp-chart="<?php echo esc_attr( InsightsView::chart_data( $period['series'], $revenue['label'], 120, true ) ); ?>"></div>

			<div class="iftp-btn-widget__split">
				<p class="iftp-btn-widget__section-title">
					<?php
					/* translators: %s: number of payments */
					echo esc_html( sprintf( _n( 'Payments by status · %s payment', 'Payments by status · %s payments', $started, 'ifthenpay-pay-button' ), number_format_i18n( $started ) ) );
					?>
				</p>
				<?php if ( $started > 0 ) : ?>
					<?php // Paid, Expired, Pending, Failed: the order that keeps every neighbouring pair apart for colour-blind readers. ?>
					<div class="iftp-btn-split" aria-hidden="true">
						<?php foreach ( $split as $status => $count ) : ?>
							<?php if ( $count > 0 ) : ?>
								<span class="iftp-btn-split__part iftp-btn-split__part--<?php echo esc_attr( $status ); ?>" style="flex-grow: <?php echo esc_attr( (string) $count ); ?>"></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<ul class="iftp-btn-split__legend">
					<?php foreach ( $split as $status => $count ) : ?>
						<li class="iftp-btn-split__item">
							<span class="iftp-btn-split__key iftp-btn-split__key--<?php echo esc_attr( $status ); ?>" aria-hidden="true"></span>
							<span class="iftp-btn-split__name"><?php echo esc_html( $labels[ $status ] ?? $status ); ?></span>
							<span class="iftp-btn-split__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="iftp-btn-widget__recent">
				<p class="iftp-btn-widget__section-title"><?php esc_html_e( 'Latest payments', 'ifthenpay-pay-button' ); ?></p>
				<?php if ( array() === $recent ) : ?>
					<p class="iftp-btn-widget__empty"><?php esc_html_e( 'No payments yet.', 'ifthenpay-pay-button' ); ?></p>
				<?php else : ?>
					<ul class="iftp-btn-recent">
						<?php foreach ( $recent as $record ) : ?>
							<li class="iftp-btn-recent__item">
								<a class="iftp-btn-recent__link" href="<?php echo esc_url( EntriesPage::entry_url( $record['ref'] ) ); ?>">
									<?php $presenter->print_avatar( $record['customer_name'], $record['customer_email'], 32 ); ?>
									<span class="iftp-btn-recent__who">
										<span class="iftp-btn-recent__name"><?php echo esc_html( '' !== $record['customer_name'] ? $record['customer_name'] : ( '' !== $record['customer_email'] ? $record['customer_email'] : $record['ref'] ) ); ?></span>
										<span class="iftp-btn-recent__time"><?php $presenter->print_time( $record['created_at'] ); ?></span>
									</span>
									<span class="iftp-btn-recent__amount"><?php echo esc_html( $presenter->format_amount( $record['amount'] ) ); ?></span>
									<?php $presenter->print_status_badge( $record['status'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<p class="iftp-btn-widget__footer">
				<a class="iftp-btn-widget__cta" href="<?php echo esc_url( EntriesPage::list_url() ); ?>"><?php esc_html_e( 'View all entries', 'ifthenpay-pay-button' ); ?> <span aria-hidden="true">→</span></a>
				<a href="<?php echo esc_url( GatewaySettingsPage::url() ); ?>"><?php esc_html_e( 'Gateway Configuration', 'ifthenpay-pay-button' ); ?></a>
			</p>
		</div>
		<?php
	}
}
