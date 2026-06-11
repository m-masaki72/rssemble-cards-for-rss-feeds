# feature-checklist スキル

機能追加・変更後に必須の5箇所更新を漏れなく確認・実行する。

## トリガー

以下のいずれかが含まれる場合に発動する（単独の「チェックリスト」は誤発動防止のため対象外）：

- 機能追加チェック、機能追加の確認、追加機能チェック、機能チェック
- 機能追加後、機能を追加した、新機能を追加、機能の確認
- feature checklist、feature check、new feature checklist、featureチェック

## 手順

### 1. 変更ファイルの自動判定

```bash
git diff --name-only HEAD
git diff --name-only --cached
```

変更ファイル一覧をもとに、後述の5項目のうちどれが未対応かを自動判定してユーザーに提示する。

### 2. 5項目チェック（AskUserQuestion で未実施を確認）

チェック対象と確認ポイント：

| # | ファイル | 確認内容 |
|---|---------|---------|
| 1 | `rssemble-cards-for-rss-feeds/readme.txt` / `readme-ja.txt` | Features セクションに新機能を記載、Changelog にエントリを追加 |
| 2 | `rssemble-cards-for-rss-feeds/includes/class-admin.php` | About タブ内 `rss-d-about-card` の `<ul>` に機能説明を追加 |
| 3 | `rssemble-cards-for-rss-feeds/languages/rssemble-cards-for-rss-feeds-ja.po` | 新規 `msgid` / `msgstr` を追記 |
| 4 | `docs/index.html` | 特徴セクション・パラメーター一覧を更新 |
| 5 | `node scripts/po2mo.js` 実行 | `.po` を変更した場合のみ必須 |

### 3. .po 変更時は自動コンパイル

変更ファイルに `rssemble-cards-for-rss-feeds-ja.po` が含まれていれば、確認なしに以下を実行する：

```bash
node scripts/po2mo.js
```

成功メッセージを確認して報告する。

### 4. 未対応項目の誘導

未対応の項目があれば、対象ファイルを開いて編集を誘導する。  
ユーザーが「後で対応」と言えばスキップを記録して次へ進む。

### 5. 完了報告

すべての項目が完了（またはスキップが明示）されたら完了メッセージを出力する。

## 注意事項

- `po2mo.js` 実行後に `.mo` ファイルの更新日時が変わっていることで成功確認できる
- `readme.txt` の Changelog エントリは `Stable tag:` と一致するバージョン番号で記載すること
