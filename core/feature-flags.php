<?php
/**
 * Feature flag helpers and defaults.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prime default feature flag options.
 */
function hr_sa_feature_flags_initialize_defaults(): void
{
    $defaults = [
        'hr_sa_jsonld_enabled'      => '1',
        'hr_sa_og_enabled'          => '0',
        'hr_sa_debug_enabled'       => hr_sa_get_settings_defaults()['hr_sa_debug_enabled'],
        'hr_sa_respect_other_seo'   => '1',
    ];

    foreach ($defaults as $option => $value) {
        if (get_option($option, null) === null) {
            add_option($option, $value);
        }
    }
}

/**
 * Determine whether a stored flag value is enabled.
 */
function hr_sa_is_flag_enabled(string $option, bool $default = false): bool
{
    $value = get_option($option, $default ? '1' : '0');
    return $value === '1' || $value === 1 || $value === true;
}

/**
 * Whether JSON-LD emission is enabled.
 */
function hr_sa_is_jsonld_enabled(): bool
{
    $enabled = hr_sa_is_flag_enabled('hr_sa_jsonld_enabled', true);
    return (bool) apply_filters('hr_sa_jsonld_enabled', $enabled);
}

/**
 * Whether OG/Twitter emission is enabled.
 */
function hr_sa_is_og_enabled(): bool
{
    $enabled = hr_sa_is_flag_enabled('hr_sa_og_enabled', false);
    return (bool) apply_filters('hr_sa_og_enabled', $enabled);
}

/**
 * Whether Debug tools are enabled.
 */
function hr_sa_is_debug_enabled(): bool
{
    $option = hr_sa_get_setting('hr_sa_debug_enabled');
    $enabled = is_bool($option) ? $option : hr_sa_is_flag_enabled('hr_sa_debug_enabled', false);

    return (bool) apply_filters('hr_sa_debug_enabled', $enabled);
}

/**
 * Resolve the conflict mode string.
 */
function hr_sa_get_conflict_mode(): string
{
    $mode = (string) get_option('hr_sa_conflict_mode', 'respect');
    $mode = $mode === 'force' ? 'force' : 'respect';

    return (string) apply_filters('hr_sa_conflict_mode', $mode);
}

/**
 * Whether other SEO plugins should be respected (i.e., skip emission).
 */
function hr_sa_should_respect_other_seo(): bool
{
    $option = hr_sa_is_flag_enabled('hr_sa_respect_other_seo', true);
    $mode   = hr_sa_get_conflict_mode();

    return $mode === 'respect' && $option;
}
