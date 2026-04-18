# udonarium-axe-backend

[ユドナリウム](https://github.com/TK11235/udonarium) 向け SkyWay 2023 JWT 認証バックエンドです。
レンタルサーバー（Apache + PHP 8.3）で動作する軽量な PHP 実装です。

## 機能

- **SkyWay 2023 JWT トークン発行** (`POST /v1/skyway2023/token`)
- **CORS 検証**（許可オリジンのみ受け付け）
- **Apache `.htaccess`** による機密ファイルの遮断
- サブディレクトリ運用対応

## 必要環境

| 環境   | バージョン       |
| ------ | ---------------- |
| PHP    | 8.3 以上         |
| Apache | mod_rewrite 有効 |

開発環境は Docker（`php:8.3-apache`）で再現できます。

## セットアップ

### 1. ファイルの配置

リポジトリをドキュメントルートに配置します。

```
/home/user/www/backend/   ← ドキュメントルート（index.php がここ）
/home/user/.env           ← .env はドキュメントルートの一つ上を推奨
```

### 2. `.env` の作成

```sh
cp .env.example .env
```

`.env.example` を参考に値を設定してください。

```dotenv
SKYWAY_APP_ID=your_skyway_app_id_here
SKYWAY_SECRET=your_skyway_secret_here
SKYWAY_UDONARIUM_LOBBY_SIZE=3
ACCESS_CONTROL_ALLOW_ORIGIN=https://your-udonarium-front.example.com
```

> **注意:** `.env` はドキュメントルートの**一つ上**に置くことを推奨します。
> ドキュメントルート直下に置く場合は `.htaccess` による保護が有効であることを確認してください。

### 3. パーミッションの設定

FTP クライアントでファイルを右クリックし、「パーミッション」や「属性変更」からパーミッションを設定してください。

```
/home/user/
├── .env                          600  ← 機密情報（推奨配置場所）
└── www/backend/                  755
    ├── .htaccess                  644
    ├── index.php                  644
    └── src/                       755
        ├── .htaccess              644
        └── *.php                  644
```

| 対象                                  | パーミッション | FTP クライアントでの設定手順                           |
| ------------------------------------- | -------------- | ------------------------------------------------------ |
| ディレクトリ全般                      | `755`          | フォルダを右クリック →「パーミッション」→ `755` を入力 |
| `.php` / `.htaccess` 等の一般ファイル | `644`          | ファイルを右クリック →「パーミッション」→ `644` を入力 |
| `.env`                                | `600`          | `.env` を右クリック →「パーミッション」→ `600` を入力  |

> **重要:** `.env` は必ず `600` に設定してください。`644` のままだとサーバー上の他のユーザーから読み取られるリスクがあります。

<details>
<summary>SSH が使える場合</summary>

付属のスクリプトで一括設定できます。

```sh
bash bin/set-permissions.sh
```

または手動で実行：

```sh
find . -type d -exec chmod 755 {} +
find . -type f -exec chmod 644 {} +
chmod 600 ../.env
```

</details>

### 4. Apache の設定

さくらのレンタルサーバーでは `.htaccess` が標準で有効なため、追加設定は不要です。

### 5. 動作確認

```sh
curl https://your-backend.example.com/v1/status
# => OK
```

## API

### `GET /v1/status`

ヘルスチェック用エンドポイント。

**レスポンス:**

```
200 OK
```

### `POST /v1/skyway2023/token`

SkyWay 2023 の JWT トークンを発行します。

**リクエスト:**

```json
{
  "formatVersion": 1,
  "channelName": "room-name",
  "peerId": "peer-id"
}
```

**レスポンス:**

```json
{
  "token": "eyJ..."
}
```

## 開発

### Docker で起動

```sh
docker compose up
```

`http://localhost:3000` でアクセスできます。

### テスト

```sh
bin/phpunit
```

### 静的解析

```sh
bin/phpstan analyse
```

### コードフォーマット

```sh
bin/php-cs-fixer fix
```

## セキュリティ

脆弱性を発見された場合は [SECURITY.md](SECURITY.md) をご確認ください。

## ライセンス

[MIT](LICENSE)
