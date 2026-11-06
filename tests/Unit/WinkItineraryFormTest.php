<?php
/**
 * The Wink web components app registers the itinerary form component under the tag
 * <wink-itinerary-picker>. The old <wink-itinerary-form> tag is not defined, so a block that
 * emitted it rendered nothing.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WinkItineraryFormTest extends TestCase
{
    private \winkItineraryForm $block;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/wink/includes/');
        Functions\when('trailingslashit')->alias(static fn (string $s): string => rtrim($s, '/') . '/');
        Functions\when('get_option')->returnArg(2);
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('sanitize_key')->returnArg(1);

        $this->block = new \winkItineraryForm();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRendersTheRegisteredPickerTag(): void
    {
        $this->assertSame(
            '<wink-itinerary-picker></wink-itinerary-picker>',
            $this->block->winkElement()
        );
    }

    public function testDoesNotRenderTheUndefinedFormTag(): void
    {
        $this->assertStringNotContainsString('wink-itinerary-form', $this->block->winkElement());
    }
}
