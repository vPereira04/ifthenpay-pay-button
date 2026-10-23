<?php
/**
 * Shared cell rendering for the Entries list and the single entry page.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Entries;

use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use Ifthenpay\PayButton\Repository\TransactionStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status badges, method pills, amounts, dates and customer blocks, so both screens print them the same way.
 * Everything that's the same on every row is memoized, since a page can hold 250 rows.
 */
class EntryPresenter {

	// The Created column shows "2 hours ago" up to this age, then the short date.
	private const RELATIVE_DATE_MAX_AGE = 30 * DAY_IN_SECONDS;

	// Method names that match no entity code or alias even after method_key() normalizes them.
	private const METHOD_SYNONYMS = array( 'CREDITCARD' => 'CCARD' );

	/**
	 * Settings storage, for the method logos.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Row-invariant strings.
	 *
	 * @var array<string, string>
	 */
	private array $memo = array();

	/**
	 * Connected methods keyed by method_key() of their entity code and alias.
	 *
	 * @var array<string, array{entity: string, logo: string, logo_dark: string}>|null
	 */
	private ?array $method_catalog = null;

	/**
	 * Sets up the presenter.
	 *
	 * @param SettingsRepository $settings Settings storage.
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * The status slug => label map. Cancelled is folded into "Failed" here.
	 *
	 * @param bool $with_all Whether to include the "All" (`''`) entry.
	 * @return array<string, string>
	 */
	public function status_labels( bool $with_all = false ): array {
		$labels = array(
			TransactionStore::STATUS_PENDING => __( 'Pending', 'ifthenpay-pay-button' ),
			TransactionStore::STATUS_PAID    => __( 'Paid', 'ifthenpay-pay-button' ),
			TransactionStore::STATUS_FAILED  => __( 'Failed', 'ifthenpay-pay-button' ),
			TransactionStore::STATUS_EXPIRED => __( 'Expired', 'ifthenpay-pay-button' ),
		);

		return $with_all ? array( '' => __( 'All', 'ifthenpay-pay-button' ) ) + $labels : $labels;
	}

	/**
	 * A status's display label. Cancelled shows as "Failed".
	 *
	 * @param string $status One of TransactionStore::ALL_STATUSES.
	 */
	public function status_label( string $status ): string {
		$status = self::list_status( $status );

		return $this->memo[ 'status_' . $status ] ??= $this->status_labels()[ $status ] ?? $status;
	}

	/**
	 * The status a row files under in the list: cancelled is a kind of failed.
	 *
	 * @param string $status One of TransactionStore::ALL_STATUSES.
	 */
	public static function list_status( string $status ): string {
		return TransactionStore::STATUS_CANCELLED === $status ? TransactionStore::STATUS_FAILED : $status;
	}

	/**
	 * Prints a status badge. The label carries the meaning, never colour alone.
	 *
	 * @param string $status One of TransactionStore::ALL_STATUSES.
	 */
	public function print_status_badge( string $status ): void {
		printf(
			'<span class="iftp-btn-badge iftp-btn-badge--%1$s"><span class="iftp-btn-badge__dot" aria-hidden="true"></span>%2$s</span>',
			esc_attr( self::list_status( $status ) ),
			esc_html( $this->status_label( $status ) )
		);
	}

	/**
	 * Prints the dash for an empty value, the way core list tables do: screen readers get the label, not "em dash".
	 *
	 * @param string $label What's missing, already translated.
	 */
	public function print_empty_value( string $label ): void {
		echo '<span class="iftp-btn-empty-value" aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	}

	/**
	 * An amount in the site's number format, e.g. "4.134,69 €" in Portuguese or "4,134.69 €" in English.
	 *
	 * @param float $amount The amount in euros.
	 */
	public function format_amount( float $amount ): string {
		return number_format_i18n( $amount, 2 ) . ' €';
	}

	/**
	 * The site's date + time format, for the full date on hover.
	 */
	public function datetime_format(): string {
		return $this->memo['datetime_format'] ??= (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
	}

	/**
	 * A compact date + time format, translatable so each locale gets its own order.
	 */
	public function short_datetime_format(): string {
		/* translators: compact date and time format for the payment entries table, see https://www.php.net/manual/datetime.format.php */
		return $this->memo['short_datetime_format'] ??= _x( 'd/m/Y H:i', 'short date and time format', 'ifthenpay-pay-button' );
	}

	/**
	 * Prints a <time>: "2 hours ago" while recent, the short date after that, the full date on hover.
	 *
	 * @param int  $timestamp The unix timestamp.
	 * @param bool $relative  Whether a recent date reads as "… ago".
	 */
	public function print_time( int $timestamp, bool $relative = true ): void {
		$age = time() - $timestamp;

		if ( $relative && $age >= 0 && $age < self::RELATIVE_DATE_MAX_AGE ) {
			/* translators: %s: time since the payment was created, e.g. "2 hours" */
			$label = sprintf( __( '%s ago', 'ifthenpay-pay-button' ), human_time_diff( $timestamp ) );
		} else {
			$label = Dates::local( $this->short_datetime_format(), $timestamp );
		}

		printf(
			'<time datetime="%1$s" title="%2$s">%3$s</time>',
			esc_attr( Dates::local( 'c', $timestamp ) ),
			esc_attr( Dates::local( $this->datetime_format(), $timestamp ) ),
			esc_html( $label )
		);
	}

	/**
	 * Prints a customer as name over email.
	 *
	 * @param string $name  The customer's name, or ''.
	 * @param string $email The customer's email, or ''.
	 */
	public function print_customer( string $name, string $email ): void {
		if ( '' === $name && '' === $email ) {
			$this->print_empty_value( __( 'No customer', 'ifthenpay-pay-button' ) );

			return;
		}
		?>
		<span class="iftp-btn-customer">
			<?php $this->print_avatar( $name, $email, 36 ); ?>
			<span class="iftp-btn-customer__text">
				<?php if ( '' !== $name ) : ?>
					<span class="iftp-btn-customer__name" title="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $name ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $email ) : ?>
					<?php $this->print_email( $email, 'iftp-btn-customer__email' ); ?>
				<?php endif; ?>
			</span>
		</span>
		<?php
	}

	/**
	 * Prints the customer's picture: their Gravatar over their initials. Gravatar's "blank" default is a transparent
	 * image, so someone without one just shows the initials, no JS needed. Decorative: the name sits next to it.
	 * When the site turned avatars off (Settings → Discussion), only the initials show and nothing is fetched.
	 *
	 * @param string $name  The customer's name, or ''.
	 * @param string $email The customer's email, or ''.
	 * @param int    $size  The rendered size in px.
	 */
	public function print_avatar( string $name, string $email, int $size ): void {
		$source = '' !== $name ? $name : (string) strtok( $email, '@' );
		$words  = preg_split( '/[\s._-]+/u', trim( $source ), -1, PREG_SPLIT_NO_EMPTY );
		$words  = is_array( $words ) ? $words : array();
		$letter = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			$letter .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}

		// A stable tint per person, so the same customer always looks the same.
		$tone  = '' !== $source ? ( absint( crc32( strtolower( '' !== $email ? $email : $name ) ) ) % 6 ) + 1 : 0;
		$image = '' !== $email && false !== is_email( $email ) && get_option( 'show_avatars' )
			? get_avatar_url(
				$email,
				array(
					'size'    => $size * 2,
					'default' => 'blank',
				)
			)
			: false;
		?>
		<span class="iftp-btn-avatar iftp-btn-avatar--tone-<?php echo esc_attr( (string) $tone ); ?>" style="--iftp-avatar-size: <?php echo esc_attr( (string) $size ); ?>px" aria-hidden="true">
			<?php if ( '' !== $letter ) : ?>
				<span class="iftp-btn-avatar__initials"><?php echo esc_html( $letter ); ?></span>
			<?php else : ?>
				<svg class="iftp-btn-avatar__glyph" width="16" height="16" viewBox="0 0 16 16" focusable="false"><circle cx="8" cy="5.5" r="2.75" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M2.75 13.5c.75-2.5 2.75-3.75 5.25-3.75s4.5 1.25 5.25 3.75" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
			<?php endif; ?>
			<?php if ( is_string( $image ) && '' !== $image ) : ?>
				<img class="iftp-btn-avatar__img" src="<?php echo esc_url( $image ); ?>" alt="" width="<?php echo esc_attr( (string) $size ); ?>" height="<?php echo esc_attr( (string) $size ); ?>" loading="lazy" decoding="async" />
			<?php endif; ?>
		</span>
		<?php
	}

	/**
	 * Prints a KPI's change as an arrow pill: "▲ 12.4%". The words say it too, for screen readers.
	 *
	 * @param array<string, mixed> $kpi     A KPI from Insights.
	 * @param string               $compare What it's compared with, e.g. "vs previous 7 days".
	 * @param string               $up_is   'good' (revenue, paid), 'bad' (failed, expired) or 'neutral' (pending): colours the arrow.
	 */
	public function print_delta( array $kpi, string $compare, string $up_is = 'good' ): void {
		$trend = is_string( $kpi['trend'] ?? null ) ? $kpi['trend'] : 'flat';
		$tone  = 'neutral';

		if ( 'up' === $trend || 'down' === $trend ) {
			if ( 'neutral' !== $up_is ) {
				$tone = ( 'up' === $trend ) === ( 'good' === $up_is ) ? 'good' : 'bad';
			}
		} elseif ( 'new' === $trend ) {
			$tone = 'new';
		}
		$delta = is_numeric( $kpi['delta'] ?? null ) ? (float) $kpi['delta'] : null;
		$arrow = array(
			'up'   => 'M8 3.5v9M4 7.5l4-4 4 4',
			'down' => 'M8 12.5v-9M4 8.5l4 4 4-4',
			'flat' => 'M3.5 8h9',
			'new'  => 'M8 3v10M3 8h10',
		);

		if ( 'new' === $trend ) {
			$text   = __( 'New', 'ifthenpay-pay-button' );
			$spoken = __( 'nothing to compare with yet', 'ifthenpay-pay-button' );
		} else {
			/* translators: %s: a percentage number */
			$text = sprintf( __( '%s%%', 'ifthenpay-pay-button' ), number_format_i18n( abs( (float) $delta ), 1 ) );

			if ( 'up' === $trend ) {
				/* translators: 1: percentage, 2: e.g. "vs previous 7 days" */
				$spoken = sprintf( __( 'up %1$s %2$s', 'ifthenpay-pay-button' ), $text, $compare );
			} elseif ( 'down' === $trend ) {
				/* translators: 1: percentage, 2: e.g. "vs previous 7 days" */
				$spoken = sprintf( __( 'down %1$s %2$s', 'ifthenpay-pay-button' ), $text, $compare );
			} else {
				/* translators: %s: e.g. "vs previous 7 days" */
				$spoken = sprintf( __( 'no change %s', 'ifthenpay-pay-button' ), $compare );
			}
		}
		?>
		<span class="iftp-btn-delta iftp-btn-delta--<?php echo esc_attr( $trend ); ?> iftp-btn-delta--<?php echo esc_attr( $tone ); ?>">
			<svg class="iftp-btn-delta__arrow" width="12" height="12" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="<?php echo esc_attr( $arrow[ $trend ] ?? $arrow['flat'] ); ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			<span aria-hidden="true"><?php echo esc_html( $text ); ?></span>
			<span class="screen-reader-text"><?php echo esc_html( $spoken ); ?></span>
		</span>
		<?php
	}

	/**
	 * Prints an email as a mailto link. is_email() lets ?, &, # and % through, which would inject mail headers, so those stay plain text.
	 *
	 * @param string $email     The email address.
	 * @param string $css_class The element's class.
	 */
	public function print_email( string $email, string $css_class ): void {
		if ( false === strpbrk( $email, '?&#%' ) ) {
			printf( '<a class="%1$s" href="%2$s" title="%3$s">%4$s</a>', esc_attr( $css_class ), esc_url( 'mailto:' . $email ), esc_attr( $email ), esc_html( $email ) );

			return;
		}

		printf( '<span class="%1$s" title="%2$s">%3$s</span>', esc_attr( $css_class ), esc_attr( $email ), esc_html( $email ) );
	}

	/**
	 * Prints the method as a logo + label pill. It's only known once a payment is paid (or for ad hoc ones), so the rest get a placeholder.
	 *
	 * @param string $pay_method The transaction's pay_method.
	 * @param string $status     The transaction's status.
	 */
	public function print_method( string $pay_method, string $status ): void {
		if ( '' === $pay_method && TransactionStore::STATUS_PENDING === $status ) {
			?>
			<span class="iftp-btn-method-pill iftp-btn-method-pill--placeholder" title="<?php esc_attr_e( 'The customer hasn\'t picked a payment method yet.', 'ifthenpay-pay-button' ); ?>"><?php esc_html_e( 'Awaiting selection', 'ifthenpay-pay-button' ); ?></span>
			<?php
			return;
		}

		if ( '' === $pay_method && TransactionStore::STATUS_EXPIRED === $status ) {
			?>
			<span class="iftp-btn-method-pill iftp-btn-method-pill--placeholder" title="<?php esc_attr_e( 'The payment expired before the customer picked a payment method.', 'ifthenpay-pay-button' ); ?>"><?php esc_html_e( 'Expired payment', 'ifthenpay-pay-button' ); ?></span>
			<?php
			return;
		}

		if ( '' === $pay_method ) {
			$this->print_empty_value( __( 'No payment method', 'ifthenpay-pay-button' ) );

			return;
		}

		if ( TransactionStore::AD_HOC_METHOD === $pay_method ) {
			?>
			<span class="iftp-btn-method-pill iftp-btn-method-pill--adhoc">
				<svg class="iftp-btn-method-pill__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><rect x="1.5" y="4" width="13" height="8" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.25"/><circle cx="8" cy="8" r="1.75" fill="none" stroke="currentColor" stroke-width="1.25"/></svg>
				<?php echo esc_html( $pay_method ); ?>
			</span>
			<?php
			return;
		}

		$logos = $this->method_catalog()[ self::method_key( $pay_method ) ] ?? null;

		if ( null === $logos || '' === $logos['logo'] ) {
			?>
			<span class="iftp-btn-method-pill iftp-btn-method-pill--plain"><?php echo esc_html( $pay_method ); ?></span>
			<?php
			return;
		}
		?>
		<span class="iftp-btn-method-pill" data-entity="<?php echo esc_attr( $logos['entity'] ); ?>">
			<img class="iftp-btn-method-pill__logo" src="<?php echo esc_url( $logos['logo'] ); ?>"<?php echo '' !== $logos['logo_dark'] ? ' data-logo-dark="' . esc_url( $logos['logo_dark'] ) . '"' : ''; ?> alt="" height="16" loading="lazy" />
			<?php echo esc_html( $pay_method ); ?>
		</span>
		<?php
	}

	/**
	 * Connected methods keyed by method_key() of both their entity code and alias, since pay_method can hold either.
	 *
	 * @return array<string, array{entity: string, logo: string, logo_dark: string}>
	 */
	private function method_catalog(): array {
		if ( null !== $this->method_catalog ) {
			return $this->method_catalog;
		}

		$catalog = array();

		foreach ( $this->settings->get_methods() as $method ) {
			if ( '' === $method['entity'] ) {
				continue;
			}

			$logos = array(
				'entity'    => $method['entity'],
				'logo'      => $method['logo'] ?? '',
				'logo_dark' => $method['logo_dark'] ?? '',
			);

			$catalog[ self::method_key( $method['entity'] ) ] = $logos;
			$catalog += array( self::method_key( $method['alias'] ) => $logos );
		}

		unset( $catalog[''] );

		$this->method_catalog = $catalog;

		return $catalog;
	}

	/**
	 * Normalizes a method name: `MB WAY`, `mbway` and `MBWAY` are one key.
	 *
	 * @param string $name An entity code, alias or display name.
	 */
	private static function method_key( string $name ): string {
		$key = (string) preg_replace( '/[^A-Z0-9]/', '', strtoupper( $name ) );

		return self::METHOD_SYNONYMS[ $key ] ?? $key;
	}
}
