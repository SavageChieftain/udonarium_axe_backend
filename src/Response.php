<?php

declare(strict_types=1);

class Response
{
    /** @param array<string, string> $headers */
    private function __construct(
        private readonly int    $statusCode,
        private readonly string $contentType,
        private readonly string $body,
        private readonly array  $headers = [],
    ) {}

    /**
     * JSON レスポンスを生成する。
     *
     * @param mixed $data
     */
    public static function json(int $code, mixed $data): self
    {
        return new self(
            statusCode: $code,
            contentType: 'application/json',
            body: (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * プレーンテキストレスポンスを生成する。
     */
    public static function text(int $code, string $body): self
    {
        return new self(
            statusCode: $code,
            contentType: 'text/plain',
            body: $body,
        );
    }

    /**
     * 204 No Content レスポンスを生成する。
     */
    public static function noContent(): self
    {
        return new self(statusCode: 204, contentType: '', body: '');
    }

    /**
     * 追加ヘッダーをマージした新しいインスタンスを返す。
     *
     * @param array<string, string> $extra
     */
    public function withHeaders(array $extra): self
    {
        return new self($this->statusCode, $this->contentType, $this->body, array_merge($this->headers, $extra));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * レスポンスを送出して終了する。
     *
     * @return never
     * @codeCoverageIgnore
     */
    public function send(): never
    {
        http_response_code($this->statusCode);
        if ($this->contentType !== '') {
            header('Content-Type: ' . $this->contentType);
        }

        header('X-Content-Type-Options: nosniff');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
        exit;
    }
}
