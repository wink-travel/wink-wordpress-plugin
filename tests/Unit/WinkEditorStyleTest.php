<?php
/**
 * Tests for how the block previews' stylesheet reaches the block editor.
 *
 * Blocks are registered as block API version 3, so the editor canvas is an iframe. Styles
 * added on 'enqueue_block_editor_assets' only reach the outer page, not the iframe, which
 * leaves every Wink block preview unstyled. 'enqueue_block_assets' is the hook WordPress
 * copies into the iframe, but it also fires on the front end, so the callback must only
 * enqueue inside wp-admin.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WinkEditorStyleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('plugin_dir_url')->justReturn('https://example.com/wp-content/plugins/wink/includes/');
        Functions\when('trailingslashit')->alias(static fn (string $s): string => rtrim($s, '/') . '/');
        Functions\when('get_option')->returnArg(2);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('wp_register_script')->justReturn(true);
        Functions\when('wp_localize_script')->justReturn(true);
        Functions\when('register_block_type')->justReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function allBlocks(): array
    {
        return [
            'lookup'         => ['winkLookup'],
            'search'         => ['winkSearch'],
            'account'        => ['winkAccount'],
            'itinerary'      => ['winkItinerary'],
            'itinerary form' => ['winkItineraryForm'],
            'content'        => ['winkContent'],
        ];
    }

    #[DataProvider('allBlocks')]
    public function testEveryBlockLoadsTheStylesheetThroughTheIframeAwareHook(string $class): void
    {
        $block = new $class();

        Actions\expectAdded('enqueue_block_assets')->once();
        Actions\expectAdded('enqueue_block_editor_assets')->never();

        $block->gutenbergBlockRegistration();

        $this->addToAssertionCount(1);
    }

    public function testStylesheetIsEnqueuedInsideWpAdmin(): void
    {
        $block = new \winkLookup();

        Functions\when('is_admin')->justReturn(true);
        Functions\expect('wp_enqueue_style')
            ->once()
            ->with(\Mockery::type('string'), 'https://example.com/wp-content/plugins/wink/includes/elements/css/elements.css', \Mockery::any(), \Mockery::any());

        $block->enqueueEditorStyle();

        $this->addToAssertionCount(1);
    }

    public function testStylesheetIsNotEnqueuedOnTheFrontEnd(): void
    {
        $block = new \winkLookup();

        Functions\when('is_admin')->justReturn(false);
        Functions\expect('wp_enqueue_style')->never();

        $block->enqueueEditorStyle();

        $this->addToAssertionCount(1);
    }
}
