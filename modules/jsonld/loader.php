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
/** @var array<int, string> $hr_sa_jsonld_last_active_emitters */
$GLOBALS['hr_sa_jsonld_last_active_emitters'] = [];

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

    $graph = hr_sa_jsonld_collect_graph();
    if (!$graph) {
        return;
    }

    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $graph = apply_filters('hr_sa_jsonld_graph_nodes', $graph);
    $graph = hr_sa_jsonld_normalize_internal_urls($graph);
    $graph = hr_sa_jsonld_enforce_org_and_brand($graph);
    $graph = hr_sa_jsonld_dedupe_by_id($graph);

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    echo '<script type="application/ld+json">' . hr_sa_jsonld_encode($payload) . '</script>' . PHP_EOL;
}

/**
 * Collect nodes from all registered emitters.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_collect_graph(): array
{
    $graph = [];
    $active = [];
    foreach ($GLOBALS['hr_sa_jsonld_emitters'] as $id => $callback) {
        if (!is_callable($callback)) {
            continue;
        }

        $nodes = (array) call_user_func($callback);
        foreach ($nodes as $node) {
            if (is_array($node) && $node) {
                $graph[] = $node;
            }
        }

        if ($nodes) {
            $active[] = (string) $id;
        }
    }

    $GLOBALS['hr_sa_jsonld_last_active_emitters'] = $active;

    return $graph;
}

/**
 * Return a list of emitter identifiers that produced nodes on the last run.
 *
 * @return array<int, string>
 */
function hr_sa_jsonld_get_active_emitters(): array
{
    return $GLOBALS['hr_sa_jsonld_last_active_emitters'] ?? [];
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
 * Sanitize answer markup for FAQ nodes.
 */
function hr_sa_jsonld_sanitize_answer_html(string $html): string
{
    $allowed = [
        'p'      => [],
        'br'     => [],
        'ul'     => [],
        'ol'     => [],
        'li'     => [],
        'strong' => [],
        'em'     => [],
        'b'      => [],
        'i'      => [],
        'a'      => [
            'href'  => [],
            'title' => [],
            'rel'   => [],
        ],
    ];

    $clean = strip_shortcodes($html);
    $clean = wp_kses($clean, $allowed);
    $clean = (string) preg_replace("/\r\n?/", "\n", $clean);
    $clean = (string) preg_replace('/[ \t]+/u', ' ', $clean);
    $clean = (string) preg_replace("/\n{3,}/u", "\n\n", $clean);

    return trim($clean);
}

/**
 * Canonical site URL provided by HRDF.
 */
function hr_sa_jsonld_site_url(): string
{
    static $site = null;
    if ($site !== null) {
        return $site;
    }

    $payload = hr_sa_hrdf_site_payload();
    $url     = isset($payload['url']) ? (string) $payload['url'] : '';

    if ($url === '') {
        $site = '';
    } else {
        $site = trailingslashit($url);
    }

    return $site;
}

function hr_sa_jsonld_org_id(): string
{
    $site = rtrim(hr_sa_jsonld_site_url(), '/');

    return $site !== '' ? $site . '#org' : '';
}

function hr_sa_jsonld_website_id(): string
{
    $site = rtrim(hr_sa_jsonld_site_url(), '/');

    return $site !== '' ? $site . '#website' : '';
}

/**
 * Determine current URL from the shared context.
 */
function hr_sa_jsonld_current_url(): string
{
    $context = hr_sa_get_context();

    return isset($context['url']) ? (string) $context['url'] : '';
}

/**
 * Force all internal URLs to HTTPS.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_normalize_internal_urls(array $graph): array
{
    $host = wp_parse_url(hr_sa_jsonld_site_url(), PHP_URL_HOST);
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
 * Ensure Organization node exists and referenced by Product.brand.
 *
 * @param array<int, array<string, mixed>> $graph
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_enforce_org_and_brand(array $graph): array
{
    $org_id  = hr_sa_jsonld_org_id();
    $site    = rtrim(hr_sa_jsonld_site_url(), '/');
    $has_org = false;

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_sa_jsonld_normalize_type($node['@type'] ?? null);
        if (in_array('Organization', $types, true)) {
            if ($org_id !== '') {
                $graph[$index]['@id'] = $org_id;
            }
            if ($site !== '') {
                $graph[$index]['url'] = $site;
            }
            $has_org = true;
        }
    }

    if (!$has_org) {
        $graph[] = hr_sa_jsonld_build_organization_node();
    }

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_sa_jsonld_normalize_type($node['@type'] ?? null);
        if (in_array('Product', $types, true) && $org_id !== '') {
            $graph[$index]['brand'] = [
                '@type' => 'Brand',
                '@id'   => $org_id,
            ];
        }
    }

    return $graph;
}

/**
 * Build the Organization node using HRDF data.
 */
function hr_sa_jsonld_build_organization_node(): array
{
    $site      = hr_sa_hrdf_site_payload();
    $org_id    = hr_sa_jsonld_org_id();
    $site_url  = rtrim(hr_sa_jsonld_site_url(), '/');
    $node      = ['@type' => 'Organization'];

    if ($org_id !== '') {
        $node['@id'] = $org_id;
    }
    if ($site_url !== '') {
        $node['url'] = $site_url;
    }

    $name = $site['org']['name'] ?? $site['name'] ?? '';
    if ($name !== '') {
        $node['name'] = $name;
    }

    $logo = $site['logo_url'] ?? '';
    if ($logo !== '') {
        $node['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo,
        ];
    }

    $legal_name = $site['org']['legal_name'] ?? '';
    if ($legal_name !== '') {
        $node['legalName'] = $legal_name;
    }

    $address = $site['org']['address'] ?? [];
    if (is_array($address) && $address) {
        $node['address'] = array_merge(['@type' => 'PostalAddress'], $address);
    }

    $same_as = $site['org']['same_as'] ?? [];
    if (is_array($same_as) && $same_as) {
        $node['sameAs'] = $same_as;
    }

    $contact_points = $site['org']['contact'] ?? [];
    if (is_array($contact_points) && $contact_points) {
        $node['contactPoint'] = $contact_points;
    }

    return $node;
}

/**
 * Build the WebSite node.
 */
function hr_sa_jsonld_build_website_node(): array
{
    $site     = hr_sa_hrdf_site_payload();
    $site_url = rtrim(hr_sa_jsonld_site_url(), '/');
    $node     = ['@type' => 'WebSite'];

    if ($site_url !== '') {
        $node['@id'] = $site_url . '#website';
        $node['url'] = $site_url;
    }

    if (!empty($site['name'])) {
        $node['name'] = $site['name'];
    }

    $org_id = hr_sa_jsonld_org_id();
    if ($org_id !== '') {
        $node['publisher'] = ['@id' => $org_id];
    }

    return $node;
}

/**
 * Build the WebPage node for the current view.
 */
function hr_sa_jsonld_build_webpage_node(): array
{
    $context    = hr_sa_get_context();
    $current_url = isset($context['url']) ? (string) $context['url'] : '';
    $title       = isset($context['title']) ? (string) $context['title'] : '';

    $node = ['@type' => 'WebPage'];

    if ($current_url !== '') {
        $node['@id'] = rtrim($current_url, '/') . '#webpage';
        $node['url'] = $current_url;
    }

    if ($title !== '') {
        $node['name'] = $title;
    }

    $website_id = hr_sa_jsonld_website_id();
    if ($website_id !== '') {
        $node['isPartOf'] = ['@id' => $website_id];
    }

    $org_id = hr_sa_jsonld_org_id();
    if ($org_id !== '') {
        $node['about'] = ['@id' => $org_id];
    }

    return $node;
}

require_once __DIR__ . '/org.php';
require_once __DIR__ . '/itinerary.php';
require_once __DIR__ . '/faq.php';
require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/trip.php';
