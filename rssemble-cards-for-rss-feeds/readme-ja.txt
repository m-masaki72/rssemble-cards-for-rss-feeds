=== Rssemble Cards for RSS Feeds ===
Contributors: masakimori
Tags: rss, feed, grid, ogp, cards
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示します。外部サービス依存なし。

== 説明 ==

Rssemble Cards for RSS Feeds は、複数のRSSフィードを取得し、OGP画像付きカードグリッドとして表示するWordPressプラグインです。WordPress組み込み機能（SimplePie・トランジェント・DOMDocument）のみで動作し、外部サービスへの依存は一切ありません。

主な機能:

* 複数フィードの集約・URL重複排除（同一URLは新しい日付を優先）
* OGP画像の自動取得（優先順位: media:content → enclosure → og:image → デフォルト画像）
* 8種類の表示タイプ: grid / list / list_vertical / text / text_line / image_only / carousel / popup_grid
* レスポンシブレイアウト（PC: 3列、タブレット: 2列、スマホ: 1列 ― 設定変更可）
* カードのホバーズームとシャドウエフェクト
* トランジェントベースのキャッシュ（WP-Cron不要）
* RSSキャッシュ期間の設定（12時間 / 1日 / 1週間 / 1ヶ月）
* OGP画像URLは1ヶ月固定キャッシュ（取得失敗のネガティブキャッシュも含む）
* フィード取得失敗時のスタレキャッシュフォールバック
* オブジェクトキャッシュ（Redis、Memcached）およびCloudflare対応

== インストール ==

1. `rssemble-cards-for-rss-feeds` フォルダを `/wp-content/plugins/` にアップロードするか、**プラグイン > 新規追加 > プラグインのアップロード** からインストールしてください。
2. **プラグイン** 画面からプラグインを有効化してください。
3. **設定 > Rssemble Cards** でフィードURLを設定してください。
4. 投稿・固定ページ・ウィジェットにショートコードを追加してください。

== 使い方 ==

基本:

`[rss_display]`

パラメーター指定:

`[rss_display columns="4" count="8"]`
`[rss_display columns="2" count="6" feed="https://example.com/feed"]`
`[rss_display orderby="random" target="_self"]`

パラメーター一覧:

* columns    : カラム数（2 / 3 / 4）。デフォルト: 管理画面設定値。
* count      : 表示件数。デフォルト: 管理画面設定値。
* feed       : フィードURL（複数の場合はカンマ区切り）。デフォルト: 登録済み全フィード。
* orderby    : 並び順（date / random）。デフォルト: date。
* target     : リンクの開き方（_blank / _self）。デフォルト: 管理画面設定値。
* type       : 表示タイプ（grid / list / list_vertical / text / text_line / image_only / carousel / popup_grid）。
* date       : 日付を表示（1 / 0）。デフォルト: 1。
* site       : サイト名を表示（1 / 0）。デフォルト: 0。
* desc       : 説明を表示（1 / 0）。デフォルト: 0。
* bold       : タイトルを太字（1 / 0）。デフォルト: 0。
* responsive : レスポンシブ対応（1 / 0）。デフォルト: 1。
* title_lines: タイトル最大行数（1 / 2 / 3）。デフォルト: 管理画面設定値。
* img        : デフォルト画像URLの上書き。

== よくある質問 ==

= 画像が表示されない =

RSSフィードに画像が含まれておらず、記事の og:image も取得できない場合は、管理画面で設定したデフォルト画像（未設定の場合はプラグイン付属のプレースホルダー画像）が表示されます。

= すぐにキャッシュをクリアするには? =

**設定 > Rssemble Cards** の「今すぐ更新」ボタンをクリックしてください。RSSキャッシュがクリアされます。OGP画像キャッシュ（1ヶ月固定）はクリアされません。

= WP-Cronを使いますか? =

いいえ。フィードはショートコードが実行されたタイミングでキャッシュが存在しない・期限切れの場合にオンデマンドで取得されます。

== スクリーンショット ==

1. フロントエンドのカードグリッド表示。
2. 管理画面の設定画面。

== 変更履歴 ==

= 1.0.0 =
* 初回リリース。

== アップグレード通知 ==

= 1.0.0 =
初回リリース。
