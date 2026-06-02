# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress プラグイン。複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示する。外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

## Architecture

```
rss-display/
  rss-display.php            # エントリポイント。定数定義・クラスのインクルード・シングルトン起動
  includes/
    class-feed-manager.php   # RSSフィード取得・パース・トランジェントキャッシュ・重複排除
    class-ogp-fetcher.php    # 記事ページからog:image等を取得・キャッシュ
    class-shortcode.php      # [rss_display] ショートコード処理・HTML生成・CSS enqueue
    class-admin.php          # 管理画面（設定 → RSS Display）・設定保存・キャッシュ更新
  assets/css/rss-display.css # フロントエンドのグリッド・カードスタイル
  assets/css/admin.css       # 管理画面スタイル
  assets/js/admin.js         # 管理画面のメディアライブラリ選択UI
  assets/img/placeholder.png # デフォルト画像（フォールバック）
```

### データフロー

1. ショートコード実行 → `RSS_D_Feed_Manager::get_items()` → 各フィードを `get_feed_payload()` で取得
2. キャッシュ戦略：RSSはトランジェント（ユーザー設定TTL: 12h/1d/1w/1m）、OGP画像は固定1ヶ月
3. 取得失敗時はstaleキャッシュをフォールバック表示
4. 画像解決の優先順位：RSS内画像（media:content → media:thumbnail → enclosure）→ OGP取得（og:image等）→ デフォルト画像
5. SimplePie側のキャッシュは無効化し、プラグインのトランジェントで一元管理

### 設定

`RSS_D_OPTION`（`rss_d_settings`）オプションに配列で保存。`RSS_Display::get_settings()` でデフォルトとマージして取得。

### CSS変数

グリッド列数（`--rss-d-columns`）とタイトル行数（`--rss-d-title-lines`）はインラインスタイルのCSS変数でショートコードから渡す。

カラーは `rss-display.css` の `:root` にプラグイン独自変数（`--rss-d-*`）を定義し、値は `var(--wp--preset--color--*, フォールバック値)` 形式で WordPress FSE テーマに自動追従する。FSE 非対応テーマでは固定色（黒白ベース）にフォールバック。overlay 上の白文字（`#fff`）のみ固定値。

## Key Constraints

- PHP 7.4+、WordPress 6.0+
- `ABSPATH` 未定義時の早期 exit を全クラスに実装
- アンインストール時は設定オプションのみ削除（トランジェントは自然失効に任せる）
- トランジェントキー：RSS = `rss_d_feed_{md5(url)}`、OGP = `rss_d_ogp_{md5(url)}`
