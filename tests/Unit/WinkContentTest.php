<?php
/**
 * Tests for winkContent::winkElement() and winkContent::resolveLayoutType().
 *
 * These tests verify the HTML rendering logic and how the block handles:
 * - Known / unknown layout IDs
 * - The WPBakery lowercase 'layoutid' attribute alias
 * - Editor-context detection (should return escaped HTML)
 * - API failure fallback behaviour (should return 'HOTEL' default)
 * - The "escape late" security contract (esc_attr applied at output, not input)
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Concrete test subclass of winkContent that bypasses the constructor's WordPress
 * hook registration and WinkApiClient instantiation, substituting a mock client.
 */
class TestableWinkContent extends \winkContent
{
    public \WinkApiClient $apiClient;

    public function __construct(\WinkApiClient $apiClient)
    {
        // Skip parent::__construct() entirely — it calls add_action, add_shortcode,
        // get_option, etc. which are not relevant here. Set the minimum properties.
        $this->blockCode      = 'winkcontent';
        $this->blockName      = 'wink Content';
        $this->attributes     = [];
        $this->pluginURL      = 'https://example.com/wp-content/plugins/wink/includes/';
        $this->imgURL         = 'https://example.com/wp-content/plugins/wink/img/';
        $this->environmentVal = 'production';
        $this->clientIdKey    = WINK_CLIENT_ID_OPTION;
        $this->clientSecretKey = 'winkSecret';
        $this->apiClient      = $apiClient;
    }

    // Expose the protected resolveLayoutType() for direct testing.
    public function resolveLayoutTypePublic(string $layoutId): string
    {
        return $this->resolveLayoutType($layoutId);
    }
}

// elementHandler.php (winkElements base class) is loaded by tests/bootstrap.php.
require_once dirname(__DIR__, 2) . '/includes/elements/winkcontent.php';

class WinkContentTest extends TestCase
{
    private \WinkApiClient $mockApiClient;
    private TestableWinkContent $content;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub WordPress escaping functions used by winkElement().
        Functions\when('esc_attr')->alias(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES));
        Functions\when('esc_html')->alias(fn($v) => htmlspecialchars((string) $v, ENT_QUOTES));
        Functions\when('is_admin')->justReturn(false);
        Functions\when('sanitize_key')->alias(fn($v) => strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $v)));
        Functions\when('error_log')->justReturn(null);
        Functions\when('set_transient')->justReturn(true);

        $this->mockApiClient = Mockery::mock(\WinkApiClient::class);
        $this->content       = new TestableWinkContent($this->mockApiClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── winkElement: basic attribute rendering ────────────────────────────────

    public function test_winkElement_renders_content_loader_tag(): void
    {
        $output = $this->content->winkElement([]);
        $this->assertStringContainsString('<wink-content-loader', $output);
        $this->assertStringContainsString('</wink-content-loader>', $output);
    }

    public function test_winkElement_includes_layout_attribute_when_provided(): void
    {
        $output = $this->content->winkElement(['layout' => 'HOTEL']);
        $this->assertStringContainsString('layout="HOTEL"', $output);
    }

    public function test_winkElement_includes_id_attribute_when_layoutId_provided(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([]);
        $output = $this->content->winkElement(['layoutId' => 'abc-123']);
        $this->assertStringContainsString('id="abc-123"', $output);
    }

    // ── winkElement: WPBakery lowercase alias ─────────────────────────────────

    public function test_winkElement_accepts_lowercase_layoutid_from_wpbakery(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([]);
        $output = $this->content->winkElement(['layoutid' => 'wpb-layout-id']);
        $this->assertStringContainsString('id="wpb-layout-id"', $output);
    }

    // ── winkElement: layout type resolution ───────────────────────────────────

    public function test_winkElement_resolves_layout_type_from_api_when_only_id_given(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->once()->andReturn([
            ['id' => 'abc-123', 'name' => 'Hotels', 'layout' => 'HOTEL'],
            ['id' => 'def-456', 'name' => 'Tours',  'layout' => 'TOUR'],
        ]);

        $output = $this->content->winkElement(['layoutId' => 'def-456']);
        $this->assertStringContainsString('layout="TOUR"', $output);
    }

    public function test_winkElement_uses_explicit_layout_attribute_without_api_call(): void
    {
        // getLayouts should NOT be called when 'layout' is explicitly provided without 'layoutId'.
        $this->mockApiClient->shouldNotReceive('getLayouts');

        $output = $this->content->winkElement(['layout' => 'ACTIVITY']);
        $this->assertStringContainsString('layout="ACTIVITY"', $output);
    }

    public function test_winkElement_defaults_to_HOTEL_when_layout_id_not_in_api_results(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([
            ['id' => 'different-id', 'name' => 'Hotels', 'layout' => 'HOTEL'],
        ]);

        $output = $this->content->winkElement(['layoutId' => 'unknown-id']);
        $this->assertStringContainsString('layout="HOTEL"', $output);
    }

    // ── winkElement: editor context ───────────────────────────────────────────

    public function test_winkElement_returns_escaped_html_in_editor_context(): void
    {
        // Simulate Gutenberg editor REST request.
        $_REQUEST['context'] = 'edit';

        $output = $this->content->winkElement([]);

        // In editor mode the tag should be HTML-entity-escaped so the browser
        // renders the tag text rather than initialising the web component.
        $this->assertStringContainsString('&lt;wink-content-loader', $output);
        $this->assertStringNotContainsString('<wink-content-loader>', $output);

        unset($_REQUEST['context']);
    }

    public function test_winkElement_returns_raw_html_outside_editor(): void
    {
        Functions\when('is_admin')->justReturn(false);
        unset($_REQUEST['context'], $_REQUEST['action']);

        $output = $this->content->winkElement([]);
        $this->assertStringContainsString('<wink-content-loader', $output);
        $this->assertStringNotContainsString('&lt;wink-content-loader', $output);
    }

    // ── winkElement: XSS / escaping contract ─────────────────────────────────

    public function test_winkElement_escapes_layout_attribute_value(): void
    {
        // If somehow a layout attribute contained a quote, it must be encoded.
        $malicious = 'HOTEL" onload="alert(1)';
        $output = $this->content->winkElement(['layout' => $malicious]);

        // The raw quote must not appear in the output.
        $this->assertStringNotContainsString('"HOTEL" onload', $output);
        // But the value should still be present in escaped form.
        $this->assertStringContainsString('HOTEL', $output);
    }

    public function test_winkElement_escapes_id_attribute_value(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([]);
        $malicious = 'id"><script>alert(1)</script>';
        $output = $this->content->winkElement(['layoutId' => $malicious]);

        $this->assertStringNotContainsString('<script>', $output);
    }

    // ── resolveLayoutType: direct tests ──────────────────────────────────────

    public function test_resolveLayoutType_returns_layout_for_matching_id(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([
            ['id' => 'tour-1', 'name' => 'Tours', 'layout' => 'TOUR'],
        ]);

        $result = $this->content->resolveLayoutTypePublic('tour-1');
        $this->assertSame('TOUR', $result);
    }

    public function test_resolveLayoutType_returns_HOTEL_when_no_match(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([
            ['id' => 'something-else', 'name' => 'Other', 'layout' => 'ACTIVITY'],
        ]);

        $result = $this->content->resolveLayoutTypePublic('non-existent');
        $this->assertSame('HOTEL', $result);
    }

    public function test_resolveLayoutType_returns_HOTEL_when_layout_field_empty(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')->andReturn([
            ['id' => 'layout-x', 'name' => 'Unknown', 'layout' => ''],
        ]);

        $result = $this->content->resolveLayoutTypePublic('layout-x');
        $this->assertSame('HOTEL', $result);
    }

    public function test_resolveLayoutType_returns_HOTEL_on_WinkAuthException(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')
            ->andThrow(new \WinkAuthException('bad credentials'));

        Functions\when('set_transient')->justReturn(true);

        $result = $this->content->resolveLayoutTypePublic('any-id');
        $this->assertSame('HOTEL', $result);
    }

    public function test_resolveLayoutType_sets_auth_error_transient_on_WinkAuthException(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')
            ->andThrow(new \WinkAuthException('OAuth failed'));

        $transientKey   = null;
        $transientValue = null;
        Functions\when('set_transient')->alias(function($key, $value) use (&$transientKey, &$transientValue) {
            $transientKey   = $key;
            $transientValue = $value;
        });

        $this->content->resolveLayoutTypePublic('any-id');

        $this->assertSame('wink_auth_error', $transientKey);
        $this->assertSame('OAuth failed', $transientValue);
    }

    public function test_resolveLayoutType_returns_HOTEL_on_WinkDataException(): void
    {
        $this->mockApiClient->shouldReceive('getLayouts')
            ->andThrow(new \WinkDataException('API timeout'));

        $result = $this->content->resolveLayoutTypePublic('any-id');
        $this->assertSame('HOTEL', $result);
    }
}
