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
		$settings = RSS_Display::get_settings();

		$atts = shortcode_atts(
			array(
				'columns' => $settings['columns'],
				'count'   => $settings['count'],
				'feed'    => '',
				'orderby' => 'date',
				'target'  => '',
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

		// 表示件数。
		$count = absint( $atts['count'] );
		if ( $count <= 0 ) {
			$count = absint( $settings['count'] );
		}

		// ソート順。
		$orderby = ( 'random' === $atts['orderby'] ) ? 'random' : 'date';

		// 対象フィードの決定（feed 指定があればそのフィードのみ）。
		if ( '' !== $atts['feed'] ) {
			$feeds = array( $atts['feed'] );
		} else {
			$feeds = $this->parse_feeds( $settings['feeds'] );
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

		$default_image = $this->get_default_image_url( $settings );

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
		<div class="rss-d-grid" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
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
				$date_label = $item['timestamp'] ? date_i18n( $date_format, $item['timestamp'] ) : '';
				?>
				<?php if ( '' !== $link ) : ?>
				<a class="rss-d-card" href="<?php echo esc_url( $link ); ?>"<?php echo $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 固定の安全な文字列。 ?>>
				<?php else : ?>
				<div class="rss-d-card">
				<?php endif; ?>
					<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
					<span class="rss-d-overlay" aria-hidden="true"></span>
					<?php if ( '' !== $date_label ) : ?>
						<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $title ) : ?>
						<h3 class="rss-d-title"><?php echo esc_html( $title ); ?></h3>
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
