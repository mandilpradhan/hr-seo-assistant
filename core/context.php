<?php
/**
 * HRDF-backed context helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build a snapshot of HRDF values for the current request.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_context(): array
{
    $post_id = is_singular() ? (int) get_queried_object_id() : 0;

    $meta_title       = hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.meta.title', $post_id, ''));
    $meta_description = hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.meta.description', $post_id, ''));
    $canonical_url    = hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.meta.canonical_url', $post_id, ''));
    $meta_og_type     = hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.meta.og_type', $post_id, ''));
    $hero_image       = hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.hero.image_url', $post_id, ''));

    $site_name            = hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.site.name', null, ''));
    $site_url             = hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.site.url', null, ''));
    $site_logo            = hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.site.logo_url', null, ''));
    $site_search_template = hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.site.search_url_template', null, ''));

    $org_address = hr_sa_hrdf_address(hr_sa_hrdf_get('hrdf.org.address', null, []));
    $org_geo     = hr_sa_hrdf_geo(hr_sa_hrdf_get('hrdf.org.geo', null, []));

    $context = [
        'post_id' => $post_id,
        'site'    => [
            'name'                 => $site_name,
            'url'                  => $site_url,
            'logo_url'             => $site_logo,
            'search_url_template'  => $site_search_template,
        ],
        'org'     => [
            'legalName'   => hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.org.legalName', null, '')),
            'slogan'      => hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.org.slogan', null, '')),
            'description' => hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.org.description', null, '')),
            'foundingDate'=> hr_sa_hrdf_string(hr_sa_hrdf_get('hrdf.org.foundingDate', null, '')),
            'sameAs'      => hr_sa_hrdf_url_list(hr_sa_hrdf_get('hrdf.org.sameAs', null, [])),
            'contactPoint'=> hr_sa_hrdf_contact_points(hr_sa_hrdf_get('hrdf.org.contactPoint', null, [])),
            'address'     => $org_address,
            'geo'         => $org_geo,
        ],
        'meta'    => [
            'title'         => $meta_title,
            'description'   => $meta_description,
            'canonical_url' => $canonical_url,
            'og_type'       => $meta_og_type,
        ],
        'hero'    => [
            'image_url' => $hero_image,
        ],
        'offer'   => hr_sa_hrdf_array(hr_sa_hrdf_get('hrdf.offer.primary', $post_id, [])),
        'trip'    => [
            'dates' => hr_sa_hrdf_array(hr_sa_hrdf_get('hrdf.trip.dates', $post_id, [])),
        ],
        'policy'  => [
            'privacy_url' => hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.policy.privacy_url', null, '')),
            'terms_url'   => hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.policy.terms_url', null, '')),
            'refund_url'  => hr_sa_hrdf_url(hr_sa_hrdf_get('hrdf.policy.refund_url', null, '')),
        ],
    ];

    $resolved_type = $meta_og_type !== '' ? $meta_og_type : (hr_sa_context_has_trip($context) ? 'product' : 'website');

    $context['title']          = $meta_title;
    $context['description']    = $meta_description;
    $context['url']            = $canonical_url;
    $context['canonical_url']  = $canonical_url;
    $context['image']          = $hero_image;
    $context['site_name']      = $site_name;
    $context['type']           = $resolved_type;
    $context['has_trip']       = hr_sa_context_has_trip($context);

    /**
     * Filter the resolved HRDF context snapshot.
     *
     * @param array<string, mixed> $context
     */
    return apply_filters('hr_sa_get_context', $context);
}

/**
 * Determine whether the context includes trip indicators.
 */
function hr_sa_context_has_trip(array $context): bool
{
    $offer = $context['offer'] ?? [];
    if (is_array($offer) && !empty($offer)) {
        return true;
    }

    $trip_dates = $context['trip']['dates'] ?? [];
    if (is_array($trip_dates)) {
        foreach ($trip_dates as $entry) {
            if (is_array($entry) && !empty($entry)) {
                return true;
            }
            if (is_scalar($entry) && trim((string) $entry) !== '') {
                return true;
            }
        }
    }

    return false;
}

/**
 * Proxy for hr_df_get() that tolerates missing integrations.
 *
 * @param mixed $default
 *
 * @return mixed
 */
function hr_sa_hrdf_get(string $key, ?int $post_id = null, $default = null)
{
    if (!function_exists('hr_df_get')) {
        return $default;
    }

    $target = $post_id ?? 0;

    return hr_df_get($key, $target, $default);
}

/**
 * Retrieve the raw HRDF document for inspection.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_hrdf_document(?int $post_id = null): array
{
    if (!function_exists('hr_df_document')) {
        return [];
    }

    $document = hr_df_document($post_id ?? 0);

    return is_array($document) ? $document : [];
}

/**
 * Normalize a value into a trimmed string.
 */
function hr_sa_hrdf_string($value): string
{
    if (is_string($value)) {
        $value = trim($value);
        return $value;
    }

    if (is_scalar($value)) {
        return trim((string) $value);
    }

    return '';
}

/**
 * Normalize a value into an HTTPS URL string.
 */
function hr_sa_hrdf_url($value): string
{
    $string = hr_sa_hrdf_string($value);
    if ($string === '') {
        return '';
    }

    $sanitized = esc_url_raw($string);
    if ($sanitized === '' || !wp_http_validate_url($sanitized)) {
        return '';
    }

    $scheme = strtolower((string) wp_parse_url($sanitized, PHP_URL_SCHEME));
    if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    return set_url_scheme($sanitized, 'https');
}

/**
 * Normalize a list of stringable values.
 *
 * @param mixed $value
 *
 * @return array<int, mixed>
 */
function hr_sa_hrdf_array($value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values($value);
}

/**
 * Normalize a list of URLs.
 *
 * @param mixed $value
 *
 * @return array<int, string>
 */
function hr_sa_hrdf_url_list($value): array
{
    $list = [];
    if (!is_array($value)) {
        return $list;
    }

    foreach ($value as $candidate) {
        $url = hr_sa_hrdf_url($candidate);
        if ($url !== '') {
            $list[] = $url;
        }
    }

    return $list;
}

/**
 * Normalize contact point rows.
 *
 * @param mixed $value
 *
 * @return array<int, array<string, string>>
 */
function hr_sa_hrdf_contact_points($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $points = [];

    foreach ($value as $row) {
        if (!is_array($row)) {
            continue;
        }

        $contact = [];
        $telephone = hr_sa_hrdf_string($row['telephone'] ?? '');
        if ($telephone !== '') {
            $contact['telephone'] = preg_replace('/\s+/u', '', $telephone);
        }

        $email = hr_sa_hrdf_string($row['email'] ?? '');
        if ($email !== '') {
            $contact['email'] = $email;
        }

        $contact_type = hr_sa_hrdf_string($row['contactType'] ?? '');
        if ($contact_type !== '') {
            $contact['contactType'] = $contact_type;
        }

        $area_served = hr_sa_hrdf_string($row['areaServed'] ?? '');
        if ($area_served !== '') {
            $contact['areaServed'] = $area_served;
        }

        if ($contact) {
            $points[] = $contact;
        }
    }

    return $points;
}

/**
 * Normalize an address payload.
 *
 * @param mixed $value
 *
 * @return array<string, string>
 */
function hr_sa_hrdf_address($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $keys = ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'];
    $normalized = [];

    foreach ($keys as $key) {
        $string = hr_sa_hrdf_string($value[$key] ?? '');
        if ($string !== '') {
            $normalized[$key] = $string;
        }
    }

    return $normalized;
}

/**
 * Normalize geo coordinates.
 *
 * @param mixed $value
 *
 * @return array<string, float|string>
 */
function hr_sa_hrdf_geo($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $lat = $value['latitude'] ?? null;
    $lng = $value['longitude'] ?? null;

    if ($lat === null || $lng === null) {
        return [];
    }

    $latitude  = is_numeric($lat) ? (float) $lat : hr_sa_hrdf_string($lat);
    $longitude = is_numeric($lng) ? (float) $lng : hr_sa_hrdf_string($lng);

    if ($latitude === '' || $longitude === '') {
        return [];
    }

    return [
        'latitude'  => $latitude,
        'longitude' => $longitude,
    ];
}
