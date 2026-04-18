<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    // ─── create() ────────────────────────────────────────────────────────────

    public function testCreateSetsMethodUppercased(): void
    {
        $req = Request::create('get', '/foo');
        $this->assertSame('GET', $req->method);
    }

    public function testCreateSetsPath(): void
    {
        $req = Request::create('POST', '/v1/test');
        $this->assertSame('/v1/test', $req->path);
    }

    public function testCreateSetsOrigin(): void
    {
        $req = Request::create('GET', '/', 'https://example.com');
        $this->assertSame('https://example.com', $req->origin);
    }

    public function testCreateDefaultsOriginToEmptyString(): void
    {
        $req = Request::create('GET', '/');
        $this->assertSame('', $req->origin);
    }

    public function testBodyReturnsBodyWhenUnderLimit(): void
    {
        $body = '{"foo":"bar"}';
        $req  = Request::create('POST', '/', '', $body);
        $this->assertSame($body, $req->body(65536));
    }

    public function testBodyReturnsFalseWhenOverLimit(): void
    {
        $body = str_repeat('x', 65537);
        $req  = Request::create('POST', '/', '', $body);
        $this->assertFalse($req->body(65536));
    }

    public function testBodyReturnsBodyAtExactLimit(): void
    {
        $body = str_repeat('x', 65536);
        $req  = Request::create('POST', '/', '', $body);
        $this->assertSame($body, $req->body(65536));
    }

    // ─── fromGlobals() ───────────────────────────────────────────────────────

    public function testFromGlobalsReadsMethodPathAndOrigin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/v1/test';
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        $_SERVER['HTTP_ORIGIN']    = 'https://example.com';

        $req = Request::fromGlobals();
        $this->assertSame('POST', $req->method);
        $this->assertSame('/v1/test', $req->path);
        $this->assertSame('https://example.com', $req->origin);
    }

    public function testFromGlobalsStripsSubdirectoryPrefix(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/backend/v1/status';
        $_SERVER['SCRIPT_NAME']    = '/backend/index.php';

        $req = Request::fromGlobals();
        $this->assertSame('/v1/status', $req->path);
    }

    public function testFromGlobalsEmptyPathAfterStripBecomesSlash(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/backend';
        $_SERVER['SCRIPT_NAME']    = '/backend/index.php';

        $req = Request::fromGlobals();
        $this->assertSame('/', $req->path);
    }

    public function testFromGlobalsDoesNotStripWhenPathDoesNotMatchScriptDir(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/other/path';
        $_SERVER['SCRIPT_NAME']    = '/backend/index.php';

        $req = Request::fromGlobals();
        $this->assertSame('/other/path', $req->path);
    }

    public function testFromGlobalsDefaultsMethodToGetWhenMissing(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['SCRIPT_NAME'] = '/index.php';

        $req = Request::fromGlobals();
        $this->assertSame('GET', $req->method);
    }

    public function testFromGlobalsDefaultsOriginToEmptyWhenMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        unset($_SERVER['HTTP_ORIGIN']);

        $req = Request::fromGlobals();
        $this->assertSame('', $req->origin);
    }

    public function testFromGlobalsUppercasesMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'options';
        $_SERVER['REQUEST_URI']    = '/';
        $_SERVER['SCRIPT_NAME']    = '/index.php';

        $req = Request::fromGlobals();
        $this->assertSame('OPTIONS', $req->method);
    }
}
