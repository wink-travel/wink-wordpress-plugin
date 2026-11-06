<?php
/**
 * Regression tests for "translations loaded too early".
 *
 * Since WordPress 6.7, calling a translation function (esc_html__, __, ...) before the
 * 'init' action makes WordPress log a notice. The block classes are instantiated while
 * the plugin file loads, so their constructors must never translate anything. Labels are
 * translated later, when shortcodeData() is called through the 'winkShortcodes' filter.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WinkElementsTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Minimal WordPress functions the constructors depend on.
        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/wink/includes/');
        Functions\when('trailingslashit')->alias(static fn (string $s): string => rtrim($s, '/') . '/');
        Functions\when('get_option')->returnArg(2);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('add_shortcode')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public static function simpleBlocks(): array
    {
        return [
            'lookup'         => ['winkLookup', 'winklookup', 'Wink Lookup'],
            'search'         => ['winkSearch', 'winksearch', 'Wink Search'],
            'itinerary'      => ['winkItinerary', 'winkitinerary', 'wink Itinerary Button'],
            'itinerary form' => ['winkItineraryForm', 'winkitineraryform', 'wink Itinerary Form'],
        ];
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function allBlocks(): array
    {
        return [
            'lookup'         => ['winkLookup'],
            'search'         => ['winkSearch'],
            'itinerary'      => ['winkItinerary'],
            'itinerary form' => ['winkItineraryForm'],
            'content'        => ['winkContent'],
        ];
    }

    #[DataProvider('allBlocks')]
    public function testConstructorDoesNotTranslate(string $class): void
    {
        Functions\expect('esc_html__')->never();
        Functions\expect('esc_attr__')->never();
        Functions\expect('__')->never();

        new $class();

        // expect()->never() is verified when Monkey\tearDown() runs; add a real assertion too.
        $this->addToAssertionCount(1);
    }

    #[DataProvider('simpleBlocks')]
    public function testShortcodeDataStillReturnsTranslatedName(string $class, string $code, string $label): void
    {
        $block = new $class();

        Functions\when('esc_html__')->alias(static fn (string $text): string => $text);
        Functions\when('__')->alias(static fn (string $text): string => $text);

        $data = $block->shortcodeData([]);

        $this->assertCount(1, $data);
        $this->assertSame($code, $data[0]['code']);
        $this->assertSame($label, $data[0]['name']);
    }
}
