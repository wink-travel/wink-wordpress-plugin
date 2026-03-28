<?php
/**
 * Tests for the WinkException hierarchy.
 *
 * Verifies that the exception types are related correctly so that catch blocks
 * that catch the base class also catch the subtypes, and that each type carries
 * the correct message through to the catch site.
 */

declare(strict_types=1);

namespace Wink\Tests\Unit;

use PHPUnit\Framework\TestCase;

class WinkExceptionTest extends TestCase
{
    // ── Inheritance structure ─────────────────────────────────────────────────

    public function test_WinkAuthException_is_a_WinkApiException(): void
    {
        $e = new \WinkAuthException('bad credentials');
        $this->assertInstanceOf(\WinkApiException::class, $e);
    }

    public function test_WinkDataException_is_a_WinkApiException(): void
    {
        $e = new \WinkDataException('api error');
        $this->assertInstanceOf(\WinkApiException::class, $e);
    }

    public function test_WinkApiException_is_a_RuntimeException(): void
    {
        $e = new \WinkApiException('base error');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    // ── Catch-by-base-type works ──────────────────────────────────────────────

    public function test_catch_base_type_catches_WinkAuthException(): void
    {
        $caught = null;
        try {
            throw new \WinkAuthException('token rejected');
        } catch (\WinkApiException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught);
        $this->assertSame('token rejected', $caught->getMessage());
    }

    public function test_catch_base_type_catches_WinkDataException(): void
    {
        $caught = null;
        try {
            throw new \WinkDataException('layout fetch failed');
        } catch (\WinkApiException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught);
        $this->assertSame('layout fetch failed', $caught->getMessage());
    }

    // ── Selective catch works ─────────────────────────────────────────────────

    public function test_WinkDataException_is_not_caught_as_WinkAuthException(): void
    {
        $caughtAuth = false;
        $caughtData = false;
        try {
            throw new \WinkDataException('data error');
        } catch (\WinkAuthException $e) {
            $caughtAuth = true;
        } catch (\WinkDataException $e) {
            $caughtData = true;
        }
        $this->assertFalse($caughtAuth, 'WinkDataException must not be caught as WinkAuthException');
        $this->assertTrue($caughtData);
    }

    // ── Message is preserved ──────────────────────────────────────────────────

    public function test_exception_message_is_preserved(): void
    {
        $message = 'OAuth token request failed: Connection timed out';
        $e = new \WinkAuthException($message);
        $this->assertSame($message, $e->getMessage());
    }

    public function test_exception_code_is_preserved(): void
    {
        $e = new \WinkDataException('error', 503);
        $this->assertSame(503, $e->getCode());
    }

    public function test_previous_exception_is_preserved(): void
    {
        $original = new \RuntimeException('connection refused');
        $e = new \WinkAuthException('wrapped', 0, $original);
        $this->assertSame($original, $e->getPrevious());
    }
}
