# PROGRESS.md

## 残タスク

### 将来検討：マネタイズ復活時

- Polar.sh（販売・ライセンスキー発行）+ plugin-update-checker（自動アップデート配信）の組み合わせを検討

### Phase 1: 必須対応（申請前にすべて完了）

- [x] `enqueue_assets()` の未定義変数バグ修正（`$is_paying` / `$upgrade_url` / `$types`）
- [x] **Freemius 完全除去・全機能無料化**：`freemius.php` 削除、`is_paying` 分岐・制限ロジック・アップグレード UI をすべて除去
- [x] `Plugin URI` を実在URLに変更（`https://github.com/m-masaki72/rss-display`）
- [x] `Author` を英語/ローマ字表記に変更（`Masaki Mori`）
- [x] `Tested up to` を最新版（6.7）に更新
- [x] `readme.txt` に `== Screenshots ==` / `== Upgrade Notice ==` セクション追加
- [x] `admin.js` から `isPaying`/`proTypes`/`upgradeUrl` 残留コード除去
- [x] `sync-docs.yml` の typo 修正（`stefanprodan` → `stefanzweifel`）
- [x] `release.yml` から Freemius SDK ダウンロードステップ除去
- [x] アイコン画像生成：`wp-assets/icon-256x256.png`（`scripts/generate-assets.js` で生成、SVN `/assets/` に配置する）
- [x] バナー画像生成：`wp-assets/banner-772x250.png`・`wp-assets/banner-1544x500.png`（同上）

### Phase 2: 品質対応

- [x] 英語を msgid にした国際化対応（class-admin.php 全文書き換え）
- [x] `.pot` 生成・`languages/rss-display-ja.po` / `.mo` 作成（`scripts/po2mo.js` で生成）
- [ ] Plugin Check プラグインで自動チェック実行・警告解消
- [ ] PHP 8.2 での deprecation なし確認
- [x] WPCS (WordPress Coding Standards) 適合確認（phpcs exit:0 達成）
- [x] render_card_overlay_body() 共通ヘルパー切り出し（grid/carousel コピペ解消）
- [x] maybe_register_style() eager enqueue 削除（register のみ）
- [x] desc を mb_substr(0,300) で切り捨て（トランジェント肥大化防止）

### Phase 3: 動作検証（WordPress実環境）

- [ ] 取得失敗時のstaleフォールバック動作確認
- [ ] OGP negative cacheの動作確認
- [ ] ショートコード全8タイプの表示確認
- [ ] アンインストール後のオプション削除確認
- [ ] WordPress 6.0（最小要件）での動作確認

### Phase 4: SVN 申請

- [ ] https://wordpress.org/plugins/developers/add/ から申請
- [ ] 審査通過後、SVN に `/trunk/`・`/assets/`・`/tags/1.0.0/` 構成でコミット

## 既知の制約

- `curl_multi` パスは `pre_http_request` フィルタをバイパスする（WPプロキシ・SSL設定は適用済み）
- OGPの128KB打ち切りは curl_multi パスのみ有効（直列フォールバックは無制限）

## 起動方法メモ

```bash
# PHPプレビューサーバー
php -S localhost:8080 preview/router.php
# http://localhost:8080/?feed=https://zenn.dev/feed&type=carousel&columns=3

# テスト実行
php tests/run.php
```
