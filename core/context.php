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
    $post_id   = is_singular() ? (int) get_queried_object_id() : 0;
    $type      = hr_sa_detect_content_type();
    $site_name = hr_sa_context_clean_string((string) hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name')));
    $site_name = $site_name !== '' ? $site_name : hr_sa_context_clean_string((string) get_bloginfo('name'));
    $locale    = (string) hr_sa_get_setting('hr_sa_locale', get_locale());
    $twitter   = (string) hr_sa_get_setting('hr_sa_twitter_handle', '');
    $image     = hr_sa_resolve_context_image_url($post_id > 0 ? $post_id : null);

    $context = [
        'url'            => hr_sa_guess_canonical_url(),
        'type'           => $type,
        'title'          => hr_sa_resolve_context_title($type, $post_id, $site_name),
        'description'    => hr_sa_resolve_context_description($type, $post_id, $site_name),
        'country'        => hr_sa_resolve_context_country($type, $post_id),
        'site_name'      => $site_name,
        'locale'         => $locale !== '' ? $locale : get_locale(),
        'twitter_handle' => $twitter,
        'hero_url'       => $image,
        'image'          => $image,
    ];

    return apply_filters('hr_sa_get_context', $context);
}

/**
 * Guess a canonical URL for the current view.
 */
function hr_sa_guess_canonical_url(): string
{
    if (is_front_page() || is_home()) {
        return trailingslashit(set_url_scheme(home_url('/'), 'https'));
    }

    if (is_singular()) {
        $permalink = get_permalink();
        if ($permalink) {
            return trailingslashit(set_url_scheme($permalink, 'https'));
        }
    }

    $current_url = home_url(add_query_arg([]));
    if (is_string($current_url) && $current_url !== '') {
        return set_url_scheme($current_url, 'https');
    }

    return trailingslashit(set_url_scheme(home_url('/'), 'https'));
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
 * Resolve a normalized string from potentially formatted content.
 */
function hr_sa_context_clean_string(string $text): string
{
    $text = wp_strip_all_tags($text, true);
    $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    $text = (string) preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

/**
 * Condense text and trim to a word boundary.
 */
function hr_sa_trim_text(string $text, int $limit = 0): string
{
    $clean = hr_sa_context_clean_string($text);
    if ($clean === '') {
        return '';
    }

    if ($limit > 0) {
        $clean = wp_trim_words($clean, $limit, '…');
    }

    return $clean;
}

/**
 * Apply template replacements and normalize whitespace.
 *
 * @param array<string, string> $replacements
 */
function hr_sa_apply_template_replacements(string $template, array $replacements): string
{
    if ($template === '') {
        return '';
    }

    $result = strtr($template, $replacements);
    $result = (string) preg_replace('/\{\{[^}]+\}\}/', '', $result);

    return hr_sa_context_clean_string($result);
}

/**
 * Append a brand suffix to a title if it is not already present.
 */
function hr_sa_append_brand_suffix(string $title, string $site_name): string
{
    $title = hr_sa_context_clean_string($title);
    $brand = hr_sa_context_clean_string($site_name);

    if ($brand === '') {
        return $title;
    }

    if ($title === '') {
        return $brand;
    }

    if (stripos($title, $brand) !== false) {
        return $title;
    }

    return $title . ' | ' . $brand;
}

/**
 * Resolve comma-separated country names for a trip.
 */
function hr_sa_resolve_trip_countries(int $post_id): string
{
    $terms = wp_get_post_terms($post_id, 'country', ['fields' => 'names']);
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    $names = array_map('hr_sa_context_clean_string', array_map('strval', $terms));
    $names = array_values(array_filter($names, static fn(string $name): bool => $name !== ''));

    return $names ? implode(', ', $names) : '';
}

/**
 * Build the context title using templates and fallbacks.
 */
function hr_sa_resolve_context_title(string $type, int $post_id, string $site_name): string
{
    if ($type === 'home') {
        return $site_name;
    }

    if ($type === 'trip' && $post_id > 0) {
        $template = (string) hr_sa_get_setting('hr_sa_tpl_trip', '{{trip_name}} | Motorcycle Tour in {{country}}');
        $replacements = [
            '{{trip_name}}' => hr_sa_context_clean_string(get_the_title($post_id) ?: ''),
            '{{country}}'   => hr_sa_resolve_trip_countries($post_id),
            '{{site_name}}' => $site_name,
        ];
        $title = hr_sa_apply_template_replacements($template, $replacements);
        if ($title === '') {
            $title = $replacements['{{trip_name}}'];
        }

        return $title !== '' ? $title : $site_name;
    }

    if ($post_id > 0) {
        $template = (string) hr_sa_get_setting('hr_sa_tpl_page', '{{page_title}}');
        $replacements = [
            '{{page_title}}' => hr_sa_context_clean_string(get_the_title($post_id) ?: ''),
            '{{site_name}}'  => $site_name,
        ];
        $title = hr_sa_apply_template_replacements($template, $replacements);
        if ($title === '') {
            $title = $replacements['{{page_title}}'];
        }

        if (hr_sa_get_setting('hr_sa_tpl_page_brand_suffix')) {
            $title = hr_sa_append_brand_suffix($title, $site_name);
        }

        return $title !== '' ? $title : $site_name;
    }

    return $site_name;
}

/**
 * Resolve the context description based on content type.
 */
function hr_sa_resolve_context_description(string $type, int $post_id, string $site_name): string
{
    if ($type === 'home') {
        $tagline = (string) get_bloginfo('description', 'display');
        $description = hr_sa_trim_text($tagline, 40);

        return $description !== '' ? $description : $site_name;
    }

    $candidates = [];
    if ($post_id > 0) {
        if ($type === 'trip') {
            if (function_exists('get_field')) {
                $acf_description = get_field('description', $post_id);
                if (is_string($acf_description) && $acf_description !== '') {
                    $candidates[] = $acf_description;
                }
            }

            $meta_description = get_post_meta($post_id, 'description', true);
            if (is_string($meta_description) && $meta_description !== '') {
                $candidates[] = $meta_description;
            }
        }

        $excerpt = (string) get_post_field('post_excerpt', $post_id);
        if ($excerpt !== '') {
            $candidates[] = $excerpt;
        }

        $content = (string) get_post_field('post_content', $post_id);
        if ($content !== '') {
            $candidates[] = $content;
        }
    }

    foreach ($candidates as $candidate) {
        $clean = hr_sa_trim_text(strip_shortcodes((string) $candidate), 45);
        if ($clean !== '') {
            return $clean;
        }
    }

    $fallback = (string) get_bloginfo('description', 'display');
    $clean = hr_sa_trim_text($fallback, 40);

    return $clean !== '' ? $clean : $site_name;
}

/**
 * Resolve the country string for the context payload.
 */
function hr_sa_resolve_context_country(string $type, int $post_id): string
{
    if ($type !== 'trip' || $post_id <= 0) {
        return '';
    }

    return hr_sa_resolve_trip_countries($post_id);
}

/**
 * Resolve the preferred image URL for the context.
 */
function hr_sa_resolve_context_image_url(?int $post_id): ?string
{
    $candidates = [];

    if ($post_id) {
        $meta = get_post_meta($post_id, '_hrih_header_image_url', true);
        if (is_array($meta) && isset($meta['url'])) {
            $meta = (string) $meta['url'];
        }
        if (is_string($meta) && $meta !== '') {
            $candidates[] = $meta;
        }
    }

    $connector = hr_sa_get_media_help_hero_url();
    if ($connector) {
        $candidates[] = $connector;
    }

    $fallback = (string) hr_sa_get_setting('hr_sa_fallback_image', '');
    if ($fallback !== '') {
        $candidates[] = $fallback;
    }

    $resolved = null;
    foreach ($candidates as $candidate) {
        $sanitized = hr_sa_sanitize_context_image_url((string) $candidate);
        if ($sanitized === null) {
            continue;
        }

        $transformed = hr_sa_apply_image_preset_to_url($sanitized);
        if ($transformed !== '') {
            $resolved = $transformed;
            break;
        }
    }

    /**
     * Allow the resolved image URL to be filtered.
     *
     * @param string|null $resolved
     * @param int|null    $post_id
     */
    $resolved = apply_filters('hr_sa_context_image_url', $resolved, $post_id);

    return $resolved !== '' ? $resolved : null;
}

/**
 * Sanitize hero/fallback image URLs.
 */
function hr_sa_sanitize_context_image_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    $url = esc_url_raw($url);
    if ($url === '' || !wp_http_validate_url($url)) {
        return null;
    }

    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
    if ($scheme && !in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return set_url_scheme($url, 'https');
}

/**
 * Apply the configured CDN preset to an image URL.
 */
function hr_sa_apply_image_preset_to_url(string $url): string
{
    $preset = trim((string) hr_sa_get_image_preset());
    if ($preset === '') {
        return $url;
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    $query_args = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query_args);
    }

    foreach (explode(',', $preset) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || strpos($segment, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $segment, 2));
        if ($key === '') {
            continue;
        }

        $query_args[$key] = $value;
    }

    $parts['query'] = $query_args ? http_build_query($query_args, '', '&', PHP_QUERY_RFC3986) : '';

    $rebuilt = hr_sa_build_url_from_parts($parts);

    return $rebuilt !== '' ? $rebuilt : $url;
}

/**
 * Reconstruct a URL from its parsed components.
 *
 * @param array<string, mixed> $parts
 */
function hr_sa_build_url_from_parts(array $parts): string
{
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host   = $parts['host'] ?? '';
    if ($host === '') {
        return '';
    }

    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? null;
    $auth = '';
    if ($user !== '') {
        $auth = $user;
        if ($pass !== null) {
            $auth .= ':' . $pass;
        }
        $auth .= '@';
    }

    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path     = $parts['path'] ?? '';
    $query    = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $scheme . $auth . $host . $port . $path . $query . $fragment;
}
