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

add_action('wp', 'hr_sa_jsonld_maybe_schedule');

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
 * Fetch schema setting from legacy option store.
 *
 * @param mixed $default
 * @return mixed
 */
function hr_sa_jsonld_get_schema_option(string $key, $default = '')
{
    $options = get_option('hr_schema_settings', []);
    if (is_array($options) && array_key_exists($key, $options) && $options[$key] !== '') {
        return $options[$key];
    }

    return $default;
}

/**
 * Resolve the Organization logo URL.
 */
function hr_sa_jsonld_get_logo_url(): string
{
    $logo_id = (int) hr_sa_jsonld_get_schema_option('org_logo_id', 0);
    if ($logo_id > 0) {
        $url = wp_get_attachment_image_url($logo_id, 'full');
        if ($url) {
            return $url;
        }
    }

    $theme_logo_id = (int) get_theme_mod('custom_logo');
    if ($theme_logo_id > 0) {
        $url = wp_get_attachment_image_url($theme_logo_id, 'full');
        if ($url) {
            return $url;
        }
    }

    return '';
}

/**
 * Canonical HTTPS site URL with trailing slash.
 */
function hr_sa_jsonld_site_url(): string
{
    static $site = '';
    if ($site !== '') {
        return $site;
    }

    $site = trailingslashit(set_url_scheme(home_url('/'), 'https'));
    return $site;
}

function hr_sa_jsonld_org_id(): string
{
    return hr_sa_jsonld_site_url() . '#org';
}

function hr_sa_jsonld_website_id(): string
{
    return hr_sa_jsonld_site_url() . '#website';
}

/**
 * Determine current URL similar to legacy behavior.
 */
function hr_sa_jsonld_current_url(): string
{
    if (is_front_page() || is_home()) {
        return hr_sa_jsonld_site_url();
    }

    if (is_singular()) {
        $permalink = get_permalink();
        if ($permalink) {
            return trailingslashit($permalink);
        }
    }

    global $wp;
    if ($wp instanceof WP) {
        $request = (string) $wp->request;
        if ($request !== '') {
            return home_url(add_query_arg([], $request));
        }
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
    return home_url($uri);
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
    $site    = hr_sa_jsonld_site_url();
    $has_org = false;

    foreach ($graph as $index => $node) {
        if (!is_array($node)) {
            continue;
        }

        $types = hr_sa_jsonld_normalize_type($node['@type'] ?? null);
        if (in_array('Organization', $types, true)) {
            $graph[$index]['@id'] = $org_id;
            $graph[$index]['url'] = $site;
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
        if (in_array('Product', $types, true)) {
            $graph[$index]['brand'] = [
                '@type' => 'Brand',
                '@id'   => $org_id,
            ];
        }
    }

    return $graph;
}

/**
 * Build the Organization node using legacy settings.
 */
function hr_sa_jsonld_build_organization_node(): array
{
    $site_url    = hr_sa_jsonld_site_url();
    $organization = [
        '@type' => 'Organization',
        '@id'   => hr_sa_jsonld_org_id(),
        'name'  => (string) hr_sa_jsonld_get_schema_option('org_name', get_bloginfo('name')),
        'url'   => $site_url,
    ];

    $logo_url = hr_sa_jsonld_get_logo_url();
    if ($logo_url) {
        $organization['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo_url,
        ];
    }

    $optionals = [
        'legalName'    => 'org_legal_name',
        'slogan'       => 'org_slogan',
        'description'  => 'org_description',
        'foundingDate' => 'org_founding_date',
    ];

    foreach ($optionals as $field => $key) {
        $value = hr_sa_jsonld_get_schema_option($key);
        if ($value !== '') {
            $organization[$field] = $value;
        }
    }

    $address = [
        'streetAddress'   => hr_sa_jsonld_get_schema_option('org_address_street'),
        'addressLocality' => hr_sa_jsonld_get_schema_option('org_address_locality'),
        'addressRegion'   => hr_sa_jsonld_get_schema_option('org_address_region'),
        'postalCode'      => hr_sa_jsonld_get_schema_option('org_address_postal'),
        'addressCountry'  => hr_sa_jsonld_get_schema_option('org_address_country'),
    ];

    if (array_filter($address, static fn($value) => $value !== '')) {
        $organization['address'] = array_merge(['@type' => 'PostalAddress'], $address);
    }

    $same_as_raw = hr_sa_jsonld_get_schema_option('org_sameas', '');
    if ($same_as_raw !== '') {
        $same_as = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $same_as_raw) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $same_as[] = esc_url_raw($line);
        }
        if ($same_as) {
            $organization['sameAs'] = $same_as;
        }
    }

    $contact_points_raw = hr_sa_jsonld_get_schema_option('org_contact_points', '');
    if ($contact_points_raw !== '') {
        $decoded = json_decode((string) $contact_points_raw, true);
        if (is_array($decoded) && $decoded) {
            $contact_points = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $contact_point = ['@type' => 'ContactPoint'];
                if (!empty($row['contactType'])) {
                    $contact_point['contactType'] = $row['contactType'];
                }
                if (!empty($row['telephone'])) {
                    $contact_point['telephone'] = preg_replace('/\s+/u', '', (string) $row['telephone']);
                }
                if (!empty($row['email'])) {
                    $contact_point['email'] = $row['email'];
                }
                if (!empty($row['areaServed'])) {
                    $contact_point['areaServed'] = $row['areaServed'];
                }
                if (!empty($row['availableLanguage'])) {
                    $contact_point['availableLanguage'] = $row['availableLanguage'];
                }
                if (!empty($row['contactOption'])) {
                    $contact_point['contactOption'] = $row['contactOption'];
                }

                if (!empty($contact_point['telephone']) || !empty($contact_point['email'])) {
                    $contact_points[] = $contact_point;
                }
            }

            if ($contact_points) {
                $organization['contactPoint'] = $contact_points;
            }
        }
    }

    return $organization;
}

/**
 * Build the WebSite node.
 */
function hr_sa_jsonld_build_website_node(): array
{
    return [
        '@type' => 'WebSite',
        '@id'   => hr_sa_jsonld_website_id(),
        'url'   => hr_sa_jsonld_site_url(),
        'name'  => get_bloginfo('name'),
    ];
}

/**
 * Build the WebPage node for the current view.
 */
function hr_sa_jsonld_build_webpage_node(): array
{
    $current_url = hr_sa_jsonld_current_url();

    return [
        '@type'    => 'WebPage',
        '@id'      => trailingslashit($current_url) . '#webpage',
        'url'      => $current_url,
        'name'     => function_exists('wp_get_document_title') ? wp_get_document_title() : get_the_title(),
        'isPartOf' => ['@id' => hr_sa_jsonld_website_id()],
        'about'    => ['@id' => hr_sa_jsonld_org_id()],
    ];
}

require_once __DIR__ . '/org.php';
require_once __DIR__ . '/itinerary.php';
require_once __DIR__ . '/faq.php';
require_once __DIR__ . '/vehicles.php';
require_once __DIR__ . '/trip.php';
