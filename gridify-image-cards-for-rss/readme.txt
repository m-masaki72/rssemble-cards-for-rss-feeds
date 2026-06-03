=== Gridify Image Cards for RSS ===
Contributors: masakimori
Tags: rss, feed, grid, ogp, cards
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
Donate link: https://ofuse.me/801de8c8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display multiple RSS feeds as OGP image card grids. No external service dependencies.

== Description ==

Gridify Image Cards for RSS fetches multiple RSS feeds and displays them as image card grids using OGP images. It runs entirely on WordPress built-in features (SimplePie, transients, DOMDocument) — no external services required.

Key features:

* Aggregates multiple RSS feeds with URL-based deduplication (newest date wins)
* Automatic OGP image retrieval (priority: media:content → enclosure → og:image → default image)
* 8 layout types: grid, list, list_vertical, text, text_line, image_only, carousel, popup_grid
* Responsive layout (3 columns on desktop, 2 on tablet, 1 on mobile — configurable)
* Hover zoom + shadow effect on cards
* Transient-based caching (no WP-Cron required)
* Configurable RSS cache duration (12 hours / 1 day / 1 week / 1 month)
* OGP image URL cached for 1 month (includes negative cache for failed fetches)
* Stale cache fallback when a feed fetch fails
* Compatible with object cache (Redis, Memcached) and Cloudflare

== Installation ==

1. Upload the `gridify-image-cards-for-rss` folder to `/wp-content/plugins/`, or install via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin from the **Plugins** screen.
3. Go to **Settings > Gridify Image Cards** and configure your feed URLs.
4. Add the shortcode to any post, page, or widget.

== Usage ==

Basic:

`[rss_display]`

With parameters:

`[rss_display columns="4" count="8"]`
`[rss_display columns="2" count="6" feed="https://example.com/feed"]`
`[rss_display orderby="random" target="_self"]`

Parameter reference:

* columns    : Number of columns (2 / 3 / 4). Default: admin setting.
* count      : Number of items to display. Default: admin setting.
* feed       : Comma-separated feed URL(s). Default: all registered feeds.
* orderby    : Sort order (date / random). Default: date.
* target     : Link target (_blank / _self). Default: admin setting.
* type       : Display type (grid / list / list_vertical / text / text_line / image_only / carousel / popup_grid).
* date       : Show date (1 / 0). Default: 1.
* site       : Show site name (1 / 0). Default: 0.
* desc       : Show description (1 / 0). Default: 0.
* bold       : Bold title (1 / 0). Default: 0.
* responsive : Responsive columns (1 / 0). Default: 1.
* title_lines: Maximum title lines (1 / 2 / 3). Default: admin setting.
* img        : Override default image URL.

== Frequently Asked Questions ==

= No image is displayed =

If the RSS feed contains no image and the article's og:image cannot be fetched, the default image configured in the admin settings (or the bundled placeholder) is shown.

= How do I clear the cache immediately? =

Go to **Settings > Gridify Image Cards** and click **Refresh Now**. This clears the RSS cache; OGP image cache (1-month fixed) is not affected.

= Does it use WP-Cron? =

No. Feeds are fetched on demand when the shortcode runs and the cache is missing or expired.

== Screenshots ==

1. Card grid display on the front end.
2. Admin settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
