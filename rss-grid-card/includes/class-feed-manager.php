<?php
/**
 * RSS フィードの取得・パース・トランジェントキャッシュ・重複排除を担当するクラス。
 *
 * @package RSS_Grid_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSS_GC_Feed_Manager {

	/** トランジェントのキー接頭辞 */
	const CACHE_PREFIX = 'rss_gc_feed_';

	/**
	 * データ自体の保持期間（最大1ヶ月）。
	 * 「鮮度」はペイロード内の fetched 時刻とユーザー設定TTLで判定するため、
	 * データそのものはフォールバック用に長めに保持しておく。
	 */
	const STORE_TTL = MONTH_IN_SECONDS;

	/** 1フィードあたり正規化する最大件数 */
	const MAX_ITEMS_PER_FEED = 50;

	/**
	 * OGP 画像取得クラス（現状は直接利用しないが将来拡張のため保持）。
	 *
	 * @var RSS_GC_OGP_Fetcher
	 */
	private $ogp_fetcher;

	/**
	 * コンストラクタ。
	 *
	 * @param RSS_GC_OGP_Fetcher $ogp_fetcher OGP 取得クラス。
	 */
	public function __construct( $ogp_fetcher ) {
		$this->ogp_fetcher = $ogp_fetcher;
	}

	/**
	 * 設定された RSS キャッシュTTL（秒）を取得する。
	 *
	 * @return int
	 */
	private function get_ttl() {
		$settings = RSS_Grid_Card::get_settings();
		$ttl      = absint( $settings['cache_ttl'] );

		return $ttl > 0 ? $ttl : DAY_IN_SECONDS;
	}

	/**
	 * 複数フィードから表示用アイテム配列を取得する。
	 * 重複排除・ソート・件数制限まで済ませた状態で返す。
	 *
	 * @param array  $feeds   フィードURLの配列。
	 * @param int    $count   表示件数。
	 * @param string $orderby ソート順（date|random）。
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
	 * 記事URLをキーに重複排除する。
	 * 重複時は日付（timestamp）が新しい方を優先する。
	 *
	 * @param array $items アイテム配列。
	 * @return array
	 */
	private function deduplicate( $items ) {
		$map = array();

		foreach ( $items as $item ) {
			$key = isset( $item['url'] ) ? $item['url'] : '';

			if ( '' === $key ) {
				// URL が無いものはユニーク扱いとして残す。
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
	 * 単一フィードのペイロードを取得する。
	 *
	 * 動作：
	 *   1. キャッシュがあり鮮度内ならそのまま返す（リアルタイム取得＋キャッシュ）。
	 *   2. 鮮度切れなら再取得し、成功すれば上書きして返す。
	 *   3. 再取得に失敗した場合は、前回キャッシュ（stale）をフォールバックとして返す。
	 *
	 * @param string $feed_url フィードURL。
	 * @return array { fetched:int, items:array, count:int, error:bool }
	 */
	public function get_feed_payload( $feed_url ) {
		$key = self::CACHE_PREFIX . md5( $feed_url );
		$ttl = $this->get_ttl();
		$now = time();

		$payload   = get_transient( $key );
		$has_cache = ( is_array( $payload ) && isset( $payload['fetched'] ) );

		// 鮮度内ならそのまま返す。
		if ( $has_cache && ( $now - $payload['fetched'] ) < $ttl ) {
			return $payload;
		}

		// 再取得。
		$items = $this->fetch_and_normalize( $feed_url );

		if ( false !== $items ) {
			$payload = array(
				'fetched' => $now,
				'items'   => $items,
				'count'   => count( $items ),
				'error'   => false,
			);
			set_transient( $key, $payload, self::STORE_TTL );

			return $payload;
		}

		// 取得失敗：前回キャッシュ（stale）があればフォールバックとして返す。
		// トランジェントは上書きしないため、保持期間内は stale データが残る。
		if ( $has_cache ) {
			$payload['error'] = true;

			return $payload;
		}

		// キャッシュも無い完全な失敗。
		return array(
			'fetched' => $now,
			'items'   => array(),
			'count'   => 0,
			'error'   => true,
		);
	}

	/**
	 * フィードを取得して表示用に正規化する。失敗時は false を返す。
	 *
	 * @param string $feed_url フィードURL。
	 * @return array|false
	 */
	private function fetch_and_normalize( $feed_url ) {
		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once ABSPATH . WPINC . '/feed.php';
		}

		// SimplePie 側のキャッシュは無効化し、本プラグインのトランジェントで一元管理する。
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'zero_lifetime' ) );
		$feed = fetch_feed( $feed_url );
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'zero_lifetime' ) );

		if ( is_wp_error( $feed ) ) {
			return false;
		}

		$max = $feed->get_item_quantity( self::MAX_ITEMS_PER_FEED );
		$raw = $feed->get_items( 0, $max );

		$items = array();

		foreach ( $raw as $item ) {
			$url   = $item->get_permalink();
			$url   = $url ? esc_url_raw( $url ) : '';
			$title = $item->get_title();
			$title = $title ? wp_strip_all_tags( $title ) : '';
			$ts    = $item->get_date( 'U' );
			$ts    = $ts ? (int) $ts : 0;
			$image = $this->extract_image_from_item( $item );

			$items[] = array(
				'url'       => $url,
				'title'     => html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ),
				'timestamp' => $ts,
				// RSS 内画像。無ければ ''（後段で OGP → デフォルトへフォールバック）。
				'image'     => $image,
			);
		}

		return $items;
	}

	/**
	 * SimplePie キャッシュ無効化用フィルタコールバック。
	 *
	 * @return int
	 */
	public function zero_lifetime() {
		return 0;
	}

	/**
	 * RSS アイテムから画像URLを抽出する。
	 * 優先順位：media:content → media:thumbnail → enclosure。無ければ ''。
	 *
	 * @param object $item SimplePie_Item。
	 * @return string
	 */
	private function extract_image_from_item( $item ) {
		$media_ns = 'http://search.yahoo.com/mrss/';

		// 1. media:content（Media RSS 名前空間）。
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

			// medium / type が不明でも url があれば最初のものを採用。
			if ( ! empty( $contents[0]['attribs']['']['url'] ) ) {
				return esc_url_raw( $contents[0]['attribs']['']['url'] );
			}
		}

		// media:thumbnail。
		$thumbs = $item->get_item_tags( $media_ns, 'thumbnail' );
		if ( is_array( $thumbs ) && ! empty( $thumbs[0]['attribs']['']['url'] ) ) {
			return esc_url_raw( $thumbs[0]['attribs']['']['url'] );
		}

		// 2. enclosure。
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
	 * URL の拡張子から画像らしいかどうかを判定する（HTML パースではなく URL 判定）。
	 *
	 * @param string $url 判定対象URL。
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
	 * 指定フィード群の RSS キャッシュのみをクリアする（OGP キャッシュは残す）。
	 *
	 * @param array $feeds フィードURLの配列。
	 * @return void
	 */
	public function clear_feed_cache( $feeds ) {
		foreach ( $feeds as $feed_url ) {
			$feed_url = trim( $feed_url );
			if ( '' === $feed_url ) {
				continue;
			}
			delete_transient( self::CACHE_PREFIX . md5( $feed_url ) );
		}
	}

	/**
	 * 管理画面ステータス表示用：フィードの取得状態を返す（フェッチはしない）。
	 *
	 * @param string $feed_url フィードURL。
	 * @return array { fetched:int, count:int, error:bool, cached:bool }
	 */
	public function get_feed_status( $feed_url ) {
		$key     = self::CACHE_PREFIX . md5( $feed_url );
		$payload = get_transient( $key );

		if ( is_array( $payload ) && isset( $payload['fetched'] ) ) {
			return array(
				'fetched' => (int) $payload['fetched'],
				'count'   => isset( $payload['count'] ) ? (int) $payload['count'] : 0,
				'error'   => ! empty( $payload['error'] ),
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
