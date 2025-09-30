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
    hr_sa_og_boot_module();
}

/**
 * Register the OG/Twitter hooks once.
 */
function hr_sa_og_boot_module(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    hr_sa_maybe_register_external_og_scrub();
    add_action('wp', 'hr_sa_social_meta_maybe_schedule');
    $booted = true;
}

/**
 * Register the WP Travel Engine OG scrubber when requested.
 */
function hr_sa_maybe_register_external_og_scrub(): void
{
    if (!hr_sa_conflict_mode_is('block_others')) {
        return;
    }

    static $scrubber = null;
    static $registered = false;

    if ($registered) {
        return;
    }

    if ($scrubber === null) {
        $scrubber = static function (): void {
            if (!hr_sa_conflict_mode_is('block_others')) {
                return;
            }

            global $wp_filter;

            if (empty($wp_filter['wp_head']) || empty($wp_filter['wp_head']->callbacks)) {
                return;
            }

            $target_class  = 'WPTravelEngine\\Plugin';
            $target_method = 'wptravelengine_add_og_tag';

            foreach ($wp_filter['wp_head']->callbacks as $priority => &$bucket) {
                if (!is_array($bucket)) {
                    continue;
                }

                foreach ($bucket as $id => $cb) {
                    if (empty($cb['function']) || !is_array($cb['function'])) {
                        continue;
                    }

                    [$obj_or_class, $method] = $cb['function'];

                    if (strcasecmp((string) $method, $target_method) !== 0) {
                        continue;
                    }

                    $class = is_object($obj_or_class) ? get_class($obj_or_class) : (string) $obj_or_class;
                    $class_match = ($class === $target_class)
                        || (stripos($class, 'WPTravelEngine') !== false && substr($class, -strlen('\\Plugin')) === '\\Plugin');

                    if ($class_match) {
                        unset($bucket[$id]);
                    }
                }

                if (empty($bucket)) {
                    unset($wp_filter['wp_head']->callbacks[$priority]);
                }
            }
            unset($bucket);
        };
    }

    add_action('wp', $scrubber, 0);
    add_action('get_header', $scrubber, 0);
    add_action('template_redirect', $scrubber, 0);
    add_action('wp_head', $scrubber, 0);

    $registered = true;
}

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

    add_action('wp_head', 'hr_sa_output_social_meta', 6);
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
        'title'          => (string) ($context['meta']['title'] ?? ''),
        'description'    => (string) ($context['meta']['description'] ?? ''),
        'url'            => (string) ($context['meta']['canonical_url'] ?? ''),
        'image'          => (string) ($context['hero']['image_url'] ?? ''),
        'site_name'      => (string) ($context['site']['name'] ?? ''),
        'locale'         => '',
        'twitter_handle' => '',
        'og_type'        => (string) ($context['meta']['og_type'] ?? ''),
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
    $meta = $context['meta'] ?? [];
    $site = $context['site'] ?? [];
    $hero = $context['hero'] ?? [];

    $title       = hr_sa_hrdf_string($meta['title'] ?? '');
    $description = hr_sa_hrdf_string($meta['description'] ?? '');
    $url         = hr_sa_hrdf_url($meta['canonical_url'] ?? '');
    $image       = hr_sa_hrdf_url($hero['image_url'] ?? '');
    $site_name   = hr_sa_hrdf_string($site['name'] ?? '');

    $type = hr_sa_hrdf_string($meta['og_type'] ?? '');
    if ($type === '') {
        $type = hr_sa_context_has_trip($context) ? 'product' : 'website';
    }

    $tags = [];
    if ($title !== '') {
        $tags['og:title'] = $title;
    }
    if ($description !== '') {
        $tags['og:description'] = $description;
    }
    $tags['og:type'] = $type;
    if ($url !== '') {
        $tags['og:url'] = $url;
    }
    if ($image !== '') {
        $tags['og:image'] = $image;
    }
    if ($site_name !== '') {
        $tags['og:site_name'] = $site_name;
    }

    /**
     * Filter the final Open Graph tag set prior to emission.
     *
     * @param array<string, string> $tags
     * @param array<string, mixed>  $context
     */
    return apply_filters('hr_sa_og_tags', $tags, $context);
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
    $meta = $context['meta'] ?? [];
    $hero = $context['hero'] ?? [];

    $title       = hr_sa_hrdf_string($meta['title'] ?? '');
    $description = hr_sa_hrdf_string($meta['description'] ?? '');
    $image       = hr_sa_hrdf_url($hero['image_url'] ?? '');

    $card = $image !== '' ? 'summary_large_image' : 'summary';

    $tags = [
        'twitter:card' => $card,
    ];

    if ($title !== '') {
        $tags['twitter:title'] = $title;
    }

    if ($description !== '') {
        $tags['twitter:description'] = $description;
    }

    if ($image !== '') {
        $tags['twitter:image'] = $image;
    }

    /**
     * Filter the final Twitter Card tag set prior to emission.
     *
     * @param array<string, string> $tags
     * @param array<string, mixed>  $context
     */
    $tags = apply_filters('hr_sa_twitter_tags', $tags, $context);

    if (!isset($tags['twitter:card'])) {
        $tags = ['twitter:card' => $card] + $tags;
    }

    return $tags;
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
