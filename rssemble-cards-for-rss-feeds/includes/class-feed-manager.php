<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Fetches, parses, caches, and deduplicates RSS feed items.
 *
 * @package Rssemble_Cards_For_RSS_Feeds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages RSS feed fetching, transient caching, and deduplication.
 */
class RSSECAFO_Feed_Manager {

	/** Transient key prefix. */
	const CACHE_PREFIX = 'rssecafo_feed_';

	/**
	 * How long to keep cached data (longer than the longest TTL of 1 month).
	 * Freshness is determined by the fetched timestamp + user TTL setting;
	 * the raw payload is kept longer as a stale fallback.
	 */
	const STORE_TTL = 2 * MONTH_IN_SECONDS;

	/** Maximum items fetched per feed. */
	const MAX_ITEMS_PER_FEED = 24;

	/** Transient key prefix: feed fetch failed recently, do not retry yet. */
	const FAIL_PREFIX = 'rssecafo_fail_';

	/** Transient key prefix: background refresh requested (value = request time). */
	const WAIT_PREFIX = 'rssecafo_wait_';

	/** WP-Cron hook that refreshes one feed. */
	const CRON_HOOK = 'rssecafo_refresh_feed';

	/** Seconds to wait before retrying a failed feed that has a stale cache. */
	const RETRY_STALE = 3600;

	/** Seconds to wait before retrying a failed feed that has no cache at all. */
	const RETRY_EMPTY = 300;

	/** If WP-Cron has not refreshed the feed after this many seconds, fetch during the request. */
	const CRON_GRACE = 3600;

	/** Per-request in-memory cache to avoid redundant transient reads. */
	private static $payload_cache = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Returns the configured RSS cache TTL in seconds.
	 *
	 * @return int
	 */
	private function get_ttl() {
		static $ttl = null;
		if ( null === $ttl ) {
			$settings = RSSECAFO_Plugin::get_settings();
			$value    = absint( $settings['cache_ttl'] );
			$ttl      = $value > 0 ? $value : DAY_IN_SECONDS;
		}
		return $ttl;
	}

	/**
	 * Returns display items merged from multiple feeds, deduplicated and sorted.
	 *
	 * @param array  $feeds   Array of feed URLs.
	 * @param int    $count   Number of items to return.
	 * @param string $orderby Sort order: 'date' or 'random'.
	 * @return array
	 */
	public function get_items( $feeds, $count, $orderby = 'date' ) {
		$all = array();

		foreach ( $feeds as $feed_url ) {
			$feed_url = trim( $feed_url );
			if ( '' === $feed_url ) {
				continue;
			}

			$payload = $this->get_feed_payload( $feed_url );
			if ( ! empty( $payload['items'] ) ) {
				foreach ( $payload['items'] as $item ) {
					$all[] = $item;
				}
			}
		}

		$all = $this->deduplicate( $all );

		if ( 'random' === $orderby ) {
			shuffle( $all );
		} else {
			usort(
				$all,
				static function ( $a, $b ) {
					return $b['timestamp'] <=> $a['timestamp'];
				}
			);
		}

		$count = absint( $count );
		if ( $count > 0 ) {
			$all = array_slice( $all, 0, $count );
		}

		return $all;
	}

	/**
	 * Deduplicates items by URL, keeping the one with the newer timestamp.
	 *
	 * @param array $items Raw item array.
	 * @return array
	 */
	private function deduplicate( $items ) {
		$map = array();

		foreach ( $items as $item ) {
			$key = isset( $item['url'] ) ? $item['url'] : '';

			if ( '' === $key ) {
				// Items without a URL are kept as unique entries.
				$map[ uniqid( 'nourl_', true ) ] = $item;
				continue;
			}

			if ( ! isset( $map[ $key ] ) || $item['timestamp'] > $map[ $key ]['timestamp'] ) {
				$map[ $key ] = $item;
			}
		}

		return array_values( $map );
	}

	/**
	 * Returns the payload for a single feed, fetching and caching as needed.
	 *
	 * Strategy:
	 *   1. Return cached payload if still fresh (within user-configured TTL).
	 *   2. On stale, return the stale payload and refresh it in the background (WP-Cron).
	 *   3. Fetch during the request only when nothing is cached or WP-Cron did not run.
	 *   4. After a failed fetch, do not retry until the back-off expires.
	 *
	 * @param string $feed_url Feed URL.
	 * @return array { fetched:int, items:array, count:int, error:bool }
	 */
	public function get_feed_payload( $feed_url ) {
		if ( isset( self::$payload_cache[ $feed_url ] ) ) {
			return self::$payload_cache[ $feed_url ];
		}

		$key = self::CACHE_PREFIX . md5( $feed_url );
		$ttl = $this->get_ttl();
		$now = time();

		$payload   = get_transient( $key );
		$has_cache = ( is_array( $payload ) && isset( $payload['fetched'] ) );

		// Return cached payload if still fresh.
		if ( $has_cache && ( $now - $payload['fetched'] ) < $ttl ) {
			self::$payload_cache[ $feed_url ] = $payload;
			return $payload;
		}

		// Back off after a recent failure, or hand the refresh over to WP-Cron.
		if ( false !== get_transient( self::FAIL_PREFIX . md5( $feed_url ) )
			|| ( $has_cache && $this->defer_refresh( $feed_url, $now ) ) ) {
			if ( ! $has_cache ) {
				$payload = array(
					'fetched' => $now,
					'items'   => array(),
					'count'   => 0,
					'error'   => true,
				);
			}
			self::$payload_cache[ $feed_url ] = $payload;
			return $payload;
		}

		return $this->refresh_feed( $feed_url );
	}

	/**
	 * Schedules a background refresh of a stale feed.
	 *
	 * @param string $feed_url Feed URL.
	 * @param int    $now      Current timestamp.
	 * @return bool False when the refresh must run in the current request.
	 */
	private function defer_refresh( $feed_url, $now ) {
		$wait_key = self::WAIT_PREFIX . md5( $feed_url );
		$since    = get_transient( $wait_key );

		if ( false !== $since ) {
			return ( $now - (int) $since ) < self::CRON_GRACE;
		}

		$args = array( $feed_url );
		if ( ! wp_next_scheduled( self::CRON_HOOK, $args ) && false === wp_schedule_single_event( $now, self::CRON_HOOK, $args ) ) {
			return false;
		}
		set_transient( $wait_key, $now, self::STORE_TTL );
		return true;
	}

	/**
	 * Fetches a feed now and updates the cache.
	 *
	 * @param string $feed_url Feed URL.
	 * @return array { fetched:int, items:array, count:int, error:bool }
	 */
	public function refresh_feed( $feed_url ) {
		$key       = self::CACHE_PREFIX . md5( $feed_url );
		$now       = time();
		$payload   = get_transient( $key );
		$has_cache = ( is_array( $payload ) && isset( $payload['fetched'] ) );

		$items = $this->fetch_and_normalize( $feed_url );
		delete_transient( self::WAIT_PREFIX . md5( $feed_url ) );

		if ( false !== $items ) {
			$payload = array(
				'fetched' => $now,
				'items'   => $items,
				'count'   => count( $items ),
				'error'   => false,
			);
			set_transient( $key, $payload, self::STORE_TTL );
			delete_transient( self::FAIL_PREFIX . md5( $feed_url ) );
			self::$payload_cache[ $feed_url ] = $payload;

			return $payload;
		}

		set_transient( self::FAIL_PREFIX . md5( $feed_url ), $now, $has_cache ? self::RETRY_STALE : self::RETRY_EMPTY );

		// Fetch failed: return stale cache if available.
		// The transient is intentionally not overwritten so stale data persists until STORE_TTL expires.
		if ( $has_cache ) {
			$payload['error'] = true;
			self::$payload_cache[ $feed_url ] = $payload;

			return $payload;
		}

		// No cache and fetch failed.
		$payload = array(
			'fetched' => $now,
			'items'   => array(),
			'count'   => 0,
			'error'   => true,
		);
		self::$payload_cache[ $feed_url ] = $payload;
		return $payload;
	}

	/**
	 * Fetches a feed and normalizes items into display arrays. Returns false on failure.
	 *
	 * @param string $feed_url Feed URL.
	 * @return array|false
	 */
	private function fetch_and_normalize( $feed_url ) {
		// Disable SimplePie's own cache; this plugin manages caching via transients.
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'zero_lifetime' ) );
		$feed = fetch_feed( $feed_url );
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'zero_lifetime' ) );

		if ( is_wp_error( $feed ) ) {
			return false;
		}

		$max = $feed->get_item_quantity( self::MAX_ITEMS_PER_FEED );
		$raw = $feed->get_items( 0, $max );

		$items = array();

		$site_name = html_entity_decode( wp_strip_all_tags( (string) $feed->get_title() ), ENT_QUOTES, 'UTF-8' );

		foreach ( $raw as $item ) {
			$url   = $item->get_permalink();
			$url   = $url ? esc_url_raw( $url ) : '';
			$title = $item->get_title();
			$title = $title ? wp_strip_all_tags( $title ) : '';
			$ts    = $item->get_date( 'U' );
			$ts    = $ts ? (int) $ts : 0;
			$image = $this->extract_image_from_item( $item );
			$desc  = $item->get_description();
			$desc  = $desc ? wp_strip_all_tags( $desc ) : '';
			$desc  = html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' );
			$desc  = mb_substr( $desc, 0, 300 );

			$items[] = array(
				'url'       => $url,
				'title'     => html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ),
				'timestamp' => $ts,
				'image'     => $image,
				'desc'      => $desc,
				'site'      => $site_name,
			);
		}

		return $items;
	}

	/**
	 * Filter callback that disables SimplePie's transient cache lifetime.
	 *
	 * @return int
	 */
	public function zero_lifetime() {
		return 0;
	}

	/**
	 * Extracts an image URL from an RSS item.
	 * Priority: media:content → media:thumbnail → enclosure. Returns '' if none found.
	 *
	 * @param object $item SimplePie_Item instance.
	 * @return string
	 */
	private function extract_image_from_item( $item ) {
		$media_ns = 'http://search.yahoo.com/mrss/';

		// 1. media:content (Media RSS namespace).
		$contents = $item->get_item_tags( $media_ns, 'content' );
		if ( is_array( $contents ) ) {
			foreach ( $contents as $content ) {
				if ( empty( $content['attribs']['']['url'] ) ) {
					continue;
				}
				$candidate = $content['attribs']['']['url'];
				$medium    = isset( $content['attribs']['']['medium'] ) ? $content['attribs']['']['medium'] : '';
				$type      = isset( $content['attribs']['']['type'] ) ? $content['attribs']['']['type'] : '';

				if ( 'image' === $medium || 0 === strpos( (string) $type, 'image' ) || $this->looks_like_image( $candidate ) ) {
					return esc_url_raw( $candidate );
				}
			}

			// Fall back to first entry even if medium/type is unknown.
			if ( ! empty( $contents[0]['attribs']['']['url'] ) ) {
				return esc_url_raw( $contents[0]['attribs']['']['url'] );
			}
		}

		// 2. media:thumbnail.
		$thumbs = $item->get_item_tags( $media_ns, 'thumbnail' );
		if ( is_array( $thumbs ) && ! empty( $thumbs[0]['attribs']['']['url'] ) ) {
			return esc_url_raw( $thumbs[0]['attribs']['']['url'] );
		}

		// 3. enclosure.
		$enclosure = $item->get_enclosure();
		if ( $enclosure ) {
			$thumb = $enclosure->get_thumbnail();
			if ( $thumb ) {
				return esc_url_raw( $thumb );
			}

			$link = $enclosure->get_link();
			$type = (string) $enclosure->get_type();
			if ( $link && ( false !== strpos( $type, 'image' ) || $this->looks_like_image( $link ) ) ) {
				return esc_url_raw( $link );
			}
		}

		return '';
	}

	/**
	 * Returns true if the URL path extension looks like an image file.
	 *
	 * @param string $url URL to inspect.
	 * @return bool
	 */
	private function looks_like_image( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}

		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ), true );
	}

	/**
	 * Clears RSS transient cache for the given feeds (OGP cache is preserved).
	 *
	 * @param array $feeds Array of feed URLs.
	 * @return void
	 */
	public function clear_feed_cache( $feeds ) {
		foreach ( $feeds as $feed_url ) {
			$feed_url = trim( $feed_url );
			if ( '' === $feed_url ) {
				continue;
			}
			delete_transient( self::CACHE_PREFIX . md5( $feed_url ) );
			delete_transient( self::FAIL_PREFIX . md5( $feed_url ) );
			delete_transient( self::WAIT_PREFIX . md5( $feed_url ) );
			unset( self::$payload_cache[ $feed_url ] );
		}
	}

	/**
	 * Returns cached feed status for admin display without triggering a fetch.
	 *
	 * @param string $feed_url Feed URL.
	 * @return array { fetched:int, count:int, error:bool, cached:bool }
	 */
	public function get_feed_status( $feed_url ) {
		$key     = self::CACHE_PREFIX . md5( $feed_url );
		$payload = get_transient( $key );

		if ( is_array( $payload ) && isset( $payload['fetched'] ) ) {
			return array(
				'fetched' => (int) $payload['fetched'],
				'count'   => isset( $payload['count'] ) ? (int) $payload['count'] : 0,
				'error'   => false !== get_transient( self::FAIL_PREFIX . md5( $feed_url ) ),
				'cached'  => true,
			);
		}

		return array(
			'fetched' => 0,
			'count'   => 0,
			'error'   => false,
			'cached'  => false,
		);
	}
}
