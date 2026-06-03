<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Fetches OGP image URLs from article pages and caches the results.
 *
 * Parallel fetching uses curl_multi. This bypasses the pre_http_request filter
 * but respects WP_HTTP_Proxy settings, https_ssl_verify filter, and the bundled
 * CA certificate. Environments without curl_multi fall back to wp_remote_get.
 *
 * @package Gridify_Image_Cards_For_RSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and caches OGP image URLs from article pages.
 */
class RSS_D_OGP_Fetcher {

	/** Transient key prefix. */
	const CACHE_PREFIX = 'rss_d_ogp_';

	/** OGP cache duration (fixed 1 month). */
	const CACHE_TTL = MONTH_IN_SECONDS;

	/** HTTP timeout in seconds. */
	const TIMEOUT = 5;

	/**
	 * Returns the OGP image URL for a single article URL.
	 *
	 * @param string $article_url Article URL.
	 * @return string Image URL, or '' if not found.
	 */
	public function get_image( $article_url ) {
		$map = $this->get_images( array( $article_url ) );
		$url = trim( $article_url );
		return isset( $map[ $url ] ) ? $map[ $url ] : '';
	}

	/**
	 * Returns OGP image URLs for multiple article URLs.
	 * Cached URLs are skipped; uncached ones are fetched in parallel via curl_multi
	 * (or serially via wp_remote_get if curl_multi is unavailable).
	 *
	 * @param string[] $urls Article URLs.
	 * @return array Map of URL => image URL ('' if not found).
	 */
	public function get_images( $urls ) {
		$results  = array();
		$to_fetch = array();
		$keys     = array(); // Avoids repeated md5() calls.

		$seen = array();
		foreach ( $urls as $url ) {
			$url = trim( $url );
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$keys[ $url ] = self::CACHE_PREFIX . md5( $url );
			$cached       = get_transient( $keys[ $url ] );
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
			// Serial fallback when curl_multi is unavailable.
			foreach ( $to_fetch as $url ) {
				$image = $this->fetch_ogp_image( $url );
				set_transient( $keys[ $url ], $image, self::CACHE_TTL );
				$results[ $url ] = $image;
			}
			return $results;
		}

		// Parallel requests via curl_multi.
		// Note: WordPress HTTP filters (pre_http_request etc.) are bypassed here.
		$mh      = curl_multi_init(); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_init
		$handles = array();

		$ssl_verify = (bool) apply_filters( 'https_ssl_verify', true );
		$ca_cert    = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
		$user_agent = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' );
		$proxy      = new WP_HTTP_Proxy();

		foreach ( $to_fetch as $url ) {
			$ch = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
			if ( false === $ch ) {
				$results[ $url ] = '';
				continue;
			}
			curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
				$ch,
				array(
					CURLOPT_RETURNTRANSFER   => true,
					CURLOPT_FOLLOWLOCATION   => true,
					CURLOPT_MAXREDIRS        => 3,
					CURLOPT_TIMEOUT          => self::TIMEOUT,
					CURLOPT_SSL_VERIFYPEER   => $ssl_verify,
					CURLOPT_SSL_VERIFYHOST   => $ssl_verify ? 2 : 0,
					CURLOPT_USERAGENT        => $user_agent,
					CURLOPT_HTTPHEADER       => array( 'Accept: text/html,application/xhtml+xml' ),
					CURLOPT_PROTOCOLS        => CURLPROTO_HTTP | CURLPROTO_HTTPS,
					CURLOPT_REDIR_PROTOCOLS  => CURLPROTO_HTTP | CURLPROTO_HTTPS,
					// Abort after 128 KB — enough to capture the <head>.
					CURLOPT_BUFFERSIZE       => 131072,
					CURLOPT_NOPROGRESS       => false,
					CURLOPT_PROGRESSFUNCTION => static function ( $resource, $dl_total, $dl_now, $ul_total, $ul_now ) {
						return $dl_now > 131072 ? 1 : 0;
					},
				)
			);

			// Apply WordPress-bundled CA certificate.
			if ( $ssl_verify && file_exists( $ca_cert ) ) {
				curl_setopt( $ch, CURLOPT_CAINFO, $ca_cert ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
			}

			// Apply WordPress proxy settings to curl.
			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) {
				curl_setopt( $ch, CURLOPT_PROXY, $proxy->host() . ':' . $proxy->port() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				if ( $proxy->use_authentication() ) {
					curl_setopt( $ch, CURLOPT_PROXYUSERPWD, $proxy->authentication() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
				}
			}

			curl_multi_add_handle( $mh, $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_add_handle
			$handles[ $url ] = $ch;
		}

		// Wait for all requests to complete, with a wall-clock deadline to prevent infinite loops.
		$running  = null;
		$deadline = microtime( true ) + self::TIMEOUT + 2;
		do {
			curl_multi_exec( $mh, $running ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_exec
			if ( $running > 0 && -1 === curl_multi_select( $mh ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_select
				usleep( 100000 );
			}
		} while ( $running > 0 && microtime( true ) < $deadline );

		foreach ( $handles as $url => $ch ) {
			$body  = curl_multi_getcontent( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_getcontent
			$image = $body ? $this->parse_og_image( $body ) : '';
			if ( '' !== $image ) {
				$image = esc_url_raw( $this->to_absolute_url( $image, $url ) );
			}
			set_transient( $keys[ $url ], $image, self::CACHE_TTL );
			$results[ $url ] = $image;
			curl_multi_remove_handle( $mh, $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_remove_handle
			curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
		}

		curl_multi_close( $mh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_multi_close

		return $results;
	}

	/**
	 * Fetches an article page and extracts its OGP image URL.
	 *
	 * @param string $url Article URL.
	 * @return string Image URL, or '' on failure.
	 */
	private function fetch_ogp_image( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 3,
				'sslverify'   => (bool) apply_filters( 'https_ssl_verify', true ),
				'user-agent'  => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
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
	 * Parses OGP/Twitter meta tags from HTML using DOMDocument + DOMXPath.
	 *
	 * @param string $html HTML string.
	 * @return string Image URL, or '' if not found.
	 */
	private function parse_og_image( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument();

		// Prepend XML encoding hint to ensure UTF-8 parsing.
		$loaded = $doc->loadHTML( '<?xml encoding="UTF-8">' . $html );

		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( ! $loaded ) {
			return '';
		}

		$xpath = new DOMXPath( $doc );

		// Query meta tags in priority order.
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
	 * Converts a relative or protocol-relative URL to an absolute URL.
	 *
	 * @param string $maybe_relative Target URL (absolute or relative).
	 * @param string $base_url       Base article URL for resolution.
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
