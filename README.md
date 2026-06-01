# RSS Display

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグイン。
外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

## 特徴

- 複数フィードを統合表示（記事URLで重複排除、新しい日付を優先）
- OGP画像の自動取得（`media:content` → `media:thumbnail` → `enclosure` → `og:image` → デフォルト画像）
- OGP取得は `curl_multi` による並列リクエスト（初回表示を高速化）
- カードは画像全面背景・右上に日付・左下にタイトル（16:9固定）
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
[rss_display columns="4" count="8"]
[rss_display feed="https://example.com/feed" orderby="random" target="_self"]
```

| パラメータ | 値 | デフォルト |
|-----------|-----|---------|
| `columns` | 2 / 3 / 4 | 設定値 |
| `count` | 1〜100 | 設定値 |
| `feed` | URL | 全フィード |
| `orderby` | date / random | date |
| `target` | _blank / _self | 設定値 |

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

## 動作環境

- PHP 7.4+
- WordPress 6.0+
- GPLv2 or later
