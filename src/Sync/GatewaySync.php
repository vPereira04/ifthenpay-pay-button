<?php
/**
 * Gateway/methods catalog sync.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Sync;

use Ifthenpay\PayButton\Api\IfthenpayClient;
use Ifthenpay\PayButton\Repository\SettingsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Syncs Gateway Key rows and the methods catalog from ifthenpay into settings.
 *
 * @phpstan-import-type Method from SettingsRepository
 */
class GatewaySync {

	/**
	 * Settings storage.
	 *
	 * @var SettingsRepository
	 */
	private SettingsRepository $settings;

	/**
	 * Ifthenpay API client.
	 *
	 * @var IfthenpayClient
	 */
	private IfthenpayClient $client;

	/**
	 * Sets up the sync.
	 *
	 * @param SettingsRepository|null $settings Settings storage.
	 * @param IfthenpayClient|null    $client   Ifthenpay API client.
	 */
	public function __construct( ?SettingsRepository $settings = null, ?IfthenpayClient $client = null ) {
		$this->settings = $settings ?? new SettingsRepository();
		$this->client   = $client ?? new IfthenpayClient();
	}

	/**
	 * First-time connection: stores the Backoffice Key if it has a Gateway Key row, and builds the methods of every Gateway Key.
	 * The first key is the default one, for buttons that don't pick their own.
	 *
	 * @param string $backoffice_key The Backoffice Key to connect.
	 */
	public function connect( string $backoffice_key ): bool {
		$rows         = $this->client->get_gateway_keys( $backoffice_key, true );
		$gateway_keys = array_values( array_filter( array_map( array( $this, 'extract_gateway_key' ), $rows ) ) );

		if ( array() === $gateway_keys ) {
			return false;
		}

		$this->settings->set_backoffice_key( $backoffice_key );
		$this->settings->set_gateway_keys( $gateway_keys );
		$this->settings->set_gateway_key( $gateway_keys[0] );
		$this->build_all( $rows, $gateway_keys );

		return true;
	}

	/**
	 * Re-fetches the Gateway Key list and rebuilds the methods of every key.
	 */
	public function sync(): bool {
		$rows         = $this->client->get_gateway_keys( $this->settings->get_backoffice_key() );
		$gateway_keys = array_values( array_filter( array_map( array( $this, 'extract_gateway_key' ), $rows ) ) );

		if ( array() === $gateway_keys ) {
			return false;
		}

		$this->settings->set_gateway_keys( $gateway_keys );

		if ( ! in_array( $this->settings->get_gateway_key(), $gateway_keys, true ) ) {
			$this->settings->set_gateway_key( $gateway_keys[0] );
		}

		$this->build_all( $rows, $gateway_keys );

		return true;
	}

	/**
	 * Whether the stored settings are missing the Gateway Key list, a key's methods or a catalog field, and need a resync.
	 */
	public function needs_backfill(): bool {
		if ( ! $this->settings->is_connected() ) {
			return false;
		}

		$gateway_keys = $this->settings->get_gateway_keys();

		if ( array() === $gateway_keys ) {
			return true;
		}

		foreach ( $gateway_keys as $gateway_key ) {
			$methods = $this->settings->get_methods( $gateway_key );

			if ( array() === $methods ) {
				return true;
			}

			foreach ( $methods as $method ) {
				if ( ! array_key_exists( 'logo', $method ) || ! array_key_exists( 'visible', $method ) || '' === $method['alias'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Builds and stores the methods of every Gateway Key, from one fetch of the catalog.
	 *
	 * @param array<int, array<string, mixed>> $rows         The Gateway Key rows.
	 * @param array<int, string>               $gateway_keys Their keys.
	 */
	private function build_all( array $rows, array $gateway_keys ): void {
		$catalog = $this->client->get_available_methods();

		foreach ( $gateway_keys as $gateway_key ) {
			$this->settings->set_methods( $this->build_methods( $this->find_row_for_gateway_key( $rows, $gateway_key ) ?? array(), $gateway_key, $catalog ), $gateway_key );
		}
	}

	/**
	 * Merges ifthenpay's methods catalog with the accounts provisioned on this gateway row.
	 * I keep `IsVisible: false` entries too, so the entries screens can still show their icon/alias.
	 *
	 * @param array<string, mixed>             $gateway_row The Gateway Key row to resolve accounts from.
	 * @param string                           $gateway_key Its Gateway Key.
	 * @param array<int, array<string, mixed>> $catalog     ifthenpay's methods catalog.
	 * @return array<int, Method>
	 */
	private function build_methods( array $gateway_row, string $gateway_key, array $catalog ): array {
		$existing = array_column( $this->settings->get_methods( $gateway_key ), null, 'entity' );
		$methods  = array();

		foreach ( $catalog as $entry ) {
			$entity  = is_scalar( $entry['Entity'] ?? null ) ? (string) $entry['Entity'] : '';
			$account = $this->resolve_account( $gateway_row, $entity );
			$visible = ! empty( $entry['IsVisible'] );

			if ( '' === $entity ) {
				continue;
			}

			$methods[] = array(
				'entity'    => $entity,
				'alias'     => is_scalar( $entry['Method'] ?? null ) ? (string) $entry['Method'] : $entity,
				'logo'      => is_scalar( $entry['SmallImageUrl'] ?? $entry['ImageUrl'] ?? null ) ? (string) ( $entry['SmallImageUrl'] ?? $entry['ImageUrl'] ) : '',
				'logo_dark' => is_scalar( $entry['SmallImageUrlDark'] ?? null ) ? (string) $entry['SmallImageUrlDark'] : '',
				'account'   => $account,
				'enabled'   => $visible && '' !== $account && ! empty( $existing[ $entity ]['enabled'] ),
				'position'  => is_numeric( $entry['Position'] ?? null ) ? (int) $entry['Position'] : 0,
				'visible'   => $visible,
			);
		}

		return $methods;
	}

	/**
	 * Reads the Gateway Key value out of a gateway row, whichever key name the API used.
	 *
	 * @param array<string, mixed> $row A Gateway Key row.
	 */
	private function extract_gateway_key( array $row ): string {
		$key = $row['GatewayKey'] ?? $row['gatewayKey'] ?? $row['Chave'] ?? '';

		return is_scalar( $key ) ? (string) $key : '';
	}

	/**
	 * Finds the row matching a Gateway Key, or falls back to the first row.
	 *
	 * @param array<int, array<string, mixed>> $rows        The Gateway Key rows to search.
	 * @param string                           $gateway_key The Gateway Key to find.
	 * @return array<string, mixed>|null
	 */
	private function find_row_for_gateway_key( array $rows, string $gateway_key ): ?array {
		foreach ( $rows as $row ) {
			if ( $this->extract_gateway_key( $row ) === $gateway_key ) {
				return $row;
			}
		}

		return $rows[0] ?? null;
	}

	/**
	 * A method's `ENTITY|ACCOUNT` segment from the row, with stray spacing trimmed.
	 * Multibanco's column is "Multibanco", not its entity code "MB" like every other method.
	 *
	 * @param array<string, mixed> $row    The Gateway Key row.
	 * @param string               $entity The method entity code.
	 */
	private function resolve_account( array $row, string $entity ): string {
		$column = 'MB' === $entity ? 'Multibanco' : $entity;
		$value  = $row[ $column ] ?? '';

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		return implode( '|', array_map( 'trim', explode( '|', $value, 2 ) ) );
	}
}
