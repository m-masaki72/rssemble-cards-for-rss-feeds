<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Admin settings page (Settings → Gridify Image Cards).
 *
 * @package Gridify_Image_Cards_For_RSS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the admin settings page (Settings → Gridify Image Cards).
 */
class RSS_D_Admin {

	/**
	 * Feed manager instance.
	 *
	 * @var RSS_D_Feed_Manager
	 */
	private $feed_manager;

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'gridify-image-cards-for-rss';

	/**
	 * Constructor.
	 *
	 * @param RSS_D_Feed_Manager $feed_manager Feed manager instance.
	 */
	public function __construct( $feed_manager ) {
		$this->feed_manager = $feed_manager;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_rss_d_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_rss_d_refresh', array( $this, 'handle_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_rss_d_preview', array( $this, 'ajax_preview' ) );
		add_filter( 'plugin_action_links_gridify-image-cards-for-rss/gridify-image-cards-for-rss.php', array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add a "Settings" link to the plugin action links.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . $this->page_slug );
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Settings', 'gridify-image-cards-for-rss' ) ) );
		return $links;
	}

	/**
	 * Register settings submenu under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Gridify Image Cards', 'gridify-image-cards-for-rss' ),
			__( 'Gridify Image Cards', 'gridify-image-cards-for-rss' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register plugin settings.
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
	 * Sanitize settings input.
	 *
	 * @param array $input Raw form values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = RSS_Display::default_settings();
		$out      = array();

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		// Feed URLs (one per line).
		$feeds_raw   = isset( $input['feeds'] ) ? (string) $input['feeds'] : '';
		$clean_lines = array_map( 'esc_url_raw', RSS_Display::parse_feeds( $feeds_raw ) );
		$clean_lines = array_filter( $clean_lines );
		$out['feeds'] = implode( "\n", $clean_lines );

		// Display count (1–100).
		$count        = isset( $input['count'] ) ? absint( $input['count'] ) : $defaults['count'];
		$out['count'] = $count > 0 ? min( $count, 100 ) : $defaults['count'];

		// Columns (2/3/4).
		$columns        = isset( $input['columns'] ) ? (int) $input['columns'] : $defaults['columns'];
		$out['columns'] = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : $defaults['columns'];

		// Title max lines (1/2/3).
		$tl                 = isset( $input['title_lines'] ) ? (int) $input['title_lines'] : $defaults['title_lines'];
		$out['title_lines'] = in_array( $tl, array( 1, 2, 3 ), true ) ? $tl : $defaults['title_lines'];

		// Cache TTL.
		$allowed_ttl      = array( 43200, 86400, 604800, 2592000 );
		$ttl              = isset( $input['cache_ttl'] ) ? (int) $input['cache_ttl'] : $defaults['cache_ttl'];
		$out['cache_ttl'] = in_array( $ttl, $allowed_ttl, true ) ? $ttl : $defaults['cache_ttl'];

		// Default image.
		$out['default_image_id']  = isset( $input['default_image_id'] ) ? absint( $input['default_image_id'] ) : 0;
		$out['default_image_url'] = isset( $input['default_image_url'] ) ? esc_url_raw( trim( $input['default_image_url'] ) ) : '';

		// Open links in new tab.
		$out['link_new_tab'] = ! empty( $input['link_new_tab'] ) ? 1 : 0;

		// Display type.
		$type        = isset( $input['type'] ) ? (string) $input['type'] : $defaults['type'];
		$out['type'] = in_array( $type, RSS_Display::allowed_types(), true ) ? $type : $defaults['type'];

		// Sort order.
		$orderby        = isset( $input['orderby'] ) ? (string) $input['orderby'] : $defaults['orderby'];
		$out['orderby'] = in_array( $orderby, array( 'date', 'random' ), true ) ? $orderby : 'date';

		// Display options.
		$out['show_desc'] = ! empty( $input['show_desc'] ) ? 1 : 0;
		$out['show_date'] = ! empty( $input['show_date'] ) ? 1 : 0;
		$out['show_site'] = ! empty( $input['show_site'] ) ? 1 : 0;

		return $out;
	}

	/**
	 * Enqueue admin assets (settings page only).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . $this->page_slug !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'rss-d-admin', RSS_D_URL . 'assets/css/admin.css', array(), RSS_D_VERSION );
		wp_enqueue_style( 'gridify-image-cards-for-rss', RSS_D_URL . 'assets/css/gridify-image-cards-for-rss.css', array(), RSS_D_VERSION );
		wp_enqueue_script( 'rss-d-admin', RSS_D_URL . 'assets/js/admin.js', array( 'jquery' ), RSS_D_VERSION, true );
		$defaults = RSS_Display::default_settings();
		wp_localize_script(
			'rss-d-admin',
			'rssDAdmin',
			array(
				'chooseTitle'    => __( 'Select default image', 'gridify-image-cards-for-rss' ),
				'chooseButton'   => __( 'Use this image', 'gridify-image-cards-for-rss' ),
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'rss_d_preview' ),
				'defaultType'    => $defaults['type'],
				'defaultColumns' => (string) $defaults['columns'],
				'defaultCount'   => (string) $defaults['count'],
				'msgLoading'     => __( '読み込み中…', 'gridify-image-cards-for-rss' ),
				'msgError'       => __( '取得に失敗しました。', 'gridify-image-cards-for-rss' ),
				'msgNetError'    => __( '通信エラーが発生しました。', 'gridify-image-cards-for-rss' ),
				'btnCopy'        => __( 'Copy', 'gridify-image-cards-for-rss' ),
				'btnCopied'      => __( 'Copied!', 'gridify-image-cards-for-rss' ),
			)
		);
	}

	/**
	 * Handle settings form save and redirect back to the plugin settings page.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gridify-image-cards-for-rss' ) );
		}

		check_admin_referer( 'rss_d_save' );

		$input = isset( $_POST[ RSS_D_OPTION ] ) && is_array( $_POST[ RSS_D_OPTION ] )
			? wp_unslash( $_POST[ RSS_D_OPTION ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		update_option( RSS_D_OPTION, $this->sanitize( $input ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => $this->page_slug,
					'rss_d_saved' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle "Refresh now" form: clear RSS transient cache.
	 *
	 * @return void
	 */
	public function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'gridify-image-cards-for-rss' ) );
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
	 * AJAX preview: render shortcode and return HTML.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( 'rss_d_preview' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$allowed_types = RSS_Display::allowed_types();
		$post_type     = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$type          = in_array( $post_type, $allowed_types, true ) ? $post_type : 'grid';
		$columns     = isset( $_POST['columns'] ) ? (int) $_POST['columns'] : 3; // phpcs:ignore
		$columns     = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : 3;
		$count       = isset( $_POST['count'] ) ? absint( $_POST['count'] ) : 6; // phpcs:ignore
		$count       = min( max( 1, $count ), 100 );
		$responsive  = isset( $_POST['responsive'] ) && '0' === $_POST['responsive'] ? '0' : '1'; // phpcs:ignore
		$new_tab     = ! empty( $_POST['new_tab'] ) ? '1' : '0'; // phpcs:ignore
		$show_desc   = ! empty( $_POST['show_desc'] ) ? '1' : '0'; // phpcs:ignore
		$show_date   = ! empty( $_POST['show_date'] ) ? '1' : '0'; // phpcs:ignore
		$show_site   = ! empty( $_POST['show_site'] ) ? '1' : '0'; // phpcs:ignore
		$bold_title  = ! empty( $_POST['bold_title'] ) ? '1' : '0'; // phpcs:ignore
		$title_lines = isset( $_POST['title_lines'] ) ? absint( $_POST['title_lines'] ) : 2; // phpcs:ignore
		$title_lines = min( $title_lines, 10 );

		$html = do_shortcode(
			sprintf(
				'[rss_display type="%s" columns="%d" count="%d" responsive="%s" target="%s" desc="%s" date="%s" site="%s" bold="%s" title_lines="%d"]',
				esc_attr( $type ),
				$columns,
				$count,
				$responsive,
				$new_tab ? '_blank' : '_self',
				$show_desc,
				$show_date,
				$show_site,
				$bold_title,
				$title_lines
			)
		);

		if ( '' === $html ) {
			$html = '<p style="padding:2em;color:#888;">' . esc_html__( 'No items found. Make sure feed URLs are registered in the settings.', 'gridify-image-cards-for-rss' ) . '</p>';
		}

		wp_send_json_success(
			array(
				'html'   => $html,
				'js_url' => RSS_D_URL . 'assets/js/gridify-image-cards-for-rss.js',
			)
		);
	}

	/**
	 * Render the settings page HTML.
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

		$option = RSS_D_OPTION;

		$types = array(
			'grid'          => 'grid — ' . __( '画像背景＋タイトルオーバーレイ', 'gridify-image-cards-for-rss' ),
			'list_vertical' => 'list_vertical — ' . __( '上画像・下テキストカード', 'gridify-image-cards-for-rss' ),
			'text'          => 'text — ' . __( 'テキストのみカード（説明付き）', 'gridify-image-cards-for-rss' ),
			'text_line'     => 'text_line — ' . __( '1行テキスト＋区切り線', 'gridify-image-cards-for-rss' ),
			'image_only'    => 'image_only — ' . __( '画像のみ', 'gridify-image-cards-for-rss' ),
			'list'          => 'list — ' . __( 'サムネイル横並び＋テキスト', 'gridify-image-cards-for-rss' ),
			'carousel'      => 'carousel — ' . __( 'スライドカルーセル', 'gridify-image-cards-for-rss' ),
			'popup_grid'    => 'popup_grid — ' . __( 'クリックでモーダル表示グリッド', 'gridify-image-cards-for-rss' ),
		);
		?>
		<div class="wrap rss-d-admin">
			<h1><?php echo esc_html__( 'Gridify Image Cards for RSS Settings', 'gridify-image-cards-for-rss' ); ?></h1>

			<?php if ( isset( $_GET['rss_d_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'gridify-image-cards-for-rss' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rss_d_refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'RSS cache cleared. Items will be re-fetched on next page load. OGP image cache is preserved.', 'gridify-image-cards-for-rss' ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Tab navigation -->
			<nav class="rss-d-tabs" role="tablist">
				<button class="rss-d-tab active" role="tab" aria-selected="true"  data-tab="basic"><?php esc_html_e( 'Basic', 'gridify-image-cards-for-rss' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="display"><?php esc_html_e( 'Display', 'gridify-image-cards-for-rss' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="preview"><?php esc_html_e( 'Preview', 'gridify-image-cards-for-rss' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="status"><?php esc_html_e( 'Feed Status', 'gridify-image-cards-for-rss' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="usage"><?php esc_html_e( 'Usage', 'gridify-image-cards-for-rss' ); ?></button>
				<button class="rss-d-tab"        role="tab" aria-selected="false" data-tab="about"><?php esc_html_e( 'About', 'gridify-image-cards-for-rss' ); ?></button>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rss-d-settings-form">
				<input type="hidden" name="action" value="rss_d_save" />
				<?php wp_nonce_field( 'rss_d_save' ); ?>

				<!-- ========== Basic tab ========== -->
				<div class="rss-d-tab-panel active" data-panel="basic">

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="rss_d_feeds"><?php esc_html_e( 'RSS Feed URLs', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<textarea id="rss_d_feeds" name="<?php echo esc_attr( $option ); ?>[feeds]" rows="6" class="large-text code" placeholder="https://example.com/feed&#10;https://example.org/feed"><?php echo esc_textarea( $settings['feeds'] ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Enter one feed URL per line.', 'gridify-image-cards-for-rss' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_cache_ttl"><?php esc_html_e( 'Cache Duration', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<select id="rss_d_cache_ttl" name="<?php echo esc_attr( $option ); ?>[cache_ttl]">
									<option value="43200"  <?php selected( $settings['cache_ttl'], 43200 ); ?>><?php esc_html_e( '12 hours', 'gridify-image-cards-for-rss' ); ?></option>
									<option value="86400"  <?php selected( $settings['cache_ttl'], 86400 ); ?>><?php esc_html_e( '1 day', 'gridify-image-cards-for-rss' ); ?></option>
									<option value="604800" <?php selected( $settings['cache_ttl'], 604800 ); ?>><?php esc_html_e( '1 week', 'gridify-image-cards-for-rss' ); ?></option>
									<option value="2592000"<?php selected( $settings['cache_ttl'], 2592000 ); ?>><?php esc_html_e( '1 month', 'gridify-image-cards-for-rss' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'How often RSS feeds are re-fetched. OGP image cache is fixed at 1 month.', 'gridify-image-cards-for-rss' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Default Image', 'gridify-image-cards-for-rss' ); ?></th>
							<td>
								<div class="rss-d-default-image">
									<input type="hidden" id="rss_d_default_image_id" name="<?php echo esc_attr( $option ); ?>[default_image_id]" value="<?php echo esc_attr( $settings['default_image_id'] ); ?>" />
									<div class="rss-d-image-preview">
										<?php if ( $default_preview ) : ?>
											<img src="<?php echo esc_url( $default_preview ); ?>" alt="" />
										<?php endif; ?>
									</div>
									<p>
										<button type="button" class="button" id="rss_d_select_image"><?php esc_html_e( 'Select from Media Library', 'gridify-image-cards-for-rss' ); ?></button>
										<button type="button" class="button" id="rss_d_clear_image"><?php esc_html_e( 'Remove', 'gridify-image-cards-for-rss' ); ?></button>
									</p>
									<p>
										<label for="rss_d_default_image_url"><?php esc_html_e( 'Or specify a URL directly:', 'gridify-image-cards-for-rss' ); ?></label><br />
										<input type="url" id="rss_d_default_image_url" name="<?php echo esc_attr( $option ); ?>[default_image_url]" value="<?php echo esc_attr( $settings['default_image_url'] ); ?>" class="regular-text" placeholder="https://example.com/default.png" />
									</p>
									<p class="description"><?php esc_html_e( 'Media Library selection takes priority. If neither is set, the bundled placeholder image is used.', 'gridify-image-cards-for-rss' ); ?></p>
								</div>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Link Target', 'gridify-image-cards-for-rss' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[link_new_tab]" value="1" <?php checked( ! empty( $settings['link_new_tab'] ) ); ?> />
									<?php esc_html_e( 'Open links in a new tab', 'gridify-image-cards-for-rss' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>

				</div><!-- /basic -->

				<!-- ========== Display tab ========== -->
				<div class="rss-d-tab-panel" data-panel="display">

					<p class="description" style="margin:1em 0;"><?php esc_html_e( 'These are the default values used when no shortcode attribute overrides them.', 'gridify-image-cards-for-rss' ); ?></p>

					<table class="form-table" role="presentation">

						<tr>
							<th scope="row">
								<label for="rss_d_type"><?php esc_html_e( 'Display Type', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<select id="rss_d_type" name="<?php echo esc_attr( $option ); ?>[type]">
									<?php foreach ( $types as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['type'], $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_columns"><?php esc_html_e( 'Columns', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<select id="rss_d_columns" name="<?php echo esc_attr( $option ); ?>[columns]">
									<?php foreach ( array( 2, 3, 4 ) as $c ) : ?>
										<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $settings['columns'], $c ); ?>><?php echo esc_html( $c ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_count"><?php esc_html_e( 'Item Count', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<input type="number" id="rss_d_count" name="<?php echo esc_attr( $option ); ?>[count]" value="<?php echo esc_attr( $settings['count'] ); ?>" min="1" max="100" class="small-text" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_orderby"><?php esc_html_e( 'Order By', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<select id="rss_d_orderby" name="<?php echo esc_attr( $option ); ?>[orderby]">
									<option value="date"   <?php selected( $settings['orderby'], 'date' ); ?>><?php esc_html_e( 'Date (newest first)', 'gridify-image-cards-for-rss' ); ?></option>
									<option value="random" <?php selected( $settings['orderby'], 'random' ); ?>><?php esc_html_e( 'Random', 'gridify-image-cards-for-rss' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="rss_d_title_lines"><?php esc_html_e( 'Title Max Lines', 'gridify-image-cards-for-rss' ); ?></label>
							</th>
							<td>
								<select id="rss_d_title_lines" name="<?php echo esc_attr( $option ); ?>[title_lines]">
									<?php foreach ( array( 1, 2, 3 ) as $l ) : ?>
										<option value="<?php echo esc_attr( $l ); ?>" <?php selected( $settings['title_lines'], $l ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Display Options', 'gridify-image-cards-for-rss' ); ?></th>
							<td>
								<fieldset>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_date]" value="1" <?php checked( ! empty( $settings['show_date'] ) ); ?> />
										<?php esc_html_e( 'Show date', 'gridify-image-cards-for-rss' ); ?>
									</label>
									<label style="display:block;margin-bottom:6px;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_site]" value="1" <?php checked( ! empty( $settings['show_site'] ) ); ?> />
										<?php esc_html_e( 'Show site name', 'gridify-image-cards-for-rss' ); ?>
									</label>
									<label style="display:block;">
										<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_desc]" value="1" <?php checked( ! empty( $settings['show_desc'] ) ); ?> />
										<?php esc_html_e( 'Show description', 'gridify-image-cards-for-rss' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

					</table>

					<?php submit_button(); ?>

				</div><!-- /display -->

				<!-- ========== Preview tab ========== -->
				<div class="rss-d-tab-panel" data-panel="preview">

					<div class="rss-d-preview-toolbar">
						<!-- Device width switcher -->
						<div class="rss-d-preview-devices">
							<button type="button" class="rss-d-device-btn active" data-width="100%" title="Desktop">
								<span class="dashicons dashicons-desktop"></span>
							</button>
							<button type="button" class="rss-d-device-btn" data-width="1024px" title="Tablet">
								<span class="dashicons dashicons-tablet"></span>
							</button>
							<button type="button" class="rss-d-device-btn" data-width="375px" title="Smartphone">
								<span class="dashicons dashicons-smartphone"></span>
							</button>
						</div>

						<!-- Type selector -->
						<label><?php esc_html_e( 'Type:', 'gridify-image-cards-for-rss' ); ?>
							<select id="rss-d-preview-type">
								<?php foreach ( $types as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<!-- Columns selector -->
						<label><?php esc_html_e( 'Columns:', 'gridify-image-cards-for-rss' ); ?>
							<select id="rss-d-preview-columns">
								<?php foreach ( array( 2, 3, 4 ) as $c ) : ?>
									<option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>

						<!-- Count selector -->
						<label><?php esc_html_e( 'Count:', 'gridify-image-cards-for-rss' ); ?>
							<input type="number" id="rss-d-preview-count" value="6" min="1" max="100" style="width:60px;" />
						</label>

						<button type="button" id="rss-d-preview-btn" class="button button-primary"><?php esc_html_e( 'Refresh Preview', 'gridify-image-cards-for-rss' ); ?></button>
					</div>

					<!-- オプション行 -->
					<div class="rss-d-preview-options">
						<label title="<?php esc_attr_e( 'タブレット2列・スマホ1列に自動調整', 'gridify-image-cards-for-rss' ); ?>">
							<input type="checkbox" id="rss-d-preview-responsive" checked />
							<?php esc_html_e( 'レスポンシブ', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label>
							<input type="checkbox" id="rss-d-preview-new-tab" />
							<?php esc_html_e( '新規タブ', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label>
							<input type="checkbox" id="rss-d-preview-show-desc" />
							<?php esc_html_e( '説明', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label>
							<input type="checkbox" id="rss-d-preview-show-date" />
							<?php esc_html_e( '日付', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label>
							<input type="checkbox" id="rss-d-preview-show-site" />
							<?php esc_html_e( 'サイト名', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label>
							<input type="checkbox" id="rss-d-preview-bold-title" />
							<?php esc_html_e( '太字タイトル', 'gridify-image-cards-for-rss' ); ?>
						</label>
						<label title="<?php esc_attr_e( '表示タイトル行数（0=無制限）', 'gridify-image-cards-for-rss' ); ?>"><?php esc_html_e( 'タイトル行数:', 'gridify-image-cards-for-rss' ); ?>
							<input type="number" id="rss-d-preview-title-lines" value="2" min="0" max="10" style="width:46px;" />
						</label>
					</div>

					<div id="rss-d-preview-shortcode" style="display:none;margin:8px 0;padding:6px 12px;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:3px;display:none;align-items:center;gap:8px;">
						<code id="rss-d-preview-shortcode-text" style="flex:1;font-size:13px;word-break:break-all;background:none;padding:0;"></code>
						<button type="button" id="rss-d-preview-shortcode-copy" class="button button-small"><?php esc_html_e( 'Copy', 'gridify-image-cards-for-rss' ); ?></button>
					</div>

					<div class="rss-d-preview-frame-wrap">
						<div class="rss-d-preview-frame" id="rss-d-preview-frame">
							<p class="rss-d-preview-placeholder"><?php esc_html_e( 'Click "Refresh Preview" to see a live preview.', 'gridify-image-cards-for-rss' ); ?></p>
						</div>
					</div>

				</div><!-- /preview -->

			</form><!-- /form -->

			<!-- ========== Feed Status tab ========== -->
			<div class="rss-d-tab-panel" data-panel="status">

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0;">
					<input type="hidden" name="action" value="rss_d_refresh" />
					<?php wp_nonce_field( 'rss_d_refresh' ); ?>
					<?php submit_button( __( 'Refresh Now (clear RSS cache)', 'gridify-image-cards-for-rss' ), 'secondary', 'submit', false ); ?>
					<span class="description"><?php esc_html_e( 'OGP image cache is not cleared.', 'gridify-image-cards-for-rss' ); ?></span>
				</form>

				<?php if ( empty( $feeds ) ) : ?>
					<p><?php esc_html_e( 'No feeds registered yet.', 'gridify-image-cards-for-rss' ); ?></p>
				<?php else : ?>
					<table class="widefat striped rss-d-status-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Feed URL', 'gridify-image-cards-for-rss' ); ?></th>
								<th><?php esc_html_e( 'Last Fetched', 'gridify-image-cards-for-rss' ); ?></th>
								<th><?php esc_html_e( 'Item Count', 'gridify-image-cards-for-rss' ); ?></th>
								<th><?php esc_html_e( 'Status', 'gridify-image-cards-for-rss' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
							foreach ( $feeds as $feed_url ) :
								$status = $this->feed_manager->get_feed_status( $feed_url );
								if ( ! $status['cached'] ) {
									$fetched_label = esc_html__( 'Not fetched', 'gridify-image-cards-for-rss' );
									$count_label   = '&mdash;';
									$state_label   = esc_html__( 'Not fetched', 'gridify-image-cards-for-rss' );
								} else {
									$fetched_label = esc_html( date_i18n( $datetime_format, $status['fetched'] ) );
									$count_label   = esc_html( (string) $status['count'] );
									$state_label   = $status['error']
										? esc_html__( 'Error (showing stale cache)', 'gridify-image-cards-for-rss' )
										: esc_html__( 'OK', 'gridify-image-cards-for-rss' );
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

			<!-- ========== Usage tab ========== -->
			<div class="rss-d-tab-panel" data-panel="usage">

				<!-- Parameter reference -->
				<h2><?php esc_html_e( 'Parameter Reference', 'gridify-image-cards-for-rss' ); ?></h2>
				<table class="widefat striped" style="max-width:800px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Parameter', 'gridify-image-cards-for-rss' ); ?></th>
							<th><?php esc_html_e( 'Default', 'gridify-image-cards-for-rss' ); ?></th>
							<th><?php esc_html_e( 'Description', 'gridify-image-cards-for-rss' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><code>feed</code></td><td>—</td><td><?php esc_html_e( 'Feed URL(s), comma-separated', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>type</code></td><td><code>grid</code></td><td><?php esc_html_e( 'Display type', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>columns</code></td><td><code>3</code></td><td><?php esc_html_e( 'Number of columns (2/3/4)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>count</code></td><td><code>6</code></td><td><?php esc_html_e( 'Number of items to display', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>orderby</code></td><td><code>date</code></td><td><?php esc_html_e( 'Sort order (date/random)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>target</code></td><td>—</td><td><?php esc_html_e( 'Link target (_blank/_self)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>date</code></td><td><code>1</code></td><td><?php esc_html_e( 'Show date (1/0)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>site</code></td><td><code>0</code></td><td><?php esc_html_e( 'Show site name (1/0)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>desc</code></td><td><code>0</code></td><td><?php esc_html_e( 'Show description (1/0)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>bold</code></td><td><code>0</code></td><td><?php esc_html_e( 'Bold title (1/0)', 'gridify-image-cards-for-rss' ); ?></td></tr>
						<tr><td><code>img</code></td><td>—</td><td><?php esc_html_e( 'Override default image URL', 'gridify-image-cards-for-rss' ); ?></td></tr>
					</tbody>
				</table>

			</div><!-- /usage -->

			<!-- ========== About tab ========== -->
			<div class="rss-d-tab-panel" data-panel="about">

				<div class="rss-d-about-wrap">

					<div class="rss-d-about-header">
						<h2>Gridify Image Cards for RSS <span class="rss-d-about-version">v<?php echo esc_html( RSS_D_VERSION ); ?></span></h2>
						<p><?php esc_html_e( '複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグインです。', 'gridify-image-cards-for-rss' ); ?></p>
					</div>

					<div class="rss-d-about-grid">

						<div class="rss-d-about-card">
							<h3><?php esc_html_e( '主な機能', 'gridify-image-cards-for-rss' ); ?></h3>
							<ul>
								<li><?php esc_html_e( '8種類のレイアウト（grid / list / carousel など）', 'gridify-image-cards-for-rss' ); ?></li>
								<li><?php esc_html_e( '複数フィードの集約・重複排除', 'gridify-image-cards-for-rss' ); ?></li>
								<li><?php esc_html_e( 'OGP画像の自動取得・キャッシュ', 'gridify-image-cards-for-rss' ); ?></li>
								<li><?php esc_html_e( 'レスポンシブ対応（PC/タブレット/スマホ）', 'gridify-image-cards-for-rss' ); ?></li>
								<li><?php esc_html_e( '外部サービス依存なし（WordPress組み込み機能のみ）', 'gridify-image-cards-for-rss' ); ?></li>
							</ul>
						</div>

						<div class="rss-d-about-card">
							<h3><?php esc_html_e( '基本情報', 'gridify-image-cards-for-rss' ); ?></h3>
							<table class="rss-d-about-table">
								<tr><th><?php esc_html_e( 'バージョン', 'gridify-image-cards-for-rss' ); ?></th><td><?php echo esc_html( RSS_D_VERSION ); ?></td></tr>
								<tr><th><?php esc_html_e( '必要環境', 'gridify-image-cards-for-rss' ); ?></th><td>PHP 7.4+ / WordPress 6.0+</td></tr>
								<tr><th><?php esc_html_e( 'ライセンス', 'gridify-image-cards-for-rss' ); ?></th><td>GPL-2.0-or-later</td></tr>
								<tr>
									<th><?php esc_html_e( 'ウェブサイト', 'gridify-image-cards-for-rss' ); ?></th>
									<td><a href="https://gridify-image-cards-for-rss.pages.dev/" target="_blank" rel="noopener noreferrer">gridify-image-cards-for-rss.pages.dev</a></td>
								</tr>
							</table>
						</div>

						<div class="rss-d-about-card rss-d-about-card--full">
							<h3><?php esc_html_e( 'サポート・お問い合わせ', 'gridify-image-cards-for-rss' ); ?></h3>
							<p>
								<?php esc_html_e( 'ドキュメント・デモ・バグ報告はプラグイン公式サイトをご利用ください。', 'gridify-image-cards-for-rss' ); ?>
							</p>
							<a href="https://gridify-image-cards-for-rss.pages.dev/" target="_blank" rel="noopener noreferrer" class="button button-primary">
								<?php esc_html_e( '公式サイトを開く', 'gridify-image-cards-for-rss' ); ?>
							</a>
						</div>

					</div><!-- /rss-d-about-grid -->

				</div><!-- /rss-d-about-wrap -->

			</div><!-- /about -->

		</div><!-- /wrap -->
		<?php
	}
}
