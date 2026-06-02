<?php
/**
 * 管理画面（設定 → RSS Display）と設定保存を担当するクラス。
 *
 * @package RSS_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSS_D_Admin {

	/**
	 * フィードマネージャ。
	 *
	 * @var RSS_D_Feed_Manager
	 */
	private $feed_manager;

	/** 設定ページのスラッグ */
	private $page_slug = 'rss-display';

	/**
	 * コンストラクタ。
	 *
	 * @param RSS_D_Feed_Manager $feed_manager フィードマネージャ。
	 */
	public function __construct( $feed_manager ) {
		$this->feed_manager = $feed_manager;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_rss_d_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_rss_d_preview', array( $this, 'ajax_preview' ) );
	}

	/**
	 * 設定メニューを追加する（設定 → RSS Display）。
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'RSS Display', 'rss-display' ),
			__( 'RSS Display', 'rss-display' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * 設定を登録する。
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'rss_d_group',
			RSS_D_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => RSS_Display::default_settings(),
			)
		);
	}

	/**
	 * 設定値のサニタイズ。
	 *
	 * @param array $input フォーム入力値。
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults  = RSS_Display::default_settings();
		$out       = array();
		$is_paying = rss_display_fs_is_paying();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		// フィードURL（1行1URL）。
		$feeds_raw   = isset( $input['feeds'] ) ? (string) $input['feeds'] : '';
		$lines       = preg_split( '/\r\n|\r|\n/', $feeds_raw );
		$clean_lines = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$url = esc_url_raw( $line );
			if ( '' !== $url ) {
				$clean_lines[] = $url;
			}
		}
		if ( ! $is_paying && count( $clean_lines ) > RSS_D_FREE_MAX_FEEDS ) {
			$clean_lines = array_slice( $clean_lines, 0, RSS_D_FREE_MAX_FEEDS );
			add_settings_error(
				RSS_D_OPTION,
				'rss_d_feeds_capped',
				sprintf( '無料プランは最大%d件のフィードURLです。Proプランで無制限に利用できます。', RSS_D_FREE_MAX_FEEDS ),
				'warning'
			);
		}
		$out['feeds'] = implode( "\n", $clean_lines );

		// 表示件数（1〜100、無料は最大20件）。
		$count        = isset( $input['count'] ) ? absint( $input['count'] ) : $defaults['count'];
		$out['count'] = $count > 0 ? min( $count, 100 ) : $defaults['count'];
		if ( ! $is_paying && $out['count'] > RSS_D_FREE_MAX_COUNT ) {
			$out['count'] = RSS_D_FREE_MAX_COUNT;
			add_settings_error(
				RSS_D_OPTION,
				'rss_d_count_capped',
				sprintf( '無料プランは最大%d件までです。Proプランで100件まで利用できます。', RSS_D_FREE_MAX_COUNT ),
				'warning'
			);
		}

		// 列数（2/3/4）。
		$columns        = isset( $input['columns'] ) ? (int) $input['columns'] : $defaults['columns'];
		$out['columns'] = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : $defaults['columns'];
		if ( ! $is_paying && $out['columns'] > RSS_D_FREE_MAX_COLUMNS ) {
			$out['columns'] = RSS_D_FREE_MAX_COLUMNS;
			add_settings_error( RSS_D_OPTION, 'rss_d_columns_capped', '3列・4列はProプランが必要です。', 'warning' );
		}

		// タイトル最大行数（1/2/3）。
		$tl                 = isset( $input['title_lines'] ) ? (int) $input['title_lines'] : $defaults['title_lines'];
		$out['title_lines'] = in_array( $tl, array( 1, 2, 3 ), true ) ? $tl : $defaults['title_lines'];

		// キャッシュ時間（無料は1日固定）。
		$allowed_ttl = array( 43200, 86400, 604800, 2592000 );
		$ttl         = isset( $input['cache_ttl'] ) ? (int) $input['cache_ttl'] : $defaults['cache_ttl'];
		if ( ! $is_paying ) {
			$out['cache_ttl'] = RSS_D_FREE_CACHE_TTL;
		} else {
			$out['cache_ttl'] = in_array( $ttl, $allowed_ttl, true ) ? $ttl : $defaults['cache_ttl'];
		}

		// デフォルト画像。
		$out['default_image_id']  = isset( $input['default_image_id'] ) ? absint( $input['default_image_id'] ) : 0;
		$out['default_image_url'] = isset( $input['default_image_url'] ) ? esc_url_raw( trim( $input['default_image_url'] ) ) : '';

		// リンクを別タブで開く。
		$out['link_new_tab'] = ! empty( $input['link_new_tab'] ) ? 1 : 0;

		// 表示タイプ（デフォルト）。
		$allowed_types = array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid' );
		$type          = isset( $input['type'] ) ? (string) $input['type'] : $defaults['type'];
		if ( ! $is_paying && ! in_array( $type, RSS_D_FREE_TYPES, true ) ) {
			$type = $defaults['type'];
		}
		$out['type'] = in_array( $type, $allowed_types, true ) ? $type : $defaults['type'];

		// ソート順。
		$orderby = isset( $input['orderby'] ) ? (string) $input['orderby'] : $defaults['orderby'];
		if ( ! $is_paying ) {
			$orderby = 'date';
		}
		$out['orderby'] = in_array( $orderby, array( 'date', 'random' ), true ) ? $orderby : 'date';

		// 表示オプション。
		$out['show_desc'] = ! empty( $input['show_desc'] ) ? 1 : 0;
		$out['show_date'] = isset( $input['show_date'] ) ? ( '0' === (string) $input['show_date'] ? 0 : 1 ) : 1;
		$out['show_site'] = ! empty( $input['show_site'] ) ? 1 : 0;

		return $out;
	}

	/**
	 * 管理画面アセットの読み込み（設定ページのみ）。
	 *
	 * @param string $hook 現在の管理画面フック。
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . $this->page_slug !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'rss-d-admin', RSS_D_URL . 'assets/css/admin.css', array(), RSS_D_VERSION );
		wp_enqueue_style( 'rss-display', RSS_D_URL . 'assets/css/rss-display.css', array(), RSS_D_VERSION );
		wp_enqueue_script( 'rss-d-admin', RSS_D_URL . 'assets/js/admin.js', array( 'jquery' ), RSS_D_VERSION, true );
		wp_localize_script(
			'rss-d-admin',
			'rssDAdmin',
			array(
				'chooseTitle'  => __( 'デフォルト画像を選択', 'rss-display' ),
				'chooseButton' => __( 'この画像を使う', 'rss-display' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'rss_d_preview' ),
				'isPaying'     => $is_paying,
				'upgradeUrl'   => $upgrade_url,
				'proTypes'     => array_values( array_diff( array_keys( $types ), RSS_D_FREE_TYPES ) ),
				'proMsg'       => __( 'このオプションはProプランが必要です。', 'rss-display' ),
			)
		);
	}

	/**
	 * 「今すぐ更新」ボタンの処理（RSSキャッシュのみクリア）。
	 *
	 * @return void
	 */
	public function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '権限がありません。', 'rss-display' ) );
		}

		check_admin_referer( 'rss_d_refresh' );

		$settings = RSS_Display::get_settings();
		$feeds    = RSS_Display::parse_feeds( $settings['feeds'] );
		$this->feed_manager->clear_feed_cache( $feeds );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => $this->page_slug,
					'rss_d_refreshed' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * AJAXプレビュー：ショートコードをレンダリングしてHTMLを返す。
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'rss_d_preview' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// 許可パラメータのみ受け取る。
		$allowed_types = array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid' );
		$type    = isset( $_POST['type'] ) && in_array( $_POST['type'], $allowed_types, true ) ? sanitize_key( $_POST['type'] ) : 'grid'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce確認済み。
		$columns = isset( $_POST['columns'] ) ? (int) $_POST['columns'] : 3; // phpcs:ignore
		$columns = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : 3;
		$count   = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 6; // phpcs:ignore
		$count   = min( max( 1, $count ), RSS_D_FREE_MAX_COUNT );

		// do_shortcode 経由でレンダリングする。グローバル設定はそのまま使われ、
		// type/columns/count のみショートコード属性で上書きする。
		$html = do_shortcode(
			sprintf(
				'[rss_display type="%s" columns="%d" count="%d"]',
				esc_attr( $type ),
				$columns,
				$count
			)
		);

		if ( '' === $html ) {
			$html = '<p style="padding:2em;color:#888;">' . esc_html__( 'フィードからアイテムを取得できませんでした。フィードURLが登録済みか確認してください。', 'rss-display' ) . '</p>';
		}

		wp_send_json_success(
			array(
				'html'   => $html,
				'js_url' => RSS_D_URL . 'assets/js/rss-display.js',
			)
		);
	}

	/**
	 * 設定ページのHTMLを描画する。
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = RSS_Display::get_settings();
		$feeds    = RSS_Display::parse_feeds( $settings['feeds'] );

		$default_preview = '';
		if ( ! empty( $settings['default_image_id'] ) ) {
			$default_preview = wp_get_attachment_image_url( (int) $settings['default_image_id'], 'medium' );
		}
		if ( ! $default_preview && ! empty( $settings['default_image_url'] ) ) {
			$default_preview = $settings['default_image_url'];
		}

		$option      = RSS_D_OPTION;
		$is_paying   = rss_display_fs_is_paying();
		$fs          = rss_display_fs();
		$upgrade_url = ( ! $is_paying && $fs ) ? $fs->get_upgrade_url() : '#';

		// タイプ一覧（表示設定・プレビュー両タブで共有）。
		$types = array(
			'grid'         => 'grid — 全面背景画像＋タイトルオーバーレイ',
			'list_vertical'=> 'list_vertical — 画像上・テキスト下カード',
			'text'         => 'text — テキストのみカード（説明文あり）',
			'text_line'    => 'text_line — 1行テキスト・区切り線リスト',
			'image_only'   => 'image_only — 画像のみ (Pro)',
			'list'         => 'list — 横並び（サムネイル＋テキスト）(Pro)',
			'carousel'     => 'carousel — カルーセルスライダー (Pro)',
			'popup_grid'   => 'popup_grid — グリッド＋ポップアップ (Pro)',
		);
		?>
		<div class="wrap rss-d-admin">
			<h1><?php echo esc_html__( 'RSS Display 設定', 'rss-display' ); ?></h1>

			<?php if ( isset( $_GET['rss_d_refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'RSSキャッシュをクリアしました。次回表示時に再取得されます（OGP画像キャッシュは保持されます）。', 'rss-display' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- タブナビゲーション -->
			<nav class="rss-d-tabs" role="tablist">
				<button class="rss-d-tab active" role="tab" aria-selected="true"  data-tab="basic"><?php esc_html_e( '基本設定', 'rss-display' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="display"><?php esc_html_e( '表示設定', 'rss-display' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="preview"><?php esc_html_e( 'プレビュー', 'rss-display' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="status"><?php esc_html_e( 'フィード状態', 'rss-display' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="usage"><?php esc_html_e( '使い方', 'rss-display' ); ?></button>
			</nav>

			<form method="post" action="options.php" id="rss-d-settings-form">
				<?php settings_fields( 'rss_d_group' ); ?>

				<!-- ========== 基本設定タブ ========== -->
				<div class="rss-d-tab-panel active" data-panel="basic">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="rss_d_feeds"><?php esc_html_e( 'RSSフィードURL一覧', 'rss-display' ); ?></label>
							</th>
							<td>
								<textarea id="rss_d_feeds" name="<?php echo esc_attr( $option ); ?>[feeds]" rows="6" class="large-text code" placeholder="https://example.com/feed&#10;https://example.org/feed"><?php echo esc_textarea( $settings['feeds'] ); ?></textarea>
								<p class="description"><?php esc_html_e( '1行に1つのフィードURLを入力してください。', 'rss-display' ); ?></p>
								<?php if ( ! $is_paying ) : ?>
									<p class="description" style="color:#b32d2e;">
										<?php
										printf(
											wp_kses( '無料プランは最大%1$d件まで。<a href="%2$s">Proプラン</a>で無制限に利用できます。', array( 'a' => array( 'href' => array() ) ) ),
											RSS_D_FREE_MAX_FEEDS,
											esc_url( $upgrade_url )
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_cache_ttl"><?php esc_html_e( 'キャッシュ時間', 'rss-display' ); ?></label>
							</th>
							<td>
								<select id="rss_d_cache_ttl" name="<?php echo esc_attr( $option ); ?>[cache_ttl]" <?php disabled( ! $is_paying ); ?>>
									<option value="43200"  <?php selected( $settings['cache_ttl'], 43200 ); ?> <?php disabled( ! $is_paying ); ?>><?php esc_html_e( '12時間', 'rss-display' ); ?><?php echo ! $is_paying ? ' (Pro)' : ''; ?></option>
									<option value="86400"  <?php selected( $settings['cache_ttl'], 86400 ); ?>><?php esc_html_e( '1日', 'rss-display' ); ?></option>
									<option value="604800" <?php selected( $settings['cache_ttl'], 604800 ); ?> <?php disabled( ! $is_paying ); ?>><?php esc_html_e( '1週間', 'rss-display' ); ?><?php echo ! $is_paying ? ' (Pro)' : ''; ?></option>
									<option value="2592000"<?php selected( $settings['cache_ttl'], 2592000 ); ?> <?php disabled( ! $is_paying ); ?>><?php esc_html_e( '1ヶ月', 'rss-display' ); ?><?php echo ! $is_paying ? ' (Pro)' : ''; ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'RSSフィードの再取得間隔です。OGP画像のキャッシュは1ヶ月固定です。', 'rss-display' ); ?>
									<?php if ( ! $is_paying ) : ?>
										<br>
										<?php
										printf(
											wp_kses( '無料プランは1日固定です。<a href="%s">Proプラン</a>で変更できます。', array( 'a' => array( 'href' => array() ) ) ),
											esc_url( $upgrade_url )
										);
										?>
									<?php endif; ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'デフォルト画像', 'rss-display' ); ?></th>
							<td>
								<div class="rss-d-default-image">
									<input type="hidden" id="rss_d_default_image_id" name="<?php echo esc_attr( $option ); ?>[default_image_id]" value="<?php echo esc_attr( $settings['default_image_id'] ); ?>" />
									<div class="rss-d-image-preview">
										<?php if ( $default_preview ) : ?>
											<img src="<?php echo esc_url( $default_preview ); ?>" alt="" />
										<?php endif; ?>
									</div>
									<p>
										<button type="button" class="button" id="rss_d_select_image"><?php esc_html_e( 'メディアライブラリから選択', 'rss-display' ); ?></button>
										<button type="button" class="button" id="rss_d_clear_image"><?php esc_html_e( '選択を解除', 'rss-display' ); ?></button>
									</p>
									<p>
										<label for="rss_d_default_image_url"><?php esc_html_e( 'またはURLで直接指定:', 'rss-display' ); ?></label><br />
										<input type="url" id="rss_d_default_image_url" name="<?php echo esc_attr( $option ); ?>[default_image_url]" value="<?php echo esc_attr( $settings['default_image_url'] ); ?>" class="regular-text" placeholder="https://example.com/default.png" />
									</p>
									<p class="description"><?php esc_html_e( 'メディアライブラリの選択が優先されます。両方未設定の場合はプラグイン同梱のプレースホルダー画像が使われます。', 'rss-display' ); ?></p>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'リンクの開き方', 'rss-display' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[link_new_tab]" value="1" <?php checked( ! empty( $settings['link_new_tab'] ) ); ?> />
									<?php esc_html_e( 'リンクを別タブで開く', 'rss-display' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>

					<hr />

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em;">
						<input type="hidden" name="action" value="rss_d_refresh" />
						<?php wp_nonce_field( 'rss_d_refresh' ); ?>
						<?php submit_button( __( '今すぐ更新（RSSキャッシュをクリア）', 'rss-display' ), 'secondary', 'submit', false ); ?>
						<span class="description"><?php esc_html_e( 'OGP画像キャッシュはクリアされません。', 'rss-display' ); ?></span>
					</form>

				</div><!-- /basic -->

				<!-- ========== 表示設定タブ ========== -->
				<div class="rss-d-tab-panel" data-panel="display">

					<p class="description" style="margin:1em 0;"><?php esc_html_e( 'ショートコードで個別指定しない場合に使われるデフォルト値です。', 'rss-display' ); ?></p>

					<table class="form-table" role="presentation">

						<tr>
							<th scope="row">
								<label for="rss_d_type"><?php esc_html_e( '表示タイプ', 'rss-display' ); ?></label>
							</th>
							<td>
								<select id="rss_d_type" name="<?php echo esc_attr( $option ); ?>[type]">
									<?php foreach ( $types as $val => $label ) :
										$locked = ! $is_paying && ! in_array( $val, RSS_D_FREE_TYPES, true );
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['type'], $val ); ?> <?php disabled( $locked ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_columns"><?php esc_html_e( 'グリッド列数', 'rss-display' ); ?></label>
							</th>
							<td>
								<select id="rss_d_columns" name="<?php echo esc_attr( $option ); ?>[columns]">
									<?php foreach ( array( 2, 3, 4 ) as $c ) :
										$is_locked = ( ! $is_paying && $c > RSS_D_FREE_MAX_COLUMNS );
									?>
										<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $settings['columns'], $c ); ?> <?php disabled( $is_locked ); ?>><?php echo esc_html( $c . '列' . ( $is_locked ? '（Pro）' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php if ( ! $is_paying ) : ?>
									<p class="description">
										<?php printf( wp_kses( '3列・4列は <a href="%s">Proプラン</a> で利用できます。', array( 'a' => array( 'href' => array() ) ) ), esc_url( $upgrade_url ) ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_count"><?php esc_html_e( '表示件数', 'rss-display' ); ?></label>
							</th>
							<td>
								<input type="number" id="rss_d_count" name="<?php echo esc_attr( $option ); ?>[count]" value="<?php echo esc_attr( $settings['count'] ); ?>" min="1" max="<?php echo $is_paying ? '100' : RSS_D_FREE_MAX_COUNT; ?>" class="small-text" />
								<?php if ( ! $is_paying ) : ?>
									<p class="description">
										<?php printf( wp_kses( '無料プランは最大%1$d件まで。<a href="%2$s">Proプラン</a>で100件まで利用できます。', array( 'a' => array( 'href' => array() ) ) ), RSS_D_FREE_MAX_COUNT, esc_url( $upgrade_url ) ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_orderby"><?php esc_html_e( 'ソート順', 'rss-display' ); ?></label>
							</th>
							<td>
								<select id="rss_d_orderby" name="<?php echo esc_attr( $option ); ?>[orderby]" <?php disabled( ! $is_paying ); ?>>
									<option value="date"   <?php selected( $settings['orderby'], 'date' ); ?>><?php esc_html_e( '日付順（新しい順）', 'rss-display' ); ?></option>
									<option value="random" <?php selected( $settings['orderby'], 'random' ); ?> <?php disabled( ! $is_paying ); ?>><?php esc_html_e( 'ランダム (Pro)', 'rss-display' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_title_lines"><?php esc_html_e( 'タイトル最大行数', 'rss-display' ); ?></label>
							</th>
							<td>
								<select id="rss_d_title_lines" name="<?php echo esc_attr( $option ); ?>[title_lines]">
									<?php foreach ( array( 1, 2, 3 ) as $l ) : ?>
										<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $settings['title_lines'], $l ); ?>><?php echo esc_html( $l . '行' ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( '表示オプション', 'rss-display' ); ?></th>
							<td>
								<fieldset>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_date]" value="1" <?php checked( ! empty( $settings['show_date'] ) ); ?> />
										<?php esc_html_e( '日付を表示', 'rss-display' ); ?>
									</label>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_site]" value="1" <?php checked( ! empty( $settings['show_site'] ) ); ?> />
										<?php esc_html_e( 'サイト名を表示', 'rss-display' ); ?>
									</label>
									<label style="display:block;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_desc]" value="1" <?php checked( ! empty( $settings['show_desc'] ) ); ?> />
										<?php esc_html_e( '説明文を表示', 'rss-display' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

					</table>

					<?php submit_button(); ?>

				</div><!-- /display -->

				<!-- ========== プレビュータブ ========== -->
				<div class="rss-d-tab-panel" data-panel="preview">

					<div class="rss-d-preview-toolbar">
						<!-- デバイス幅切替 -->
						<div class="rss-d-preview-devices">
							<button type="button" class="rss-d-device-btn active" data-width="100%" title="デスクトップ">
								<span class="dashicons dashicons-desktop"></span>
							</button>
							<button type="button" class="rss-d-device-btn" data-width="768px" title="タブレット">
								<span class="dashicons dashicons-tablet"></span>
							</button>
							<button type="button" class="rss-d-device-btn" data-width="375px" title="スマートフォン">
								<span class="dashicons dashicons-smartphone"></span>
							</button>
						</div>

						<!-- 表示タイプ切替 -->
						<label><?php esc_html_e( 'タイプ:', 'rss-display' ); ?>
							<select id="rss-d-preview-type">
								<?php foreach ( $types as $val => $label ) :
									$locked = ! $is_paying && ! in_array( $val, RSS_D_FREE_TYPES, true );
								?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php disabled( $locked ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<!-- 列数切替 -->
						<label><?php esc_html_e( '列:', 'rss-display' ); ?>
							<select id="rss-d-preview-columns">
								<?php foreach ( array( 2, 3, 4 ) as $c ) :
									$locked = ! $is_paying && $c > RSS_D_FREE_MAX_COLUMNS;
								?>
									<option value="<?php echo esc_attr( $c ); ?>" <?php disabled( $locked ); ?>><?php echo esc_html( $c ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<!-- 件数切替 -->
						<label><?php esc_html_e( '件数:', 'rss-display' ); ?>
							<input type="number" id="rss-d-preview-count" value="6" min="1" max="20" style="width:60px;" />
						</label>

						<button type="button" id="rss-d-preview-btn" class="button button-primary"><?php esc_html_e( 'プレビュー更新', 'rss-display' ); ?></button>
					</div>

					<div class="rss-d-preview-frame-wrap">
						<div class="rss-d-preview-frame" id="rss-d-preview-frame">
							<p class="rss-d-preview-placeholder"><?php esc_html_e( '「プレビュー更新」ボタンを押すと実際の表示が確認できます。', 'rss-display' ); ?></p>
						</div>
					</div>

				</div><!-- /preview -->

			</form><!-- /form -->

			<!-- ========== フィード状態タブ ========== -->
			<div class="rss-d-tab-panel" data-panel="status">

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0;">
					<input type="hidden" name="action" value="rss_d_refresh" />
					<?php wp_nonce_field( 'rss_d_refresh' ); ?>
					<?php submit_button( __( '今すぐ更新（RSSキャッシュをクリア）', 'rss-display' ), 'secondary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'OGP画像キャッシュはクリアされません。', 'rss-display' ); ?></span>
				</form>

				<?php if ( empty( $feeds ) ) : ?>
					<p><?php esc_html_e( 'フィードがまだ登録されていません。', 'rss-display' ); ?></p>
				<?php else : ?>
					<table class="widefat striped rss-d-status-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'フィードURL', 'rss-display' ); ?></th>
								<th><?php esc_html_e( '最終取得時刻', 'rss-display' ); ?></th>
								<th><?php esc_html_e( '取得件数', 'rss-display' ); ?></th>
								<th><?php esc_html_e( '状態', 'rss-display' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
							foreach ( $feeds as $feed_url ) :
								$status = $this->feed_manager->get_feed_status( $feed_url );
								if ( ! $status['cached'] ) {
									$fetched_label = esc_html__( '未取得', 'rss-display' );
									$count_label   = '&mdash;';
									$state_label   = esc_html__( '未取得', 'rss-display' );
								} else {
									$fetched_label = esc_html( date_i18n( $datetime_format, $status['fetched'] ) );
									$count_label   = esc_html( (string) $status['count'] );
									$state_label   = $status['error']
										? esc_html__( '取得失敗（前回キャッシュを表示）', 'rss-display' )
										: esc_html__( '正常', 'rss-display' );
								}
							?>
								<tr>
									<td class="rss-d-status-url"><?php echo esc_html( $feed_url ); ?></td>
									<td><?php echo $fetched_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo $count_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo $state_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

			</div><!-- /status -->

			<!-- ========== 使い方タブ ========== -->
			<div class="rss-d-tab-panel" data-panel="usage">

				<!-- ショートコードジェネレーター -->
				<h2><?php esc_html_e( 'ショートコードジェネレーター', 'rss-display' ); ?></h2>
				<div class="rss-d-generator">

					<table class="form-table rss-d-generator-table" role="presentation">
						<tr>
							<th scope="row"><label for="rss-d-gen-feed"><?php esc_html_e( 'フィードURL', 'rss-display' ); ?></label></th>
							<td>
								<input type="text" id="rss-d-gen-feed" class="large-text" placeholder="https://example.com/feed, https://example.org/feed" />
								<p class="description"><?php esc_html_e( 'カンマ区切りで複数指定可。空欄の場合は管理画面登録済みフィードを使用。', 'rss-display' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rss-d-gen-type"><?php esc_html_e( '表示タイプ', 'rss-display' ); ?></label></th>
							<td>
								<select id="rss-d-gen-type">
									<?php foreach ( $types as $val => $label ) :
										$locked = ! $is_paying && ! in_array( $val, RSS_D_FREE_TYPES, true );
									?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php disabled( $locked ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rss-d-gen-columns"><?php esc_html_e( '列数', 'rss-display' ); ?></label></th>
							<td>
								<select id="rss-d-gen-columns">
									<?php foreach ( array( 2, 3, 4 ) as $c ) :
										$locked = ! $is_paying && $c > RSS_D_FREE_MAX_COLUMNS;
									?>
										<option value="<?php echo esc_attr( $c ); ?>" <?php disabled( $locked ); ?>><?php echo esc_html( $c . '列' . ( $locked ? '（Pro）' : '' ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rss-d-gen-count"><?php esc_html_e( '表示件数', 'rss-display' ); ?></label></th>
							<td>
								<input type="number" id="rss-d-gen-count" value="6" min="1" max="<?php echo $is_paying ? '100' : RSS_D_FREE_MAX_COUNT; ?>" class="small-text" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rss-d-gen-orderby"><?php esc_html_e( 'ソート順', 'rss-display' ); ?></label></th>
							<td>
								<select id="rss-d-gen-orderby">
									<option value=""><?php esc_html_e( '管理画面設定に従う', 'rss-display' ); ?></option>
									<option value="date"><?php esc_html_e( '日付順（新しい順）', 'rss-display' ); ?></option>
									<option value="random" <?php disabled( ! $is_paying ); ?>><?php esc_html_e( 'ランダム (Pro)', 'rss-display' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="rss-d-gen-target"><?php esc_html_e( 'リンクの開き方', 'rss-display' ); ?></label></th>
							<td>
								<select id="rss-d-gen-target">
									<option value=""><?php esc_html_e( '管理画面設定に従う', 'rss-display' ); ?></option>
									<option value="_blank"><?php esc_html_e( '別タブで開く', 'rss-display' ); ?></option>
									<option value="_self"><?php esc_html_e( '同じタブで開く', 'rss-display' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '表示オプション', 'rss-display' ); ?></th>
							<td>
								<fieldset>
									<label style="display:inline-block;margin-right:16px;">
										<input type="checkbox" id="rss-d-gen-date" checked />
										<?php esc_html_e( '日付', 'rss-display' ); ?>
									</label>
									<label style="display:inline-block;margin-right:16px;">
										<input type="checkbox" id="rss-d-gen-site" />
										<?php esc_html_e( 'サイト名', 'rss-display' ); ?>
									</label>
									<label style="display:inline-block;margin-right:16px;">
										<input type="checkbox" id="rss-d-gen-desc" />
										<?php esc_html_e( '説明文', 'rss-display' ); ?>
									</label>
									<label style="display:inline-block;">
										<input type="checkbox" id="rss-d-gen-bold" />
										<?php esc_html_e( 'タイトル太字', 'rss-display' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>

					<!-- 生成結果 -->
					<div class="rss-d-generator-output">
						<label for="rss-d-gen-result" style="font-weight:600;display:block;margin-bottom:6px;"><?php esc_html_e( '生成されたショートコード', 'rss-display' ); ?></label>
						<div class="rss-d-gen-result-wrap">
							<input type="text" id="rss-d-gen-result" class="large-text code" readonly />
							<button type="button" id="rss-d-gen-copy" class="button"><?php esc_html_e( 'コピー', 'rss-display' ); ?></button>
						</div>
						<p id="rss-d-gen-copy-msg" class="description" style="display:none;color:#00a32a;"><?php esc_html_e( 'コピーしました！', 'rss-display' ); ?></p>
					</div>

				</div><!-- /rss-d-generator -->

				<hr style="margin:2em 0;" />

				<!-- パラメータ早見表 -->
				<h2><?php esc_html_e( 'パラメータ一覧', 'rss-display' ); ?></h2>
				<table class="widefat striped" style="max-width:800px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'パラメータ', 'rss-display' ); ?></th>
							<th><?php esc_html_e( 'デフォルト', 'rss-display' ); ?></th>
							<th><?php esc_html_e( '説明', 'rss-display' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>feed</code></td><td>—</td><td><?php esc_html_e( 'フィードURL（カンマ区切りで複数可）', 'rss-display' ); ?></td></tr>
						<tr><td><code>type</code></td><td><code>grid</code></td><td><?php esc_html_e( '表示タイプ', 'rss-display' ); ?></td></tr>
						<tr><td><code>columns</code></td><td><code>3</code></td><td><?php esc_html_e( '列数 (2/3/4)', 'rss-display' ); ?></td></tr>
						<tr><td><code>count</code></td><td><code>6</code></td><td><?php esc_html_e( '表示件数', 'rss-display' ); ?></td></tr>
						<tr><td><code>orderby</code></td><td><code>date</code></td><td><?php esc_html_e( 'ソート順 (date/random)', 'rss-display' ); ?></td></tr>
						<tr><td><code>target</code></td><td>—</td><td><?php esc_html_e( 'リンクの開き方 (_blank/_self)', 'rss-display' ); ?></td></tr>
						<tr><td><code>date</code></td><td><code>1</code></td><td><?php esc_html_e( '日付表示 (1/0)', 'rss-display' ); ?></td></tr>
						<tr><td><code>site</code></td><td><code>0</code></td><td><?php esc_html_e( 'サイト名表示 (1/0)', 'rss-display' ); ?></td></tr>
						<tr><td><code>desc</code></td><td><code>0</code></td><td><?php esc_html_e( '説明文表示 (1/0)', 'rss-display' ); ?></td></tr>
						<tr><td><code>bold</code></td><td><code>0</code></td><td><?php esc_html_e( 'タイトル太字 (1/0)', 'rss-display' ); ?></td></tr>
						<tr><td><code>img</code></td><td>—</td><td><?php esc_html_e( 'デフォルト画像URLを上書き', 'rss-display' ); ?></td></tr>
					</tbody>
				</table>

			</div><!-- /usage -->

		</div><!-- /wrap -->
		<?php
	}
}
