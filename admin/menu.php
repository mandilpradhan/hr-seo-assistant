<?php
/**
 * Admin menu registration and asset loading.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once HR_SA_PLUGIN_DIR . 'admin/pages/overview.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/settings.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/modules.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/debug.php';

add_action('admin_menu', 'hr_sa_register_admin_menu');
add_action('admin_enqueue_scripts', 'hr_sa_enqueue_admin_assets');

/**
 * Register the HR SEO Assistant admin menu and subpages.
 */
function hr_sa_register_admin_menu(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $top_slug = 'hr-sa-overview';

    add_menu_page(
        __('HR SEO Assistant', HR_SA_TEXT_DOMAIN),
        __('HR SEO', HR_SA_TEXT_DOMAIN),
        'manage_options',
        $top_slug,
        'hr_sa_render_overview_page',
        'dashicons-chart-area',
        58
    );

    add_submenu_page(
        $top_slug,
        __('Overview', HR_SA_TEXT_DOMAIN),
        __('Overview', HR_SA_TEXT_DOMAIN),
        'manage_options',
        $top_slug,
        'hr_sa_render_overview_page'
    );

    add_submenu_page(
        $top_slug,
        __('Settings', HR_SA_TEXT_DOMAIN),
        __('Settings', HR_SA_TEXT_DOMAIN),
        'manage_options',
        'hr-sa-settings',
        'hr_sa_render_settings_page'
    );

    add_submenu_page(
        $top_slug,
        __('Modules', HR_SA_TEXT_DOMAIN),
        __('Modules', HR_SA_TEXT_DOMAIN),
        'manage_options',
        'hr-sa-modules',
        'hr_sa_render_modules_page'
    );

    if (hr_sa_is_debug_enabled()) {
        add_submenu_page(
            $top_slug,
            __('Debug', HR_SA_TEXT_DOMAIN),
            __('Debug', HR_SA_TEXT_DOMAIN),
            'manage_options',
            'hr-sa-debug',
            'hr_sa_render_debug_page'
        );
    }
}

/**
 * Load admin assets for plugin screens.
 */
function hr_sa_enqueue_admin_assets(string $hook_suffix): void
{
    $screen = get_current_screen();
    if (!$screen) {
        return;
    }

    $allowed = [
        'toplevel_page_hr-sa-overview',
        'hr-sa-overview_page_hr-sa-settings',
        'hr-sa-overview_page_hr-sa-modules',
        'hr-sa-overview_page_hr-sa-debug',
    ];

    if (!in_array($screen->id, $allowed, true)) {
        return;
    }

    wp_enqueue_style(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.css',
        [],
        HR_SA_VERSION
    );

    wp_enqueue_script(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.js',
        ['wp-i18n'],
        HR_SA_VERSION,
        true
    );

    wp_set_script_translations('hr-sa-admin', HR_SA_TEXT_DOMAIN);

    if ($screen->id === 'hr-sa-overview_page_hr-sa-settings') {
        wp_enqueue_media();
    }
}
