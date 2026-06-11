# bump-version スキル

バージョン番号を4ファイル同時に更新し、コミットまで行う。

## トリガー

以下のいずれかが含まれる場合に発動する：

- 日本語：バージョンを上げ、バージョン上げ、バージョンアップ、バージョン更新、バージョン変更、にバージョン、新バージョン、リリースバージョン
- 英語：bump version、update version、bump v、version bump
- パターン：`v\d+\.\d+` と組み合わせた「にして」「へ」「を」

## 手順

### 1. 現バージョン確認

```bash
grep -m1 "Version:" rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php
```

取得したバージョンを表示する。

### 2. 新バージョンの決定

- ユーザーが引数でバージョンを指定していれば確認スキップ
- 指定がなければ `vX.Y.Z` 形式で提案して確認を取る

### 3. 4ファイルを一括更新

以下を **Edit ツール**で更新する（grep で正確な行を特定してから編集）：

| ファイル | 対象 |
|---------|------|
| `rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php` | ヘッダーコメントの `Version:` 行、`define( 'RSSECAFO_VERSION'` 行 |
| `package.json` | `"version":` 行 |
| `rssemble-cards-for-rss-feeds/readme.txt` | `Stable tag:` 行 |
| `rssemble-cards-for-rss-feeds/readme-ja.txt` | `Stable tag:` 行 |

### 4. Changelog テンプレートを追記

`readme.txt` の `== Changelog ==` 直後、および `readme-ja.txt` の `== 変更履歴 ==` 直後に以下を挿入する：

```
= X.Y.Z =
* [TODO: Add changelog entries here]

```

（既存エントリより前に挿入すること）

### 5. バージョン一致確認

```bash
grep -E "(Version:|define.*RSSECAFO_VERSION|\"version\"|Stable tag:)" \
  rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php \
  package.json \
  rssemble-cards-for-rss-feeds/readme.txt \
  rssemble-cards-for-rss-feeds/readme-ja.txt
```

全て新バージョンになっていることを確認して報告する。不一致があれば修正してから続行。

### 6. コミット

```bash
git add rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php package.json rssemble-cards-for-rss-feeds/readme.txt rssemble-cards-for-rss-feeds/readme-ja.txt
git commit -m "bump: v{OLD} → v{NEW}"
```

## 注意事項

- `docs/index.html` にバージョン記載がある場合は別途更新が必要（機能変更時のみ）
- Changelog の TODO 行は次の作業前に必ず実際の変更内容に書き換えること
