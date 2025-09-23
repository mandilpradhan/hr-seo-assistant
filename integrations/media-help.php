<?php
/**
 * Integrations with the Media Help plugin (hero image provider).
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retrieve the current hero image URL provided by Media Help, if available.
 */
function hr_sa_get_media_help_hero_url(): ?string
{
    $url = apply_filters('hr_mh_current_hero_url', null);
    if (!is_string($url) || $url === '') {
        return null;
    }

    $url = esc_url_raw($url);
    return $url !== '' ? $url : null;
}
