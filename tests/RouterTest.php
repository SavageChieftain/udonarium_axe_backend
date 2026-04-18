<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * {@see Router} のテストケース。
 *
 * ルーティングの正常ディスパッチ、大文字小文字の無視、
 * 未登録パスの null 応答、メソッド不一致時の 405 応答を検証する。
 */
class RouterTest extends TestCase
{
    /**
     * 登録済みハンドラが正しくディスパッチされ、レスポンスを返すことを確認する。
     */
    public function testDispatchCallsRegisteredHandler(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, ['ok' => true]));

        $response = $router->dispatch('GET', '/foo');
        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * dispatch() のメソッド比較が大文字小文字を区別しないことを確認する。
     */
    public function testDispatchIsMethodCaseInsensitive(): void
    {
        $router = new Router();
        $router->add('POST', '/bar', fn(): Response => Response::json(201, []));

        $this->assertNotNull($router->dispatch('post', '/bar'));
        $this->assertNotNull($router->dispatch('POST', '/bar'));
    }

    /**
     * 未登録のパスに対して null を返すことを確認する。
     */
    public function testDispatchReturnsNullForUnknownPath(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, []));

        $this->assertNull($router->dispatch('GET', '/unknown'));
    }

    /**
     * パスは登録済みだがメソッドが異なる場合に 405 を返すことを確認する。
     */
    public function testDispatchReturns405ForWrongMethod(): void
    {
        $router = new Router();
        $router->add('GET', '/foo', fn(): Response => Response::json(200, []));

        $response = $router->dispatch('POST', '/foo');
        $this->assertNotNull($response);
        $this->assertSame(405, $response->getStatusCode());
    }

    /**
     * 405 レスポンスに Allow ヘッダーが含まれ、許可メソッドが列挙されることを確認する。
     */
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

    /**
     * dispatch() が null を返す場合に null 合体演算子でフォールバックできることを確認する。
     */
    public function testDispatchReturnsFallback(): void
    {
        $router = new Router();

        $response = $router->dispatch('GET', '/missing')
            ?? Response::json(404, ['error' => 'Not Found.']);

        $this->assertSame(404, $response->getStatusCode());
    }
}
