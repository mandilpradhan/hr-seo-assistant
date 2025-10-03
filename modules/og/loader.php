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
    $post_id = is_singular() ? (int) get_queried_object_id() : 0;

    $blocked         = hr_sa_should_respect_other_seo() && hr_sa_other_seo_active();
    $og_enabled      = !$blocked && hr_sa_is_og_enabled();
    $twitter_enabled = !$blocked && hr_sa_is_twitter_enabled();

    $offers = [];
    if ($post_id > 0 && ($context['type'] ?? '') === 'trip') {
        $trip_payload = hr_sa_hrdf_trip_payload($post_id);
        $offers       = is_array($trip_payload['offers']) ? $trip_payload['offers'] : [];
    }

    $og_tags      = $og_enabled ? hr_sa_prepare_og_tags($context, $offers) : [];
    $twitter_tags = $twitter_enabled ? hr_sa_prepare_twitter_tags($context) : [];

    $fields = [
        'title'          => (string) ($context['title'] ?? ''),
        'description'    => (string) ($context['description'] ?? ''),
        'url'            => (string) ($context['url'] ?? ''),
        'image'          => (string) ($context['image'] ?? ($context['hero_url'] ?? '')),
        'site_name'      => (string) ($context['site_name'] ?? ''),
        'og_site_name'   => (string) ($context['og_site_name'] ?? ''),
        'locale'         => (string) ($context['locale'] ?? ''),
        'twitter_handle' => (string) ($context['twitter_handle'] ?? ''),
        'twitter_site'   => (string) ($context['twitter_site'] ?? ''),
        'twitter_creator'=> (string) ($context['twitter_creator'] ?? ''),
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
 * @param array<int, mixed> $offers
 * @return array<string, string>
 */
function hr_sa_prepare_og_tags(array $context, array $offers = []): array
{
    $title       = trim((string) ($context['title'] ?? ''));
    $description = trim((string) ($context['description'] ?? ''));
    $url         = trim((string) ($context['url'] ?? ''));
    $image       = trim((string) ($context['image'] ?? ($context['hero_url'] ?? '')));
    $site_name   = trim((string) ($context['og_site_name'] ?? ($context['site_name'] ?? '')));
    $locale      = trim((string) ($context['locale'] ?? ''));
    $type        = hr_sa_map_og_type((string) ($context['type'] ?? ''));

    $tags = [
        'og:title'       => $title,
        'og:description' => $description,
        'og:type'        => $type,
        'og:url'         => $url,
        'og:image'       => $image,
        'og:site_name'   => $site_name,
    ];

    if ($locale !== '') {
        $tags['og:locale'] = $locale;
    }

    if ($type === 'product') {
        $offer = hr_sa_og_select_offer($offers);
        if ($offer) {
            $tags['product:price:amount']   = $offer['amount'];
            $tags['product:price:currency'] = $offer['currency'];
            if ($offer['availability'] !== '') {
                $tags['product:availability'] = $offer['availability'];
            }
        }
    }

    $tags = array_filter($tags, static fn($value): bool => is_string($value) && $value !== '');
    $ordered_keys = ['og:title', 'og:description', 'og:type', 'og:url', 'og:image', 'og:site_name', 'og:locale', 'product:price:amount', 'product:price:currency', 'product:availability'];
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
    $handle      = trim((string) ($context['twitter_site'] ?? ($context['twitter_handle'] ?? '')));
    $creator     = trim((string) ($context['twitter_creator'] ?? ''));

    $tags = [
        'twitter:card'        => 'summary_large_image',
        'twitter:title'       => $title,
        'twitter:description' => $description,
        'twitter:image'       => $image,
    ];

    if ($handle !== '') {
        $tags['twitter:site'] = $handle;
    }

    if ($creator !== '') {
        $tags['twitter:creator'] = $creator;
    }

    $tags = array_filter($tags, static fn($value): bool => is_string($value) && $value !== '');
    $ordered_keys = ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:site'];
    if ($creator !== '') {
        $ordered_keys[] = 'twitter:creator';
    }
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
 * Select the first offer for Open Graph price metadata.
 *
 * @param array<int, mixed> $offers
 * @return array{amount: string, currency: string, availability: string}|null
 */
function hr_sa_og_select_offer(array $offers): ?array
{
    foreach ($offers as $offer) {
        if (!is_array($offer) || !isset($offer['price']) || !is_array($offer['price'])) {
            continue;
        }

        $amount   = $offer['price']['amount'] ?? null;
        $currency = isset($offer['price']['currency']) ? strtoupper(hr_sa_hrdf_normalize_text($offer['price']['currency'])) : '';

        if (!is_numeric($amount) || $currency === '') {
            continue;
        }

        $availability = isset($offer['availability']) ? hr_sa_og_map_availability((string) $offer['availability']) : '';

        return [
            'amount'       => hr_sa_hrdf_format_price((float) $amount),
            'currency'     => $currency,
            'availability' => $availability,
        ];
    }

    return null;
}

/**
 * Map schema availability URLs to Open Graph tokens.
 */
function hr_sa_og_map_availability(string $value): string
{
    $value = trim($value);

    return match ($value) {
        'https://schema.org/InStock'             => 'instock',
        'https://schema.org/SoldOut'             => 'oos',
        'https://schema.org/LimitedAvailability' => 'limited availability',
        default                                  => '',
    };
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
