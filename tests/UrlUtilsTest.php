<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class UrlUtilsTest extends TestCase
{
    // ─── parseAllowedOrigins ─────────────────────────────────────────────────

    public function testParseAllowedOriginsReturnsSingleOrigin(): void
    {
        $result = UrlUtils::parseAllowedOrigins('https://example.com');
        $this->assertSame(['https://example.com'], $result);
    }

    public function testParseAllowedOriginsReturnsMultipleOrigins(): void
    {
        $result = UrlUtils::parseAllowedOrigins('https://example.com,https://www.example.com');
        $this->assertSame(['https://example.com', 'https://www.example.com'], $result);
    }

    public function testParseAllowedOriginsTrimsWhitespace(): void
    {
        $result = UrlUtils::parseAllowedOrigins('https://example.com , https://www.example.com');
        $this->assertSame(['https://example.com', 'https://www.example.com'], $result);
    }

    // ─── isAllowedOrigin ─────────────────────────────────────────────────────

    public function testIsAllowedOriginReturnsTrueForMatchingOrigin(): void
    {
        $this->assertTrue(
            UrlUtils::isAllowedOrigin('https://example.com', ['https://example.com']),
        );
    }

    public function testIsAllowedOriginReturnsFalseForNonMatchingOrigin(): void
    {
        $this->assertFalse(
            UrlUtils::isAllowedOrigin('https://evil.com', ['https://example.com']),
        );
    }

    public function testIsAllowedOriginReturnsTrueForWildcard(): void
    {
        $this->assertTrue(
            UrlUtils::isAllowedOrigin('https://any.thing', ['*']),
        );
    }

    // ─── findMatchedOrigin ───────────────────────────────────────────────────

    public function testFindMatchedOriginReturnsAllowedString(): void
    {
        // レスポンスには管理者が設定した文字列が返ること
        $result = UrlUtils::findMatchedOrigin(
            'https://example.com',
            ['https://example.com'],
        );
        $this->assertSame('https://example.com', $result);
    }

    public function testFindMatchedOriginReturnsNullForNoMatch(): void
    {
        $result = UrlUtils::findMatchedOrigin(
            'https://evil.com',
            ['https://example.com'],
        );
        $this->assertNull($result);
    }

    public function testFindMatchedOriginReturnsWildcardLiteralString(): void
    {
        $result = UrlUtils::findMatchedOrigin('https://any.com', ['*']);
        $this->assertSame('*', $result);
    }

    public function testFindMatchedOriginRejectsInvalidUrl(): void
    {
        $result = UrlUtils::findMatchedOrigin('not-a-url', ['https://example.com']);
        $this->assertNull($result);
    }

    public function testFindMatchedOriginDistinguishesByScheme(): void
    {
        $result = UrlUtils::findMatchedOrigin(
            'http://example.com',
            ['https://example.com'],
        );
        $this->assertNull($result);
    }

    public function testFindMatchedOriginDistinguishesByPort(): void
    {
        $result = UrlUtils::findMatchedOrigin(
            'https://example.com:8080',
            ['https://example.com'],
        );
        $this->assertNull($result);
    }

    public function testFindMatchedOriginMatchesWithExplicitPort(): void
    {
        $result = UrlUtils::findMatchedOrigin(
            'https://example.com:8080',
            ['https://example.com:8080'],
        );
        $this->assertSame('https://example.com:8080', $result);
    }

    public function testParseAllowedOriginsReturnsEmptyArrayForEmptyString(): void
    {
        $result = UrlUtils::parseAllowedOrigins('');
        $this->assertSame([], $result);
    }

    public function testParseAllowedOriginsReturnsEmptyArrayForWhitespaceOnly(): void
    {
        $result = UrlUtils::parseAllowedOrigins('   ');
        $this->assertSame([], $result);
    }

    public function testParseAllowedOriginsFiltersEmptyEntries(): void
    {
        $result = UrlUtils::parseAllowedOrigins('https://a.com,,https://b.com');
        $this->assertSame(['https://a.com', 'https://b.com'], $result);
    }

    // ─── findMatchedOrigin: malformed allowed origin ─────────────────────────

    public function testFindMatchedOriginSkipsMalformedAllowedOrigin(): void
    {
        // 許可リスト側のオリジンが不正な URL でも他のエントリが評価される
        $result = UrlUtils::findMatchedOrigin(
            'https://example.com',
            ['not-valid', 'https://example.com'],
        );
        $this->assertSame('https://example.com', $result);
    }
}
