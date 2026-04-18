<?php

declare(strict_types=1);

/**
 * URL・オリジン関連のユーティリティメソッドを提供するクラス。
 *
 * CORS オリジン検証やオリジン文字列のパースを担当する。
 */
class UrlUtils
{
    /**
     * カンマ区切りのオリジン文字列を配列に変換する。
     *
     * 空文字列・空白のみの場合は空配列を返す。
     * 各要素は前後の空白がトリミングされ、空要素は除外される。
     *
     * @param string $origins カンマ区切りのオリジン文字列
     *
     * @return string[] トリミング済みオリジンの配列
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
     *
     * ホスト・スキーム・ポートがすべて一致したときのみ許可する。
     * ワイルドカード "*" が含まれる場合はすべてのオリジンを許可する。
     *
     * @param string   $requestOrigin  検証対象のリクエスト Origin ヘッダー値
     * @param string[] $allowedOrigins 許可オリジンのリスト
     *
     * @return bool 許可される場合は true
     */
    public static function isAllowedOrigin(string $requestOrigin, array $allowedOrigins): bool
    {
        return self::findMatchedOrigin($requestOrigin, $allowedOrigins) !== null;
    }

    /**
     * リクエストオリジンが許可リストに含まれる場合、許可リスト側のオリジン文字列を返す。
     *
     * 一致しない場合は null を返す。
     * レスポンスヘッダーには必ずこの戻り値（管理者が設定した文字列）を使用すること。
     * リクエストの生文字列をそのままヘッダーに返すと HTTP ヘッダーインジェクションのリスクがある。
     *
     * @param string   $requestOrigin  検証対象のリクエスト Origin ヘッダー値
     * @param string[] $allowedOrigins 許可オリジンのリスト
     *
     * @return string|null 一致した許可オリジン文字列、または null
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
