<?php
/**
 * 記事ページから OGP 画像URLを取得するクラス。
 * 取得結果（未検出含む）は記事URLをキーに1ヶ月トランジェントキャッシュする。
 *
 * @package RSS_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSS_D_OGP_Fetcher {

	/** トランジェントのキー接頭辞 */
	const CACHE_PREFIX = 'rss_d_ogp_';

	/** OGP キャッシュ期間（固定1ヶ月） */
	const CACHE_TTL = MONTH_IN_SECONDS;

	/** HTTP タイムアウト（秒） */
	const TIMEOUT = 5;

	/**
	 * 記事URLから OGP 画像URLを取得する（単一URL用ラッパー）。
	 *
	 * @param string $article_url 記事URL。
	 * @return string 画像URL。見つからなければ ''。
	 */
	public function get_image( $article_url ) {
		$map = $this->get_images( array( $article_url ) );
		$url = trim( $article_url );
		return isset( $map[ $url ] ) ? $map[ $url ] : '';
	}

	/**
	 * 複数記事URLの OGP 画像を取得する。
	 * キャッシュ済みはスキップし、未取得URLのみ curl_multi で並列リクエスト（使えない環境は直列）。
	 * 空URLは透過的にスキップする。
	 *
	 * @param string[] $urls 記事URLの配列。
	 * @return array URL => 画像URL のマップ（未検出は ''）。
	 */
	public function get_images( $urls ) {
		$results  = array();
		$to_fetch = array();
		$keys     = array(); // md5 の再計算を避けるキャッシュ。

		$seen = array();
		foreach ( $urls as $url ) {
			$url = trim( $url );
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ]  = true;
			$keys[ $url ]  = self::CACHE_PREFIX . md5( $url );
			$cached        = get_transient( $keys[ $url ] );
			if ( false !== $cached ) {
				$results[ $url ] = (string) $cached;
			} else {
				$to_fetch[] = $url;
			}
		}

		if ( empty( $to_fetch ) ) {
			return $results;
		}

		if ( ! function_exists( 'curl_multi_init' ) ) {
			// curl_multi が使えない環境は直列フォールバック。
			foreach ( $to_fetch as $url ) {
				$image           = $this->fetch_ogp_image( $url );
				set_transient( $keys[ $url ], $image, self::CACHE_TTL );
				$results[ $url ] = $image;
			}
			return $results;
		}

		// curl_multi で並列リクエスト。
		// 注意: WordPress HTTP フィルタ（pre_http_request 等）はバイパスされる。
		$mh      = curl_multi_init();
		$handles = array();

		foreach ( $to_fetch as $url ) {
			$ch = curl_init( $url );
			if ( false === $ch ) {
				$results[ $url ] = '';
				continue;
			}
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 3,
					CURLOPT_TIMEOUT        => self::TIMEOUT,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_USERAGENT      => 'WordPress/RSS-Display',
					CURLOPT_HTTPHEADER     => array( 'Accept: text/html,application/xhtml+xml' ),
					// <head> が取れれば十分なので最大128KBで打ち切る。
					CURLOPT_BUFFERSIZE        => 131072,
					CURLOPT_NOPROGRESS        => false,
					CURLOPT_PROGRESSFUNCTION  => static function ( $ch, $dl_total, $dl_now ) {
						return $dl_now > 131072 ? 1 : 0;
					},
				)
			);
			curl_multi_add_handle( $mh, $ch );
			$handles[ $url ] = $ch;
		}

		// 全リクエスト完了まで待つ。
		$running = null;
		do {
			curl_multi_exec( $mh, $running );
			if ( -1 === curl_multi_select( $mh ) ) {
				usleep( 100000 );
			}
		} while ( $running > 0 );

		foreach ( $handles as $url => $ch ) {
			$body  = curl_multi_getcontent( $ch );
			$image = $body ? $this->parse_og_image( $body ) : '';
			if ( '' !== $image ) {
				$image = esc_url_raw( $this->to_absolute_url( $image, $url ) );
			}
			set_transient( $keys[ $url ], $image, self::CACHE_TTL );
			$results[ $url ] = $image;
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}

		curl_multi_close( $mh );

		return $results;
	}

	/**
	 * 記事HTMLを取得し、OGP 画像URLを抽出する。
	 *
	 * @param string $url 記事URL。
	 * @return string 画像URL。失敗・未検出なら ''。
	 */
	private function fetch_ogp_image( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'WordPress/RSS-Display',
				'headers'     => array(
					'Accept' => 'text/html,application/xhtml+xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return '';
		}

		$image = $this->parse_og_image( $body );
		if ( '' === $image ) {
			return '';
		}

		return esc_url_raw( $this->to_absolute_url( $image, $url ) );
	}

	/**
	 * DOMDocument + DOMXPath で og:image 等のメタタグを抽出する（正規表現不使用）。
	 *
	 * @param string $html HTML文字列。
	 * @return string 画像URL。未検出なら ''。
	 */
	private function parse_og_image( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();

		// 文字コードのヒントを付与して UTF-8 として読み込む。
		$loaded = $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $loaded ) {
			return '';
		}

		$xpath = new DOMXPath( $doc );

		// 優先順位順にメタタグを探索する。
		$queries = array(
			'//meta[@property="og:image"]/@content',
			'//meta[@property="og:image:url"]/@content',
			'//meta[@property="og:image:secure_url"]/@content',
			'//meta[@name="twitter:image"]/@content',
			'//meta[@name="twitter:image:src"]/@content',
		);

		foreach ( $queries as $query ) {
			$nodes = $xpath->query( $query );
			if ( $nodes && $nodes->length > 0 ) {
				$value = trim( $nodes->item( 0 )->nodeValue );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * 相対URL／プロトコル相対URLを基準URLで絶対URL化する。
	 *
	 * @param string $maybe_relative 対象URL（絶対・相対いずれも可）。
	 * @param string $base_url       基準となる記事URL。
	 * @return string
	 */
	private function to_absolute_url( $maybe_relative, $base_url ) {
		$maybe_relative = trim( $maybe_relative );

		if ( preg_match( '#^https?://#i', $maybe_relative ) ) {
			return $maybe_relative;
		}

		if ( 0 === strpos( $maybe_relative, '//' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			return ( $scheme ? $scheme : 'https' ) . ':' . $maybe_relative;
		}

		$parts = wp_parse_url( $base_url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $maybe_relative;
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		if ( 0 === strpos( $maybe_relative, '/' ) ) {
			return $origin . $maybe_relative;
		}

		$base_path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$dir       = preg_replace( '#/[^/]*$#', '/', $base_path );
		if ( '' === $dir || null === $dir ) {
			$dir = '/';
		}

		return $origin . $dir . $maybe_relative;
	}
}
