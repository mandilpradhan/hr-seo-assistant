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
    if ($normalized !== '') {
        return $normalized;
    }

    if ($content_type === 'trip') {
        return 'product';
    }

    if ($content_type === 'home') {
        return 'website';
    }

    return 'article';
}

/**
 * Retrieve global site details from HRDF with fallbacks.
 *
 * @return array{name: string, url: string, logo?: string|null}
 */
function hr_sa_resolve_site_profile(): array
{
    $site_name = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get('hrdf.site.name', 0, ''));
    if ($site_name === '') {
        $site_name = hr_sa_sanitize_text_value((string) get_bloginfo('name'));
    }

    $site_url = hr_sa_normalize_url((string) hr_sa_hrdf_get('hrdf.site.url', 0, ''));
    if ($site_url === null) {
        $site_url = trailingslashit((string) home_url('/'));
    }

    $logo = hr_sa_normalize_url((string) hr_sa_hrdf_get('hrdf.site.logo_url', 0, ''));
    if ($logo === null) {
        $logo = hr_sa_normalize_url((string) get_site_icon_url());
    }

    return [
        'name' => $site_name,
        'url'  => $site_url,
        'logo' => $logo,
    ];
}

/**
 * Resolve per-post meta information from HRDF with safe fallbacks.
 *
 * @return array{title: string, description: string, canonical_url: string, og_type?: string}
 */
function hr_sa_resolve_meta_profile(int $post_id): array
{
    $title = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get('hrdf.meta.title', $post_id, ''));
    if ($title === '' && $post_id > 0) {
        $title = hr_sa_sanitize_text_value((string) get_the_title($post_id));
    }

    $description = hr_sa_sanitize_description((string) hr_sa_hrdf_get('hrdf.meta.description', $post_id, ''));
    if ($description === '' && $post_id > 0) {
        $fallback_excerpt = get_the_excerpt($post_id);
        if (is_string($fallback_excerpt)) {
            $description = hr_sa_sanitize_description($fallback_excerpt);
        }
    }

    $canonical = hr_sa_normalize_url((string) hr_sa_hrdf_get('hrdf.meta.canonical_url', $post_id, ''));
    if ($canonical === null && $post_id > 0) {
        $fallback_url = get_permalink($post_id);
        $canonical    = $fallback_url ? hr_sa_normalize_url((string) $fallback_url) : null;
    }

    if ($canonical === null) {
        $canonical = trailingslashit((string) home_url('/'));
    }

    $meta = [
        'title'          => $title,
        'description'    => $description,
        'canonical_url'  => $canonical,
        'og_type'        => hr_sa_sanitize_text_value((string) hr_sa_hrdf_get('hrdf.meta.og_type', $post_id, '')),
    ];

    return $meta;
}

/**
 * Resolve hero and gallery images from HRDF with safe fallbacks.
 *
 * @return array{primary: string|null, all: array<int, string>}
 */
function hr_sa_resolve_image_profile(int $post_id): array
{
    $primary = hr_sa_normalize_url((string) hr_sa_hrdf_get('hrdf.hero.image_url', $post_id, ''));

    $gallery = hr_sa_collect_image_urls((array) hr_sa_hrdf_get('hrdf.gallery.images', $post_id, []));
    $trip_gallery = hr_sa_collect_image_urls((array) hr_sa_hrdf_get('hrdf.trip.gallery.images', $post_id, []));

    $images = [];
    if ($primary !== null) {
        $images[] = $primary;
    }

    foreach (array_merge($gallery, $trip_gallery) as $url) {
        if (!in_array($url, $images, true)) {
            $images[] = $url;
        }
    }

    if ($primary === null && !empty($images)) {
        $primary = $images[0];
    }

    if ($primary === null && $post_id > 0) {
        $fallback = get_the_post_thumbnail_url($post_id, 'full');
        $normalized = $fallback ? hr_sa_normalize_url((string) $fallback) : null;
        if ($normalized !== null) {
            $primary = $normalized;
            if (!in_array($normalized, $images, true)) {
                $images[] = $normalized;
            }
        }
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
