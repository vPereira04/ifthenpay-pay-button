<?php
/**
 * The Performance section on top of the Entries list.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Reports\Insights;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The revenue chart for 7 days, 30 days and 12 months, and the revenue by method under it. All three periods
 * render up front; the tabs only show and hide them, so switching is instant.
 *
 * @phpstan-import-type Period from Insights
 */
class InsightsView {

	// The period the tabs open on.
	private const OPEN_PERIOD = '7d';

	/**
	 * The numbers.
	 *
	 * @var Insights
	 */
	private Insights $insights;

	/**
	 * Shared cell rendering (delta pills, methods, amounts).
	 *
	 * @var EntryPresenter
	 */
	private EntryPresenter $presenter;

	/**
	 * Sets up the view.
	 *
	 * @param Insights       $insights  The numbers.
	 * @param EntryPresenter $presenter Shared cell rendering.
	 */
	public function __construct( Insights $insights, EntryPresenter $presenter ) {
		$this->insights  = $insights;
		$this->presenter = $presenter;
	}

	/**
	 * The data a chart container carries for charts.js.
	 *
	 * @param array<string, mixed> $series  A period's series.
	 * @param string               $label   What's plotted, for the tooltip and screen readers.
	 * @param int                  $height  The chart's height in px.
	 * @param bool                 $compact Sparkline style: no axes, no hover.
	 */
	public static function chart_data( array $series, string $label, int $height, bool $compact = false ): string {
		global $wp_locale;

		// The site's own separators, so the chart writes amounts exactly like the rest of the screen ("1.234,56 €").
		$separators = $wp_locale instanceof \WP_Locale ? $wp_locale->number_format : array();

		return (string) wp_json_encode(
			array(
				'label'     => $label,
				'labels'    => $series['labels'],
				'tooltips'  => $series['tooltips'],
				'values'    => $series['values'],
				'locale'    => str_replace( '_', '-', get_user_locale() ),
				'currency'  => 'EUR',
				'height'    => $height,
				'compact'   => $compact,
				'decimal'   => html_entity_decode( (string) ( $separators['decimal_point'] ?? '.' ), ENT_QUOTES, 'UTF-8' ),
				'thousands' => html_entity_decode( (string) ( $separators['thousands_sep'] ?? ',' ), ENT_QUOTES, 'UTF-8' ),
			)
		);
	}

	/**
	 * Renders the section: one card, the title and period switch on top, then the chart.
	 */
	public function render(): void {
		$periods = $this->insights->all();
		$current = self::OPEN_PERIOD;
		?>
		<section class="iftp-btn-insights" aria-labelledby="iftp-btn-insights-title" data-iftp-insights>
			<header class="iftp-btn-insights__head">
				<h2 id="iftp-btn-insights-title" class="iftp-btn-insights__title"><?php esc_html_e( 'Performance', 'ifthenpay-pay-button' ); ?></h2>
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
	 * One period's chart, with its numbers for screen readers.
	 *
	 * @param array<string, mixed> $period  A period from Insights.
	 * @param bool                 $visible Whether it's the one showing.
	 * @phpstan-param Period $period
	 */
	private function render_panel( array $period, bool $visible ): void {
		$key = $period['key'];
		?>
		<div class="iftp-btn-insights__panel" role="tabpanel" id="iftp-btn-period-<?php echo esc_attr( $key ); ?>" aria-labelledby="iftp-btn-period-tab-<?php echo esc_attr( $key ); ?>" data-iftp-period-panel="<?php echo esc_attr( $key ); ?>"<?php echo $visible ? '' : ' hidden'; ?>>
			<figure class="iftp-btn-insights__figure">
				<div class="iftp-btn-chart iftp-btn-chart--hero" data-iftp-chart="<?php echo esc_attr( self::chart_data( $period['series'], $period['kpis']['revenue']['label'], 180 ) ); ?>" data-iftp-chart-hint="<?php esc_attr_e( 'Use the arrow keys to read each point.', 'ifthenpay-pay-button' ); ?>"></div>
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
				<div class="iftp-btn-tile__body" data-iftp-period-delta="<?php echo esc_attr( $key ); ?>"<?php echo self::OPEN_PERIOD === $key ? '' : ' hidden'; ?>>
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
