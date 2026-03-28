<?php
/**
 * Tests for WinkApiClient.
 *
 * WinkApiClient calls WordPress functions (get_transient, set_transient, wp_remote_post,
 * wp_remote_get, etc.). Brain\Monkey lets us define what those functions return in each
 * test without needing a real WordPress installation or a live Wink API.
 *
 * Pattern used throughout:
 *   Monkey\Functions\when('wordpress_function')->justReturn($value)
 *   — tells Brain\Monkey "whenever this WP function is called, return this value".
 *
 * Note: winkCore::environmentURL() is a real static method loaded by the bootstrap,
 * so it is called directly — Brain\Monkey cannot mock static class methods.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WinkApiClientTest extends TestCase
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

    // ── Empty credentials short-circuit ──────────────────────────────────────

    public function test_getLayouts_returns_empty_array_when_clientId_is_empty(): void
    {
        $client = new \WinkApiClient('', 'secret', 'production');
        $result = $client->getLayouts();
        $this->assertSame([], $result);
    }

    public function test_getLayouts_returns_empty_array_when_clientSecret_is_empty(): void
    {
        $client = new \WinkApiClient('client-id', '', 'production');
        $result = $client->getLayouts();
        $this->assertSame([], $result);
    }

    public function test_getLayouts_returns_empty_array_when_both_credentials_empty(): void
    {
        $client = new \WinkApiClient('', '', 'production');
        $result = $client->getLayouts();
        $this->assertSame([], $result);
    }

    // ── Layout cache hit ──────────────────────────────────────────────────────

    public function test_getLayouts_returns_cached_layouts_without_calling_api(): void
    {
        $cachedLayouts = [
            ['id' => 'layout-1', 'name' => 'Hotel Grid', 'layout' => 'HOTEL'],
            ['id' => 'layout-2', 'name' => 'Tour List',  'layout' => 'TOUR'],
        ];

        // Transient hit — layouts are in cache, no API call needed.
        Functions\when('get_transient')->justReturn($cachedLayouts);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $result = $client->getLayouts();

        $this->assertSame($cachedLayouts, $result);
    }

    // ── Token cache hit, layout cache miss ────────────────────────────────────

    public function test_getLayouts_uses_cached_bearer_token_when_available(): void
    {
        $layouts = [['id' => 'l1', 'name' => 'Hotels', 'layout' => 'HOTEL']];

        // First call: layout transient miss, then bearer token transient hit.
        Functions\expect('get_transient')
            ->once()->with('wink_layouts_data')->andReturn(false)
            ->andAlsoExpectIt()
            ->once()->with('wink_bearer_token')->andReturn('cached-bearer-token');

        // With the cached token, fetchLayouts is called.
        // winkCore::environmentURL() is the real implementation from bootstrap.
        $apiResponse = [
            'headers'  => [],
            'body'     => json_encode($layouts),
            'response' => ['code' => 200],
        ];
        Functions\when('wp_remote_get')->justReturn($apiResponse);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($layouts));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $result = $client->getLayouts();

        $this->assertSame($layouts, $result);
    }

    // ── Token cache miss triggers OAuth request ───────────────────────────────

    public function test_getBearerToken_fetches_new_token_when_cache_empty(): void
    {
        $layouts      = [['id' => 'l1', 'name' => 'Hotels', 'layout' => 'HOTEL']];
        $tokenPayload = json_encode(['access_token' => 'fresh-token', 'expires_in' => 3600]);

        // Both transients miss — need to fetch token then layouts.
        Functions\expect('get_transient')
            ->once()->with('wink_layouts_data')->andReturn(false)
            ->andAlsoExpectIt()
            ->once()->with('wink_bearer_token')->andReturn(false);

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        // OAuth POST response.
        Functions\when('wp_remote_post')->justReturn(['body' => $tokenPayload]);
        Functions\when('wp_remote_retrieve_body')
            ->alias(function($response) use ($tokenPayload, $layouts) {
                // First call is for the token, second for layouts.
                static $calls = 0;
                $calls++;
                return $calls === 1 ? $tokenPayload : json_encode($layouts);
            });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_get')->justReturn(['body' => json_encode($layouts)]);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $result = $client->getLayouts();

        $this->assertSame($layouts, $result);
    }

    // ── OAuth failure throws WinkAuthException ────────────────────────────────

    public function test_getLayouts_throws_WinkAuthException_when_oauth_returns_wp_error(): void
    {
        $this->expectException(\WinkAuthException::class);
        $this->expectExceptionMessageMatches('/OAuth token request failed/');

        Functions\when('get_transient')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(new \WP_Error('http_request_failed', 'Connection refused'));
        Functions\when('is_wp_error')->alias(function($v) { return $v instanceof \WP_Error; });

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();
    }

    public function test_getLayouts_throws_WinkAuthException_when_oauth_response_missing_token(): void
    {
        $this->expectException(\WinkAuthException::class);
        $this->expectExceptionMessageMatches('/access_token/');

        Functions\when('get_transient')->justReturn(false);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_post')->justReturn(['body' => json_encode(['error' => 'invalid_client'])]);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['error' => 'invalid_client']));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();
    }

    // ── Layout API failure throws WinkDataException ───────────────────────────

    public function test_getLayouts_throws_WinkDataException_when_layout_api_returns_wp_error(): void
    {
        $this->expectException(\WinkDataException::class);
        $this->expectExceptionMessageMatches('/Layout API request failed/');

        // Token is cached so we skip OAuth.
        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'valid-token' : false;
            });

        Functions\when('wp_remote_get')->justReturn(new \WP_Error('http_request_failed', 'Timeout'));
        Functions\when('is_wp_error')->alias(function($v) { return $v instanceof \WP_Error; });

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();
    }

    public function test_getLayouts_throws_WinkDataException_on_non_200_http_status(): void
    {
        $this->expectException(\WinkDataException::class);
        $this->expectExceptionMessageMatches('/HTTP 503/');

        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'valid-token' : false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_get')->justReturn(['body' => '{"error":"maintenance"}']);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"error":"maintenance"}');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(503);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();
    }

    // ── 401 response deletes bearer transient ────────────────────────────────

    public function test_getLayouts_deletes_bearer_transient_on_401_response(): void
    {
        $deletedKeys = [];

        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'stale-token' : false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_get')->justReturn(['body' => '{"message":"Unauthorized"}']);
        Functions\when('wp_remote_retrieve_body')->justReturn('{"message":"Unauthorized"}');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);
        Functions\when('delete_transient')->alias(function($key) use (&$deletedKeys) {
            $deletedKeys[] = $key;
        });

        try {
            $client = new \WinkApiClient('client-id', 'secret', 'production');
            $client->getLayouts();
        } catch (\WinkAuthException $e) {
            // Expected.
        }

        $this->assertContains(
            'wink_bearer_token',
            $deletedKeys,
            'The bearer token transient must be deleted when the API returns 401'
        );
    }

    // ── Successful layouts are cached ─────────────────────────────────────────

    public function test_getLayouts_caches_result_with_WINK_CACHE_TTL(): void
    {
        $layouts = [['id' => 'l1', 'name' => 'Hotels', 'layout' => 'HOTEL']];
        $setCalls = [];

        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'valid-token' : false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_get')->justReturn(['body' => json_encode($layouts)]);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode($layouts));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('set_transient')->alias(function($key, $value, $ttl) use (&$setCalls) {
            $setCalls[] = ['key' => $key, 'ttl' => $ttl];
        });

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();

        $layoutCache = array_filter($setCalls, fn($c) => $c['key'] === 'wink_layouts_data');
        $this->assertNotEmpty($layoutCache, 'Layout data should be cached');

        $cached = array_values($layoutCache)[0];
        $this->assertSame(WINK_CACHE_TTL, $cached['ttl'], 'TTL should equal WINK_CACHE_TTL constant');
    }

    // ── SSL verification disabled only for development ────────────────────────

    public function test_ssl_verification_enabled_for_production(): void
    {
        $capturedArgs = [];

        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'valid-token' : false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_get')->alias(function($url, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return ['body' => '[]'];
        });
        Functions\when('wp_remote_retrieve_body')->justReturn('[]');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('set_transient')->justReturn(true);

        $client = new \WinkApiClient('client-id', 'secret', 'production');
        $client->getLayouts();

        $this->assertTrue($capturedArgs['sslverify'], 'SSL verification must be enabled for production');
    }

    public function test_ssl_verification_disabled_for_development(): void
    {
        $capturedArgs = [];

        Functions\when('get_transient')
            ->alias(function($key) {
                return $key === 'wink_bearer_token' ? 'valid-token' : false;
            });

        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('error_log')->justReturn(null);
        Functions\when('wp_remote_get')->alias(function($url, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return ['body' => '[]'];
        });
        Functions\when('wp_remote_retrieve_body')->justReturn('[]');
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('set_transient')->justReturn(true);

        $client = new \WinkApiClient('client-id', 'secret', 'development');
        $client->getLayouts();

        $this->assertFalse($capturedArgs['sslverify'], 'SSL verification must be disabled for development');
    }
}
