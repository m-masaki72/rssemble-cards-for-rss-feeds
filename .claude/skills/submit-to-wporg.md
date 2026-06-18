# WordPress.org 提出スキル

WordPress.org プラグインディレクトリへの提出・更新を行う。

## トリガー

「提出用ZIPを作って」「WordPress.orgに出す準備」「submit」「リリース確認」など。

---

## 初回提出

### 1. 提出前チェック

```bash
# WordPress 最新版確認（Tested up to: と一致させる）
curl -s https://api.wordpress.org/core/version-check/1.7/ | python -c "import sys,json; print(json.load(sys.stdin)['offers'][0]['version'])"

# 4ファイルのバージョン一致確認
grep -E "(^Version:|define.*RSSECAFO_VERSION|\"version\"|^Stable tag:)" rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php package.json rssemble-cards-for-rss-feeds/readme.txt rssemble-cards-for-rss-feeds/readme-ja.txt

# @package タグ確認（全5ファイルが Rssemble_Cards_For_RSS_Feeds になっているか）
grep -n "@package" rssemble-cards-for-rss-feeds/*.php rssemble-cards-for-rss-feeds/includes/*.php

# Text Domain 確認
grep -rn "__(" rssemble-cards-for-rss-feeds/includes/ | grep -v "rssemble-cards-for-rss-feeds" | grep -v "^Binary"
```

チェックリスト:
- [ ] `Tested up to:` が WordPress 最新安定版と一致している
- [ ] `Stable tag:` が PHP ヘッダーの `Version:` と一致している
- [ ] `Plugin Name:` が PHP ヘッダーと一致している
- [ ] `@package` タグが全5ファイルで `Rssemble_Cards_For_RSS_Feeds` になっている
- [ ] `Text Domain` が全ファイルで `rssemble-cards-for-rss-feeds` になっている

### 2. ZIP 作成

```bash
npm run zip
```

`scripts/make-zip.js` が実行され、`assets/screenshot-*.png` / `banner-*.png` / `icon-*.png` を除外した ZIP が生成される。
出力: `rssemble-cards-for-rss-feeds.zip`（プロジェクトルート）

### 3. アップロード

https://wordpress.org/plugins/developers/add/ （masakimori アカウントでログイン）

### 4. レビューメール返信

返信は**簡潔に**（AI的な長文は嫌がられる）。

```
Hi,

Thank you for the feedback. I've fixed the issues and uploaded a new version.

[変更内容を1〜3行で端的に記載]

Best regards,
Masaki Mori
```

スラグ変更申請が必要な場合はメール本文に明記する（コード変更だけでは不足）:

> Please reserve the new slug: **rssemble-cards-for-rss-feeds**

---

## 更新リリース（v1.0.1以降）

GitHub Actions で ZIP作成・GitHub Release・SVNデプロイが全自動で行われる。

### 1. 事前チェック

```bash
# WordPress 最新版確認
curl -s https://api.wordpress.org/core/version-check/1.7/ | python -c "import sys,json; print(json.load(sys.stdin)['offers'][0]['version'])"

# 4ファイルのバージョン一致確認
grep -E "(^Version:|define.*RSSECAFO_VERSION|\"version\"|^Stable tag:)" rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php package.json rssemble-cards-for-rss-feeds/readme.txt rssemble-cards-for-rss-feeds/readme-ja.txt

# @package タグ確認
grep -n "@package" rssemble-cards-for-rss-feeds/*.php rssemble-cards-for-rss-feeds/includes/*.php

# テスト実行
php tests/run.php
```

チェックリスト:
- [ ] `Tested up to:` が WordPress 最新安定版と一致している
- [ ] 4ファイルのバージョンがすべて一致している
- [ ] `@package` タグが全5ファイルで `Rssemble_Cards_For_RSS_Feeds` になっている
- [ ] テストが全件 PASS
- [ ] デザイン変更がある場合は `node scripts/generate-assets.js` で `wp-assets/` を再生成済み

### 2. タグ作成・プッシュ

```bash
git tag v{VERSION} && git push origin v{VERSION}
```

### 3. GitHub Actions 確認

```bash
gh run list --limit 3
gh run watch {RUN_ID}
```

所要時間の目安:
- `release` ジョブ（ZIP作成 + GitHub Release）: 約10〜15秒
- `deploy-to-wporg` ジョブ（SVN反映）: 約5〜6分

### 4. WordPress.org 反映確認

```bash
curl -s "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=rssemble-cards-for-rss-feeds" | python -c "import sys,json; d=json.load(sys.stdin); print('version:', d.get('version')); print('last_updated:', d.get('last_updated'))"
```

SVNデプロイ完了直後に反映される。`version:` が期待値と一致していれば完了。

---

## 共通注意事項

- スラグは承認後に変更不可
- `wp-assets/`（バナー・アイコン）はコード変更では自動更新されない。デザイン変更時のみ `node scripts/generate-assets.js` を実行してからタグを打つこと
- Windows環境ではパイプ経由の `node -e` で `/dev/stdin` が使えない。代わりに `python -c "import sys,json; ..."` を使うこと
- `load_plugin_textdomain()` は WordPress 4.6+ では不要（WordPress.org ホスト時）
