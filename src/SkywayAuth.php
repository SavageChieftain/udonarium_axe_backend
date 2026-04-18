<?php

declare(strict_types=1);

class SkywayAuth
{
    /**
     * SkyWay 2023 用の JWT トークンを生成する。
     * TypeScript 実装（udonarium-backend-vercel）と同じ構造・署名方式。
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
     * Base64URL エンコード（パディングなし、"+" → "-"、"/" → "_"）。
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * RFC 4122 v4 準拠の UUID を生成する。
     */
    private static function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
