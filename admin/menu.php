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
require_once HR_SA_PLUGIN_DIR . 'admin/pages/module-jsonld.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/module-open-graph.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/module-ai.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/debug.php';
require_once HR_SA_PLUGIN_DIR . 'admin/ajax/ai.php';
require_once HR_SA_PLUGIN_DIR . 'admin/ajax/jsonld-preview.php';
require_once HR_SA_PLUGIN_DIR . 'admin/ajax/modules.php';
require_once HR_SA_PLUGIN_DIR . 'admin/meta-boxes/ai.php';

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

    foreach (HRSA\Modules::all() as $module) {
        if (!is_array($module) || empty($module['slug']) || empty($module['render'])) {
            continue;
        }

        $submenu_slug = 'hr-sa-module-' . sanitize_title((string) $module['slug']);
        $callback     = is_callable($module['render']) ? $module['render'] : '__return_null';

        add_submenu_page(
            $top_slug,
            (string) $module['label'],
            (string) $module['label'],
            (string) ($module['capability'] ?? 'manage_options'),
            $submenu_slug,
            $callback
        );
    }

    add_submenu_page(
        $top_slug,
        __('Legacy Settings', HR_SA_TEXT_DOMAIN),
        __('Legacy Settings', HR_SA_TEXT_DOMAIN),
        'manage_options',
        'hr-sa-settings',
        'hr_sa_render_settings_page'
    );
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
    $is_social_screen   = strpos($hook_suffix, 'hr-sa-module-open-graph') !== false;
    $is_ai_screen       = strpos($hook_suffix, 'hr-sa-module-ai') !== false;
    $is_jsonld_screen   = strpos($hook_suffix, 'hr-sa-module-json-ld') !== false;
    $needs_media        = $is_settings_screen || $is_social_screen;

    if ($needs_media) {
        wp_enqueue_media();
    }

    wp_enqueue_style(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.css',
        [],
        HR_SA_VERSION
    );

    $script_dependencies = ['wp-i18n'];
    if ($needs_media) {
        $script_dependencies[] = 'media-editor';
    }

    wp_enqueue_script(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.js',
        $script_dependencies,
        HR_SA_VERSION,
        true
    );

    if ($is_jsonld_screen) {
        wp_register_script(
            'hr-sa-admin-jsonld',
            HR_SA_PLUGIN_URL . 'assets/module-jsonld.js',
            ['wp-i18n'],
            HR_SA_VERSION,
            true
        );

        $preview_data = [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('hr_sa_jsonld_preview'),
            'defaultTarget' => 0,
            'messages'      => [
                'loading'   => __('Loading preview…', HR_SA_TEXT_DOMAIN),
                'error'     => __('We could not load the preview. Please try again.', HR_SA_TEXT_DOMAIN),
                'empty'     => __('No JSON-LD nodes were generated for this selection.', HR_SA_TEXT_DOMAIN),
                'ready'     => __('Select an item to load its JSON-LD.', HR_SA_TEXT_DOMAIN),
                'tableKey'  => __('Property', HR_SA_TEXT_DOMAIN),
                'tableValue'=> __('Value', HR_SA_TEXT_DOMAIN),
                'jsonEmpty' => __('JSON will appear here after loading a preview.', HR_SA_TEXT_DOMAIN),
            ],
            'targets'       => hr_sa_jsonld_preview_build_targets(),
        ];

        wp_localize_script('hr-sa-admin-jsonld', 'hrSaJsonLdPreview', $preview_data);
        wp_set_script_translations('hr-sa-admin-jsonld', HR_SA_TEXT_DOMAIN);
        wp_enqueue_script('hr-sa-admin-jsonld');
    }

    if ($is_settings_screen || $is_ai_screen) {
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

    wp_localize_script(
        'hr-sa-admin',
        'hrSaModules',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('hrsa_toggle_module'),
            'strings' => [
                'toggleError' => __('Unable to update the module. Please try again.', HR_SA_TEXT_DOMAIN),
                'enabledLabel' => __('Enabled', HR_SA_TEXT_DOMAIN),
                'disabledLabel' => __('Disabled', HR_SA_TEXT_DOMAIN),
            ],
        ]
    );

    wp_set_script_translations('hr-sa-admin', HR_SA_TEXT_DOMAIN);
}

/**
 * Build the JSON-LD preview selector data.
 *
 * @return array<int, array{id:int,label:string,type:string}>
 */
function hr_sa_jsonld_preview_build_targets(): array
{
    $targets = [
        [
            'id'    => 0,
            'label' => __('Home', HR_SA_TEXT_DOMAIN),
            'type'  => __('Front Page', HR_SA_TEXT_DOMAIN),
        ],
    ];

    $seen = [0 => true];

    $post_types = ['post', 'trip', 'page'];
    $recent     = get_posts([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'numberposts'    => 12,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'suppress_filters' => true,
    ]);

    foreach ($recent as $item) {
        if (!$item instanceof WP_Post) {
            continue;
        }

        $type_object = get_post_type_object($item->post_type);
        $type_label  = $type_object && isset($type_object->labels->singular_name)
            ? (string) $type_object->labels->singular_name
            : ucfirst((string) $item->post_type);

        if (isset($seen[$item->ID])) {
            continue;
        }

        $targets[] = [
            'id'    => (int) $item->ID,
            'label' => get_the_title($item),
            'type'  => $type_label,
        ];

        $seen[$item->ID] = true;
    }

    wp_reset_postdata();

    return $targets;
}
