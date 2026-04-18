<?php

declare(strict_types=1);

// PHP エラーをブラウザに表示しない（情報漏洩防止）
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/src/AppConfig.php';
require_once __DIR__ . '/src/Request.php';
require_once __DIR__ . '/src/Response.php';
require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/SkywayAuth.php';
require_once __DIR__ . '/src/UrlUtils.php';

// ─── リクエスト解析 ──────────────────────────────────────────────────────────

$request = Request::fromGlobals();

// ─── Origin 検証不要なエンドポイント ────────────────────────────────────────

// GET / および GET /v1/status はブラウザから直接アクセスされる場合があるため、
// Origin ヘッダーなしでもレスポンスを返す。
$publicRouter = new Router();
$publicRouter->add('GET', '/', fn(): Response => Response::json(200, ['message' => 'Hello udonarium-backend!']));
$publicRouter->add('GET', '/v1/status', fn(): Response => Response::text(200, 'OK'));

$publicResponse = $publicRouter->dispatch($request->method, $request->path);
if ($publicResponse !== null) {
    $publicResponse->send();
}

// ─── 設定読み込み ────────────────────────────────────────────────────────────

// .env の探索順: 同一ディレクトリ（FTP 環境向け）→ 一つ上（SSH 環境向け）
// 環境変数 ENV_PATH を設定すると任意のパスを直接指定できる。
try {
    $config = AppConfig::load(
        __DIR__ . '/.env',
        dirname(__DIR__) . '/.env',
    );
} catch (\InvalidArgumentException) {
    Response::json(500, ['error' => 'Internal Server Error.'])->send();
}

// ─── CORS: Origin 検証 ───────────────────────────────────────────────────────

if ($request->origin === '') {
    Response::json(400, ['error' => 'Origin is required.'])->send();
}

// レスポンスには管理者が設定した文字列を使用（リクエストの生文字列は使わない）
$matchedOrigin = UrlUtils::findMatchedOrigin($request->origin, $config->allowedOrigins);

if ($matchedOrigin === null) {
    Response::json(403, ['error' => 'Forbidden.'])->send();
}

// ─── CORS レスポンスヘッダー ─────────────────────────────────────────────────

$corsHeaders = [
    'Access-Control-Allow-Origin'  => $matchedOrigin,
    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers' => 'Content-Type, Accept',
    'Access-Control-Max-Age'       => '86400',
    'Vary'                         => 'Origin',
];

// プリフライトリクエスト
if ($request->method === 'OPTIONS') {
    Response::noContent()->withHeaders($corsHeaders)->send();
}

// ─── ルーティング ────────────────────────────────────────────────────────────

$router = new Router();

$router->add('POST', '/v1/skyway2023/token', function () use ($request, $config): Response {
    // リクエストボディのサイズを 64KB に制限（DoS 対策）
    $rawBody = $request->body(65536);
    if ($rawBody === false) {
        return Response::json(413, ['error' => 'Request Entity Too Large.']);
    }

    $body = json_decode($rawBody, true);

    if (
        !is_array($body)
        || !isset($body['formatVersion']) || $body['formatVersion'] !== 1
        || !isset($body['channelName'])   || !is_string($body['channelName']) || strlen($body['channelName']) > 200
        || !isset($body['peerId'])        || !is_string($body['peerId'])       || strlen($body['peerId']) > 200
    ) {
        return Response::json(400, ['error' => 'Bad Request.']);
    }

    $token = SkywayAuth::generate(
        appId: $config->appId,
        secret: $config->secret,
        lobbySize: $config->lobbySize,
        channelName: $body['channelName'],
        peerId: $body['peerId'],
    );

    return Response::json(200, ['token' => $token]);
});

$response = $router->dispatch($request->method, $request->path)
    ?? Response::json(404, ['error' => 'Not Found.']);

$response->withHeaders($corsHeaders)->send();
