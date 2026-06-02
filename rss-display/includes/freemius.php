<?php
/**
 * Freemius SDK ブートストラップとヘルパー関数。
 *
 * @package RSS_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RSS_D_FREE_MAX_FEEDS',   3 );
define( 'RSS_D_FREE_MAX_COLUMNS', 2 );
define( 'RSS_D_FREE_MAX_COUNT',   20 );
define( 'RSS_D_FREE_TYPES',   array( 'grid', 'list_vertical', 'text', 'text_line' ) );
define( 'RSS_D_FREE_ORDERBY', array( 'date' ) );
define( 'RSS_D_FREE_CACHE_TTL',   86400 );

/**
 * Freemius インスタンスを返す。SDK が未インストールの場合は null。
 *
 * @return Freemius|null
 */
function rss_display_fs() {
	global $rss_display_fs;
	return isset( $rss_display_fs ) ? $rss_display_fs : null;
}

/**
 * 有効な有料ライセンスがあれば true を返す。SDK 不在時は false。
 *
 * @return bool
 */
function rss_display_fs_is_paying() {
	$fs = rss_display_fs();
	return $fs ? $fs->is_paying() : false;
}
