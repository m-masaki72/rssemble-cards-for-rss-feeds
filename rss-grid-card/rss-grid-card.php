<?php
/**
 * Plugin Name:       RSS Grid Card
 * Plugin URI:        https://example.com/rss-grid-card
 * Description:        複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するプラグイン。外部サービス依存なし。
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            森
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rss-grid-card
 * Domain Path:       /languages
 *
 * @package RSS_Grid_Card
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RSS_GC_VERSION', '1.0.0' );
define( 'RSS_GC_FILE', __FILE__ );
define( 'RSS_GC_DIR', plugin_dir_path( __FILE__ ) );
define( 'RSS_GC_URL', plugin_dir_url( __FILE__ ) );
define( 'RSS_GC_OPTION', 'rss_gc_settings' );

require_once RSS_GC_DIR . 'includes/class-feed-manager.php';
require_once RSS_GC_DIR . 'includes/class-ogp-fetcher.php';
require_once RSS_GC_DIR . 'includes/class-shortcode.php';
require_once RSS_GC_DIR . 'includes/class-admin.php';

/**
 * プラグイン本体（シングルトン）。
 */
final class RSS_Grid_Card {

	/**
	 * シングルトンインスタンス。
	 *
	 * @var RSS_Grid_Card|null
	 */
	private static $instance = null;

	/**
	 * フィードマネージャ。
	 *
	 * @var RSS_GC_Feed_Manager
	 */
	public $feed_manager;

	/**
	 * OGP 取得クラス。
	 *
	 * @var RSS_GC_OGP_Fetcher
	 */
	public $ogp_fetcher;

	/**
	 * ショートコード処理クラス。
	 *
	 * @var RSS_GC_Shortcode
	 */
	public $shortcode;

	/**
	 * 管理画面クラス。
	 *
	 * @var RSS_GC_Admin|null
	 */
	public $admin = null;

	/**
	 * インスタンスを取得する。
	 *
	 * @return RSS_Grid_Card
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
		$this->ogp_fetcher  = new RSS_GC_OGP_Fetcher();
		$this->feed_manager = new RSS_GC_Feed_Manager( $this->ogp_fetcher );
		$this->shortcode    = new RSS_GC_Shortcode( $this->feed_manager, $this->ogp_fetcher );

		if ( is_admin() ) {
			$this->admin = new RSS_GC_Admin( $this->feed_manager );
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * 翻訳ファイルを読み込む。
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'rss-grid-card',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * デフォルト設定値。
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'feeds'             => '',
			'count'             => 12,
			'columns'           => 3,
			'title_lines'       => 2,
			'cache_ttl'         => 86400, // 1日。
			'default_image_id'  => 0,
			'default_image_url' => '',
			'link_new_tab'      => 1,
		);
	}

	/**
	 * 保存済み設定をデフォルトとマージして取得する。
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( RSS_GC_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::default_settings() );
	}

	/**
	 * アンインストール時のクリーンアップ。
	 * 設定オプションを削除する（トランジェントは自然失効に任せる）。
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( RSS_GC_OPTION );
	}
}

register_uninstall_hook( __FILE__, array( 'RSS_Grid_Card', 'uninstall' ) );

/**
 * プラグイン本体へのアクセサ。
 *
 * @return RSS_Grid_Card
 */
function rss_grid_card() {
	return RSS_Grid_Card::instance();
}

// アンインストール処理実行中は本体を起動しない。
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	rss_grid_card();
}
