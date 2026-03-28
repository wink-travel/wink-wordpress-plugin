<?php
/**
 * Tests for winkCore::environmentURL().
 *
 * This is the simplest unit test target in the codebase: a pure static method that
 * takes two strings and returns a URL. No WordPress functions, no database, no mocks.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

// wink.php (and therefore winkCore) is loaded by tests/bootstrap.php.

class WinkCoreTest extends TestCase
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

    // ── Production environment ────────────────────────────────────────────────

    public function test_production_js_url(): void
    {
        $this->assertSame(
            'https://elements.wink.travel',
            \winkCore::environmentURL('js', 'production')
        );
    }

    public function test_production_json_url(): void
    {
        $this->assertSame(
            'https://iam.wink.travel',
            \winkCore::environmentURL('json', 'production')
        );
    }

    public function test_production_api_url(): void
    {
        $this->assertSame(
            'https://api.wink.travel',
            \winkCore::environmentURL('api', 'production')
        );
    }

    // ── Staging environment ───────────────────────────────────────────────────

    public function test_staging_js_url(): void
    {
        $this->assertSame(
            'https://staging-elements.wink.travel',
            \winkCore::environmentURL('js', 'staging')
        );
    }

    public function test_staging_json_url(): void
    {
        $this->assertSame(
            'https://staging-iam.wink.travel',
            \winkCore::environmentURL('json', 'staging')
        );
    }

    public function test_staging_api_url(): void
    {
        $this->assertSame(
            'https://staging-api.wink.travel',
            \winkCore::environmentURL('api', 'staging')
        );
    }

    // ── Development environment ───────────────────────────────────────────────

    public function test_development_js_url(): void
    {
        $this->assertSame(
            'https://dev.traveliko.com:8011',
            \winkCore::environmentURL('js', 'development')
        );
    }

    public function test_development_json_url(): void
    {
        $this->assertSame(
            'https://dev.traveliko.com:9000',
            \winkCore::environmentURL('json', 'development')
        );
    }

    public function test_development_api_url(): void
    {
        $this->assertSame(
            'https://dev.traveliko.com:8443',
            \winkCore::environmentURL('api', 'development')
        );
    }

    // ── Unknown environment falls back to production ──────────────────────────

    public function test_unknown_environment_defaults_to_production_js(): void
    {
        // An unrecognised environment (e.g. a typo like 'prod') must not crash the
        // site — the match default arm returns production URLs safely.
        $this->assertSame(
            'https://elements.wink.travel',
            \winkCore::environmentURL('js', 'prod')
        );
    }

    public function test_unknown_environment_defaults_to_production_api(): void
    {
        $this->assertSame(
            'https://api.wink.travel',
            \winkCore::environmentURL('api', 'anything_unexpected')
        );
    }

    // ── Unknown target throws ─────────────────────────────────────────────────

    public function test_unknown_target_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \winkCore::environmentURL('ftp', 'production');
    }

    public function test_unknown_target_in_staging_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \winkCore::environmentURL('css', 'staging');
    }

    // ── Return type is always a non-empty string ──────────────────────────────

    public function test_all_known_combinations_return_non_empty_strings(): void
    {
        $targets      = ['js', 'json', 'api'];
        $environments = ['production', 'staging', 'development'];

        foreach ($environments as $env) {
            foreach ($targets as $target) {
                $url = \winkCore::environmentURL($target, $env);
                $this->assertIsString($url, "{$target}/{$env} should return a string");
                $this->assertNotEmpty($url, "{$target}/{$env} should not be empty");
                $this->assertStringStartsWith('https://', $url, "{$target}/{$env} should be HTTPS");
            }
        }
    }
}
