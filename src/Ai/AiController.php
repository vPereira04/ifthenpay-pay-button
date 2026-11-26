<?php
/**
 * The AI chat's requests.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Admin\Buttons\AiStudioPage;
use Ifthenpay\PayButton\Admin\Buttons\DemoPage;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonRepository;
use Ifthenpay\PayButton\Plugin;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only AJAX: connect, disconnect and pick a model; open, rename and delete chats; send a message, undo, start over; and the preview page.
 * Every call checks the nonce and the admin capability. The key comes in once, on connect, and never goes back out.
 *
 * @phpstan-import-type Thread from Studio
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
		add_action( 'wp_ajax_iftp_btn_ai_open', array( $this, 'open' ) );
		add_action( 'wp_ajax_iftp_btn_ai_chat_rename', array( $this, 'rename' ) );
		add_action( 'wp_ajax_iftp_btn_ai_chat_delete', array( $this, 'delete' ) );
		add_action( 'wp_ajax_iftp_btn_ai_preview', array( $this, 'preview' ) );
	}

	/**
	 * Where a conversation's preview loads from.
	 *
	 * @param string $target A button id, or Studio::NEW_TARGET.
	 * @param string $chat   The chat's id.
	 */
	public static function preview_url( string $target, string $chat ): string {
		return add_query_arg(
			array(
				'action'   => 'iftp_btn_ai_preview',
				'target'   => $target,
				'chat'     => $chat,
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
		$thread  = $this->studio->send( $saved, $this->chat(), $message, $this->text( 'job' ) );

		if ( is_wp_error( $thread ) ) {
			$this->fail( $thread );
		}

		wp_send_json_success( $this->view( $thread ) );
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
			wp_send_json_success( array( 'state' => 'done' ) + $this->view( $this->studio->thread( $saved, $this->chat() ) ) );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Steps back one answer.
	 */
	public function undo(): void {
		$this->guard();

		wp_send_json_success( $this->view( $this->studio->undo( $this->target(), $this->chat() ) ) );
	}

	/**
	 * Starts the chat over.
	 */
	public function reset(): void {
		$this->guard();

		$saved = $this->target();
		$chat  = $this->chat();
		$this->studio->forget( $chat );

		wp_send_json_success( $this->view( $this->studio->thread( $saved, $chat ) ) );
	}

	/**
	 * Opens a chat, or a new one under an id the browser made up: it exists once its first message is sent.
	 */
	public function open(): void {
		$this->guard();

		wp_send_json_success( $this->view( $this->studio->thread( $this->target(), $this->chat() ) ) );
	}

	/**
	 * Renames a chat; an empty name goes back to the automatic one.
	 */
	public function rename(): void {
		$this->guard();

		if ( ! Chats::rename( $this->chat(), $this->text( 'title' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'That chat no longer exists.', 'ifthenpay-pay-button' ) ), 404 );
		}

		wp_send_json_success( array( 'chats' => self::chats( $this->chat() ) ) );
	}

	/**
	 * Deletes a chat.
	 */
	public function delete(): void {
		$this->guard();

		$chat = $this->chat();
		$this->studio->forget( $chat );

		wp_send_json_success( array( 'chats' => self::chats( '' ) ) );
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
		$thread = $this->studio->thread( $saved, $this->chat() );
		$latest = Studio::latest( $thread );
		$button = new Button( 0, '' !== $latest['title'] ? $latest['title'] : __( 'Your title', 'ifthenpay-pay-button' ), '', true, $latest['config'] );

		// The draft's code hasn't been read by anyone yet, so the page runs sandboxed: it can draw and move, but it can't reach the admin session.
		header( 'Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox' );

		// The same demo page as every other preview: the button first, and the form once it is clicked.
		( new DemoPage( $this->buttons ) )->show( $button );
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

		if ( Studio::SITE_TARGET === $raw ) {
			return Studio::site_button();
		}

		$button = $this->buttons->find( absint( $raw ) );

		if ( null === $button ) {
			wp_send_json_error( array( 'message' => __( 'That button no longer exists.', 'ifthenpay-pay-button' ) ), 404 );
		}

		return $button;
	}

	/**
	 * The chat the request is about. An id that couldn't be one stops here.
	 */
	private function chat(): string {
		// phpcs:ignore WordPress.Security.NonceVerification -- checked by every caller first.
		$id = isset( $_REQUEST['chat'] ) ? sanitize_key( wp_unslash( $_REQUEST['chat'] ) ) : '';

		if ( ! Chats::valid( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That chat is not valid.', 'ifthenpay-pay-button' ) ), 400 );
		}

		return $id;
	}

	/**
	 * What the studio shows for a conversation, with the list of chats beside it.
	 *
	 * @param array<string, mixed> $thread The conversation.
	 * @phpstan-param Thread $thread
	 * @return array<string, mixed>
	 */
	private function view( array $thread ): array {
		return Studio::view( $thread, self::preview_url( $thread['target'], $thread['id'] ) ) + array( 'chats' => self::chats( $thread['id'] ) );
	}

	/**
	 * The admin's chats for the list: newest first, each with where it opens.
	 *
	 * @param string $current The open chat's id, if any.
	 * @return array<int, array<string, mixed>>
	 */
	public static function chats( string $current ): array {
		return array_map(
			static fn( array $entry ): array => array(
				'id'      => $entry['id'],
				'title'   => '' !== $entry['title'] ? $entry['title'] : __( 'Untitled chat', 'ifthenpay-pay-button' ),
				'custom'  => $entry['custom'],
				'target'  => $entry['target'],
				'sub'     => self::chat_subject( $entry['target'] ),
				/* translators: %s: how long ago, e.g. "3 hours" */
				'when'    => sprintf( __( '%s ago', 'ifthenpay-pay-button' ), human_time_diff( $entry['updated'] ) ),
				'url'     => AiStudioPage::chat_url( $entry['target'], $entry['id'] ),
				'current' => $entry['id'] === $current,
			),
			Chats::all()
		);
	}

	/**
	 * What a chat is about, for the list.
	 *
	 * @param string $target A button id, Studio::NEW_TARGET or Studio::SITE_TARGET.
	 */
	private static function chat_subject( string $target ): string {
		if ( Studio::NEW_TARGET === $target ) {
			return __( 'New button', 'ifthenpay-pay-button' );
		}

		if ( Studio::SITE_TARGET === $target ) {
			return __( 'Default design', 'ifthenpay-pay-button' );
		}

		$title = get_post_field( 'post_title', (int) $target, 'raw' );

		return is_string( $title ) ? $title : '';
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
