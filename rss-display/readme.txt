=== RSS Display ===
Contributors: mori
Tags: rss, feed, display, ogp, grid
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示します。外部サービス依存なし。

== Description ==

RSS Display は、複数のRSSフィードをまとめて取得し、OGP画像を背景にしたカードグリッドで表示するWordPressプラグインです。外部サービスへの依存はなく、WordPress組み込み機能（SimplePie / トランジェント / DOMDocument）のみで動作します。

主な特徴:

* 複数のRSSフィードを統合表示（記事URLで重複排除、新しい日付を優先）
* OGP画像の自動取得（media:content → enclosure → og:image → デフォルト画像 の優先順位）
* カードは画像全面背景・右上に日付・左下にタイトル（16:9固定）
* レスポンシブ対応（PC3列 / タブレット2列 / スマホ1列、列数は設定可能）
* ホバー時の画像ズーム＋影
* リアルタイム取得＋トランジェントキャッシュ（Cron不使用）
* RSSキャッシュ時間はユーザー設定可（12時間 / 1日 / 1週間 / 1ヶ月）
* OGP画像URLは固定1ヶ月キャッシュ（取得失敗の negative cache 含む）
* 取得失敗時は前回キャッシュ（stale）をフォールバック表示
* Object Cache（Redis等）導入環境では自動的に高速化、Cloudflare環境とも相性良好

== Installation ==

1. `rss-display` フォルダを `/wp-content/plugins/` にアップロードします
   （または管理画面の「プラグイン > 新規追加 > プラグインのアップロード」からZIPを直接アップロード）。
2. 管理画面の「プラグイン」からプラグインを有効化します。
3. 「設定 > RSS Display」でフィードURL等を設定します。
4. 投稿・固定ページ・ウィジェットにショートコードを貼り付けます。

== Usage ==

基本:

`[rss_display]`

パラメータ指定:

`[rss_display columns="4" count="8"]`
`[rss_display columns="2" count="6" feed="https://example.com/feed"]`
`[rss_display orderby="random" target="_self"]`

パラメータ一覧:

* columns  : 列数（2 / 3 / 4）。デフォルトは設定値。
* count    : 表示件数。デフォルトは設定値。
* feed     : 特定フィードのみ表示（URL）。デフォルトは全フィード。
* orderby  : ソート順（date / random）。デフォルトは date。
* target   : リンクの開き方（_blank / _self）。デフォルトは設定値。

== Frequently Asked Questions ==

= 画像が表示されません =
RSSに画像が含まれず、記事ページのog:imageも取得できない場合は、
管理画面で指定したデフォルト画像（未設定時は同梱のプレースホルダー）が表示されます。

= キャッシュをすぐに更新したい =
「設定 > RSS Display」の「今すぐ更新」ボタンでRSSキャッシュをクリアできます。
OGP画像キャッシュ（1ヶ月固定）はクリアされません。

= Cron は使いますか =
使いません。ショートコード実行時にキャッシュが無い／古い場合のみ取得します。

== Screenshots ==

1. Card grid display on the front end.
2. Admin settings screen.

== Changelog ==

= 1.0.0 =
* 初回リリース。

== Upgrade Notice ==

= 1.0.0 =
Initial release.
