<?php
/**
 * Tests for elements the Wink web components no longer draw.
 *
 * The account button (<wink-account-button>) is retired: the web-components app registers the
 * tag as an inert element that renders nothing. The plugin no longer offers the block, shortcode
 * or page-builder widgets, but posts that already contain [winkaccount] must not suddenly show
 * the raw shortcode text to visitors, so the shortcode stays registered and renders nothing.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WinkRetiredElementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRetiredShortcodesStayRegisteredAndRenderNothing(): void
    {
        Functions\expect('add_shortcode')
            ->once()
            ->with('winkaccount', '__return_empty_string');

        \winkElements::registerRetiredShortcodes();

        $this->addToAssertionCount(1);
    }

    public function testTheAccountBlockClassIsGone(): void
    {
        $this->assertFalse(class_exists('winkAccount', false));
    }
}
