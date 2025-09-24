<?php
/**
 * Open Graph and Twitter Card emitter.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed>|null $hr_sa_last_social_snapshot */
$GLOBALS['hr_sa_last_social_snapshot'] = null;

/**
 * Initialize the OG/Twitter module hooks.
 */
function hr_sa_bootstrap_og_module(): void
{
    add_action('wp', 'hr_sa_social_meta_maybe_schedule');
}
add_action('init', 'hr_sa_bootstrap_og_module');

/**
 * Remove external OG emitters when conflict mode requests it.
 */
function hr_sa_maybe_block_external_og_tags(): void
{
    if (is_admin() || !hr_sa_should_block_external_og()) {
        return;
    }

    $default_targets = [
        [
            'hook'     => 'wp_head',
            'callback' => ['\WPTravelEngine\Plugin', 'wptravelengine_add_og_tag'],
            'priority' => 5,
        ],
        [
            'hook'     => 'wp_head',
            'callback' => '\WPTravelEngine\Plugin::wptravelengine_add_og_tag',
            'priority' => 5,
        ],
        [
            'hook'     => 'wp_head',
            'callback' => ['WPTravelEngine\Plugin', 'wptravelengine_add_og_tag'],
            'priority' => 5,
        ],
        [
            'hook'     => 'wp_head',
            'callback' => 'WPTravelEngine\Plugin::wptravelengine_add_og_tag',
            'priority' => 5,
        ],
    ];

    /**
     * Filter the list of third-party OG emitters that should be unhooked.
     *
     * @param array<int, array<string, mixed>> $default_targets
     */
    $targets = apply_filters('hr_sa_external_og_tag_hooks', $default_targets);

    foreach ($targets as $target) {
        if (!is_array($target)) {
            continue;
        }

        $hook     = isset($target['hook']) ? (string) $target['hook'] : '';
        $callback = $target['callback'] ?? null;
        $priority = isset($target['priority']) ? (int) $target['priority'] : 10;

        if ($hook === '' || $callback === null) {
            continue;
        }

        remove_action($hook, $callback, $priority);
    }
}
add_action('init', 'hr_sa_maybe_block_external_og_tags', 20);
add_action('wp', 'hr_sa_maybe_block_external_og_tags', 1);

/**
 * Schedule social meta output when appropriate.
 */
function hr_sa_social_meta_maybe_schedule(): void
{
    $GLOBALS['hr_sa_last_social_snapshot'] = null;

    if (!hr_sa_is_og_enabled() && !hr_sa_is_twitter_enabled()) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_output_social_meta', 15);
}

/**
 * Print Open Graph and Twitter meta tags in the document head.
 */
function hr_sa_output_social_meta(): void
{
    $snapshot = hr_sa_get_social_tag_snapshot();

    if ($snapshot['blocked'] || (!$snapshot['og_enabled'] && !$snapshot['twitter_enabled'])) {
        return;
    }

    if ($snapshot['og_enabled']) {
        foreach ($snapshot['og'] as $property => $value) {
            $content = hr_sa_escape_meta_content($property, $value);
            if ($content === '') {
                continue;
            }

            printf(
                '<meta property="%1$s" content="%2$s" />' . PHP_EOL,
                esc_attr($property),
                $content
            );
        }
    }

    if ($snapshot['twitter_enabled']) {
        foreach ($snapshot['twitter'] as $name => $value) {
            $content = hr_sa_escape_meta_content($name, $value);
            if ($content === '') {
                continue;
            }

            printf(
                '<meta name="%1$s" content="%2$s" />' . PHP_EOL,
                esc_attr($name),
                $content
            );
        }
    }
}

/**
 * Retrieve (and cache) the computed social tag snapshot for the request.
 *
 * @return array{og_enabled: bool, twitter_enabled: bool, blocked: bool, og: array<string, string>, twitter: array<string, string>, fields: array<string, string>}
 */
function hr_sa_get_social_tag_snapshot(): array
{
    $snapshot = $GLOBALS['hr_sa_last_social_snapshot'] ?? null;
    if (is_array($snapshot)) {
        return $snapshot;
    }

    $snapshot = hr_sa_collect_social_tag_data();
    $GLOBALS['hr_sa_last_social_snapshot'] = $snapshot;

    return $snapshot;
}

/**
 * Build the OG/Twitter snapshot from the shared context.
 *
 * @return array{og_enabled: bool, twitter_enabled: bool, blocked: bool, og: array<string, string>, twitter: array<string, string>, fields: array<string, string>}
 */
function hr_sa_collect_social_tag_data(): array
{
    $context = hr_sa_get_context();

    $blocked         = hr_sa_should_respect_other_seo() && hr_sa_other_seo_active();
    $og_enabled      = !$blocked && hr_sa_is_og_enabled();
    $twitter_enabled = !$blocked && hr_sa_is_twitter_enabled();

    $og_tags      = $og_enabled ? hr_sa_prepare_og_tags($context) : [];
    $twitter_tags = $twitter_enabled ? hr_sa_prepare_twitter_tags($context) : [];

    $fields = [
        'title'          => (string) ($context['title'] ?? ''),
        'description'    => (string) ($context['description'] ?? ''),
        'url'            => (string) ($context['url'] ?? ''),
        'image'          => (string) ($context['image'] ?? ($context['hero_url'] ?? '')),
        'site_name'      => (string) ($context['site_name'] ?? ''),
        'locale'         => (string) ($context['locale'] ?? ''),
        'twitter_handle' => (string) ($context['twitter_handle'] ?? ''),
    ];

    $snapshot = [
        'og_enabled'      => $og_enabled,
        'twitter_enabled' => $twitter_enabled,
        'blocked'         => $blocked,
        'og'              => $og_tags,
        'twitter'         => $twitter_tags,
        'fields'          => $fields,
    ];

    /**
     * Filter the computed social tag snapshot before it is cached.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $context
     */
    return apply_filters('hr_sa_social_tag_snapshot', $snapshot, $context);
}

/**
 * Prepare Open Graph tag values from the context array.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, string>
 */
function hr_sa_prepare_og_tags(array $context): array
{
    $title       = trim((string) ($context['title'] ?? ''));
    $description = trim((string) ($context['description'] ?? ''));
    $url         = trim((string) ($context['url'] ?? ''));
    $image       = trim((string) ($context['image'] ?? ($context['hero_url'] ?? '')));
    $site_name   = trim((string) ($context['site_name'] ?? ''));
    $locale      = trim((string) ($context['locale'] ?? ''));
    $type        = hr_sa_map_og_type((string) ($context['type'] ?? ''));

    if ($locale === '') {
        $locale = get_locale();
    }

    $tags = [
        'og:title'       => $title,
        'og:description' => $description,
        'og:type'        => $type,
        'og:url'         => $url,
        'og:image'       => $image,
        'og:site_name'   => $site_name,
        'og:locale'      => $locale,
    ];

    $tags = array_filter($tags, static fn($value): bool => is_string($value) && $value !== '');
    $ordered_keys = ['og:title', 'og:description', 'og:type', 'og:url', 'og:image', 'og:site_name', 'og:locale'];
    $ordered = [];
    foreach ($ordered_keys as $key) {
        if (isset($tags[$key])) {
            $ordered[$key] = $tags[$key];
        }
    }
    foreach ($tags as $key => $value) {
        if (!isset($ordered[$key])) {
            $ordered[$key] = $value;
        }
    }

    /**
     * Filter the final Open Graph tag set prior to emission.
     *
     * @param array<string, string> $ordered
     * @param array<string, mixed>  $context
     */
    return apply_filters('hr_sa_og_tags', $ordered, $context);
}

/**
 * Prepare Twitter Card tag values from the context array.
 *
 * @param array<string, mixed> $context
 *
 * @return array<string, string>
 */
function hr_sa_prepare_twitter_tags(array $context): array
{
    $title       = trim((string) ($context['title'] ?? ''));
    $description = trim((string) ($context['description'] ?? ''));
    $image       = trim((string) ($context['image'] ?? ($context['hero_url'] ?? '')));
    $handle      = trim((string) ($context['twitter_handle'] ?? ''));

    $tags = [
        'twitter:card'        => 'summary_large_image',
        'twitter:title'       => $title,
        'twitter:description' => $description,
        'twitter:image'       => $image,
    ];

    if ($handle !== '') {
        $tags['twitter:site'] = $handle;
    }

    $tags = array_filter($tags, static fn($value): bool => is_string($value) && $value !== '');
    $ordered_keys = ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:site'];
    $ordered = [];
    foreach ($ordered_keys as $key) {
        if (isset($tags[$key])) {
            $ordered[$key] = $tags[$key];
        }
    }
    foreach ($tags as $key => $value) {
        if (!isset($ordered[$key])) {
            $ordered[$key] = $value;
        }
    }

    /**
     * Filter the final Twitter Card tag set prior to emission.
     *
     * @param array<string, string> $ordered
     * @param array<string, mixed>  $context
     */
    $ordered = apply_filters('hr_sa_twitter_tags', $ordered, $context);

    if (!isset($ordered['twitter:card'])) {
        $ordered = ['twitter:card' => 'summary_large_image'] + $ordered;
    }

    return $ordered;
}

/**
 * Map internal content types to Open Graph types.
 */
function hr_sa_map_og_type(string $content_type): string
{
    switch ($content_type) {
        case 'home':
            return 'website';
        case 'trip':
            return 'product';
        default:
            return 'article';
    }
}

/**
 * Escape meta content values according to their expected type.
 */
function hr_sa_escape_meta_content(string $key, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $url_keys = ['og:url', 'og:image', 'twitter:image'];
    if (in_array($key, $url_keys, true)) {
        return esc_url($value);
    }

    return esc_attr($value);
}
