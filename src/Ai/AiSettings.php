<?php
/**
 * How the AI assistant behaves: its limits and its manner.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The merchant's own caps on what the AI may cost, and how it answers. Set in Configuration › AI.
 *
 * @phpstan-type Settings array{hourly: int, daily: int, thinking: string, creativity: string, memory: int, keep_days: int, fallback: bool, prices: bool}
 */
class AiSettings {

	public const THINKING   = array( 'quick', 'balanced', 'deep' );
	public const CREATIVITY = array( 'focused', 'balanced', 'creative' );
	public const MEMORY     = array( 6, 12, 24 );
	public const KEEP_DAYS  = array( 1, 2, 7, 30 );

	public const MAX_HOURLY = 500;
	public const MAX_DAILY  = 5000;

	private const OPTION = 'iftp_btn_ai_settings';

	/**
	 * What a site starts with: the limits the studio always had.
	 *
	 * @return Settings
	 */
	public static function defaults(): array {
		return array(
			'hourly'     => 60,
			'daily'      => 0,
			'thinking'   => 'quick',
			'creativity' => 'balanced',
			'memory'     => 12,
			'keep_days'  => 2,
			'fallback'   => true,
			'prices'     => true,
		);
	}

	/**
	 * The saved settings, over the defaults.
	 *
	 * @return Settings
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION, array() );

		return self::sanitize( is_array( $stored ) ? $stored : array(), true );
	}

	/**
	 * Stores the settings. Not autoloaded: only the AI's own screens and calls read them.
	 *
	 * @param array<string, mixed> $settings Already sanitized.
	 */
	public static function set( array $settings ): void {
		update_option( self::OPTION, $settings, false );
	}

	/**
	 * Removes them, on uninstall.
	 */
	public static function delete(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Cleans settings from the form, or from storage.
	 *
	 * @param array<string, mixed> $raw    The settings.
	 * @param bool                 $stored Whether they come from storage, where a missing switch means its default rather than off.
	 * @return Settings
	 */
	public static function sanitize( array $raw, bool $stored = false ): array {
		$defaults = self::defaults();
		$int      = static fn( string $key ): int => isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (int) $raw[ $key ] : (int) $defaults[ $key ];
		$word     = static fn( string $key, array $allowed ): string => in_array( $raw[ $key ] ?? null, $allowed, true ) ? (string) $raw[ $key ] : (string) $defaults[ $key ];
		$number   = static fn( string $key, array $allowed ): int => in_array( $int( $key ), $allowed, true ) ? $int( $key ) : (int) $defaults[ $key ];
		$flag     = static fn( string $key ): bool => $stored && ! array_key_exists( $key, $raw ) ? (bool) $defaults[ $key ] : ! empty( $raw[ $key ] );

		return array(
			'hourly'     => max( 1, min( self::MAX_HOURLY, $int( 'hourly' ) ) ),
			'daily'      => max( 0, min( self::MAX_DAILY, $int( 'daily' ) ) ),
			'thinking'   => $word( 'thinking', self::THINKING ),
			'creativity' => $word( 'creativity', self::CREATIVITY ),
			'memory'     => $number( 'memory', self::MEMORY ),
			'keep_days'  => $number( 'keep_days', self::KEEP_DAYS ),
			'fallback'   => $flag( 'fallback' ),
			'prices'     => $flag( 'prices' ),
		);
	}

	/**
	 * The sampling temperature each creativity setting stands for.
	 *
	 * @param string $creativity One of CREATIVITY.
	 */
	public static function temperature( string $creativity ): float {
		$map = array(
			'focused'  => 0.3,
			'balanced' => 0.7,
			'creative' => 1.0,
		);

		return $map[ $creativity ] ?? 0.7;
	}
}
