<?php
/**
 * The Backoffice key, anti-phishing keys and signing secret are stored sealed.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Ai\Vault;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use Ifthenpay\PayButton\Repository\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * What's in the database is never the secret itself, and sites that saved one before sealing keep working.
 */
final class SecretsStorageTest extends TestCase {

	/**
	 * Resets the state before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['iftp_test_options'] = array();
	}

	/**
	 * What an option holds right now.
	 *
	 * @param string $name The option.
	 * @return mixed
	 */
	private function stored( string $name ) {
		return $GLOBALS['iftp_test_options'][ $name ] ?? null;
	}

	/**
	 * Vault round trips and reveals legacy plain values.
	 */
	public function test_vault_round_trips_and_reveals_legacy_plain_values(): void {
		$sealed = Vault::seal( 'abc-123' );

		$this->assertTrue( Vault::is_sealed( $sealed ) );
		$this->assertSame( 'abc-123', Vault::reveal( $sealed ) );
		$this->assertSame( 'abc-123', Vault::reveal( 'abc-123' ), 'Saved before sealing: already plain.' );
	}

	/**
	 * Vault gives nothing for a tampered value.
	 */
	public function test_vault_gives_nothing_for_a_tampered_value(): void {
		$this->assertSame( '', Vault::reveal( substr( Vault::seal( 'abc-123' ), 0, -4 ) . 'AAAA' ) );
	}

	/**
	 * Backoffice key is stored sealed and read back.
	 */
	public function test_backoffice_key_is_stored_sealed_and_read_back(): void {
		$settings = new SettingsRepository();
		$settings->set_backoffice_key( 'ABCD-123456' );

		$this->assertTrue( Vault::is_sealed( (string) $this->stored( 'iftp_btn_backoffice_key' ) ) );
		$this->assertSame( 'ABCD-123456', $settings->get_backoffice_key() );
		$this->assertTrue( $settings->is_connected() );
	}

	/**
	 * Clearing the backoffice key disconnects.
	 */
	public function test_clearing_the_backoffice_key_disconnects(): void {
		$settings = new SettingsRepository();
		$settings->set_backoffice_key( 'ABCD-123456' );
		$settings->set_backoffice_key( '' );

		$this->assertSame( '', $this->stored( 'iftp_btn_backoffice_key' ) );
		$this->assertFalse( $settings->is_connected() );
	}

	/**
	 * A plain backoffice key from before is sealed on first read.
	 */
	public function test_a_plain_backoffice_key_from_before_is_sealed_on_first_read(): void {
		$GLOBALS['iftp_test_options']['iftp_btn_backoffice_key'] = 'ABCD-123456';

		$this->assertSame( 'ABCD-123456', ( new SettingsRepository() )->get_backoffice_key() );
		$this->assertTrue( Vault::is_sealed( (string) $this->stored( 'iftp_btn_backoffice_key' ) ) );
		$this->assertSame( 'ABCD-123456', ( new SettingsRepository() )->get_backoffice_key() );
	}

	/**
	 * A backoffice key that cannot be opened counts as disconnected.
	 */
	public function test_a_backoffice_key_that_cannot_be_opened_counts_as_disconnected(): void {
		$GLOBALS['iftp_test_options']['iftp_btn_backoffice_key'] = 'v1:' . base64_encode( str_repeat( 'x', 60 ) );

		$this->assertFalse( ( new SettingsRepository() )->is_connected() );
	}

	/**
	 * Anti phishing keys are stored sealed per gateway key.
	 */
	public function test_anti_phishing_keys_are_stored_sealed_per_gateway_key(): void {
		$settings = new SettingsRepository();
		$settings->set_anti_phishing_key( 'AAAA-000001', 'apk-one' );
		$settings->set_anti_phishing_key( 'BBBB-000002', 'apk-two' );

		foreach ( (array) $this->stored( 'iftp_btn_anti_phishing_keys' ) as $value ) {
			$this->assertTrue( Vault::is_sealed( (string) $value ) );
		}

		$this->assertSame( 'apk-one', $settings->get_anti_phishing_key( 'AAAA-000001' ) );
		$this->assertSame( 'apk-two', $settings->get_anti_phishing_key( 'BBBB-000002' ) );
		$this->assertSame( '', $settings->get_anti_phishing_key( 'CCCC-000003' ) );
	}

	/**
	 * A plain anti phishing key from before is sealed on first read.
	 */
	public function test_a_plain_anti_phishing_key_from_before_is_sealed_on_first_read(): void {
		$GLOBALS['iftp_test_options']['iftp_btn_anti_phishing_keys'] = array( 'AAAA-000001' => 'apk-one' );

		$this->assertSame( 'apk-one', ( new SettingsRepository() )->get_anti_phishing_key( 'AAAA-000001' ) );
		$this->assertTrue( Vault::is_sealed( (string) $this->stored( 'iftp_btn_anti_phishing_keys' )['AAAA-000001'] ) );
	}

	/**
	 * Signing secret is made once and stored sealed.
	 */
	public function test_signing_secret_is_made_once_and_stored_sealed(): void {
		$checkout = new CheckoutSettings();
		$first    = $checkout->get_signing_secret();

		$this->assertTrue( strlen( $first ) >= 32 );
		$this->assertTrue( Vault::is_sealed( (string) $this->stored( 'iftp_btn_signing_secret' ) ) );
		$this->assertSame( $first, $checkout->get_signing_secret() );
	}

	/**
	 * A plain signing secret from before keeps its value.
	 */
	public function test_a_plain_signing_secret_from_before_keeps_its_value(): void {
		$plain = str_repeat( 's', 64 );
		$GLOBALS['iftp_test_options']['iftp_btn_signing_secret'] = $plain;

		$this->assertSame( $plain, ( new CheckoutSettings() )->get_signing_secret(), 'Links already signed must still verify.' );
		$this->assertTrue( Vault::is_sealed( (string) $this->stored( 'iftp_btn_signing_secret' ) ) );
	}

	/**
	 * Rotating the signing secret changes it.
	 */
	public function test_rotating_the_signing_secret_changes_it(): void {
		$checkout = new CheckoutSettings();
		$before   = $checkout->get_signing_secret();
		$checkout->rotate_signing_secret();

		$this->assertTrue( $before !== $checkout->get_signing_secret() );
	}
}
