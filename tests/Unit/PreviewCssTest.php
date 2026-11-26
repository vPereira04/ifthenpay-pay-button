<?php
/**
 * Checks the CSS a drawn button brings to its card on the Buttons screen.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Buttons\Button;
use Ifthenpay\PayButton\Frontend\ButtonRenderer;
use Ifthenpay\PayButton\Repository\CheckoutSettings;
use PHPUnit\Framework\TestCase;

/**
 * Only a drawn button has any, it is scoped to that button, and its animations don't clash with another's.
 */
final class PreviewCssTest extends TestCase
{

    protected function tearDown(): void
    {
        unset($GLOBALS['iftp_test_unfiltered']);
    }

    /**
     * @param array<string, mixed> $design The design.
     * @param string               $css    The button's CSS.
     * @param string               $js     The button's script.
     */
    private function button( int $id, array $design, string $css, string $js = '' ): Button
    {
        $GLOBALS['iftp_test_unfiltered'] = true;

        return new Button($id, 'T', 't', true, Button::sanitize_config(array( 'design' => $design, 'code' => array( 'button_css' => $css, 'button_js' => $js ) )));
    }

    public function test_a_drawn_buttons_script_runs_in_its_own_scope_on_the_card(): void
    {
        $js = ButtonRenderer::preview_js($this->button(7, array( 'source' => 'code' ), '.a{}', 'scope.forEach( function ( r ) {} );'), new CheckoutSettings());

        $this->assertStringContainsString('document.querySelectorAll(\'[data-iftp-code="7"]\')', $js);
        $this->assertStringContainsString('scope.forEach', $js);
        // A script that throws can't take the list down with it.
        $this->assertStringContainsString('catch(e)', $js);
    }

    public function test_only_a_drawn_button_with_a_script_has_one(): void
    {
        $this->assertSame('', ButtonRenderer::preview_js($this->button(7, array( 'source' => 'custom' ), '.a{}', 'x();'), new CheckoutSettings()));
        $this->assertSame('', ButtonRenderer::preview_js($this->button(7, array( 'source' => 'code' ), '.a{}', ''), new CheckoutSettings()));
    }

    public function test_a_button_that_is_not_drawn_brings_nothing(): void
    {
        $this->assertSame('', ButtonRenderer::preview_css($this->button(7, array( 'source' => 'custom' ), '.iftp-btn-pay__button { color: red; }'), new CheckoutSettings()));
        $this->assertSame('', ButtonRenderer::preview_css($this->button(7, array( 'source' => 'site' ), '.iftp-btn-pay__button { color: red; }'), new CheckoutSettings()));
    }

    public function test_a_drawn_button_without_css_brings_nothing(): void
    {
        $this->assertSame('', ButtonRenderer::preview_css($this->button(7, array( 'source' => 'code' ), ''), new CheckoutSettings()));
    }

    public function test_a_drawn_button_is_scoped_and_strong_enough_for_the_card(): void
    {
        $css = ButtonRenderer::preview_css($this->button(7, array( 'source' => 'code' ), '.iftp-btn-pay__button { color: red; }'), new CheckoutSettings());

        $this->assertStringContainsString(str_repeat('[data-iftp-code="7"]', 4) . '{.iftp-btn-pay__button { color: red; }', $css);
        // Hovering the preview has to reach the button, which the card switches off by default.
        $this->assertStringContainsString('{pointer-events:auto}', $css);
    }

    public function test_animations_are_renamed_per_button_and_lifted_out(): void
    {
        $css = '.iftp-btn-pay__button { animation: spin 1s; } @keyframes spin { to { opacity: 1; } }';
        $one = ButtonRenderer::preview_css($this->button(7, array( 'source' => 'code' ), $css), new CheckoutSettings());
        $two = ButtonRenderer::preview_css($this->button(8, array( 'source' => 'code' ), $css), new CheckoutSettings());

        $this->assertStringContainsString('@keyframes spin-b7', $one);
        $this->assertStringContainsString('animation: spin-b7', $one);
        $this->assertStringContainsString('@keyframes spin-b8', $two);
        // The keyframes sit before the scoped rule, never inside its braces.
        $this->assertLessThan(strpos($one, '[data-iftp-code'), strpos($one, '@keyframes'));
    }

    public function test_an_advanced_design_is_drawn_in_the_colours_picked(): void
    {
        $design = array( 'source' => 'advanced', 'advanced' => 'skate-store', 'advanced_colors' => array( '#ff0000', '', '' ) );
        $css    = ButtonRenderer::preview_css($this->button(9, $design, ''), new CheckoutSettings());

        $this->assertStringContainsString('--a1:#ff0000;--a2:#ff2d6f;', $css);
        $this->assertStringContainsString('{pointer-events:auto}', $css);
    }
}
