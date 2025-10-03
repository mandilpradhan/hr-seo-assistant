<?php
/**
 * HR Data Framework access helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve a value from the HR Data Framework using a dot-notated key.
 *
 * @param string   $path    Dot-notated key path (e.g. `trip.product.name`).
 * @param int|null $post_id Optional context post ID.
 * @param mixed    $default Default value when HRDF does not return a value.
 *
 * @return mixed
 */
function hr_sa_hrdf_get(string $path, ?int $post_id = null, $default = null)
{
    static $cache = [];

    $cache_key = $path . '|' . ($post_id ?? 0);
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    /** @var mixed $value */
    $value = apply_filters('hr_sa_hrdf_get_value', null, $path, $post_id);
    if ($value === null) {
        $value = $default;
    }

    $cache[$cache_key] = $value;

    return $value;
}

/**
 * Retrieve an array value from HRDF.
 */
function hr_sa_hrdf_get_array(string $path, ?int $post_id = null): array
{
    $value = hr_sa_hrdf_get($path, $post_id, []);
    if (!is_array($value)) {
        return [];
    }

    return $value;
}

/**
 * Determine whether the HRDF key resolves to a non-empty value.
 */
function hr_sa_hrdf_has_value(string $path, ?int $post_id = null): bool
{
    $value = hr_sa_hrdf_get($path, $post_id);

    if (is_array($value)) {
        return !empty($value);
    }

    return $value !== null && $value !== '';
}
