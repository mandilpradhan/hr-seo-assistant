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
        'hr_sa_jsonld_enabled'    => '1',
        'hr_sa_og_enabled'        => '1',
        'hr_sa_debug_enabled'     => hr_sa_get_settings_defaults()['hr_sa_debug_enabled'],
        'hr_sa_respect_other_seo' => '1',
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
    $enabled = hr_sa_is_flag_enabled('hr_sa_og_enabled', true);
    $enabled = (bool) apply_filters('hr_sa_og_enabled', $enabled);

    /**
     * Filter whether Open Graph output should be enabled.
     *
     * @param bool $enabled
     */
    return (bool) apply_filters('hr_sa_enable_og', $enabled);
}

/**
 * Whether Twitter Card emission is enabled.
 */
function hr_sa_is_twitter_enabled(): bool
{
    $enabled = hr_sa_is_og_enabled();

    /**
     * Filter whether Twitter Card output should be enabled.
     *
     * @param bool $enabled
     */
    return (bool) apply_filters('hr_sa_enable_twitter', $enabled);
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
    $mode    = (string) get_option('hr_sa_conflict_mode', 'respect');
    if ($mode === 'block_og' || $mode === 'block_others') {
        $mode = 'force';
    }

    $allowed = ['respect', 'force'];
    $mode    = in_array($mode, $allowed, true) ? $mode : 'respect';

    /**
     * Allow integrations to adjust the resolved conflict mode.
     *
     * @param string $mode
     */
    $mode = (string) apply_filters('hr_sa_conflict_mode', $mode);

    return $mode;
}

/**
 * Convenience helper to compare the current conflict mode string.
 */
function hr_sa_conflict_mode_is(string $mode): bool
{
    if ($mode === 'block_og' || $mode === 'block_others') {
        $mode = 'force';
    }

    return hr_sa_get_conflict_mode() === $mode;
}

/**
 * Whether other SEO plugins should be respected (i.e., skip emission).
 */
function hr_sa_should_respect_other_seo(): bool
{
    $option = hr_sa_is_flag_enabled('hr_sa_respect_other_seo', true);

    return hr_sa_get_conflict_mode() === 'respect' && $option;
}
