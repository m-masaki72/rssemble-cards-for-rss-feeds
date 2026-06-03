# WordPress.org 提出スキル

WordPress.org プラグインディレクトリへの提出・再提出前チェックと ZIP 作成を行う。

## トリガー

「提出用ZIPを作って」「WordPress.orgに出す準備」「submit」など。

## 手順

### 1. 提出前チェックリスト

```
readme.txt の確認:
  - [ ] Tested up to: が WordPress 最新安定版になっているか
  - [ ] Stable tag: がプラグインのバージョンと一致しているか
  - [ ] Plugin Name: が PHP ヘッダーと一致しているか

PHP ファイルの確認:
  - [ ] @package タグが Gridify_Image_Cards_For_RSS になっているか（全5ファイル）
  - [ ] Text Domain が gridify-image-cards-for-rss になっているか
  - [ ] Version が readme.txt の Stable tag と一致しているか
```

Tested up to の確認方法:
```bash
# WordPress 最新版を確認
curl -s https://api.wordpress.org/core/version-check/1.7/ | node -e "const d=require('fs').readFileSync('/dev/stdin','utf8'); console.log(JSON.parse(d).offers[0].version)"
```

### 2. ZIP 作成

```bash
npm run zip
```

`scripts/make-zip.js` が実行され、以下を**除外**した ZIP が生成される:
- `assets/screenshot-*.png`
- `assets/banner-*.png`
- `assets/icon-*.png`

出力: `gridify-image-cards-for-rss.zip`（プロジェクトルート）

### 3. アップロード

https://wordpress.org/plugins/developers/add/ （masakimori アカウントでログイン）

### 4. メール返信テンプレート

再提出後、レビューメールに返信する。返信は**簡潔に**（AI的な長文は嫌がられる）。

```
Hi,

Thank you for the feedback. I've fixed the issues and uploaded a new version.

[変更内容を1〜3行で端的に記載]

Note: The "Text Domain mismatch" warning is expected — we have requested a slug change
to "gridify-image-cards-for-rss" in this reply.

Best regards,
Masaki Mori
```

### スラグ変更申請が必要な場合

メール本文に以下を明記する（コード変更だけでは不足）:

> Please reserve the new slug: **gridify-image-cards-for-rss**

## 注意事項

- スラグは承認後に変更不可
- SVN アクセス取得後にスクリーンショット・バナー・アイコンを別途アップロード
- `load_plugin_textdomain()` は WordPress 4.6+ では不要（WordPress.org ホスト時）
