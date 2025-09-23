<?php
/**
 * Shared SEO context provider consumed by emitters.
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
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $site_name = hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name'));
    $locale    = hr_sa_get_setting('hr_sa_locale', get_locale());
    $type      = hr_sa_detect_content_type();
    $post_id   = is_singular() ? get_queried_object_id() : 0;
    $post      = $post_id ? get_post($post_id) : null;

    $context = [
        'url'            => hr_sa_guess_canonical_url(),
        'type'           => $type,
        'title'          => hr_sa_context_build_title($type, $post, $site_name),
        'description'    => hr_sa_context_build_description($type, $post),
        'country'        => hr_sa_context_resolve_country($type, $post),
        'site_name'      => $site_name,
        'locale'         => $locale,
        'twitter_handle' => hr_sa_context_normalize_whitespace((string) hr_sa_get_setting('hr_sa_twitter_handle', '')),
        'hero_url'       => hr_sa_get_media_help_hero_url(),
        'fallback_image' => hr_sa_context_prepare_fallback_image(),
    ];

    $context = apply_filters('hr_sa_get_context', $context);

    $cached = $context;

    return $context;
}

/**
 * Guess a canonical URL for the current view.
 */
function hr_sa_guess_canonical_url(): string
{
    if (is_front_page() || is_home()) {
        return trailingslashit(home_url('/'));
    }

    if (is_singular()) {
        $permalink = get_permalink();
        if ($permalink) {
            return trailingslashit($permalink);
        }
    }

    $current_url = home_url(add_query_arg([]));
    return is_string($current_url) ? $current_url : trailingslashit(home_url('/'));
}

/**
 * Detect the content type label for the context payload.
 */
function hr_sa_detect_content_type(): string
{
    if (is_front_page() || is_home()) {
        return 'home';
    }

    if (is_singular('trip')) {
        return 'trip';
    }

    if (is_singular()) {
        return 'page';
    }

    return 'page';
}

/**
 * Resolve the fallback image stored in settings.
 */
function hr_sa_context_prepare_fallback_image(): string
{
    $fallback = (string) hr_sa_get_setting('hr_sa_fallback_image', '');
    if ($fallback === '') {
        return '';
    }

    $fallback = esc_url_raw($fallback);

    return $fallback ?: '';
}

/**
 * Build a context-aware title string.
 */
function hr_sa_context_build_title(string $type, ?\WP_Post $post, string $site_name): string
{
    $base_title = '';
    if ($post) {
        $base_title = get_the_title($post) ?: '';
    } else {
        $base_title = wp_get_document_title();
    }

    $base_title = hr_sa_context_normalize_whitespace($base_title);

    if ($type === 'home') {
        $tagline = hr_sa_context_normalize_whitespace((string) get_bloginfo('description', 'display'));
        if ($base_title === '') {
            $base_title = $site_name;
        }

        if ($tagline !== '' && stripos($base_title, $tagline) === false) {
            return hr_sa_context_normalize_whitespace($base_title . ' | ' . $tagline);
        }

        return $base_title !== '' ? $base_title : $site_name;
    }

    if ($type === 'trip') {
        $template   = (string) hr_sa_get_setting('hr_sa_tpl_trip');
        $countries  = hr_sa_context_trip_countries($post);
        $rendered   = hr_sa_context_apply_template($template, [
            'trip_name' => $base_title !== '' ? $base_title : $site_name,
            'country'   => $countries,
            'site_name' => $site_name,
        ]);
        $rendered   = $rendered !== '' ? $rendered : ($base_title !== '' ? $base_title : $site_name);

        return hr_sa_context_normalize_whitespace($rendered);
    }

    $template = (string) hr_sa_get_setting('hr_sa_tpl_page');
    $rendered = hr_sa_context_apply_template($template, [
        'page_title' => $base_title !== '' ? $base_title : $site_name,
        'site_name'  => $site_name,
    ]);

    if (hr_sa_get_setting('hr_sa_tpl_page_brand_suffix')) {
        if ($rendered !== '' && $site_name !== '' && stripos($rendered, $site_name) === false) {
            $rendered .= ' | ' . $site_name;
        }
    }

    if ($rendered === '') {
        $rendered = $base_title !== '' ? $base_title : $site_name;
    }

    return hr_sa_context_normalize_whitespace($rendered);
}

/**
 * Build a context-aware description string.
 */
function hr_sa_context_build_description(string $type, ?\WP_Post $post): string
{
    $raw = '';

    if ($type === 'trip' && $post) {
        if (function_exists('get_field')) {
            $raw = (string) get_field('description', $post->ID);
        }

        if ($raw === '') {
            $raw = (string) get_post_field('post_excerpt', $post->ID);
        }

        if ($raw === '') {
            $raw = (string) get_post_field('post_content', $post->ID);
        }
    } elseif ($post) {
        $raw = (string) get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);

        if ($raw === '') {
            $raw = (string) get_post_field('post_excerpt', $post->ID);
        }

        if ($raw === '') {
            $raw = (string) get_post_field('post_content', $post->ID);
        }
    }

    if ($raw === '') {
        $raw = (string) get_bloginfo('description', 'display');
    }

    return hr_sa_context_normalize_description($raw);
}

/**
 * Resolve country context for trip content types.
 */
function hr_sa_context_resolve_country(string $type, ?\WP_Post $post): string
{
    if ($type !== 'trip' || !$post) {
        return '';
    }

    $terms = wp_get_post_terms($post->ID, 'country', ['fields' => 'names']);
    if (is_wp_error($terms) || !$terms) {
        return '';
    }

    $names = array_filter(array_map('strval', $terms));
    if (!$names) {
        return '';
    }

    $countries = implode(', ', $names);

    return hr_sa_context_normalize_whitespace($countries);
}

/**
 * Collect trip country list as a rendered string for templates.
 */
function hr_sa_context_trip_countries(?\WP_Post $post): string
{
    if (!$post) {
        return '';
    }

    $terms = wp_get_post_terms($post->ID, 'country', ['fields' => 'names']);
    if (is_wp_error($terms) || !$terms) {
        return '';
    }

    $names = array_filter(array_map('strval', $terms));

    return hr_sa_context_normalize_whitespace(implode(', ', $names));
}

/**
 * Replace template placeholders with provided values.
 *
 * @param array<string, string> $values
 */
function hr_sa_context_apply_template(string $template, array $values): string
{
    if ($template === '') {
        return '';
    }

    $replacements = [];
    foreach ($values as $key => $value) {
        $replacements['{{' . $key . '}}'] = $value;
    }

    $rendered = strtr($template, $replacements);

    return hr_sa_context_normalize_whitespace($rendered);
}

/**
 * Normalize whitespace in arbitrary strings.
 */
function hr_sa_context_normalize_whitespace(string $value): string
{
    $value = wp_strip_all_tags($value, true);
    $value = (string) preg_replace('/\s+/u', ' ', $value);

    return trim($value);
}

/**
 * Normalize description text and truncate when necessary.
 */
function hr_sa_context_normalize_description(string $value, int $max_chars = 280): string
{
    $value = hr_sa_context_normalize_whitespace($value);

    if ($value === '') {
        return '';
    }

    if ($max_chars <= 0) {
        return $value;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length <= $max_chars) {
        return $value;
    }

    $slice_length = max(1, $max_chars - 1);
    $slice = function_exists('mb_substr') ? mb_substr($value, 0, $slice_length) : substr($value, 0, $slice_length);
    $slice = rtrim($slice, " \t\n\r\0\x0B,;:-");

    return $slice . '…';
}

/**
 * Apply the configured CDN preset to an image URL.
 *
 * @param string $url Base image URL.
 *
 * @return string
 */
function hr_sa_apply_image_preset(string $url): string
{
    $preset = trim((string) hr_sa_get_image_preset());
    if ($preset === '') {
        return $url;
    }

    $parts = array_filter(array_map('trim', explode(',', $preset)));
    if (!$parts) {
        return $url;
    }

    $args = [];
    foreach ($parts as $part) {
        if (strpos($part, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $part, 2));
        if ($key === '') {
            continue;
        }

        $args[$key] = $value;
    }

    if ($args === []) {
        return $url;
    }

    return add_query_arg($args, $url);
}

/**
 * Normalize a candidate social image URL.
 *
 * @param mixed $value Raw candidate value.
 */
function hr_sa_normalize_social_image_candidate($value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $url = esc_url_raw($value);
    if ($url === '' || !wp_http_validate_url($url)) {
        return null;
    }

    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower((string) $parts['scheme']) !== 'https') {
        return null;
    }

    $url = hr_sa_apply_image_preset($url);
    $url = esc_url_raw($url);
    if ($url === '' || !wp_http_validate_url($url)) {
        return null;
    }

    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower((string) $parts['scheme']) !== 'https') {
        return null;
    }

    return $url;
}

/**
 * Resolve the social image URL and data for a given post.
 *
 * @return array{url: ?string, source: ?string}
 */
function hr_sa_get_social_image_resolution(int $post_id): array
{
    $url    = null;
    $source = null;

    if ($post_id > 0) {
        $meta_value = get_post_meta($post_id, '_hrih_header_image_url', true);
        $normalized = hr_sa_normalize_social_image_candidate($meta_value);
        if ($normalized !== null) {
            $url    = $normalized;
            $source = 'meta';
        }
    }

    if ($url === null) {
        $fallback = hr_sa_context_prepare_fallback_image();
        $fallback = (string) apply_filters('hr_mh_site_fallback_image', $fallback);
        $normalized = hr_sa_normalize_social_image_candidate($fallback);
        if ($normalized !== null) {
            $url    = $normalized;
            $source = 'fallback';
        }
    }

    if ($url !== null) {
        /**
         * Filter the resolved social image URL prior to output.
         *
         * @param string $url    Social image URL after normalization.
         * @param array{post_id: int, source: ?string} $data Additional context about the resolution.
         */
        $filtered = apply_filters('hr_sa_social_image_url', $url, [
            'post_id' => $post_id,
            'source'  => $source,
        ]);

        if (is_string($filtered) && $filtered !== '') {
            $filtered = esc_url_raw($filtered);
            if ($filtered !== '' && wp_http_validate_url($filtered)) {
                $parts = wp_parse_url($filtered);
                if ($parts && !empty($parts['scheme']) && !empty($parts['host']) && strtolower((string) $parts['scheme']) === 'https') {
                    $url = $filtered;
                } else {
                    $url    = null;
                    $source = null;
                }
            } else {
                $url    = null;
                $source = null;
            }
        } elseif ($filtered === '') {
            $url    = null;
            $source = null;
        }
    }

    if ($url === null) {
        return [
            'url'    => null,
            'source' => null,
        ];
    }

    return [
        'url'    => $url,
        'source' => $source,
    ];
}

/**
 * Resolve the final social image URL for a given post.
 *
 * @return string|null
 */
function hr_sa_resolve_social_image_url(int $post_id): ?string
{
    $resolution = hr_sa_get_social_image_resolution($post_id);

    return $resolution['url'];
}
