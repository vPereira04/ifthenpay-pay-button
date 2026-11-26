<?php
/**
 * Checks that the AI can draw the checkout and write script, and only for users who may.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Ai\ButtonSchema;
use Ifthenpay\PayButton\Ai\Prompt;
use Ifthenpay\PayButton\Buttons\Button;
use PHPUnit\Framework\TestCase;

/**
 * The checkout's code and script follow the editor's rule: only a user who may post unfiltered HTML. The button's CSS is for everyone.
 */
final class DrawnCheckoutTest extends TestCase
{

    protected function tearDown(): void
    {
        unset($GLOBALS['iftp_test_unfiltered']);
    }

    private function may( bool $may ): void
    {
        $GLOBALS['iftp_test_unfiltered'] = $may;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkout_props(): array
    {
        $schema = ButtonSchema::schema();

        return $schema['properties']['checkout']['properties'];
    }

    public function test_the_schema_offers_checkout_code_only_to_who_may(): void
    {
        $this->may(true);
        $props = $this->checkout_props();
        $this->assertArrayHasKey('code', $props);
        $this->assertArrayHasKey('css', $props);
        $this->assertArrayHasKey('js', $props);
        $this->assertContains('css', ButtonSchema::schema()['properties']['checkout']['required']);

        $this->may(false);
        $props = $this->checkout_props();
        $this->assertArrayNotHasKey('code', $props);
        $this->assertArrayNotHasKey('css', $props);
        $this->assertArrayNotHasKey('js', $props);
    }

    public function test_the_button_script_is_offered_only_to_who_may(): void
    {
        $this->may(false);
        $this->assertArrayNotHasKey('js', ButtonSchema::schema()['properties']['button']['properties']);
        $this->assertArrayHasKey('css', ButtonSchema::schema()['properties']['button']['properties']);

        $this->may(true);
        $this->assertArrayHasKey('js', ButtonSchema::schema()['properties']['button']['properties']);
    }

    public function test_good_code_passes(): void
    {
        $this->may(true);
        $answer = array(
            'button'   => array( 'css' => '.iftp-btn-pay__button { color: red; }', 'js' => 'scope.forEach( function ( r ) {} );' ),
            'checkout' => array( 'code' => true, 'css' => '.iftp-btn-form { color: red; } @keyframes co-pop { to { opacity: 1; } }', 'js' => 'scope.forEach( function ( r ) {} );' ),
        );
        $this->assertNull(ButtonSchema::drawn_problem($answer));
    }

    public function test_broken_checkout_css_is_refused_with_its_name(): void
    {
        $this->may(true);
        $answer = array( 'checkout' => array( 'code' => true, 'css' => '.iftp-btn-form { color: red;' ) );
        $problem = ButtonSchema::drawn_problem($answer);
        $this->assertNotNull($problem);
        $this->assertStringContainsString('checkout and pay page', (string) $problem);
    }

    public function test_script_that_would_close_the_page_is_refused(): void
    {
        $this->may(true);
        $this->assertNotNull(ButtonSchema::drawn_problem(array( 'checkout' => array( 'code' => true, 'css' => '.a{}', 'js' => 'var x = "</script>";' ) )));
        $this->assertNotNull(ButtonSchema::drawn_problem(array( 'button' => array( 'css' => '.a{}', 'js' => '<!-- x' ) )));
    }

    public function test_checkout_code_is_ignored_while_it_is_off(): void
    {
        $this->may(true);
        $this->assertNull(ButtonSchema::drawn_problem(array( 'checkout' => array( 'code' => false, 'css' => '.broken {' ) )));
    }

    public function test_a_user_who_may_not_gets_no_checkout_or_script_checks_to_pass_or_fail(): void
    {
        $this->may(false);
        // Their answer's script and checkout code are never used, so they are never judged; the button's CSS still is.
        $this->assertNull(ButtonSchema::drawn_problem(array( 'checkout' => array( 'code' => true, 'css' => '.broken {' ), 'button' => array( 'css' => '.a{}', 'js' => '<!--' ) )));
        $this->assertNotNull(ButtonSchema::drawn_problem(array( 'button' => array( 'css' => '.a {' ) )));
    }

    public function test_instructions_for_script_and_checkout_are_only_for_who_may(): void
    {
        $method = new \ReflectionMethod(Prompt::class, 'drawn_checkout');
        $script = new \ReflectionMethod(Prompt::class, 'script');

        $this->may(false);
        $this->assertSame(array(), $method->invoke(null));
        $this->assertSame(array(), $script->invoke(null));

        $this->may(true);
        $text = implode("\n", $method->invoke(null));
        // The three traps of the reset, and the markup the AI has to know.
        $this->assertStringContainsString('d: path(', $text);
        $this->assertStringContainsString('.iftp-btn-sheet::before', $text);
        $this->assertStringContainsString('iftp-btn-form__trap', $text);
        $this->assertStringContainsString('iftp-btn-form__submit', $text);
        $this->assertStringContainsString('checkout.code', $text);
        $this->assertStringContainsString('requestAnimationFrame', implode("\n", $script->invoke(null)));
    }

    public function test_every_class_the_instructions_name_exists_in_the_plugin(): void
    {
        $this->may(true);
        $text  = implode("\n", (new \ReflectionMethod(Prompt::class, 'drawn_checkout'))->invoke(null));
        $found = array();
        preg_match_all('/\.?(iftp-btn-[a-z0-9_-]+)/', $text, $found);
        $source = '';

        foreach (array( 'src', 'assets/css' ) as $folder) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/' . $folder));

            foreach ($iterator as $file) {
                if ($file->isFile() && ! str_contains($file->getFilename(), '.min.') && in_array($file->getExtension(), array( 'php', 'css' ), true)) {
                    $source .= file_get_contents($file->getPathname());
                }
            }
        }

        foreach (array_unique($found[1]) as $class) {
            // Prefixes written with a suffix ("-- modifiers") and the plugin's own state names are matched on their stem.
            $this->assertStringContainsString($class, $source, $class . ' is named in the AI\'s instructions but does not exist.');
        }
    }

    public function test_the_ai_can_switch_a_checkout_to_its_own_code_and_back(): void
    {
        $this->may(true);
        $base   = Button::sanitize_config(array());
        $answer = array( 'checkout' => array( 'code' => true, 'css' => '.iftp-btn-form { color: navy; }', 'js' => '' ) );
        $made   = ButtonSchema::to_config($answer, $base, 'T');

        $this->assertSame(Button::LOOK_CODE, $made['config']['look_mode']);
        $this->assertSame('.iftp-btn-form { color: navy; }', $made['config']['code']['page_css']);

        // An answer with no CSS changes nothing, and never switches an empty checkout on.
        $empty = ButtonSchema::to_config(array( 'checkout' => array( 'code' => true, 'css' => '' ) ), $base, 'T');
        $this->assertSame(Button::LOOK_SITE, $empty['config']['look_mode']);

        // Broken CSS is not used.
        $broken = ButtonSchema::to_config(array( 'checkout' => array( 'code' => true, 'css' => '.a {' ) ), $base, 'T');
        $this->assertSame(Button::LOOK_SITE, $broken['config']['look_mode']);
        $this->assertSame('', $broken['config']['code']['page_css']);

        // Code off goes back to a ready-made look and keeps the CSS saved.
        $back = ButtonSchema::to_config(array( 'checkout' => array( 'code' => false, 'own' => true ) ), $made['config'], 'T');
        $this->assertSame(Button::LOOK_OWN, $back['config']['look_mode']);
        $this->assertSame('.iftp-btn-form { color: navy; }', $back['config']['code']['page_css']);

        // A change that sends no checkout leaves a drawn checkout alone.
        $kept = ButtonSchema::to_config(array( 'summary' => 'New words' ), $made['config'], 'T');
        $this->assertSame(Button::LOOK_CODE, $kept['config']['look_mode']);
        $this->assertSame('.iftp-btn-form { color: navy; }', $kept['config']['code']['page_css']);
    }

    public function test_a_user_who_may_not_cannot_draw_the_checkout(): void
    {
        $this->may(false);
        $base = Button::sanitize_config(array());
        $made = ButtonSchema::to_config(array( 'checkout' => array( 'code' => true, 'css' => '.iftp-btn-form { color: navy; }', 'js' => 'x()' ) ), $base, 'T');

        $this->assertNotSame(Button::LOOK_CODE, $made['config']['look_mode']);
        $this->assertSame('', $made['config']['code']['page_css']);
        $this->assertSame('', $made['config']['code']['page_js']);
    }

    public function test_the_ai_writes_its_plan_before_anything_else(): void
    {
        $this->may(true);
        $keys = array_keys(ButtonSchema::schema()['properties']);

        $this->assertSame('plan', $keys[0]);
        $this->assertContains('plan', ButtonSchema::schema()['required']);
        // A change patch does not have to send one.
        $this->assertNotContains('plan', ButtonSchema::schema(true)['required']);
    }

    public function test_asks_that_draw_get_more_thinking(): void
    {
        $this->assertTrue(Prompt::draws('make a football button by code'));
        $this->assertTrue(Prompt::draws('faz um botão personalizado'));
        $this->assertFalse(Prompt::draws('change the price to 20'));
    }
}
