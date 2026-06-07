<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name:       Rssemble Cards for RSS Feeds
 * Plugin URI:        https://rssemble-cards-for-rss-feeds.pages.dev/
 * Description:       Display multiple RSS feeds as OGP image card grids. No external service dependencies.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Masaki Mori
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rssemble-cards-for-rss-feeds
 * Domain Path:       /languages
 *
 * @package Rssemble_Cards_For_RSS_Feeds
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RSSECAFO_VERSION', '1.0.0' );
define( 'RSSECAFO_FILE', __FILE__ );
define( 'RSSECAFO_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSSECAFO_URL', plugin_dir_url( __FILE__ ) );
define( 'RSSECAFO_OPTION', 'rssecafo_settings' );


require_once RSSECAFO_DIR . 'includes/class-feed-manager.php';
require_once RSSECAFO_DIR . 'includes/class-ogp-fetcher.php';
require_once RSSECAFO_DIR . 'includes/class-shortcode.php';
require_once RSSECAFO_DIR . 'includes/class-admin.php';

/**
 * プラグイン本体（シングルトン）。
 */
final class RSSECAFO_Plugin {

	/**
	 * シングルトンインスタンス。
	 *
	 * @var RSSECAFO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * フィードマネージャ。
	 *
	 * @var RSSECAFO_Feed_Manager
	 */
	public $feed_manager;

	/**
	 * OGP 取得クラス。
	 *
	 * @var RSSECAFO_OGP_Fetcher
	 */
	public $ogp_fetcher;

	/**
	 * ショートコード処理クラス。
	 *
	 * @var RSSECAFO_Shortcode
	 */
	public $shortcode;

	/**
	 * 管理画面クラス。
	 *
	 * @var RSSECAFO_Admin|null
	 */
	public $admin = null;

	/**
	 * インスタンスを取得する。
	 *
	 * @return RSSECAFO_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * コンストラクタ。各コンポーネントを初期化する。
	 */
	private function __construct() {
		$this->ogp_fetcher  = new RSSECAFO_OGP_Fetcher();
		$this->feed_manager = new RSSECAFO_Feed_Manager();
		$this->shortcode    = new RSSECAFO_Shortcode( $this->feed_manager, $this->ogp_fetcher );

		if ( is_admin() ) {
			$this->admin = new RSSECAFO_Admin( $this->feed_manager );
		}

	}

	/**
	 * デフォルト設定値。
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'feeds'             => '',
			'count'             => 10,
			'columns'           => 3,
			'title_lines'       => 2,
			'cache_ttl'         => 86400,
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

	/**
	 * Parses a newline-separated feed URL string into an array of URLs.
	 *
	 * @param string $raw Newline-separated feed URL string.
	 * @return array
	 */
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

	/**
	 * 保存済み設定をデフォルトとマージして取得する。
	 *
	 * @return array
	 */
	public static function get_settings() {
		static $cache = null;
		if ( null === $cache ) {
			$saved = get_option( RSSECAFO_OPTION, array() );
			$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
		}
		return $cache;
	}

	/**
	 * Returns the list of valid display types.
	 *
	 * @return string[]
	 */
	public static function allowed_types() {
		return array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid' );
	}

	/**
	 * アンインストール時のクリーンアップ。
	 * 設定オプションを削除する（トランジェントは自然失効に任せる）。
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( RSSECAFO_OPTION );
	}
}

register_uninstall_hook( __FILE__, array( 'RSSECAFO_Plugin', 'uninstall' ) );

/**
 * プラグイン本体へのアクセサ。
 *
 * @return RSSECAFO_Plugin
 */
function rssecafo_plugin() { // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, Universal.Files.SeparateFunctionsFromOO.Mixed
	return RSSECAFO_Plugin::instance();
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_script(
			'rssemble-cards-for-rss-feeds',
			RSSECAFO_URL . 'assets/js/rssemble-cards-for-rss-feeds.js',
			array(),
			RSSECAFO_VERSION,
			true
		);
	}
);

// Do not boot the plugin during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	rssecafo_plugin();
}
