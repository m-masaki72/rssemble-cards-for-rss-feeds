# RSS Display

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグイン。
外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

## 特徴

- 複数フィードを統合表示（記事URLで重複排除、新しい日付を優先）
- OGP画像の自動取得（`media:content` → `media:thumbnail` → `enclosure` → `og:image` → デフォルト画像）
- OGP取得は `curl_multi` による並列リクエスト（初回表示を高速化）
- グリッド表示／リスト表示を切り替え可能
- 説明文・サイト名・日付の表示をショートコードで制御
- レスポンシブ対応（PC: 設定列数 / タブレット: 2列 / スマホ: 1列）
- ホバー時の画像ズーム＋オーバーレイ
- トランジェントキャッシュ（Cron不使用、リアルタイム取得）
- 取得失敗時は前回キャッシュ（stale）をフォールバック表示

## インストール

1. `rss-display` フォルダを `/wp-content/plugins/` に配置
2. WordPress管理画面でプラグインを有効化
3. `設定 > RSS Display` でフィードURLを設定

## ショートコード

```
[rss_display]
[rss_display columns="4" count="8" type="list"]
[rss_display feed="https://example.com/feed" desc="1" site="1" date="0"]
[rss_display orderby="random" target="_self" img="https://example.com/default.jpg"]
```

| パラメータ | 値 | デフォルト | 説明 |
|-----------|-----|---------|------|
| `columns` | 2 / 3 / 4 | 設定値 | グリッド列数 |
| `count` | 1〜100 | 設定値 | 表示件数 |
| `feed` | URL | 全フィード | 単一フィードを指定（設定のURLを上書き） |
| `orderby` | date / random | date | ソート順 |
| `target` | _blank / _self | 設定値 | リンクの開き方 |
| `img` | URL | 設定値 | デフォルト画像URL（RSS・OGP画像がない場合に使用） |
| `desc` | 0 / 1 | 0 | 説明文を表示するか |
| `date` | 0 / 1 | 1 | 日付を表示するか |
| `site` | 0 / 1 | 0 | サイト名を表示するか |
| `type` | grid / list | grid | 表示タイプ |

## 設定項目

| 項目 | デフォルト |
|------|---------|
| フィードURL（複数可） | なし |
| 表示件数 | 10 |
| 列数 | 3 |
| タイトル行数 | 2 |
| RSSキャッシュ期間 | 1日（12時間/1日/1週間/1ヶ月） |
| デフォルト画像 | 同梱のplaceholder.png |
| リンクを別タブで開く | ON |

キャッシュの手動クリアは管理画面の「今すぐ更新」ボタンから。

## ローカルプレビュー（PHP組み込みサーバー）

PHPをインストール後:

```bash
cd /path/to/rss-display
php -S localhost:8080 preview/router.php
```

ブラウザで `http://localhost:8080` を開くと、任意のRSSフィードURLを入力してカードグリッドの見た目を確認できる。

## テスト

PHPUnit不要のスタンドアロンテストランナーを同梱。WordPress環境なしで実行可能。

```bash
php tests/run.php
```

- `tests/bootstrap.php` — WordPressスタブ定義
- `tests/run.php` — テストスイート（49テスト）

カバー範囲：重複排除・ソート・件数制限・複数フィード結合・全ショートコードパラメータ・XSSエスケープ・画像フォールバック優先順位・エッジケース。

## 動作環境

- PHP 7.4+
- WordPress 6.0+
- GPLv2 or later
