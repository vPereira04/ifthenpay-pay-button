<?php
/**
 * The Buttons screen's actions.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Admin\Buttons;

use Ifthenpay\PayButton\Ai\Studio;
use Ifthenpay\PayButton\Bridge\InvoiceHook;
use Ifthenpay\PayButton\Bridge\OutgoingWebhook;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Buttons\Templates;
use Ifthenpay\PayButton\Plugin;
use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save, copy and delete go through admin-post with a per-button nonce and redirect back; the on/off switch
 * and the webhook test are small AJAX calls so the list doesn't reload.
 */
class ButtonsController {

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * Sets up the controller.
	 *
	 * @param ButtonRepository $buttons Buttons storage.
	 */
	public function __construct( ButtonRepository $buttons ) {
		$this->buttons = $buttons;
	}

	/**
	 * Hooks every action.
	 */
	public function register(): void {
		add_action( 'admin_post_' . ButtonsPage::SAVE_ACTION, array( $this, 'save' ) );
		add_action( 'admin_post_' . ButtonsPage::DUPLICATE_ACTION, array( $this, 'duplicate' ) );
		add_action( 'admin_post_' . ButtonsPage::DELETE_ACTION, array( $this, 'delete' ) );
		add_action( 'admin_post_' . ButtonsPage::RESTORE_ACTION, array( $this, 'restore' ) );
		add_action( 'admin_post_' . ButtonsPage::TEMPLATE_ACTION, array( $this, 'start_template' ) );
		add_action( 'admin_post_' . ButtonsPage::SKIP_ACTION, array( $this, 'skip_welcome' ) );
		add_action( 'wp_ajax_iftp_btn_tour_seen', array( $this, 'tour_seen' ) );
		add_action( 'wp_ajax_iftp_btn_toggle_button', array( $this, 'toggle' ) );
		add_action( 'wp_ajax_iftp_btn_test_webhook', array( $this, 'test_webhook' ) );
		add_action( 'wp_ajax_iftp_btn_test_invoice', array( $this, 'test_invoice' ) );
	}

	/**
	 * Saves the editor's form.
	 */
	public function save(): void {
		$id = $this->requested_id();

		check_admin_referer( ButtonsPage::SAVE_ACTION . '_' . $id );
		$this->guard();

		if ( $id > 0 && null === $this->buttons->find( $id ) ) {
			$this->back( ButtonsPage::list_url( array( 'iftp_notice' => 'error' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() above.
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$active = isset( $_POST['active'] ) && '1' === sanitize_key( wp_unslash( $_POST['active'] ) );
		$rotate = isset( $_POST['rotate_secret'] );
		$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$by_ai  = isset( $_POST[ AiStudioPage::DRAFT_VAR ] );
		// The "make it the default" button submits the form too.
		$make_default = isset( $_POST['make_default'] );
		// Button::sanitize_config() cleans every key by its own rules, so here it's only unslashed.
		$config = isset( $_POST['config'] ) && is_array( $_POST['config'] ) ? map_deep( wp_unslash( $_POST['config'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $title ) {
			$title = __( 'Pay button', 'ifthenpay-pay-button' );
		}

		if ( $rotate ) {
			$config['webhook_secret'] = '';
		}

		// "Save and make it the default": its design becomes the site's, and it follows that from now on.
		if ( $make_default ) {
			( new CheckoutSettings() )->set_button_design( ButtonDesign::sanitize( $config['design'] ?? array() ) );
			$config['design'] = array( 'source' => ButtonDesign::SOURCE_SITE ) + ( is_array( $config['design'] ?? null ) ? $config['design'] : array() );
		}

		// The chat that built this version is done with once it's kept; read the button before it changes.
		$chatted = $by_ai && $id > 0 ? $this->buttons->find( $id ) : null;
		$saved   = $this->buttons->save( $id, $title, $slug, $active, $config );

		if ( 0 === $saved ) {
			$this->back( ButtonsPage::list_url( array( 'iftp_notice' => 'error' ) ) );
		}

		if ( $by_ai ) {
			( new Studio() )->forget( $chatted );
		}

		// Back to the tab they saved from.
		$fragment = in_array( $tab, array( 'form', 'design', 'after', 'connect' ), true ) ? '#' . $tab : '';

		$this->back( add_query_arg( 'iftp_notice', $make_default ? 'default' : ( $id > 0 ? 'saved' : 'created' ), ButtonsPage::edit_url( $saved ) ) . $fragment );
	}

	/**
	 * Makes a button from a starter and opens it in the editor. It starts off, so nothing half-set goes live.
	 */
	public function start_template(): void {
		check_admin_referer( ButtonsPage::TEMPLATE_ACTION );
		$this->guard();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked by check_admin_referer() above.
		$key      = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		$template = Templates::get( $key );

		if ( null === $template ) {
			$this->back( ButtonsPage::list_url( array( 'iftp_notice' => 'error' ) ) );
		}

		$config = array_replace_recursive( Button::defaults(), $template['config'] );
		$saved  = $this->buttons->save( 0, $template['title'], '', false, $config );

		if ( 0 === $saved ) {
			$this->back( ButtonsPage::list_url( array( 'iftp_notice' => 'error' ) ) );
		}

		$this->back( add_query_arg( 'iftp_notice', 'started', ButtonsPage::edit_url( $saved ) ) );
	}

	/**
	 * Remembers that this user skipped the welcome screen.
	 */
	public function skip_welcome(): void {
		check_admin_referer( ButtonsPage::SKIP_ACTION );
		$this->guard();

		update_user_meta( get_current_user_id(), ButtonsPage::WELCOME_SKIPPED_META, '1' );

		$this->back( ButtonsPage::list_url() );
	}

	/**
	 * Copies a button.
	 */
	public function duplicate(): void {
		$id = $this->requested_id();

		check_admin_referer( ButtonsPage::DUPLICATE_ACTION . '_' . $id );
		$this->guard();

		$button = $this->buttons->find( $id );
		$copy   = null !== $button ? $this->buttons->duplicate( $button ) : 0;

		$this->back( ButtonsPage::list_url( array( 'iftp_notice' => $copy > 0 ? 'duplicated' : 'error' ) ) );
	}

	/**
	 * Trashes a button, and the notice offers Undo. Its payments stay.
	 */
	public function delete(): void {
		$id = $this->requested_id();

		check_admin_referer( ButtonsPage::DELETE_ACTION . '_' . $id );
		$this->guard();

		$button  = $this->buttons->find( $id );
		$deleted = null !== $button && $this->buttons->delete( $button );

		$this->back(
			ButtonsPage::list_url(
				$deleted ? array(
					'iftp_notice'  => 'deleted',
					'iftp_restore' => (string) $id,
				) : array( 'iftp_notice' => 'error' )
			)
		);
	}

	/**
	 * Undo for a delete: brings the button back as it was.
	 */
	public function restore(): void {
		$id = $this->requested_id();

		check_admin_referer( ButtonsPage::RESTORE_ACTION . '_' . $id );
		$this->guard();

		$this->back( ButtonsPage::list_url( array( 'iftp_notice' => $this->buttons->restore( $id ) ? 'restored' : 'error' ) ) );
	}

	/**
	 * AJAX: turns a button on or off from the list.
	 */
	public function toggle(): void {
		check_ajax_referer( ButtonsPage::AJAX_NONCE );
		$this->guard();

		$button = $this->buttons->find( $this->requested_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by check_ajax_referer() above.
		$active = isset( $_POST['active'] ) && '1' === sanitize_key( wp_unslash( $_POST['active'] ) );

		if ( null === $button || ! $this->buttons->set_active( $button, $active ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not change the button. Please try again.', 'ifthenpay-pay-button' ) ), 400 );
		}

		wp_send_json_success( array( 'active' => $active ) );
	}

	/**
	 * AJAX: remembers that this user has seen the tour, so it doesn't start by itself again.
	 */
	public function tour_seen(): void {
		check_ajax_referer( ButtonsPage::AJAX_NONCE );
		$this->guard();

		update_user_meta( get_current_user_id(), Tour::SEEN_META, '1' );

		wp_send_json_success();
	}

	/**
	 * AJAX: sends a test event to the saved webhook.
	 */
	public function test_webhook(): void {
		check_ajax_referer( ButtonsPage::AJAX_NONCE );
		$this->guard();

		$button = $this->buttons->find( $this->requested_id() );

		if ( null === $button || '' === $button->config['webhook_url'] ) {
			wp_send_json_error( array( 'message' => __( 'Save a webhook address first.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$result = ( new OutgoingWebhook( $this->buttons ) )->send_test( $button );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message' => 0 === $result['code']
						/* translators: %s: the error message */
						? sprintf( __( 'Could not reach it: %s', 'ifthenpay-pay-button' ), $result['message'] )
						/* translators: 1: HTTP status code, 2: HTTP status text */
						: sprintf( __( 'It answered %1$d %2$s.', 'ifthenpay-pay-button' ), $result['code'], $result['message'] ),
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: %d: HTTP status code */
				'message' => sprintf( __( 'Test sent. It answered %d.', 'ifthenpay-pay-button' ), $result['code'] ),
			)
		);
	}

	/**
	 * AJAX: sends the invoicing request as the editor has it now, saved or not, with the latest paid payment.
	 */
	public function test_invoice(): void {
		check_ajax_referer( ButtonsPage::AJAX_NONCE );
		$this->guard();

		$button = $this->buttons->find( $this->requested_id() );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; InvoiceHook::sanitize() cleans each key by its own rules.
		$raw = isset( $_POST['hook'] ) && is_array( $_POST['hook'] ) ? map_deep( wp_unslash( $_POST['hook'] ), static fn( $value ) => is_string( $value ) ? $value : '' ) : array();

		if ( null === $button ) {
			wp_send_json_error( array( 'message' => __( 'Save the button first.', 'ifthenpay-pay-button' ) ), 400 );
		}

		$result = ( new InvoiceHook( $this->buttons ) )->send_test( InvoiceHook::sanitize( $raw ), $button );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'message' => 0 === $result['code']
						/* translators: %s: the error message */
						? sprintf( __( 'Not sent: %s', 'ifthenpay-pay-button' ), $result['message'] )
						/* translators: 1: HTTP status code, 2: HTTP status text */
						: sprintf( __( 'It answered %1$d %2$s.', 'ifthenpay-pay-button' ), $result['code'], $result['message'] ),
					'body'    => $result['body'],
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: %d: HTTP status code */
				'message' => sprintf( __( 'Sent. It answered %d.', 'ifthenpay-pay-button' ), $result['code'] ),
				'body'    => $result['body'],
			)
		);
	}

	/**
	 * Stops anyone without the admin capability.
	 */
	private function guard(): void {
		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * The button ID the request names, from the query or the form.
	 */
	private function requested_id(): int {
		// phpcs:disable WordPress.Security.NonceVerification -- read only to build the nonce action each caller then checks.
		$raw = wp_unslash( $_REQUEST[ ButtonsPage::EDIT_VAR ] ?? 0 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint() below.
		// phpcs:enable WordPress.Security.NonceVerification

		return is_scalar( $raw ) ? absint( $raw ) : 0;
	}

	/**
	 * Redirects and stops.
	 *
	 * @param string $url Where to.
	 */
	private function back( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
