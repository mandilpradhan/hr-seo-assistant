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
 * Build the canonical SEO context for the current request using HRDF values.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_context(): array
{
    $post_id = is_singular() ? (int) get_queried_object_id() : 0;
    $type    = hr_sa_detect_content_type();
    $site    = hr_sa_hrdf_site_payload();

    $context = [
        'type'           => $type,
        'url'            => $site['url'] ?? '',
        'title'          => $site['name'] ?? '',
        'description'    => '',
        'country'        => '',
        'site_name'      => $site['name'] ?? '',
        'og_site_name'   => $site['og_name'] ?? '',
        'locale'         => $site['locale'] ?? '',
        'twitter_handle' => $site['twitter']['handle'] ?? ($site['twitter']['site'] ?? ''),
        'twitter_site'   => $site['twitter']['site'] ?? '',
        'twitter_creator'=> $site['twitter']['creator'] ?? '',
        'hero_url'       => '',
        'image'          => '',
        'offers'         => [],
    ];

    if ($type === 'trip' && $post_id > 0) {
        $trip = hr_sa_hrdf_trip_payload($post_id);

        if (!empty($trip['url'])) {
            $context['url'] = $trip['url'];
        }
        if (!empty($trip['title'])) {
            $context['title'] = $trip['title'];
        }
        if (!empty($trip['description'])) {
            $context['description'] = $trip['description'];
        }

        $images = is_array($trip['images']) ? $trip['images'] : [];
        if ($images) {
            $primary = (string) reset($images);
            if ($primary !== '') {
                $context['image']    = $primary;
                $context['hero_url'] = $primary;
            }
        }

        $context['offers'] = is_array($trip['offers']) ? $trip['offers'] : [];
    }

    if ($context['hero_url'] === '') {
        $context['hero_url'] = $context['image'];
    }

    /**
     * Filter the assembled SEO context array.
     *
     * @param array<string, mixed> $context
     */
    return apply_filters('hr_sa_get_context', $context);
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
 * Resolve comma-separated country names for a trip.
 *
 * Used by AI helpers that still rely on taxonomy data.
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
 * Determine whether the image URL replacement feature is enabled.
 */
function hr_sa_is_image_url_replacement_enabled(): bool
{
    $enabled = (bool) hr_sa_get_setting('hr_sa_image_url_replace_enabled');

    /**
     * Filter whether image URL replacement should run.
     *
     * @param bool $enabled
     */
    return (bool) apply_filters('hr_sa_image_url_replace_enabled', $enabled);
}

/**
 * Retrieve the configured prefix/suffix replacement rules.
 *
 * @return array{prefix_find: string, prefix_replace: string, suffix_find: string, suffix_replace: string}
 */
function hr_sa_get_image_url_replacement_rules(): array
{
    $rules = [
        'prefix_find'    => (string) hr_sa_get_setting('hr_sa_image_url_prefix_find', ''),
        'prefix_replace' => (string) hr_sa_get_setting('hr_sa_image_url_prefix_replace', ''),
        'suffix_find'    => (string) hr_sa_get_setting('hr_sa_image_url_suffix_find', ''),
        'suffix_replace' => (string) hr_sa_get_setting('hr_sa_image_url_suffix_replace', ''),
    ];

    /**
     * Filter the image URL replacement rules.
     *
     * @param array<string, string> $rules
     */
    return apply_filters('hr_sa_image_url_replacement_rules', $rules);
}

/**
 * Apply configured prefix and suffix replacements to an image URL.
 */
function hr_sa_apply_image_url_replacements(string $url): string
{
    if ($url === '' || !hr_sa_is_image_url_replacement_enabled()) {
        return $url;
    }

    $rules    = hr_sa_get_image_url_replacement_rules();
    $original = $url;
    $updated  = $url;

    if ($rules['prefix_find'] !== '') {
        $prefix_length = strlen($rules['prefix_find']);
        if ($prefix_length > 0 && strpos($updated, $rules['prefix_find']) === 0) {
            $updated = $rules['prefix_replace'] . substr($updated, $prefix_length);
        }
    }

    if ($rules['suffix_find'] !== '') {
        $suffix_length = strlen($rules['suffix_find']);
        if ($suffix_length > 0 && strlen($updated) >= $suffix_length && substr($updated, -$suffix_length) === $rules['suffix_find']) {
            $updated = substr($updated, 0, strlen($updated) - $suffix_length) . $rules['suffix_replace'];
        }
    } elseif ($rules['suffix_replace'] !== '') {
        $updated .= $rules['suffix_replace'];
    }

    /**
     * Filter the transformed image URL prior to sanitization.
     *
     * @param string                $updated
     * @param string                $original
     * @param array<string, string> $rules
     */
    $updated = (string) apply_filters('hr_sa_image_url_replacement', $updated, $original, $rules);

    $sanitized = hr_sa_sanitize_context_image_url($updated);

    return $sanitized ?? $original;
}
