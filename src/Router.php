<?php

declare(strict_types=1);

/**
 * シンプルなメソッド + パスベースの HTTP ルーター。
 *
 * 完全一致のパスとメソッドの組み合わせでハンドラを呼び出す。
 * パターンマッチングやパラメータキャプチャは行わない。
 */
class Router
{
    /** @var array<string, array<string, callable(): Response>> パス → メソッド → ハンドラ */
    private array $routes = [];

    /**
     * ルートを登録する。
     *
     * @param string             $method  HTTP メソッド（大文字・小文字は問わない）
     * @param string             $path    完全一致で照合するパス
     * @param callable(): Response $handler レスポンスを返すハンドラ関数
     *
     * @return void
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    /**
     * リクエストに一致するルートハンドラを実行して Response を返す。
     *
     * @param string $method HTTP メソッド（大文字・小文字は問わない）
     * @param string $path   リクエストパス
     *
     * @return Response|null 一致した場合はハンドラの戻り値。
     *                       パスが未登録なら null。
     *                       パスは存在するがメソッドが未登録なら 405 レスポンス。
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
