<?php
/**
 * Anthropic's Claude.
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
 * The Messages API, with one tool the model must call: its input is the button, in our schema.
 */
class Claude extends Provider {

	private const BASE = 'https://api.anthropic.com/v1/';
	private const TOOL = 'build_button';

	// The API release the anthropic-version header asks for: Anthropic's date, not ours.
	private const API_DATE = '2023-06-01';

	/**
	 * The provider's id, as stored.
	 */
	public function id(): string {
		return 'claude';
	}

	/**
	 * Its name, as the merchant knows it.
	 */
	public function label(): string {
		return 'Claude';
	}

	/**
	 * Who makes it, under its name.
	 */
	public function maker(): string {
		return __( 'by Anthropic', 'ifthenpay-pay-button' );
	}

	/**
	 * Where the merchant makes a key.
	 */
	public function key_url(): string {
		return 'https://console.anthropic.com/settings/keys';
	}

	/**
	 * The models this key can use for chat, id => name, best first.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>|WP_Error
	 */
	public function models( string $key ) {
		$response = $this->request( 'GET', self::BASE . 'models?limit=100', $this->headers( $key ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$names = array();

		foreach ( is_array( $response['data'] ?? null ) ? $response['data'] : array() as $model ) {
			$id = is_string( $model['id'] ?? null ) ? sanitize_text_field( $model['id'] ) : '';

			if ( '' !== $id ) {
				$names[ $id ] = is_string( $model['display_name'] ?? null ) && '' !== $model['display_name'] ? sanitize_text_field( $model['display_name'] ) : $id;
			}
		}

		if ( array() === $names ) {
			return new WP_Error( 'iftp_btn_ai_no_models', __( 'This key has no Claude models available.', 'ifthenpay-pay-button' ) );
		}

		// Sonnet first: quick enough to chat with, and very good at this.
		$ids     = array_keys( $names );
		$version = '(\d+(?:-\d)?)(?:-\d{8})?$/';
		$ordered = array_unique( array_merge( self::rank( $ids, '/^claude-sonnet-' . $version ), self::rank( $ids, '/^claude-opus-' . $version ), self::rank( $ids, '/^claude-haiku-' . $version ), $ids ) );

		return array_combine( $ordered, array_map( static fn( string $id ): string => $names[ $id ], $ordered ) );
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
		$response = $this->request(
			'POST',
			self::BASE . 'messages',
			$this->headers( $key ),
			array(
				'model'       => $model,
				'max_tokens'  => static::MAX_TOKENS,
				'system'      => $system,
				'messages'    => array_map(
					static fn( array $turn ): array => array(
						'role'    => $turn['role'],
						'content' => $turn['text'],
					),
					self::turns( $messages )
				),
				'tools'       => array(
					array(
						'name'         => self::TOOL,
						'description'  => 'Set the whole pay button: its checkout, form, spots, button design and checkout design, plus your reply to the merchant.',
						'input_schema' => $schema,
					),
				),
				'tool_choice' => array(
					'type' => 'tool',
					'name' => self::TOOL,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 'max_tokens' === ( $response['stop_reason'] ?? '' ) ) {
			return $this->cut_off();
		}

		foreach ( is_array( $response['content'] ?? null ) ? $response['content'] : array() as $block ) {
			if ( 'tool_use' === ( $block['type'] ?? '' ) && is_array( $block['input'] ?? null ) ) {
				return $block['input'];
			}
		}

		return $this->cut_off();
	}

	/**
	 * The headers every call sends.
	 *
	 * @param string $key The API key.
	 * @return array<string, string>
	 */
	private function headers( string $key ): array {
		return array(
			'x-api-key'         => $key,
			'anthropic-version' => self::API_DATE,
		);
	}
}
