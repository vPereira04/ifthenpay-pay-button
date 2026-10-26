<?php
/**
 * OpenAI's GPT models.
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
 * Chat Completions with a strict JSON schema, so the reply always parses into our shape.
 */
class ChatGpt extends Provider {

	private const BASE = 'https://api.openai.com/v1/';

	// Reasoning counts against the output budget too.
	protected const MAX_TOKENS = 16000;

	/**
	 * The provider's id, as stored.
	 */
	public function id(): string {
		return 'openai';
	}

	/**
	 * Its name, as the merchant knows it.
	 */
	public function label(): string {
		return 'ChatGPT';
	}

	/**
	 * Who makes it, under its name.
	 */
	public function maker(): string {
		return __( 'by OpenAI', 'ifthenpay-pay-button' );
	}

	/**
	 * Where the merchant makes a key.
	 */
	public function key_url(): string {
		return 'https://platform.openai.com/api-keys';
	}

	/**
	 * The models this key can use for chat, id => name, best first.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>|WP_Error
	 */
	public function models( string $key ) {
		$response = $this->request( 'GET', self::BASE . 'models', $this->headers( $key ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$ids = array();

		foreach ( is_array( $response['data'] ?? null ) ? $response['data'] : array() as $model ) {
			$id = is_string( $model['id'] ?? null ) ? sanitize_text_field( $model['id'] ) : '';

			// Text chat models that take a JSON schema; not speech, images, search or embeddings.
			if ( 1 === preg_match( '/^(gpt-[4-9]|o[1-9])/', $id ) && 1 !== preg_match( '/audio|realtime|tts|transcribe|image|search|instruct|embedding|codex|vision|gpt-4-|gpt-4$/', $id ) ) {
				$ids[] = $id;
			}
		}

		if ( array() === $ids ) {
			return new WP_Error( 'iftp_btn_ai_no_models', __( 'This key has no ChatGPT models available.', 'ifthenpay-pay-button' ) );
		}

		sort( $ids );

		$ordered = array_unique( array_merge( self::rank( $ids, '/^gpt-(\d+(?:\.\d+)?)-mini(?:-\d{4}-\d{2}-\d{2})?$/' ), self::rank( $ids, '/^gpt-(\d+(?:\.\d+)?)(?:-\d{4}-\d{2}-\d{2})?$/' ), $ids ) );

		return array_combine( $ordered, $ordered );
	}

	/**
	 * Answers the chat with an object in the schema's shape.
	 *
	 * @param string                            $key      The API key.
	 * @param string                            $model    The model id.
	 * @param string                            $system   The instructions.
	 * @param array<int, array<string, string>> $messages The chat so far, ending with the merchant's turn.
	 * @param array<string, mixed>              $schema   A JSON schema with lowercase types and every property required.
	 * @return array<string, mixed>|WP_Error
	 */
	public function complete( string $key, string $model, string $system, array $messages, array $schema ) {
		$body = array(
			'model'                 => $model,
			'messages'              => array_merge(
				array(
					array(
						'role'    => 'system',
						'content' => $system,
					),
				),
				array_map(
					static fn( array $turn ): array => array(
						'role'    => $turn['role'],
						'content' => $turn['text'],
					),
					self::turns( $messages )
				)
			),
			'response_format'       => array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'pay_button',
					'strict' => true,
					'schema' => self::strict( $schema ),
				),
			),
			'max_completion_tokens' => static::MAX_TOKENS,
		);

		// Reasoning models think less, so the merchant isn't left waiting; older ones don't take the setting.
		if ( 1 === preg_match( '/^(o\d|gpt-([5-9]|\d{2}))/', $model ) ) {
			$body['reasoning_effort'] = 'low';
		}

		$response = $this->request( 'POST', self::BASE . 'chat/completions', $this->headers( $key ), $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$choice = is_array( $response['choices'][0] ?? null ) ? $response['choices'][0] : array();

		if ( is_string( $choice['message']['refusal'] ?? null ) && '' !== $choice['message']['refusal'] ) {
			return new WP_Error( 'iftp_btn_ai_refused', __( 'ChatGPT would not answer that. Try saying it another way.', 'ifthenpay-pay-button' ) );
		}

		if ( 'length' === ( $choice['finish_reason'] ?? '' ) || ! is_string( $choice['message']['content'] ?? null ) ) {
			return $this->cut_off();
		}

		return $this->decode_text( $choice['message']['content'] );
	}

	/**
	 * The headers every call sends.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>
	 */
	private function headers( string $key ): array {
		return array( 'Authorization' => 'Bearer ' . $key );
	}

	/**
	 * Strict mode wants every object closed to extra keys.
	 *
	 * @param array<string, mixed> $schema A JSON schema.
	 * @return array<string, mixed>
	 */
	private static function strict( array $schema ): array {
		if ( 'object' === ( $schema['type'] ?? '' ) ) {
			$schema['additionalProperties'] = false;
		}

		if ( is_array( $schema['properties'] ?? null ) ) {
			$schema['properties'] = array_map( array( self::class, 'strict' ), $schema['properties'] );
		}

		if ( is_array( $schema['items'] ?? null ) ) {
			$schema['items'] = self::strict( $schema['items'] );
		}

		return $schema;
	}
}
