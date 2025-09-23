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
    $context = [
        'url'            => hr_sa_guess_canonical_url(),
        'type'           => hr_sa_detect_content_type(),
        'title'          => '', // TODO: Populate with contextual title in Phase 1.
        'description'    => '', // TODO: Populate with contextual description in Phase 1.
        'country'        => '', // TODO: Populate with geographic context in Phase 1.
        'site_name'      => hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name')),
        'locale'         => hr_sa_get_setting('hr_sa_locale', get_locale()),
        'twitter_handle' => hr_sa_get_setting('hr_sa_twitter_handle', ''),
        'hero_url'       => hr_sa_get_media_help_hero_url(),
    ];

    return apply_filters('hr_sa_get_context', $context);
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
