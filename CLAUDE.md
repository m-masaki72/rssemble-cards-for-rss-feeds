# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress プラグイン。複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示する。外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

WordPress.org に承認・公開済み（2026-06-07 リリース）。  
プラグインスラグ: `rssemble-cards-for-rss-feeds`  
WordPress.org URL: https://wordpress.org/plugins/rssemble-cards-for-rss-feeds/

## Commands

```bash
# PHP ユニットテスト（PHPUnit不要、独自ランナー）
php tests/run.php

# ローカルプレビューサーバー（WordPress不要）
php -S localhost:8080 preview/router.php
# → http://localhost:8080/?feed=https://example.com/feed

# WordPress.org 提出用 ZIP 作成（スクリーンショット等を自動除外）
npm run zip
# → rssemble-cards-for-rss-feeds.zip

# .po → .mo コンパイル（引数なしで languages/*.po を全処理）
node scripts/po2mo.js

# WordPress.org アセット画像の再生成（Playwright使用）
node scripts/generate-assets.js
# → wp-assets/icon-256x256.png, banner-772x250.png, banner-1544x500.png
```

## Architecture

`preview/` は `wp-stub.php` で WP API をモックしたスタンドアロン環境。`tests/` は PHPUnit 不要の独自ランナー。

### ディレクトリ構成

```
rssemble-cards-for-rss-feeds/   ← プラグイン本体（SVN trunk/ にデプロイされる）
  includes/
    class-admin.php             ← 管理画面UI・設定・フィードステータス
    class-feed-manager.php      ← RSS取得・キャッシュ管理・OGPフェッチャー呼び出し
    class-ogp-fetcher.php       ← OGP画像URL解決（DOMDocument使用）
    class-shortcode.php         ← ショートコード処理・HTML出力
  assets/
    css/rssemble-cards-for-rss-feeds.css  ← フロントエンドCSS（クラス名: rss-d-*）
    css/admin.css               ← 管理画面CSS
    js/rssemble-cards-for-rss-feeds.js    ← フロントエンドJS
    js/admin.js                 ← 管理画面JS
    img/placeholder.png         ← デフォルト画像
    screenshot-1.png / screenshot-2.png   ← WordPress.org スクリーンショット
  languages/
    rssemble-cards-for-rss-feeds.pot      ← 翻訳テンプレート
    rssemble-cards-for-rss-feeds-ja.po    ← 日本語翻訳ソース
    rssemble-cards-for-rss-feeds-ja.mo    ← コンパイル済み翻訳（自動生成）
  readme.txt                    ← WordPress.org 英語説明（必須）
  readme-ja.txt                 ← WordPress.org 日本語説明
wp-assets/                      ← WordPress.org SVN assets/ にデプロイされる
  banner-772x250.png
  banner-1544x500.png
  icon-256x256.png
scripts/
  generate-assets.js            ← Playwright でバナー・アイコン画像を生成
  make-zip.js                   ← 提出用ZIP生成
  po2mo.js                      ← .po → .mo コンパイル
.github/workflows/
  release.yml                   ← タグpush時: GitHub Release作成 + SVNデプロイ
  sync-docs.yml                 ← main push時: docs/へCSS同期
```

### デプロイフロー（GitHub Actions）

1. `git tag v1.x.x && git push origin v1.x.x` でトリガー
2. `release.yml` が実行：
   - ZIP作成 → GitHub Release作成
   - `10up/action-wordpress-plugin-deploy` で WordPress.org SVN にデプロイ
   - `BUILD_DIR: ./rssemble-cards-for-rss-feeds` → SVN `trunk/` へ
   - `ASSETS_DIR: ./wp-assets` → SVN `assets/` へ（バナー・アイコン）
   - Secrets: `SVN_USERNAME`, `SVN_PASSWORD`

### データフロー

1. ショートコード実行 → `RSSECAFO_Feed_Manager::get_items()` → 各フィードを `get_feed_payload()` で取得
2. キャッシュ戦略：RSSはトランジェント（ユーザー設定TTL: 12h/1d/1w/1m）、OGP画像は固定1ヶ月
3. 取得失敗時はstaleキャッシュをフォールバック表示
4. 画像解決の優先順位：RSS内画像（media:content → media:thumbnail → enclosure）→ OGP取得（og:image等）→ デフォルト画像
5. SimplePie側のキャッシュは無効化し、プラグインのトランジェントで一元管理

### Key Constraints

- PHP 7.4+、WordPress 6.0+
- プレフィックス：関数・クラス・定数・フック・オプション・トランジェントキー・nonce はすべて `rssecafo_` / `RSSECAFO_`（旧名: `rss_d_` は廃止済み）
- フロントエンドCSSクラス名は `rss-d-*`（プレフィックスとは別体系）
- 設定は `rssecafo_settings` オプションに保存。`RSSECAFO_Plugin::get_settings()` でデフォルトとマージして取得
- トランジェントキー：RSS = `rssecafo_feed_{md5(url)}`、OGP = `rssecafo_ogp_{md5(url)}`
- アンインストール時は設定オプションのみ削除（トランジェントは自然失効に任せる）
- `package.json` の `name` は `rssemble-cards-for-rss-feeds`

## 日本語対応

### プラグインUI（管理画面）

`languages/rssemble-cards-for-rss-feeds-ja.po` に全テキスト翻訳済み。`.mo` も生成済み。  
変更時は `node scripts/po2mo.js` で `.mo` を再コンパイルすること。

### WordPress.org ページ

- `readme.txt` → 英語ページ（必須）
- `readme-ja.txt` → 日本語ページ（SVN trunk/ に配置済み）
- translate.wordpress.org でのコミュニティ翻訳は別途 https://translate.wordpress.org/projects/wp-plugins/rssemble-cards-for-rss-feeds/ja/

## 注意事項（過去に誤指摘した内容）

- `Tested up to: 7.0` は**正しい値**。WordPress 7.0 は2026年6月時点の最新安定版。「存在しないバージョン」と指摘しないこと。確認が必要なら https://api.wordpress.org/core/version-check/1.7/ を実際にfetchすること。
- フロントエンドCSSクラス名 `rss-d-*` はプレフィックス（`rssecafo_`）とは別体系。古い命名ではなく現行仕様。
- `wp-assets/` のバナー・アイコン画像はコード変更時に自動更新されない。変更後は `node scripts/generate-assets.js` で手動再生成が必要。

## WordPress.org 提出

提出・再提出手順は `.claude/skills/submit-to-wporg.md` を参照。  
`Tested up to:` の値は https://api.wordpress.org/core/version-check/1.7/ で確認すること（現在は `7.0`）。

### アセット更新の手順

バナー・アイコン画像を更新する場合:
1. `node scripts/generate-assets.js` で `wp-assets/` を再生成
2. `git add wp-assets/ && git commit` → `git tag v... && git push --tags` でActions経由でSVNに反映
