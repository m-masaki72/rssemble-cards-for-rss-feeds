<?php
/**
 * RSS Display プレビューサーバー
 * 使い方: php -S localhost:8080 preview/router.php
 * アクセス: http://localhost:8080/?feed=https://zenn.dev/feed
 */

require_once __DIR__ . '/wp-stub.php';

$plugin_dir = dirname(__DIR__) . '/gridify-image-cards-for-rss/';
define('RSS_D_VERSION', '1.0.0');
define('RSS_D_FILE',    $plugin_dir . 'gridify-image-cards-for-rss.php');
define('RSS_D_DIR',     $plugin_dir);
define('RSS_D_URL',     '/plugin/');
define('RSS_D_OPTION',  'rss_d_settings');

// RSS_Display::get_settings() / default_settings() の代替（class-feed-manager が参照するため先に定義）
class RSS_Display {
    public static function default_settings() {
        return [
            'feeds'             => '',
            'count'             => 12,
            'columns'           => 3,
            'title_lines'       => 2,
            'cache_ttl'         => 86400,
            'default_image_id'  => 0,
            'default_image_url' => '',
            'link_new_tab'      => 1,
            'type'              => 'grid',
            'orderby'           => 'date',
            'show_desc'         => 0,
            'show_date'         => 1,
            'show_site'         => 0,
        ];
    }
    public static function get_settings() {
        return self::default_settings();
    }
}

require_once $plugin_dir . 'includes/class-ogp-fetcher.php';
require_once $plugin_dir . 'includes/class-feed-manager.php';

// --- パラメータ受け取り ---
$allowed_types = ['grid', 'image_only', 'list', 'list_vertical', 'text', 'text_line', 'carousel', 'popup_grid'];

$feed_url = $_GET['feed']    ?? 'https://zenn.dev/feed';
$columns  = (int)($_GET['columns']  ?? 3);
$count    = (int)($_GET['count']    ?? 12);
$orderby  = $_GET['orderby'] ?? 'date';
$target   = $_GET['target']  ?? '_blank';
$type     = $_GET['type']    ?? 'grid';
$show_desc = !empty($_GET['desc']);
$show_date = !isset($_GET['date']) || $_GET['date'] !== '0';
$show_site = !empty($_GET['site']);

if (!in_array($columns, [2, 3, 4], true))           $columns = 3;
if ($count < 1 || $count > 100)                      $count   = 12;
if (!in_array($orderby, ['date', 'random'], true))   $orderby = 'date';
if (!in_array($target,  ['_blank', '_self'], true))  $target  = '_blank';
if (!in_array($type, $allowed_types, true))          $type    = 'grid';

$feed_url = filter_var(trim($feed_url), FILTER_SANITIZE_URL);

// --- フィード取得 ---
$ogp_fetcher  = new RSS_D_OGP_Fetcher();
$feed_manager = new RSS_D_Feed_Manager($ogp_fetcher);

$items   = [];
$elapsed = 0;

if ($feed_url) {
    $t0      = microtime(true);
    $items   = $feed_manager->get_items([$feed_url], $count, $orderby);
    $elapsed = round((microtime(true) - $t0) * 1000);
}

// OGP並列取得
$ogp_urls = [];
foreach ($items as $item) {
    if ($item['image'] === '' && $item['url'] !== '') {
        $ogp_urls[] = $item['url'];
    }
}
$ogp_map = !empty($ogp_urls) ? $ogp_fetcher->get_images($ogp_urls) : [];

$default_image = '/plugin/assets/img/placeholder.png';
$title_lines   = 2;
$date_format   = 'Y/m/d';
$target_attr   = $target === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';

?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RSS Display — Preview</title>
<link rel="stylesheet" href="/plugin/assets/css/gridify-image-cards-for-rss.css">
<script src="/plugin/assets/js/gridify-image-cards-for-rss.js" defer></script>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         background: #f0f0f0; color: #222; }
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
  <h1>RSS Display Preview</h1>
  <form method="get">
    <div class="field">
      <label>Feed</label>
      <input type="url" name="feed" value="<?= htmlspecialchars($feed_url) ?>" placeholder="https://zenn.dev/feed">
    </div>
    <div class="field">
      <label>タイプ</label>
      <select name="type">
        <?php foreach ($allowed_types as $t): ?>
          <option value="<?= $t ?>" <?= $t === $type ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
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
      <label>説明文</label>
      <input type="checkbox" name="desc" value="1" <?= $show_desc ? 'checked' : '' ?>>
    </div>
    <div class="field">
      <label>日付</label>
      <input type="checkbox" name="date" value="1" <?= $show_date ? 'checked' : '' ?>>
    </div>
    <div class="field">
      <label>サイト名</label>
      <input type="checkbox" name="site" value="1" <?= $show_site ? 'checked' : '' ?>>
    </div>
    <button type="submit">表示</button>
  </form>
</div>

<div class="preview-main">

<?php if ($feed_url): ?>
  <p class="preview-meta">
    <strong><?= htmlspecialchars($feed_url) ?></strong> &nbsp;|&nbsp;
    type: <strong><?= htmlspecialchars($type) ?></strong> &nbsp;|&nbsp;
    <?= count($items) ?>件取得 &nbsp;|&nbsp; <?= $elapsed ?>ms
  </p>
<?php endif; ?>

<?php if (empty($items)): ?>
  <div class="preview-empty">フィードを入力して「表示」を押してください。</div>
<?php elseif ($type === 'carousel'): ?>
  <div class="rss-d-carousel-wrap" id="rss-d-carousel-1" style="--rss-d-columns:<?= $columns ?>;--rss-d-title-lines:<?= $title_lines ?>;">
    <button class="rss-d-carousel-btn rss-d-carousel-prev" aria-label="前へ">&#10094;</button>
    <div class="rss-d-carousel-viewport">
      <div class="rss-d-carousel-track">
        <?php foreach ($items as $item):
          $image = $item['image'];
          if ($image === '' && $item['url'] !== '') {
              $image = $ogp_map[$item['url']] ?? '';
          }
          if ($image === '') $image = $default_image;

          $title      = $item['title'];
          $link       = $item['url'];
          $date_label = ($show_date && $item['timestamp']) ? date($date_format, $item['timestamp']) : '';
          $site_name  = $show_site ? ($item['site'] ?? '') : '';
        ?>
          <?php if ($link !== ''): ?>
          <a class="rss-d-card rss-d-carousel-card" href="<?= htmlspecialchars($link) ?>"<?= $target_attr ?>>
          <?php else: ?>
          <div class="rss-d-card rss-d-carousel-card">
          <?php endif; ?>
            <img class="rss-d-img" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
            <span class="rss-d-overlay" aria-hidden="true"></span>
            <?php if ($date_label !== ''): ?><span class="rss-d-date"><?= htmlspecialchars($date_label) ?></span><?php endif; ?>
            <?php if ($site_name !== ''): ?><span class="rss-d-site"><?= htmlspecialchars($site_name) ?></span><?php endif; ?>
            <?php if ($title !== ''): ?><h3 class="rss-d-title"><?= htmlspecialchars($title) ?></h3><?php endif; ?>
          <?php echo $link !== '' ? '</a>' : '</div>'; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <button class="rss-d-carousel-btn rss-d-carousel-next" aria-label="次へ">&#10095;</button>
  </div>
<?php elseif ($type === 'popup_grid'): ?>
  <div class="rss-d-grid rss-d-type-popup_grid" id="rss-d-popup-1" style="--rss-d-columns:<?= $columns ?>;--rss-d-title-lines:<?= $title_lines ?>;">
    <?php foreach ($items as $item):
      $image = $item['image'];
      if ($image === '' && $item['url'] !== '') {
          $image = $ogp_map[$item['url']] ?? '';
      }
      if ($image === '') $image = $default_image;

      $title      = $item['title'];
      $link       = $item['url'];
      $date_label = ($show_date && $item['timestamp']) ? date($date_format, $item['timestamp']) : '';
      $desc_text  = $show_desc ? ($item['desc'] ?? '') : '';
      $site_name  = $show_site ? ($item['site'] ?? '') : '';
      $newtab     = $target === '_blank' ? '1' : '0';
    ?>
      <button class="rss-d-card rss-d-popup-trigger"
        data-image="<?= htmlspecialchars($image) ?>"
        data-title="<?= htmlspecialchars($title) ?>"
        data-link="<?= htmlspecialchars($link) ?>"
        data-date="<?= htmlspecialchars($date_label) ?>"
        data-desc="<?= htmlspecialchars($desc_text) ?>"
        data-site="<?= htmlspecialchars($site_name) ?>"
        data-newtab="<?= $newtab ?>">
        <img class="rss-d-img" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
        <span class="rss-d-overlay" aria-hidden="true"></span>
        <?php if ($date_label !== ''): ?><span class="rss-d-date"><?= htmlspecialchars($date_label) ?></span><?php endif; ?>
        <?php if ($site_name !== ''): ?><span class="rss-d-site"><?= htmlspecialchars($site_name) ?></span><?php endif; ?>
        <?php if ($title !== ''): ?><h3 class="rss-d-title"><?= htmlspecialchars($title) ?></h3><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <div class="rss-d-modal-overlay" id="rss-d-popup-1-modal" role="dialog" aria-modal="true" aria-label="記事詳細" hidden>
    <div class="rss-d-modal">
      <button class="rss-d-modal-close" type="button" aria-label="閉じる">&times;</button>
      <img class="rss-d-modal-img" src="" alt="" />
      <div class="rss-d-modal-body">
        <p class="rss-d-modal-meta"><span class="rss-d-modal-site"></span><span class="rss-d-modal-date"></span></p>
        <h2 class="rss-d-modal-title"></h2>
        <p class="rss-d-modal-desc"></p>
        <a class="rss-d-modal-link button" href="#">記事を読む →</a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="rss-d-grid rss-d-type-<?= htmlspecialchars($type) ?>" style="--rss-d-columns:<?= $columns ?>;--rss-d-title-lines:<?= $title_lines ?>;">
    <?php foreach ($items as $item):
      $image = $item['image'];
      if ($image === '' && $item['url'] !== '') {
          $image = $ogp_map[$item['url']] ?? '';
      }
      if ($image === '') $image = $default_image;

      $title      = $item['title'];
      $link       = $item['url'];
      $date_label = ($show_date && $item['timestamp']) ? date($date_format, $item['timestamp']) : '';
      $desc_text  = $show_desc ? ($item['desc'] ?? '') : '';
      $site_name  = $show_site ? ($item['site'] ?? '') : '';
    ?>
      <?php if ($link !== ''): ?>
      <a class="rss-d-card" href="<?= htmlspecialchars($link) ?>"<?= $target_attr ?>>
      <?php else: ?>
      <div class="rss-d-card">
      <?php endif; ?>

        <?php if ($type === 'text' || $type === 'text_line'): ?>
        <div class="rss-d-card-body">
          <?php if ($site_name !== ''): ?><span class="rss-d-site"><?= htmlspecialchars($site_name) ?></span><?php endif; ?>
          <?php if ($title !== ''): ?><h3 class="rss-d-title"><?= htmlspecialchars($title) ?></h3><?php endif; ?>
          <?php if ($type === 'text' && $desc_text !== ''): ?><p class="rss-d-desc"><?= htmlspecialchars($desc_text) ?></p><?php endif; ?>
          <?php if ($date_label !== ''): ?><span class="rss-d-date"><?= htmlspecialchars($date_label) ?></span><?php endif; ?>
        </div>

        <?php elseif ($type === 'list' || $type === 'list_vertical'): ?>
        <img class="rss-d-img" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
        <div class="rss-d-card-body">
          <?php if ($site_name !== ''): ?><span class="rss-d-site"><?= htmlspecialchars($site_name) ?></span><?php endif; ?>
          <?php if ($title !== ''): ?><h3 class="rss-d-title"><?= htmlspecialchars($title) ?></h3><?php endif; ?>
          <?php if ($desc_text !== ''): ?><p class="rss-d-desc"><?= htmlspecialchars($desc_text) ?></p><?php endif; ?>
          <?php if ($date_label !== ''): ?><span class="rss-d-date"><?= htmlspecialchars($date_label) ?></span><?php endif; ?>
        </div>

        <?php else: ?>
        <img class="rss-d-img" src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy">
        <span class="rss-d-overlay" aria-hidden="true"></span>
        <?php if ($type === 'grid'): ?>
          <?php if ($date_label !== ''): ?><span class="rss-d-date"><?= htmlspecialchars($date_label) ?></span><?php endif; ?>
          <?php if ($site_name !== ''): ?><span class="rss-d-site"><?= htmlspecialchars($site_name) ?></span><?php endif; ?>
          <?php if ($title !== ''): ?><h3 class="rss-d-title"><?= htmlspecialchars($title) ?></h3><?php endif; ?>
          <?php if ($desc_text !== ''): ?><p class="rss-d-desc"><?= htmlspecialchars($desc_text) ?></p><?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

      <?php echo $link !== '' ? '</a>' : '</div>'; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</div>
</body>
</html>
