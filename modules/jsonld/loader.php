<?php
/**
 * JSON-LD loader and shared helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, callable> $hr_sa_jsonld_emitters */
$GLOBALS['hr_sa_jsonld_emitters'] = [];

/**
 * Register hooks for the JSON-LD module.
 */
function hr_sa_jsonld_boot(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    add_action('wp', 'hr_sa_jsonld_maybe_schedule');
    add_action('save_post', 'hr_sa_jsonld_invalidate_post_cache');
    $booted = true;
}

/**
 * Conditionally schedule JSON-LD output.
 */
function hr_sa_jsonld_maybe_schedule(): void
{
    if (is_admin()) {
        return;
    }

    if (!hr_sa_is_jsonld_enabled()) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_jsonld_print_graph', 98);
}

/**
 * Register an emitter callback.
 */
function hr_sa_jsonld_register_emitter(string $id, callable $callback): void
{
    $GLOBALS['hr_sa_jsonld_emitters'][$id] = $callback;
}

/**
 * Output the consolidated JSON-LD graph.
 */
function hr_sa_jsonld_print_graph(): void
{
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;

    $post_id = is_singular() ? (int) get_queried_object_id() : 0;

    $cached = hr_sa_jsonld_get_cached_payload($post_id);
    if (is_string($cached) && $cached !== '') {
        echo $cached;
        return;
    }

    $graph = hr_sa_jsonld_collect_graph($post_id);
    if (!$graph) {
        return;
    }

    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $graph = apply_filters('hr_sa_jsonld_graph_nodes', $graph, $post_id);

    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $payload_array = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    $json = '<script type="application/ld+json">' . hr_sa_jsonld_encode($payload_array) . '</script>' . PHP_EOL;

    hr_sa_jsonld_set_cached_payload($post_id, $json);

    echo $json;
}

/**
 * Collect nodes from all registered emitters.
 *
 * @param int $post_id
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_collect_graph(int $post_id): array
{
    $graph = [];

    foreach ($GLOBALS['hr_sa_jsonld_emitters'] as $callback) {
        if (!is_callable($callback)) {
            continue;
        }

        $nodes = (array) call_user_func($callback, $post_id);
        foreach ($nodes as $node) {
            if (is_array($node) && $node) {
                $graph[] = $node;
            }
        }
    }

    return $graph;
}

/**
 * Encode data for JSON-LD output.
 *
 * @param mixed $data
 */
function hr_sa_jsonld_encode($data): string
{
    return (string) wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Normalize @type values to arrays of unique strings.
 */
function hr_sa_jsonld_normalize_type($type): array
{
    if (is_array($type)) {
        $types = array_filter(array_map('strval', $type));
        return array_values(array_unique($types));
    }

    if ($type === null || $type === '') {
        return [];
    }

    return [(string) $type];
}

/**
 * Remove duplicate nodes by @id.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_dedupe_by_id(array $graph): array
{
    $result = [];
    $seen   = [];

    foreach ($graph as $node) {
        if (!is_array($node)) {
            continue;
        }

        $id = isset($node['@id']) ? (string) $node['@id'] : '';
        if ($id !== '' && isset($seen[$id])) {
            continue;
        }

        if ($id !== '') {
            $seen[$id] = true;
        }

        $result[] = $node;
    }

    return $result;
}

/**
 * Force internal URLs to HTTPS using the resolved site host.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_normalize_internal_urls(array $graph): array
{
    $site_profile = hr_sa_resolve_site_profile();
    $site_url     = $site_profile['url'] ?? '';
    $host         = $site_url ? wp_parse_url($site_url, PHP_URL_HOST) : null;

    if (!$host) {
        return $graph;
    }

    $pattern     = '#^https?://' . preg_quote((string) $host, '#') . '#i';
    $replacement = 'https://' . $host;

    $normalize = static function ($value) use (&$normalize, $pattern, $replacement) {
        if (is_array($value)) {
            foreach ($value as $key => $sub) {
                $value[$key] = $normalize($sub);
            }
            return $value;
        }

        if (is_string($value)) {
            return preg_replace($pattern, $replacement, $value);
        }

        return $value;
    };

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }
        $graph[$index] = $normalize($node);
    }

    return $graph;
}

/**
 * Retrieve cached JSON-LD payload when available.
 */
function hr_sa_jsonld_get_cached_payload(int $post_id): ?string
{
    $cache_key = hr_sa_jsonld_cache_key($post_id);
    $cached    = get_transient($cache_key);

    return is_string($cached) ? $cached : null;
}

/**
 * Persist the cached JSON-LD payload for the post.
 */
function hr_sa_jsonld_set_cached_payload(int $post_id, string $payload): void
{
    set_transient(hr_sa_jsonld_cache_key($post_id), $payload, 10 * MINUTE_IN_SECONDS);
}

/**
 * Delete cached payload when a post is saved.
 */
function hr_sa_jsonld_invalidate_post_cache(int $post_id): void
{
    delete_transient(hr_sa_jsonld_cache_key((int) $post_id));
}

/**
 * Build the cache key for a given post.
 */
function hr_sa_jsonld_cache_key(int $post_id): string
{
    return 'seo_jsonld_' . $post_id;
}

require_once __DIR__ . '/org.php';
require_once __DIR__ . '/trip.php';
