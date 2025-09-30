<?php
/**
 * HRDF-backed JSON-LD emitter.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<int, array<string, mixed>> $hr_sa_jsonld_last_graph */
$GLOBALS['hr_sa_jsonld_last_graph'] = [];

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

    add_action('wp_head', 'hr_sa_jsonld_print_graph', 5);
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

    $context = hr_sa_get_context();
    $graph   = hr_sa_jsonld_build_graph($context);

    $GLOBALS['hr_sa_jsonld_last_graph'] = $graph;

    if (!$graph) {
        return;
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values($graph),
    ];

    $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return;
    }

    echo '<script type="application/ld+json">' . $json . '</script>' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Build the HRDF graph for the provided context.
 *
 * @param array<string, mixed> $context
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_build_graph(array $context): array
{
    $graph = [];

    $org_node     = hr_sa_jsonld_build_organization_node($context);
    $website_node = hr_sa_jsonld_build_website_node($context);
    $webpage_node = hr_sa_jsonld_build_webpage_node($context);
    $product_node = hr_sa_jsonld_build_product_node($context);

    foreach ([$org_node, $website_node, $webpage_node, $product_node] as $node) {
        if (is_array($node) && $node) {
            $graph[] = $node;
        }
    }

    return $graph;
}

/**
 * Retrieve the list of emitters that produced nodes.
 *
 * @return array<int, string>
 */
function hr_sa_jsonld_get_active_emitters(): array
{
    return $GLOBALS['hr_sa_jsonld_last_graph'] ? ['hrdf'] : [];
}

/**
 * Build the Organization node when enough data is present.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>|null
 */
function hr_sa_jsonld_build_organization_node(array $context): ?array
{
    $site = $context['site'] ?? [];
    $org  = $context['org'] ?? [];

    $site_url  = hr_sa_jsonld_sanitize_url($site['url'] ?? '');
    $site_name = hr_sa_jsonld_sanitize_text($site['name'] ?? '');

    if ($site_url === '' || $site_name === '') {
        return null;
    }

    $node = [
        '@type' => 'Organization',
        '@id'   => hr_sa_jsonld_append_fragment($site_url, 'org'),
        'name'  => $site_name,
        'url'   => $site_url,
    ];

    $logo = hr_sa_jsonld_sanitize_url($site['logo_url'] ?? '');
    if ($logo !== '') {
        $node['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo,
        ];
    }

    $optionals = ['legalName', 'slogan', 'description', 'foundingDate'];
    foreach ($optionals as $key) {
        $value = hr_sa_jsonld_sanitize_text($org[$key] ?? '');
        if ($value !== '') {
            $node[$key] = $value;
        }
    }

    if (!empty($org['sameAs']) && is_array($org['sameAs'])) {
        $same_as = [];
        foreach ($org['sameAs'] as $candidate) {
            $url = hr_sa_jsonld_sanitize_url($candidate);
            if ($url !== '') {
                $same_as[] = $url;
            }
        }
        if ($same_as) {
            $node['sameAs'] = $same_as;
        }
    }

    if (!empty($org['contactPoint']) && is_array($org['contactPoint'])) {
        $contact_points = [];
        foreach ($org['contactPoint'] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $contact = ['@type' => 'ContactPoint'];
            $telephone = hr_sa_jsonld_sanitize_text($row['telephone'] ?? '');
            if ($telephone !== '') {
                $contact['telephone'] = preg_replace('/\s+/u', '', $telephone);
            }

            $email = hr_sa_jsonld_sanitize_text($row['email'] ?? '');
            if ($email !== '') {
                $contact['email'] = $email;
            }

            $contact_type = hr_sa_jsonld_sanitize_text($row['contactType'] ?? '');
            if ($contact_type !== '') {
                $contact['contactType'] = $contact_type;
            }

            $area_served = hr_sa_jsonld_sanitize_text($row['areaServed'] ?? '');
            if ($area_served !== '') {
                $contact['areaServed'] = $area_served;
            }

            if (count($contact) > 1) {
                $contact_points[] = $contact;
            }
        }

        if ($contact_points) {
            $node['contactPoint'] = $contact_points;
        }
    }

    if (!empty($org['address']) && is_array($org['address'])) {
        $address = [];
        foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $key) {
            $value = hr_sa_jsonld_sanitize_text($org['address'][$key] ?? '');
            if ($value !== '') {
                $address[$key] = $value;
            }
        }

        if ($address) {
            $node['address'] = ['@type' => 'PostalAddress'] + $address;
        }
    }

    if (!empty($org['geo']) && is_array($org['geo'])) {
        $lat = $org['geo']['latitude'] ?? null;
        $lng = $org['geo']['longitude'] ?? null;

        if ($lat !== null && $lng !== null) {
            $latitude  = is_numeric($lat) ? (float) $lat : hr_sa_jsonld_sanitize_text((string) $lat);
            $longitude = is_numeric($lng) ? (float) $lng : hr_sa_jsonld_sanitize_text((string) $lng);

            if ($latitude !== '' && $longitude !== '') {
                $node['geo'] = [
                    '@type'    => 'GeoCoordinates',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            }
        }
    }

    return $node;
}

/**
 * Build the WebSite node.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>|null
 */
function hr_sa_jsonld_build_website_node(array $context): ?array
{
    $site = $context['site'] ?? [];

    $site_url  = hr_sa_jsonld_sanitize_url($site['url'] ?? '');
    $site_name = hr_sa_jsonld_sanitize_text($site['name'] ?? '');

    if ($site_url === '' || $site_name === '') {
        return null;
    }

    $node = [
        '@type' => 'WebSite',
        '@id'   => hr_sa_jsonld_append_fragment($site_url, 'website'),
        'url'   => $site_url,
        'name'  => $site_name,
    ];

    $search_template = hr_sa_jsonld_sanitize_text($site['search_url_template'] ?? '');
    if ($search_template !== '') {
        $node['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => str_replace('%s', '{search_term_string}', $search_template),
            'query-input' => 'required name=search_term_string',
        ];
    }

    return $node;
}

/**
 * Build the WebPage node.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>|null
 */
function hr_sa_jsonld_build_webpage_node(array $context): ?array
{
    $site    = $context['site'] ?? [];
    $meta    = $context['meta'] ?? [];
    $site_url = hr_sa_jsonld_sanitize_url($site['url'] ?? '');
    $canonical = hr_sa_jsonld_sanitize_url($meta['canonical_url'] ?? '');
    $title     = hr_sa_jsonld_sanitize_text($meta['title'] ?? '');

    if ($canonical === '' || $title === '' || $site_url === '') {
        return null;
    }

    $node = [
        '@type'    => 'WebPage',
        '@id'      => hr_sa_jsonld_append_fragment($canonical, 'webpage'),
        'url'      => $canonical,
        'name'     => $title,
        'isPartOf' => ['@id' => hr_sa_jsonld_append_fragment($site_url, 'website')],
        'about'    => ['@id' => hr_sa_jsonld_append_fragment($site_url, 'org')],
    ];

    return $node;
}

/**
 * Build the Product node when a trip is detected.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, mixed>|null
 */
function hr_sa_jsonld_build_product_node(array $context): ?array
{
    if (!hr_sa_context_has_trip($context)) {
        return null;
    }

    $site = $context['site'] ?? [];
    $meta = $context['meta'] ?? [];

    $canonical = hr_sa_jsonld_sanitize_url($meta['canonical_url'] ?? '');
    $title     = hr_sa_jsonld_sanitize_text($meta['title'] ?? '');

    if ($canonical === '' || $title === '') {
        return null;
    }

    $node = [
        '@type' => 'Product',
        '@id'   => hr_sa_jsonld_append_fragment($canonical, 'trip'),
        'name'  => $title,
        'url'   => $canonical,
    ];

    $site_url = hr_sa_jsonld_sanitize_url($site['url'] ?? '');
    if ($site_url !== '') {
        $node['brand'] = [
            '@type' => 'Organization',
            '@id'   => hr_sa_jsonld_append_fragment($site_url, 'org'),
        ];
    }

    $description = hr_sa_jsonld_sanitize_text($meta['description'] ?? '');
    if ($description !== '') {
        $node['description'] = $description;
    }

    $images = hr_sa_jsonld_prepare_images($context);
    if ($images) {
        $node['image'] = $images;
    }

    $offers = $context['offer'] ?? [];
    if (is_array($offers) && !empty($offers)) {
        $node['offers'] = $offers;
    }

    return $node;
}

/**
 * Prepare product images from the context.
 *
 * @param array<string, mixed> $context
 *
 * @return array<int, string>
 */
function hr_sa_jsonld_prepare_images(array $context): array
{
    $hero = $context['hero']['image_url'] ?? '';
    $image = hr_sa_jsonld_sanitize_url($hero);

    return $image !== '' ? [$image] : [];
}

/**
 * Append a fragment identifier to a base URL.
 */
function hr_sa_jsonld_append_fragment(string $url, string $fragment): string
{
    $clean = rtrim($url, '#');

    return $clean . '#' . $fragment;
}

/**
 * Normalize a URL for JSON-LD output.
 */
function hr_sa_jsonld_sanitize_url($value): string
{
    return hr_sa_hrdf_url($value);
}

/**
 * Normalize a string for JSON-LD output.
 */
function hr_sa_jsonld_sanitize_text($value): string
{
    return hr_sa_hrdf_string($value);
}
