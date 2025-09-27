<?php
/**
 * AJAX handler for Open Graph preview requests.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_hr_sa_open_graph_preview', 'hr_sa_handle_open_graph_preview');

/**
 * Process the Open Graph preview request.
 */
function hr_sa_handle_open_graph_preview(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(
            ['message' => __('You do not have permission to run this preview.', HR_SA_TEXT_DOMAIN)],
            403
        );
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'hr_sa_og_preview')) {
        wp_send_json_error(
            ['message' => __('Your session has expired. Refresh the page and try again.', HR_SA_TEXT_DOMAIN)],
            400
        );
    }

    $target = isset($_POST['target']) ? absint((string) wp_unslash($_POST['target'])) : 0;

    $prepared = hr_sa_jsonld_preview_prepare_context($target);
    if (is_wp_error($prepared)) {
        wp_send_json_error(
            ['message' => $prepared->get_error_message()],
            400
        );
    }

    $restore            = $prepared['restore'];
    $original_snapshot  = $GLOBALS['hr_sa_last_social_snapshot'] ?? null;

    try {
        $GLOBALS['hr_sa_last_social_snapshot'] = null;
        $snapshot = hr_sa_collect_social_tag_data();
    } finally {
        $GLOBALS['hr_sa_last_social_snapshot'] = $original_snapshot;
        if (is_callable($restore)) {
            $restore();
        }
        wp_reset_postdata();
    }

    $fields       = array_map('hr_sa_og_preview_clean_value', $snapshot['fields']);
    $og_tags      = array_map('hr_sa_og_preview_clean_value', $snapshot['og']);
    $twitter_tags = array_map('hr_sa_og_preview_clean_value', $snapshot['twitter']);

    wp_send_json_success(
        [
            'og_enabled'      => (bool) $snapshot['og_enabled'],
            'twitter_enabled' => (bool) $snapshot['twitter_enabled'],
            'blocked'         => (bool) $snapshot['blocked'],
            'fields'          => $fields,
            'og'              => $og_tags,
            'twitter'         => $twitter_tags,
        ]
    );
}

/**
 * Normalize preview values for safe output.
 *
 * @param mixed $value Value to clean.
 */
function hr_sa_og_preview_clean_value($value): string
{
    if (!is_string($value)) {
        $value = '';
    }

    $value = wp_strip_all_tags($value, true);
    $value = html_entity_decode($value, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');

    return trim($value);
}
