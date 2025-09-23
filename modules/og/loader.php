<?php
/**
 * Placeholder OG/Twitter loader for future phases.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register OG/Twitter emission hooks.
 */
function hr_sa_bootstrap_og_module(): void
{
    add_action('wp', 'hr_sa_og_maybe_schedule');
}
add_action('init', 'hr_sa_bootstrap_og_module');

/**
 * Conditionally register the wp_head output callback.
 */
function hr_sa_og_maybe_schedule(): void
{
    if (is_admin()) {
        return;
    }

    $og_enabled      = hr_sa_is_og_enabled();
    $twitter_enabled = hr_sa_is_twitter_enabled();

    if (!$og_enabled && !$twitter_enabled) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_render_social_meta', 47);
}

/**
 * Output Open Graph and Twitter Card meta tags.
 */
function hr_sa_render_social_meta(): void
{
    static $printed = false;

    if ($printed) {
        return;
    }

    $printed = true;

    $context      = hr_sa_get_context();
    $post_id      = is_singular() ? get_queried_object_id() : 0;
    $image_url    = hr_sa_resolve_social_image_url($post_id);
    $og_tags      = hr_sa_is_og_enabled() ? hr_sa_build_og_tags($context, $image_url) : [];
    $twitter_tags = hr_sa_is_twitter_enabled() ? hr_sa_build_twitter_tags($context, $image_url) : [];

    if (!$og_tags && !$twitter_tags) {
        return;
    }

    echo PHP_EOL . '<!-- HR SEO Assistant: Social Meta -->' . PHP_EOL;

    foreach ($og_tags as $property => $value) {
        hr_sa_output_social_meta_tag('property', $property, $value);
    }

    foreach ($twitter_tags as $name => $value) {
        hr_sa_output_social_meta_tag('name', $name, $value);
    }

    echo '<!-- /HR SEO Assistant -->' . PHP_EOL;
}

/**
 * Build Open Graph tags from context.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, string>
 */
function hr_sa_build_og_tags(array $context, ?string $image_url = null): array
{
    $locale = isset($context['locale']) && $context['locale'] !== '' ? (string) $context['locale'] : 'en_US';

    $tags = [
        'og:title'       => (string) ($context['title'] ?? ''),
        'og:description' => (string) ($context['description'] ?? ''),
        'og:type'        => hr_sa_resolve_og_type($context),
        'og:url'         => (string) ($context['url'] ?? ''),
        'og:site_name'   => (string) ($context['site_name'] ?? ''),
        'og:locale'      => $locale,
    ];

    if ($image_url) {
        $tags['og:image'] = $image_url;
    }

    $tags = array_filter($tags, static function ($value) {
        return $value !== null && $value !== '';
    });

    /**
     * Filter the Open Graph tag map before it is rendered.
     *
     * @param array<string, string> $tags    Assembled OG tags.
     * @param array<string, mixed>  $context Current SEO context.
     */
    return (array) apply_filters('hr_sa_og_tags', $tags, $context);
}

/**
 * Build Twitter Card tags from context.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, string>
 */
function hr_sa_build_twitter_tags(array $context, ?string $image_url = null): array
{
    $tags = [
        'twitter:card'        => 'summary_large_image',
        'twitter:title'       => (string) ($context['title'] ?? ''),
        'twitter:description' => (string) ($context['description'] ?? ''),
    ];

    if ($image_url) {
        $tags['twitter:image'] = $image_url;
    }

    $handle = isset($context['twitter_handle']) ? (string) $context['twitter_handle'] : '';
    if ($handle !== '') {
        $tags['twitter:site'] = $handle;
    }

    $tags = array_filter($tags, static function ($value) {
        return $value !== null && $value !== '';
    });

    /**
     * Filter the Twitter Card tag map before it is rendered.
     *
     * @param array<string, string> $tags    Assembled Twitter tags.
     * @param array<string, mixed>  $context Current SEO context.
     */
    return (array) apply_filters('hr_sa_twitter_tags', $tags, $context);
}

/**
 * Determine the OG type string for the current context.
 *
 * @param array<string, mixed> $context
 */
function hr_sa_resolve_og_type(array $context): string
{
    $type = isset($context['type']) ? (string) $context['type'] : 'page';

    switch ($type) {
        case 'home':
            $og_type = 'website';
            break;
        case 'trip':
            $og_type = 'trip';
            break;
        case 'product':
            $og_type = 'product';
            break;
        default:
            $og_type = 'article';
            break;
    }

    /**
     * Filter the resolved og:type value.
     *
     * @param string               $og_type og:type string.
     * @param array<string, mixed> $context Current SEO context.
     */
    return (string) apply_filters('hr_sa_og_type', $og_type, $context);
}

/**
 * Output a single meta tag for social data.
 */
function hr_sa_output_social_meta_tag(string $attribute, string $key, string $value): void
{
    $value = trim($value);
    if ($key === '' || $value === '') {
        return;
    }

    $escaped_value = hr_sa_social_meta_escape_value($key, $value);
    if ($escaped_value === '') {
        return;
    }

    printf(
        '<meta %1$s="%2$s" content="%3$s" />' . PHP_EOL,
        esc_attr($attribute),
        esc_attr($key),
        $escaped_value
    );
}

/**
 * Escape meta values for attribute output.
 */
function hr_sa_social_meta_escape_value(string $key, string $value): string
{
    $url_keys = [
        'og:url',
        'og:image',
        'og:image:secure_url',
        'twitter:url',
        'twitter:image',
    ];

    if (in_array($key, $url_keys, true)) {
        $value = esc_url($value);
    }

    return esc_attr($value);
}
