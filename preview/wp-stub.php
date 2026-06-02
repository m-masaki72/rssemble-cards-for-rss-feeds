<?php
/**
 * WordPress関数・定数のスタブ。
 * プレビュー専用。本番コードには含まれない。
 */

// 定数
define('ABSPATH', __DIR__ . '/');
define('WPINC', 'wp-includes');
define('MONTH_IN_SECONDS', 2592000);
define('DAY_IN_SECONDS',   86400);
define('WEEK_IN_SECONDS',  604800);

// URL/パスヘルパー
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file)  { return ''; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }

// オプション（スタブ設定を返す）
function get_option($key, $default = false) {
    if ($key === 'rss_gc_settings') return false;
    if ($key === 'rss_d_settings')  return false; // default_settings() に委ねる
    if ($key === 'date_format')     return 'Y/m/d';
    if ($key === 'time_format')     return 'H:i';
    return $default;
}
function update_option($key, $value, $autoload = null) { return true; }
function delete_option($key) { return true; }

// トランジェント（インメモリキャッシュ）
$_stub_transients = [];
function get_transient($key) {
    global $_stub_transients;
    return $_stub_transients[$key] ?? false;
}
function set_transient($key, $value, $expiration = 0) {
    global $_stub_transients;
    $_stub_transients[$key] = $value;
    return true;
}
function delete_transient($key) {
    global $_stub_transients;
    unset($_stub_transients[$key]);
    return true;
}

// WP_Error
class WP_Error {
    public function __construct(public string $code = '', public string $message = '') {}
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

// エスケープ
function esc_url_raw($url) { return filter_var(trim($url), FILTER_SANITIZE_URL) ?: ''; }
function esc_url($url)     { return htmlspecialchars(trim($url), ENT_QUOTES, 'UTF-8'); }
function esc_attr($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function wp_strip_all_tags($s) { return strip_tags((string)$s); }
function html_entity_decode_wp($s) { return html_entity_decode((string)$s, ENT_QUOTES, 'UTF-8'); }

// wp_parse_args
function wp_parse_args($args, $defaults = []) {
    if (is_string($args)) parse_str($args, $args);
    return array_merge((array)$defaults, (array)$args);
}

// wp_parse_url
function wp_parse_url($url, $component = -1) {
    return $component === -1 ? parse_url($url) : parse_url($url, $component);
}

// date_i18n（ロケールなしで date() を使う）
function date_i18n($format, $timestamp = null) {
    return date($format, $timestamp ?? time());
}

// absint
function absint($v) { return abs((int)$v); }

// HTTP リクエスト
function wp_remote_get($url, $args = []) {
    $timeout = $args['timeout'] ?? 5;
    $ua      = $args['user-agent'] ?? 'WordPress/RSS-Grid-Card-Preview';

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => $timeout,
            'user_agent'    => $ua,
            'ignore_errors' => true,
            'follow_location' => true,
            'max_redirects' => $args['redirection'] ?? 3,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return new WP_Error('http_request_failed', 'リクエスト失敗');
    }

    // $http_response_header から HTTP ステータスコードを取得
    $code = 200;
    if (!empty($http_response_header)) {
        if (preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }

    return ['response' => ['code' => $code], 'body' => $body];
}
function wp_remote_retrieve_response_code($response) {
    return $response['response']['code'] ?? 0;
}
function wp_remote_retrieve_body($response) {
    return $response['body'] ?? '';
}

// fetch_feed（SimplePie を直接使う）
function fetch_feed($url) {
    if (!class_exists('SimplePie')) {
        // WordPress同梱のSimplePieが無いので、composerまたはpharを探す
        // 見つからなければ軽量パーサーへフォールバック
        return _stub_parse_feed($url);
    }
    $feed = new SimplePie();
    $feed->set_feed_url($url);
    $feed->set_cache_duration(0);
    $feed->enable_cache(false);
    $feed->init();
    if ($feed->error()) return new WP_Error('feed_error', $feed->error());
    return $feed;
}

// SimplePie が使えない場合の軽量フォールバック
function _stub_parse_feed($url) {
    $response = wp_remote_get($url, ['timeout' => 15, 'user-agent' => 'RSS-Display-Preview/1.0']);
    if (is_wp_error($response)) return new WP_Error('feed_error', '取得失敗');
    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) return new WP_Error('feed_error', "HTTP $code");
    $xml = wp_remote_retrieve_body($response);
    if (!$xml) return new WP_Error('feed_error', '取得失敗');
    return new StubFeed($xml);
}

// --- StubFeed / StubItem ---

class StubFeed {
    private array $items = [];
    private DOMDocument $doc;

    public function __construct(string $xml) {
        libxml_use_internal_errors(true);
        $this->doc = new DOMDocument();
        $this->doc->loadXML($xml);
        libxml_clear_errors();

        $nodes = $this->doc->getElementsByTagName('item');   // RSS
        if ($nodes->length === 0) {
            $nodes = $this->doc->getElementsByTagName('entry'); // Atom
        }

        foreach ($nodes as $node) {
            $this->items[] = new StubItem($node);
        }
    }

    public function get_title(): string {
        $nl = $this->doc->getElementsByTagName('title');
        return $nl->length > 0 ? trim($nl->item(0)->textContent) : '';
    }

    public function get_item_quantity(int $max): int {
        return min(count($this->items), $max);
    }

    public function get_items(int $start, int $max): array {
        return array_slice($this->items, $start, $max);
    }

    public function error(): bool { return false; }
}

class StubItem {
    private DOMElement $node;

    public function __construct(DOMElement $node) {
        $this->node = $node;
    }

    private function text(string $tag): string {
        $nl = $this->node->getElementsByTagName($tag);
        return $nl->length > 0 ? trim($nl->item(0)->textContent) : '';
    }

    public function get_permalink(): string {
        // Atom: <link href="...">
        $links = $this->node->getElementsByTagName('link');
        foreach ($links as $l) {
            $href = $l->getAttribute('href');
            if ($href) return $href;
            if ($l->textContent) return trim($l->textContent);
        }
        return $this->text('link');
    }

    public function get_title(): string {
        return $this->text('title');
    }

    public function get_description(): string {
        return $this->text('description') ?: $this->text('summary') ?: $this->text('content');
    }

    public function get_date(string $format): mixed {
        $raw = $this->text('pubDate') ?: $this->text('published') ?: $this->text('updated');
        if (!$raw) return $format === 'U' ? 0 : '';
        $ts = strtotime($raw);
        return $format === 'U' ? (int)$ts : date($format, $ts);
    }

    public function get_item_tags(string $ns, string $tag): ?array {
        $found = [];
        foreach ($this->node->childNodes as $child) {
            if (!($child instanceof DOMElement)) continue;
            if ($child->localName === $tag && $child->namespaceURI === $ns) {
                $attribs = [];
                foreach ($child->attributes as $attr) {
                    $attribs[''][$attr->localName] = $attr->value;
                }
                $found[] = ['attribs' => $attribs, 'data' => $child->textContent];
            }
        }
        return $found ?: null;
    }

    public function get_enclosure(): ?object {
        $nl = $this->node->getElementsByTagName('enclosure');
        if ($nl->length === 0) return null;
        $el = $nl->item(0);
        return new class($el) {
            public function __construct(private DOMElement $el) {}
            public function get_link(): string  { return $this->el->getAttribute('url'); }
            public function get_type(): string  { return $this->el->getAttribute('type'); }
            public function get_thumbnail(): string { return ''; }
        };
    }
}

// add_filter / remove_filter / add_action（無視）
function add_filter($hook, $cb, $pri = 10, $args = 1) {}
function remove_filter($hook, $cb, $pri = 10) {}
function add_action($hook, $cb, $pri = 10, $args = 1) {}
function add_shortcode($tag, $cb) {}
function wp_enqueue_style(...$a) {}
function wp_register_style(...$a) {}
function wp_style_is($h, $s = 'enqueued') { return false; }

// wp_get_attachment_image_url（常に false）
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return false; }

// register_uninstall_hook（無視）
function register_uninstall_hook($file, $cb) {}

// is_admin
function is_admin() { return false; }

// load_plugin_textdomain（無視）
function load_plugin_textdomain(...$a) {}
