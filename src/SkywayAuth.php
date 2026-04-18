<?php

declare(strict_types=1);

/**
 * SkyWay 2023 用の認証トークン（JWT）を生成するユーティリティクラス。
 *
 * TypeScript 実装（udonarium-backend-vercel）と同一の構造・署名方式で
 * HS256 署名付き JWT を生成する。
 */
class SkywayAuth
{
    /**
     * SkyWay 2023 用の JWT トークンを生成する。
     *
     * ロビーチャンネルとルームチャンネルの両方に対するスコープを含む
     * トークンを返す。有効期限は発行時刻から 24 時間。
     *
     * @param string $appId       SkyWay アプリケーション ID
     * @param string $secret      HMAC-SHA256 署名に使用するシークレットキー
     * @param int    $lobbySize   ロビーチャンネルの最大数
     * @param string $channelName ルームチャンネル名
     * @param string $peerId      ピア ID
     * @param string $jti         JWT ID（空文字列の場合は UUID v4 を自動生成）
     * @param int    $iat         発行時刻 Unix タイムスタンプ（0 の場合は現在時刻）
     *
     * @return string Base64URL エンコードされた JWT 文字列
     *
     * @throws \JsonException JSON エンコードに失敗した場合
     */
    public static function generate(
        string $appId,
        string $secret,
        int    $lobbySize,
        string $channelName,
        string $peerId,
        string $jti = '',
        int    $iat = 0,
    ): string {
        $jti = $jti !== '' ? $jti : self::generateUuid();
        $iat = $iat !== 0 ? $iat : time();

        $lobbyChannels = [
            [
                'name'    => "udonarium-lobby-*-of-{$lobbySize}",
                'actions' => ['read', 'create'],
                'members' => [
                    [
                        'name'         => $peerId,
                        'actions'      => ['write'],
                        'publication'  => ['actions' => []],
                        'subscription' => ['actions' => []],
                    ],
                ],
            ],
        ];

        $roomChannels = [
            [
                'name'    => $channelName,
                'actions' => ['read', 'create'],
                'members' => [
                    [
                        'name'         => $peerId,
                        'actions'      => ['write'],
                        'publication'  => ['actions' => ['write']],
                        'subscription' => ['actions' => ['write']],
                    ],
                    [
                        'name'         => '*',
                        'actions'      => ['signal'],
                        'publication'  => ['actions' => []],
                        'subscription' => ['actions' => []],
                    ],
                ],
            ],
        ];

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $scope = [
            'app' => [
                'id'       => $appId,
                'turn'     => true,
                'actions'  => ['read'],
                'channels' => array_merge($lobbyChannels, $roomChannels),
            ],
        ];

        $payload = [
            'jti'     => $jti,
            'iat'     => $iat,
            'exp'     => $iat + 60 * 60 * 24,
            'version' => 2,
            'scope'   => $scope,
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        $jwtHeader    = self::base64UrlEncode((string) json_encode($header, $flags));
        $jwtPayload   = self::base64UrlEncode((string) json_encode($payload, $flags));
        $jwtSignature = self::base64UrlEncode(
            (string) hash_hmac('sha256', $jwtHeader . '.' . $jwtPayload, $secret, true),
        );

        return $jwtHeader . '.' . $jwtPayload . '.' . $jwtSignature;
    }

    /**
     * Base64URL エンコードする。
     *
     * RFC 4648 §5 に従い、パディングを除去し "+" → "-"、"/" → "_" に置換する。
     *
     * @param string $data エンコード対象のバイナリデータ
     *
     * @return string Base64URL エンコードされた文字列
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * RFC 4122 v4 準拠の UUID を生成する。
     *
     * @return string ハイフン区切りの 36 文字 UUID 文字列
     */
    private static function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
