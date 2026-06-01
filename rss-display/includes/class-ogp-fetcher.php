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
	 * 記事URLから OGP 画像URLを取得する。
	 * 結果は1ヶ月キャッシュ。未検出（''）も negative cache として保存し、
	 * 同一URLへの再リクエストを抑制する。
	 *
	 * @param string $article_url 記事URL。
	 * @return string 画像URL。見つからなければ ''。
	 */
	public function get_image( $article_url ) {
		$article_url = trim( $article_url );
		if ( '' === $article_url ) {
			return '';
		}

		$key    = self::CACHE_PREFIX . md5( $article_url );
		$cached = get_transient( $key );

		// '' （negative cache）も「取得済み」として有効に扱う。
		// get_transient は未保存時のみ false を返すため === false で判定する。
		if ( false !== $cached ) {
			return is_string( $cached ) ? $cached : '';
		}

		$image = $this->fetch_ogp_image( $article_url );
		set_transient( $key, $image, self::CACHE_TTL );

		return $image;
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

		// 相対URL・プロトコル相対URLを絶対URLへ解決する。
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
	 * （URL文字列の正規化処理であり HTML パースではない）
	 *
	 * @param string $maybe_relative 対象URL（絶対・相対いずれも可）。
	 * @param string $base_url       基準となる記事URL。
	 * @return string
	 */
	private function to_absolute_url( $maybe_relative, $base_url ) {
		$maybe_relative = trim( $maybe_relative );

		// 既に絶対URL（http:// または https://）。
		if ( preg_match( '#^https?://#i', $maybe_relative ) ) {
			return $maybe_relative;
		}

		// プロトコル相対URL（//example.com/...）。
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

		// ルート相対（/path/to/image.png）。
		if ( 0 === strpos( $maybe_relative, '/' ) ) {
			return $origin . $maybe_relative;
		}

		// 相対パス（path/to/image.png）→ 基準URLのディレクトリに連結。
		$base_path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$dir       = preg_replace( '#/[^/]*$#', '/', $base_path );
		if ( '' === $dir || null === $dir ) {
			$dir = '/';
		}

		return $origin . $dir . $maybe_relative;
	}
}
