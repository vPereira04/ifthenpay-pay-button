<?php
/**
 * Checks when a chat message counts as being about the button.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Ai\Prompt;
use PHPUnit\Framework\TestCase;

/**
 * A message about the card, the dark mode or the form must leave the button alone; one about the button, or a redesign, may change it.
 */
final class PromptScopeTest extends TestCase
{

    /**
     * @dataProvider aboutOtherThings
     *
     * @param string $message What the merchant wrote.
     */
    public function test_other_changes_leave_the_button( string $message ): void
    {
        $this->assertFalse(Prompt::touches_button($message));
    }

    /**
     * @dataProvider aboutTheButton
     *
     * @param string $message What the merchant wrote.
     */
    public function test_button_and_redesign_requests_may_change_it( string $message ): void
    {
        $this->assertTrue(Prompt::touches_button($message));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function aboutOtherThings(): array
    {
        return array(
        'dark colours' => array( 'dont use dark blue for the background, use dark gray or black' ),
        'the form'     => array( 'add a field for the number of guests and a wine pairing option' ),
        'a reservation' => array( 'make the dining reservation form shorter' ),
        'portuguese'   => array( 'usa um fundo preto em vez de azul escuro' ),
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function aboutTheButton(): array
    {
        return array(
        'the button'  => array( 'make the button gold' ),
        'portuguese'  => array( 'muda o botão para dourado' ),
        'a redesign'  => array( 'redesign everything, it looks boring' ),
        'a new style' => array( 'I want a completely different look' ),
        );
    }
}
