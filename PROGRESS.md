# PROGRESS.md

## 残タスク

### 品質・テスト
- [ ] 取得失敗時のstaleフォールバック動作確認（WordPress実環境）
- [ ] OGP negative cacheの動作確認（WordPress実環境）
- [ ] Freemiusサブスクの動作検証（WordPress実環境）

### リリース
- [ ] WordPress.org への申請（スクリーンショット・バナー画像の準備が必要）
- [ ] Freemius public_key を本番値に差し替え

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
