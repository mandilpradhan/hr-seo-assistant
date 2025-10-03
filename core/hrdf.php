<?php
/**
 * HR Data Framework integration helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve a value from the HR Data Framework using helper accessors when available.
 *
 * @param mixed $default
 * @return mixed
 */
function hr_sa_hrdf_get(string $path, ?int $post_id = null, $default = null)
{
    $path       = trim($path);
    $accessor   = 'hr_df_' . str_replace('.', '_', $path);
    $has_post   = $post_id !== null;
    $post_id    = $has_post ? (int) $post_id : null;
    $parameters = $has_post ? [$post_id] : [];

    if (function_exists($accessor)) {
        return $accessor(...$parameters);
    }

    if (function_exists('hr_df_get')) {
        return hr_df_get($path, $has_post ? $post_id : null, $default);
    }

    return $default;
}

/**
 * Convenience wrapper for site-level lookups.
 *
 * @param mixed $default
 * @return mixed
 */
function hr_sa_hrdf_site(string $path, $default = null)
{
    return hr_sa_hrdf_get('site.' . $path, null, $default);
}

/**
 * Convenience wrapper for trip-level lookups.
 *
 * @param mixed $default
 * @return mixed
 */
function hr_sa_hrdf_trip(string $path, int $post_id, $default = null)
{
    return hr_sa_hrdf_get('trip.' . $path, $post_id, $default);
}

/**
 * Normalize arbitrary structures into plain arrays.
 *
 * @param mixed $value
 * @return array<mixed>
 */
function hr_sa_hrdf_force_array($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_object($value)) {
        return get_object_vars($value);
    }

    return [];
}

/**
 * Select the first available value from a list of keys within a source array.
 *
 * @param array<string, mixed> $source
 * @param array<int, string>   $keys
 * @param mixed                $default
 * @return mixed
 */
function hr_sa_hrdf_pick(array $source, array $keys, $default = null)
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }
    }

    return $default;
}

/**
 * Normalize arbitrary values into trimmed strings.
 */
function hr_sa_hrdf_normalize_text($value): string
{
    if (is_array($value) || is_object($value)) {
        $value = wp_json_encode($value);
    }

    $text = wp_strip_all_tags((string) $value, true);
    $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    $text = (string) preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

/**
 * Normalize URL values and coerce to HTTPS when possible.
 */
function hr_sa_hrdf_normalize_url($value): string
{
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }

    $sanitized = esc_url_raw($url);
    if ($sanitized === '') {
        return '';
    }

    return set_url_scheme($sanitized, 'https');
}

/**
 * Normalize a list of URLs.
 *
 * @param mixed $value
 * @return array<int, string>
 */
function hr_sa_hrdf_normalize_url_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $urls = [];
    foreach ($value as $candidate) {
        $normalized = hr_sa_hrdf_normalize_url($candidate);
        if ($normalized !== '') {
            $urls[] = $normalized;
        }
    }

    return array_values(array_unique($urls));
}

/**
 * Format a numeric price for schema output.
 */
function hr_sa_hrdf_format_price(float $value): string
{
    return number_format($value, 2, '.', '');
}

/**
 * Cast arbitrary values to array.
 *
 * @param mixed $value
 * @return array<int, mixed>
 */
function hr_sa_hrdf_to_array($value): array
{
    return is_array($value) ? $value : [];
}

/**
 * Retrieve and cache site-level HRDF payload.
 *
 * @return array<string, mixed>
 */
function hr_sa_hrdf_site_payload(?int $post_id = null): array
{
    if ($post_id === null && is_singular('trip')) {
        $post_id = (int) get_queried_object_id();
    }

    static $cache = [];
    $key = $post_id ?? 0;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if ($post_id === null || $post_id <= 0) {
        return $cache[$key] = [];
    }

    $sections     = hr_sa_hrdf_trip_schema_sections($post_id);
    $site_section = hr_sa_hrdf_force_array($sections['site'] ?? []);
    $org_section  = hr_sa_hrdf_force_array($sections['organization'] ?? []);
    $og_section   = hr_sa_hrdf_force_array($sections['og'] ?? []);
    $twitter      = hr_sa_hrdf_force_array($sections['twitter'] ?? []);

    $name     = hr_sa_hrdf_normalize_text((string) ($site_section['name'] ?? ''));
    $url      = hr_sa_hrdf_normalize_url($site_section['url'] ?? '');
    $logo     = hr_sa_hrdf_normalize_url((string) hr_sa_hrdf_pick($site_section, ['logo_url', 'logo'], ''));
    $locale   = hr_sa_hrdf_normalize_text((string) ($site_section['locale'] ?? ''));
    $og_name  = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($og_section, ['site_name'], $site_section['og_site_name'] ?? ''));

    $address_raw = hr_sa_hrdf_force_array(hr_sa_hrdf_pick($org_section, ['address'], []));
    $address     = [];
    foreach (
        [
            'streetAddress'   => ['streetAddress', 'street'],
            'addressLocality' => ['addressLocality', 'locality'],
            'addressRegion'   => ['addressRegion', 'region'],
            'postalCode'      => ['postalCode', 'postal'],
            'addressCountry'  => ['addressCountry', 'country'],
        ] as $field => $candidates
    ) {
        $value = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($address_raw, $candidates, ''));
        if ($value !== '') {
            $address[$field] = $value;
        }
    }

    $same_as = [];
    $raw_same = hr_sa_hrdf_force_array(hr_sa_hrdf_pick($org_section, ['same_as', 'sameAs'], []));
    foreach ($raw_same as $entry) {
        $candidate = hr_sa_hrdf_normalize_url($entry);
        if ($candidate !== '') {
            $same_as[] = $candidate;
        }
    }

    $contact_points = [];
    $raw_points     = hr_sa_hrdf_force_array(hr_sa_hrdf_pick($org_section, ['contact_points', 'contactPoint'], []));
    foreach ($raw_points as $row) {
        if (!is_array($row) && !is_object($row)) {
            continue;
        }

        $row           = hr_sa_hrdf_force_array($row);
        $contact_point = ['@type' => 'ContactPoint'];
        $contact_type  = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($row, ['contactType', 'type'], ''));
        $telephone     = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($row, ['telephone', 'phone'], ''));
        $email         = sanitize_email((string) hr_sa_hrdf_pick($row, ['email'], ''));
        $area_served   = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($row, ['areaServed'], ''));
        $language      = hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($row, ['availableLanguage', 'language'], ''));

        if ($contact_type !== '') {
            $contact_point['contactType'] = $contact_type;
        }
        if ($telephone !== '') {
            $contact_point['telephone'] = preg_replace('/\s+/u', '', $telephone) ?: $telephone;
        }
        if ($email !== '') {
            $contact_point['email'] = $email;
        }
        if ($area_served !== '') {
            $contact_point['areaServed'] = $area_served;
        }
        if ($language !== '') {
            $contact_point['availableLanguage'] = $language;
        }

        if (count($contact_point) > 1) {
            $contact_points[] = $contact_point;
        }
    }

    $twitter_payload = array_filter(
        [
            'site'    => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($twitter, ['site', 'site_handle'], '')),
            'creator' => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($twitter, ['creator'], '')),
            'handle'  => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($twitter, ['handle'], '')),
        ],
        static fn(string $value): bool => $value !== ''
    );

    $org_payload = [
        'name'       => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($org_section, ['name'], '')),
        'legal_name' => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($org_section, ['legal_name', 'legalName'], '')),
        'address'    => $address,
        'same_as'    => $same_as,
        'contact'    => $contact_points,
    ];

    foreach ($org_payload as $field => $value) {
        if (is_array($value)) {
            if ($value === []) {
                unset($org_payload[$field]);
            }
            continue;
        }

        if ($value === '') {
            unset($org_payload[$field]);
        }
    }

    $payload = [
        'name'     => $name,
        'url'      => $url,
        'logo_url' => $logo,
        'locale'   => $locale,
        'og_name'  => $og_name,
        'twitter'  => is_array($twitter_payload) ? $twitter_payload : [],
        'org'      => $org_payload,
    ];

    foreach ($payload as $field => $value) {
        if (in_array($field, ['twitter', 'org'], true)) {
            $payload[$field] = is_array($value) ? $value : [];
            continue;
        }

        if (is_array($value) && $value === []) {
            unset($payload[$field]);
            continue;
        }

        if (is_string($value) && $value === '') {
            unset($payload[$field]);
        }
    }

    return $cache[$key] = $payload;
}

/**
 * Retrieve the batched HRDF payload for a trip request.
 *
 * @return array<string, mixed>
 */
function hr_sa_hrdf_trip_schema_payload(int $post_id): array
{
    static $cache = [];
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    if (!function_exists('hrdf_trip_schema_payload')) {
        return $cache[$post_id] = [];
    }

    $payload = hrdf_trip_schema_payload($post_id);
    if (!is_array($payload)) {
        return $cache[$post_id] = [];
    }

    return $cache[$post_id] = $payload;
}

/**
 * Extract normalized sections from the batched trip payload.
 *
 * @return array{
 *     site: array<mixed>,
 *     organization: array<mixed>,
 *     webpage: array<mixed>,
 *     trip: array<mixed>,
 *     product: array<mixed>,
 *     og: array<mixed>,
 *     twitter: array<mixed>
 * }
 */
function hr_sa_hrdf_trip_schema_sections(int $post_id): array
{
    static $cache = [];
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $payload  = hr_sa_hrdf_trip_schema_payload($post_id);
    $sections = [
        'site'         => [],
        'organization' => [],
        'webpage'      => [],
        'trip'         => [],
        'product'      => [],
        'og'           => [],
        'twitter'      => [],
    ];

    foreach (array_keys($sections) as $key) {
        $sections[$key] = hr_sa_hrdf_force_array($payload[$key] ?? []);
    }

    return $cache[$post_id] = $sections;
}

/**
 * Retrieve and cache the full HRDF payload for a trip.
 *
 * @return array<string, mixed>
 */
function hr_sa_hrdf_trip_payload(int $post_id): array
{
    static $cache = [];
    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

    $sections = hr_sa_hrdf_trip_schema_sections($post_id);
    $trip     = hr_sa_hrdf_force_array($sections['trip'] ?? []);

    $aggregate = hr_sa_hrdf_force_array(hr_sa_hrdf_pick($trip, ['aggregate_rating', 'aggregateRating'], []));

    $payload = [
        'url'                 => hr_sa_hrdf_normalize_url((string) hr_sa_hrdf_pick($trip, ['url'], '')),
        'title'               => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($trip, ['title', 'name'], '')),
        'description'         => hr_sa_hrdf_normalize_text((string) hr_sa_hrdf_pick($trip, ['description'], '')),
        'images'              => hr_sa_hrdf_normalize_url_list(hr_sa_hrdf_pick($trip, ['images', 'image'], [])),
        'additional_property' => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['additional_property', 'additionalProperty'], [])),
        'itinerary'           => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['itinerary', 'itinerary.steps', 'itinerarySteps'], [])),
        'faq'                 => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['faq'], [])),
        'vehicles'            => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['vehicles'], [])),
        'reviews'             => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['reviews'], [])),
        'aggregate_rating'    => $aggregate !== [] ? $aggregate : null,
        'offers'              => hr_sa_hrdf_to_array(hr_sa_hrdf_pick($trip, ['offers'], [])),
        'product'             => hr_sa_hrdf_force_array($sections['product'] ?? []),
        'og'                  => hr_sa_hrdf_force_array($sections['og'] ?? []),
        'twitter'             => hr_sa_hrdf_force_array($sections['twitter'] ?? []),
    ];

    return $cache[$post_id] = $payload;
}
