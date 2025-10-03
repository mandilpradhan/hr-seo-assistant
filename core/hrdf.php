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
function hr_sa_hrdf_site_payload(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $name      = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('name', ''));
    $url       = hr_sa_hrdf_normalize_url(hr_sa_hrdf_site('url', ''));
    $logo_url  = hr_sa_hrdf_normalize_url(hr_sa_hrdf_site('logo_url', ''));
    $locale    = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('locale', ''));
    $og_name   = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('og.site_name', ''));
    $same_as   = [];
    $raw_same  = hr_sa_hrdf_to_array(hr_sa_hrdf_site('org.same_as', []));
    foreach ($raw_same as $entry) {
        $candidate = hr_sa_hrdf_normalize_url($entry);
        if ($candidate !== '') {
            $same_as[] = $candidate;
        }
    }

    $contact_points = [];
    $raw_points     = hr_sa_hrdf_to_array(hr_sa_hrdf_site('org.contact_points', []));
    foreach ($raw_points as $row) {
        if (!is_array($row)) {
            continue;
        }

        $contact_point = ['@type' => 'ContactPoint'];
        $contact_type  = hr_sa_hrdf_normalize_text($row['contactType'] ?? '');
        $telephone     = hr_sa_hrdf_normalize_text($row['telephone'] ?? '');
        $email         = sanitize_email((string) ($row['email'] ?? ''));
        $area_served   = hr_sa_hrdf_normalize_text($row['areaServed'] ?? '');
        $language      = hr_sa_hrdf_normalize_text($row['availableLanguage'] ?? '');

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

    $address = [];
    $address_keys = [
        'streetAddress'   => 'org.address.street',
        'addressLocality' => 'org.address.locality',
        'addressRegion'   => 'org.address.region',
        'postalCode'      => 'org.address.postal',
        'addressCountry'  => 'org.address.country',
    ];
    foreach ($address_keys as $field => $path) {
        $value = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site($path, ''));
        if ($value !== '') {
            $address[$field] = $value;
        }
    }

    $twitter_site    = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('twitter.site', ''));
    $twitter_creator = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('twitter.creator', ''));
    $twitter_handle  = hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('twitter.handle', ''));

    $org_payload = [
        'name'       => $name,
        'legal_name' => hr_sa_hrdf_normalize_text(hr_sa_hrdf_site('org.legal_name', '')),
        'address'    => $address,
        'same_as'    => $same_as,
        'contact'    => $contact_points,
    ];

    $cache = [
        'name'      => $name,
        'url'       => $url,
        'logo_url'  => $logo_url,
        'locale'    => $locale,
        'og_name'   => $og_name,
        'twitter'   => array_filter(
            [
                'site'    => $twitter_site,
                'creator' => $twitter_creator,
                'handle'  => $twitter_handle,
            ],
            static fn(string $value): bool => $value !== ''
        ),
        'org'       => $org_payload,
    ];

    return $cache;
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

    $aggregate = hr_sa_hrdf_trip('aggregateRating', $post_id, null);

    $payload = [
        'url'                 => hr_sa_hrdf_normalize_url(hr_sa_hrdf_trip('url', $post_id, '')),
        'title'               => hr_sa_hrdf_normalize_text(hr_sa_hrdf_trip('title', $post_id, '')),
        'description'         => hr_sa_hrdf_normalize_text(hr_sa_hrdf_trip('description', $post_id, '')),
        'images'              => hr_sa_hrdf_normalize_url_list(hr_sa_hrdf_trip('images', $post_id, [])),
        'additional_property' => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('additional_property', $post_id, [])),
        'itinerary'           => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('itinerary.steps', $post_id, [])),
        'faq'                 => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('faq', $post_id, [])),
        'vehicles'            => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('vehicles', $post_id, [])),
        'reviews'             => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('reviews', $post_id, [])),
        'aggregate_rating'    => is_array($aggregate) ? $aggregate : null,
        'offers'              => hr_sa_hrdf_to_array(hr_sa_hrdf_trip('offers', $post_id, [])),
    ];

    return $cache[$post_id] = $payload;
}
