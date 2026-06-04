<?php
/**
 * WordPress スタブ定義。WP環境なしでロジックをテストするためのモック。
 */

define( 'ABSPATH', '/fake/wp/' );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'RSS_D_OPTION', 'rss_d_settings' );
define( 'RSS_D_VERSION', '1.0.0' );
define( 'RSS_D_URL', 'http://localhost/wp-content/plugins/rssemble-cards-for-rss-feeds/' );

function esc_url_raw( $url ) { return $url; }
function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v ); }
function esc_html__( $text, $domain = 'default' ) { return $text; }
function esc_html_e( $text, $domain = 'default' ) { echo $text; }
function __( $text, $domain = 'default' ) { return $text; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function absint( $v ) { return abs( (int) $v ); }
function shortcode_atts( array $pairs, $atts, $shortcode = '' ): array {
	$atts = is_array( $atts ) ? $atts : [];
	$out  = [];
	foreach ( $pairs as $key => $default ) {
		$out[ $key ] = array_key_exists( $key, $atts ) ? $atts[ $key ] : $default;
	}
	return $out;
}
function add_shortcode( $tag, $cb ) {}
function add_action( $hook, $cb ) {}
function add_filter( $hook, $cb ) {}
function remove_filter( $hook, $cb ) {}
function wp_register_style() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_style_is() { return false; }
function wp_get_attachment_image_url() { return false; }
function get_option( $k, $d = '' ) {
	if ( 'date_format' === $k ) { return 'Y-m-d'; }
	return $d;
}
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $ttl ) {}
function delete_transient( $k ) {}
function date_i18n( $fmt, $ts ) { return date( $fmt, $ts ); }
function fetch_feed( $url ) { return new WP_Error( 'stub', 'stub' ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_singular() { return false; }
function get_post() { return null; }
function has_shortcode() { return false; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_remote_get() { return new WP_Error( 'stub', 'stub' ); }
function wp_remote_retrieve_body( $r ) { return ''; }

class WP_Error {
	public function __construct( $code = '', $msg = '' ) {}
}

class RSS_Display {
	public static function get_settings() {
		return array(
			'feeds'             => '',
			'count'             => 10,
			'columns'           => 3,
			'title_lines'       => 2,
			'cache_ttl'         => DAY_IN_SECONDS,
			'default_image_id'  => 0,
			'default_image_url' => '',
			'link_new_tab'      => 1,
			'type'              => 'grid',
			'orderby'           => 'date',
			'show_desc'         => 0,
			'show_date'         => 1,
			'show_site'         => 0,
		);
	}

	public static function allowed_types() {
		return array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid' );
	}

	public static function parse_feeds( $raw ) {
		$feeds = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$feeds[] = $line;
			}
		}
		return $feeds;
	}
}

require_once __DIR__ . '/../rssemble-cards-for-rss-feeds/includes/class-feed-manager.php';
require_once __DIR__ . '/../rssemble-cards-for-rss-feeds/includes/class-ogp-fetcher.php';
require_once __DIR__ . '/../rssemble-cards-for-rss-feeds/includes/class-shortcode.php';
