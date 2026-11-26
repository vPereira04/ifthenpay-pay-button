<?php
/**
 * Checks the AI's answer size and wait settings.
 *
 * @package Ifthenpay\PayButton
 */

declare(strict_types=1);

namespace Ifthenpay\PayButton\Tests\Unit;

use Ifthenpay\PayButton\Ai\AiSettings;
use Ifthenpay\PayButton\Ai\Providers\Claude;
use PHPUnit\Framework\TestCase;

/**
 * A drawn button and checkout are long, so a site starts with room for them, and the merchant can change both limits.
 */
final class AiSettingsTest extends TestCase
{

    public function test_a_site_starts_with_room_for_drawn_code(): void
    {
        $defaults = AiSettings::defaults();
        $this->assertSame(16000, $defaults['answer']);
        $this->assertSame(120, $defaults['wait']);
    }

    public function test_settings_saved_before_these_existed_get_the_defaults(): void
    {
        $old = AiSettings::sanitize(array( 'hourly' => 30, 'thinking' => 'deep' ), true);
        $this->assertSame(16000, $old['answer']);
        $this->assertSame(120, $old['wait']);
        $this->assertSame(30, $old['hourly']);
    }

    public function test_every_offered_value_is_kept(): void
    {
        foreach (AiSettings::ANSWER as $size) {
            $this->assertSame($size, AiSettings::sanitize(array( 'answer' => (string) $size ))['answer']);
        }

        foreach (AiSettings::WAIT as $seconds) {
            $this->assertSame($seconds, AiSettings::sanitize(array( 'wait' => (string) $seconds ))['wait']);
        }
    }

    public function test_a_value_that_is_not_offered_falls_back(): void
    {
        $clean = AiSettings::sanitize(array( 'answer' => '999999', 'wait' => '3600' ));
        $this->assertSame(16000, $clean['answer']);
        $this->assertSame(120, $clean['wait']);

        $junk = AiSettings::sanitize(array( 'answer' => 'lots', 'wait' => array( 1 ) ));
        $this->assertSame(16000, $junk['answer']);
        $this->assertSame(120, $junk['wait']);
    }

    public function test_the_answer_size_reaches_the_request_and_stays_in_bounds(): void
    {
        $provider = new Claude();
        $read     = static function ( Claude $provider ): int {
            $property = new \ReflectionProperty($provider, 'answer');

            return $property->getValue($provider);
        };

        $provider->set_answer_size(32000);
        $this->assertSame(32000, $read($provider));

        $provider->set_answer_size(10);
        $this->assertSame(1024, $read($provider));

        $provider->set_answer_size(10000000);
        $this->assertSame(64000, $read($provider));
    }
}
