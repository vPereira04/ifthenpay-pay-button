<?php
/**
 * Encrypted storage for the AI connection.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seals a secret with libsodium (WordPress ships a fallback, so it's always there) under a key made from the
 * site's secret keys in wp-config.php. Those live in a file, not the database, so a copy of the database alone
 * opens nothing.
 *
 * The option's name comes from the same secret, so it says nothing either: no "ai", no "key", no provider.
 */
class Vault {

	private const VERSION = 'v1:';

	// The placeholder wp-config-sample.php ships with; a site still on it has no real key.
	private const SAMPLE_PHRASE = 'put your unique phrase here';

	/**
	 * Encrypts a value, with a fresh nonce each time.
	 *
	 * @param string $plain The value.
	 */
	public static function seal( string $plain ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary ciphertext, stored as text.
		return self::VERSION . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
	}

	/**
	 * Decrypts a sealed value; null when it was tampered with or the site's keys changed since.
	 *
	 * @param string $sealed What seal() gave.
	 */
	public static function open( string $sealed ): ?string {
		if ( 0 !== strpos( $sealed, self::VERSION ) ) {
			return null;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reverses seal().
		$raw = base64_decode( substr( $sealed, strlen( self::VERSION ) ), true );

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		try {
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
		} catch ( \Throwable $e ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}

	/**
	 * A storage name that only this site can work out.
	 *
	 * @param string $purpose What it holds, mixed into the hash.
	 */
	public static function name( string $purpose ): string {
		return 'iftp_btn_' . substr( hash_hmac( 'sha256', 'name|' . $purpose, self::secret() ), 0, 20 );
	}

	/**
	 * Whether the secret comes from wp-config.php. Without it WordPress keeps its keys in the database, so the
	 * lock and its key would sit side by side.
	 */
	public static function is_strong(): bool {
		if ( defined( 'IFTP_BTN_SECRET' ) && is_string( IFTP_BTN_SECRET ) && strlen( IFTP_BTN_SECRET ) >= 32 ) {
			return true;
		}

		foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY' ) as $constant ) {
			$value = defined( $constant ) ? constant( $constant ) : '';

			if ( ! is_string( $value ) || strlen( $value ) < 32 || self::SAMPLE_PHRASE === $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The encryption key.
	 */
	private static function key(): string {
		return sodium_crypto_generichash( 'key|' . self::secret(), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * The site's secret: our own constant when set, else WordPress's secret keys.
	 */
	private static function secret(): string {
		if ( defined( 'IFTP_BTN_SECRET' ) && is_string( IFTP_BTN_SECRET ) && strlen( IFTP_BTN_SECRET ) >= 32 ) {
			return IFTP_BTN_SECRET;
		}

		return wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
	}
}
