<?php
/**
 * シンプルなテストランナー（PHPUnit不要）。
 * 実行: php tests/run.php
 */

require_once __DIR__ . '/bootstrap.php';

// -----------------------------------------------------------------------
// テストヘルパー
// -----------------------------------------------------------------------

$pass   = 0;
$fail   = 0;
$errors = [];

function assert_equals( $expected, $actual, string $label ): void {
	global $pass, $fail, $errors;
	if ( $expected === $actual ) {
		echo "\033[32m  ✔ {$label}\033[0m\n";
		$pass++;
	} else {
		echo "\033[31m  ✘ {$label}\033[0m\n";
		echo "      expected: " . var_export( $expected, true ) . "\n";
		echo "      actual:   " . var_export( $actual, true ) . "\n";
		$fail++;
		$errors[] = $label;
	}
}

function assert_true( bool $v, string $label ): void {
	assert_equals( true, $v, $label );
}

function assert_false( bool $v, string $label ): void {
	assert_equals( false, $v, $label );
}

function assert_contains( string $needle, string $haystack, string $label ): void {
	global $pass, $fail, $errors;
	if ( false !== strpos( $haystack, $needle ) ) {
		echo "\033[32m  ✔ {$label}\033[0m\n";
		$pass++;
	} else {
		echo "\033[31m  ✘ {$label}\033[0m\n";
		echo "      needle not found: " . var_export( $needle, true ) . "\n";
		$fail++;
		$errors[] = $label;
	}
}

function assert_not_contains( string $needle, string $haystack, string $label ): void {
	global $pass, $fail, $errors;
	if ( false === strpos( $haystack, $needle ) ) {
		echo "\033[32m  ✔ {$label}\033[0m\n";
		$pass++;
	} else {
		echo "\033[31m  ✘ {$label}\033[0m\n";
		echo "      needle should not be present: " . var_export( $needle, true ) . "\n";
		$fail++;
		$errors[] = $label;
	}
}

function section( string $title ): void {
	echo "\n\033[1m{$title}\033[0m\n";
}

// -----------------------------------------------------------------------
// テスト用ダミーデータ生成
// -----------------------------------------------------------------------

function make_item( array $override = [] ): array {
	return array_merge(
		[
			'url'       => 'https://example.com/article/1',
			'title'     => 'テスト記事タイトル',
			'timestamp' => mktime( 12, 0, 0, 6, 1, 2026 ),
			'image'     => 'https://example.com/img.jpg',
			'desc'      => 'これはテスト用の説明文ですわ。',
			'site'      => 'テストサイト',
		],
		$override
	);
}

// -----------------------------------------------------------------------
// RSSECAFO_Feed_Manager: deduplicate テスト
// -----------------------------------------------------------------------

$fm        = new RSSECAFO_Feed_Manager();
$ref_dedup = new ReflectionMethod( RSSECAFO_Feed_Manager::class, 'deduplicate' );
$ref_dedup->setAccessible( true );

section( 'RSSECAFO_Feed_Manager::deduplicate()' );

// 重複URLの集約
$items_dup = [
	make_item( [ 'url' => 'https://example.com/a', 'timestamp' => 1000 ] ),
	make_item( [ 'url' => 'https://example.com/a', 'timestamp' => 2000 ] ),
	make_item( [ 'url' => 'https://example.com/b', 'timestamp' => 500 ] ),
];
$result = $ref_dedup->invoke( $fm, $items_dup );
assert_equals( 2, count( $result ), '重複URLは1件に集約される' );

$a_item = array_values( array_filter( $result, fn( $i ) => $i['url'] === 'https://example.com/a' ) );
assert_equals( 2000, $a_item[0]['timestamp'], '重複時は新しいtimestampが残る' );

// URLなしアイテムはユニーク扱い
$items_nourl = [
	make_item( [ 'url' => '', 'timestamp' => 100 ] ),
	make_item( [ 'url' => '', 'timestamp' => 200 ] ),
];
$result_nourl = $ref_dedup->invoke( $fm, $items_nourl );
assert_equals( 2, count( $result_nourl ), 'URLなしアイテムは重複排除しない' );

// 同一URLが3件以上あっても最新1件のみ残る
$items_triple = [
	make_item( [ 'url' => 'https://example.com/x', 'timestamp' => 100 ] ),
	make_item( [ 'url' => 'https://example.com/x', 'timestamp' => 300 ] ),
	make_item( [ 'url' => 'https://example.com/x', 'timestamp' => 200 ] ),
];
$result_triple = $ref_dedup->invoke( $fm, $items_triple );
assert_equals( 1, count( $result_triple ), '3件重複は1件に集約される' );
assert_equals( 300, $result_triple[0]['timestamp'], '3件重複でも最新timestampが残る' );

// -----------------------------------------------------------------------
// RSSECAFO_Feed_Manager: get_items ソート・件数・複数フィード結合
// -----------------------------------------------------------------------

section( 'RSSECAFO_Feed_Manager::get_items() ソート・件数・複数フィード' );

class TestFeedManager extends RSSECAFO_Feed_Manager {
	public array $fake_items = [];

	public function get_feed_payload( $feed_url ): array {
		return [
			'fetched' => time(),
			'items'   => $this->fake_items,
			'count'   => count( $this->fake_items ),
			'error'   => false,
		];
	}
}

$tfm            = new TestFeedManager( $ogp_fetcher_stub );
$tfm->fake_items = [
	make_item( [ 'url' => 'https://example.com/old', 'timestamp' => 1000 ] ),
	make_item( [ 'url' => 'https://example.com/new', 'timestamp' => 9999 ] ),
	make_item( [ 'url' => 'https://example.com/mid', 'timestamp' => 5000 ] ),
];

$sorted = $tfm->get_items( [ 'https://dummy.feed/' ], 10, 'date' );
assert_equals( 'https://example.com/new', $sorted[0]['url'], 'date順: 最新が先頭' );
assert_equals( 'https://example.com/old', $sorted[2]['url'], 'date順: 最古が末尾' );

$limited = $tfm->get_items( [ 'https://dummy.feed/' ], 2, 'date' );
assert_equals( 2, count( $limited ), 'count=2 で2件に制限される' );

// count=0 は全件返す（absint(0)=0 → スライスしない）
$all_items = $tfm->get_items( [ 'https://dummy.feed/' ], 0, 'date' );
assert_equals( 3, count( $all_items ), 'count=0 で全件返す' );

// 複数フィードを渡すと結合・重複排除される
$tfm2            = new TestFeedManager( $ogp_fetcher_stub );
$tfm2->fake_items = [
	make_item( [ 'url' => 'https://example.com/shared', 'timestamp' => 1000 ] ),
	make_item( [ 'url' => 'https://example.com/unique', 'timestamp' => 2000 ] ),
];
$merged = $tfm2->get_items( [ 'https://feed1/', 'https://feed2/' ], 10, 'date' );
// 同じfake_itemsが2フィード分返ってくるが、URLで重複排除されて2件になる
assert_equals( 2, count( $merged ), '複数フィードの同一URLは重複排除される' );

// -----------------------------------------------------------------------
// RSSECAFO_Feed_Manager: desc/site フィールドの保持
// -----------------------------------------------------------------------

section( 'RSSECAFO_Feed_Manager — desc/site フィールド保持' );

$tfm->fake_items = [
	make_item( [ 'desc' => '説明文ですわ', 'site' => 'サイト名ですわ' ] ),
];
$result_fields = $tfm->get_items( [ 'https://dummy.feed/' ], 10, 'date' );
assert_equals( '説明文ですわ', $result_fields[0]['desc'], 'desc フィールドが保持される' );
assert_equals( 'サイト名ですわ', $result_fields[0]['site'], 'site フィールドが保持される' );

// desc が空でも配列キーは存在する
$tfm->fake_items = [ make_item( [ 'desc' => '' ] ) ];
$result_empty_desc = $tfm->get_items( [ 'https://dummy.feed/' ], 10, 'date' );
assert_true( array_key_exists( 'desc', $result_empty_desc[0] ), 'desc キーが空でも存在する' );
assert_true( array_key_exists( 'site', $result_empty_desc[0] ), 'site キーが常に存在する' );

// -----------------------------------------------------------------------
// RSSECAFO_Shortcode: render() 出力テスト
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — 新パラメータ (desc / date / site / img / type)' );

$ogp_stub = new class {
	public function get_images( array $urls ): array {
		return array_fill_keys( $urls, '' );
	}
};

class FakeFeedManager extends RSSECAFO_Feed_Manager {
	public array $items = [];
	public function get_items( $feeds, $count, $orderby = 'date' ): array {
		return $this->items;
	}
}

function make_sc( array $items ): RSSECAFO_Shortcode {
	global $ogp_stub;
	$fm        = new FakeFeedManager( $ogp_stub );
	$fm->items = $items;
	return new RSSECAFO_Shortcode( $fm, $ogp_stub );
}

// feed を渡して early-return を回避するラッパー
function render_sc( RSSECAFO_Shortcode $sc, array $atts = [] ): string {
	return $sc->render( array_merge( [ 'feed' => 'https://dummy.feed/' ], $atts ) );
}

$sc = make_sc( [ make_item() ] );

// desc
$html_no_desc = render_sc( $sc, [ 'desc' => '0' ] );
assert_not_contains( 'rss-d-desc', $html_no_desc, 'desc=0 のとき .rss-d-desc が出力されない' );

$html_with_desc = render_sc( $sc, [ 'desc' => '1' ] );
assert_contains( 'rss-d-desc', $html_with_desc, 'desc=1 のとき .rss-d-desc が出力される' );
assert_contains( 'これはテスト用の説明文ですわ', $html_with_desc, 'desc=1 のとき説明文テキストが出力される' );

// date
$html_with_date = render_sc( $sc, [ 'date' => '1' ] );
assert_contains( 'rss-d-date', $html_with_date, 'date=1 のとき .rss-d-date が出力される' );

$html_no_date = render_sc( $sc, [ 'date' => '0' ] );
assert_not_contains( 'rss-d-date', $html_no_date, 'date=0 のとき .rss-d-date が出力されない' );

// site
$html_no_site = render_sc( $sc, [ 'site' => '0' ] );
assert_not_contains( 'rss-d-site', $html_no_site, 'site=0 のとき .rss-d-site が出力されない' );

$html_with_site = render_sc( $sc, [ 'site' => '1' ] );
assert_contains( 'rss-d-site', $html_with_site, 'site=1 のとき .rss-d-site が出力される' );
assert_contains( 'テストサイト', $html_with_site, 'site=1 のときサイト名テキストが出力される' );

// img
$sc_no_img        = make_sc( [ make_item( [ 'image' => '' ] ) ] );
$html_custom_img  = render_sc( $sc_no_img, [ 'img' => 'https://custom.example.com/img.png' ] );
assert_contains( 'https://custom.example.com/img.png', $html_custom_img, 'img パラメータの画像URLがsrcに使われる' );

// img 指定なしかつRSS画像なし → placeholderが使われる
$html_placeholder = render_sc( $sc_no_img );
assert_contains( 'placeholder.png', $html_placeholder, 'img省略・RSS画像なしはplaceholderにフォールバック' );

// type
$html_grid = render_sc( $sc );
assert_contains( 'rss-d-type-grid', $html_grid, 'type 省略時は rss-d-type-grid クラスが付く' );
assert_not_contains( 'rss-d-card-body', $html_grid, 'type=grid では .rss-d-card-body が出力されない' );

// 不正な type 値はデフォルト grid になる
$html_invalid_type = render_sc( $sc, [ 'type' => 'unknown_type' ] );
assert_contains( 'rss-d-type-grid', $html_invalid_type, '不正な type 値は grid にフォールバック' );

section( 'RSSECAFO_Shortcode::render() — 既存パラメータ (target / columns / count)' );

// target
$html_blank = render_sc( $sc, [ 'target' => '_blank' ] );
assert_contains( 'rel="noopener noreferrer"', $html_blank, 'target=_blank のとき rel が付く' );
assert_contains( 'target="_blank"', $html_blank, 'target=_blank のとき target 属性が付く' );

$html_self = render_sc( $sc, [ 'target' => '_self' ] );
assert_not_contains( 'rel="noopener noreferrer"', $html_self, 'target=_self のとき rel が付かない' );

$html_col2 = render_sc( $sc, [ 'columns' => '2' ] );
assert_contains( '--rss-d-columns:2', $html_col2, 'columns=2 が CSS変数に反映される' );

$html_col4 = render_sc( $sc, [ 'columns' => '4' ] );
assert_contains( '--rss-d-columns:4', $html_col4, 'columns=4 が CSS変数に反映される' );

$html_col3 = render_sc( $sc, [ 'columns' => '3' ] );
assert_contains( '--rss-d-columns:3', $html_col3, 'columns=3 が CSS変数に反映される' );

// 不正な columns 値はデフォルト設定値(3)にフォールバック
$html_col_invalid = render_sc( $sc, [ 'columns' => '99' ] );
assert_contains( '--rss-d-columns:3', $html_col_invalid, '不正な columns 値は設定値(3)にフォールバック' );

section( 'RSSECAFO_Shortcode::render() — HTML構造・エッジケース' );

// URL なしアイテム: <div> タグで出力
$sc_nourl  = make_sc( [ make_item( [ 'url' => '' ] ) ] );
$html_nourl = render_sc( $sc_nourl );
assert_contains( '<div class="rss-d-card">', $html_nourl, 'URL なしアイテムは <div> で出力' );
assert_not_contains( '<a class="rss-d-card"', $html_nourl, 'URL なしアイテムは <a> を使わない' );

// タイムスタンプ0のアイテム: 日付出力なし
$sc_no_ts   = make_sc( [ make_item( [ 'timestamp' => 0 ] ) ] );
$html_no_ts = render_sc( $sc_no_ts, [ 'date' => '1' ] );
assert_not_contains( 'rss-d-date', $html_no_ts, 'timestamp=0 のとき date=1 でも日付が出ない' );

// タイトルなし: h3 タグが出力されない
$sc_no_title   = make_sc( [ make_item( [ 'title' => '' ] ) ] );
$html_no_title = render_sc( $sc_no_title );
assert_not_contains( 'class="rss-d-title"', $html_no_title, 'title が空のとき .rss-d-title が出力されない' );

// XSS対策: タイトルの特殊文字がエスケープされる
$sc_xss   = make_sc( [ make_item( [ 'title' => '<script>alert("xss")</script>' ] ) ] );
$html_xss = render_sc( $sc_xss );
assert_not_contains( '<script>', $html_xss, 'タイトル内の <script> タグがエスケープされる' );
assert_contains( '&lt;script&gt;', $html_xss, 'タイトル内の < が &lt; にエスケープされる' );

// XSS対策: desc の特殊文字がエスケープされる
$sc_xss_desc   = make_sc( [ make_item( [ 'desc' => '<b>bold</b>' ] ) ] );
$html_xss_desc = render_sc( $sc_xss_desc, [ 'desc' => '1' ] );
assert_not_contains( '<b>', $html_xss_desc, 'desc 内の <b> タグがエスケープされる' );

// アイテムが0件のとき空文字を返す
$sc_empty   = make_sc( [] );
$html_empty = render_sc( $sc_empty );
assert_equals( '', $html_empty, 'アイテムが0件のとき空文字を返す' );

// 画像フォールバック優先順位: RSS画像 > OGPキャッシュ > img パラメータ > placeholder
// RSS画像ありのとき img パラメータは使われない
$sc_rss_img      = make_sc( [ make_item( [ 'image' => 'https://rss.example.com/rss.jpg' ] ) ] );
$html_rss_img    = render_sc( $sc_rss_img, [ 'img' => 'https://custom.example.com/custom.jpg' ] );
assert_contains( 'https://rss.example.com/rss.jpg', $html_rss_img, 'RSS画像があるとき RSS画像が優先される' );
assert_not_contains( 'https://custom.example.com/custom.jpg', $html_rss_img, 'RSS画像があるとき img パラメータは使われない' );

// list_vertical タイプ: site/title/desc/date が card-body 内に出力される（list_verticalは無料タイプ）
$html_list_full = render_sc(
	make_sc( [ make_item() ] ),
	[ 'type' => 'list_vertical', 'site' => '1', 'desc' => '1', 'date' => '1' ]
);
$body_pos  = strpos( $html_list_full, 'rss-d-card-body' );
$site_pos  = strpos( $html_list_full, 'rss-d-site"' );
$title_pos = strpos( $html_list_full, 'rss-d-title"' );
$desc_pos  = strpos( $html_list_full, 'rss-d-desc"' );
$date_pos  = strpos( $html_list_full, 'rss-d-date"' );
assert_true( $site_pos > $body_pos, 'list_vertical タイプ: site が card-body の後に出力される' );
assert_true( $title_pos > $body_pos, 'list_vertical タイプ: title が card-body の後に出力される' );
assert_true( $desc_pos > $body_pos, 'list_vertical タイプ: desc が card-body の後に出力される' );
assert_true( $date_pos > $body_pos, 'list_vertical タイプ: date が card-body の後に出力される' );

// -----------------------------------------------------------------------
// RSSECAFO_Plugin::parse_feeds() テスト
// -----------------------------------------------------------------------

section( 'RSSECAFO_Plugin::parse_feeds()' );

$feeds_simple = RSSECAFO_Plugin::parse_feeds( "https://feed1.example.com\nhttps://feed2.example.com" );
assert_equals( 2, count( $feeds_simple ), '改行区切りURLを2件パースできる' );
assert_equals( 'https://feed1.example.com', $feeds_simple[0], '1件目のURLが正しい' );
assert_equals( 'https://feed2.example.com', $feeds_simple[1], '2件目のURLが正しい' );

$feeds_empty_lines = RSSECAFO_Plugin::parse_feeds( "https://feed1.example.com\n\nhttps://feed2.example.com\n" );
assert_equals( 2, count( $feeds_empty_lines ), '空行は無視される' );

$feeds_trim = RSSECAFO_Plugin::parse_feeds( "  https://feed1.example.com  \n  https://feed2.example.com  " );
assert_equals( 'https://feed1.example.com', $feeds_trim[0], 'URLがtrimされる（前後の空白除去）' );

$feeds_crlf = RSSECAFO_Plugin::parse_feeds( "https://feed1.example.com\r\nhttps://feed2.example.com" );
assert_equals( 2, count( $feeds_crlf ), 'CRLF区切りもパースできる' );

$feeds_empty = RSSECAFO_Plugin::parse_feeds( '' );
assert_equals( 0, count( $feeds_empty ), '空文字は空配列を返す' );

// -----------------------------------------------------------------------
// グローバル設定の type デフォルト反映テスト
// -----------------------------------------------------------------------

section( 'グローバル設定 type のデフォルト反映' );

// bootstrap.php の get_settings() が type='grid' を返すので
// atts 未指定時は grid になるはず
$sc_default_type = make_sc( [ make_item() ] );
$html_default_type = render_sc( $sc_default_type );
assert_contains( 'rss-d-type-grid', $html_default_type, 'get_settings() の type=grid が atts未指定時のデフォルトになる' );

// -----------------------------------------------------------------------
// render_type の分岐確認（追加タイプ）
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — render_type 分岐確認' );

$sc_types = make_sc( [ make_item() ] );

$html_list_vertical = render_sc( $sc_types, [ 'type' => 'list_vertical' ] );
assert_contains( 'rss-d-type-list_vertical', $html_list_vertical, 'type=list_vertical のとき rss-d-type-list_vertical クラスが出力される' );

$html_text = render_sc( $sc_types, [ 'type' => 'text' ] );
assert_contains( 'rss-d-type-text', $html_text, 'type=text のとき rss-d-type-text クラスが出力される' );

$html_text_line = render_sc( $sc_types, [ 'type' => 'text_line' ] );
assert_contains( 'rss-d-type-text_line', $html_text_line, 'type=text_line のとき rss-d-type-text_line クラスが出力される' );

// -----------------------------------------------------------------------
// carousel タイプ テスト
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — carousel タイプ' );

$sc_carousel = make_sc( [ make_item(), make_item( [ 'url' => 'https://example.com/article/2', 'title' => '記事2' ] ) ] );
$html_carousel = render_sc( $sc_carousel, [ 'type' => 'carousel' ] );
assert_contains( 'rss-d-type-carousel', $html_carousel, 'type=carousel のとき rss-d-type-carousel クラスが出力される' );
assert_contains( 'rss-d-carousel-wrap', $html_carousel, 'carousel: rss-d-carousel-wrap が出力される' );
assert_contains( 'rss-d-carousel-prev', $html_carousel, 'carousel: prev ボタンが出力される' );
assert_contains( 'rss-d-carousel-next', $html_carousel, 'carousel: next ボタンが出力される' );

// -----------------------------------------------------------------------
// popup_grid タイプ テスト
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — popup_grid タイプ' );

$sc_popup = make_sc( [ make_item() ] );

// render_popup_grid の内部構造はreflection経由で直接テスト
$ref_popup = new ReflectionMethod( RSSECAFO_Shortcode::class, 'render_popup_grid' );
$ref_popup->setAccessible( true );
$items_resolved = [
	[
		'title'      => 'テスト記事',
		'url'        => 'https://example.com/article/1',
		'_image'     => 'https://example.com/img.jpg',
		'_date_label'=> '2026-06-02',
		'_desc_text' => '説明文',
		'_site_name' => 'テストサイト',
	]
];
ob_start();
$ref_popup->invoke( $sc_popup, $items_resolved, 3, 2, false );
$html_popup_internal = ob_get_clean();

assert_contains( 'rss-d-type-popup_grid', $html_popup_internal, 'render_popup_grid: rss-d-type-popup_grid クラスが出力される' );
assert_contains( 'rss-d-popup-trigger', $html_popup_internal, 'render_popup_grid: .rss-d-popup-trigger ボタンが出力される' );
assert_contains( 'data-title=', $html_popup_internal, 'render_popup_grid: data-title 属性が出力される' );
assert_contains( 'data-image=', $html_popup_internal, 'render_popup_grid: data-image 属性が出力される' );
assert_contains( 'data-link=', $html_popup_internal, 'render_popup_grid: data-link 属性が出力される' );
assert_contains( 'rss-d-modal-overlay', $html_popup_internal, 'render_popup_grid: .rss-d-modal-overlay が出力される' );
assert_contains( 'data-newtab="0"', $html_popup_internal, 'render_popup_grid: new_tab=false のとき data-newtab="0"' );

ob_start();
$ref_popup->invoke( $sc_popup, $items_resolved, 3, 2, true );
$html_popup_blank = ob_get_clean();
assert_contains( 'data-newtab="1"', $html_popup_blank, 'render_popup_grid: new_tab=true のとき data-newtab="1"' );

// render_carousel も同様にreflection経由でテスト
$ref_carousel = new ReflectionMethod( RSSECAFO_Shortcode::class, 'render_carousel' );
$ref_carousel->setAccessible( true );
ob_start();
$ref_carousel->invoke( $sc_popup, $items_resolved, 3, 2, false, false, false, false );
$html_carousel_internal = ob_get_clean();
assert_contains( 'rss-d-carousel-wrap', $html_carousel_internal, 'render_carousel: rss-d-carousel-wrap が出力される' );
assert_contains( 'rss-d-carousel-track', $html_carousel_internal, 'render_carousel: rss-d-carousel-track が出力される' );
assert_contains( 'rss-d-carousel-prev', $html_carousel_internal, 'render_carousel: prev ボタンが出力される' );
assert_contains( 'rss-d-carousel-next', $html_carousel_internal, 'render_carousel: next ボタンが出力される' );

// -----------------------------------------------------------------------
// i18n 回帰テスト
// -----------------------------------------------------------------------

section( 'i18n: プラグインヘッダー宣言' );

$plugin_file = file_get_contents( __DIR__ . '/../rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php' );
assert_contains( 'Text Domain:',                 $plugin_file, 'ヘッダーに Text Domain が宣言されている' );
assert_contains( 'rssemble-cards-for-rss-feeds', $plugin_file, 'Text Domain が正しいスラグ値になっている' );
assert_contains( 'Domain Path:',                 $plugin_file, 'ヘッダーに Domain Path が宣言されている' );
assert_contains( '/languages',                   $plugin_file, 'Domain Path が /languages になっている' );

section( 'i18n: .pot ファイル' );

$pot_path = __DIR__ . '/../rssemble-cards-for-rss-feeds/languages/rssemble-cards-for-rss-feeds.pot';
assert_true( file_exists( $pot_path ), '.pot ファイルが存在する' );
if ( file_exists( $pot_path ) ) {
	$pot = file_get_contents( $pot_path );
	assert_contains( 'msgid',                        $pot, '.pot に msgid エントリが含まれる' );
	assert_contains( 'rssemble-cards-for-rss-feeds', $pot, '.pot にテキストドメインが含まれる' );
}

section( 'i18n: 日本語 .mo / .po ファイル' );

$mo_path = __DIR__ . '/../rssemble-cards-for-rss-feeds/languages/rssemble-cards-for-rss-feeds-ja.mo';
$po_path = __DIR__ . '/../rssemble-cards-for-rss-feeds/languages/rssemble-cards-for-rss-feeds-ja.po';
assert_true( file_exists( $mo_path ), '日本語 .mo ファイルが存在する' );
assert_true( file_exists( $po_path ), '日本語 .po ファイルが存在する' );
if ( file_exists( $mo_path ) && file_exists( $po_path ) ) {
	assert_true( filemtime( $mo_path ) >= filemtime( $po_path ), '.mo が .po より新しい（再コンパイル済み）' );
}

section( 'i18n: テキストドメイン統一チェック' );

$php_files         = glob( __DIR__ . '/../rssemble-cards-for-rss-feeds/includes/*.php' );
$wrong_domain_found = false;
$wrong_domain_file  = '';
foreach ( $php_files as $f ) {
	$src = file_get_contents( $f );
	if ( preg_match( '/(?:esc_html__|esc_attr__|esc_html_e|_e|__)\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"](?!rssemble-cards-for-rss-feeds)[^\'"]+[\'"]\s*\)/s', $src ) ) {
		$wrong_domain_found = true;
		$wrong_domain_file  = basename( $f );
		break;
	}
}
assert_false( $wrong_domain_found, 'includes/*.php 内の __() は全て正しいテキストドメインを使っている' . ( $wrong_domain_file ? " (問題ファイル: {$wrong_domain_file})" : '' ) );

// -----------------------------------------------------------------------
// bold / responsive / title_lines パラメータ
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — bold / responsive / title_lines' );

$sc_bold = make_sc( [ make_item() ] );

// bold=1 → rss-d-title--bold クラスが付く
$html_bold = render_sc( $sc_bold, [ 'bold' => '1' ] );
assert_contains( 'rss-d-title--bold', $html_bold, 'bold=1 のとき rss-d-title--bold クラスが出力される' );

// bold=0（デフォルト）→ rss-d-title--bold クラスが付かない
$html_no_bold = render_sc( $sc_bold, [ 'bold' => '0' ] );
assert_not_contains( 'rss-d-title--bold', $html_no_bold, 'bold=0 のとき rss-d-title--bold クラスが出力されない' );

// responsive=1（デフォルト）→ rss-d-responsive クラスが付く
$html_responsive = render_sc( $sc_bold, [ 'responsive' => '1' ] );
assert_contains( 'rss-d-responsive', $html_responsive, 'responsive=1 のとき rss-d-responsive クラスが出力される' );

// responsive=0 → rss-d-responsive クラスが付かない
$html_no_responsive = render_sc( $sc_bold, [ 'responsive' => '0' ] );
assert_not_contains( 'rss-d-responsive', $html_no_responsive, 'responsive=0 のとき rss-d-responsive クラスが出力されない' );

// title_lines=3 → CSS変数に反映される
$html_title_lines = render_sc( $sc_bold, [ 'title_lines' => '3' ] );
assert_contains( '--rss-d-title-lines:3', $html_title_lines, 'title_lines=3 が CSS変数に反映される' );

// title_lines が範囲外（負）→ 2 にクランプ
$html_title_lines_neg = render_sc( $sc_bold, [ 'title_lines' => '-1' ] );
assert_contains( '--rss-d-title-lines:2', $html_title_lines_neg, 'title_lines=-1 は 2 にクランプされる' );

// title_lines が範囲外（11）→ 2 にクランプ
$html_title_lines_over = render_sc( $sc_bold, [ 'title_lines' => '11' ] );
assert_contains( '--rss-d-title-lines:2', $html_title_lines_over, 'title_lines=11 は 2 にクランプされる' );

// -----------------------------------------------------------------------
// card_size / text_size パラメータ
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — card_size / text_size' );

// card_size=small → --rss-d-card-scale:0.8 が出力される
$html_card_small = render_sc( $sc_bold, [ 'card_size' => 'small' ] );
assert_contains( '--rss-d-card-scale:0.8', $html_card_small, 'card_size=small は --rss-d-card-scale:0.8 になる' );

// card_size=large → --rss-d-card-scale:1.25 が出力される
$html_card_large = render_sc( $sc_bold, [ 'card_size' => 'large' ] );
assert_contains( '--rss-d-card-scale:1.25', $html_card_large, 'card_size=large は --rss-d-card-scale:1.25 になる' );

// card_size 不正値 → medium（1）にフォールバック
$html_card_invalid = render_sc( $sc_bold, [ 'card_size' => 'huge' ] );
assert_contains( '--rss-d-card-scale:1', $html_card_invalid, '不正な card_size は medium(1) にフォールバックされる' );

// text_size=small → --rss-d-text-scale:0.8 が出力される
$html_text_small = render_sc( $sc_bold, [ 'text_size' => 'small' ] );
assert_contains( '--rss-d-text-scale:0.8', $html_text_small, 'text_size=small は --rss-d-text-scale:0.8 になる' );

// text_size=large → --rss-d-text-scale:1.25 が出力される
$html_text_large = render_sc( $sc_bold, [ 'text_size' => 'large' ] );
assert_contains( '--rss-d-text-scale:1.25', $html_text_large, 'text_size=large は --rss-d-text-scale:1.25 になる' );

// -----------------------------------------------------------------------
// popup_grid — .rss-d-wrap 構造とモーダル位置
// -----------------------------------------------------------------------

section( 'RSSECAFO_Shortcode::render() — popup_grid .rss-d-wrap 構造' );

$sc_popup = make_sc( [ make_item() ] );
$html_popup = render_sc( $sc_popup, [ 'type' => 'popup_grid' ] );

// .rss-d-wrap が出力される
assert_contains( '<div class="rss-d-wrap">', $html_popup, 'popup_grid: .rss-d-wrap が出力される' );

// .rss-d-modal-overlay が .rss-d-wrap の外側（後ろ）にある
$wrap_open  = strpos( $html_popup, '<div class="rss-d-wrap">' );
$wrap_close = strpos( $html_popup, '</div></div>' ); // grid の閉じタグ + wrap の閉じタグ
$modal_pos  = strpos( $html_popup, 'rss-d-modal-overlay' );
assert_true( $wrap_open !== false && $modal_pos !== false && $modal_pos > $wrap_close,
	'popup_grid: .rss-d-modal-overlay が .rss-d-wrap の外側（後）に出力される' );

// -----------------------------------------------------------------------
// RSSECAFO_OGP_Fetcher::normalize_path() — パス正規化
// -----------------------------------------------------------------------

section( 'RSSECAFO_OGP_Fetcher::normalize_path()' );

$ogp = new RSSECAFO_OGP_Fetcher();
$ref = new ReflectionMethod( RSSECAFO_OGP_Fetcher::class, 'normalize_path' );
$ref->setAccessible( true );

// 基本的な .. 解決
assert_equals( '/a/c.jpg', $ref->invoke( $ogp, '/a/b/../c.jpg' ), 'normalize_path: /a/b/../c.jpg → /a/c.jpg' );

// ルートを越える .. はルートで止まる
assert_equals( '/b.jpg', $ref->invoke( $ogp, '/a/../../b.jpg' ), 'normalize_path: /a/../../b.jpg はルートで止まり /b.jpg になる' );
assert_equals( '/foo.png', $ref->invoke( $ogp, '/../foo.png' ), 'normalize_path: /../foo.png → /foo.png（leading / は保持）' );

// . の除去
assert_equals( '/a/b.jpg', $ref->invoke( $ogp, '/a/./b.jpg' ), 'normalize_path: /a/./b.jpg → /a/b.jpg' );

// 複数の .. を連続適用
assert_equals( '/a/d.jpg', $ref->invoke( $ogp, '/a/b/c/../../d.jpg' ), 'normalize_path: /a/b/c/../../d.jpg → /a/d.jpg' );

// ルートのみ
assert_equals( '/', $ref->invoke( $ogp, '/' ), 'normalize_path: / → /' );

// -----------------------------------------------------------------------
// 結果サマリ
// -----------------------------------------------------------------------

echo "\n";
echo str_repeat( '-', 50 ) . "\n";
$total = $pass + $fail;
if ( $fail === 0 ) {
	echo "\033[32m全 {$total} テスト通過\033[0m\n";
} else {
	echo "\033[31m{$fail} / {$total} テスト失敗\033[0m\n";
	foreach ( $errors as $e ) {
		echo "  - {$e}\n";
	}
}
echo str_repeat( '-', 50 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
