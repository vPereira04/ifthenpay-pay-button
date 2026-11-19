<?php
/**
 * Google Gemini.
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
 * The Gemini API's generateContent, with a response schema so the reply is always JSON in our shape.
 */
class Gemini extends Provider {

	private const BASE = 'https://generativelanguage.googleapis.com/v1beta/';

	// Thinking counts against the output budget, so it gets plenty of room.
	protected const MAX_TOKENS = 16384;

	/**
	 * The provider's id, as stored.
	 */
	public function id(): string {
		return 'gemini';
	}

	/**
	 * Its name, as the merchant knows it.
	 */
	public function label(): string {
		return 'Gemini';
	}

	/**
	 * Who makes it, under its name.
	 */
	public function maker(): string {
		return __( 'by Google', 'ifthenpay-pay-button' );
	}

	/**
	 * Where the merchant makes a key.
	 */
	public function key_url(): string {
		return 'https://aistudio.google.com/apikey';
	}

	/**
	 * The models this key can use for chat, id => name, best first.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>|WP_Error
	 */
	public function models( string $key ) {
		$response = $this->request( 'GET', self::BASE . 'models?pageSize=200', array( 'x-goog-api-key' => $key ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$names = array();

		foreach ( is_array( $response['models'] ?? null ) ? $response['models'] : array() as $model ) {
			$id      = is_string( $model['name'] ?? null ) ? preg_replace( '#^models/#', '', $model['name'] ) : '';
			$methods = is_array( $model['supportedGenerationMethods'] ?? null ) ? $model['supportedGenerationMethods'] : array();

			// Only chat models that write text; not embeddings, images, speech, live audio or the small open ones.
			if ( ! is_string( $id ) || '' === $id || ! in_array( 'generateContent', $methods, true ) || 1 === preg_match( '/embed|aqa|imagen|veo|tts|image|live|audio|gemma|learnlm|robotics|computer-use/', $id ) || 0 !== strpos( $id, 'gemini-' ) ) {
				continue;
			}

			$names[ $id ] = is_string( $model['displayName'] ?? null ) && '' !== $model['displayName'] ? sanitize_text_field( $model['displayName'] ) : $id;
		}

		if ( array() === $names ) {
			return new WP_Error( 'iftp_btn_ai_no_models', __( 'This key has no Gemini chat models available.', 'ifthenpay-pay-button' ) );
		}

		$ids     = array_keys( $names );
		$flash   = self::rank( $ids, '/^gemini-(\d+(?:\.\d+)?)-flash(?!-lite)/' );
		$pro     = self::rank( $ids, '/^gemini-(\d+(?:\.\d+)?)-pro/' );
		$ordered = array_unique( array_merge( $flash, $pro, $ids ) );

		return array_combine( $ordered, array_map( static fn( string $id ): string => $names[ $id ], $ordered ) );
	}

	/**
	 * When the picked model fails, the ones most likely to answer. The newest models are the busiest, so older stable ones come
	 * first, then the lite ones (fast, and with room of their own), then newer ones, and Pro last: a free key has no Pro quota.
	 *
	 * @param array<string, string> $models What models() gave.
	 * @param string                $picked The model that failed.
	 * @return array<int, string> Model ids.
	 */
	public function fallbacks( array $models, string $picked ): array {
		$version = 1 === preg_match( '/^gemini-(\d+(?:\.\d+)?)/', $picked, $match ) ? (float) $match[1] : 0.0;
		$scored  = array();

		foreach ( array_keys( $models ) as $id ) {
			if ( $id === $picked ) {
				continue;
			}

			$number   = 1 === preg_match( '/^gemini-(\d+(?:\.\d+)?)/', $id, $found ) ? (float) $found[1] : 0.0;
			$unstable = 1 === preg_match( '/preview|exp|beta|alpha/', $id ) ? 1 : 0;

			if ( false !== strpos( $id, '-pro' ) ) {
				$bucket = 3;
			} elseif ( false !== strpos( $id, 'lite' ) ) {
				$bucket = 1;
			} elseif ( $number <= $version ) {
				$bucket = 0;
			} else {
				$bucket = 2;
			}

			// Within a bucket: stable before preview, then the closest to the picked version, the newer last.
			$scored[ $id ] = array( $bucket, $unstable, abs( $number - $version ), $number );
		}

		uasort(
			$scored,
			static fn( array $a, array $b ): int => $a <=> $b
		);

		return array_keys( $scored );
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
		$thinking = $this->thinking_config( $model );
		$openapi  = self::to_openapi( $schema );
		$result   = $this->attempt( $key, $model, $system, $messages, $openapi, $thinking );

		// Some models turn down a schema this size; the shape then goes in the instructions instead.
		if ( is_wp_error( $result ) && 'iftp_btn_ai_schema' === $result->get_error_code() ) {
			$result = $this->attempt( $key, $model, $system . "\n\nAnswer with one JSON object that follows this JSON schema exactly:\n" . wp_json_encode( $schema ), $messages, null, $thinking );
		}

		return $result;
	}

	/**
	 * One generate call; a model that won't take the thinking setting is asked again without it, and stays without it.
	 *
	 * @param string                            $key      The API key.
	 * @param string                            $model    The model id.
	 * @param string                            $system   The instructions.
	 * @param array<int, array<string, string>> $messages The chat.
	 * @param array<string, mixed>|null         $schema   The response schema, or null for plain JSON.
	 * @param array<string, int|string>         $thinking The thinkingConfig, emptied if the model turns it down.
	 * @return array<string, mixed>|WP_Error
	 */
	private function attempt( string $key, string $model, string $system, array $messages, ?array $schema, array &$thinking ) {
		$result = $this->generate( $key, $model, $system, $messages, $schema, $thinking );

		if ( is_wp_error( $result ) && array() !== $thinking && in_array( $result->get_error_code(), array( 'iftp_btn_ai_refused', 'iftp_btn_ai_schema' ), true ) && false !== stripos( $result->get_error_message(), 'think' ) ) {
			$thinking = array();
			$result   = $this->generate( $key, $model, $system, $messages, $schema, $thinking );
		}

		// Some models take no system instruction; it then goes in as the first message.
		if ( is_wp_error( $result ) && 'iftp_btn_ai_refused' === $result->get_error_code() && false !== stripos( $result->get_error_message(), 'instruction' ) && '' !== $system && array() !== $messages ) {
			array_unshift(
				$messages,
				array(
					'role' => 'user',
					'text' => $system,
				)
			);
			$result = $this->generate( $key, $model, '', $messages, $schema, $thinking );
		}

		return $result;
	}

	/**
	 * How long the model may think, from the merchant's setting. Left alone it can think for over a minute on a whole button, and the
	 * retries then run out of time.
	 *
	 * @param string $model The model id.
	 * @return array<string, int|string> Gemini's thinkingConfig, or nothing for a model that doesn't think.
	 */
	private function thinking_config( string $model ): array {
		if ( 1 !== preg_match( '/^gemini-(\d+(?:\.\d+)?)/', $model, $match ) || (float) $match[1] < 2.5 ) {
			return array();
		}

		$levels  = array(
			'quick'    => 'low',
			'balanced' => 'medium',
			'deep'     => 'high',
		);
		$budgets = array(
			'quick'    => 2048,
			'balanced' => 6144,
			'deep'     => 12288,
		);

		// Gemini 3 and later think by level; 2.5 by a token budget.
		return (float) $match[1] >= 3 ? array( 'thinkingLevel' => $levels[ $this->thinking ] ?? 'low' ) : array( 'thinkingBudget' => $budgets[ $this->thinking ] ?? 2048 );
	}

	/**
	 * One generateContent call.
	 *
	 * @param string                            $key      The API key.
	 * @param string                            $model    The model id.
	 * @param string                            $system   The instructions.
	 * @param array<int, array<string, string>> $messages The chat.
	 * @param array<string, mixed>|null         $schema   The response schema, or null for plain JSON.
	 * @param array<string, int|string>         $thinking The thinkingConfig, if any.
	 * @return array<string, mixed>|WP_Error
	 */
	private function generate( string $key, string $model, string $system, array $messages, ?array $schema, array $thinking ) {
		$config = array(
			'temperature'      => $this->temperature,
			'maxOutputTokens'  => $this->answer,
			'responseMimeType' => 'application/json',
		);

		if ( null !== $schema ) {
			$config['responseSchema'] = $schema;
		}

		if ( array() !== $thinking ) {
			$config['thinkingConfig'] = $thinking;
		}

		$body = array(
			'contents'         => array_map(
				static fn( array $message ): array => array(
					'role'  => 'assistant' === $message['role'] ? 'model' : 'user',
					'parts' => array( array( 'text' => $message['text'] ) ),
				),
				self::turns( $messages )
			),
			'generationConfig' => $config,
		);

		if ( '' !== $system ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $system ) ) );
		}

		$response = $this->request(
			'POST',
			self::BASE . 'models/' . preg_replace( '/[^a-z0-9.\-]/', '', strtolower( $model ) ) . ':generateContent',
			array( 'x-goog-api-key' => $key ),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( is_string( $response['promptFeedback']['blockReason'] ?? null ) ) {
			return new WP_Error( 'iftp_btn_ai_refused', __( 'Gemini would not answer that. Try saying it another way.', 'ifthenpay-pay-button' ) );
		}

		$candidate = is_array( $response['candidates'][0] ?? null ) ? $response['candidates'][0] : array();
		$text      = '';

		foreach ( is_array( $candidate['content']['parts'] ?? null ) ? $candidate['content']['parts'] : array() as $part ) {
			if ( empty( $part['thought'] ) && is_string( $part['text'] ?? null ) ) {
				$text .= $part['text'];
			}
		}

		if ( 'MAX_TOKENS' === ( $candidate['finishReason'] ?? '' ) || '' === trim( $text ) ) {
			return $this->cut_off();
		}

		return $this->decode_text( $text );
	}

	/**
	 * Gemini's schema is OpenAPI's subset: upper-case types, no additionalProperties.
	 *
	 * @param array<string, mixed> $schema A JSON schema.
	 * @return array<string, mixed>
	 */
	private static function to_openapi( array $schema ): array {
		unset( $schema['additionalProperties'] );

		if ( is_string( $schema['type'] ?? null ) ) {
			$schema['type'] = strtoupper( $schema['type'] );
		}

		if ( is_array( $schema['properties'] ?? null ) ) {
			$schema['properties']       = array_map( array( self::class, 'to_openapi' ), $schema['properties'] );
			$schema['propertyOrdering'] = array_keys( $schema['properties'] );
		}

		if ( is_array( $schema['items'] ?? null ) ) {
			$schema['items'] = self::to_openapi( $schema['items'] );
		}

		return $schema;
	}
}
