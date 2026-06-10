# Rssemble Cards for RSS Feeds

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress: 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-21759b)](https://wordpress.org/plugins/rssemble-cards-for-rss-feeds/)
[![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)](https://www.php.net)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-active-0073aa)](https://wordpress.org/plugins/rssemble-cards-for-rss-feeds/)
[![Demo](https://img.shields.io/badge/Demo-live-brightgreen)](https://rssemble-cards-for-rss-feeds.pages.dev/)

A WordPress plugin that fetches multiple RSS feeds and displays them as OGP image card grids. No external service dependencies — runs entirely on WordPress built-in features (SimplePie, transients, DOMDocument).

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグイン。外部サービス依存なし（WordPress組み込みのSimplePie・トランジェント・DOMDocumentのみ使用）。

---

## Demo

**[Live Demo](https://rssemble-cards-for-rss-feeds.pages.dev/)** — WordPress なしで全レイアウトタイプを試せるスタンドアロンプレビューです。

## Features

- Aggregates multiple RSS feeds with URL-based deduplication (newest date wins)
- Automatic OGP image retrieval: RSS image → `og:image` → default image
- Parallel OGP fetching via `curl_multi` (respects WP proxy settings and SSL verification)
- 8 layout types: `grid` / `list` / `list_vertical` / `text` / `text_line` / `image_only` / `carousel` / `popup_grid`
- Responsive layout (configurable columns on desktop, 2 on tablet, 1 on mobile)
- FSE theme color variable support (`--wp--preset--color--*`) with fallback
- Transient-based caching (no WP-Cron), configurable TTL (12h / 1d / 1w / 1mo)
- Stale cache fallback when a feed fetch fails
- Admin UI with live preview (desktop / tablet / mobile width switching)

## Installation

### From WordPress.org

1. Go to **Plugins > Add New** and search for `Rssemble Cards for RSS Feeds`
2. Install and activate
3. Go to **Settings > Rssemble Cards** and configure your feed URLs

### Manual

1. Download the ZIP from [WordPress.org](https://wordpress.org/plugins/rssemble-cards-for-rss-feeds/) or [GitHub Releases](../../releases)
2. Upload via **Plugins > Add New > Upload Plugin**
3. Activate and configure

## Usage

```
[rssecafo]
[rssecafo type="carousel" columns="3" count="10"]
[rssecafo feed="https://example.com/feed" desc="1" site="1"]
[rssecafo orderby="random" target="_self"]
```

### Shortcode Parameters

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `type` | grid / list / list_vertical / text / text_line / image_only / carousel / popup_grid | admin setting | Layout type |
| `columns` | 2 / 3 / 4 | admin setting | Number of columns |
| `count` | 1–100 | admin setting | Number of items |
| `feed` | URL(s) | all registered | Comma-separated feed URL(s) |
| `orderby` | date / random | date | Sort order |
| `target` | _blank / _self | admin setting | Link target |
| `img` | URL | admin setting | Override default image URL |
| `desc` | 0 / 1 | admin setting | Show description |
| `date` | 0 / 1 | 1 | Show date |
| `site` | 0 / 1 | 0 | Show site name |
| `bold` | 0 / 1 | 0 | Bold title |
| `responsive` | 0 / 1 | 1 | Responsive columns |
| `title_lines` | 1 / 2 / 3 | admin setting | Max title lines |

## Development

```bash
# PHP unit tests (no PHPUnit required — custom runner)
php tests/run.php

# Local preview server (no WordPress required)
php -S localhost:8080 preview/router.php

# Build ZIP for submission
npm run zip

# Compile .po → .mo
node scripts/po2mo.js

# Regenerate wp-assets images (requires Playwright)
node scripts/generate-assets.js
```

### Requirements

- PHP 7.4+
- WordPress 6.0+
- Node.js (for build scripts)

## License

[GNU General Public License v2.0 or later](./LICENSE)
