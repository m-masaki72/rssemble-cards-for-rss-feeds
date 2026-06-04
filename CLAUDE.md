# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

WordPress プラグイン。複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示する。外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

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

# .pot / .po → .mo コンパイル
node scripts/po2mo.js
```

## Architecture

`preview/` は `wp-stub.php` で WP API をモックしたスタンドアロン環境。`tests/` は PHPUnit 不要の独自ランナー、`tests/e2e/` は Playwright。

### データフロー

1. ショートコード実行 → `RSS_D_Feed_Manager::get_items()` → 各フィードを `get_feed_payload()` で取得
2. キャッシュ戦略：RSSはトランジェント（ユーザー設定TTL: 12h/1d/1w/1m）、OGP画像は固定1ヶ月
3. 取得失敗時はstaleキャッシュをフォールバック表示
4. 画像解決の優先順位：RSS内画像（media:content → media:thumbnail → enclosure）→ OGP取得（og:image等）→ デフォルト画像
5. SimplePie側のキャッシュは無効化し、プラグインのトランジェントで一元管理

### Key Constraints

- PHP 7.4+、WordPress 6.0+
- 設定は `rss_d_settings` オプションに保存。`RSS_Display::get_settings()` でデフォルトとマージして取得
- トランジェントキー：RSS = `rss_d_feed_{md5(url)}`、OGP = `rss_d_ogp_{md5(url)}`
- アンインストール時は設定オプションのみ削除（トランジェントは自然失効に任せる）

## WordPress.org 提出

提出・再提出手順は `.claude/skills/submit-to-wporg.md` を参照。  
提出前に `readme.txt` の `Tested up to:` を WordPress 最新安定版に更新すること。
