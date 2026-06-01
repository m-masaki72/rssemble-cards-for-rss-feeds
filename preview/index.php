<?php
/**
 * RSS Grid Card プレビューサーバー
 * 使い方: php -S localhost:8080 -t preview/
 */

require_once __DIR__ . '/wp-stub.php';

$plugin_dir = dirname(__DIR__) . '/rss-grid-card/';
define('RSS_GC_VERSION', '1.0.0');
define('RSS_GC_FILE',    $plugin_dir . 'rss-grid-card.php');
define('RSS_GC_DIR',     $plugin_dir);
define('RSS_GC_URL',     '/plugin/');   // 静的アセット配信用
define('RSS_GC_OPTION',  'rss_gc_settings');

require_once $plugin_dir . 'includes/class-ogp-fetcher.php';
require_once $plugin_dir . 'includes/class-feed-manager.php';

// --- パラメータ受け取り ---
$feed_url = $_GET['feed']    ?? 'https://zenn.dev/feed';
$columns  = (int)($_GET['columns']  ?? 3);
$count    = (int)($_GET['count']    ?? 12);
$orderby  = $_GET['orderby'] ?? 'date';
$target   = $_GET['target']  ?? '_blank';

if (!in_array($columns, [2, 3, 4], true)) $columns = 3;
if ($count < 1 || $count > 100)           $count   = 12;
if (!in_array($orderby, ['date', 'random'], true)) $orderby = 'date';
if (!in_array($target,  ['_blank', '_self'], true)) $target  = '_blank';

$feed_url = filter_var(trim($feed_url), FILTER_SANITIZE_URL);

// --- フィード取得 ---
$ogp_fetcher  = new RSS_GC_OGP_Fetcher();
$feed_manager = new RSS_GC_Feed_Manager($ogp_fetcher);

$items   = [];
$error   = '';
$elapsed = 0;

if ($feed_url) {
    $t0      = microtime(true);
    $items   = $feed_manager->get_items([$feed_url], $count, $orderby);
    $elapsed = round((microtime(true) - $t0) * 1000);
}

// --- デフォルト画像 ---
$default_image = '/plugin/assets/img/placeholder.png';

$title_lines  = 2;
$date_format  = 'Y/m/d';
$target_attr  = $target === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';

?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RSS Grid Card — Preview</title>
<link rel="stylesheet" href="/plugin/assets/css/grid-card.css">
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         background: #f5f5f5; color: #222; }
  .preview-bar {
    position: sticky; top: 0; z-index: 100;
    background: #1e1e2e; color: #cdd6f4; padding: 12px 20px;
    display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.4);
  }
  .preview-bar h1 { margin: 0; font-size: 15px; color: #cba6f7; white-space: nowrap; }
  .preview-bar form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; flex: 1; }
  .preview-bar input[type=url] {
    flex: 1; min-width: 220px; padding: 6px 10px;
    background: #313244; color: #cdd6f4; border: 1px solid #45475a; border-radius: 6px;
    font-size: 13px;
  }
  .preview-bar select, .preview-bar input[type=number] {
    padding: 6px 8px; background: #313244; color: #cdd6f4;
    border: 1px solid #45475a; border-radius: 6px; font-size: 13px;
  }
  .preview-bar label { font-size: 12px; color: #a6adc8; white-space: nowrap; }
  .preview-bar button {
    padding: 6px 14px; background: #cba6f7; color: #1e1e2e;
    border: none; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;
  }
  .preview-bar button:hover { background: #b07de0; }
  .field { display: flex; align-items: center; gap: 5px; }
  .preview-main { max-width: 1200px; margin: 24px auto; padding: 0 20px; }
  .preview-meta { font-size: 12px; color: #888; margin-bottom: 12px; }
  .preview-meta strong { color: #555; }
  .preview-empty { text-align: center; padding: 60px 20px; color: #999; font-size: 15px; }
</style>
</head>
<body>

<div class="preview-bar">
  <h1>RSS Grid Card Preview</h1>
  <form method="get">
    <div class="field">
      <label>Feed URL</label>
      <input type="url" name="feed" value="<?= htmlspecialchars($feed_url) ?>"
             placeholder="https://zenn.dev/feed">
    </div>
    <div class="field">
      <label>列数</label>
      <select name="columns">
        <?php foreach ([2, 3, 4] as $c): ?>
          <option value="<?= $c ?>" <?= $c === $columns ? 'selected' : '' ?>><?= $c ?>列</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>件数</label>
      <input type="number" name="count" value="<?= $count ?>" min="1" max="100" style="width:60px">
    </div>
    <div class="field">
      <label>順序</label>
      <select name="orderby">
        <option value="date"   <?= $orderby === 'date'   ? 'selected' : '' ?>>新着順</option>
        <option value="random" <?= $orderby === 'random' ? 'selected' : '' ?>>ランダム</option>
      </select>
    </div>
    <div class="field">
      <label>リンク</label>
      <select name="target">
        <option value="_blank" <?= $target === '_blank' ? 'selected' : '' ?>>別タブ</option>
        <option value="_self"  <?= $target === '_self'  ? 'selected' : '' ?>>同タブ</option>
      </select>
    </div>
    <button type="submit">表示</button>
  </form>
</div>

<div class="preview-main">

<?php if ($feed_url): ?>
  <p class="preview-meta">
    <strong><?= htmlspecialchars($feed_url) ?></strong> &nbsp;|&nbsp;
    <?= count($items) ?>件取得 &nbsp;|&nbsp; <?= $elapsed ?>ms
  </p>
<?php endif; ?>

<?php if (empty($items)): ?>
  <div class="preview-empty">フィードを入力して「表示」を押してください。</div>
<?php else: ?>
  <div class="rss-gc-grid" style="--rss-gc-columns:<?= $columns ?>;--rss-gc-title-lines:<?= $title_lines ?>;">
    <?php foreach ($items as $item):
      $image = $item['image'];
      if ($image === '' && $item['url'] !== '') {
          $ogp = $ogp_fetcher->get_image($item['url']);
          if ($ogp !== '') $image = $ogp;
      }
      if ($image === '') $image = $default_image;

      $title      = $item['title'];
      $link       = $item['url'];
      $date_label = $item['timestamp'] ? date($date_format, $item['timestamp']) : '';
    ?>
      <?php if ($link !== ''): ?>
      <a class="rss-gc-card" href="<?= htmlspecialchars($link) ?>"<?= $target_attr ?>>
      <?php else: ?>
      <div class="rss-gc-card">
      <?php endif; ?>
        <img class="rss-gc-img" src="<?= htmlspecialchars($image) ?>"
             alt="<?= htmlspecialchars($title) ?>" loading="lazy">
        <span class="rss-gc-overlay" aria-hidden="true"></span>
        <?php if ($date_label !== ''): ?>
          <span class="rss-gc-date"><?= htmlspecialchars($date_label) ?></span>
        <?php endif; ?>
        <?php if ($title !== ''): ?>
          <h3 class="rss-gc-title"><?= htmlspecialchars($title) ?></h3>
        <?php endif; ?>
      <?php echo $link !== '' ? '</a>' : '</div>'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
