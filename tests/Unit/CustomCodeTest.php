<?php
/**
 * Checks the CSS a drawn button may carry, and how its @keyframes are lifted out of the button's scope.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Checkout\CustomCode;
use PHPUnit\Framework\TestCase;

/**
 * The AI writes this CSS, so what it may use and where its animations end up have to hold.
 */
final class CustomCodeTest extends TestCase
{

    public function test_keyframes_are_lifted_and_renamed_for_the_button(): void
    {
        $css = ".iftp-btn-pay__button::before{animation:fly .9s both}\n@keyframes fly{0%{transform:none}100%{transform:translateX(9em)}}\n.fly{color:red}";

        list( $frames, $rest ) = CustomCode::lift_keyframes(7, $css);

        $this->assertSame('@keyframes fly-b7{0%{transform:none}100%{transform:translateX(9em)}}', $frames);
        $this->assertStringContainsString('animation:fly-b7 .9s both', $rest);
        $this->assertStringContainsString('.fly{color:red}', $rest, 'A class that shares the name is not an animation.');
        $this->assertStringNotContainsString('@keyframes', $rest);
    }

    public function test_css_without_keyframes_is_left_alone(): void
    {
        $css = '.iftp-btn-pay__button{color:#fff}';

        $this->assertSame(array( '', $css ), CustomCode::lift_keyframes(1, $css));
    }

    public function test_keyframes_and_svg_pictures_pass_the_checks(): void
    {
        $css = ".iftp-btn-pay__button{background:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'%3E%3C/svg%3E\")}\n@keyframes spin{to{transform:rotate(1turn)}}";

        $this->assertNull(CustomCode::css_error($css, true));
    }

    public function test_font_faces_and_any_url_are_allowed_and_lifted(): void
    {
        $css = ".a{background:url(https://x.test/a.png)}\n@font-face{font-family:g;src:url(https://x.test/g.woff2)}";

        $this->assertNull(CustomCode::css_error($css, true));

        list( $lifted, $rest ) = CustomCode::lift_keyframes(3, $css);

        $this->assertStringStartsWith('@font-face', $lifted);
        $this->assertStringNotContainsString('@font-face', $rest);
    }

    /**
     * @dataProvider refused
     *
     * @param string $css CSS that must not be accepted.
     */
    public function test_css_that_cannot_sit_in_a_scope_is_refused( string $css ): void
    {
        $this->assertNotNull(CustomCode::css_error($css, true));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function refused(): array
    {
        return array(
            'import'      => array( '@import url(https://x.test/a.css);' ),
            'layer'       => array( '@layer base{.a{color:red}}' ),
            'unbalanced'  => array( '.a{color:red' ),
        );
    }
}
