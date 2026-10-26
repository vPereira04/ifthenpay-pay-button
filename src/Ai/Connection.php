<?php
/**
 * The merchant's AI connection.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

use Ifthenpay\PayButton\Ai\Providers\ChatGpt;
use Ifthenpay\PayButton\Ai\Providers\Claude;
use Ifthenpay\PayButton\Ai\Providers\Gemini;
use Ifthenpay\PayButton\Ai\Providers\Provider;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which provider, which key and which model, all sealed together in one Vault entry: the database only ever
 * holds a row with a meaningless name and an encrypted value. The key never leaves the server except in the
 * header of a call to its own provider; screens get its last four characters at most.
 *
 * @phpstan-type Stored array{provider: string, key: string, model: string, models: array<string, string>, since: int}
 */
class Connection {

	// Longer than any real key, so a paste of something else is caught here.
	private const MAX_KEY = 300;

	/**
	 * The decrypted connection, read once per request.
	 *
	 * @var array<string, mixed>|false|null
	 * @phpstan-var Stored|false|null
	 */
	private static $cache = null;

	/**
	 * Every provider, in the order the connect dialog shows them.
	 *
	 * @return array<string, Provider>
	 */
	public static function providers(): array {
		$providers = array( new Gemini(), new Claude(), new ChatGpt() );

		return array_combine( array_map( static fn( Provider $provider ): string => $provider->id(), $providers ), $providers );
	}

	/**
	 * The connection, or null when there is none or it can't be opened.
	 *
	 * @return Stored|null
	 */
	public function get(): ?array {
		if ( null === self::$cache ) {
			self::$cache = $this->read();
		}

		return false === self::$cache ? null : self::$cache;
	}

	/**
	 * Whether the chat can run.
	 */
	public function is_connected(): bool {
		return null !== $this->get();
	}

	/**
	 * A connection is stored but won't open, usually because the site's secret keys changed. It has to be made again.
	 */
	public function is_broken(): bool {
		return ! $this->is_connected() && '' !== (string) get_option( Vault::name( 'connection' ), '' );
	}

	/**
	 * The provider in use.
	 */
	public function provider(): ?Provider {
		$stored = $this->get();

		return null !== $stored ? ( self::providers()[ $stored['provider'] ] ?? null ) : null;
	}

	/**
	 * Checks the key with its provider (listing its models costs nothing), then stores it all sealed.
	 *
	 * @param string $provider_id One of providers().
	 * @param string $key         The API key.
	 * @return true|WP_Error
	 */
	public function connect( string $provider_id, string $key ) {
		$provider = self::providers()[ $provider_id ] ?? null;
		$key      = trim( $key );

		if ( null === $provider ) {
			return new WP_Error( 'iftp_btn_ai_provider', __( 'Pick which AI to connect.', 'ifthenpay-pay-button' ) );
		}

		if ( '' === $key || strlen( $key ) > self::MAX_KEY || 1 === preg_match( '/\s/', $key ) ) {
			return new WP_Error( 'iftp_btn_ai_key_format', __( 'That doesn\'t look like an API key. Paste it again, all of it.', 'ifthenpay-pay-button' ) );
		}

		$models = $provider->models( $key );

		if ( is_wp_error( $models ) ) {
			return $models;
		}

		$this->write(
			array(
				'provider' => $provider->id(),
				'key'      => $key,
				'model'    => $provider->default_model( $models ),
				'models'   => $models,
				'since'    => time(),
			)
		);

		return true;
	}

	/**
	 * Switches the model, if the key has it.
	 *
	 * @param string $model A model id.
	 */
	public function set_model( string $model ): bool {
		$stored = $this->get();

		if ( null === $stored || ! isset( $stored['models'][ $model ] ) ) {
			return false;
		}

		$stored['model'] = $model;
		$this->write( $stored );

		return true;
	}

	/**
	 * Forgets the connection.
	 */
	public function disconnect(): void {
		delete_option( Vault::name( 'connection' ) );
		self::$cache = false;
	}

	/**
	 * What the screens may show: never the key, only its last four characters.
	 *
	 * @return array{provider: string, label: string, model: string, models: array<string, string>, hint: string}|null
	 */
	public function summary(): ?array {
		$stored   = $this->get();
		$provider = $this->provider();

		if ( null === $stored || null === $provider ) {
			return null;
		}

		return array(
			'provider' => $provider->id(),
			'label'    => $provider->label(),
			'model'    => $stored['model'],
			'models'   => $stored['models'],
			'hint'     => '••••' . substr( $stored['key'], -4 ),
		);
	}

	/**
	 * Reads and opens the stored connection.
	 *
	 * @return Stored|false
	 */
	private function read() {
		$sealed = get_option( Vault::name( 'connection' ), '' );
		$plain  = is_string( $sealed ) && '' !== $sealed ? Vault::open( $sealed ) : null;
		$stored = null !== $plain ? json_decode( $plain, true ) : null;

		if ( ! is_array( $stored ) || ! is_string( $stored['provider'] ?? null ) || ! is_string( $stored['key'] ?? null ) || ! isset( self::providers()[ $stored['provider'] ] ) ) {
			return false;
		}

		$models = is_array( $stored['models'] ?? null ) ? array_map( 'strval', $stored['models'] ) : array();

		return array(
			'provider' => $stored['provider'],
			'key'      => $stored['key'],
			'model'    => is_string( $stored['model'] ?? null ) ? $stored['model'] : (string) array_key_first( $models ),
			'models'   => $models,
			'since'    => (int) ( $stored['since'] ?? 0 ),
		);
	}

	/**
	 * Seals and stores the connection. Not autoloaded, so it's only read when the chat or its settings need it.
	 *
	 * @param array<string, mixed> $stored The connection.
	 * @phpstan-param Stored $stored
	 */
	private function write( array $stored ): void {
		update_option( Vault::name( 'connection' ), Vault::seal( (string) wp_json_encode( $stored ) ), false );
		self::$cache = $stored;
	}
}
