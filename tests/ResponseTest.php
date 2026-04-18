<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonSetsStatusCodeAndBody(): void
    {
        $response = Response::json(200, ['key' => 'value']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key":"value"}', $response->getBody());
    }

    public function testJsonEncodesUnicodeWithoutEscape(): void
    {
        $response = Response::json(200, ['msg' => 'こんにちは']);
        $this->assertStringContainsString('こんにちは', $response->getBody());
    }

    public function testTextSetsStatusCodeAndBody(): void
    {
        $response = Response::text(200, 'OK');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getBody());
    }

    public function testNoContentReturns204WithEmptyBody(): void
    {
        $response = Response::noContent();
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getBody());
    }

    public function testWithHeadersMergesHeaders(): void
    {
        $response = Response::json(200, [])
            ->withHeaders(['X-Foo' => 'bar', 'X-Baz' => 'qux']);

        $headers = $response->getHeaders();
        $this->assertSame('bar', $headers['X-Foo']);
        $this->assertSame('qux', $headers['X-Baz']);
    }

    public function testWithHeadersIsImmutable(): void
    {
        $original = Response::json(200, []);
        $updated  = $original->withHeaders(['X-New' => 'value']);

        $this->assertEmpty($original->getHeaders());
        $this->assertArrayHasKey('X-New', $updated->getHeaders());
    }

    public function testWithHeadersOverridesExistingKey(): void
    {
        $response = Response::json(200, [])
            ->withHeaders(['X-Foo' => 'first'])
            ->withHeaders(['X-Foo' => 'second']);

        $this->assertSame('second', $response->getHeaders()['X-Foo']);
    }

    public function testSendMethodIsNeverReturnType(): void
    {
        $method     = new \ReflectionMethod(Response::class, 'send');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('never', (string) $returnType);
    }

    public function testJsonThrowsOnUnencodableValue(): void
    {
        $this->expectException(\JsonException::class);
        Response::json(200, NAN);
    }
}
