# Rssemble Cards for RSS Feeds

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグイン。外部サービス依存なし。

## 特徴

- 複数フィードを統合表示（記事URLで重複排除、新着優先）
- OGP画像の自動取得（RSS内画像 → og:image → デフォルト画像）
- OGP取得は `curl_multi` による並列リクエスト（WPプロキシ設定・SSL検証に対応）
- 8種類の表示タイプ（grid / list_vertical / carousel / popup_grid など）
- WordPress FSEテーマのカラー変数（`--wp--preset--color--*`）に自動追従（非対応テーマは黒白ベースでフォールバック）
- Freemiumモデル（無料3フィード・2列・4タイプ、Pro無制限）
- 管理画面タブUI + プレビュー機能（デスクトップ/タブレット/スマホ幅切替）
- レスポンシブ対応（PC: 設定列数 / タブレット: 2列 / スマホ: 1列）
- トランジェントキャッシュ（Cron不使用）、取得失敗時はstaleフォールバック

## インストール

1. `rssemble-cards-for-rss-feeds` フォルダを `/wp-content/plugins/` に配置
2. WordPress管理画面でプラグインを有効化
3. `設定 > Rssemble Cards` でフィードURLと表示設定を入力

## ショートコード

```
[rss_display]
[rss_display type="carousel" columns="3" count="10"]
[rss_display type="popup_grid" feed="https://example.com/feed" desc="1" site="1"]
[rss_display orderby="random" target="_self" img="https://example.com/default.jpg"]
```

### パラメータ一覧

| パラメータ | 値 | デフォルト | 説明 |
|-----------|-----|---------|------|
| `type` | grid / list_vertical / text / text_line / image_only / list / carousel / popup_grid | 設定値 | 表示タイプ |
| `columns` | 2 / 3 / 4 | 設定値 | 列数 |
| `count` | 1〜100 | 設定値 | 表示件数 |
| `feed` | URL | 全フィード | 単一フィードを指定 |
| `orderby` | date / random | 設定値 | ソート順 |
| `target` | _blank / _self | 設定値 | リンクの開き方 |
| `img` | URL | 設定値 | デフォルト画像URL |
| `desc` | 0 / 1 | 設定値 | 説明文表示 |
| `date` | 0 / 1 | 設定値 | 日付表示 |
| `site` | 0 / 1 | 設定値 | サイト名表示 |

## 無料 / Pro

| 機能 | 無料 | Pro |
|------|------|-----|
| フィード数 | 3件 | 無制限 |
| 列数 | 2列のみ | 2〜4列 |
| 表示タイプ | grid / list_vertical / text / text_line | 全8種 |
| ソート順 | date のみ | date / random |
| 表示件数 | 20件まで | 100件まで |
| キャッシュTTL | 1日固定 | 12h〜1ヶ月 |

## 管理画面

- **基本設定** — フィードURL・キャッシュ時間・デフォルト画像・リンク設定
- **表示設定** — 表示タイプ・列数・件数・ソート順・日付/サイト名/説明文のデフォルト値
- **プレビュー** — デバイス幅切替（PC/タブレット/スマホ）、タイプ・列数・件数をAJAXでリアルタイムプレビュー
- **フィード状態** — 各フィードの最終取得時刻・件数・エラー状態
- **使い方** — ショートコード一覧

## 動作環境

- PHP 7.4+
- WordPress 6.0+
- GPLv2 or later
