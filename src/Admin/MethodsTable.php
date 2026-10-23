<?php
/**
 * Gateway payment methods table renderer.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Ajax\SettingsController;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the payment methods table: Enabled, the Default star, the method, its account and its status / activation request.
 *
 * @phpstan-import-type Method from SettingsRepository
 */
class MethodsTable {

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Sets up the table.
	 *
	 * @param SettingsRepository|null $settings Settings storage.
	 */
	public function __construct( ?SettingsRepository $settings = null ) {
		$this->settings = $settings ?? new SettingsRepository();
	}

	/**
	 * The table markup, for the AJAX responses that swap it in.
	 */
	public function render(): string {
		ob_start();
		$this->output();

		return (string) ob_get_clean();
	}

	/**
	 * Prints the full methods table.
	 */
	public function output(): void {
		// I keep IsVisible:false methods in storage for their icon/alias, but ifthenpay isn't offering them.
		$methods        = array_filter( $this->settings->get_methods(), static fn ( array $method ): bool => $method['visible'] ?? true );
		$default_method = $this->settings->get_default_method();
		$gateway_key    = $this->settings->get_gateway_key();

		if ( array() === $methods ) {
			?>
			<p class="iftp-btn-methods__empty"><?php esc_html_e( 'No payment methods loaded yet. Reload this page to fetch them from ifthenpay.', 'ifthenpay-pay-button' ); ?></p>
			<?php
			return;
		}
		?>
		<table class="iftp-btn-methods">
			<caption class="screen-reader-text"><?php esc_html_e( 'Payment methods on this Gateway Key', 'ifthenpay-pay-button' ); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="iftp-btn-methods__col iftp-btn-methods__col--control"><?php esc_html_e( 'Enabled', 'ifthenpay-pay-button' ); ?></th>
					<th scope="col" class="iftp-btn-methods__col iftp-btn-methods__col--control iftp-btn-methods__col--divider"><?php esc_html_e( 'Default', 'ifthenpay-pay-button' ); ?></th>
					<th scope="col" class="iftp-btn-methods__col iftp-btn-methods__col--method"><?php esc_html_e( 'Method', 'ifthenpay-pay-button' ); ?></th>
					<th scope="col" class="iftp-btn-methods__col iftp-btn-methods__col--account"><?php esc_html_e( 'Account', 'ifthenpay-pay-button' ); ?></th>
					<th scope="col" class="iftp-btn-methods__col iftp-btn-methods__col--status"><?php esc_html_e( 'Status', 'ifthenpay-pay-button' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $methods as $method ) : ?>
					<?php $this->render_row( $method, $default_method, $gateway_key ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a single method row.
	 *
	 * @param array<string, mixed> $method         The catalog method row.
	 * @param string               $default_method The currently configured default method entity.
	 * @param string               $gateway_key    The selected Gateway Key, for the activation cooldown.
	 * @phpstan-param Method $method
	 */
	private function render_row( array $method, string $default_method, string $gateway_key ): void {
		$entity      = $method['entity'];
		$alias       = $method['alias'];
		$logo        = $method['logo'] ?? '';
		$logo_dark   = $method['logo_dark'] ?? '';
		$enabled     = (bool) $method['enabled'];
		$provisioned = '' !== $method['account'];
		$is_default  = $enabled && $entity === $default_method;
		$star_id     = 'iftp-btn-default-' . sanitize_html_class( $entity );
		$row_class   = 'iftp-btn-methods__row' . ( $provisioned ? '' : ' iftp-btn-methods__row--unprovisioned' );
		?>
		<tr class="<?php echo esc_attr( $row_class ); ?>" data-iftp-method-row data-entity="<?php echo esc_attr( $entity ); ?>">
			<td class="iftp-btn-methods__cell iftp-btn-methods__cell--control">
				<input
					type="checkbox"
					class="iftp-btn-methods__enabled"
					name="enabled_methods[]"
					value="<?php echo esc_attr( $entity ); ?>"
					data-iftp-method-enabled
					aria-label="<?php /* translators: %s: payment method name, e.g. "MB WAY" */ echo esc_attr( sprintf( __( 'Enable %s', 'ifthenpay-pay-button' ), $alias ) ); ?>"
					<?php checked( $enabled ); ?>
					<?php disabled( ! $provisioned ); ?>
				/>
			</td>
			<td class="iftp-btn-methods__cell iftp-btn-methods__cell--control iftp-btn-methods__cell--divider">
				<?php // A radio group gives "exactly one default, never none" for free; the label is the visible star. ?>
				<input
					type="radio"
					class="screen-reader-text iftp-btn-methods__default"
					id="<?php echo esc_attr( $star_id ); ?>"
					name="default_method"
					value="<?php echo esc_attr( $entity ); ?>"
					data-iftp-method-default
					<?php checked( $is_default ); ?>
					<?php disabled( ! $enabled ); ?>
				/>
				<label
					for="<?php echo esc_attr( $star_id ); ?>"
					class="iftp-btn-star<?php echo $is_default ? ' iftp-btn-star--checked' : ''; ?><?php echo $enabled ? '' : ' iftp-btn-star--hidden'; ?>"
					title="<?php esc_attr_e( 'Default payment method', 'ifthenpay-pay-button' ); ?>"
					data-iftp-star
					<?php echo $enabled ? '' : ' hidden'; ?>
				><span class="iftp-btn-star__glyph" aria-hidden="true" data-iftp-star-glyph><?php echo $is_default ? '&#9733;' : '&#9734;'; ?></span><span class="screen-reader-text">
					<?php
					/* translators: %s: payment method name, e.g. "MB WAY" */
					echo esc_html( sprintf( __( 'Make %s the default payment method', 'ifthenpay-pay-button' ), $alias ) );
					?>
				</span></label>
			</td>
			<td class="iftp-btn-methods__cell iftp-btn-methods__cell--method">
				<span class="iftp-btn-methods__logo-slot">
					<?php if ( '' !== $logo ) : ?>
						<img class="iftp-btn-methods__logo" src="<?php echo esc_url( $logo ); ?>"<?php echo '' !== $logo_dark ? ' data-logo-dark="' . esc_url( $logo_dark ) . '"' : ''; ?> alt="" height="24" loading="lazy" />
					<?php endif; ?>
				</span>
				<span class="iftp-btn-methods__label<?php echo $provisioned ? '' : ' iftp-btn-methods__label--dimmed'; ?>"><?php echo esc_html( $alias ); ?></span>
			</td>
			<td class="iftp-btn-methods__cell iftp-btn-methods__cell--account">
				<?php if ( $provisioned ) : ?>
					<code class="iftp-btn-methods__account"><?php echo esc_html( self::account_display( $entity, $method['account'] ) ); ?></code>
				<?php else : ?>
					<span aria-hidden="true">&#8212;</span><span class="screen-reader-text"><?php esc_html_e( 'No account', 'ifthenpay-pay-button' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="iftp-btn-methods__cell iftp-btn-methods__cell--status">
				<?php if ( $provisioned ) : ?>
					<?php // Both labels ship in the markup so settings.js can swap them live as "Enabled" is toggled. ?>
					<span
						class="iftp-btn-method-status iftp-btn-method-status--<?php echo $enabled ? 'enabled' : 'activated'; ?>"
						data-iftp-method-status
						data-label-enabled="<?php esc_attr_e( 'Enabled', 'ifthenpay-pay-button' ); ?>"
						data-label-activated="<?php esc_attr_e( 'Activated', 'ifthenpay-pay-button' ); ?>"
					><?php echo $enabled ? esc_html__( 'Enabled', 'ifthenpay-pay-button' ) : esc_html__( 'Activated', 'ifthenpay-pay-button' ); ?></span>
				<?php else : ?>
					<?php $requested = false !== get_transient( SettingsController::activation_cooldown_key( $gateway_key, $entity ) ); ?>
					<span class="iftp-btn-method-status iftp-btn-method-status--unavailable iftp-btn-methods__label--dimmed"><?php esc_html_e( 'Not activated', 'ifthenpay-pay-button' ); ?></span>
					<button
						type="button"
						class="iftp-btn-btn iftp-btn-btn--secondary iftp-btn-methods__activate"
						data-iftp-request-activation="<?php echo esc_attr( $entity ); ?>"
						<?php disabled( $requested ); ?>
					><?php echo $requested ? esc_html__( 'Requested', 'ifthenpay-pay-button' ) : esc_html__( 'Request Activation', 'ifthenpay-pay-button' ); ?></button>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Classic Multibanco (numeric Entidade|Subentidade) shows both parts; everything else just its account code.
	 *
	 * @param string $entity  The method entity code.
	 * @param string $account The stored `ENTITY|ACCOUNT` segment.
	 */
	private static function account_display( string $entity, string $account ): string {
		$parts = array_map( 'trim', explode( '|', $account ) );

		if ( 'MB' === $entity && ctype_digit( $parts[0] ) ) {
			return implode( ' | ', $parts );
		}

		return (string) end( $parts );
	}
}
