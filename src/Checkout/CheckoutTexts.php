<?php
/**
 * Every word the payer reads around a payment, and the merchant's own versions of them.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Checkout;

use Ifthenpay\PayButton\Repository\CheckoutSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The checkout's texts. Each one is translated like any other string, and a merchant can write their own on the
 * Checkout screen's Texts tab, so a site can take payments in a language the plugin isn't translated into.
 * Blank means the translated default. Changing words uses {tokens}, the same way merge tags do.
 *
 * @phpstan-type TextGroup array{title: string, description: string, view: string, items: array<string, string>}
 */
class CheckoutTexts {

	public const MAX_LENGTH = 200;

	/**
	 * The default text for every key, in the site's language.
	 *
	 * @param int $count The number plural texts are for.
	 * @return array<string, string>
	 */
	public static function defaults( int $count = 2 ): array {
		return array(
			// The checkout form.
			'header'           => (string) get_bloginfo( 'name' ),
			'pay_now'          => __( 'Pay now', 'ifthenpay-pay-button' ),
			'first_step'       => __( 'Your payment', 'ifthenpay-pay-button' ),
			/* translators: {step} and {steps} stay as they are: the current step and how many there are */
			'step_of'          => __( 'Step {step} of {steps}', 'ifthenpay-pay-button' ),
			/* translators: {price} stays as it is: the cheapest option's price */
			'from'             => __( 'from {price}', 'ifthenpay-pay-button' ),
			'amount'           => __( 'Amount', 'ifthenpay-pay-button' ),
			/* translators: {min} and {max} stay as they are: the smallest and largest amount */
			'between'          => __( 'Between {min} and {max}.', 'ifthenpay-pay-button' ),
			'quantity'         => __( 'Quantity', 'ifthenpay-pay-button' ),
			'name'             => __( 'Name', 'ifthenpay-pay-button' ),
			'email'            => __( 'Email', 'ifthenpay-pay-button' ),
			'phone'            => __( 'Phone', 'ifthenpay-pay-button' ),
			'nif'              => __( 'Tax number (NIF)', 'ifthenpay-pay-button' ),
			'note'             => __( 'Note', 'ifthenpay-pay-button' ),
			'optional'         => __( '(optional)', 'ifthenpay-pay-button' ),
			'choose'           => __( 'Choose…', 'ifthenpay-pay-button' ),
			'back'             => __( 'Back', 'ifthenpay-pay-button' ),
			'next'             => __( 'Next', 'ifthenpay-pay-button' ),
			'pay'              => __( 'Continue to payment', 'ifthenpay-pay-button' ),
			'starting'         => __( 'Taking you to ifthenpay…', 'ifthenpay-pay-button' ),
			'secure'           => __( 'Secure payment by', 'ifthenpay-pay-button' ),
			// Spots.
			/* translators: {count} stays as it is: how many spots are left */
			'spots_left'       => _n( 'Only {count} spot left', 'Only {count} spots left', $count, 'ifthenpay-pay-button' ),
			/* translators: {count} stays as it is: how many spots are left for this option */
			'option_left'      => _n( '{count} left', '{count} left', $count, 'ifthenpay-pay-button' ),
			'sold_out'         => __( 'Sold out', 'ifthenpay-pay-button' ),
			// The popup after paying.
			'checking'         => __( 'Checking your payment…', 'ifthenpay-pay-button' ),
			'reference'        => __( 'Reference', 'ifthenpay-pay-button' ),
			'method'           => __( 'Method', 'ifthenpay-pay-button' ),
			'close'            => __( 'Close', 'ifthenpay-pay-button' ),
			'redirecting'      => __( 'Payment received. Taking you on…', 'ifthenpay-pay-button' ),
			// Problems.
			'failed'           => __( 'We could not start the payment. Please try again.', 'ifthenpay-pay-button' ),
			'choose_one'       => __( 'Choose at least one option.', 'ifthenpay-pay-button' ),
			/* translators: {field} stays as it is: the field's label */
			'fill_in'          => __( 'Fill in {field}.', 'ifthenpay-pay-button' ),
			/* translators: {field} stays as it is: the checkbox's label */
			'tick'             => __( 'Tick "{field}" to continue.', 'ifthenpay-pay-button' ),
			/* translators: {field} stays as it is: the field's label */
			'check_field'      => __( 'Check {field}.', 'ifthenpay-pay-button' ),
			'bad_email'        => __( 'Enter a valid email address.', 'ifthenpay-pay-button' ),
			'bad_nif'          => __( 'Enter a valid tax number.', 'ifthenpay-pay-button' ),
			'enter_amount'     => __( 'Enter the amount you want to pay.', 'ifthenpay-pay-button' ),
			/* translators: {min} and {max} stay as they are: the smallest and largest amount */
			'amount_range'     => __( 'Enter an amount between {min} and {max}.', 'ifthenpay-pay-button' ),
			'pick_option'      => __( 'Pick an option to continue.', 'ifthenpay-pay-button' ),
			/* translators: {max} stays as it is: the most spots one payment can take */
			'bad_quantity'     => __( 'Choose a quantity from 1 to {max}.', 'ifthenpay-pay-button' ),
			'too_much'         => __( 'That is more than one payment can take. Choose fewer.', 'ifthenpay-pay-button' ),
			'all_taken'        => __( 'Sold out. Every spot has been taken.', 'ifthenpay-pay-button' ),
			/* translators: {count} stays as it is: how many spots are left */
			'only_left'        => _n( 'Only {count} spot is left.', 'Only {count} spots are left.', $count, 'ifthenpay-pay-button' ),
			/* translators: {option} stays as it is: the option's label */
			'option_sold_out'  => __( '{option} is sold out. Pick another option.', 'ifthenpay-pay-button' ),
			/* translators: {count} and {option} stay as they are: how many spots are left, and the option's label */
			'option_only_left' => _n( 'Only {count} spot is left for {option}.', 'Only {count} spots are left for {option}.', $count, 'ifthenpay-pay-button' ),
			'unavailable'      => __( 'This payment is not available right now.', 'ifthenpay-pay-button' ),
			'link_invalid'     => __( 'This payment link is no longer valid. Ask for a new one.', 'ifthenpay-pay-button' ),
			'link_no_amount'   => __( 'This payment link is missing its amount.', 'ifthenpay-pay-button' ),
			'rate_limited'     => __( 'Too many attempts. Please wait a minute and try again.', 'ifthenpay-pay-button' ),
			'busy'             => __( 'A lot of people are paying right now. Please try again.', 'ifthenpay-pay-button' ),
			// Only screen readers say these.
			'one_less'         => __( 'One less', 'ifthenpay-pay-button' ),
			'one_more'         => __( 'One more', 'ifthenpay-pay-button' ),
			'suggested'        => __( 'Suggested amounts', 'ifthenpay-pay-button' ),
			'methods'          => __( 'Payment methods', 'ifthenpay-pay-button' ),
		);
	}

	/**
	 * The Texts tab's groups: which keys go together, a hint where the key alone doesn't say enough, and the preview
	 * view that shows them.
	 *
	 * @return array<string, TextGroup>
	 */
	public static function groups(): array {
		return array(
			'checkout' => array(
				'title'       => __( 'Checkout form', 'ifthenpay-pay-button' ),
				'description' => __( 'The button, the form and its buttons. A button\'s own text, first page title or note label still wins over these.', 'ifthenpay-pay-button' ),
				'view'        => 'checkout',
				'items'       => array(
					'header'     => __( 'Top line of the form. Your site name when blank.', 'ifthenpay-pay-button' ),
					'pay_now'    => __( 'Text on a pay button that has none of its own.', 'ifthenpay-pay-button' ),
					'first_step' => __( 'Title of the first page, in a form with pages.', 'ifthenpay-pay-button' ),
					'step_of'    => '',
					'from'       => __( 'Price when options cost different amounts.', 'ifthenpay-pay-button' ),
					'amount'     => __( 'Also in the popup\'s payment details.', 'ifthenpay-pay-button' ),
					'between'    => __( 'Under the amount the payer types.', 'ifthenpay-pay-button' ),
					'quantity'   => '',
					'name'       => '',
					'email'      => '',
					'phone'      => '',
					'nif'        => '',
					'note'       => __( 'The free-text field.', 'ifthenpay-pay-button' ),
					'optional'   => __( 'Next to fields that can be left blank.', 'ifthenpay-pay-button' ),
					'choose'     => __( 'First line of a dropdown with no placeholder.', 'ifthenpay-pay-button' ),
					'back'       => '',
					'next'       => '',
					'pay'        => __( 'The last button of the form.', 'ifthenpay-pay-button' ),
					'starting'   => __( 'On that button while the payment starts.', 'ifthenpay-pay-button' ),
					'secure'     => __( 'Before the ifthenpay logo at the bottom.', 'ifthenpay-pay-button' ),
				),
			),
			'spots'    => array(
				'title'       => __( 'Spots', 'ifthenpay-pay-button' ),
				'description' => __( 'For buttons that sell a limited number of places. Your own text is used for any number, so word it to fit one and many alike, e.g. "Left: {count}".', 'ifthenpay-pay-button' ),
				'view'        => 'checkout',
				'items'       => array(
					'spots_left'  => __( 'Under the title, when few are left.', 'ifthenpay-pay-button' ),
					'option_left' => __( 'Badge on an option.', 'ifthenpay-pay-button' ),
					'sold_out'    => __( 'Badge on an option, and the button once everything is taken.', 'ifthenpay-pay-button' ),
				),
			),
			'popup'    => array(
				'title'       => __( 'Popup after paying', 'ifthenpay-pay-button' ),
				'description' => __( 'Its titles and messages are on the Messages tab.', 'ifthenpay-pay-button' ),
				'view'        => 'paid',
				'items'       => array(
					'checking'    => __( 'While it asks ifthenpay how the payment went.', 'ifthenpay-pay-button' ),
					'reference'   => '',
					'method'      => '',
					'close'       => '',
					'redirecting' => __( 'When Paid sends the payer to a page.', 'ifthenpay-pay-button' ),
				),
			),
			'errors'   => array(
				'title'       => __( 'Problems', 'ifthenpay-pay-button' ),
				'description' => __( 'Shown above the button when something stops the payment. An empty required field gets the browser\'s own message, already in the payer\'s language.', 'ifthenpay-pay-button' ),
				'view'        => 'checkout',
				'items'       => array(
					'failed'           => '',
					'choose_one'       => __( 'A required Multiple choice with nothing ticked.', 'ifthenpay-pay-button' ),
					'fill_in'          => '',
					'tick'             => '',
					'check_field'      => __( 'An answer that isn\'t right for its field.', 'ifthenpay-pay-button' ),
					'bad_email'        => '',
					'bad_nif'          => '',
					'enter_amount'     => '',
					'amount_range'     => '',
					'pick_option'      => '',
					'bad_quantity'     => '',
					'too_much'         => '',
					'all_taken'        => '',
					'only_left'        => '',
					'option_sold_out'  => '',
					'option_only_left' => '',
					'unavailable'      => '',
					'link_invalid'     => '',
					'link_no_amount'   => '',
					'rate_limited'     => '',
					'busy'             => '',
				),
			),
			'a11y'     => array(
				'title'       => __( 'For screen readers', 'ifthenpay-pay-button' ),
				'description' => __( 'Not shown on screen, but read aloud to people who can\'t see it.', 'ifthenpay-pay-button' ),
				'view'        => 'checkout',
				'items'       => array(
					'one_less'  => __( 'The − next to Quantity.', 'ifthenpay-pay-button' ),
					'one_more'  => __( 'The + next to Quantity.', 'ifthenpay-pay-button' ),
					'suggested' => '',
					'methods'   => __( 'The logos at the bottom.', 'ifthenpay-pay-button' ),
				),
			),
		);
	}

	/**
	 * One text: the merchant's own, or the default, with its {tokens} filled in.
	 *
	 * @param string                $key    One of the defaults() keys.
	 * @param array<string, scalar> $tokens Token => value, e.g. 'count' => 3. A count also picks the plural.
	 */
	public static function get( string $key, array $tokens = array() ): string {
		$own  = ( new CheckoutSettings() )->get_texts();
		$text = $own[ $key ] ?? self::defaults( isset( $tokens['count'] ) ? (int) $tokens['count'] : 2 )[ $key ] ?? '';

		$pairs = array();

		foreach ( $tokens as $token => $value ) {
			$pairs[ '{' . $token . '}' ] = (string) $value;
		}

		return array() !== $pairs ? strtr( $text, $pairs ) : $text;
	}

	/**
	 * Keeps the known keys the merchant actually changed, as plain text.
	 *
	 * @param mixed $raw The submitted texts.
	 * @return array<string, string>
	 */
	public static function sanitize( $raw ): array {
		$raw      = is_array( $raw ) ? $raw : array();
		$defaults = self::defaults();
		$clean    = array();

		foreach ( array_keys( $defaults ) as $key ) {
			$text = is_string( $raw[ $key ] ?? null ) ? mb_substr( sanitize_text_field( $raw[ $key ] ), 0, self::MAX_LENGTH ) : '';

			// Typing the default back in is the same as leaving it blank, so it keeps following translations.
			if ( '' !== $text && $text !== $defaults[ $key ] ) {
				$clean[ $key ] = $text;
			}
		}

		return $clean;
	}
}
