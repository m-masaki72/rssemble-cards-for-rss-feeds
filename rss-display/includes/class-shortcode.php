<?php
/**
 * ショートコード [rss_display] を処理し、表示HTMLを生成するクラス。
 *
 * @package RSS_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RSS_D_Shortcode {

	/**
	 * フィードマネージャ。
	 *
	 * @var RSS_D_Feed_Manager
	 */
	private $feed_manager;

	/**
	 * OGP 取得クラス。
	 *
	 * @var RSS_D_OGP_Fetcher
	 */
	private $ogp_fetcher;

	/**
	 * コンストラクタ。
	 *
	 * @param RSS_D_Feed_Manager $feed_manager フィードマネージャ。
	 * @param RSS_D_OGP_Fetcher  $ogp_fetcher  OGP 取得クラス。
	 */
	public function __construct( $feed_manager, $ogp_fetcher ) {
		$this->feed_manager = $feed_manager;
		$this->ogp_fetcher  = $ogp_fetcher;

		add_shortcode( 'rss_display', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_register_style' ) );
	}

	/**
	 * フロントエンド用CSSを登録し、ショートコードを含む投稿では先読みで enqueue する。
	 *
	 * @return void
	 */
	public function maybe_register_style() {
		wp_register_style(
			'rss-display',
			RSS_D_URL . 'assets/css/rss-display.css',
			array(),
			RSS_D_VERSION
		);

		// 投稿本文にショートコードがあれば head 出力のため先読み enqueue する。
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'rss_display' ) ) {
				wp_enqueue_style( 'rss-display' );
			}
		}
	}

	/**
	 * ショートコードのレンダリング処理。
	 *
	 * @param array $atts ショートコード属性。
	 * @return string
	 */
	public function render( $atts ) {
		$settings   = RSS_Display::get_settings();
		$is_paying  = rss_display_fs_is_paying();

		$atts = shortcode_atts(
			array(
				'columns' => $settings['columns'],
				'count'   => $settings['count'],
				'feed'    => '',
				'orderby' => 'date',
				'target'  => '',
				'img'     => '',
				'desc'    => '0',
				'date'    => '1',
				'site'    => '0',
				'type'    => 'grid',
			),
			$atts,
			'rss_display'
		);

		// 列数（2/3/4のみ許可）。
		$columns = (int) $atts['columns'];
		if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
			$columns = (int) $settings['columns'];
			if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
				$columns = 3;
			}
		}
		if ( ! $is_paying && $columns > RSS_D_FREE_MAX_COLUMNS ) {
			$columns = RSS_D_FREE_MAX_COLUMNS;
		}

		// 表示件数。
		$count = absint( $atts['count'] );
		if ( $count <= 0 ) {
			$count = absint( $settings['count'] );
		}

		// ソート順。
		$orderby = ( 'random' === $atts['orderby'] ) ? 'random' : 'date';
		if ( ! $is_paying && ! in_array( $orderby, RSS_D_FREE_ORDERBY, true ) ) {
			return $this->upgrade_notice();
		}

		// 対象フィードの決定（feed 指定があればそのフィードのみ）。
		if ( '' !== $atts['feed'] ) {
			$feeds = array( $atts['feed'] );
		} else {
			$feeds = $this->parse_feeds( $settings['feeds'] );
			if ( ! $is_paying && count( $feeds ) > RSS_D_FREE_MAX_FEEDS ) {
				$feeds = array_slice( $feeds, 0, RSS_D_FREE_MAX_FEEDS );
			}
		}

		if ( empty( $feeds ) ) {
			return '';
		}

		// リンクの開き方（ショートコード指定 > 管理画面設定）。
		if ( '_self' === $atts['target'] || '_blank' === $atts['target'] ) {
			$new_tab = ( '_blank' === $atts['target'] );
		} else {
			$new_tab = ! empty( $settings['link_new_tab'] );
		}

		$items = $this->feed_manager->get_items( $feeds, $count, $orderby );

		if ( empty( $items ) ) {
			return '';
		}

		// このページではCSSが必要なため enqueue する（footer出力にもフォールバック）。
		if ( ! wp_style_is( 'rss-display', 'registered' ) ) {
			wp_register_style(
				'rss-display',
				RSS_D_URL . 'assets/css/rss-display.css',
				array(),
				RSS_D_VERSION
			);
		}
		wp_enqueue_style( 'rss-display' );

		$default_image = '' !== $atts['img'] ? esc_url_raw( $atts['img'] ) : $this->get_default_image_url( $settings );

		$show_desc = ( '1' === (string) $atts['desc'] );
		$show_date = ( '0' !== (string) $atts['date'] );
		$show_site = ( '1' === (string) $atts['site'] );

		$allowed_types = array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line' );
		$type          = in_array( $atts['type'], $allowed_types, true ) ? $atts['type'] : 'grid';
		if ( ! $is_paying && ! in_array( $type, RSS_D_FREE_TYPES, true ) ) {
			return $this->upgrade_notice();
		}

		$title_lines = (int) $settings['title_lines'];
		if ( ! in_array( $title_lines, array( 1, 2, 3 ), true ) ) {
			$title_lines = 2;
		}

		$date_format = get_option( 'date_format' );
		$target_attr = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

		// RSS画像がないアイテムのURLのみ並列OGP取得。
		$ogp_urls = array();
		foreach ( $items as $item ) {
			if ( '' === $item['image'] && '' !== $item['url'] ) {
				$ogp_urls[] = $item['url'];
			}
		}
		$ogp_map = ! empty( $ogp_urls ) ? $this->ogp_fetcher->get_images( $ogp_urls ) : array();

		ob_start();
		?>
		<div class="rss-d-grid rss-d-type-<?php echo esc_attr( $type ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
			<?php foreach ( $items as $item ) : ?>
				<?php
				// 画像解決：RSS内画像 → OGP取得（並列済み）→ デフォルト画像。
				$image = $item['image'];
				if ( '' === $image && '' !== $item['url'] ) {
					$image = $ogp_map[ $item['url'] ] ?? '';
				}
				if ( '' === $image ) {
					$image = $default_image;
				}

				$title      = $item['title'];
				$link       = $item['url'];
				$date_label = ( $show_date && $item['timestamp'] ) ? date_i18n( $date_format, $item['timestamp'] ) : '';
				$desc_text  = $show_desc ? ( $item['desc'] ?? '' ) : '';
				$site_name  = $show_site ? ( $item['site'] ?? '' ) : '';
				?>
				<?php if ( '' !== $link ) : ?>
				<a class="rss-d-card" href="<?php echo esc_url( $link ); ?>"<?php echo $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 固定の安全な文字列。 ?>>
				<?php else : ?>
				<div class="rss-d-card">
				<?php endif; ?>

					<?php if ( 'text' === $type || 'text_line' === $type ) : ?>
					<?php // テキスト系：画像なし ?>
					<div class="rss-d-card-body">
						<?php if ( '' !== $site_name ) : ?>
							<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $title ) : ?>
							<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( 'text' === $type && '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php elseif ( 'list' === $type || 'list_vertical' === $type ) : ?>
					<?php // リスト系：画像＋テキスト横並び or 縦並び ?>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<div class="rss-d-card-body">
						<?php if ( '' !== $site_name ) : ?>
							<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $title ) : ?>
							<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php else : ?>
					<?php // grid / image_only：全面背景画像 ?>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<span class="rss-d-overlay" aria-hidden="true"></span>
					<?php if ( 'grid' === $type ) : ?>
					<?php if ( '' !== $date_label ) : ?>
						<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $site_name ) : ?>
						<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $title ) : ?>
						<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>
					<?php if ( '' !== $desc_text ) : ?>
						<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
					<?php endif; ?>
					<?php endif; // image_only は何も出さない ?>
					<?php endif; ?>

				<?php if ( '' !== $link ) : ?>
				</a>
				<?php else : ?>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Pro 機能にアクセスした無料ユーザー向けのアップグレード案内 HTML を返す。
	 *
	 * @return string
	 */
	private function upgrade_notice() {
		$fs          = rss_display_fs();
		$upgrade_url = $fs ? $fs->get_upgrade_url() : '#';
		return sprintf(
			'<p class="rss-d-upgrade-notice">%s <a href="%s">%s</a></p>',
			esc_html__( 'この機能は RSS Display Pro プランでご利用いただけます。', 'rss-display' ),
			esc_url( $upgrade_url ),
			esc_html__( 'アップグレードする →', 'rss-display' )
		);
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
	 * デフォルト画像URLを取得する。
	 * 優先順位：メディアライブラリ選択 → URL直接指定 → 同梱プレースホルダー。
	 *
	 * @param array $settings 設定配列。
	 * @return string
	 */
	private function get_default_image_url( $settings ) {
		if ( ! empty( $settings['default_image_id'] ) ) {
			$url = wp_get_attachment_image_url( (int) $settings['default_image_id'], 'full' );
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $settings['default_image_url'] ) ) {
			return $settings['default_image_url'];
		}

		return RSS_D_URL . 'assets/img/placeholder.png';
	}
}
