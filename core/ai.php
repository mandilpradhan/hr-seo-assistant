<?php
/**
 * AI helper scaffolding and shared sanitizers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine whether AI features are enabled for the current admin request.
 */
function hr_sa_ai_is_enabled(): bool
{
    if (!is_admin()) {
        return false;
    }

    if (!current_user_can('manage_options')) {
        return false;
    }

    $settings = hr_sa_get_ai_settings();

    return !empty($settings['hr_sa_ai_enabled']);
}

/**
 * Retrieve AI settings, optionally including the raw API key.
 *
 * @return array{hr_sa_ai_enabled: bool, hr_sa_ai_api_key: string, hr_sa_ai_model: string, hr_sa_ai_temperature: float, hr_sa_ai_max_tokens: int, hr_sa_ai_has_key: bool}
 */
function hr_sa_ai_get_settings(bool $with_key = false): array
{
    $settings = hr_sa_get_ai_settings();
    $settings['hr_sa_ai_has_key'] = $settings['hr_sa_ai_api_key'] !== '';

    if (!$with_key) {
        $settings['hr_sa_ai_api_key'] = '';
    }

    return $settings;
}

/**
 * Retrieve the maximum character length for generated titles.
 */
function hr_sa_ai_get_title_limit(): int
{
    $limit = (int) apply_filters('hr_sa_ai_title_limit', 70);

    return $limit > 0 ? $limit : 70;
}

/**
 * Retrieve the maximum character length for generated descriptions.
 */
function hr_sa_ai_get_description_limit(): int
{
    $limit = (int) apply_filters('hr_sa_ai_description_limit', 160);

    return $limit > 0 ? $limit : 160;
}

/**
 * Normalize text content by stripping tags, collapsing whitespace, and optionally truncating.
 */
function hr_sa_ai_normalize_text(?string $text, int $limit = 0): string
{
    $text = is_string($text) ? $text : '';
    $text = wp_strip_all_tags($text, true);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    if ($limit > 0 && $text !== '') {
        $text = hr_sa_ai_truncate($text, $limit);
    }

    return $text;
}

/**
 * Trim text to the supplied character limit while preserving multibyte characters.
 */
function hr_sa_ai_truncate(string $value, int $limit): string
{
    if ($limit <= 0) {
        return $value;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) > $limit) {
            return rtrim(mb_substr($value, 0, $limit));
        }

        return $value;
    }

    if (strlen($value) > $limit) {
        return rtrim(substr($value, 0, $limit));
    }

    return $value;
}

/**
 * Sanitize a stored AI meta value.
 */
function hr_sa_ai_sanitize_meta_value($value, string $field): string
{
    $value = is_string($value) ? $value : '';
    $value = hr_sa_ai_normalize_text($value);

    switch ($field) {
        case 'title':
            $value = hr_sa_ai_truncate($value, hr_sa_ai_get_title_limit());
            break;
        case 'description':
            $value = hr_sa_ai_truncate($value, hr_sa_ai_get_description_limit());
            break;
        case 'keywords':
            $value = str_replace(';', ',', $value);
            $value = (string) preg_replace('/\s*,\s*/', ', ', $value);
            $value = hr_sa_ai_truncate($value, 512);
            break;
    }

    return $value;
}

/**
 * Sanitize the stored AI title meta value.
 */
function hr_sa_ai_sanitize_meta_title($value): string
{
    return hr_sa_ai_sanitize_meta_value($value, 'title');
}

/**
 * Sanitize the stored AI description meta value.
 */
function hr_sa_ai_sanitize_meta_description($value): string
{
    return hr_sa_ai_sanitize_meta_value($value, 'description');
}

/**
 * Sanitize the stored AI keywords meta value.
 */
function hr_sa_ai_sanitize_meta_keywords($value): string
{
    return hr_sa_ai_sanitize_meta_value($value, 'keywords');
}

/**
 * Prepare context data for a post prior to AI generation.
 *
 * @return array<string, mixed>
 */
function hr_sa_ai_prepare_post_context(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $title   = hr_sa_ai_normalize_text(get_the_title($post_id));
    $excerpt = (string) get_post_field('post_excerpt', $post_id);

    if ($excerpt === '') {
        $raw_excerpt = wp_trim_words(strip_shortcodes((string) get_post_field('post_content', $post_id)), 55, '');
        $excerpt     = hr_sa_ai_normalize_text($raw_excerpt, 320);
    } else {
        $excerpt = hr_sa_ai_normalize_text($excerpt, 320);
    }

    $content_raw = (string) get_post_field('post_content', $post_id);
    $content     = hr_sa_ai_normalize_text(strip_shortcodes($content_raw), 1200);
    $permalink   = get_permalink($post_id);

    $context = [
        'post_id'   => $post_id,
        'post_type' => $post->post_type,
        'title'     => $title,
        'excerpt'   => $excerpt,
        'content'   => $content,
        'permalink' => $permalink ? esc_url_raw($permalink) : '',
    ];

    if ($post->post_type === 'trip') {
        $trip_details = [
            'destinations' => '',
            'duration'     => '',
            'price_range'  => '',
        ];

        if (function_exists('hr_sa_resolve_trip_countries')) {
            $trip_details['destinations'] = hr_sa_ai_normalize_text(hr_sa_resolve_trip_countries($post_id));
        }

        $duration = '';
        if (function_exists('hr_trip_duration_days')) {
            $days = (int) hr_trip_duration_days($post_id);
            if ($days > 0) {
                /* translators: %d: number of days. */
                $duration = sprintf(_n('%d day', '%d days', $days, HR_SA_TEXT_DOMAIN), $days);
            }
        }

        if ($duration === '') {
            $duration_meta = get_post_meta($post_id, 'duration', true);
            if (is_string($duration_meta) && $duration_meta !== '') {
                $duration = hr_sa_ai_normalize_text($duration_meta, 120);
            }
        }

        if ($duration === '') {
            $duration_meta = get_post_meta($post_id, 'trip_duration', true);
            if (is_string($duration_meta) && $duration_meta !== '') {
                $duration = hr_sa_ai_normalize_text($duration_meta, 120);
            }
        }

        $product_nodes = function_exists('hr_sa_trip_build_product_nodes')
            ? hr_sa_trip_build_product_nodes($post_id)
            : [];
        $product = is_array($product_nodes) ? ($product_nodes[0] ?? []) : [];

        if ($duration === '' && isset($product['additionalProperty']) && is_array($product['additionalProperty'])) {
            foreach ($product['additionalProperty'] as $property) {
                if (!is_array($property)) {
                    continue;
                }
                $name  = isset($property['name']) ? strtolower((string) $property['name']) : '';
                $value = isset($property['value']) ? (string) $property['value'] : '';
                if ($name === 'duration' && $value !== '') {
                    $duration = hr_sa_ai_normalize_text($value, 120);
                    break;
                }
            }
        }

        $price_range = '';
        if (isset($product['offers']) && is_array($product['offers'])) {
            $offers   = $product['offers'];
            $currency = isset($offers['priceCurrency']) ? hr_sa_ai_normalize_text((string) $offers['priceCurrency'], 8) : '';
            $low      = isset($offers['lowPrice']) && is_numeric($offers['lowPrice']) ? (float) $offers['lowPrice'] : 0.0;
            $high     = isset($offers['highPrice']) && is_numeric($offers['highPrice']) ? (float) $offers['highPrice'] : 0.0;

            if ($low > 0 && $high > 0) {
                $formatted_low  = number_format_i18n($low);
                $formatted_high = number_format_i18n($high);
                if ($high > $low) {
                    $price_range = trim(sprintf('%s %s – %s', $currency, $formatted_low, $formatted_high));
                } else {
                    $price_range = trim(sprintf('%s %s', $currency, $formatted_high));
                }
            } elseif ($low > 0) {
                $price_range = trim(sprintf('%s %s', $currency, number_format_i18n($low)));
            }
        }

        if ($price_range === '') {
            $price_meta = get_post_meta($post_id, 'price', true);
            if (is_string($price_meta) && $price_meta !== '') {
                $price_range = hr_sa_ai_normalize_text($price_meta, 120);
            }
        }

        $trip_details['duration']    = $duration;
        $trip_details['price_range'] = $price_range;

        $context['trip'] = $trip_details;
    }

    /**
     * Filter the prepared AI context prior to generation.
     *
     * @param array<string, mixed> $context
     * @param int                  $post_id
     */
    return apply_filters('hr_sa_ai_prepare_post_context', $context, $post_id);
}

/**
 * Build the contextual lines included in AI prompts.
 *
 * @param array<string, mixed> $context
 *
 * @return array<int, string>
 */
function hr_sa_ai_build_context_lines(array $context): array
{
    $lines = [];

    $site_name = hr_sa_ai_normalize_text((string) hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name')), 120);
    if ($site_name !== '') {
        $lines[] = sprintf('Site name: %s', $site_name);
    }

    $locale = hr_sa_ai_normalize_text((string) hr_sa_get_setting('hr_sa_locale', 'en_US'), 32);
    if ($locale !== '') {
        $lines[] = sprintf('Locale: %s', $locale);
    }

    if (!empty($context['post_type'])) {
        $lines[] = sprintf('Post type: %s', hr_sa_ai_normalize_text((string) $context['post_type'], 60));
    }

    if (!empty($context['title'])) {
        $lines[] = sprintf('Original title: %s', hr_sa_ai_normalize_text((string) $context['title'], 180));
    }

    if (!empty($context['excerpt'])) {
        $lines[] = sprintf('Summary: %s', hr_sa_ai_normalize_text((string) $context['excerpt'], 320));
    }

    if (!empty($context['content'])) {
        $lines[] = sprintf('Content notes: %s', hr_sa_ai_normalize_text((string) $context['content'], 1200));
    }

    if (!empty($context['trip']) && is_array($context['trip'])) {
        $trip = $context['trip'];

        if (!empty($trip['destinations'])) {
            $lines[] = sprintf('Destinations: %s', hr_sa_ai_normalize_text((string) $trip['destinations'], 240));
        }

        if (!empty($trip['duration'])) {
            $lines[] = sprintf('Duration: %s', hr_sa_ai_normalize_text((string) $trip['duration'], 120));
        }

        if (!empty($trip['price_range'])) {
            $lines[] = sprintf('Price range: %s', hr_sa_ai_normalize_text((string) $trip['price_range'], 120));
        }
    }

    return array_values(array_filter(array_map('trim', $lines)));
}

/**
 * Build the chat completion messages for a given generation request.
 *
 * @param array<string, mixed> $context
 *
 * @return array<int, array<string, string>>|WP_Error
 */
function hr_sa_ai_build_messages(string $type_key, array $context)
{
    $type_key = $type_key !== '' ? $type_key : 'generic';

    $system_parts = [
        'You are an SEO assistant for a WordPress site. Provide concise, high quality metadata.',
        'Respond with plain text only. Do not include HTML, quotes, code blocks, or Markdown.',
    ];

    $locale = hr_sa_ai_normalize_text((string) hr_sa_get_setting('hr_sa_locale', 'en_US'), 32);
    if ($locale !== '') {
        $system_parts[] = sprintf('Write in %s.', $locale);
    }

    $system_message = implode(' ', array_filter($system_parts));

    $context_lines = hr_sa_ai_build_context_lines($context);
    $context_text  = implode("\n", $context_lines);

    $title_limit = hr_sa_ai_get_title_limit();
    $desc_limit  = hr_sa_ai_get_description_limit();

    switch ($type_key) {
        case 'title':
            $instruction = sprintf(
                'Generate an SEO title no longer than %d characters. Focus on clarity, destination, and trip highlights.',
                $title_limit
            );
            break;
        case 'description':
            $instruction = sprintf(
                'Generate an engaging SEO meta description no longer than %d characters. Summarize key details and include a call to action.',
                $desc_limit
            );
            break;
        case 'keywords':
            $instruction = 'Generate 6-10 SEO keywords separated by commas. Prioritize destinations, activities, and travel themes. Do not number the list.';
            break;
        case 'test_connection':
            $instruction = 'Respond with the single word PONG to confirm the service is reachable.';
            $context_text = '';
            break;
        default:
            if ($type_key === '') {
                $type_key = 'generic';
            }
            $instruction = 'Generate a concise SEO suggestion based on the provided context.';
            break;
    }

    if ($type_key !== 'test_connection' && $context_text === '') {
        $message = __('We could not prepare enough context for AI generation.', HR_SA_TEXT_DOMAIN);

        return new WP_Error('hr_sa_ai_missing_context', $message);
    }

    $user_message = $instruction;
    if ($context_text !== '') {
        $user_message .= "\n\nContext:\n" . $context_text;
    }

    $messages = [
        [
            'role'    => 'system',
            'content' => $system_message,
        ],
        [
            'role'    => 'user',
            'content' => $user_message,
        ],
    ];

    /**
     * Filter the chat completion messages before the request is dispatched.
     *
     * @param array<int, array<string, string>> $messages
     * @param string                            $type_key
     * @param array<string, mixed>              $context
     */
    return apply_filters('hr_sa_ai_request_messages', $messages, $type_key, $context);
}

/**
 * AI generation helper leveraging the OpenAI Chat Completions API.
 *
 * @param array<string, mixed> $context
 * @param array<string, mixed> $settings
 *
 * @return string|WP_Error
 */
function hr_sa_ai_generate(string $type, array $context, array $settings)
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return new WP_Error('hr_sa_ai_forbidden', __('You do not have permission to generate AI content.', HR_SA_TEXT_DOMAIN));
    }

    $type_key              = sanitize_key($type) ?: 'generic';
    $ai_settings           = $settings ?: hr_sa_ai_get_settings(true);
    $display_notice_errors = $type_key !== 'test_connection';

    if (empty($ai_settings['hr_sa_ai_enabled'])) {
        $message = __('AI assistance is currently disabled. Enable it in the settings page.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_disabled', $message);
    }

    if (empty($ai_settings['hr_sa_ai_api_key'])) {
        $message = __('Add an API key before generating AI content.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_missing_key', $message);
    }

    $messages = hr_sa_ai_build_messages($type_key, $context);
    if (is_wp_error($messages)) {
        $message = $messages->get_error_message();
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return $messages;
    }

    $model = isset($ai_settings['hr_sa_ai_model']) && $ai_settings['hr_sa_ai_model'] !== ''
        ? sanitize_text_field((string) $ai_settings['hr_sa_ai_model'])
        : (string) hr_sa_get_settings_defaults()['hr_sa_ai_model'];

    $temperature = isset($ai_settings['hr_sa_ai_temperature'])
        ? (float) $ai_settings['hr_sa_ai_temperature']
        : (float) hr_sa_get_settings_defaults()['hr_sa_ai_temperature'];
    $temperature = max(0.0, min(2.0, $temperature));
    if ($type_key === 'test_connection') {
        $temperature = 0.0;
    }

    $max_tokens = isset($ai_settings['hr_sa_ai_max_tokens'])
        ? (int) $ai_settings['hr_sa_ai_max_tokens']
        : (int) hr_sa_get_settings_defaults()['hr_sa_ai_max_tokens'];
    $max_tokens = max(16, min(4096, $max_tokens));

    switch ($type_key) {
        case 'title':
            $max_tokens = min($max_tokens, 128);
            break;
        case 'description':
            $max_tokens = min($max_tokens, 192);
            break;
        case 'keywords':
            $max_tokens = min($max_tokens, 256);
            break;
        case 'test_connection':
            $max_tokens = min($max_tokens, 32);
            break;
        default:
            $max_tokens = min($max_tokens, 256);
            break;
    }

    $request_body = [
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens,
    ];

    /**
     * Filter the request body before it is encoded to JSON.
     *
     * @param array<string, mixed> $request_body
     * @param string               $type_key
     * @param array<string, mixed> $context
     * @param array<string, mixed> $settings
     */
    $request_body = apply_filters('hr_sa_ai_request_body', $request_body, $type_key, $context, $ai_settings);

    $encoded_body = wp_json_encode($request_body);
    if ($encoded_body === false) {
        $message = __('We could not encode the AI request payload.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_payload_error', $message);
    }

    $endpoint = apply_filters('hr_sa_ai_endpoint', 'https://api.openai.com/v1/chat/completions', $type_key, $context, $ai_settings);

    $request_args = [
        'timeout'    => 30,
        'headers'    => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $ai_settings['hr_sa_ai_api_key'],
        ],
        'body'       => $encoded_body,
        'data_format'=> 'body',
        'user-agent' => 'HR SEO Assistant/' . HR_SA_VERSION,
    ];

    /**
     * Filter the HTTP request arguments before dispatching the AI call.
     *
     * @param array<string, mixed> $request_args
     * @param array<string, mixed> $request_body
     * @param string               $type_key
     * @param array<string, mixed> $context
     * @param array<string, mixed> $settings
     */
    $request_args = apply_filters('hr_sa_ai_request_args', $request_args, $request_body, $type_key, $context, $ai_settings);

    $response = wp_remote_post($endpoint, $request_args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $message       = $error_message !== ''
            ? sprintf(__('The AI request failed: %s', HR_SA_TEXT_DOMAIN), $error_message)
            : __('The AI request failed due to an unknown error.', HR_SA_TEXT_DOMAIN);

        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_http_error', $message, $response);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
        $api_message = '';
        if (is_array($data) && isset($data['error']['message'])) {
            $api_message = hr_sa_ai_normalize_text((string) $data['error']['message'], 240);
        }

        $message = $api_message !== ''
            ? sprintf(__('The AI service returned an error: %s', HR_SA_TEXT_DOMAIN), $api_message)
            : sprintf(__('The AI service returned an unexpected HTTP %d response.', HR_SA_TEXT_DOMAIN), $code);

        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_response_error', $message, $response);
    }

    if (!is_array($data)) {
        $message = __('The AI service returned an unreadable response.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_invalid_json', $message);
    }

    $content = '';
    if (isset($data['choices']) && is_array($data['choices']) && isset($data['choices'][0])) {
        $choice = $data['choices'][0];
        if (is_array($choice) && isset($choice['message']['content'])) {
            $content = (string) $choice['message']['content'];
        } elseif (is_array($choice) && isset($choice['text'])) {
            $content = (string) $choice['text'];
        }
    }

    if ($content === '') {
        $message = __('The AI service returned an empty response.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_empty_response', $message, $data);
    }

    $sanitized = hr_sa_ai_sanitize_meta_value($content, $type_key);
    if ($sanitized === '') {
        $message = __('The AI response could not be sanitized.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'type'           => $type_key,
            'status'         => 'error',
            'message'        => $message,
            'display_notice' => $display_notice_errors,
        ]);

        return new WP_Error('hr_sa_ai_sanitization_failed', $message, $data);
    }

    $summary_messages = [
        'title'           => __('Generated title suggestion.', HR_SA_TEXT_DOMAIN),
        'description'     => __('Generated description suggestion.', HR_SA_TEXT_DOMAIN),
        'keywords'        => __('Generated keyword suggestion.', HR_SA_TEXT_DOMAIN),
        'test_connection' => __('Connection successful.', HR_SA_TEXT_DOMAIN),
    ];

    $summary_message = $summary_messages[$type_key] ?? __('AI request completed.', HR_SA_TEXT_DOMAIN);

    hr_sa_ai_store_last_request_summary([
        'type'           => $type_key,
        'status'         => 'success',
        'message'        => $summary_message,
        'display_notice' => false,
    ]);

    if ($type_key === 'test_connection') {
        return $summary_message;
    }

    return $sanitized;
}

/**
 * Store a short summary of the last AI-related request.
 *
 * @param array<string, mixed> $summary
 */
function hr_sa_ai_store_last_request_summary(array $summary): void
{
    $normalized = hr_sa_ai_normalize_request_summary($summary);
    set_transient('hr_sa_ai_last', $normalized, DAY_IN_SECONDS);
}

/**
 * Retrieve the last stored AI request summary.
 *
 * @return array<string, mixed>|null
 */
function hr_sa_ai_get_last_request_summary(): ?array
{
    $summary = get_transient('hr_sa_ai_last');
    if (!is_array($summary) || !$summary) {
        return null;
    }

    return hr_sa_ai_normalize_request_summary($summary);
}

/**
 * Normalize the AI request summary payload.
 *
 * @param array<string, mixed> $summary
 *
 * @return array<string, mixed>
 */
function hr_sa_ai_normalize_request_summary(array $summary): array
{
    $defaults = [
        'timestamp'      => time(),
        'type'           => 'generic',
        'status'         => '',
        'message'        => '',
        'display_notice' => false,
    ];

    $summary = array_merge($defaults, array_intersect_key($summary, $defaults));

    $summary['timestamp'] = is_numeric($summary['timestamp']) ? (int) $summary['timestamp'] : time();
    if ($summary['timestamp'] <= 0) {
        $summary['timestamp'] = time();
    }

    $summary['type'] = $summary['type'] !== '' ? sanitize_key((string) $summary['type']) : 'generic';
    $summary['status'] = $summary['status'] !== '' ? sanitize_key((string) $summary['status']) : '';
    $summary['message'] = hr_sa_ai_normalize_text((string) $summary['message']);
    $summary['display_notice'] = (bool) $summary['display_notice'];

    return $summary;
}

add_action('admin_notices', 'hr_sa_ai_render_admin_notice');

/**
 * Render admin notices for AI errors captured via the transient log.
 */
function hr_sa_ai_render_admin_notice(): void
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $summary = hr_sa_ai_get_last_request_summary();
    if (!$summary || empty($summary['display_notice']) || ($summary['status'] ?? '') !== 'error') {
        return;
    }

    $message = isset($summary['message']) ? (string) $summary['message'] : '';
    if ($message === '') {
        return;
    }

    $type_label = $summary['type'] !== ''
        ? ucwords(str_replace('_', ' ', (string) $summary['type']))
        : __('AI request', HR_SA_TEXT_DOMAIN);
    $timestamp = isset($summary['timestamp']) ? (int) $summary['timestamp'] : 0;
    $time_label = $timestamp > 0 ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : '';

    echo '<div class="notice notice-error is-dismissible">';
    echo '<p>';
    if ($time_label !== '') {
        printf(
            /* translators: 1: request type, 2: error message, 3: timestamp. */
            esc_html__('%1$s: %2$s (%3$s)', HR_SA_TEXT_DOMAIN),
            esc_html($type_label),
            esc_html($message),
            esc_html($time_label)
        );
    } else {
        printf(
            /* translators: 1: request type, 2: error message. */
            esc_html__('%1$s: %2$s', HR_SA_TEXT_DOMAIN),
            esc_html($type_label),
            esc_html($message)
        );
    }
    echo '</p>';
    echo '</div>';

    $summary['display_notice'] = false;
    hr_sa_ai_store_last_request_summary($summary);
}
