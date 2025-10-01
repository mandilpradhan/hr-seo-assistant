<?php
/**
 * Organization/WebSite/WebPage nodes sourced from HRDF.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

hr_sa_jsonld_register_emitter('org', 'hr_sa_jsonld_emit_org_graph');

/**
 * Emit base graph nodes for organization/site/webpage.
 *
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_emit_org_graph(int $post_id = 0): array
{
    $site_profile  = hr_sa_resolve_site_profile();
    $meta_profile  = hr_sa_resolve_meta_profile($post_id);
    $image_profile = hr_sa_resolve_image_profile($post_id);

    $site_url = isset($site_profile['url']) && is_string($site_profile['url'])
        ? (string) $site_profile['url']
        : '';
    $site_name = isset($site_profile['name']) && is_string($site_profile['name'])
        ? (string) $site_profile['name']
        : '';
    $logo = isset($site_profile['logo']) && is_string($site_profile['logo'])
        ? (string) $site_profile['logo']
        : null;

    if ($site_url === '' || $site_name === '') {
        return [];
    }

    $canonical = $meta_profile['canonical_url'] ?? '';
    $page_title = $meta_profile['title'] ?? '';
    $page_description = $meta_profile['description'] ?? '';

    $org_id     = rtrim($site_url, '/') . '#org';
    $website_id = rtrim($site_url, '/') . '#website';
    $webpage_id = $canonical !== '' ? rtrim($canonical, '/') . '#webpage' : '';

    $organization = hr_sa_jsonld_build_organization_node($org_id, $site_url, $site_name, $logo);
    if ($organization === null) {
        return [];
    }

    $website = hr_sa_jsonld_build_website_node($website_id, $site_url, $site_name, $org_id);
    $webpage = $webpage_id !== ''
        ? hr_sa_jsonld_build_webpage_node($webpage_id, $canonical, $page_title, $website_id, $org_id, $page_description, $image_profile)
        : null;

    $graph = [$organization];

    if ($website !== null) {
        $graph[] = $website;
    }

    if ($webpage !== null) {
        $graph[] = $webpage;
    }

    return $graph;
}

/**
 * Build the Organization node from HRDF data.
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_build_organization_node(string $org_id, string $site_url, string $site_name, ?string $logo): ?array
{
    if ($site_name === '' || $site_url === '') {
        return null;
    }

    $organization = [
        '@type' => 'Organization',
        '@id'   => $org_id,
        'url'   => $site_url,
        'name'  => $site_name,
    ];

    $legal_name = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get_first([
        'hrdf.org.legalName',
    ], 0, ''));
    if ($legal_name !== '') {
        $organization['legalName'] = $legal_name;
    }

    if ($logo) {
        $organization['logo'] = [
            '@type' => 'ImageObject',
            'url'   => $logo,
        ];
    }

    $price_range = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get_first([
        'hrdf.org.priceRange',
    ], 0, ''));
    if ($price_range !== '') {
        $organization['priceRange'] = $price_range;
    }

    $vat_id = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get_first([
        'hrdf.org.vatId',
    ], 0, ''));
    if ($vat_id !== '') {
        $organization['vatID'] = $vat_id;
    }

    $registration_number = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get_first([
        'hrdf.org.registrationNumber',
    ], 0, ''));
    if ($registration_number !== '') {
        $organization['registrationNumber'] = $registration_number;
    }

    $same_as = hr_sa_jsonld_collect_urls((array) hr_sa_hrdf_get_first([
        'hrdf.org.sameAs',
    ], 0, []));
    if ($same_as) {
        $organization['sameAs'] = $same_as;
    }

    $contact_points = hr_sa_jsonld_prepare_contact_points((array) hr_sa_hrdf_get_first([
        'hrdf.org.contactPoints',
        'hrdf.org.contactPoint',
    ], 0, []));
    if ($contact_points) {
        $organization['contactPoint'] = $contact_points;
    }

    $address = hr_sa_jsonld_prepare_postal_address((array) hr_sa_hrdf_get_first([
        'hrdf.org.address',
    ], 0, []));
    if ($address) {
        $organization['address'] = $address;
    }

    $geo = hr_sa_jsonld_prepare_geo((array) hr_sa_hrdf_get_first([
        'hrdf.org.geo',
    ], 0, []));
    if ($geo) {
        $organization['geo'] = $geo;
    }

    $opening_hours = hr_sa_jsonld_prepare_opening_hours((array) hr_sa_hrdf_get_first([
        'hrdf.org.openingHours',
    ], 0, []));
    if ($opening_hours) {
        $organization['openingHoursSpecification'] = $opening_hours;
    }

    return $organization;
}

/**
 * Build the WebSite node.
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_build_website_node(string $website_id, string $site_url, string $site_name, string $org_id): ?array
{
    if ($site_url === '' || $site_name === '') {
        return null;
    }

    $website = [
        '@type'     => 'WebSite',
        '@id'       => $website_id,
        'url'       => $site_url,
        'name'      => $site_name,
        'publisher' => [
            '@id' => $org_id,
        ],
    ];

    $search_template = hr_sa_hrdf_get_first([
        'hrdf.website.search_url_template',
        'hrdf.site.search_url_template',
    ], 0, '');
    if (is_string($search_template) && strpos($search_template, '{search_term_string}') !== false) {
        $website['potentialAction'] = [
            '@type'       => 'SearchAction',
            'target'      => str_replace('{search_term_string}', '{search_term_string}', $search_template),
            'query-input' => 'required name=search_term_string',
        ];
    }

    $policy_urls = hr_sa_jsonld_collect_policy_urls();
    if ($policy_urls) {
        $website['publishingPrinciples'] = $policy_urls;
    }

    return $website;
}

/**
 * Build the WebPage node for the current view.
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_build_webpage_node(
    string $webpage_id,
    string $canonical,
    string $title,
    string $website_id,
    string $org_id,
    string $description,
    array $image_profile
): ?array {
    if ($canonical === '' || $title === '') {
        return null;
    }

    $webpage = [
        '@type'    => 'WebPage',
        '@id'      => $webpage_id,
        'url'      => $canonical,
        'name'     => $title,
        'isPartOf' => ['@id' => $website_id],
        'about'    => ['@id' => $org_id],
    ];

    if ($description !== '') {
        $webpage['description'] = $description;
    }

    $primary_image = $image_profile['primary'] ?? null;
    if (is_string($primary_image) && $primary_image !== '') {
        $webpage['image'] = $primary_image;
        $webpage['primaryImageOfPage'] = [
            '@type' => 'ImageObject',
            'url'   => $primary_image,
        ];
    }

    return $webpage;
}

/**
 * Normalize policy URLs for WebSite.publishingPrinciples.
 *
 * @return array<int, string>
 */
function hr_sa_jsonld_collect_policy_urls(): array
{
    $policies = [
        (string) hr_sa_hrdf_get('hrdf.policy.privacy_url', 0, ''),
        (string) hr_sa_hrdf_get('hrdf.policy.terms_url', 0, ''),
        (string) hr_sa_hrdf_get('hrdf.policy.refund_url', 0, ''),
    ];

    $urls = [];
    foreach ($policies as $policy_url) {
        $normalized = hr_sa_normalize_url($policy_url);
        if ($normalized && !in_array($normalized, $urls, true)) {
            $urls[] = $normalized;
        }
    }

    return $urls;
}

/**
 * Prepare ContactPoint entries provided by HRDF.
 *
 * @param array<int, mixed> $raw
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_prepare_contact_points(array $raw): array
{
    $points = [];
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $contact_point = ['@type' => 'ContactPoint'];

        foreach (['contactType', 'telephone', 'email', 'areaServed', 'availableLanguage', 'contactOption'] as $field) {
            if (!empty($entry[$field]) && is_string($entry[$field])) {
                $value = hr_sa_sanitize_text_value((string) $entry[$field]);
                if ($value !== '') {
                    $contact_point[$field] = $value;
                }
            }
        }

        if (!isset($contact_point['telephone']) && !isset($contact_point['email'])) {
            continue;
        }

        $points[] = $contact_point;
    }

    return $points;
}

/**
 * Prepare PostalAddress structure when provided by HRDF.
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_prepare_postal_address(array $raw): array
{
    if (!$raw) {
        return [];
    }

    $address = ['@type' => 'PostalAddress'];
    foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $field) {
        if (!empty($raw[$field]) && is_string($raw[$field])) {
            $value = hr_sa_sanitize_text_value((string) $raw[$field]);
            if ($value !== '') {
                $address[$field] = $value;
            }
        }
    }

    return count($address) > 1 ? $address : [];
}

/**
 * Normalize GeoCoordinates structure when provided by HRDF.
 *
 * @return array<string, mixed>
 */
function hr_sa_jsonld_prepare_geo(array $raw): array
{
    if (!$raw) {
        return [];
    }

    $geo = ['@type' => 'GeoCoordinates'];
    foreach (['latitude', 'longitude'] as $field) {
        if (isset($raw[$field]) && is_numeric($raw[$field])) {
            $geo[$field] = (float) $raw[$field];
        }
    }

    return count($geo) > 1 ? $geo : [];
}

/**
 * Normalize OpeningHoursSpecification entries when provided by HRDF.
 *
 * @param array<int, mixed> $raw
 * @return array<int, array<string, mixed>>
 */
function hr_sa_jsonld_prepare_opening_hours(array $raw): array
{
    $items = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $spec = ['@type' => 'OpeningHoursSpecification'];
        if (!empty($row['dayOfWeek']) && is_array($row['dayOfWeek'])) {
            $spec['dayOfWeek'] = array_values(array_filter(array_map('strval', $row['dayOfWeek'])));
        }
        if (!empty($row['opens']) && is_string($row['opens'])) {
            $spec['opens'] = $row['opens'];
        }
        if (!empty($row['closes']) && is_string($row['closes'])) {
            $spec['closes'] = $row['closes'];
        }
        if (!empty($row['validFrom']) && is_string($row['validFrom'])) {
            $spec['validFrom'] = $row['validFrom'];
        }
        if (!empty($row['validThrough']) && is_string($row['validThrough'])) {
            $spec['validThrough'] = $row['validThrough'];
        }

        if (count($spec) > 1) {
            $items[] = $spec;
        }
    }

    return $items;
}

/**
 * Sanitize an array of URL strings.
 *
 * @param array<int, mixed> $urls
 * @return array<int, string>
 */
function hr_sa_jsonld_collect_urls(array $urls): array
{
    $results = [];
    foreach ($urls as $url) {
        if (!is_string($url)) {
            continue;
        }

        $normalized = hr_sa_normalize_url($url);
        if ($normalized && !in_array($normalized, $results, true)) {
            $results[] = $normalized;
        }
    }

    return $results;
}
