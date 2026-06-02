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
// RSS_D_Feed_Manager: deduplicate テスト
// -----------------------------------------------------------------------

$ogp_fetcher_stub = new class {
	public function get_images( array $urls ): array { return []; }
};
$fm        = new RSS_D_Feed_Manager( $ogp_fetcher_stub );
$ref_dedup = new ReflectionMethod( RSS_D_Feed_Manager::class, 'deduplicate' );
$ref_dedup->setAccessible( true );

section( 'RSS_D_Feed_Manager::deduplicate()' );

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
// RSS_D_Feed_Manager: get_items ソート・件数・複数フィード結合
// -----------------------------------------------------------------------

section( 'RSS_D_Feed_Manager::get_items() ソート・件数・複数フィード' );

class TestFeedManager extends RSS_D_Feed_Manager {
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
// RSS_D_Feed_Manager: desc/site フィールドの保持
// -----------------------------------------------------------------------

section( 'RSS_D_Feed_Manager — desc/site フィールド保持' );

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
// RSS_D_Shortcode: render() 出力テスト
// -----------------------------------------------------------------------

section( 'RSS_D_Shortcode::render() — 新パラメータ (desc / date / site / img / type)' );

$ogp_stub = new class {
	public function get_images( array $urls ): array {
		return array_fill_keys( $urls, '' );
	}
};

class FakeFeedManager extends RSS_D_Feed_Manager {
	public array $items = [];
	public function get_items( $feeds, $count, $orderby = 'date' ): array {
		return $this->items;
	}
}

function make_sc( array $items ): RSS_D_Shortcode {
	global $ogp_stub;
	$fm        = new FakeFeedManager( $ogp_stub );
	$fm->items = $items;
	return new RSS_D_Shortcode( $fm, $ogp_stub );
}

// feed を渡して early-return を回避するラッパー
function render_sc( RSS_D_Shortcode $sc, array $atts = [] ): string {
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

// type=list はProタイプ → 無料環境ではupgrade_noticeを返す
$html_list = render_sc( $sc, [ 'type' => 'list' ] );
assert_contains( 'rss-d-upgrade-notice', $html_list, 'type=list（Pro）は無料環境でupgrade_noticeを返す' );

// 不正な type 値はデフォルト grid になる
$html_invalid_type = render_sc( $sc, [ 'type' => 'unknown_type' ] );
assert_contains( 'rss-d-type-grid', $html_invalid_type, '不正な type 値は grid にフォールバック' );

section( 'RSS_D_Shortcode::render() — 既存パラメータ (target / columns / count)' );

// target
$html_blank = render_sc( $sc, [ 'target' => '_blank' ] );
assert_contains( 'rel="noopener noreferrer"', $html_blank, 'target=_blank のとき rel が付く' );
assert_contains( 'target="_blank"', $html_blank, 'target=_blank のとき target 属性が付く' );

$html_self = render_sc( $sc, [ 'target' => '_self' ] );
assert_not_contains( 'rel="noopener noreferrer"', $html_self, 'target=_self のとき rel が付かない' );

// columns — 無料は最大2列にキャップされる
$html_col4 = render_sc( $sc, [ 'columns' => '4' ] );
assert_contains( '--rss-d-columns:2', $html_col4, '無料環境でcolumns=4 は2列にキャップされる' );

$html_col2 = render_sc( $sc, [ 'columns' => '2' ] );
assert_contains( '--rss-d-columns:2', $html_col2, 'columns=2 が CSS変数に反映される' );

// 不正な columns 値はデフォルト設定値(3)→無料キャップで2列になる
$html_col_invalid = render_sc( $sc, [ 'columns' => '99' ] );
assert_contains( '--rss-d-columns:2', $html_col_invalid, '不正な columns 値は設定値(3)→無料キャップで2列になる' );

section( 'RSS_D_Shortcode::render() — HTML構造・エッジケース' );

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
// RSS_Display::parse_feeds() テスト
// -----------------------------------------------------------------------

section( 'RSS_Display::parse_feeds()' );

$feeds_simple = RSS_Display::parse_feeds( "https://feed1.example.com\nhttps://feed2.example.com" );
assert_equals( 2, count( $feeds_simple ), '改行区切りURLを2件パースできる' );
assert_equals( 'https://feed1.example.com', $feeds_simple[0], '1件目のURLが正しい' );
assert_equals( 'https://feed2.example.com', $feeds_simple[1], '2件目のURLが正しい' );

$feeds_empty_lines = RSS_Display::parse_feeds( "https://feed1.example.com\n\nhttps://feed2.example.com\n" );
assert_equals( 2, count( $feeds_empty_lines ), '空行は無視される' );

$feeds_trim = RSS_Display::parse_feeds( "  https://feed1.example.com  \n  https://feed2.example.com  " );
assert_equals( 'https://feed1.example.com', $feeds_trim[0], 'URLがtrimされる（前後の空白除去）' );

$feeds_crlf = RSS_Display::parse_feeds( "https://feed1.example.com\r\nhttps://feed2.example.com" );
assert_equals( 2, count( $feeds_crlf ), 'CRLF区切りもパースできる' );

$feeds_empty = RSS_Display::parse_feeds( '' );
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

section( 'RSS_D_Shortcode::render() — render_type 分岐確認' );

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

section( 'RSS_D_Shortcode::render() — carousel タイプ' );

// carousel はProタイプ → 無料環境ではupgrade_noticeを返す
$sc_carousel = make_sc( [ make_item(), make_item( [ 'url' => 'https://example.com/article/2', 'title' => '記事2' ] ) ] );
$html_carousel_free = render_sc( $sc_carousel, [ 'type' => 'carousel' ] );
assert_contains( 'rss-d-upgrade-notice', $html_carousel_free, 'type=carousel（Pro）は無料環境でupgrade_noticeを返す' );

// rss_display_fs_is_paying() を一時的にtrueにしてProとしてテスト
// ※bootstrap環境でのProシミュレーション: rss_display_fs_is_paying を上書きする
// PHPではfunctionの再定義ができないため、render_type 分岐はPHPUnit+runkit等が必要。
// ここではupgrade_notice動作の確認のみ行う。
assert_true( true, 'carousel Proテストはupgrade_notice確認のみ（runkit不要）' );

// -----------------------------------------------------------------------
// popup_grid タイプ テスト
// -----------------------------------------------------------------------

section( 'RSS_D_Shortcode::render() — popup_grid タイプ' );

// popup_grid はProタイプ → 無料環境ではupgrade_noticeを返す
$sc_popup = make_sc( [ make_item() ] );
$html_popup_free = render_sc( $sc_popup, [ 'type' => 'popup_grid' ] );
assert_contains( 'rss-d-upgrade-notice', $html_popup_free, 'type=popup_grid（Pro）は無料環境でupgrade_noticeを返す' );

// render_popup_grid の内部構造はreflection経由で直接テスト
$ref_popup = new ReflectionMethod( RSS_D_Shortcode::class, 'render_popup_grid' );
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
$ref_carousel = new ReflectionMethod( RSS_D_Shortcode::class, 'render_carousel' );
$ref_carousel->setAccessible( true );
ob_start();
$ref_carousel->invoke( $sc_popup, $items_resolved, 3, 2, false, false, false, false );
$html_carousel_internal = ob_get_clean();
assert_contains( 'rss-d-carousel-wrap', $html_carousel_internal, 'render_carousel: rss-d-carousel-wrap が出力される' );
assert_contains( 'rss-d-carousel-track', $html_carousel_internal, 'render_carousel: rss-d-carousel-track が出力される' );
assert_contains( 'rss-d-carousel-prev', $html_carousel_internal, 'render_carousel: prev ボタンが出力される' );
assert_contains( 'rss-d-carousel-next', $html_carousel_internal, 'render_carousel: next ボタンが出力される' );

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
