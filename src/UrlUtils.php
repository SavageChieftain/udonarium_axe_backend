<?php

declare(strict_types=1);

class UrlUtils
{
    /**
     * カンマ区切りのオリジン文字列を配列に変換する。
     * 空文字列・空白のみの場合は空配列を返す。
     *
     * @return string[]
     */
    public static function parseAllowedOrigins(string $origins): array
    {
        if (trim($origins) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $origins))));
    }

    /**
     * リクエストオリジンが許可リストに含まれるか検証する。
     * ホスト・スキーム・ポートがすべて一致したときのみ許可する。
     * ワイルドカード "*" が含まれる場合はすべて許可する。
     *
     * @param string[] $allowedOrigins
     */
    public static function isAllowedOrigin(string $requestOrigin, array $allowedOrigins): bool
    {
        return self::findMatchedOrigin($requestOrigin, $allowedOrigins) !== null;
    }

    /**
     * リクエストオリジンが許可リストに含まれる場合、許可リスト側のオリジン文字列を返す。
     * 一致しない場合は null を返す。
     *
     * レスポンスヘッダーには必ずこの戻り値（管理者が設定した文字列）を使用すること。
     * リクエストの生文字列をそのままヘッダーに返すと HTTP ヘッダーインジェクションのリスクがある。
     *
     * @param string[] $allowedOrigins
     */
    public static function findMatchedOrigin(string $requestOrigin, array $allowedOrigins): ?string
    {
        if (in_array('*', $allowedOrigins, true)) {
            return '*';
        }

        $requestParsed = parse_url($requestOrigin);
        if (!is_array($requestParsed) || !isset($requestParsed['host'])) {
            return null;
        }

        foreach ($allowedOrigins as $allowed) {
            $allowedParsed = parse_url($allowed);
            if (!is_array($allowedParsed) || !isset($allowedParsed['host'])) {
                continue;
            }

            $hostMatch   = $requestParsed['host']   === $allowedParsed['host'];
            $schemeMatch = ($requestParsed['scheme'] ?? '') === ($allowedParsed['scheme'] ?? '');
            $portMatch   = ($requestParsed['port']   ?? '') === ($allowedParsed['port']   ?? '');

            if ($hostMatch && $schemeMatch && $portMatch) {
                // リクエストの生文字列ではなく、管理者が設定した文字列を返す
                return $allowed;
            }
        }

        return null;
    }
}
