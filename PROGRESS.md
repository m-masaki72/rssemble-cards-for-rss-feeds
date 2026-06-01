# PROGRESS.md

## 現状 (2026-06-01)

### 完成済み

#### プラグイン本体 (`rss-grid-card/`)
- [x] エントリポイント (`rss-grid-card.php`) — シングルトン起動、定数定義
- [x] フィード取得・パース (`class-feed-manager.php`) — SimplePie + トランジェントキャッシュ、重複排除、staleフォールバック
- [x] OGP画像取得 (`class-ogp-fetcher.php`) — DOMXPathによるog:image解析、1ヶ月キャッシュ、negative cache
- [x] ショートコード (`class-shortcode.php`) — `[rss_grid_card]` 実装、パラメータ処理、HTML生成
- [x] 管理画面 (`class-admin.php`) — 設定保存、キャッシュ手動クリア、メディアライブラリ対応
- [x] フロントCSS (`assets/css/grid-card.css`) — カードグリッドUI、CSS変数、レスポンシブ
- [x] 管理画面CSS/JS (`assets/css/admin.css`, `assets/js/admin.js`)
- [x] プレースホルダー画像 (`assets/img/placeholder.png`)
- [x] WordPress.org用readme (`readme.txt`)

#### ローカルプレビュー環境 (`preview/`)
- [x] WordPressスタブ (`wp-stub.php`) — SimplePieなし環境でも動くStubFeed/StubItem実装
- [x] プレビューサーバー (`index.php`) — フィードURL・列数・件数・順序・リンクをGETパラメータで制御
- [x] ルーター (`router.php`) — 静的アセット配信 + パストラバーサル防止

#### GitHub Pages 静的プレビュー (`docs/`)
- [x] 静的プレビュー (`docs/index.html`) — ダミーデータ12件、JS制御パネル（列数/件数/タイトル行数/順序）
- [x] CSSコピー (`docs/grid-card.css`)

#### リポジトリ管理
- [x] `.gitignore`
- [x] `CLAUDE.md` (Claude Code用アーキテクチャガイド)
- [x] `README.md`
- [x] git初期コミット済み (`e709970`)

---

### 残タスク

#### すぐにできる
- [ ] GitHubにリモートリポジトリを作成してpush
- [ ] GitHub Pages を `docs/` フォルダから有効化
- [ ] `docs/grid-card.css` の同期手順をワークフロー化（plugin側更新時に手動コピーが必要）

#### 品質・テスト
- [ ] WordPress実環境での手動テスト（インストール〜表示まで一通り）
- [ ] PHP 7.4 互換確認（`str_starts_with` は PHP 8.0+ のためrouterのみ影響、本体は問題なし）
- [ ] 取得失敗時のstaleフォールバック動作確認
- [ ] OGP negative cacheの動作確認

#### 将来の改善候補
- [ ] WordPress.org への申請
- [ ] Pro機能検討（複数フィード高度フィルタ、AJAXページング、カスタムテーマ等）
- [ ] Freemius組み込み（サブスクリプション販売向け）
- [ ] `docs/` プレビューのランディングページ化（マネタイズ導線）

---

## 起動方法メモ

```bash
# PHPプレビューサーバー（ルートから）
php -S localhost:8080 preview/router.php

# アクセス
# http://localhost:8080/?feed=https://zenn.dev/feed&columns=3&count=12
```
