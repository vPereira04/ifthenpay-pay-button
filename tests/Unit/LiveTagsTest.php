<?php
/**
 * Live tags in a form's own text.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Frontend\LiveTags;
use PHPUnit\Framework\TestCase;

final class LiveTagsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! function_exists( 'esc_html' ) ) {
			eval( 'function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a stand-in for WordPress.
		}

		if ( ! function_exists( 'esc_attr' ) ) {
			eval( 'function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- a stand-in for WordPress.
		}
	}

	public function test_wraps_a_known_tag_with_its_fallback(): void {
		$html = LiveTags::html( 'Hey {first_name|there}!', array( 'first_name' ) );

		$this->assertSame( 'Hey <span class="iftp-btn-form__tag" data-iftp-btn-tag="cf_first_name" data-iftp-btn-fallback="there">there</span>!', $html );
	}

	public function test_field_prefix_works_too(): void {
		$this->assertStringContainsString( 'data-iftp-btn-tag="cf_company"', LiveTags::html( '{field:company}', array( 'company' ) ) );
	}

	public function test_unknown_tag_stays_as_typed_and_text_is_escaped(): void {
		$this->assertSame( 'Hi {nobody} &lt;b&gt;', LiveTags::html( 'Hi {nobody} <b>', array( 'first_name' ) ) );
	}
}
