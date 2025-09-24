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
require_once HR_SA_PLUGIN_DIR . 'admin/ajax/ai.php';
require_once HR_SA_PLUGIN_DIR . 'admin/meta-boxes/meta.php';

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
    if (strpos($hook_suffix, 'hr-sa-') === false) {
        return;
    }

    $is_settings_screen = strpos($hook_suffix, 'hr-sa-settings') !== false;

    if ($is_settings_screen) {
        wp_enqueue_media();
    }

    wp_enqueue_style(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.css',
        [],
        HR_SA_VERSION
    );

    $script_dependencies = ['wp-i18n'];
    if ($is_settings_screen) {
        $script_dependencies[] = 'media-editor';
    }

    wp_enqueue_script(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.js',
        $script_dependencies,
        HR_SA_VERSION,
        true
    );

    if ($is_settings_screen) {
        $ai_settings_full = hr_sa_get_ai_settings();
        $ai_settings      = hr_sa_ai_get_settings();

        wp_localize_script(
            'hr-sa-admin',
            'hrSaAdminSettings',
            [
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonceTest'    => wp_create_nonce('hr_sa_ai_test'),
                'aiEnabled'    => (bool) $ai_settings['hr_sa_ai_enabled'],
                'hasKey'       => (bool) $ai_settings['hr_sa_ai_has_key'],
                'hasKeyMasked' => hr_sa_mask_api_key_for_display($ai_settings_full['hr_sa_ai_api_key'] ?? ''),
                'messages'     => [
                    'testing'     => __('Testing connection…', HR_SA_TEXT_DOMAIN),
                    'success'     => __('Connection successful.', HR_SA_TEXT_DOMAIN),
                    'missingKey'  => __('Add an API key before testing the connection.', HR_SA_TEXT_DOMAIN),
                    'disabled'    => __('Enable AI assistance before testing the connection.', HR_SA_TEXT_DOMAIN),
                    'error'       => __('Unable to reach the AI service. Please check your settings and try again.', HR_SA_TEXT_DOMAIN),
                    'requestError'=> __('We could not generate content at this time. Please try again later.', HR_SA_TEXT_DOMAIN),
                ],
            ]
        );
    }

    wp_set_script_translations('hr-sa-admin', HR_SA_TEXT_DOMAIN);
}
