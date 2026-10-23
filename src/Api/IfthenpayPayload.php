<?php
/**
 * Pay by Link request payload builder.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Api;

use Ifthenpay\PayButton\Dates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the Pay by Link request payload and the accounts string.
 */
class IfthenpayPayload {

	/**
	 * Joins the enabled methods' accounts. Each `account` is already a full `ENTITY|ACCOUNT` segment from ifthenpay.
	 *
	 * @param array<int, array{entity: string, account: string}> $enabled_methods The site's enabled payment methods.
	 */
	public static function build_accounts_string( array $enabled_methods ): string {
		return implode( ';', array_filter( array_column( $enabled_methods, 'account' ), static fn( string $account ): bool => '' !== $account ) );
	}

	/**
	 * Formats an amount the way the ifthenpay API expects it.
	 *
	 * @param float $amount The amount to format.
	 */
	public static function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Builds the full Pay by Link request payload.
	 *
	 * @param string                                                $id                       The payment reference.
	 * @param float                                                 $amount                   The payment amount.
	 * @param string                                                $description              The payer-facing description.
	 * @param array<int, array{entity: string, account: string}>    $enabled_methods          The site's enabled payment methods.
	 * @param array{success: string, error: string, cancel: string} $return_urls              Where ifthenpay sends the payer back to, per outcome.
	 * @param string                                                $lang                     The ifthenpay `lang` code.
	 * @param string                                                $selected_method_position The catalog Position of the pre-selected method, or '' for none.
	 * @param int                                                   $expiry_days              Days the link stays payable, or 0 for ifthenpay's default.
	 * @return array<string, mixed>
	 */
	public static function build_payment_payload(
		string $id,
		float $amount,
		string $description,
		array $enabled_methods,
		array $return_urls,
		string $lang = 'en',
		string $selected_method_position = '',
		int $expiry_days = 0
	): array {
		$payload = array(
			'id'          => $id,
			'amount'      => self::format_amount( $amount ),
			'description' => $description,
			'accounts'    => self::build_accounts_string( $enabled_methods ),
			'success_url' => $return_urls['success'],
			'error_url'   => $return_urls['error'],
			'cancel_url'  => $return_urls['cancel'],
			'otp'         => 'true',
			'lang'        => $lang,
		);

		if ( '' !== $selected_method_position ) {
			$payload['selected_method'] = $selected_method_position;
		}

		if ( $expiry_days > 0 ) {
			$payload['expiredate'] = Dates::local( 'Ymd', time() + $expiry_days * DAY_IN_SECONDS );
		}

		return $payload;
	}

	/**
	 * Maps a WordPress locale (e.g. `pt_PT`, `es_ES`) to an ifthenpay `lang` code.
	 *
	 * @param string $locale The WordPress locale, e.g. get_locale().
	 */
	public static function locale_to_lang( string $locale ): string {
		$prefix = strtolower( substr( $locale, 0, 2 ) );

		return in_array( $prefix, array( 'pt', 'es', 'fr' ), true ) ? $prefix : 'en';
	}
}
