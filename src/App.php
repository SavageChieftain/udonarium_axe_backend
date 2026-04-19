<?php

declare(strict_types=1);

require_once __DIR__ . '/Config/AppConfig.php';
require_once __DIR__ . '/Http/Request.php';
require_once __DIR__ . '/Http/Response.php';
require_once __DIR__ . '/Http/Router.php';
require_once __DIR__ . '/Auth/SkywayAuth.php';
require_once __DIR__ . '/Util/UrlUtils.php';
require_once __DIR__ . '/Middleware/CorsMiddleware.php';

/**
 * アプリケーションのリクエスト処理パイプライン。
 *
 * 全ルートを単一のルーターに登録し、CORS はミドルウェアとして適用する。
 * exit を呼ばないため、パイプライン全体をテスト可能にする。
 */
final class App
{
    private Router $router;

    private AppConfig $config;

    private Request $request;

    public function __construct(string $baseDir)
    {
        $this->config = AppConfig::load(
            $baseDir . '/.env',
            dirname($baseDir) . '/.env',
        );
        $this->router = new Router();
    }

    /**
     * リクエストを処理してレスポンスを返す。
     *
     * @param Request $request リクエスト
     *
     * @return Response 処理結果のレスポンス
     */
    public function handle(Request $request): Response
    {
        $this->request = $request;

        return $this->router
            ->get('/', fn(): Response => Response::json(200, ['message' => 'Ready to serve your realm.']))
            ->get('/v1/status', fn(): Response => Response::text(200, 'OK'))
            ->use('/v1/skyway2023/*', $this->applyCors(...))
            ->post('/v1/skyway2023/token', $this->handleTokenRequest(...))
            ->notFound(fn(): Response => $this->applyCors(fn(): Response => Response::json(404, ['error' => 'Not Found.'])))
            ->dispatch($request->method, $request->path);
    }

    /**
     * CORS ミドルウェアを適用する。
     *
     * @param \Closure(): Response $next 次のハンドラ
     */
    private function applyCors(\Closure $next): Response
    {
        return (new CorsMiddleware($this->request, $this->config))($next);
    }

    /**
     * SkyWay トークン発行リクエストを処理する。
     */
    private function handleTokenRequest(): Response
    {
        if ($this->request->contentType !== 'application/json') {
            return Response::json(415, ['error' => 'Unsupported Media Type.']);
        }

        $rawBody = $this->request->body(65536);
        if ($rawBody === false) {
            return Response::json(413, ['error' => 'Request Entity Too Large.']);
        }

        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return Response::json(400, ['error' => 'Bad Request.']);
        }

        $formatVersion = $body['formatVersion'] ?? null;
        $channelName   = $body['channelName'] ?? null;
        $peerId        = $body['peerId'] ?? null;

        if (
            $formatVersion !== 1
            || !is_string($channelName) || strlen($channelName) > 200
            || !is_string($peerId)      || strlen($peerId) > 200
        ) {
            return Response::json(400, ['error' => 'Bad Request.']);
        }

        return Response::json(200, [
            'token' => SkywayAuth::generate(
                appId: $this->config->appId,
                secret: $this->config->secret,
                lobbySize: $this->config->lobbySize,
                channelName: $channelName,
                peerId: $peerId,
            ),
        ]);
    }
}
