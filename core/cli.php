<?php
/**
 * WP-CLI commands for HR SEO Assistant.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('hr-seo test-doc', 'hr_sa_cli_test_doc');
}

/**
 * Output a compact JSON preview of HRDF-derived values for a post.
 *
 * ## OPTIONS
 *
 * <post_id>
 * : The post ID to inspect.
 */
function hr_sa_cli_test_doc(array $args, array $assoc_args = []): void
{
    if (empty($args[0])) {
        WP_CLI::error(__('Please provide a post ID.', HR_SA_TEXT_DOMAIN));
        return;
    }

    $post_id = (int) $args[0];
    if ($post_id <= 0) {
        WP_CLI::error(__('Invalid post ID provided.', HR_SA_TEXT_DOMAIN));
        return;
    }

    if (!get_post($post_id)) {
        WP_CLI::error(__('The requested post could not be found.', HR_SA_TEXT_DOMAIN));
        return;
    }

    $meta_profile  = hr_sa_resolve_meta_profile($post_id);
    $image_profile = hr_sa_resolve_image_profile($post_id);
    $site_profile  = hr_sa_resolve_site_profile();

    $legal_name = hr_sa_sanitize_text_value((string) hr_sa_hrdf_get('hrdf.org.legalName', 0, ''));
    if ($legal_name === '') {
        $legal_name = $site_profile['name'] ?? (string) get_bloginfo('name');
    }

    $payload = [
        'meta' => [
            'title'       => $meta_profile['title'] ?? '',
            'description' => $meta_profile['description'] ?? '',
        ],
        'hero' => [
            'image_url' => $image_profile['primary'] ?? '',
        ],
        'org'  => [
            'legalName' => $legal_name,
        ],
        'site' => [
            'url' => $site_profile['url'] ?? trailingslashit((string) home_url('/')),
        ],
    ];

    if (!hr_sa_hrdf_is_available()) {
        WP_CLI::warning(__('HRDF helpers are unavailable. Values may reflect WordPress fallbacks.', HR_SA_TEXT_DOMAIN));
    }

    WP_CLI::log(wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
