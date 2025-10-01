<?php
/**
 * HRDF helper utilities.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve a value from HRDF when available.
 *
 * @param mixed $default Default value when HRDF is unavailable or returns null.
 * @return mixed
 */
function hr_sa_hrdf_get(string $dot_path, int $post_id = 0, $default = null)
{
    if (!function_exists('hr_df_get')) {
        return $default;
    }

    $value = hr_df_get($dot_path, $post_id, $default);

    return $value === null ? $default : $value;
}

/**
 * Retrieve the first non-empty value from a list of HRDF dot paths.
 *
 * @param array<int, string> $paths
 * @param mixed              $default
 * @return mixed
 */
function hr_sa_hrdf_get_first(array $paths, int $post_id = 0, $default = null)
{
    foreach ($paths as $path) {
        $value = hr_sa_hrdf_get($path, $post_id, null);

        if ($value === null) {
            continue;
        }

        if (is_string($value)) {
            if (trim($value) === '') {
                continue;
            }

            return $value;
        }

        if (is_array($value)) {
            if ($value === []) {
                continue;
            }

            return $value;
        }

        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

/**
 * Retrieve the full HRDF document for the given post when available.
 *
 * @return array<string, mixed>
 */
function hr_sa_hrdf_document(int $post_id = 0): array
{
    if (!function_exists('hr_df_document')) {
        return [];
    }

    $document = hr_df_document($post_id);

    return is_array($document) ? $document : [];
}

/**
 * Determine whether HRDF helpers are available.
 */
function hr_sa_hrdf_is_available(): bool
{
    return function_exists('hr_df_get') && function_exists('hr_df_document');
}

/**
 * Determine whether HRDF-only mode is active.
 */
function hr_sa_is_hrdf_only_mode(): bool
{
    if (defined('HR_SA_HRDF_ONLY')) {
        return (bool) HR_SA_HRDF_ONLY;
    }

    $option_value = get_option('hr_sa_hrdf_only_mode', '1');
    $enabled = $option_value === '1' || $option_value === 1 || $option_value === true;

    /**
     * Filter the resolved HRDF-only mode flag.
     *
     * @param bool   $enabled   Whether HRDF-only mode is enabled.
     * @param string $raw_value Raw option value prior to casting.
     */
    return (bool) apply_filters('hr_sa_hrdf_only_mode', $enabled, $option_value);
}
