<?php
/**
 * The Performance section on top of the Entries list.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Admin\GatewaySettingsPage;
use Ifthenpay\PayButton\Mail\PaymentEmailSender;
use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A revenue chart and three KPI tiles, each with its change against the previous period, for 7 days, 30 days
 * and 12 months. All three periods render up front; the tabs only show and hide them, so switching is instant.
 *
 * @phpstan-import-type Period from Insights
 * @phpstan-import-type Transaction from TransactionStore
 */
class InsightsView {

	// The Money waiting card lists this many of the oldest pending payments; "Remind all" covers up to the cap.
	private const WAITING_ROWS = 4;
	private const REMIND_CAP   = 500;

	/**
	 * The numbers.
	 *
	 * @var Insights
	 */
	private Insights $insights;

	/**
	 * Shared cell rendering (delta pills).
	 *
	 * @var EntryPresenter
	 */
	private EntryPresenter $presenter;

	/**
	 * The period the tabs open on, from the admin's layout.
	 *
	 * @var string
	 */
	private string $period = '7d';

	/**
	 * Settings storage: the display mode, the goal, the reminders.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Transactions storage, for the money waiting.
	 *
	 * @var TransactionStore
	 */
	private TransactionStore $transactions;

	/**
	 * Sets up the view.
	 *
	 * @param Insights           $insights     The numbers.
	 * @param EntryPresenter     $presenter    Shared cell rendering.
	 * @param SettingsRepository $settings     Settings storage.
	 * @param TransactionStore   $transactions Transactions storage.
	 */
	public function __construct( Insights $insights, EntryPresenter $presenter, SettingsRepository $settings, TransactionStore $transactions ) {
		$this->insights     = $insights;
		$this->presenter    = $presenter;
		$this->settings     = $settings;
		$this->transactions = $transactions;
	}

	/**
	 * The data a chart container carries for charts.js.
	 *
	 * @param array<string, mixed> $series A period's series.
	 * @param string               $label  What's plotted, for the tooltip and screen readers.
	 * @param int                  $height The chart's height in px.
	 * @param bool                 $compact Sparkline style: no axes.
	 * @param array<string, mixed> $extra   Options on top: `compare` (keep the second line in compact), `interactive`
	 *                                      (hover in compact), `fill` (take the container's height), `previousLabel`.
	 */
	public static function chart_data( array $series, string $label, int $height, bool $compact = false, array $extra = array() ): string {
		global $wp_locale;

		// The site's own separators, so the chart writes amounts exactly like the rest of the screen ("1.234,56 €").
		$separators = $wp_locale instanceof \WP_Locale ? $wp_locale->number_format : array();

		return (string) wp_json_encode(
			$extra + array(
				'label'         => $label,
				'labels'        => $series['labels'],
				'tooltips'      => $series['tooltips'],
				'values'        => $series['values'],
				'previous'      => $series['previous'] ?? array(),
				'previousLabel' => __( 'Previous period', 'ifthenpay-pay-button' ),
				'locale'        => str_replace( '_', '-', get_user_locale() ),
				'currency'      => 'EUR',
				'height'        => $height,
				'compact'       => $compact,
				'decimal'       => html_entity_decode( (string) ( $separators['decimal_point'] ?? '.' ), ENT_QUOTES, 'UTF-8' ),
				'thousands'     => html_entity_decode( (string) ( $separators['thousands_sep'] ?? ',' ), ENT_QUOTES, 'UTF-8' ),
			)
		);
	}

	/**
	 * Sets the period the tabs open on.
	 *
	 * @param string $period One of Insights::PERIODS.
	 */
	public function set_period( string $period ): void {
		$this->period = in_array( $period, Insights::PERIODS, true ) ? $period : '7d';
	}

	/**
	 * Renders the section: one big card, the headline and period switch on top, the stat strip, then the chart.
	 */
	public function render(): void {
		$periods = $this->insights->all();
		$current = $this->period;
		?>
		<section class="iftp-btn-insights" aria-labelledby="iftp-btn-insights-title" data-iftp-insights data-iftp-current-period="<?php echo esc_attr( $current ); ?>">
			<header class="iftp-btn-insights__head">
				<div class="iftp-btn-insights__heading">
					<h2 id="iftp-btn-insights-title" class="iftp-btn-insights__title"><?php esc_html_e( 'Performance', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-insights__sub"><?php esc_html_e( 'Paid revenue, against the period before.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-segmented" role="tablist" aria-label="<?php esc_attr_e( 'Period', 'ifthenpay-pay-button' ); ?>">
					<?php foreach ( $periods as $key => $period ) : ?>
						<button
							type="button"
							class="iftp-btn-segmented__option"
							role="tab"
							id="iftp-btn-period-tab-<?php echo esc_attr( $key ); ?>"
							aria-controls="iftp-btn-period-<?php echo esc_attr( $key ); ?>"
							aria-selected="<?php echo $key === $current ? 'true' : 'false'; ?>"
							tabindex="<?php echo $key === $current ? '0' : '-1'; ?>"
							data-iftp-period="<?php echo esc_attr( $key ); ?>"
						><?php echo esc_html( $period['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			</header>

			<?php foreach ( $periods as $key => $period ) : ?>
				<?php $this->render_panel( $period, $key === $current ); ?>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * One period: the revenue headline, the stat strip, the legend and the chart.
	 *
	 * @param array<string, mixed> $period  A period from Insights.
	 * @param bool                 $visible Whether it's the one showing.
	 * @phpstan-param Period $period
	 */
	private function render_panel( array $period, bool $visible ): void {
		$key     = $period['key'];
		$revenue = $period['kpis']['revenue'];
		?>
		<div class="iftp-btn-insights__panel" role="tabpanel" id="iftp-btn-period-<?php echo esc_attr( $key ); ?>" aria-labelledby="iftp-btn-period-tab-<?php echo esc_attr( $key ); ?>" data-iftp-period-panel="<?php echo esc_attr( $key ); ?>"<?php echo $visible ? '' : ' hidden'; ?>>
			<div class="iftp-btn-insights__stats">
				<div class="iftp-btn-insights__hero">
					<span class="iftp-btn-insights__hero-label"><?php echo esc_html( $revenue['label'] ); ?></span>
					<span class="iftp-btn-insights__hero-value"><?php echo esc_html( $revenue['display'] ); ?></span>
					<span class="iftp-btn-insights__hero-delta">
						<?php $this->presenter->print_delta( $revenue, $period['compare'] ); ?>
						<span class="iftp-btn-insights__compare" aria-hidden="true"><?php echo esc_html( $period['compare'] ); ?></span>
					</span>
				</div>
				<ul class="iftp-btn-kpis">
					<?php foreach ( array( 'paid', 'average', 'conversion' ) as $tile ) : ?>
						<?php $kpi = $period['kpis'][ $tile ]; ?>
						<li class="iftp-btn-kpi iftp-btn-kpi--<?php echo esc_attr( $tile ); ?>">
							<span class="iftp-btn-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
							<span class="iftp-btn-kpi__value"><?php echo esc_html( $kpi['display'] ); ?></span>
							<?php $this->presenter->print_delta( $kpi, $period['compare'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<figure class="iftp-btn-insights__figure">
				<figcaption class="iftp-btn-legend">
					<span class="iftp-btn-legend__item"><span class="iftp-btn-legend__key iftp-btn-legend__key--current" aria-hidden="true"></span><?php esc_html_e( 'This period', 'ifthenpay-pay-button' ); ?></span>
					<span class="iftp-btn-legend__item"><span class="iftp-btn-legend__key iftp-btn-legend__key--previous" aria-hidden="true"></span><?php esc_html_e( 'Previous period', 'ifthenpay-pay-button' ); ?></span>
				</figcaption>
				<div class="iftp-btn-chart iftp-btn-chart--hero" data-iftp-chart="<?php echo esc_attr( self::chart_data( $period['series'], $revenue['label'], 300 ) ); ?>" data-iftp-chart-hint="<?php esc_attr_e( 'Use the arrow keys to read each point.', 'ifthenpay-pay-button' ); ?>"></div>
				<?php $this->render_numbers( $period ); ?>
			</figure>
		</div>
		<?php
	}

	/**
	 * Revenue by method: one bar per method, its share of the period's revenue as the length. One colour for all:
	 * the methods are just names, so the length carries the story and the colour doesn't need to.
	 */
	public function render_methods_card(): void {
		$periods = $this->insights->all();
		?>
		<section class="iftp-btn-tile iftp-btn-shares-card" aria-labelledby="iftp-btn-shares-title">
			<header class="iftp-btn-tile__head">
				<h3 id="iftp-btn-shares-title" class="iftp-btn-tile__title"><?php esc_html_e( 'Revenue by method', 'ifthenpay-pay-button' ); ?></h3>
			</header>
			<?php foreach ( $periods as $key => $period ) : ?>
				<div class="iftp-btn-tile__body" data-iftp-period-delta="<?php echo esc_attr( $key ); ?>"<?php echo $this->period === $key ? '' : ' hidden'; ?>>
					<?php if ( array() === $period['methods'] ) : ?>
						<p class="iftp-btn-tile__empty"><?php esc_html_e( 'No paid payments in this period yet.', 'ifthenpay-pay-button' ); ?></p>
					<?php else : ?>
						<ul class="iftp-btn-shares">
							<?php foreach ( $period['methods'] as $share ) : ?>
								<li class="iftp-btn-share">
									<span class="iftp-btn-share__name"><?php $this->presenter->print_method( $share['label'], TransactionStore::STATUS_PAID ); ?></span>
									<span class="iftp-btn-share__amount"><?php echo esc_html( $share['kpi']['display'] ); ?></span>
									<span class="iftp-btn-share__bar" aria-hidden="true"><svg class="iftp-btn-share__fill" width="<?php echo esc_attr( (string) max( 1, $share['share'] ) ); ?>%" focusable="false"></svg></span>
									<span class="iftp-btn-share__meta">
										<span class="iftp-btn-share__pct">
											<?php
											/* translators: 1: share of revenue, e.g. "42.0", 2: number of payments */
											echo esc_html( sprintf( _n( '%1$s%% · %2$s payment', '%1$s%% · %2$s payments', $share['count'], 'ifthenpay-pay-button' ), number_format_i18n( $share['share'], 1 ), number_format_i18n( $share['count'] ) ) );
											?>
										</span>
										<?php $this->presenter->print_delta( $share['kpi'], $period['compare'] ); ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * The monthly goal: how far this month got, where it's heading at this pace, and what it takes per day from here.
	 */
	public function render_goal_card(): void {
		$goal  = $this->settings->get_monthly_goal();
		$month = $this->insights->month( $goal );
		?>
		<section class="iftp-btn-tile iftp-btn-goal" aria-labelledby="iftp-btn-goal-title">
			<header class="iftp-btn-tile__head">
				<h3 id="iftp-btn-goal-title" class="iftp-btn-tile__title"><?php esc_html_e( 'Monthly goal', 'ifthenpay-pay-button' ); ?></h3>
				<span class="iftp-btn-tile__hint"><?php echo esc_html( $month['label'] ); ?></span>
			</header>
			<?php if ( $goal <= 0 ) : ?>
				<p class="iftp-btn-tile__empty">
					<?php esc_html_e( 'Set a revenue goal for the month and see how close you are.', 'ifthenpay-pay-button' ); ?>
					<a href="<?php echo esc_url( GatewaySettingsPage::url() . '#iftp-btn-display-title' ); ?>"><?php esc_html_e( 'Set a goal', 'ifthenpay-pay-button' ); ?></a>
				</p>
			<?php else : ?>
				<?php
				$revenue   = $month['revenue'];
				$percent   = min( 100.0, $revenue / $goal * 100 );
				$expected  = $goal * $month['day'] / $month['days'];
				$projected = $revenue / max( 1, $month['day'] ) * $month['days'];
				$left_days = max( 1, $month['days'] - $month['day'] + 1 );
				$per_day   = max( 0.0, ( $goal - $revenue ) / $left_days );
				$reached   = $revenue >= $goal;
				$on_track  = $revenue >= $expected;
				?>
				<div class="iftp-btn-tile__body">
					<p class="iftp-btn-goal__value">
						<span class="iftp-btn-goal__now"><?php echo esc_html( $this->presenter->format_amount( $revenue ) ); ?></span>
						<span class="iftp-btn-goal__of">
							<?php
							/* translators: %s: the goal amount */
							echo esc_html( sprintf( __( 'of %s', 'ifthenpay-pay-button' ), $this->presenter->format_amount( $goal ) ) );
							?>
						</span>
					</p>
					<?php // The marker shows where the month should be by today, so ahead/behind reads at a glance. ?>
					<div class="iftp-btn-meter<?php echo $reached ? ' iftp-btn-meter--reached' : ''; ?>" role="meter" aria-valuemin="0" aria-valuemax="<?php echo esc_attr( (string) $goal ); ?>" aria-valuenow="<?php echo esc_attr( (string) min( $revenue, $goal ) ); ?>" aria-label="<?php esc_attr_e( 'Progress towards the monthly goal', 'ifthenpay-pay-button' ); ?>">
						<svg class="iftp-btn-meter__fill" width="<?php echo esc_attr( (string) round( $percent, 1 ) ); ?>%" aria-hidden="true" focusable="false"></svg>
						<?php if ( ! $reached ) : ?>
							<?php $pace = round( min( 100, $expected / $goal * 100 ), 1 ) . '%'; ?>
							<svg class="iftp-btn-meter__mark" focusable="false"><line x1="<?php echo esc_attr( $pace ); ?>" x2="<?php echo esc_attr( $pace ); ?>" y1="0" y2="100%"><title><?php esc_html_e( 'Where you\'d be today on an even pace', 'ifthenpay-pay-button' ); ?></title></line></svg>
						<?php endif; ?>
					</div>
					<ul class="iftp-btn-goal__facts">
						<li class="iftp-btn-goal__fact iftp-btn-goal__fact--<?php echo $reached || $on_track ? 'good' : 'bad'; ?>">
							<?php
							if ( $reached ) {
								/* translators: %s: percentage */
								echo esc_html( sprintf( __( 'Goal reached · %s%%', 'ifthenpay-pay-button' ), number_format_i18n( $revenue / $goal * 100, 0 ) ) );
							} elseif ( $on_track ) {
								/* translators: %s: percentage */
								echo esc_html( sprintf( __( '%s%% there · ahead of pace', 'ifthenpay-pay-button' ), number_format_i18n( $percent, 0 ) ) );
							} else {
								/* translators: %s: percentage */
								echo esc_html( sprintf( __( '%s%% there · behind pace', 'ifthenpay-pay-button' ), number_format_i18n( $percent, 0 ) ) );
							}
							?>
						</li>
						<li class="iftp-btn-goal__fact">
							<?php
							/* translators: %s: projected revenue for the month */
							echo esc_html( sprintf( __( 'On this pace: %s by month end', 'ifthenpay-pay-button' ), $this->presenter->format_amount( $projected ) ) );
							?>
						</li>
						<?php if ( ! $reached ) : ?>
							<li class="iftp-btn-goal__fact">
								<?php
								/* translators: 1: amount per day, 2: number of days left */
								echo esc_html( sprintf( _n( 'Needed: %1$s a day for %2$s day', 'Needed: %1$s a day for %2$s days', $left_days, 'ifthenpay-pay-button' ), $this->presenter->format_amount( $per_day ), number_format_i18n( $left_days ) ) );
								?>
							</li>
						<?php endif; ?>
					</ul>
					<?php // The month so far, climbing against an even pace to the goal: ahead when the blue line is above the grey. ?>
					<div class="iftp-btn-goal__chart">
						<div class="iftp-btn-chart iftp-btn-chart--fill" data-iftp-chart="
						<?php
						echo esc_attr(
							self::chart_data(
								$month['series'],
								__( 'Revenue this month', 'ifthenpay-pay-button' ),
								120,
								true,
								array(
									'compare'       => true,
									'interactive'   => true,
									'fill'          => true,
									'previousLabel' => __( 'Even pace', 'ifthenpay-pay-button' ),
								)
							)
						);
						?>
																						" data-iftp-chart-hint="<?php esc_attr_e( 'Use the arrow keys to read each point.', 'ifthenpay-pay-button' ); ?>"></div>
						<p class="iftp-btn-legend iftp-btn-legend--start">
							<span class="iftp-btn-legend__item"><span class="iftp-btn-legend__key iftp-btn-legend__key--current" aria-hidden="true"></span><?php esc_html_e( 'This month', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-legend__item"><span class="iftp-btn-legend__key iftp-btn-legend__key--previous" aria-hidden="true"></span><?php esc_html_e( 'Even pace to the goal', 'ifthenpay-pay-button' ); ?></span>
						</p>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Money waiting: what's pending, how old it is, the oldest few with a Remind each, and Remind all.
	 */
	public function render_waiting_card(): void {
		$waiting = $this->transactions->waiting( self::WAITING_ROWS );
		$refs    = $this->transactions->remindable( self::REMIND_CAP );
		$days    = $this->settings->get_remind_days();
		$ages    = array(
			'fresh' => __( 'Under a day', 'ifthenpay-pay-button' ),
			'days'  => __( '1–3 days', 'ifthenpay-pay-button' ),
			'stale' => __( 'Over 3 days', 'ifthenpay-pay-button' ),
		);
		?>
		<section class="iftp-btn-tile iftp-btn-waiting" aria-labelledby="iftp-btn-waiting-title">
			<header class="iftp-btn-tile__head">
				<h3 id="iftp-btn-waiting-title" class="iftp-btn-tile__title"><?php esc_html_e( 'Money waiting', 'ifthenpay-pay-button' ); ?></h3>
				<span class="iftp-btn-tile__hint">
					<?php
					/* translators: %s: number of pending payments */
					echo esc_html( sprintf( _n( '%s pending payment', '%s pending payments', $waiting['count'], 'ifthenpay-pay-button' ), number_format_i18n( $waiting['count'] ) ) );
					?>
				</span>
			</header>
			<div class="iftp-btn-tile__body">
				<p class="iftp-btn-waiting__amount"><?php echo esc_html( $this->presenter->format_amount( $waiting['amount'] ) ); ?></p>

				<?php if ( $waiting['amount'] > 0 ) : ?>
					<?php // Older is darker: one amber, three steps, so age reads as "more urgent" without new colours. ?>
					<div class="iftp-btn-ages" aria-hidden="true">
						<?php foreach ( $ages as $age => $label ) : ?>
							<?php if ( $waiting['ages'][ $age ] > 0 ) : ?>
								<span class="iftp-btn-ages__part iftp-btn-ages__part--<?php echo esc_attr( $age ); ?>" style="flex-grow: <?php echo esc_attr( (string) $waiting['ages'][ $age ] ); ?>"></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<ul class="iftp-btn-ages__legend">
						<?php foreach ( $ages as $age => $label ) : ?>
							<li class="iftp-btn-ages__item">
								<span class="iftp-btn-ages__key iftp-btn-ages__key--<?php echo esc_attr( $age ); ?>" aria-hidden="true"></span>
								<span class="iftp-btn-ages__name"><?php echo esc_html( $label ); ?></span>
								<span class="iftp-btn-ages__value"><?php echo esc_html( $this->presenter->format_amount( $waiting['ages'][ $age ] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( array() !== $waiting['oldest'] ) : ?>
					<p class="iftp-btn-waiting__subtitle"><?php esc_html_e( 'Waiting longest', 'ifthenpay-pay-button' ); ?></p>
					<ul class="iftp-btn-waiting__list">
						<?php foreach ( $waiting['oldest'] as $record ) : ?>
							<?php $this->render_waiting_row( $record ); ?>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<footer class="iftp-btn-tile__foot">
				<span class="iftp-btn-waiting__auto">
					<?php
					if ( $days > 0 ) {
						/* translators: %s: number of days */
						echo esc_html( sprintf( _n( 'Auto-reminder after %s day', 'Auto-reminders after %s days', $days, 'ifthenpay-pay-button' ), number_format_i18n( $days ) ) );
					} else {
						esc_html_e( 'Auto-reminders off', 'ifthenpay-pay-button' );
					}
					?>
				</span>
				<?php if ( array() !== $refs ) : ?>
					<button type="button" class="iftp-btn-btn iftp-btn-btn--primary" data-iftp-remind-all="<?php echo esc_attr( (string) wp_json_encode( $refs ) ); ?>">
						<?php
						/* translators: %s: number of customers */
						echo esc_html( sprintf( __( 'Remind all (%s)', 'ifthenpay-pay-button' ), number_format_i18n( count( $refs ) ) ) );
						?>
					</button>
				<?php endif; ?>
			</footer>
		</section>
		<?php
	}

	/**
	 * One pending payment in the Money waiting list.
	 *
	 * @param array<string, mixed> $record The transaction row.
	 * @phpstan-param Transaction $record
	 */
	private function render_waiting_row( array $record ): void {
		$name = '' !== $record['customer_name'] ? $record['customer_name'] : ( '' !== $record['customer_email'] ? $record['customer_email'] : $record['ref'] );
		?>
		<li class="iftp-btn-waiting__row">
			<?php $this->presenter->print_avatar( $record['customer_name'], $record['customer_email'], 32 ); ?>
			<span class="iftp-btn-waiting__who">
				<a class="iftp-btn-waiting__name" href="<?php echo esc_url( EntriesPage::entry_url( $record['ref'] ) ); ?>"><?php echo esc_html( $name ); ?></a>
				<span class="iftp-btn-waiting__age">
					<?php
					/* translators: %s: how long ago, e.g. "3 days" */
					echo esc_html( sprintf( __( 'waiting %s', 'ifthenpay-pay-button' ), human_time_diff( $record['created_at'] ) ) );

					if ( $record['reminded_at'] > 0 ) {
						/* translators: %s: how long ago, e.g. "2 hours" */
						echo ' · ' . esc_html( sprintf( __( 'reminded %s ago', 'ifthenpay-pay-button' ), human_time_diff( $record['reminded_at'] ) ) );
					}
					?>
				</span>
			</span>
			<span class="iftp-btn-waiting__money"><?php echo esc_html( $this->presenter->format_amount( $record['amount'] ) ); ?></span>
			<?php if ( PaymentEmailSender::can_email( $record ) ) : ?>
				<button type="button" class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-waiting__remind" data-iftp-remind="<?php echo esc_attr( $record['ref'] ); ?>" data-iftp-remind-to="<?php echo esc_attr( $record['customer_email'] ); ?>">
					<?php esc_html_e( 'Remind', 'ifthenpay-pay-button' ); ?>
				</button>
			<?php else : ?>
				<span class="iftp-btn-waiting__no-email" title="<?php esc_attr_e( 'No customer email, or recorded by hand.', 'ifthenpay-pay-button' ); ?>"><?php esc_html_e( 'No email', 'ifthenpay-pay-button' ); ?></span>
			<?php endif; ?>
		</li>
		<?php
	}

	/**
	 * The status cards' change pills, one per period; the period switch shows the matching one.
	 *
	 * @param array<string, mixed> $periods Every period from Insights.
	 * @param string               $status  The card's status.
	 * @phpstan-param array<string, Period> $periods
	 */
	public function print_status_deltas( array $periods, string $status ): void {
		// Paid up is good news; failed or expired up is bad news; pending just moves.
		$up_is = array(
			'paid'    => 'good',
			'pending' => 'neutral',
			'failed'  => 'bad',
			'expired' => 'bad',
		);
		?>
		<span class="iftp-btn-summary__trend">
			<?php foreach ( $periods as $key => $period ) : ?>
				<?php if ( isset( $period['statuses'][ $status ] ) ) : ?>
					<span class="iftp-btn-summary__delta" data-iftp-period-delta="<?php echo esc_attr( $key ); ?>"<?php echo $this->period === $key ? '' : ' hidden'; ?>>
						<?php $this->presenter->print_delta( $period['statuses'][ $status ], $period['compare'], $up_is[ $status ] ?? 'neutral' ); ?>
						<span class="iftp-btn-summary__compare" aria-hidden="true"><?php echo esc_html( $period['compare'] ); ?></span>
					</span>
				<?php endif; ?>
			<?php endforeach; ?>
		</span>
		<?php
	}

	/**
	 * Every period, for the status cards.
	 *
	 * @return array<string, Period>
	 */
	public function periods(): array {
		return $this->insights->all();
	}

	/**
	 * The chart's numbers as day and amount pairs, for screen readers: the chart itself is a picture of these.
	 *
	 * @param array<string, mixed> $period A period from Insights.
	 * @phpstan-param Period $period
	 */
	private function render_numbers( array $period ): void {
		$title_id = 'iftp-btn-insights-numbers-' . sanitize_html_class( $period['key'] );
		?>
		<div class="screen-reader-text">
			<p id="<?php echo esc_attr( $title_id ); ?>">
				<?php
				/* translators: %s: period, e.g. "7 days" */
				echo esc_html( sprintf( __( 'Paid revenue, last %s', 'ifthenpay-pay-button' ), $period['label'] ) );
				?>
			</p>
			<dl aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
				<?php foreach ( $period['series']['tooltips'] as $i => $label ) : ?>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $this->presenter->format_amount( (float) ( $period['series']['values'][ $i ] ?? 0 ) ) ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<?php
	}
}
