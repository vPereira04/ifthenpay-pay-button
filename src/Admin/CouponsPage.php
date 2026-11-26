<?php
/**
 * The Discount codes screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin;

use Ifthenpay\PayButton\Admin\Buttons\ButtonsPage;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Checkout\CheckoutService;
use Ifthenpay\PayButton\Dates;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CouponStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the codes with how often each was used, and adds, edits, switches off and deletes them.
 * A button's checkout shows the code box on its own once a code can be used on it.
 *
 * @phpstan-import-type Coupon from CouponStore
 */
class CouponsPage {

	public const PAGE_SLUG   = 'iftp-btn-codes';
	public const SAVE_ACTION = 'iftp_btn_save_coupon';
	public const ROW_ACTION  = 'iftp_btn_coupon_row';

	/**
	 * Codes storage.
	 *
	 * @var CouponStore
	 */
	private CouponStore $coupons;

	/**
	 * Buttons storage, for the "only on" choice.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * The screen's hook suffix.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Sets up the screen.
	 *
	 * @param CouponStore|null      $coupons Codes storage.
	 * @param ButtonRepository|null $buttons Buttons storage.
	 */
	public function __construct( ?CouponStore $coupons = null, ?ButtonRepository $buttons = null ) {
		$this->coupons = $coupons ?? new CouponStore();
		$this->buttons = $buttons ?? new ButtonRepository();
	}

	/**
	 * Hooks the saves and the assets.
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
		add_action( 'admin_post_' . self::ROW_ACTION, array( $this, 'row_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the submenu.
	 *
	 * @param string $parent_slug The parent menu slug.
	 */
	public function add_page( string $parent_slug ): void {
		$hook = add_submenu_page( $parent_slug, __( 'Discount codes', 'ifthenpay-pay-button' ), __( 'Discount codes', 'ifthenpay-pay-button' ), Plugin::admin_cap(), self::PAGE_SLUG, array( $this, 'render' ) );

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * The screen's URL.
	 *
	 * @param array<string, string|int> $args Extra query args, e.g. edit.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Loads the shared admin styles, only here.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}

		wp_enqueue_style( Plugin::ADMIN_STYLE_HANDLE );
		wp_enqueue_style( 'iftp-btn-buttons' );
		wp_enqueue_script( Plugin::UI_SCRIPT_HANDLE );
	}

	/**
	 * Admin-post: adds or changes a code. On a mistake it goes back to the form with what was typed.
	 */
	public function save(): void {
		check_admin_referer( self::SAVE_ACTION );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		$text = static fn( string $key ): string => isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$id   = (int) $text( 'id' );

		$data = array(
			'code'       => $text( 'code' ),
			'type'       => $text( 'type' ),
			'value'      => str_replace( ',', '.', $text( 'value' ) ),
			'min_amount' => str_replace( ',', '.', $text( 'min_amount' ) ),
			'max_uses'   => $text( 'max_uses' ),
			'button_ids' => isset( $_POST['button_ids'] ) && is_array( $_POST['button_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['button_ids'] ) ) : array(),
			'starts_at'  => self::day_to_time( $text( 'starts_at' ), false ),
			'expires_at' => self::day_to_time( $text( 'expires_at' ), true ),
			'active'     => isset( $_POST['active'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = $this->coupons->save( $data, $id );

		if ( is_wp_error( $result ) ) {
			set_transient(
				self::flash_key(),
				array(
					'error' => $result->get_error_message(),
					'data'  => $data + array( 'id' => $id ),
				),
				MINUTE_IN_SECONDS
			);
			wp_safe_redirect( self::url( $id > 0 ? array( 'edit' => $id ) : array( 'new' => 1 ) ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'iftp_notice', $id > 0 ? 'updated' : 'created', self::url() ) );
		exit;
	}

	/**
	 * Admin-post: switches a code on or off, or deletes it, from its row.
	 */
	public function row_action(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the id and the action are part of the nonce name checked next.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$action = isset( $_GET['do'] ) && is_string( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

		check_admin_referer( self::ROW_ACTION . '_' . $action . '_' . $id );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		$coupon = $this->coupons->find( $id );
		$notice = '';

		if ( null !== $coupon ) {
			if ( 'delete' === $action ) {
				$this->coupons->delete( $id );
				$notice = 'deleted';
			} elseif ( 'toggle' === $action ) {
				$this->coupons->set_active( $id, ! $coupon['active'] );
				$notice = $coupon['active'] ? 'off' : 'on';
			}
		}

		wp_safe_redirect( '' !== $notice ? add_query_arg( 'iftp_notice', $notice, self::url() ) : self::url() );
		exit;
	}

	/**
	 * Renders the screen: the codes, then the form to add or change one.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			return;
		}

		$flash   = get_transient( self::flash_key() );
		$flash   = is_array( $flash ) ? $flash : array();
		$coupons = $this->coupons->all();
		$used    = $this->coupons->used_many( array_column( $coupons, 'id' ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags from a redirect after a checked action.
		$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		$editing = $edit_id > 0 ? $this->coupons->find( $edit_id ) : null;
		$notice  = isset( $_GET['iftp_notice'] ) && is_string( $_GET['iftp_notice'] ) ? sanitize_key( wp_unslash( $_GET['iftp_notice'] ) ) : '';
		$form_on = null !== $editing || isset( $_GET['new'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( array() !== $flash ) {
			delete_transient( self::flash_key() );
		}

		$messages = array(
			'created' => __( 'Code added.', 'ifthenpay-pay-button' ),
			'updated' => __( 'Code saved.', 'ifthenpay-pay-button' ),
			'deleted' => __( 'Code deleted.', 'ifthenpay-pay-button' ),
			'on'      => __( 'Code switched on.', 'ifthenpay-pay-button' ),
			'off'     => __( 'Code switched off.', 'ifthenpay-pay-button' ),
		);
		?>
		<div id="iftp-btn-codes" class="wrap iftp-btn-buttons iftp-btn-codes">
			<?php ButtonsPage::print_header( __( 'Discount codes', 'ifthenpay-pay-button' ), '<a class="iftp-btn-btn iftp-btn-btn--primary" href="' . esc_url( self::url( array( 'new' => 1 ) ) ) . '">' . esc_html__( 'Add a code', 'ifthenpay-pay-button' ) . '</a>' ); ?>
			<?php
			if ( isset( $messages[ $notice ] ) ) {
				Toast::add( esc_html( $messages[ $notice ] ) );
			}

			if ( is_string( $flash['error'] ?? null ) ) {
				Toast::add( esc_html( $flash['error'] ), 'error' );
			}
			?>

			<section class="iftp-btn-panel" aria-labelledby="iftp-btn-codes-title">
				<div class="iftp-btn-panel__intro">
					<h2 id="iftp-btn-codes-title" class="iftp-btn-panel__title"><?php esc_html_e( 'Your codes', 'ifthenpay-pay-button' ); ?></h2>
					<p class="iftp-btn-panel__description"><?php esc_html_e( 'A button\'s checkout shows a "Have a discount code?" link once a code can be used on it. A payment that fails or expires gives its use back.', 'ifthenpay-pay-button' ); ?></p>
				</div>
				<div class="iftp-btn-panel__body">
					<?php if ( array() === $coupons ) : ?>
						<p><?php esc_html_e( 'No codes yet. Add one and payers can use it right away.', 'ifthenpay-pay-button' ); ?></p>
					<?php else : ?>
						<div class="iftp-btn-table-wrap" role="region" aria-labelledby="iftp-btn-codes-title" tabindex="0">
							<table class="iftp-btn-table">
								<thead>
									<tr>
										<th class="iftp-btn-table__head" scope="col"><?php esc_html_e( 'Code', 'ifthenpay-pay-button' ); ?></th>
										<th class="iftp-btn-table__head" scope="col"><?php esc_html_e( 'Takes off', 'ifthenpay-pay-button' ); ?></th>
										<th class="iftp-btn-table__head" scope="col"><?php esc_html_e( 'Used', 'ifthenpay-pay-button' ); ?></th>
										<th class="iftp-btn-table__head" scope="col"><?php esc_html_e( 'Valid', 'ifthenpay-pay-button' ); ?></th>
										<th class="iftp-btn-table__head" scope="col"><?php esc_html_e( 'On', 'ifthenpay-pay-button' ); ?></th>
										<th class="iftp-btn-table__head" scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'ifthenpay-pay-button' ); ?></span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $coupons as $coupon ) : ?>
										<?php $this->print_row( $coupon, $used[ $coupon['id'] ] ?? 0 ); ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<?php
			if ( $form_on ) {
				$this->print_form( is_array( $flash['data'] ?? null ) ? $flash['data'] : $editing );
			}
			?>
		</div>
		<?php
	}

	/**
	 * One row of the list.
	 *
	 * @param array<string, mixed> $coupon A code.
	 * @param int                  $used   How many payments hold it.
	 * @phpstan-param Coupon $coupon
	 */
	private function print_row( array $coupon, int $used ): void {
		$format   = (string) get_option( 'date_format' );
		$now      = time();
		$off      = CouponStore::TYPE_PERCENT === $coupon['type'] ? rtrim( rtrim( number_format( $coupon['value'], 2, '.', '' ), '0' ), '.' ) . ' %' : CheckoutService::format_money( $coupon['value'] );
		$ended    = $coupon['expires_at'] > 0 && $coupon['expires_at'] < $now;
		$valid    = '';
		$toggle   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ROW_ACTION . '&do=toggle&id=' . $coupon['id'] ), self::ROW_ACTION . '_toggle_' . $coupon['id'] );
		$delete   = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ROW_ACTION . '&do=delete&id=' . $coupon['id'] ), self::ROW_ACTION . '_delete_' . $coupon['id'] );
		$only_for = array() === $coupon['button_ids'] ? __( 'All buttons', 'ifthenpay-pay-button' ) : sprintf(
			/* translators: %d: how many buttons */
			_n( '%d button', '%d buttons', count( $coupon['button_ids'] ), 'ifthenpay-pay-button' ),
			count( $coupon['button_ids'] )
		);

		if ( $coupon['starts_at'] > 0 || $coupon['expires_at'] > 0 ) {
			$valid = ( $coupon['starts_at'] > 0 ? Dates::local( $format, $coupon['starts_at'] ) : '…' ) . ' – ' . ( $coupon['expires_at'] > 0 ? Dates::local( $format, $coupon['expires_at'] ) : '…' );
		} else {
			$valid = __( 'No end date', 'ifthenpay-pay-button' );
		}
		?>
		<tr class="iftp-btn-table__row">
			<td>
				<strong><?php echo esc_html( $coupon['code'] ); ?></strong>
				<br /><span class="iftp-btn-field__help"><?php echo esc_html( $only_for ); ?><?php echo $coupon['min_amount'] > 0 ? esc_html( ' · ' . sprintf( /* translators: %s: an amount */ __( 'from %s', 'ifthenpay-pay-button' ), CheckoutService::format_money( $coupon['min_amount'] ) ) ) : ''; ?></span>
			</td>
			<td><?php echo esc_html( $off ); ?></td>
			<td><?php echo esc_html( $coupon['max_uses'] > 0 ? $used . ' / ' . $coupon['max_uses'] : (string) $used ); ?></td>
			<td>
				<?php echo esc_html( $valid ); ?>
				<?php if ( $ended ) : ?>
					<br /><span class="iftp-btn-field__help"><?php esc_html_e( 'Ended', 'ifthenpay-pay-button' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo $coupon['active'] ? esc_html__( 'Yes', 'ifthenpay-pay-button' ) : esc_html__( 'No', 'ifthenpay-pay-button' ); ?></td>
			<td>
				<a class="iftp-btn-btn iftp-btn-btn--secondary" href="<?php echo esc_url( self::url( array( 'edit' => $coupon['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'ifthenpay-pay-button' ); ?></a>
				<a class="iftp-btn-btn iftp-btn-btn--secondary" href="<?php echo esc_url( $toggle ); ?>"><?php echo $coupon['active'] ? esc_html__( 'Switch off', 'ifthenpay-pay-button' ) : esc_html__( 'Switch on', 'ifthenpay-pay-button' ); ?></a>
				<a class="iftp-btn-btn iftp-btn-btn--secondary" href="<?php echo esc_url( $delete ); ?>" onclick="return confirm( '<?php echo esc_js( __( 'Delete this code? Payments that used it keep what they paid.', 'ifthenpay-pay-button' ) ); ?>' );"><?php esc_html_e( 'Delete', 'ifthenpay-pay-button' ); ?></a>
			</td>
		</tr>
		<?php
	}

	/**
	 * The add / edit form.
	 *
	 * @param array<string, mixed>|null $coupon The code being changed, what was typed before a mistake, or null for a new one.
	 */
	private function print_form( ?array $coupon ): void {
		$value     = static fn( string $key, string $fallback = '' ): string => is_scalar( $coupon[ $key ] ?? null ) ? (string) $coupon[ $key ] : $fallback;
		$id        = (int) $value( 'id', '0' );
		$type      = CouponStore::TYPE_FIXED === $value( 'type' ) ? CouponStore::TYPE_FIXED : CouponStore::TYPE_PERCENT;
		$picked    = is_array( $coupon['button_ids'] ?? null ) ? array_map( 'intval', $coupon['button_ids'] ) : array();
		$day       = static fn( string $key ): string => (int) $value( $key, '0' ) > 0 ? Dates::local( 'Y-m-d', (int) $value( $key, '0' ) ) : '';
		$is_new    = 0 === $id;
		$is_active = null === $coupon || ! empty( $coupon['active'] );
		?>
		<section class="iftp-btn-panel" aria-labelledby="iftp-btn-code-form-title">
			<div class="iftp-btn-panel__intro">
				<h2 id="iftp-btn-code-form-title" class="iftp-btn-panel__title"><?php echo $is_new ? esc_html__( 'Add a code', 'ifthenpay-pay-button' ) : esc_html__( 'Edit code', 'ifthenpay-pay-button' ); ?></h2>
				<p class="iftp-btn-panel__description"><?php esc_html_e( 'Payers type it in the checkout, in any case. A code never takes a payment below ifthenpay\'s smallest amount, and doesn\'t apply to pay links that carry their own signed amount.', 'ifthenpay-pay-button' ); ?></p>
			</div>
			<div class="iftp-btn-panel__body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="iftp-btn-stack">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
					<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>" />
					<?php wp_nonce_field( self::SAVE_ACTION ); ?>

					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-code-code"><?php esc_html_e( 'Code', 'ifthenpay-pay-button' ); ?></label>
						<input type="text" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-code" name="code" maxlength="40" required value="<?php echo esc_attr( $value( 'code' ) ); ?>" placeholder="SUMMER10" autocapitalize="characters" />
					</div>

					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-code-type"><?php esc_html_e( 'It takes off', 'ifthenpay-pay-button' ); ?></label>
						<span class="iftp-btn-inline">
							<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-value" name="value" min="0.01" step="0.01" required value="<?php echo esc_attr( $value( 'value' ) ); ?>" aria-label="<?php esc_attr_e( 'Amount or percentage', 'ifthenpay-pay-button' ); ?>" />
							<select class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-type" name="type">
								<option value="<?php echo esc_attr( CouponStore::TYPE_PERCENT ); ?>" <?php selected( $type, CouponStore::TYPE_PERCENT ); ?>><?php esc_html_e( '% of the total', 'ifthenpay-pay-button' ); ?></option>
								<option value="<?php echo esc_attr( CouponStore::TYPE_FIXED ); ?>" <?php selected( $type, CouponStore::TYPE_FIXED ); ?>><?php esc_html_e( '€ off the total', 'ifthenpay-pay-button' ); ?></option>
							</select>
						</span>
					</div>

					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-code-min"><?php esc_html_e( 'Works from a total of (€)', 'ifthenpay-pay-button' ); ?></label>
						<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-min" name="min_amount" min="0" step="0.01" value="<?php echo esc_attr( (float) $value( 'min_amount', '0' ) > 0 ? $value( 'min_amount' ) : '' ); ?>" placeholder="0" />
					</div>

					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-code-uses"><?php esc_html_e( 'Can be used', 'ifthenpay-pay-button' ); ?></label>
						<span class="iftp-btn-inline">
							<input type="number" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-uses" name="max_uses" min="0" step="1" value="<?php echo esc_attr( (int) $value( 'max_uses', '0' ) > 0 ? $value( 'max_uses' ) : '' ); ?>" placeholder="∞" />
							<span class="iftp-btn-field__help"><?php esc_html_e( 'times in all. Blank for no limit.', 'ifthenpay-pay-button' ); ?></span>
						</span>
					</div>

					<div class="iftp-btn-field">
						<label class="iftp-btn-field__label" for="iftp-btn-code-from"><?php esc_html_e( 'Valid', 'ifthenpay-pay-button' ); ?></label>
						<span class="iftp-btn-inline">
							<input type="date" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-from" name="starts_at" value="<?php echo esc_attr( $day( 'starts_at' ) ); ?>" aria-label="<?php esc_attr_e( 'From', 'ifthenpay-pay-button' ); ?>" />
							<span aria-hidden="true">–</span>
							<input type="date" class="iftp-btn-field__control iftp-btn-field__control--short" id="iftp-btn-code-to" name="expires_at" value="<?php echo esc_attr( $day( 'expires_at' ) ); ?>" aria-label="<?php esc_attr_e( 'Until, including that day', 'ifthenpay-pay-button' ); ?>" />
						</span>
					</div>

					<fieldset class="iftp-btn-field">
						<legend class="iftp-btn-field__label"><?php esc_html_e( 'Works on', 'ifthenpay-pay-button' ); ?></legend>
						<p class="iftp-btn-field__help"><?php esc_html_e( 'Tick none and it works on every button.', 'ifthenpay-pay-button' ); ?></p>
						<div style="max-height:220px;overflow:auto;">
							<?php foreach ( $this->buttons->all() as $button ) : ?>
								<label class="iftp-btn-check">
									<input type="checkbox" name="button_ids[]" value="<?php echo esc_attr( (string) $button->id ); ?>" <?php checked( in_array( $button->id, $picked, true ) ); ?> />
									<?php echo esc_html( $button->title ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<label class="iftp-btn-np__toggle">
						<input type="checkbox" class="iftp-btn-switch" name="active" value="1" <?php checked( $is_active ); ?> />
						<span class="iftp-btn-np__toggle-text">
							<span class="iftp-btn-np__toggle-title"><?php esc_html_e( 'On', 'ifthenpay-pay-button' ); ?></span>
							<span class="iftp-btn-np__toggle-hint"><?php esc_html_e( 'Off keeps the code but payers can\'t use it.', 'ifthenpay-pay-button' ); ?></span>
						</span>
					</label>

					<p>
						<button type="submit" class="iftp-btn-btn iftp-btn-btn--primary"><?php esc_html_e( 'Save code', 'ifthenpay-pay-button' ); ?></button>
						<a class="iftp-btn-btn iftp-btn-btn--secondary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Cancel', 'ifthenpay-pay-button' ); ?></a>
					</p>
				</form>
			</div>
		</section>
		<?php
	}

	/**
	 * The transient that carries a mistake and what was typed back to the form, for this admin only.
	 */
	private static function flash_key(): string {
		return 'iftp_btn_coupon_flash_' . get_current_user_id();
	}

	/**
	 * A date from the form as a timestamp in the site's time zone: the start of that day, or the end of it for an "until".
	 *
	 * @param string $day   Y-m-d, or ''.
	 * @param bool   $until Whether it is the last day, which runs to the end of it.
	 */
	private static function day_to_time( string $day, bool $until ): int {
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
			return 0;
		}

		$date = date_create_immutable( $day . ( $until ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() );

		return false !== $date ? $date->getTimestamp() : 0;
	}
}
