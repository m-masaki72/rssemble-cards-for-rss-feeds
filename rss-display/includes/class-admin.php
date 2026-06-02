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
		$defaults = RSS_Display::default_settings();
		$out      = array();

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
		$out['feeds'] = implode( "\n", $clean_lines );

		// 表示件数（1〜100）。
		$count        = isset( $input['count'] ) ? absint( $input['count'] ) : $defaults['count'];
		$out['count'] = $count > 0 ? min( $count, 100 ) : $defaults['count'];

		// 列数（2/3/4）。
		$columns        = isset( $input['columns'] ) ? (int) $input['columns'] : $defaults['columns'];
		$out['columns'] = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : $defaults['columns'];

		// タイトル最大行数（1/2/3）。
		$tl                 = isset( $input['title_lines'] ) ? (int) $input['title_lines'] : $defaults['title_lines'];
		$out['title_lines'] = in_array( $tl, array( 1, 2, 3 ), true ) ? $tl : $defaults['title_lines'];

		// キャッシュ時間（許可された値のみ）。
		$allowed_ttl      = array( 43200, 86400, 604800, 2592000 );
		$ttl              = isset( $input['cache_ttl'] ) ? (int) $input['cache_ttl'] : $defaults['cache_ttl'];
		$out['cache_ttl'] = in_array( $ttl, $allowed_ttl, true ) ? $ttl : $defaults['cache_ttl'];

		// デフォルト画像。
		$out['default_image_id']  = isset( $input['default_image_id'] ) ? absint( $input['default_image_id'] ) : 0;
		$out['default_image_url'] = isset( $input['default_image_url'] ) ? esc_url_raw( trim( $input['default_image_url'] ) ) : '';

		// リンクを別タブで開く。
		$out['link_new_tab'] = ! empty( $input['link_new_tab'] ) ? 1 : 0;

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
		wp_enqueue_script( 'rss-d-admin', RSS_D_URL . 'assets/js/admin.js', array( 'jquery' ), RSS_D_VERSION, true );
		wp_localize_script(
			'rss-d-admin',
			'rssDAdmin',
			array(
				'chooseTitle'  => __( 'デフォルト画像を選択', 'rss-display' ),
				'chooseButton' => __( 'この画像を使う', 'rss-display' ),
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
		$feeds    = $this->parse_feeds( $settings['feeds'] );
		$this->feed_manager->clear_feed_cache( $feeds );

		$redirect = add_query_arg(
			array(
				'page'            => $this->page_slug,
				'rss_d_refreshed' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * テキストエリアの内容（1行1URL）をURL配列にパースする。
	 *
	 * @param string $raw 改行区切りのフィードURL文字列。
	 * @return array
	 */
	private function parse_feeds( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$feeds = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$feeds[] = $line;
			}
		}

		return $feeds;
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
		$feeds    = $this->parse_feeds( $settings['feeds'] );

		$default_preview = '';
		if ( ! empty( $settings['default_image_id'] ) ) {
			$default_preview = wp_get_attachment_image_url( (int) $settings['default_image_id'], 'medium' );
		}
		if ( ! $default_preview && ! empty( $settings['default_image_url'] ) ) {
			$default_preview = $settings['default_image_url'];
		}

		$option = RSS_D_OPTION;
		?>
		<div class="wrap rss-d-admin">
			<h1><?php echo esc_html__( 'RSS Display 設定', 'rss-display' ); ?></h1>

			<?php if ( isset( $_GET['rss_d_refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示用フラグのみ。 ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'RSSキャッシュをクリアしました。次回表示時に再取得されます（OGP画像キャッシュは保持されます）。', 'rss-display' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'rss_d_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="rss_d_feeds"><?php echo esc_html__( 'RSSフィードURL一覧', 'rss-display' ); ?></label>
						</th>
						<td>
							<textarea id="rss_d_feeds" name="<?php echo esc_attr( $option ); ?>[feeds]" rows="6" class="large-text code" placeholder="https://example.com/feed&#10;https://example.org/feed"><?php echo esc_textarea( $settings['feeds'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( '1行に1つのフィードURLを入力してください。', 'rss-display' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rss_d_count"><?php echo esc_html__( '表示件数', 'rss-display' ); ?></label>
						</th>
						<td>
							<input type="number" id="rss_d_count" name="<?php echo esc_attr( $option ); ?>[count]" value="<?php echo esc_attr( $settings['count'] ); ?>" min="1" max="100" class="small-text" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rss_d_columns"><?php echo esc_html__( 'グリッド列数', 'rss-display' ); ?></label>
						</th>
						<td>
							<select id="rss_d_columns" name="<?php echo esc_attr( $option ); ?>[columns]">
								<?php foreach ( array( 2, 3, 4 ) as $c ) : ?>
									<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $settings['columns'], $c ); ?>><?php echo esc_html( $c . '列' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rss_d_title_lines"><?php echo esc_html__( 'タイトル最大行数', 'rss-display' ); ?></label>
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
						<th scope="row">
							<label for="rss_d_cache_ttl"><?php echo esc_html__( 'キャッシュ時間', 'rss-display' ); ?></label>
						</th>
						<td>
							<select id="rss_d_cache_ttl" name="<?php echo esc_attr( $option ); ?>[cache_ttl]">
								<option value="43200" <?php selected( $settings['cache_ttl'], 43200 ); ?>><?php echo esc_html__( '12時間', 'rss-display' ); ?></option>
								<option value="86400" <?php selected( $settings['cache_ttl'], 86400 ); ?>><?php echo esc_html__( '1日', 'rss-display' ); ?></option>
								<option value="604800" <?php selected( $settings['cache_ttl'], 604800 ); ?>><?php echo esc_html__( '1週間', 'rss-display' ); ?></option>
								<option value="2592000" <?php selected( $settings['cache_ttl'], 2592000 ); ?>><?php echo esc_html__( '1ヶ月', 'rss-display' ); ?></option>
							</select>
							<p class="description"><?php echo esc_html__( 'RSSフィードの再取得間隔です。OGP画像のキャッシュは1ヶ月固定です。', 'rss-display' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__( 'デフォルト画像', 'rss-display' ); ?></th>
						<td>
							<div class="rss-d-default-image">
								<input type="hidden" id="rss_d_default_image_id" name="<?php echo esc_attr( $option ); ?>[default_image_id]" value="<?php echo esc_attr( $settings['default_image_id'] ); ?>" />
								<div class="rss-d-image-preview">
									<?php if ( $default_preview ) : ?>
										<img src="<?php echo esc_url( $default_preview ); ?>" alt="" />
									<?php endif; ?>
								</div>
								<p>
									<button type="button" class="button" id="rss_d_select_image"><?php echo esc_html__( 'メディアライブラリから選択', 'rss-display' ); ?></button>
									<button type="button" class="button" id="rss_d_clear_image"><?php echo esc_html__( '選択を解除', 'rss-display' ); ?></button>
								</p>
								<p>
									<label for="rss_d_default_image_url"><?php echo esc_html__( 'またはURLで直接指定:', 'rss-display' ); ?></label><br />
									<input type="url" id="rss_d_default_image_url" name="<?php echo esc_attr( $option ); ?>[default_image_url]" value="<?php echo esc_attr( $settings['default_image_url'] ); ?>" class="regular-text" placeholder="https://example.com/default.png" />
								</p>
								<p class="description"><?php echo esc_html__( 'メディアライブラリの選択が優先されます。両方未設定の場合はプラグイン同梱のプレースホルダー画像が使われます。', 'rss-display' ); ?></p>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php echo esc_html__( 'リンクの開き方', 'rss-display' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[link_new_tab]" value="1" <?php checked( ! empty( $settings['link_new_tab'] ) ); ?> />
								<?php echo esc_html__( 'リンクを別タブで開く', 'rss-display' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'フィード取得状態', 'rss-display' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em;">
				<input type="hidden" name="action" value="rss_d_refresh" />
				<?php wp_nonce_field( 'rss_d_refresh' ); ?>
				<?php submit_button( __( '今すぐ更新（RSSキャッシュをクリア）', 'rss-display' ), 'secondary', 'submit', false ); ?>
				<span class="description"><?php echo esc_html__( 'OGP画像キャッシュはクリアされません。', 'rss-display' ); ?></span>
			</form>

			<?php if ( empty( $feeds ) ) : ?>
				<p><?php echo esc_html__( 'フィードがまだ登録されていません。', 'rss-display' ); ?></p>
			<?php else : ?>
				<table class="widefat striped rss-d-status-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'フィードURL', 'rss-display' ); ?></th>
							<th><?php echo esc_html__( '最終取得時刻', 'rss-display' ); ?></th>
							<th><?php echo esc_html__( '取得件数', 'rss-display' ); ?></th>
							<th><?php echo esc_html__( '状態', 'rss-display' ); ?></th>
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
								<td><?php echo $fetched_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上でエスケープ済み。 ?></td>
								<td><?php echo $count_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上でエスケープ済み。 ?></td>
								<td><?php echo $state_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 上でエスケープ済み。 ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php echo esc_html__( 'ショートコードの使い方', 'rss-display' ); ?></h2>
			<p><code>[rss_display]</code> <?php echo esc_html__( '— 管理画面の設定値で表示', 'rss-display' ); ?></p>
			<p><code>[rss_display columns="4" count="8"]</code></p>
			<p><code>[rss_display columns="2" count="6" feed="https://example.com/feed"]</code></p>
			<p><code>[rss_display orderby="random" target="_self"]</code></p>
			<p><?php echo esc_html__( 'type オプション（デフォルト: grid）:', 'rss-display' ); ?></p>
			<ul style="margin-left:1.5em;list-style:disc;">
				<li><code>type="grid"</code> — <?php echo esc_html__( '全面背景画像＋タイトルオーバーレイ', 'rss-display' ); ?></li>
				<li><code>type="image_only"</code> — <?php echo esc_html__( '画像のみ（テキストなし）', 'rss-display' ); ?></li>
				<li><code>type="list"</code> — <?php echo esc_html__( '横並び（サムネイル＋テキスト）', 'rss-display' ); ?></li>
				<li><code>type="list_vertical"</code> — <?php echo esc_html__( '縦積み（画像上・テキスト下）', 'rss-display' ); ?></li>
				<li><code>type="text"</code> — <?php echo esc_html__( 'テキストのみカード（説明文あり）', 'rss-display' ); ?></li>
				<li><code>type="text_line"</code> — <?php echo esc_html__( '1行テキスト・区切り線リスト', 'rss-display' ); ?></li>
			</ul>
		</div>
		<?php
	}
}
