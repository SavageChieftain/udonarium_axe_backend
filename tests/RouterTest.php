<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    public function testDispatchCallsRegisteredHandler(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, ['ok' => true]));

        $response = $router->dispatch('GET', '/foo');
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDispatchIsMethodCaseInsensitive(): void
    {
        $router = new Router();
        $router->add('POST', '/bar', fn(): Response => Response::json(201, []));

        $this->assertNotNull($router->dispatch('post', '/bar'));
        $this->assertNotNull($router->dispatch('POST', '/bar'));
    }

    public function testDispatchReturnsNullForUnknownPath(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, []));

        $this->assertNull($router->dispatch('GET', '/unknown'));
    }

    public function testDispatchReturns405ForWrongMethod(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, []));

        $response = $router->dispatch('POST', '/foo');
        $this->assertNotNull($response);
        $this->assertSame(405, $response->getStatusCode());
    }

    public function testDispatchReturnsAllowHeaderIn405(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, []));
        $router->add('POST', '/foo', fn(): Response => Response::json(201, []));

        $response = $router->dispatch('DELETE', '/foo');
        $this->assertNotNull($response);
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Allow', $headers);
        $this->assertStringContainsString('GET', $headers['Allow']);
        $this->assertStringContainsString('POST', $headers['Allow']);
    }

    public function testDispatchReturnsFallback(): void
    {
        $router = new Router();

        $response = $router->dispatch('GET', '/missing')
            ?? Response::json(404, ['error' => 'Not Found.']);

        $this->assertSame(404, $response->getStatusCode());
    }
}
