# release スキル

リリース前の全検証を行い、問題がなければ git タグを作成・プッシュして GitHub Actions のデプロイをトリガーする。

## トリガー

以下のいずれかが含まれる場合に発動する：

- 日本語：リリースして、リリース実行、リリースしたい、タグを打、デプロイ、WordPress.orgにリリース、wp.orgにリリース、本番リリース、リリースタグ、タグpush
- 英語：release v、tag v、git tag、push tag、deploy
- パターン：`v\d+\.\d+` と組み合わせた「をリリース」「でリリース」

## 前提条件チェック（スキル冒頭で必ず確認）

```bash
git status --short
git log --oneline -5
```

- 未コミット変更があれば警告し、コミットするか確認する
- `bump:` コミットが直近のログに存在するか確認する（なければ `bump-version` スキルの実行を促す）

## 手順

### 1. バージョン一致確認

```bash
grep -E "(^Version:|define.*RSSECAFO_VERSION|\"version\"|^Stable tag:)" \
  rssemble-cards-for-rss-feeds/rssemble-cards-for-rss-feeds.php \
  package.json \
  rssemble-cards-for-rss-feeds/readme.txt \
  rssemble-cards-for-rss-feeds/readme-ja.txt
```

4ファイルの値を表示し、全一致を確認する。**不一致があれば中断**し、`bump-version` スキルの実行を促す。

### 2. `Tested up to:` 確認

```bash
curl -s https://api.wordpress.org/core/version-check/1.7/ | node -e "const d=require('fs').readFileSync('/dev/stdin','utf8'); console.log(JSON.parse(d).offers[0].version)"
```

取得した最新 WordPress バージョンと `readme.txt` の `Tested up to:` を比較する。

- 一致していれば OK
- 古ければ `readme.txt` / `readme-ja.txt` の `Tested up to:` 更新を提案（更新する場合はコミットも行う）

### 3. テスト実行

```bash
php tests/run.php
```

出力に `FAIL` / `ERROR` が含まれていれば**中断**してエラー内容を表示する。全テスト PASS を確認してから次へ進む。

### 4. アセット確認

`wp-assets/` 内ファイルの更新日時と `docs/index.html` の更新日時を比較し、`docs/index.html` の方が新しければ以下を提案する：

```bash
node scripts/generate-assets.js
```

バナー・アイコン画像の変更が不要な場合はユーザーがスキップできる。

### 5. タグ作成・プッシュ

バージョン番号を取得してタグを作成・プッシュする：

```bash
git tag v{VERSION}
git push origin v{VERSION}
```

### 6. 完了案内

- GitHub Actions `release.yml` がトリガーされること、ZIP作成 + SVNデプロイが自動実行されることを案内する
- 手動提出が必要な場合は `submit-to-wporg` スキルを次のステップとして案内する

## 中断条件まとめ

| 条件 | 対応 |
|------|------|
| 未コミット変更あり | 警告して確認 |
| 4ファイルのバージョン不一致 | 中断・bump-version を促す |
| テスト FAIL | 中断・エラー表示 |
