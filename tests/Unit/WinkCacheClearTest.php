<?php
/**
 * Tests for wink::clearwinkCache().
 *
 * Verifies that clearing the cache:
 * - Requires the manage_options capability (access control)
 * - Deletes all four transient and option keys
 * - Does nothing when called by a non-admin user
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Minimal testable subclass that exposes clearwinkCache() without running
 * the full wink constructor (which registers WordPress hooks and reads options).
 */
class TestableWink extends \wink
{
    public function __construct()
    {
        // Intentionally skip parent::__construct() to avoid hook registration.
        // Only the properties needed by clearwinkCache() are initialised.
    }

    public function clearwinkCachePublic(): void
    {
        $this->clearwinkCache();
    }
}

class WinkCacheClearTest extends TestCase
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

    public function test_clearwinkCache_deletes_all_transients_and_options_for_admin(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $deleted = [];
        Functions\when('delete_transient')->alias(function($key) use (&$deleted) { $deleted[] = $key; });
        Functions\when('delete_option')->alias(function($key) use (&$deleted) { $deleted[] = $key; });

        (new TestableWink())->clearwinkCachePublic();

        $this->assertContains('wink_bearer_token',  $deleted);
        $this->assertContains('wink_layouts_data',  $deleted);
        $this->assertContains('wink_auth_error',    $deleted);
        $this->assertContains('winkData',           $deleted);
        $this->assertContains('winkdataTime',       $deleted);
        $this->assertContains('winkcontentTime',    $deleted);
        $this->assertContains('winkcontentBearer',  $deleted);
    }

    public function test_clearwinkCache_does_nothing_for_non_admin(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $deleted = [];
        Functions\when('delete_transient')->alias(function($key) use (&$deleted) { $deleted[] = $key; });
        Functions\when('delete_option')->alias(function($key) use (&$deleted) { $deleted[] = $key; });

        (new TestableWink())->clearwinkCachePublic();

        $this->assertEmpty($deleted, 'Non-admin must not be able to clear the cache');
    }

    public function test_clearwinkCache_deletes_exactly_seven_keys(): void
    {
        Functions\when('current_user_can')->justReturn(true);

        $deleted = [];
        Functions\when('delete_transient')->alias(function($key) use (&$deleted) { $deleted[] = $key; });
        Functions\when('delete_option')->alias(function($key) use (&$deleted) { $deleted[] = $key; });

        (new TestableWink())->clearwinkCachePublic();

        $this->assertCount(7, $deleted, 'Expected exactly 7 keys to be deleted');
    }
}
