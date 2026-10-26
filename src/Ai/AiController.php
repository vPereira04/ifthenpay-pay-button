<?php
/**
 * The AI chat's requests.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Frontend\PayPage;
use Ifthenpay\PayButton\Plugin;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only AJAX: connect, disconnect and pick a model; send a message, undo, start over; and the preview page.
 * Every call checks the nonce and the admin capability. The key comes in once, on connect, and never goes back out.
 */
class AiController {

	public const NONCE = 'iftp_btn_ai';

	/**
	 * Buttons storage.
	 *
	 * @var ButtonRepository
	 */
	private ButtonRepository $buttons;

	/**
	 * The AI connection.
	 *
	 * @var Connection
	 */
	private Connection $connection;

	/**
	 * The chat.
	 *
	 * @var Studio
	 */
	private Studio $studio;

	/**
	 * Sets up the controller.
	 *
	 * @param ButtonRepository $buttons    Buttons storage.
	 * @param Connection|null  $connection The AI connection.
	 */
	public function __construct( ButtonRepository $buttons, ?Connection $connection = null ) {
		$this->buttons    = $buttons;
		$this->connection = $connection ?? new Connection();
		$this->studio     = new Studio( $this->connection );
	}

	/**
	 * Hooks every action.
	 */
	public function register(): void {
		add_action( 'wp_ajax_iftp_btn_ai_connect', array( $this, 'connect' ) );
		add_action( 'wp_ajax_iftp_btn_ai_disconnect', array( $this, 'disconnect' ) );
		add_action( 'wp_ajax_iftp_btn_ai_model', array( $this, 'model' ) );
		add_action( 'wp_ajax_iftp_btn_ai_send', array( $this, 'send' ) );
		add_action( 'wp_ajax_iftp_btn_ai_poll', array( $this, 'poll' ) );
		add_action( 'wp_ajax_iftp_btn_ai_undo', array( $this, 'undo' ) );
		add_action( 'wp_ajax_iftp_btn_ai_reset', array( $this, 'reset' ) );
		add_action( 'wp_ajax_iftp_btn_ai_preview', array( $this, 'preview' ) );
	}

	/**
	 * Where a conversation's preview loads from.
	 *
	 * @param string $target A button id, or Studio::NEW_TARGET.
	 */
	public static function preview_url( string $target ): string {
		return add_query_arg(
			array(
				'action'   => 'iftp_btn_ai_preview',
				'target'   => $target,
				'_wpnonce' => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Checks the key with its provider and stores it sealed.
	 */
	public function connect(): void {
		$this->guard();

		$result = $this->connection->connect( $this->text( 'provider' ), $this->text( 'key' ) );

		if ( is_wp_error( $result ) ) {
			$this->fail( $result );
		}

		wp_send_json_success( $this->connection->summary() );
	}

	/**
	 * Forgets the key.
	 */
	public function disconnect(): void {
		$this->guard();
		$this->connection->disconnect();

		wp_send_json_success();
	}

	/**
	 * Switches the model.
	 */
	public function model(): void {
		$this->guard();

		if ( ! $this->connection->set_model( $this->text( 'model' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'That model isn\'t available for this key.', 'ifthenpay-pay-button' ) ), 400 );
		}

		wp_send_json_success( $this->connection->summary() );
	}

	/**
	 * Sends the merchant's message and answers with the new version of the button.
	 */
	public function send(): void {
		$this->guard();

		$saved = $this->target();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$thread  = $this->studio->send( $saved, $message, $this->text( 'job' ) );

		if ( is_wp_error( $thread ) ) {
			$this->fail( $thread );
		}

		wp_send_json_success( Studio::view( $thread, self::preview_url( Studio::target( $saved ) ) ) );
	}

	/**
	 * How a message went, when the browser lost the request: a proxy in front of the site can give up on a slow
	 * answer that PHP is still waiting for.
	 */
	public function poll(): void {
		$this->guard();

		$saved  = $this->target();
		$status = $this->studio->job( $this->text( 'job' ) );

		if ( 'done' === $status['state'] ) {
			wp_send_json_success( array( 'state' => 'done' ) + Studio::view( $this->studio->thread( $saved ), self::preview_url( Studio::target( $saved ) ) ) );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Steps back one answer.
	 */
	public function undo(): void {
		$this->guard();

		$saved = $this->target();

		wp_send_json_success( Studio::view( $this->studio->undo( $saved ), self::preview_url( Studio::target( $saved ) ) ) );
	}

	/**
	 * Starts over.
	 */
	public function reset(): void {
		$this->guard();

		$saved = $this->target();
		$this->studio->forget( $saved );

		wp_send_json_success( Studio::view( $this->studio->thread( $saved ), self::preview_url( Studio::target( $saved ) ) ) );
	}

	/**
	 * The pay page for the conversation's latest version, inside the studio's frame.
	 */
	public function preview(): void {
		check_ajax_referer( self::NONCE );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ), '', array( 'response' => 403 ) );
		}

		$saved  = $this->target();
		$thread = $this->studio->thread( $saved );
		$latest = Studio::latest( $thread );
		$button = new Button( 0, '' !== $latest['title'] ? $latest['title'] : __( 'Your title', 'ifthenpay-pay-button' ), '', true, $latest['config'] );

		( new PayPage( $this->buttons, new ButtonRenderer() ) )->preview( $button );
		exit;
	}

	/**
	 * Stops anyone without the nonce or the admin capability.
	 */
	private function guard(): void {
		check_ajax_referer( self::NONCE );

		if ( ! current_user_can( Plugin::admin_cap() ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'ifthenpay-pay-button' ) ), 403 );
		}
	}

	/**
	 * The button the request is about: a saved one, or null for a new one. An unknown id stops here.
	 */
	private function target(): ?Button {
		// phpcs:ignore WordPress.Security.NonceVerification -- checked by every caller first.
		$raw = isset( $_REQUEST['target'] ) ? sanitize_key( wp_unslash( $_REQUEST['target'] ) ) : '';

		if ( Studio::NEW_TARGET === $raw ) {
			return null;
		}

		$button = $this->buttons->find( absint( $raw ) );

		if ( null === $button ) {
			wp_send_json_error( array( 'message' => __( 'That button no longer exists.', 'ifthenpay-pay-button' ) ), 404 );
		}

		return $button;
	}

	/**
	 * A posted one-line value. The key is never logged or echoed back.
	 *
	 * @param string $name The field.
	 */
	private function text( string $name ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * Sends an error as JSON, and stops.
	 *
	 * @param WP_Error $error The error.
	 * @phpstan-return never
	 */
	private function fail( WP_Error $error ): void {
		wp_send_json_error(
			array(
				'message' => $error->get_error_message(),
				'code'    => $error->get_error_code(),
			),
			400
		);
	}
}
