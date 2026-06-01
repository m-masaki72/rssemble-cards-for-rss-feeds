# PROGRESS.md

## 現状 (2026-06-02)

### 完成済み

#### プラグイン本体 (`rss-display/`)
- [x] エントリポイント (`rss-display.php`) — シングルトン起動、定数定義
- [x] フィード取得・パース (`class-feed-manager.php`) — SimplePie + トランジェントキャッシュ、重複排除、staleフォールバック、MAX_ITEMS_PER_FEED=24
- [x] OGP画像取得 (`class-ogp-fetcher.php`) — curl_multi並列取得、DOMXPathによるog:image解析、1ヶ月キャッシュ、negative cache、curl_init falseガード、重複URL排除
- [x] ショートコード (`class-shortcode.php`) — `[rss_display]` 実装、パラメータ処理、HTML生成、RSS画像なしアイテムのみOGPフェッチ
- [x] 管理画面 (`class-admin.php`) — 設定保存、キャッシュ手動クリア、メディアライブラリ対応
- [x] フロントCSS (`assets/css/rss-display.css`) — カードグリッドUI、CSS変数、レスポンシブ
- [x] 管理画面CSS/JS (`assets/css/admin.css`, `assets/js/admin.js`)
- [x] プレースホルダー画像 (`assets/img/placeholder.png`)
- [x] WordPress.org用readme (`readme.txt`)

#### ローカルプレビュー環境 (`preview/`)
- [x] WordPressスタブ (`wp-stub.php`) — SimplePieなし環境でも動くStubFeed/StubItem実装
- [x] プレビューサーバー (`index.php`) — フィードURL・列数・件数・順序・リンクをGETパラメータで制御
- [x] ルーター (`router.php`) — 静的アセット配信 + パストラバーサル防止

#### 静的プレビュー (`docs/`)
- [x] 静的プレビュー (`docs/index.html`) — ダミーデータ12件、JS制御パネル（列数/件数/タイトル行数/順序）
- [x] CSS (`docs/grid-card.css`)

#### リポジトリ管理
- [x] `.gitignore`
- [x] `CLAUDE.md` (Claude Code用アーキテクチャガイド)
- [x] `README.md`
- [x] GitHub Actions (`release.yml`) — `v*` タグ push で `rss-display.zip` を自動リリース
- [x] GitHub プライベートリポジトリ push 済み (`m-masaki72/rss-display`)
- [x] v1.0.0 / v1.0.1 リリース済み（v1.0.2 は次回タグ時）

---

### 既知の制約・注意事項

- `curl_multi` パスは WordPress HTTP フィルタ（`pre_http_request` 等）をバイパスする。プロキシや独自SSL証明書をWPフィルタで制御している環境では直列フォールバック（curl_multi_init 非対応環境）が安全。
- OGP取得の128KB打ち切りは curl_multi パスのみ有効（直列フォールバックは無制限）。

---

### 残タスク

#### 品質・テスト
- [ ] WordPress実環境での手動テスト（インストール〜表示まで一通り）
- [ ] PHP 7.4 互換確認（`str_starts_with` は PHP 8.0+ のためrouterのみ影響、本体は問題なし）
- [ ] 取得失敗時のstaleフォールバック動作確認
- [ ] OGP negative cacheの動作確認

#### 将来の改善候補
- [ ] カード型以外のレイアウト追加（リスト型・マガジン型等）
- [ ] WordPress.org への申請
- [ ] Pro機能検討（複数フィード高度フィルタ、AJAXページング、カスタムテーマ等）
- [ ] curl_multi を WP HTTP API ベースに置き換え（WPフィルタ完全対応）

---

## 起動方法メモ

```bash
# PHPプレビューサーバー（ルートから）
php -S localhost:8080 preview/router.php

# アクセス
# http://localhost:8080/?feed=https://zenn.dev/feed&columns=3&count=12
```
