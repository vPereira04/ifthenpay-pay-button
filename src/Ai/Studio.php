<?php
/**
 * The chat that builds a button.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Ai\Providers\Provider;
use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Buttons\ButtonDesign;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One conversation per admin per button (or per "new button"), kept for two days. Each answer stacks a version
 * of the button, so Undo steps back one at a time. Nothing touches the saved button: the editor opens the
 * latest version unsaved, and the merchant's own Save is what keeps it.
 *
 * If the saved button changes in the meantime, its old conversation would undo that work, so it starts again.
 *
 * @phpstan-import-type ButtonConfig from Button
 * @phpstan-type Version array{title: string, config: ButtonConfig}
 * @phpstan-type Message array{role: string, text: string, changes?: array<int, array{text: string, money: bool}>, note?: string}
 * @phpstan-type Thread array{base: string, messages: array<int, Message>, versions: array<int, Version>}
 */
class Studio {

	public const NEW_TARGET  = 'new';
	public const MAX_MESSAGE = 2000;

	// How much of the chat goes back to the AI each time; the button itself always goes in full.
	private const HISTORY = 12;

	// How many steps Undo can take back.
	private const UNDO = 10;

	// A ceiling on calls per admin, so a stuck key or a held-down Enter can't run up a bill.
	private const HOURLY = 60;

	private const TTL = 2 * DAY_IN_SECONDS;

	// Seconds one message may take, every retry and fallback included.
	private const BUDGET = 170;

	// How many other models to try when the picked one fails.
	private const FALLBACKS = 2;

	// Failures another try, or another model, can get past.
	private const RETRYABLE = array( 'iftp_btn_ai_busy', 'iftp_btn_ai_timeout', 'iftp_btn_ai_gone', 'iftp_btn_ai_cut_off' );

	/**
	 * The AI connection.
	 *
	 * @var Connection
	 */
	private Connection $connection;

	/**
	 * Sets up the studio.
	 *
	 * @param Connection|null $connection The AI connection.
	 */
	public function __construct( ?Connection $connection = null ) {
		$this->connection = $connection ?? new Connection();
	}

	/**
	 * The target a button's conversation is filed under.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public static function target( ?Button $button ): string {
		return null !== $button ? (string) $button->id : self::NEW_TARGET;
	}

	/**
	 * The conversation for a button, or a fresh one.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @return Thread
	 */
	public function thread( ?Button $saved ): array {
		$stored = get_transient( $this->key( $saved ) );

		if ( is_array( $stored ) && ( $stored['base'] ?? null ) === self::fingerprint( $saved ) && is_array( $stored['versions'] ?? null ) && array() !== $stored['versions'] ) {
			/**
			 * Written by this class only.
			 *
			 * @var Thread $stored
			 */
			return $stored;
		}

		return array(
			'base'     => self::fingerprint( $saved ),
			'messages' => array(),
			'versions' => array(
				array(
					'title'  => null !== $saved ? $saved->title : '',
					'config' => null !== $saved ? $saved->config : Button::defaults(),
				),
			),
		);
	}

	/**
	 * The merchant's message in, the AI's new version of the button out.
	 *
	 * The work carries on even if a proxy in front of the site gives up on the request first; the browser then
	 * asks job() how it went.
	 *
	 * @param Button|null $saved   The saved button, or null for a new one.
	 * @param string      $message What the merchant wrote.
	 * @param string      $job     The browser's id for this message, for job().
	 * @return Thread|WP_Error
	 */
	public function send( ?Button $saved, string $message, string $job = '' ) {
		$stored   = $this->connection->get();
		$provider = $this->connection->provider();
		$message  = mb_substr( trim( sanitize_textarea_field( $message ) ), 0, self::MAX_MESSAGE );

		if ( null === $stored || null === $provider ) {
			return new WP_Error( 'iftp_btn_ai_off', __( 'Connect an AI first.', 'ifthenpay-pay-button' ) );
		}

		if ( '' === $message ) {
			return new WP_Error( 'iftp_btn_ai_empty', __( 'Write what you want first.', 'ifthenpay-pay-button' ) );
		}

		$gate = $this->open_gate();

		if ( is_wp_error( $gate ) ) {
			return $gate;
		}

		$this->set_job( $job, array( 'state' => 'working' ) );

		// Whatever ends this request, even a fatal error or the time limit, the next message gets through.
		register_shutdown_function( array( $this, 'finish' ), $job );
		ignore_user_abort( true );

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( self::BUDGET + 30 );
		}

		$thread  = $this->thread( $saved );
		$current = self::latest( $thread );
		$state   = ButtonSchema::from_config( $current['config'], $current['title'] );
		$chat    = array_map(
			static fn( array $turn ): array => array(
				'role' => $turn['role'],
				'text' => $turn['text'],
			),
			array_slice( $thread['messages'], -self::HISTORY )
		);

		$chat[] = array(
			'role' => 'user',
			'text' => Prompt::turn( $state, $message ),
		);

		$result = $this->ask( $provider, $stored, Prompt::instructions( null === $saved ), $chat );

		if ( is_wp_error( $result['answer'] ) ) {
			$this->set_job(
				$job,
				array(
					'state'   => 'error',
					'message' => $result['answer']->get_error_message(),
				)
			);

			return $result['answer'];
		}

		$answer  = $result['answer'];
		$version = ButtonSchema::to_config( $answer, $current['config'], '' !== $current['title'] ? $current['title'] : __( 'Pay button', 'ifthenpay-pay-button' ) );
		$reply   = is_scalar( $answer['reply'] ?? null ) ? mb_substr( trim( sanitize_textarea_field( (string) $answer['reply'] ) ), 0, 1000 ) : '';
		$said    = array(
			'role'    => 'assistant',
			'text'    => '' !== $reply ? $reply : __( 'Done. Have a look at the preview.', 'ifthenpay-pay-button' ),
			'changes' => ButtonSchema::changes( $state, ButtonSchema::from_config( $version['config'], $version['title'] ) ),
		);

		if ( $result['model'] !== $stored['model'] ) {
			$said['note'] = sprintf(
				/* translators: 1: the model that answered, 2: the model that was picked */
				__( 'Answered by %1$s, because %2$s didn\'t.', 'ifthenpay-pay-button' ),
				$stored['models'][ $result['model'] ] ?? $result['model'],
				$stored['models'][ $stored['model'] ] ?? $stored['model']
			);
		}

		$thread['messages'][] = array(
			'role' => 'user',
			'text' => $message,
		);
		$thread['messages'][] = $said;
		$thread['versions'][] = $version;
		$thread['versions']   = array_slice( $thread['versions'], -( self::UNDO + 1 ) );

		$this->save( $saved, $thread );
		$this->set_job( $job, array( 'state' => 'done' ) );

		return $thread;
	}

	/**
	 * How a message the browser stopped waiting for went: working, done or error (with why).
	 *
	 * @param string $job The browser's id for the message.
	 * @return array{state: string, message?: string}
	 */
	public function job( string $job ): array {
		$status = '' !== $job ? get_transient( $this->job_key( $job ) ) : false;

		if ( ! is_array( $status ) || ! is_string( $status['state'] ?? null ) ) {
			return array( 'state' => 'unknown' );
		}

		return array( 'state' => $status['state'] ) + ( is_string( $status['message'] ?? null ) ? array( 'message' => $status['message'] ) : array() );
	}

	/**
	 * Lets the next message through, and marks a job that never got to say how it went.
	 *
	 * @param string $job The browser's id for the message.
	 */
	public function finish( string $job ): void {
		delete_transient( 'iftp_btn_ai_busy_' . get_current_user_id() );

		if ( 'working' === $this->job( $job )['state'] ) {
			$this->set_job(
				$job,
				array(
					'state'   => 'error',
					'message' => __( 'The server stopped before the answer came back. Try again, or pick a faster model.', 'ifthenpay-pay-button' ),
				)
			);
		}
	}

	/**
	 * Asks the picked model; when it's overloaded, gone or too slow, asks it once more and then the next best
	 * models the key has, while the time lasts.
	 *
	 * @param Provider                          $provider The provider.
	 * @param array<string, mixed>              $stored   The connection.
	 * @phpstan-param array{provider: string, key: string, model: string, models: array<string, string>, since: int} $stored
	 * @param string                            $system   The instructions.
	 * @param array<int, array<string, string>> $chat     The chat.
	 * @phpstan-param array<int, array{role: string, text: string}> $chat
	 * @return array{answer: array<string, mixed>|WP_Error, model: string}
	 */
	private function ask( Provider $provider, array $stored, string $system, array $chat ): array {
		$started  = time();
		$fallback = array_slice( array_values( array_diff( array_keys( $stored['models'] ), array( $stored['model'] ) ) ), 0, self::FALLBACKS );
		$tries    = array_merge( array( $stored['model'], $stored['model'] ), $fallback );
		$answer   = new WP_Error( 'iftp_btn_ai_off', __( 'Connect an AI first.', 'ifthenpay-pay-button' ) );
		$model    = $stored['model'];
		$first    = '';
		$code     = '';

		foreach ( $tries as $index => $model ) {
			$left = self::BUDGET - ( time() - $started );

			// The same model again only when it was briefly overloaded.
			if ( $left < 15 || ( 1 === $index && 'iftp_btn_ai_busy' !== $code ) ) {
				continue;
			}

			if ( 1 === $index ) {
				sleep( 2 );
			}

			$provider->set_timeout( $left );
			$answer = $provider->complete( $stored['key'], $model, $system, $chat, ButtonSchema::schema() );
			$code   = is_wp_error( $answer ) ? (string) $answer->get_error_code() : '';
			$first  = 0 === $index ? $code : $first;

			if ( '' === $code || ! in_array( $code, self::RETRYABLE, true ) ) {
				break;
			}
		}

		// A model the key lost is no use as the default; the one that answered takes its place.
		if ( ! is_wp_error( $answer ) && $model !== $stored['model'] && 'iftp_btn_ai_gone' === $first ) {
			$this->connection->set_model( $model );
		}

		return array(
			'answer' => $answer,
			'model'  => $model,
		);
	}

	/**
	 * Steps back one answer.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @return Thread
	 */
	public function undo( ?Button $saved ): array {
		$thread = $this->thread( $saved );

		if ( count( $thread['versions'] ) > 1 ) {
			array_pop( $thread['versions'] );

			// The answer, and the message that asked for it.
			while ( array() !== $thread['messages'] && 'assistant' === end( $thread['messages'] )['role'] ) {
				array_pop( $thread['messages'] );
			}

			array_pop( $thread['messages'] );
			$this->save( $saved, $thread );
		}

		return $thread;
	}

	/**
	 * Starts the conversation over.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 */
	public function forget( ?Button $saved ): void {
		delete_transient( $this->key( $saved ) );
	}

	/**
	 * The button the editor should open with: the latest version, when the chat made one. A new button starts
	 * off, so the merchant checks it before anyone can pay.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 */
	public function draft( ?Button $saved ): ?Button {
		$thread = $this->thread( $saved );

		if ( count( $thread['versions'] ) < 2 ) {
			return null;
		}

		$latest = self::latest( $thread );

		return null !== $saved
			? new Button( $saved->id, $latest['title'], $saved->slug, $saved->active, $latest['config'] )
			: new Button( 0, $latest['title'], '', true, $latest['config'] );
	}

	/**
	 * What the screen needs to show a conversation.
	 *
	 * @param array<string, mixed> $thread      The conversation.
	 * @phpstan-param Thread $thread
	 * @param string               $preview_url Where its preview loads from, before the version number.
	 * @return array<string, mixed>
	 */
	public static function view( array $thread, string $preview_url ): array {
		$latest = self::latest( $thread );
		$button = new Button( 0, $latest['title'], '', true, $latest['config'] );

		return array(
			'messages' => $thread['messages'],
			'title'    => $latest['title'],
			'canUndo'  => count( $thread['versions'] ) > 1,
			'built'    => count( $thread['versions'] ) > 1,
			'button'   => ButtonDesign::preview_markup( $button->config['design'], $button->display_label() ),
			'prices'   => ButtonSchema::prices( $button->config ),
			'preview'  => add_query_arg( 'v', (string) count( $thread['messages'] ), $preview_url ),
		);
	}

	/**
	 * The conversation's latest version of the button.
	 *
	 * @param array<string, mixed> $thread The conversation.
	 * @phpstan-param Thread $thread
	 * @return Version
	 */
	public static function latest( array $thread ): array {
		return $thread['versions'][ count( $thread['versions'] ) - 1 ];
	}

	/**
	 * Lets one call through at a time per admin, and no more than HOURLY an hour.
	 *
	 * @return true|WP_Error
	 */
	private function open_gate() {
		$user = get_current_user_id();
		$busy = 'iftp_btn_ai_busy_' . $user;
		$rate = 'iftp_btn_ai_rate_' . $user;

		if ( false !== get_transient( $busy ) ) {
			return new WP_Error( 'iftp_btn_ai_busy', __( 'Still working on your last message. Give it a moment.', 'ifthenpay-pay-button' ) );
		}

		$window = get_transient( $rate );
		$window = is_array( $window ) && isset( $window['count'], $window['until'] ) ? $window : array(
			'count' => 0,
			'until' => time() + HOUR_IN_SECONDS,
		);

		if ( (int) $window['count'] >= self::HOURLY ) {
			return new WP_Error( 'iftp_btn_ai_rate', __( 'That\'s a lot of messages in one hour. Take a short break, then carry on.', 'ifthenpay-pay-button' ) );
		}

		// The window runs from the first message; later ones keep its end, so it can't stretch forever.
		$window['count'] = (int) $window['count'] + 1;
		set_transient( $rate, $window, max( 1, (int) $window['until'] - time() ) );

		// Freed by finish(); this only covers a process that dies without running it.
		set_transient( $busy, 1, self::BUDGET + 60 );

		return true;
	}

	/**
	 * Notes how a message went, for job().
	 *
	 * @param string               $job    The browser's id for the message.
	 * @param array<string, mixed> $status Its state, and an error's message.
	 */
	private function set_job( string $job, array $status ): void {
		if ( '' !== $job ) {
			set_transient( $this->job_key( $job ), $status, 10 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Where a job's status is kept.
	 *
	 * @param string $job The browser's id for the message.
	 */
	private function job_key( string $job ): string {
		return 'iftp_btn_ai_job_' . get_current_user_id() . '_' . substr( sanitize_key( $job ), 0, 32 );
	}

	/**
	 * Stores the conversation.
	 *
	 * @param Button|null          $saved  The saved button, or null for a new one.
	 * @param array<string, mixed> $thread The conversation.
	 * @phpstan-param Thread $thread
	 */
	private function save( ?Button $saved, array $thread ): void {
		set_transient( $this->key( $saved ), $thread, self::TTL );
	}

	/**
	 * Where this admin's conversation for this button is kept.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 */
	private function key( ?Button $saved ): string {
		return 'iftp_btn_ai_chat_' . get_current_user_id() . '_' . self::target( $saved );
	}

	/**
	 * A fingerprint of the saved button, so a conversation knows when it went stale.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 */
	private static function fingerprint( ?Button $saved ): string {
		return null !== $saved ? md5( (string) wp_json_encode( array( $saved->title, $saved->config ) ) ) : '';
	}
}
