<?php
/**
 * An AI provider the merchant can connect.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each provider lists its models and answers a chat with JSON in a given shape, its own way: Gemini with a
 * response schema, Claude with a forced tool call, ChatGPT with a strict JSON schema. The key only ever goes in a
 * request header, never a URL, so it can't end up in a proxy or server log. Every answer is capped at MAX_TOKENS,
 * so one message can't run up the merchant's bill.
 *
 * @phpstan-type ChatMessage array{role: string, text: string}
 */
abstract class Provider {

	// A reply that takes a while to think still fits; most come back in 5–20 seconds.
	protected const TIMEOUT = 90;

	// The most output tokens an answer may use. A whole button fits in a fraction of it.
	protected const MAX_TOKENS = 8192;

	/**
	 * How long this call may wait, in seconds.
	 *
	 * @var int
	 */
	private int $timeout = self::TIMEOUT;

	/**
	 * How hard it thinks before it answers: quick, balanced or deep.
	 *
	 * @var string
	 */
	protected string $thinking = 'quick';

	/**
	 * How freely it picks its words and colours.
	 *
	 * @var float
	 */
	protected float $temperature = 0.7;

	/**
	 * Shortens the wait, so a fallback still fits in what's left of the request.
	 *
	 * @param int $seconds At most this long.
	 */
	public function set_timeout( int $seconds ): void {
		$this->timeout = max( 10, min( self::TIMEOUT, $seconds ) );
	}

	/**
	 * The merchant's choices from Configuration › AI. A model that can't take one just ignores it.
	 *
	 * @param string $thinking    One of AiSettings::THINKING.
	 * @param float  $temperature The sampling temperature.
	 */
	public function set_style( string $thinking, float $temperature ): void {
		$this->thinking    = $thinking;
		$this->temperature = $temperature;
	}

	/**
	 * The provider's id, as stored.
	 */
	abstract public function id(): string;

	/**
	 * Its name, as the merchant knows it.
	 */
	abstract public function label(): string;

	/**
	 * Who makes it, under its name: "by Google".
	 */
	abstract public function maker(): string;

	/**
	 * Where the merchant makes a key.
	 */
	abstract public function key_url(): string;

	/**
	 * The models this key can use for chat, id => name, best first.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>|WP_Error
	 */
	abstract public function models( string $key );

	/**
	 * Answers the chat with an object in the schema's shape.
	 *
	 * @param string                  $key      The API key.
	 * @param string                  $model    The model id.
	 * @param string                  $system   The instructions.
	 * @param array<int, ChatMessage> $messages The chat so far, oldest first, ending with the merchant's turn.
	 * @param array<string, mixed>    $schema   A JSON schema with lowercase types and every property required.
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function complete( string $key, string $model, string $system, array $messages, array $schema );

	/**
	 * The model a new connection starts on: the newest fast, stable one, since the chat waits on it.
	 *
	 * @param array<string, string> $models What models() gave.
	 */
	public function default_model( array $models ): string {
		$ids = array_keys( $models );

		return $ids[0] ?? '';
	}

	/**
	 * Sends a request and decodes the JSON answer, turning every failure into a message the merchant can act on.
	 *
	 * @param string                $method  GET or POST.
	 * @param string                $url     The endpoint.
	 * @param array<string, string> $headers Headers, the key among them.
	 * @param array<string, mixed>  $body    The JSON body, for a POST.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function request( string $method, string $url, array $headers, array $body = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => $this->timeout,
			'headers' => $headers + array( 'Accept' => 'application/json' ),
		);

		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = (string) wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$timeout = false !== stripos( $response->get_error_message(), 'timed out' );

			return new WP_Error(
				$timeout ? 'iftp_btn_ai_timeout' : 'iftp_btn_ai_unreachable',
				$timeout
					/* translators: %s: the AI provider, e.g. Gemini */
					? sprintf( __( '%s took too long to answer. Try again, or ask for a smaller change.', 'ifthenpay-pay-button' ), $this->label() )
					/* translators: %s: the AI provider, e.g. Gemini */
					: sprintf( __( 'Could not reach %s from this site. Check that the server can make outgoing requests.', 'ifthenpay-pay-button' ), $this->label() )
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : array();

		if ( $code >= 200 && $code < 300 ) {
			return $decoded;
		}

		return $this->http_error( $code, is_string( $decoded['error']['message'] ?? null ) ? $decoded['error']['message'] : '' );
	}

	/**
	 * What a failed status means for the merchant. All three providers put their reason in error.message.
	 *
	 * @param int    $code   The HTTP status.
	 * @param string $reason The provider's own words, if any.
	 */
	protected function http_error( int $code, string $reason ): WP_Error {
		$reason = mb_substr( sanitize_text_field( $reason ), 0, 300 );

		if ( 401 === $code || 403 === $code ) {
			/* translators: %s: the AI provider, e.g. Gemini */
			return new WP_Error( 'iftp_btn_ai_key', sprintf( __( '%s refused the API key. Check it, or connect a new one.', 'ifthenpay-pay-button' ), $this->label() ) );
		}

		if ( 404 === $code ) {
			/* translators: %s: the AI provider, e.g. Gemini */
			return new WP_Error( 'iftp_btn_ai_gone', sprintf( __( '%s says this model isn\'t available to your key any more. Pick another one in Configuration › AI.', 'ifthenpay-pay-button' ), $this->label() ) );
		}

		if ( 429 === $code ) {
			/* translators: %s: the AI provider, e.g. Gemini */
			return new WP_Error( 'iftp_btn_ai_quota', sprintf( __( '%s says this key is over its limit or out of credit. Wait a minute, or check the billing on your account.', 'ifthenpay-pay-button' ), $this->label() ) );
		}

		// Google's 504: the model ran out of its own time, so it's a slow answer, not an overload.
		if ( 504 === $code ) {
			/* translators: %s: the AI provider, e.g. Gemini */
			return new WP_Error( 'iftp_btn_ai_timeout', sprintf( __( '%s took too long to answer. Try again, or ask for a smaller change.', 'ifthenpay-pay-button' ), $this->label() ) );
		}

		if ( $code >= 500 ) {
			/* translators: %s: the AI provider, e.g. Gemini */
			return new WP_Error( 'iftp_btn_ai_busy', sprintf( __( '%s is overloaded right now. Try again in a moment.', 'ifthenpay-pay-button' ), $this->label() ) );
		}

		return new WP_Error(
			400 === $code && false !== stripos( $reason, 'schema' ) ? 'iftp_btn_ai_schema' : 'iftp_btn_ai_refused',
			'' !== $reason
				/* translators: 1: the AI provider, e.g. Gemini, 2: its error message */
				? sprintf( __( '%1$s did not accept the request: %2$s', 'ifthenpay-pay-button' ), $this->label(), $reason )
				/* translators: %s: the AI provider, e.g. Gemini */
				: sprintf( __( '%s did not accept the request.', 'ifthenpay-pay-button' ), $this->label() )
		);
	}

	/**
	 * Decodes the JSON a model wrote as text, forgiving a Markdown fence around it.
	 *
	 * @param string $text The model's text.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function decode_text( string $text ) {
		$text    = trim( (string) preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( $text ) ) );
		$decoded = json_decode( $text, true );

		if ( ! is_array( $decoded ) ) {
			return $this->cut_off();
		}

		return $decoded;
	}

	/**
	 * The answer was not usable, usually because it ran out of room.
	 */
	protected function cut_off(): WP_Error {
		/* translators: %s: the AI provider, e.g. Gemini */
		return new WP_Error( 'iftp_btn_ai_cut_off', sprintf( __( '%s sent back an answer I could not read. Try again, or ask for a smaller change.', 'ifthenpay-pay-button' ), $this->label() ) );
	}

	/**
	 * The chat as the APIs want it: starting with the merchant, never two turns from the same side in a row.
	 *
	 * @param array<int, array<string, string>> $messages The chat.
	 * @return array<int, ChatMessage>
	 */
	protected static function turns( array $messages ): array {
		$turns = array();

		foreach ( $messages as $message ) {
			$role = 'assistant' === ( $message['role'] ?? '' ) ? 'assistant' : 'user';
			$text = (string) ( $message['text'] ?? '' );
			$last = count( $turns ) - 1;

			if ( $last >= 0 && $turns[ $last ]['role'] === $role ) {
				$turns[ $last ] = array(
					'role' => $role,
					'text' => $turns[ $last ]['text'] . "\n\n" . $text,
				);

				continue;
			}

			if ( array() === $turns && 'assistant' === $role ) {
				continue;
			}

			$turns[] = array(
				'role' => $role,
				'text' => $text,
			);
		}

		return $turns;
	}

	/**
	 * Picks the best model of a family: the highest version, stable before preview.
	 *
	 * @param array<int, string> $ids     Model ids.
	 * @param string             $pattern A regex whose first group is the version, e.g. 2.5 or 4-5.
	 * @return array<int, string> The matching ids, best first.
	 */
	protected static function rank( array $ids, string $pattern ): array {
		$scored = array();

		foreach ( $ids as $id ) {
			if ( 1 !== preg_match( $pattern, $id, $match ) ) {
				continue;
			}

			$version       = (float) str_replace( '-', '.', $match[1] );
			$unstable      = 1 === preg_match( '/preview|exp|beta|alpha/', $id ) ? 1 : 0;
			$scored[ $id ] = array( $unstable, -$version, strlen( $id ) );
		}

		uasort(
			$scored,
			static fn( array $a, array $b ): int => $a <=> $b
		);

		return array_keys( $scored );
	}
}
