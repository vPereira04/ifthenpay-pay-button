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
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An admin can have many chats, each on one button (or on a "new button"), kept for the days set in Configuration › AI;
 * Chats keeps the list. Each answer stacks a version of the button, so Undo steps back one at a time. Nothing touches
 * the saved button: the editor opens the latest version unsaved, and the merchant's own Save is what keeps it, and
 * erases the chat that built it.
 *
 * If the saved button changes in the meantime, the chat keeps what was said but carries on from the saved version,
 * since its old versions would undo that work.
 *
 * The message being answered is kept as "pending" until the answer lands, so a reload or a failure never loses it:
 * the screen picks the wait back up, or offers to send it again.
 *
 * @phpstan-import-type Settings from AiSettings
 * @phpstan-import-type ButtonConfig from Button
 * @phpstan-type Version array{title: string, config: ButtonConfig}
 * @phpstan-type Message array{role: string, text: string, changes?: array<int, array{text: string, money: bool}>, note?: string}
 * @phpstan-type Pending array{text: string, job: string, error: string}
 * @phpstan-type Thread array{id: string, target: string, base: string, messages: array<int, Message>, versions: array<int, Version>, pending?: Pending}
 */
class Studio {

	public const NEW_TARGET = 'new';
	// The site's Default Design, chatted about like a button; its stand-in has this id.
	public const SITE_TARGET = 'site';
	public const SITE_ID     = -1;
	public const MAX_MESSAGE = 2000;

	// How many steps Undo can take back.
	private const UNDO = 10;

	// Seconds one message may take at the least, every retry and fallback included. A longer wait per model raises it (see budget()).
	private const BUDGET = 170;

	// How many other models to try when the picked one fails, if the merchant allows it.
	private const FALLBACKS = 3;

	// The longest one call may wait, by how hard the merchant lets it think. A call that hangs is cut, so the next model gets the time.
	private const ATTEMPT = array(
		'quick'    => 45,
		'balanced' => 70,
		'deep'     => 100,
	);

	// Failures another try, or another model, can get past. A rate limit is per model, so another model is not limited.
	private const RETRYABLE = array( 'iftp_btn_ai_big', 'iftp_btn_ai_busy', 'iftp_btn_ai_timeout', 'iftp_btn_ai_gone', 'iftp_btn_ai_cut_off', 'iftp_btn_ai_quota', 'iftp_btn_ai_schema' );

	/**
	 * The AI connection.
	 *
	 * @var Connection
	 */
	private Connection $connection;

	/**
	 * The merchant's limits and preferences.
	 *
	 * @var array<string, mixed>
	 * @phpstan-var Settings
	 */
	private array $settings;

	/**
	 * Sets up the studio.
	 *
	 * @param Connection|null $connection The AI connection.
	 */
	public function __construct( ?Connection $connection = null ) {
		$this->connection = $connection ?? new Connection();
		$this->settings   = AiSettings::get();
	}

	/**
	 * The target a button's conversation is filed under.
	 *
	 * @param Button|null $button The saved button, or null for a new one.
	 */
	public static function target( ?Button $button ): string {
		if ( null !== $button && self::SITE_ID === $button->id ) {
			return self::SITE_TARGET;
		}

		return null !== $button ? (string) $button->id : self::NEW_TARGET;
	}

	/**
	 * The stand-in the chat edits for the Default Design: a button holding the site's checkout look and button design.
	 * Nothing else of it is used, and it is never saved as a button.
	 */
	public static function site_button(): Button {
		$settings = new CheckoutSettings();
		$config   = Button::defaults();

		$config['look_mode'] = Button::LOOK_OWN;
		$config['look']      = $settings->get_look();
		$config['design']    = $settings->get_button_design();

		return new Button( self::SITE_ID, __( 'Default design', 'ifthenpay-pay-button' ), 'default-design', true, $config );
	}

	/**
	 * Whether a button is the stand-in for the Default Design.
	 *
	 * @param Button|null $button The button.
	 */
	public static function is_site( ?Button $button ): bool {
		return null !== $button && self::SITE_ID === $button->id;
	}

	/**
	 * A version of the Default Design: the answer's look and button design on the saved form and texts, which the
	 * Default Design doesn't hold. The look is always its own, and the design can't follow itself.
	 *
	 * @param array<string, mixed> $version The AI's version.
	 * @phpstan-param Version $version
	 * @param array<string, mixed> $base    The version it changed.
	 * @phpstan-param Version $base
	 * @return Version
	 */
	private static function site_version( array $version, array $base ): array {
		$config = $base['config'];

		$config['look']      = $version['config']['look'];
		$config['look_mode'] = Button::LOOK_OWN;
		$config['design']    = $version['config']['design'];

		if ( ! in_array( $config['design']['source'], ButtonDesign::OWN_SOURCES, true ) ) {
			$config['design']['source'] = ButtonDesign::SOURCE_THEME;
		}

		return array(
			'title'  => $base['title'],
			'config' => Button::sanitize_config( $config ),
		);
	}

	/**
	 * A chat's conversation, or a fresh one when there is none under that id.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @param string      $chat  The chat's id.
	 * @return Thread
	 */
	public function thread( ?Button $saved, string $chat ): array {
		$target = self::target( $saved );
		$base   = self::fingerprint( $saved );
		$fresh  = array(
			'id'       => $chat,
			'target'   => $target,
			'base'     => $base,
			'messages' => array(),
			'versions' => array(
				array(
					'title'  => null !== $saved ? $saved->title : '',
					'config' => null !== $saved ? $saved->config : Button::defaults(),
				),
			),
		);
		$stored = Chats::load( $chat );

		if ( null === $stored || ( $stored['target'] ?? null ) !== $target || ! is_array( $stored['messages'] ?? null ) || ! is_array( $stored['versions'] ?? null ) || array() === $stored['versions'] ) {
			return $fresh;
		}

		/**
		 * Written by this class only.
		 *
		 * @var Thread $stored
		 */
		if ( $stored['base'] !== $base ) {
			// The button was saved since: what was said stays, but the versions would undo that save.
			$stored['base']     = $base;
			$stored['versions'] = $fresh['versions'];
			unset( $stored['pending'] );
		}

		return $stored;
	}

	/**
	 * The merchant's message in, the AI's new version of the button out.
	 *
	 * The work carries on even if a proxy in front of the site gives up on the request first; the browser then
	 * asks job() how it went.
	 *
	 * @param Button|null $saved   The saved button, or null for a new one.
	 * @param string      $chat    The chat's id.
	 * @param string      $message What the merchant wrote.
	 * @param string      $job     The browser's id for this message, for job().
	 * @return Thread|WP_Error
	 */
	public function send( ?Button $saved, string $chat, string $message, string $job = '' ) {
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

		$began             = time();
		$thread            = $this->thread( $saved, $chat );
		$thread['pending'] = array(
			'text'  => $message,
			'job'   => $job,
			'error' => '',
		);
		$this->save( $thread );

		// Whatever ends this request, even a fatal error or the time limit, the next message gets through.
		register_shutdown_function( array( $this, 'finish' ), $job );
		ignore_user_abort( true );

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 2 * $this->budget() + 30 );
		}

		$current = self::latest( $thread );
		$state   = ButtonSchema::from_config( $current['config'], $current['title'] );
		$turns   = array_map(
			static fn( array $turn ): array => array(
				'role' => $turn['role'],
				'text' => $turn['text'],
			),
			array_slice( $thread['messages'], -$this->settings['memory'] )
		);

		$turns[] = array(
			'role' => 'user',
			'text' => Prompt::turn( $state, $message, self::is_site( $saved ) ),
		);

		// Drawing needs room to think: a quick setting is raised one step for a request that draws, whatever the merchant chose.
		$thinking = $this->settings['thinking'];

		if ( 'quick' === $thinking && Prompt::draws( $message ) ) {
			$thinking = 'balanced';
		}

		$provider->set_style( $thinking, AiSettings::temperature( $this->settings['creativity'] ) );
		$provider->set_answer_size( $this->settings['answer'] );

		// A button being made from nothing comes back whole, so no part of its design is left to the defaults; every change after that is a patch.
		$patch = $provider->patches() && ( null !== $saved || count( $thread['versions'] ) > 1 );
		// A change to the form or the texts doesn't need the long art direction, nor the look's explanations: fewer tokens in, a quicker answer.
		$site = self::is_site( $saved );
		// Everything about the Default Design is the look.
		$design = $site || ! $patch || Prompt::wants_design( $message );
		$result = $this->ask( $provider, $stored, Prompt::instructions( null === $saved, $this->settings['prices'], $patch, $design, $site ), $turns, $job, $patch, $design, $site );

		// Deleted while it was thinking: the answer has nowhere to go, and saving would bring the chat back.
		if ( null === Chats::load( $chat ) ) {
			$gone = new WP_Error( 'iftp_btn_ai_chat_gone', __( 'That chat was deleted.', 'ifthenpay-pay-button' ) );
			$this->set_job(
				$job,
				array(
					'state'   => 'error',
					'message' => $gone->get_error_message(),
				)
			);

			return $gone;
		}

		if ( is_wp_error( $result['answer'] ) ) {
			$thread['pending']['error'] = $result['answer']->get_error_message();
			$this->save( $thread );
			$this->set_job(
				$job,
				array(
					'state'   => 'error',
					'message' => $result['answer']->get_error_message(),
				)
			);

			return $result['answer'];
		}

		$answer = $result['answer'];
		$note   = '';

		// Drawn code (the button's, or the checkout's) that the checks refuse is sent back once to be fixed, if there is time; otherwise the button keeps its old drawing.
		$problem = ButtonSchema::drawn_problem( $answer );
		// A layout fault is only worth one more try; the drawing is still used if it is not fixed.
		$layout = null === $problem ? ButtonSchema::layout_problem( $answer ) : null;
		$fault  = $problem ?? $layout;

		if ( null !== $fault && time() - $began < $this->budget() / 3 ) {
			$again = $this->ask(
				$provider,
				$stored,
				Prompt::instructions( null === $saved, $this->settings['prices'], $patch, $design, $site ),
				array_merge(
					$turns,
					array(
						array(
							'role' => 'assistant',
							'text' => (string) wp_json_encode( $answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
						),
						array(
							'role' => 'user',
							'text' => ( null !== $problem ? 'Your code was refused: ' : 'Your drawing has a layout fault: ' ) . $fault . ' Send the whole answer again with the code corrected; change nothing else.',
						),
					)
				),
				$job,
				$patch,
				$design,
				$site
			);

			if ( ! is_wp_error( $again['answer'] ) ) {
				$answer = $again['answer'];
				$result = $again;
			}

			$problem = ButtonSchema::drawn_problem( $answer );
		}

		if ( null !== $problem ) {
			$note = ' ' . __( 'The code did not pass the safety checks, so the button and the checkout keep their last drawing.', 'ifthenpay-pay-button' );
		}

		// A change that isn't about the button leaves it alone, whatever the answer sent for it.
		if ( $patch && ! $site && ! Prompt::touches_button( $message ) ) {
			unset( $answer['button'] );
		}

		// The same for a drawn checkout: a change that isn't about the look never redraws it.
		if ( $patch && ! $site && ! Prompt::wants_design( $message ) && is_array( $answer['checkout'] ?? null ) ) {
			unset( $answer['checkout']['code'], $answer['checkout']['css'], $answer['checkout']['js'] );
		}

		$version = ButtonSchema::to_config( $answer, $current['config'], '' !== $current['title'] ? $current['title'] : __( 'Pay button', 'ifthenpay-pay-button' ), $this->settings['prices'] );

		// The Default Design holds the look and the button's design only; whatever else the answer touched is dropped.
		if ( self::is_site( $saved ) ) {
			$version = self::site_version( $version, $current );
		}

		$reply = is_scalar( $answer['reply'] ?? null ) ? mb_substr( trim( sanitize_textarea_field( (string) $answer['reply'] ) ), 0, 1000 ) : '';
		$said  = array(
			'role'    => 'assistant',
			'text'    => ( '' !== $reply ? $reply : __( 'Done. Have a look at the preview.', 'ifthenpay-pay-button' ) ) . $note,
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
		unset( $thread['pending'] );

		$this->save( $thread );
		$this->set_job( $job, array( 'state' => 'done' ) );

		return $thread;
	}

	/**
	 * How a message the browser stopped waiting for went: working, done or error (with why).
	 *
	 * @param string $job The browser's id for the message.
	 * @return array{state: string, message?: string, stage?: string, model?: string}
	 */
	public function job( string $job ): array {
		$status = '' !== $job ? get_transient( $this->job_key( $job ) ) : false;

		if ( ! is_array( $status ) || ! is_string( $status['state'] ?? null ) ) {
			return array( 'state' => 'unknown' );
		}

		return array_intersect_key( $status, array_flip( array( 'state', 'message', 'stage', 'model' ) ) );
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
	 * Asks the picked model; when it's overloaded, rate limited, gone or too slow, asks it once more if that can help, and then the models
	 * the provider thinks most likely to answer, while the time lasts. A call that hangs is cut early, so one slow model can't use up the
	 * time of the rest, and after a failure the next calls think less, since they have to be quick.
	 *
	 * @param Provider                          $provider The provider.
	 * @param array<string, mixed>              $stored   The connection.
	 * @phpstan-param array{provider: string, key: string, model: string, models: array<string, string>, since: int} $stored
	 * @param string                            $system   The instructions.
	 * @param array<int, array<string, string>> $chat     The chat.
	 * @phpstan-param array<int, array{role: string, text: string}> $chat
	 * @param string                            $job      The browser's id for the message, to tell it what's going on.
	 * @param bool                              $patch    Whether the answer may hold only what changes.
	 * @param bool                              $design   Whether the look is in play.
	 * @param bool                              $site     Whether it is the site's Default Design, which needs a smaller schema.
	 * @return array{answer: array<string, mixed>|WP_Error, model: string}
	 */
	private function ask( Provider $provider, array $stored, string $system, array $chat, string $job, bool $patch, bool $design, bool $site = false ): array {
		$started = time();
		$others  = $this->settings['fallback'] ? array_slice( $provider->fallbacks( $stored['models'], $stored['model'] ), 0, self::FALLBACKS ) : array();
		$tries   = array_merge( array( $stored['model'] ), $others );
		$cap     = $this->wait();
		$answer  = new WP_Error( 'iftp_btn_ai_off', __( 'Connect an AI first.', 'ifthenpay-pay-button' ) );
		$model   = $stored['model'];
		$first   = '';
		$code    = '';
		$calls   = 0;

		foreach ( $tries as $index => $model ) {
			// The picked model gets a second go only when waiting a moment can help.
			for ( $round = 0; $round < ( 0 === $index ? 2 : 1 ); $round++ ) {
				$left = $this->budget() - ( time() - $started );

				if ( $left < 15 ) {
					break 2;
				}

				if ( 1 === $round ) {
					$wait = $this->pause( $provider, $code );

					if ( 0 === $wait ) {
						break;
					}

					sleep( $wait );
				}

				// So the screen can say it's retrying or switching, instead of looking stuck.
				if ( $calls > 0 ) {
					$this->set_job(
						$job,
						array(
							'state' => 'working',
							'stage' => $round > 0 ? 'retry' : 'fallback',
							'model' => $stored['models'][ $model ] ?? $model,
						)
					);
					$provider->set_style( 'quick', AiSettings::temperature( $this->settings['creativity'] ) );
				}

				$provider->set_timeout( min( $this->budget() - ( time() - $started ), $cap ) );
				$answer = $provider->complete( $stored['key'], $model, $system, $chat, ButtonSchema::schema( $patch, $design, $site ) );
				$code   = is_wp_error( $answer ) ? (string) $answer->get_error_code() : '';
				$first  = 0 === $calls ? $code : $first;
				++$calls;

				if ( '' === $code || ! in_array( $code, self::RETRYABLE, true ) ) {
					break 2;
				}
			}
		}

		// A model the key lost is no use as the default; the one that answered takes its place.
		if ( ! is_wp_error( $answer ) && $model !== $stored['model'] && 'iftp_btn_ai_gone' === $first ) {
			$this->connection->set_model( $model );
		}

		return array(
			'answer' => is_wp_error( $answer ) ? self::worn_out( $answer, $calls ) : $answer,
			'model'  => $model,
		);
	}

	/**
	 * How long to wait before the same model gets another go: a moment for an overload, what the provider asked for on a rate limit,
	 * and never for anything else.
	 *
	 * @param Provider $provider The provider that just failed.
	 * @param string   $code     How it failed.
	 * @return int Seconds, or 0 for no second go.
	 */
	private function pause( Provider $provider, string $code ): int {
		if ( 'iftp_btn_ai_busy' === $code ) {
			return wp_rand( 2, 3 );
		}

		$asked = $provider->retry_after();

		return 'iftp_btn_ai_quota' === $code && $asked >= 1 && $asked <= 15 ? $asked + 1 : 0;
	}

	/**
	 * When every model tried was overloaded, slow or limited, says so, and what helps, instead of the last model's own complaint.
	 *
	 * @param WP_Error $error The last failure.
	 * @param int      $calls How many calls were made.
	 */
	private static function worn_out( WP_Error $error, int $calls ): WP_Error {
		$code = (string) $error->get_error_code();

		if ( $calls < 2 || ! in_array( $code, array( 'iftp_btn_ai_busy', 'iftp_btn_ai_timeout', 'iftp_btn_ai_quota', 'iftp_btn_ai_cut_off' ), true ) ) {
			return $error;
		}

		return new WP_Error(
			$code,
			'iftp_btn_ai_quota' === $code
				? __( 'This key is over its limit on every model I tried. Free keys allow only a few messages a minute: wait a minute and try again.', 'ifthenpay-pay-button' )
				/* translators: %d: how many tries were made */
				: sprintf( _n( 'The AI was too busy or too slow to answer (%d try). Your message is safe: try again in a minute.', 'The AI was too busy or too slow to answer (%d tries, on different models). Your message is safe: try again in a minute.', $calls, 'ifthenpay-pay-button' ), $calls )
		);
	}

	/**
	 * Steps back one answer.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @param string      $chat  The chat's id.
	 * @return Thread
	 */
	public function undo( ?Button $saved, string $chat ): array {
		$thread = $this->thread( $saved, $chat );

		unset( $thread['pending'] );

		if ( count( $thread['versions'] ) > 1 ) {
			array_pop( $thread['versions'] );

			// The answer, and the message that asked for it.
			while ( array() !== $thread['messages'] && 'assistant' === end( $thread['messages'] )['role'] ) {
				array_pop( $thread['messages'] );
			}

			array_pop( $thread['messages'] );
		}

		$this->save( $thread );

		return $thread;
	}

	/**
	 * Starts the chat over: what was said and built in it is cleared.
	 *
	 * @param string $chat The chat's id.
	 */
	public function forget( string $chat ): void {
		Chats::delete( $chat );
	}

	/**
	 * The chat an address asks for, falling back to the button's latest one.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @param string      $chat  The id from the address; anything that isn't one picks the latest.
	 */
	public function resolve( ?Button $saved, string $chat ): string {
		return Chats::valid( $chat ) ? $chat : Chats::latest_for( self::target( $saved ) );
	}

	/**
	 * The button the editor should open with: the chat's latest version, when it made one. A new button starts
	 * off, so the merchant checks it before anyone can pay.
	 *
	 * @param Button|null $saved The saved button, or null for a new one.
	 * @param string      $chat  The chat's id.
	 */
	public function draft( ?Button $saved, string $chat ): ?Button {
		$thread = $this->thread( $saved, $chat );

		if ( count( $thread['versions'] ) < 2 ) {
			return null;
		}

		$latest = self::latest( $thread );

		return null !== $saved
			? new Button( $saved->id, $latest['title'], $saved->slug, $saved->active, $latest['config'] )
			: new Button( 0, $latest['title'], '', false, $latest['config'] );
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
			'chat'     => $thread['id'],
			'messages' => $thread['messages'],
			'title'    => $latest['title'],
			'canUndo'  => count( $thread['versions'] ) > 1,
			'built'    => count( $thread['versions'] ) > 1,
			// The Default Design has no prices.
			'prices'   => self::SITE_TARGET === $thread['target'] ? array() : ButtonSchema::prices( $button->config ),
			'pending'  => $thread['pending'] ?? null,
			// Keyed on the version itself: a count repeats after "Start over" or Undo, and the frame would keep the old page.
			'preview'  => add_query_arg( 'v', substr( md5( (string) wp_json_encode( $latest ) ), 0, 12 ), $preview_url ),
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
	 * The longest one call to a model may wait: the merchant's own choice, or by how hard they let it think.
	 */
	private function wait(): int {
		return $this->settings['wait'] > 0 ? $this->settings['wait'] : ( self::ATTEMPT[ $this->settings['thinking'] ] ?? self::ATTEMPT['quick'] );
	}

	/**
	 * Seconds one message may take, every retry and fallback included: room for a second model after a slow first one.
	 */
	private function budget(): int {
		return max( self::BUDGET, min( 480, 2 * $this->wait() ) );
	}

	/**
	 * Lets one call through at a time per admin, within the hourly cap per admin and the daily cap for the site.
	 *
	 * @return true|WP_Error
	 */
	private function open_gate() {
		$user = get_current_user_id();
		$busy = 'iftp_btn_ai_busy_' . $user;
		$rate = 'iftp_btn_ai_rate_' . $user;
		$day  = 'iftp_btn_ai_day_' . gmdate( 'Ymd' );

		if ( false !== get_transient( $busy ) ) {
			return new WP_Error( 'iftp_btn_ai_busy', __( 'Still working on your last message. Give it a moment.', 'ifthenpay-pay-button' ) );
		}

		$window = get_transient( $rate );
		$window = is_array( $window ) && isset( $window['count'], $window['until'] ) ? $window : array(
			'count' => 0,
			'until' => time() + HOUR_IN_SECONDS,
		);

		if ( (int) $window['count'] >= $this->settings['hourly'] ) {
			return new WP_Error( 'iftp_btn_ai_rate', __( 'That\'s a lot of messages in one hour. Take a short break, then carry on.', 'ifthenpay-pay-button' ) );
		}

		$today = (int) get_transient( $day );

		if ( $this->settings['daily'] > 0 && $today >= $this->settings['daily'] ) {
			return new WP_Error( 'iftp_btn_ai_daily', __( 'This site reached its AI messages for today. It opens again tomorrow, or an administrator can raise the limit in Configuration › AI.', 'ifthenpay-pay-button' ) );
		}

		set_transient( $day, $today + 1, DAY_IN_SECONDS );

		// The window runs from the first message; later ones keep its end, so it can't stretch forever.
		$window['count'] = (int) $window['count'] + 1;
		set_transient( $rate, $window, max( 1, (int) $window['until'] - time() ) );

		// Freed by finish(); this only covers a process that dies without running it.
		set_transient( $busy, 1, $this->budget() + 60 );

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
	 * Stores the conversation, and notes it in the list of chats.
	 *
	 * @param array<string, mixed> $thread The conversation.
	 * @phpstan-param Thread $thread
	 */
	private function save( array $thread ): void {
		$built = count( $thread['versions'] ) > 1 ? self::latest( $thread )['title'] : '';

		Chats::save( $thread['id'], $thread, $thread['target'], $built );
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
