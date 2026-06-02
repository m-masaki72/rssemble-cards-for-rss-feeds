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
	 * @param RSS_D_Feed_Manager $feed_manager
	 * @param RSS_D_OGP_Fetcher  $ogp_fetcher
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
		wp_register_style( 'rss-display', RSS_D_URL . 'assets/css/rss-display.css', array(), RSS_D_VERSION );

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'rss_display' ) ) {
				wp_enqueue_style( 'rss-display' );
				wp_enqueue_script( 'rss-display' );
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
		$settings  = RSS_Display::get_settings();
		$is_paying = rss_display_fs_is_paying();

		$atts = shortcode_atts(
			array(
				'columns' => $settings['columns'],
				'count'   => $settings['count'],
				'feed'    => '',
				'orderby' => $settings['orderby'],
				'target'  => '',
				'img'     => '',
				'desc'    => (string) $settings['show_desc'],
				'date'    => (string) $settings['show_date'],
				'site'    => (string) $settings['show_site'],
				'type'    => $settings['type'],
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
		if ( ! $is_paying && $count > RSS_D_FREE_MAX_COUNT ) {
			$count = RSS_D_FREE_MAX_COUNT;
		}

		// ソート順。
		$orderby = ( 'random' === $atts['orderby'] ) ? 'random' : 'date';
		if ( ! $is_paying && ! in_array( $orderby, RSS_D_FREE_ORDERBY, true ) ) {
			return $this->upgrade_notice();
		}

		// 対象フィード。
		if ( '' !== $atts['feed'] ) {
			$feeds = array( $atts['feed'] );
		} else {
			$feeds = RSS_Display::parse_feeds( $settings['feeds'] );
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

		if ( ! wp_style_is( 'rss-display', 'registered' ) ) {
			wp_register_style( 'rss-display', RSS_D_URL . 'assets/css/rss-display.css', array(), RSS_D_VERSION );
		}
		wp_enqueue_style( 'rss-display' );
		wp_enqueue_script( 'rss-display' );

		$default_image = '' !== $atts['img'] ? esc_url_raw( $atts['img'] ) : $this->get_default_image_url( $settings );
		$show_desc     = ( '1' === (string) $atts['desc'] );
		$show_date     = ( '0' !== (string) $atts['date'] );
		$show_site     = ( '1' === (string) $atts['site'] );

		$allowed_types = array( 'grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid' );
		$type          = in_array( $atts['type'], $allowed_types, true ) ? $atts['type'] : 'grid';
		if ( ! $is_paying && ! in_array( $type, RSS_D_FREE_TYPES, true ) ) {
			return $this->upgrade_notice();
		}

		$title_lines = (int) $settings['title_lines'];
		if ( ! in_array( $title_lines, array( 1, 2, 3 ), true ) ) {
			$title_lines = 2;
		}

		$resolved = $this->resolve_items( $items, $default_image, $show_date, $show_desc, $show_site );

		return $this->render_type( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site );
	}

	/**
	 * OGP画像解決＋表示用フィールドを付与した $resolved 配列を返す。
	 *
	 * @param array  $items         フィードアイテム配列。
	 * @param string $default_image デフォルト画像URL。
	 * @param bool   $show_date     日付表示フラグ。
	 * @param bool   $show_desc     説明文表示フラグ。
	 * @param bool   $show_site     サイト名表示フラグ。
	 * @return array
	 */
	private function resolve_items( $items, $default_image, $show_date, $show_desc, $show_site ) {
		// RSS画像がないアイテムのURLのみ並列OGP取得。
		$ogp_urls = array();
		foreach ( $items as $item ) {
			if ( '' === $item['image'] && '' !== $item['url'] ) {
				$ogp_urls[] = $item['url'];
			}
		}
		$ogp_map     = ! empty( $ogp_urls ) ? $this->ogp_fetcher->get_images( $ogp_urls ) : array();
		$date_format = get_option( 'date_format' );

		$resolved = array();
		foreach ( $items as $item ) {
			$image = $item['image'];
			if ( '' === $image && '' !== $item['url'] ) {
				$image = $ogp_map[ $item['url'] ] ?? '';
			}
			if ( '' === $image ) {
				$image = $default_image;
			}
			$item['_image']      = $image;
			$item['_date_label'] = ( $show_date && $item['timestamp'] ) ? date_i18n( $date_format, $item['timestamp'] ) : '';
			$item['_desc_text']  = $show_desc ? ( $item['desc'] ?? '' ) : '';
			$item['_site_name']  = $show_site ? ( $item['site'] ?? '' ) : '';
			$resolved[]          = $item;
		}
		return $resolved;
	}

	/**
	 * タイプに応じたレンダラーにディスパッチしHTMLを返す。
	 *
	 * @param array  $resolved    解決済みアイテム配列。
	 * @param string $type        表示タイプ。
	 * @param int    $columns     列数。
	 * @param int    $title_lines タイトル最大行数。
	 * @param bool   $new_tab     別タブで開くか。
	 * @param bool   $show_desc   説明文表示フラグ。
	 * @param bool   $show_date   日付表示フラグ。
	 * @param bool   $show_site   サイト名表示フラグ。
	 * @return string
	 */
	private function render_type( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site ) {
		ob_start();
		if ( 'carousel' === $type ) {
			$this->render_carousel( $resolved, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site );
		} elseif ( 'popup_grid' === $type ) {
			$this->render_popup_grid( $resolved, $columns, $title_lines, $new_tab );
		} else {
			$this->render_standard( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site );
		}
		return ob_get_clean();
	}

	/**
	 * 通常タイプ（grid/list/list_vertical/text/text_line/image_only）のHTML出力。
	 *
	 * @param array  $items
	 * @param string $type
	 * @param int    $columns
	 * @param int    $title_lines
	 * @param bool   $new_tab
	 * @param bool   $show_desc
	 * @param bool   $show_date
	 * @param bool   $show_site
	 * @return void
	 */
	private function render_standard( $items, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site ) {
		$target_attr = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		?>
		<div class="rss-d-grid rss-d-type-<?php echo esc_attr( $type ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
			<?php foreach ( $items as $item ) : ?>
				<?php
				$image      = $item['_image'];
				$title      = $item['title'];
				$link       = $item['url'];
				$date_label = $item['_date_label'];
				$desc_text  = $item['_desc_text'];
				$site_name  = $item['_site_name'];
				?>
				<?php if ( '' !== $link ) : ?>
				<a class="rss-d-card" href="<?php echo esc_url( $link ); ?>"<?php echo $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php else : ?>
				<div class="rss-d-card">
				<?php endif; ?>

					<?php if ( 'text' === $type || 'text_line' === $type ) : ?>
					<div class="rss-d-card-body">
						<?php if ( $show_site && '' !== $site_name ) : ?>
							<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $title ) : ?>
							<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( 'text' === $type && $show_desc && '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
						<?php if ( $show_date && '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php elseif ( 'list' === $type || 'list_vertical' === $type ) : ?>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<div class="rss-d-card-body">
						<?php if ( $show_site && '' !== $site_name ) : ?>
							<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $title ) : ?>
							<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( $show_desc && '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
						<?php if ( $show_date && '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php else : ?>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<span class="rss-d-overlay" aria-hidden="true"></span>
					<?php if ( 'grid' === $type ) : ?>
						<?php if ( $show_date && '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
						<?php if ( $show_site && '' !== $site_name ) : ?>
							<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $title ) : ?>
							<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( $show_desc && '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
					<?php endif; ?>

				<?php if ( '' !== $link ) : ?>
				</a>
				<?php else : ?>
				</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * カルーセルタイプのHTML出力。
	 *
	 * @param array $items
	 * @param int   $columns
	 * @param int   $title_lines
	 * @param bool  $new_tab
	 * @param bool  $show_desc
	 * @param bool  $show_date
	 * @param bool  $show_site
	 * @return void
	 */
	private function render_carousel( $items, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site ) {
		static $carousel_id = 0;
		$carousel_id++;
		$uid         = 'rss-d-carousel-' . $carousel_id;
		$target_attr = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		?>
		<div class="rss-d-carousel-wrap" id="<?php echo esc_attr( $uid ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
			<button class="rss-d-carousel-btn rss-d-carousel-prev" aria-label="前へ" data-target="<?php echo esc_attr( $uid ); ?>">&#10094;</button>
			<div class="rss-d-carousel-viewport">
				<div class="rss-d-carousel-track">
					<?php foreach ( $items as $item ) : ?>
						<?php
						$image      = $item['_image'];
						$title      = $item['title'];
						$link       = $item['url'];
						$date_label = $item['_date_label'];
						$desc_text  = $item['_desc_text'];
						$site_name  = $item['_site_name'];
						?>
						<?php if ( '' !== $link ) : ?>
						<a class="rss-d-card rss-d-carousel-card" href="<?php echo esc_url( $link ); ?>"<?php echo $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php else : ?>
						<div class="rss-d-card rss-d-carousel-card">
						<?php endif; ?>
							<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
							<span class="rss-d-overlay" aria-hidden="true"></span>
							<?php if ( $show_date && '' !== $date_label ) : ?>
								<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
							<?php endif; ?>
							<?php if ( $show_site && '' !== $site_name ) : ?>
								<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $title ) : ?>
								<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
							<?php endif; ?>
							<?php if ( $show_desc && '' !== $desc_text ) : ?>
								<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
							<?php endif; ?>
						<?php if ( '' !== $link ) : ?>
						</a>
						<?php else : ?>
						</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<button class="rss-d-carousel-btn rss-d-carousel-next" aria-label="次へ" data-target="<?php echo esc_attr( $uid ); ?>">&#10095;</button>
		</div>
		<?php
	}

	/**
	 * ポップアップグリッドタイプのHTML出力。
	 *
	 * @param array $items
	 * @param int   $columns
	 * @param int   $title_lines
	 * @param bool  $new_tab
	 * @return void
	 */
	private function render_popup_grid( $items, $columns, $title_lines, $new_tab ) {
		static $popup_id = 0;
		$popup_id++;
		$uid = 'rss-d-popup-' . $popup_id;
		?>
		<div class="rss-d-grid rss-d-type-popup_grid" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;" id="<?php echo esc_attr( $uid ); ?>">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php
				$image      = $item['_image'];
				$title      = $item['title'];
				$link       = $item['url'];
				$date_label = $item['_date_label'];
				$desc_text  = $item['_desc_text'];
				$site_name  = $item['_site_name'];
				?>
				<button
					class="rss-d-card rss-d-popup-trigger"
					type="button"
					aria-haspopup="dialog"
					data-image="<?php echo esc_attr( $image ); ?>"
					data-title="<?php echo esc_attr( $title ); ?>"
					data-link="<?php echo esc_attr( $link ); ?>"
					data-date="<?php echo esc_attr( $date_label ); ?>"
					data-desc="<?php echo esc_attr( $desc_text ); ?>"
					data-site="<?php echo esc_attr( $site_name ); ?>"
					data-newtab="<?php echo $new_tab ? '1' : '0'; ?>"
				>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<span class="rss-d-overlay" aria-hidden="true"></span>
					<?php if ( '' !== $date_label ) : ?>
						<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $site_name ) : ?>
						<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $title ) : ?>
						<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- モーダル -->
		<div class="rss-d-modal-overlay" id="<?php echo esc_attr( $uid . '-modal' ); ?>" role="dialog" aria-modal="true" aria-label="記事詳細" hidden>
			<div class="rss-d-modal">
				<button class="rss-d-modal-close" type="button" aria-label="閉じる">&times;</button>
				<img class="rss-d-modal-img" src="" alt="" />
				<div class="rss-d-modal-body">
					<p class="rss-d-modal-meta">
						<span class="rss-d-modal-site"></span>
						<span class="rss-d-modal-date"></span>
					</p>
					<h2 class="rss-d-modal-title"></h2>
					<p class="rss-d-modal-desc"></p>
					<a class="rss-d-modal-link button" href="#">記事を読む →</a>
				</div>
			</div>
		</div>
		<?php
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
