<?php

declare(strict_types=1);

class Router
{
    /** @var array<string, array<string, callable(): Response>> */
    private array $routes = [];

    /**
     * ルートを登録する。
     *
     * @param callable(): Response $handler
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    /**
     * リクエストに一致するルートハンドラを実行して Response を返す。
     * パスが存在しない場合は null を返す。
     * パスは存在するがメソッドが許可されていない場合は 405 を返す。
     */
    public function dispatch(string $method, string $path): ?Response
    {
        $method = strtoupper($method);

        if (!isset($this->routes[$path])) {
            return null;
        }

        if (!isset($this->routes[$path][$method])) {
            $allowed = implode(', ', array_keys($this->routes[$path]));
            return Response::text(405, 'Method Not Allowed')
                ->withHeaders(['Allow' => $allowed]);
        }

        return ($this->routes[$path][$method])();
    }
}
