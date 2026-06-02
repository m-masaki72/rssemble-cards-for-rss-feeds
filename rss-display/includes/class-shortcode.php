<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Handles the [rss_display] shortcode and generates display HTML.
 *
 * @package RSS_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [rss_display] shortcode.
 */
class RSS_D_Shortcode {

	/**
	 * Feed manager instance.
	 *
	 * @var RSS_D_Feed_Manager
	 */
	private $feed_manager;

	/**
	 * OGP fetcher instance.
	 *
	 * @var RSS_D_OGP_Fetcher
	 */
	private $ogp_fetcher;

	/**
	 * Constructor.
	 *
	 * @param RSS_D_Feed_Manager $feed_manager Feed manager instance.
	 * @param RSS_D_OGP_Fetcher  $ogp_fetcher  OGP fetcher instance.
	 */
	public function __construct( $feed_manager, $ogp_fetcher ) {
		$this->feed_manager = $feed_manager;
		$this->ogp_fetcher  = $ogp_fetcher;

		add_shortcode( 'rss_display', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_register_style' ) );
	}

	/**
	 * Registers frontend CSS and enqueues it eagerly on singular posts containing the shortcode.
	 *
	 * @return void
	 */
	public function maybe_register_style() {
		wp_register_style( 'rss-display', RSS_D_URL . 'assets/css/rss-display.css', array(), RSS_D_VERSION );
	}

	/**
	 * Renders the shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$settings = RSS_Display::get_settings();

		$atts = shortcode_atts(
			array(
				'columns'     => $settings['columns'],
				'count'       => $settings['count'],
				'feed'        => '',
				'orderby'     => $settings['orderby'],
				'target'      => '',
				'img'         => '',
				'desc'        => (string) $settings['show_desc'],
				'date'        => (string) $settings['show_date'],
				'site'        => (string) $settings['show_site'],
				'type'        => $settings['type'],
				'bold'        => '0',
				'responsive'  => '1',
				'title_lines' => '',
			),
			$atts,
			'rss_display'
		);

		// Columns: only 2/3/4 are valid.
		$columns = (int) $atts['columns'];
		if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
			$columns = (int) $settings['columns'];
			if ( ! in_array( $columns, array( 2, 3, 4 ), true ) ) {
				$columns = 3;
			}
		}

		// Item count.
		$count = absint( $atts['count'] );
		if ( $count <= 0 ) {
			$count = absint( $settings['count'] );
		}

		// Sort order.
		$orderby = ( 'random' === $atts['orderby'] ) ? 'random' : 'date';

		// Feed URLs (comma-separated shortcode attribute takes priority).
		// Only http/https schemes are permitted to prevent SSRF via internal URLs.
		if ( '' !== $atts['feed'] ) {
			$feeds = array_values(
				array_filter(
					array_map( 'trim', explode( ',', $atts['feed'] ) ),
					static function ( $u ) {
						$scheme = wp_parse_url( $u, PHP_URL_SCHEME );
						return in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true );
					}
				)
			);
		} else {
			$feeds = RSS_Display::parse_feeds( $settings['feeds'] );
		}

		if ( empty( $feeds ) ) {
			return '';
		}

		// Link target: shortcode attribute overrides admin setting.
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
		$bold_title    = ( '1' === (string) $atts['bold'] );
		$responsive    = ( '1' === (string) $atts['responsive'] );

		$type = in_array( $atts['type'], RSS_Display::allowed_types(), true ) ? $atts['type'] : 'grid';

		$title_lines = '' !== $atts['title_lines'] ? (int) $atts['title_lines'] : (int) $settings['title_lines'];
		if ( ! in_array( $title_lines, array( 1, 2, 3 ), true ) ) {
			$title_lines = 2;
		}

		$resolved = $this->resolve_items( $items, $default_image, $show_date, $show_desc, $show_site );

		return $this->render_type( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $bold_title, $responsive );
	}

	/**
	 * Resolves OGP images and attaches display fields to each item.
	 *
	 * @param array  $items         Feed item array.
	 * @param string $default_image Fallback image URL.
	 * @param bool   $show_date     Whether to show the date.
	 * @param bool   $show_desc     Whether to show the description.
	 * @param bool   $show_site     Whether to show the site name.
	 * @return array
	 */
	private function resolve_items( $items, $default_image, $show_date, $show_desc, $show_site ) {
		// Collect URLs that need OGP fetching (no RSS image).
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
	 * Dispatches rendering to the appropriate method based on type.
	 *
	 * @param array  $resolved    Resolved item array.
	 * @param string $type        Display type.
	 * @param int    $columns     Number of columns.
	 * @param int    $title_lines Maximum title lines.
	 * @param bool   $new_tab     Open links in new tab.
	 * @param bool   $show_desc   Show description.
	 * @param bool   $show_date   Show date.
	 * @param bool   $show_site   Show site name.
	 * @param bool   $bold_title  Bold title.
	 * @return string
	 */
	private function render_type( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $bold_title = false, $responsive = true ) {
		$title_class = 'rss-d-title' . ( $bold_title ? ' rss-d-title--bold' : '' );
		ob_start();
		if ( 'carousel' === $type ) {
			$this->render_carousel( $resolved, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $title_class, $responsive );
		} elseif ( 'popup_grid' === $type ) {
			$this->render_popup_grid( $resolved, $columns, $title_lines, $new_tab, $title_class, $responsive );
		} else {
			$this->render_standard( $resolved, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $title_class, $responsive );
		}
		return ob_get_clean();
	}

	/**
	 * Renders standard layout types (grid/list/list_vertical/text/text_line/image_only).
	 *
	 * @param array  $items       Resolved item array.
	 * @param string $type        Display type.
	 * @param int    $columns     Number of columns.
	 * @param int    $title_lines Maximum title lines.
	 * @param bool   $new_tab     Open links in new tab.
	 * @param bool   $show_desc   Show description.
	 * @param bool   $show_date   Show date.
	 * @param bool   $show_site   Show site name.
	 * @param string $title_class CSS class string for the title element.
	 * @return void
	 */
	private function render_standard( $items, $type, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $title_class = 'rss-d-title', $responsive = true ) {
		$target_attr    = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		$responsive_cls = $responsive ? ' rss-d-responsive' : '';
		?>
		<div class="rss-d-grid rss-d-type-<?php echo esc_attr( $type ); ?><?php echo esc_attr( $responsive_cls ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
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
							<h3 class="<?php echo esc_attr( $title_class ); ?>"><?php echo esc_html( $title ); ?></h3>
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
							<h3 class="<?php echo esc_attr( $title_class ); ?>"><?php echo esc_html( $title ); ?></h3>
						<?php endif; ?>
						<?php if ( $show_desc && '' !== $desc_text ) : ?>
							<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
						<?php endif; ?>
						<?php if ( $show_date && '' !== $date_label ) : ?>
							<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
						<?php endif; ?>
					</div>

					<?php else : ?>
						<?php $this->render_card_overlay_body( $image, $title, $date_label, $site_name, $desc_text, $show_date, $show_site, $show_desc, $title_class, 'grid' === $type ); ?>
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
	 * Renders the carousel layout.
	 *
	 * @param array  $items       Resolved item array.
	 * @param int    $columns     Number of visible columns.
	 * @param int    $title_lines Maximum title lines.
	 * @param bool   $new_tab     Open links in new tab.
	 * @param bool   $show_desc   Show description.
	 * @param bool   $show_date   Show date.
	 * @param bool   $show_site   Show site name.
	 * @param string $title_class CSS class string for the title element.
	 * @return void
	 */
	private function render_carousel( $items, $columns, $title_lines, $new_tab, $show_desc, $show_date, $show_site, $title_class = 'rss-d-title', $responsive = true ) {
		static $carousel_id = 0;
		++$carousel_id;
		$uid            = 'rss-d-carousel-' . $carousel_id;
		$target_attr    = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
		$responsive_cls = $responsive ? ' rss-d-responsive' : '';
		?>
		<div class="rss-d-carousel-wrap<?php echo esc_attr( $responsive_cls ); ?>" id="<?php echo esc_attr( $uid ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;">
			<button type="button" class="rss-d-carousel-btn rss-d-carousel-prev" aria-label="Previous" data-target="<?php echo esc_attr( $uid ); ?>">&#10094;</button>
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
							<?php $this->render_card_overlay_body( $image, $title, $date_label, $site_name, $desc_text, $show_date, $show_site, $show_desc, $title_class ); ?>
						<?php if ( '' !== $link ) : ?>
						</a>
						<?php else : ?>
						</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<button type="button" class="rss-d-carousel-btn rss-d-carousel-next" aria-label="Next" data-target="<?php echo esc_attr( $uid ); ?>">&#10095;</button>
		</div>
		<?php
	}

	/**
	 * Renders the popup grid layout.
	 *
	 * @param array  $items       Resolved item array.
	 * @param int    $columns     Number of columns.
	 * @param int    $title_lines Maximum title lines.
	 * @param bool   $new_tab     Open links in new tab.
	 * @param string $title_class CSS class string for the title element.
	 * @return void
	 */
	private function render_popup_grid( $items, $columns, $title_lines, $new_tab, $title_class = 'rss-d-title', $responsive = true ) {
		static $popup_id = 0;
		++$popup_id;
		$uid            = 'rss-d-popup-' . $popup_id;
		$responsive_cls = $responsive ? ' rss-d-responsive' : '';
		?>
		<div class="rss-d-grid rss-d-type-popup_grid<?php echo esc_attr( $responsive_cls ); ?>" style="--rss-d-columns:<?php echo esc_attr( $columns ); ?>;--rss-d-title-lines:<?php echo esc_attr( $title_lines ); ?>;" id="<?php echo esc_attr( $uid ); ?>">
			<?php foreach ( $items as $item ) : ?>
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
					data-link="<?php echo esc_url( $link ); ?>"
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
						<h3 class="<?php echo esc_attr( $title_class ); ?>"><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>

		<div class="rss-d-modal-overlay" id="<?php echo esc_attr( $uid . '-modal' ); ?>" role="dialog" aria-modal="true" aria-label="Article detail" hidden>
			<div class="rss-d-modal">
				<button class="rss-d-modal-close" type="button" aria-label="Close">&times;</button>
				<img class="rss-d-modal-img" src="" alt="" />
				<div class="rss-d-modal-body">
					<p class="rss-d-modal-meta">
						<span class="rss-d-modal-site"></span>
						<span class="rss-d-modal-date"></span>
					</p>
					<h2 class="rss-d-modal-title"></h2>
					<p class="rss-d-modal-desc"></p>
					<a class="rss-d-modal-link button" href="#">Read article &rarr;</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the overlay image block shared by grid and carousel cards.
	 *
	 * @param string $image       Image URL.
	 * @param string $title       Article title.
	 * @param string $date_label  Formatted date string (empty to hide).
	 * @param string $site_name   Site name (empty to hide).
	 * @param string $desc_text   Description text (empty to hide).
	 * @param bool   $show_date   Whether to show the date.
	 * @param bool   $show_site   Whether to show the site name.
	 * @param bool   $show_desc   Whether to show the description.
	 * @param string $title_class CSS class string for the title element.
	 * @param bool   $show_body   Whether to render the text body (false for image_only).
	 * @return void
	 */
	private function render_card_overlay_body( $image, $title, $date_label, $site_name, $desc_text, $show_date, $show_site, $show_desc, $title_class = 'rss-d-title', $show_body = true ) {
		?>
		<img class="rss-d-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
		<span class="rss-d-overlay" aria-hidden="true"></span>
		<?php if ( $show_body ) : ?>
			<?php if ( $show_date && '' !== $date_label ) : ?>
				<span class="rss-d-date"><?php echo esc_html( $date_label ); ?></span>
			<?php endif; ?>
			<?php if ( $show_site && '' !== $site_name ) : ?>
				<span class="rss-d-site"><?php echo esc_html( $site_name ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h3 class="<?php echo esc_attr( $title_class ); ?>"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<?php if ( $show_desc && '' !== $desc_text ) : ?>
				<p class="rss-d-desc"><?php echo esc_html( $desc_text ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Returns the default image URL.
	 * Priority: Media Library selection → direct URL → bundled placeholder.
	 *
	 * @param array $settings Plugin settings array.
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
