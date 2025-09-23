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
 * Stub OG loader hook.
 */
function hr_sa_bootstrap_og_module(): void
{
    if (!hr_sa_is_og_enabled()) {
        return;
    }

    // TODO: Implement OG/Twitter emission in Phase 1.
}
add_action('init', 'hr_sa_bootstrap_og_module');
