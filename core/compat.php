<?php
/**
 * Compatibility helpers for other SEO plugins.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether Rank Math is active.
 */
function hr_sa_is_rank_math_active(): bool
{
    return defined('RANK_MATH_VERSION');
}

/**
 * Check whether Yoast SEO is active.
 */
function hr_sa_is_yoast_active(): bool
{
    return defined('WPSEO_VERSION') || class_exists('WPSEO_Frontend');
}

/**
 * Check whether SEOPress is active.
 */
function hr_sa_is_seopress_active(): bool
{
    return defined('SEOPRESS_VERSION');
}

/**
 * Determine if any other SEO plugin is active.
 */
function hr_sa_other_seo_active(): bool
{
    return hr_sa_is_rank_math_active() || hr_sa_is_yoast_active() || hr_sa_is_seopress_active();
}
