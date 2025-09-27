<?php
/**
 * Module toggle AJAX handlers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

use HRSA\Modules;

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_hrsa_toggle_module', 'hr_sa_ajax_toggle_module');

/**
 * Handle AJAX requests to toggle modules.
 */
function hr_sa_ajax_toggle_module(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error([
            'ok'      => false,
            'message' => esc_html__('You do not have permission to update modules.', HR_SA_TEXT_DOMAIN),
        ]);
    }

    check_ajax_referer('hrsa_toggle_module');

    $slug    = isset($_POST['module']) ? sanitize_key((string) wp_unslash($_POST['module'])) : '';
    $enabled = isset($_POST['enabled']) ? (bool) absint($_POST['enabled']) : false;

    if ($slug === '' || !Modules::get($slug)) {
        wp_send_json_error([
            'ok'      => false,
            'message' => esc_html__('Unknown module requested.', HR_SA_TEXT_DOMAIN),
        ]);
    }

    $result = $enabled ? Modules::enable($slug) : Modules::disable($slug);

    if (!$result) {
        wp_send_json_error([
            'ok'      => false,
            'message' => esc_html__('Unable to update module state. Please try again.', HR_SA_TEXT_DOMAIN),
        ]);
    }

    if ($enabled) {
        Modules::maybe_boot($slug, true);
    }

    wp_send_json_success([
        'ok'      => true,
        'enabled' => Modules::is_enabled($slug),
    ]);
}
