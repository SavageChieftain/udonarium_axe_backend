<?php

declare(strict_types=1);

/**
 * HTTP リクエストを表すイミュータブルな値オブジェクト。
 *
 * グローバル変数（$_SERVER, php://input）から生成するか、
 * テスト用に任意の値で生成できる。
 */
class Request
{
    /**
     * php://input の読み取り上限 (64KB + 1 byte)。
     *
     * 1 byte 多く読むことで上限超過を検出できる。
     */
    private const MAX_BODY_READ = 65537;

    /**
     * @param string $method  HTTP メソッド（大文字正規化済み）
     * @param string $path    リクエストパス（スクリプトディレクトリ除去済み）
     * @param string $origin  Origin ヘッダーの値（空文字列 = 未送信）
     * @param string $rawBody リクエストボディの生データ
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $origin,
        private readonly string $rawBody,
    ) {}

    /**
     * 実行環境のグローバル変数からリクエストを生成する。
     *
     * サブディレクトリ運用にも対応し、SCRIPT_NAME のディレクトリ部分を
     * REQUEST_URI から除去したパスを生成する。
     *
     * @return self 現在の HTTP リクエストを表すインスタンス
     */
    public static function fromGlobals(): self
    {
        // サブディレクトリ運用に対応。
        // SCRIPT_NAME のディレクトリ部（例: "/backend"）を REQUEST_URI から取り除くことで、
        // ドキュメントルート直下でも /backend/ 配下でも同じパス比較が使える。
        $scriptName = is_string($_SERVER['SCRIPT_NAME'] ?? null) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        $requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $method     = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $origin     = is_string($_SERVER['HTTP_ORIGIN'] ?? null) ? $_SERVER['HTTP_ORIGIN'] : '';

        $scriptDir = rtrim(dirname($scriptName), '/');
        $parsedPath = parse_url($requestUri, PHP_URL_PATH);
        $fullPath  = is_string($parsedPath) ? $parsedPath : '/';
        $path      = ($scriptDir !== '' && str_starts_with($fullPath, $scriptDir))
            ? substr($fullPath, strlen($scriptDir))
            : $fullPath;
        $path    = ($path === '') ? '/' : $path;
        $rawBody = (string) (file_get_contents('php://input', false, null, 0, self::MAX_BODY_READ) ?: '');

        return new self(
            method: strtoupper($method),
            path: $path,
            origin: $origin,
            rawBody: $rawBody,
        );
    }

    /**
     * 任意の値からリクエストを生成する。
     *
     * 主にテストで使用する。
     *
     * @param string $method HTTP メソッド（自動的に大文字に正規化される）
     * @param string $path   リクエストパス
     * @param string $origin Origin ヘッダーの値
     * @param string $body   リクエストボディ
     *
     * @return self 指定した値を持つインスタンス
     */
    public static function create(
        string $method,
        string $path,
        string $origin = '',
        string $body = '',
    ): self {
        return new self(strtoupper($method), $path, $origin, $body);
    }

    /**
     * リクエストボディを返す。
     *
     * @param int $maxBytes ボディの最大許容バイト数
     *
     * @return string|false ボディ文字列。$maxBytes を超える場合は false
     */
    public function body(int $maxBytes): string|false
    {
        if (strlen($this->rawBody) > $maxBytes) {
            return false;
        }

        return $this->rawBody;
    }
}
