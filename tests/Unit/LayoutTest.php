<?php
/**
 * Checks the layout look at a drawn button's CSS.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Ai\Layout;
use PHPUnit\Framework\TestCase;

/**
 * It must catch art that sits on the label, and stay quiet about every layout that is fine or that it can't read.
 */
final class LayoutTest extends TestCase
{

    public function test_the_football_a_small_model_drew_is_caught(): void
    {
        // Gemini 2.5 Flash: padding .95em 2.6em, a 2em ball at left 1em.
        $css   = (string) file_get_contents(dirname(__DIR__) . '/fixtures/gemini-football.css');
        $clash = Layout::clash($css);

        $this->assertNotNull($clash);
        $this->assertStringContainsString('::before', (string) $clash);
        $this->assertStringContainsString('padding-left', (string) $clash);
        $this->assertStringContainsString('3.6em', (string) $clash);
    }

    public function test_room_for_the_art_passes(): void
    {
        $css = '.iftp-btn-pay__button { padding: .95em 2.6em .95em 3.2em; } .iftp-btn-pay__button::before { position: absolute; left: 1em; width: .9em; height: .9em; }';
        $this->assertNull(Layout::clash($css));
    }

    public function test_the_right_side_is_checked_too(): void
    {
        $css   = '.iftp-btn-pay__button { padding: 1em 1.5em; } .iftp-btn-pay__button::after { position: absolute; right: 1em; width: 2em; }';
        $clash = Layout::clash($css);

        $this->assertNotNull($clash);
        $this->assertStringContainsString('padding-right', (string) $clash);
    }

    public function test_variables_and_pixels_are_worked_out(): void
    {
        $fine = '.iftp-btn-pay__button { --ball: 30px; padding: 0 36px 0 66px; } .iftp-btn-pay__button::after { position: absolute; left: 14px; width: var(--ball); }';
        $this->assertNull(Layout::clash($fine));

        $bad = '.iftp-btn-pay__button { --ball: 40px; padding: 0 20px; } .iftp-btn-pay__button::after { position: absolute; left: 14px; width: var(--ball); }';
        $this->assertNotNull(Layout::clash($bad));
    }

    public function test_what_it_cannot_read_is_left_alone(): void
    {
        // A set width leaves free room; percentages, calc and big backdrops are not motifs; a hover state is not the resting place.
        $this->assertNull(Layout::clash('.iftp-btn-pay__button { min-width: 12em; padding: 1em; } .iftp-btn-pay__button::before { position: absolute; left: 0; width: 3em; }'));
        $this->assertNull(Layout::clash('.iftp-btn-pay__button { padding: 1em; } .iftp-btn-pay__button::before { position: absolute; left: 0; width: 100%; }'));
        $this->assertNull(Layout::clash('.iftp-btn-pay__button { padding: 1em; } .iftp-btn-pay__button::before { position: absolute; left: calc(1em + 2px); width: 3em; }'));
        $this->assertNull(Layout::clash('.iftp-btn-pay__button { padding: 1em; } .iftp-btn-pay__button:hover::before { position: absolute; left: 0; width: 3em; }'));
        $this->assertNull(Layout::clash('.iftp-btn-pay__button { padding: 1em; } .iftp-btn-pay__button::before { left: 0; width: 3em; }'));
        $this->assertNull(Layout::clash(''));
    }
}
