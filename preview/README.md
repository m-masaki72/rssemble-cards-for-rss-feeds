# ローカルプレビューサーバー

実際のRSSフィードを取得して全レイアウトタイプを確認するための開発用サーバー。

## 起動方法

```bash
php -S localhost:8081 preview/router.php
```

ブラウザで `http://localhost:8081/` を開く。

---

## よくある失敗と対処

### HTTPS フィードが取得できない（0件取得）

**症状**: `0件取得` と表示される。エラーログに `Unable to find the wrapper "https"` が出る。

**原因**: PHP に OpenSSL 拡張が読み込まれていない。

**対処 (scoop でインストールした PHP の場合)**:

1. `php.ini` が存在するか確認:
   ```bash
   php --ini
   ```
   `Loaded Configuration File: (none)` と表示されたら未作成。

2. `php.ini` を作成する:
   ```bash
   # scoop の PHP ディレクトリに移動（パスは環境に合わせて変更）
   cd C:/Users/<yourname>/scoop/apps/php/current
   cp php.ini-development php.ini
   ```

3. `php.ini` を編集して以下の行を有効化:
   ```ini
   ; 下記2行のセミコロンを外す
   extension=openssl
   extension_dir = "C:/Users/<yourname>/scoop/apps/php/current/ext"
   ```
   `extension_dir` は絶対パスで指定すること（相対パス不可）。

4. サーバーを再起動:
   ```bash
   # 既存プロセスを Ctrl+C で停止してから再起動
   php -S localhost:8081 preview/router.php
   ```

5. 確認:
   ```bash
   php -m | grep openssl
   # "openssl" と表示されればOK
   ```

---

### 定数・クラスの二重定義エラー

**症状**: `Fatal error: Cannot redeclare define()/class ...`

**原因**: `wp-stub.php` と `index.php` の両方で同じ定数やクラスを定義している。

**ルール**:
- `ABSPATH` などの定数は `wp-stub.php` にのみ定義する
- `RSS_Display` スタブクラスは `index.php` にのみ定義する（`class-feed-manager.php` が参照するため）
- `index.php` に `define('ABSPATH', ...)` を書かない

---

### クラスが見つからない（class not found）

**症状**: `Fatal error: Class 'RSS_D_OGP_Fetcher' not found`

**原因**: プラグインのディレクトリ名・定数名の変更に `index.php` が追従していない。

**確認箇所** (`preview/index.php`):

```php
$plugin_dir = dirname(__DIR__) . '/rssemble-cards-for-rss-feeds/';   // ← ディレクトリ名
define('RSS_D_VERSION', '1.0.0');                    // ← 定数プレフィックス
define('RSS_D_FILE',    $plugin_dir . 'rssemble-cards-for-rss-feeds.php');
```

プラグインを `rssemble-cards-for-rss-feeds/` 以外の名前にリネームした場合はここを更新する。

---

### `StubFeed::get_title()` / `StubItem::get_description()` が未定義

**症状**: `Fatal error: Call to undefined method StubFeed::get_title()`

**原因**: `wp-stub.php` の `StubFeed` / `StubItem` に必要なメソッドが未実装。

**対処**: `preview/wp-stub.php` の `StubFeed` / `StubItem` クラスに以下を追加する:

```php
// StubFeed
public function get_title(): string {
    $nl = $this->doc->getElementsByTagName('title');
    return $nl->length > 0 ? trim($nl->item(0)->textContent) : '';
}

// StubItem
public function get_description(): string {
    return $this->text('description') ?: $this->text('summary') ?: $this->text('content');
}
```

---

### ポート競合

**症状**: `Failed to listen on localhost:8081`

**対処**: 別ポートで起動するか、既存プロセスを終了する。

```bash
# Windows でポート使用プロセスを確認
netstat -ano | findstr :8081
# PID を確認して終了
taskkill /PID <PID> /F
```

---

## ファイル構成と役割

```
preview/
  router.php     # 静的ファイルのルーティング（PHP組み込みサーバー用）
  index.php      # プレビューUI + フィード取得・HTML生成
  wp-stub.php    # WordPress関数・クラスのスタブ（本番コードには含まれない）
```

### wp-stub.php が担うもの

| スタブ | 実際のWP関数/クラス |
|--------|---------------------|
| `get_option()` | wp_options テーブル参照 |
| `get_transient()` / `set_transient()` | トランジェントキャッシュ（インメモリ代替） |
| `wp_remote_get()` | `WP_HTTP` クラス |
| `fetch_feed()` | SimplePie 統合 |
| `StubFeed` / `StubItem` | SimplePie の `SimplePie` / `SimplePie_Item` |
| `WP_Error` | WordPress の `WP_Error` クラス |

### RSS_Display スタブクラス（index.php 内）

`class-feed-manager.php` は `RSS_Display::get_settings()` を呼び出すため、
クラスのインクルード前にスタブを定義する必要がある。

```php
// index.php の順序（この順を守ること）
class RSS_Display { ... }          // 先に定義
require_once '.../class-ogp-fetcher.php';
require_once '.../class-feed-manager.php';
```
