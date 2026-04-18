<?php

declare(strict_types=1);

class AppConfig
{
    /** @param string[] $allowedOrigins */
    public function __construct(
        public readonly string $appId,
        public readonly string $secret,
        public readonly int    $lobbySize,
        public readonly array  $allowedOrigins,
    ) {}

    /**
     * .env ファイルを読み込んでインスタンスを生成する。
     * 候補パスを先頭から順に探し、最初に見つかった .env を使用する。
     * 環境変数 ENV_PATH を設定すると任意のパスを最優先で使用できる。
     *
     * @throws \InvalidArgumentException
     */
    public static function load(string ...$candidates): self
    {
        $envPath = getenv('ENV_PATH');
        if ($envPath !== false && $envPath !== '') {
            array_unshift($candidates, $envPath);
        }

        $env = [];
        foreach ($candidates as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $result = parse_ini_file($path, false, INI_SCANNER_RAW);
            $env    = ($result !== false) ? $result : [];
            break;
        }

        return self::fromEnv($env);
    }

    /**
     * 環境変数配列からインスタンスを生成する。
     * 必須キーが欠けている場合は InvalidArgumentException をスローする。
     *
     * @param array<string, string> $env
     * @throws \InvalidArgumentException
     */
    public static function fromEnv(array $env): self
    {
        if (empty($env['SKYWAY_APP_ID'])) {
            throw new \InvalidArgumentException('SKYWAY_APP_ID is required in .env');
        }

        if (empty($env['SKYWAY_SECRET'])) {
            throw new \InvalidArgumentException('SKYWAY_SECRET is required in .env');
        }

        return new self(
            appId: $env['SKYWAY_APP_ID'],
            secret: $env['SKYWAY_SECRET'],
            lobbySize: self::parseLobbySize($env['SKYWAY_UDONARIUM_LOBBY_SIZE'] ?? '3'),
            allowedOrigins: UrlUtils::parseAllowedOrigins($env['ACCESS_CONTROL_ALLOW_ORIGIN'] ?? ''),
        );
    }

    /** @throws \InvalidArgumentException */
    private static function parseLobbySize(string $value): int
    {
        $size = (int) $value;
        if ($size < 1) {
            throw new \InvalidArgumentException('SKYWAY_UDONARIUM_LOBBY_SIZE must be a positive integer');
        }

        return $size;
    }
}
