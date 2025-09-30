<?php
/**
 * Canonical tag emission from HRDF values.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp', 'hr_sa_canonical_maybe_schedule');

/**
 * Schedule canonical tag output when appropriate.
 */
function hr_sa_canonical_maybe_schedule(): void
{
    if (is_admin()) {
        return;
    }

    if (hr_sa_should_respect_other_seo() && hr_sa_other_seo_active()) {
        return;
    }

    add_action('wp_head', 'hr_sa_output_canonical_tag', 7);
}

/**
 * Emit the canonical link element when available.
 */
function hr_sa_output_canonical_tag(): void
{
    $context = hr_sa_get_context();
    $canonical = hr_sa_hrdf_url($context['meta']['canonical_url'] ?? '');

    if ($canonical === '') {
        return;
    }

    printf('<link rel="canonical" href="%s" />' . PHP_EOL, esc_url($canonical));
}
