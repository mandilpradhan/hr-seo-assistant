<?php
/**
 * Admin bar helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_bar_menu', 'hr_sa_register_admin_bar_badge', 100);
add_action('admin_head', 'hr_sa_output_admin_bar_styles');
add_action('wp_head', 'hr_sa_output_admin_bar_styles');

/**
 * Register the OG image source badge in the admin bar.
 */
function hr_sa_register_admin_bar_badge(WP_Admin_Bar $wp_admin_bar): void
{
    if (!is_user_logged_in() || !current_user_can('manage_options') || !is_admin_bar_showing()) {
        return;
    }

    $post_id = 0;

    if (is_admin()) {
        $screen = get_current_screen();
        if ($screen && in_array($screen->base, ['post', 'post-new'], true)) {
            $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
    } else {
        $post_id = (int) get_queried_object_id();
    }

    if ($post_id <= 0) {
        $post_id = 0;
    }

    $image_data = hr_sa_resolve_social_image_url($post_id);
    $source     = $image_data['source'] ?? 'disabled';
    $url        = $image_data['url'] ?? '';

    $label_map = [
        'override' => __('OG: override', HR_SA_TEXT_DOMAIN),
        'meta'     => __('OG: meta', HR_SA_TEXT_DOMAIN),
        'fallback' => __('OG: fallback', HR_SA_TEXT_DOMAIN),
        'disabled' => __('OG: disabled', HR_SA_TEXT_DOMAIN),
    ];

    $class_map = [
        'override' => 'hr-sa-admin-bar-badge--override',
        'meta'     => 'hr-sa-admin-bar-badge--meta',
        'fallback' => 'hr-sa-admin-bar-badge--fallback',
        'disabled' => 'hr-sa-admin-bar-badge--disabled',
    ];

    $label = $label_map[$source] ?? sprintf(__('OG: %s', HR_SA_TEXT_DOMAIN), $source);
    $class = $class_map[$source] ?? 'hr-sa-admin-bar-badge--disabled';

    $wp_admin_bar->add_node([
        'id'     => 'hr-sa-og-source',
        'parent' => 'top-secondary',
        'title'  => esc_html($label),
        'href'   => $url !== '' ? $url : false,
        'meta'   => [
            'class' => 'hr-sa-admin-bar-badge ' . $class,
            'title' => __('Open Graph image source', HR_SA_TEXT_DOMAIN),
        ],
    ]);
}

/**
 * Output custom styles for the admin bar badge.
 */
function hr_sa_output_admin_bar_styles(): void
{
    if (!is_user_logged_in() || !current_user_can('manage_options') || !is_admin_bar_showing()) {
        return;
    }

    static $printed = false;
    if ($printed) {
        return;
    }

    $printed = true;
    ?>
    <style id="hr-sa-admin-bar-badge-style">
        #wpadminbar .hr-sa-admin-bar-badge {
            background-color: #1a7f37;
            border-radius: 999px;
            color: #fff !important;
            font-weight: 600;
            margin-left: 6px;
            padding: 0 12px;
        }

        #wpadminbar .hr-sa-admin-bar-badge:hover,
        #wpadminbar .hr-sa-admin-bar-badge:focus {
            color: #fff !important;
        }

        #wpadminbar .hr-sa-admin-bar-badge--meta {
            background-color: #2563eb;
        }

        #wpadminbar .hr-sa-admin-bar-badge--fallback {
            background-color: #d97706;
        }

        #wpadminbar .hr-sa-admin-bar-badge--disabled {
            background-color: #6b7280;
        }
    </style>
    <?php
}
