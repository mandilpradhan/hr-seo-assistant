<?php
/**
 * AJAX handlers for AI scaffolding.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_hr_sa_ai_test_connection', 'hr_sa_ajax_ai_test_connection');
add_action('wp_ajax_hr_sa_generate_title', 'hr_sa_ajax_ai_generate_title');
add_action('wp_ajax_hr_sa_generate_description', 'hr_sa_ajax_ai_generate_description');
add_action('wp_ajax_hr_sa_generate_keywords', 'hr_sa_ajax_ai_generate_keywords');

/**
 * Handle the "Test Connection" button from the settings screen.
 */
function hr_sa_ajax_ai_test_connection(): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', HR_SA_TEXT_DOMAIN)], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'hr_sa_ai_test')) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh and try again.', HR_SA_TEXT_DOMAIN)], 400);
    }

    $settings = hr_sa_ai_get_settings(true);

    if (empty($settings['hr_sa_ai_enabled'])) {
        wp_send_json_error(['message' => __('AI assistance is currently disabled.', HR_SA_TEXT_DOMAIN)], 200);
    }

    $api_key = isset($settings['hr_sa_ai_api_key']) ? trim((string) $settings['hr_sa_ai_api_key']) : '';
    if ($api_key === '') {
        wp_send_json_error(['message' => __('Add an API key before testing the connection.', HR_SA_TEXT_DOMAIN)], 200);
    }

    $settings['hr_sa_ai_max_tokens'] = 8;

    $context = [
        'post_id'       => 0,
        'post_type'     => 'test',
        'title'         => 'Connectivity check',
        'excerpt'       => '',
        'content'       => '',
        'canonical_url' => '',
        'site_name'     => '',
    ];

    $result = hr_sa_ai_generate('title', $context, $settings);

    if (is_wp_error($result)) {
        $message = $result->get_error_message();
        wp_send_json_error(['message' => $message], 200);
    }

    wp_send_json_success(['message' => __('OpenAI reachable', HR_SA_TEXT_DOMAIN)]);
}

/**
 * AJAX endpoint to generate a suggested title.
 */
function hr_sa_ajax_ai_generate_title(): void
{
    hr_sa_ajax_ai_handle_generation('title');
}

/**
 * AJAX endpoint to generate a suggested description.
 */
function hr_sa_ajax_ai_generate_description(): void
{
    hr_sa_ajax_ai_handle_generation('description');
}

/**
 * AJAX endpoint to generate suggested keywords.
 */
function hr_sa_ajax_ai_generate_keywords(): void
{
    hr_sa_ajax_ai_handle_generation('keywords');
}

/**
 * Shared handler for the AI generation endpoints.
 */
function hr_sa_ajax_ai_handle_generation(string $type): void
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', HR_SA_TEXT_DOMAIN)], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'hr_sa_ai_meta_box')) {
        wp_send_json_error(['message' => __('Security check failed. Please refresh and try again.', HR_SA_TEXT_DOMAIN)], 400);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id <= 0) {
        $message = __('Save the post before requesting AI suggestions.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => false,
        ]);
        wp_send_json_error(['message' => $message], 200);
    }

    $context = hr_sa_ai_prepare_post_context($post_id);
    if (!$context) {
        $message = __('We could not load post details for AI suggestions.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => false,
        ]);
        wp_send_json_error(['message' => $message], 200);
    }

    $settings = hr_sa_ai_get_settings(true);
    $result   = hr_sa_ai_generate($type, $context, $settings);

    if (is_wp_error($result)) {
        $message = $result->get_error_message();
        wp_send_json_error(['message' => $message], 200);
    }

    $sanitized = hr_sa_ai_sanitize_meta_value((string) $result, $type);
    if ($sanitized === '') {
        $message = __('The AI response was empty. Please try again.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => false,
        ]);
        wp_send_json_error(['message' => $message], 200);
    }

    wp_send_json_success([
        'value' => $sanitized,
    ]);
}
