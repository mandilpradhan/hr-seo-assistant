<?php
/**
 * Shared SEO context provider sourced from HRDF.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Build the canonical SEO context for the current request.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_context(): array
{
    $post_id = is_singular() ? (int) get_queried_object_id() : 0;

    $site   = hr_sa_resolve_site_profile();
    $meta   = hr_sa_resolve_meta_profile($post_id);
    $images = hr_sa_resolve_image_profile($post_id);

    $type    = hr_sa_resolve_content_type($post_id);
    $og_type = hr_sa_resolve_og_type($type, $meta['og_type'] ?? '');

    $context = [
        'title'          => $meta['title'] ?? '',
        'description'    => $meta['description'] ?? '',
        'url'            => $meta['canonical_url'] ?? '',
        'canonical'      => $meta['canonical_url'] ?? '',
        'site_name'      => $site['name'] ?? '',
        'site_url'       => $site['url'] ?? '',
        'hero_url'       => $images['primary'] ?? '',
        'image'          => $images['primary'] ?? '',
        'images'         => $images['all'],
        'type'           => $type,
        'og_type'        => $og_type,
        'hrdf_available' => hr_sa_hrdf_is_available(),
        'is_trip'        => ($type === 'trip'),
    ];

    $twitter_card = hr_sa_hrdf_get_first(['hrdf.twitter.card'], $post_id, '');
    if (is_string($twitter_card)) {
        $card = hr_sa_sanitize_text_value($twitter_card);
        if ($card !== '') {
            $context['twitter_card'] = $card;
        }
    }

    $twitter_site = hr_sa_hrdf_get_first(['hrdf.twitter.site'], $post_id, '');
    if (is_string($twitter_site)) {
        $site_handle = hr_sa_sanitize_text_value($twitter_site);
        if ($site_handle !== '') {
            $context['twitter_site'] = $site_handle;
        }
    }

    $twitter_creator = hr_sa_hrdf_get_first(['hrdf.twitter.creator'], $post_id, '');
    if (is_string($twitter_creator)) {
        $creator_handle = hr_sa_sanitize_text_value($twitter_creator);
        if ($creator_handle !== '') {
            $context['twitter_creator'] = $creator_handle;
        }
    }

    /**
     * Filter the HRDF-derived SEO context array.
     *
     * @param array<string, mixed> $context
     * @param int                  $post_id
     */
    return apply_filters('hr_sa_get_context', $context, $post_id);
}

/**
 * Resolve the current view's content type label.
 */
function hr_sa_resolve_content_type(int $post_id): string
{
    if (is_front_page() || is_home()) {
        return 'home';
    }

    if ($post_id > 0 && is_singular('trip')) {
        return 'trip';
    }

    if ($post_id > 0) {
        return 'page';
    }

    return 'page';
}

/**
 * Resolve the Open Graph type.
 */
function hr_sa_resolve_og_type(string $content_type, string $hrdf_type): string
{
    $normalized = hr_sa_sanitize_text_value($hrdf_type);
    return $normalized;
}

/**
 * Retrieve global site details from HRDF with fallbacks.
 *
 * @return array{name: string, url: string, logo?: string|null}
 */
function hr_sa_resolve_site_profile(): array
{
    $name_raw = hr_sa_hrdf_get_first([
        'hrdf.org.name',
        'hrdf.site.name',
    ], 0, '');
    $site_name = is_string($name_raw) ? hr_sa_sanitize_text_value($name_raw) : '';

    $url_raw = hr_sa_hrdf_get_first([
        'hrdf.org.url',
        'hrdf.site.url',
    ], 0, '');
    $site_url = is_string($url_raw) ? hr_sa_normalize_url($url_raw) : null;

    $logo_raw = hr_sa_hrdf_get_first([
        'hrdf.org.logo.url',
        'hrdf.site.logo_url',
    ], 0, '');
    $logo = is_string($logo_raw) ? hr_sa_normalize_url($logo_raw) : null;

    return array_filter([
        'name' => $site_name,
        'url'  => $site_url,
        'logo' => $logo,
    ], static fn($value) => $value !== null && $value !== '');
}

/**
 * Resolve per-post meta information from HRDF with safe fallbacks.
 *
 * @return array{title: string, description: string, canonical_url: string, og_type?: string}
 */
function hr_sa_resolve_meta_profile(int $post_id): array
{
    $title_raw = hr_sa_hrdf_get_first([
        'hrdf.webpage.title',
        'hrdf.meta.title',
        'hrdf.trip.title',
    ], $post_id, '');
    $title = is_string($title_raw) ? hr_sa_sanitize_text_value($title_raw) : '';

    $description_raw = hr_sa_hrdf_get_first([
        'hrdf.webpage.description',
        'hrdf.meta.description',
        'hrdf.trip.description',
    ], $post_id, '');
    $description = is_string($description_raw) ? hr_sa_sanitize_description($description_raw) : '';

    $canonical_raw = hr_sa_hrdf_get_first([
        'hrdf.webpage.url',
        'hrdf.meta.canonical_url',
        'hrdf.trip.url',
    ], $post_id, '');
    $canonical = is_string($canonical_raw) ? hr_sa_normalize_url($canonical_raw) : null;

    $og_type_raw = hr_sa_hrdf_get_first([
        'hrdf.meta.og_type',
        'hrdf.webpage.og_type',
    ], $post_id, '');
    $og_type = is_string($og_type_raw) ? hr_sa_sanitize_text_value($og_type_raw) : '';

    return [
        'title'         => $title,
        'description'   => $description,
        'canonical_url' => $canonical ?? '',
        'og_type'       => $og_type,
    ];
}

/**
 * Resolve hero and gallery images from HRDF with safe fallbacks.
 *
 * @return array{primary: string|null, all: array<int, string>}
 */
function hr_sa_resolve_image_profile(int $post_id): array
{
    $primary = null;
    $primary_candidates = [
        hr_sa_hrdf_get('hrdf.webpage.image', $post_id, ''),
        hr_sa_hrdf_get('hrdf.trip.primary_image', $post_id, ''),
        hr_sa_hrdf_get('hrdf.hero.image_url', $post_id, ''),
    ];

    foreach ($primary_candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        $normalized = hr_sa_normalize_url($candidate);
        if ($normalized !== null) {
            $primary = $normalized;
            break;
        }
    }

    $collections = [
        (array) hr_sa_hrdf_get('hrdf.webpage.images', $post_id, []),
        (array) hr_sa_hrdf_get('hrdf.trip.images', $post_id, []),
        (array) hr_sa_hrdf_get('hrdf.gallery.images', $post_id, []),
        (array) hr_sa_hrdf_get('hrdf.trip.gallery.images', $post_id, []),
    ];

    $images = [];
    foreach ($collections as $collection) {
        $normalized_collection = hr_sa_collect_image_urls($collection);
        foreach ($normalized_collection as $url) {
            if (!in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
    }

    if ($primary === null && !empty($images)) {
        $primary = $images[0];
    }

    return [
        'primary' => $primary,
        'all'     => array_slice($images, 0, 8),
    ];
}

/**
 * Normalize arbitrary text extracted from HRDF.
 */
function hr_sa_sanitize_text_value(string $value): string
{
    $value = wp_strip_all_tags($value, true);
    $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    $value = (string) preg_replace('/\s+/u', ' ', $value);

    return trim($value);
}

/**
 * Sanitize description text.
 */
function hr_sa_sanitize_description(string $value): string
{
    $value = strip_shortcodes($value);
    $value = hr_sa_sanitize_text_value($value);

    return $value;
}

/**
 * Normalize a URL string to HTTPS when possible.
 */
function hr_sa_normalize_url(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $url = esc_url_raw($value);
    if ($url === '' || !wp_http_validate_url($url)) {
        return null;
    }

    $scheme = wp_parse_url($url, PHP_URL_SCHEME);
    if ($scheme === null) {
        return null;
    }

    if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
        return null;
    }

    return (string) set_url_scheme($url, 'https');
}

/**
 * Filter and normalize an array of image URLs.
 *
 * @param array<int, mixed> $candidates
 * @return array<int, string>
 */
function hr_sa_collect_image_urls(array $candidates): array
{
    $urls = [];
    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }
        $normalized = hr_sa_normalize_url($candidate);
        if ($normalized === null) {
            continue;
        }

        if (!in_array($normalized, $urls, true)) {
            $urls[] = $normalized;
        }
    }

    return $urls;
}
