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
    $limit = (int) apply_filters('hr_sa_ai_title_limit', 65);

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
 * Retrieve the minimum number of keywords required from AI output.
 */
function hr_sa_ai_get_keywords_min(): int
{
    $min = (int) apply_filters('hr_sa_ai_keywords_min', 3);

    return $min > 0 ? $min : 3;
}

/**
 * Retrieve the maximum number of keywords allowed from AI output.
 */
function hr_sa_ai_get_keywords_max(): int
{
    $max = (int) apply_filters('hr_sa_ai_keywords_max', 8);
    $min = hr_sa_ai_get_keywords_min();

    if ($max < $min) {
        $max = $min;
    }

    return $max > 0 ? $max : $min;
}

/**
 * Sanitize a text snippet into plain UTF-8, removing markup and normalising whitespace.
 */
function hr_sa_ai_sanitize_plain(string $text): string
{
    $text = wp_check_invalid_utf8($text, true);
    $text = wp_strip_all_tags($text, true);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $replacements = [
        "\xE2\x80\x98" => "'",
        "\xE2\x80\x99" => "'",
        "\xE2\x80\x9C" => '"',
        "\xE2\x80\x9D" => '"',
        "\xE2\x80\x93" => '-',
        "\xE2\x80\x94" => '-',
        "\xE2\x80\xA6" => '...'
    ];

    $text = strtr($text, $replacements);
    $text = (string) preg_replace('/[`´‵‶‷]+/u', "'", $text);
    $text = (string) preg_replace('/"+/u', '"', $text);
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);

    return $text;
}

/**
 * Clamp text to a maximum length whilst preferring to cut on word boundaries.
 */
function hr_sa_ai_clamp_length(string $text, int $limit): string
{
    $text = trim($text);
    if ($limit <= 0) {
        return $text;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($length <= $limit) {
        return $text;
    }

    $slice = function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
    $last_space = false;
    if (function_exists('mb_strrpos')) {
        $last_space = mb_strrpos($slice, ' ');
    } else {
        $last_space = strrpos($slice, ' ');
    }

    if ($last_space !== false && $last_space > 0) {
        $slice = function_exists('mb_substr') ? mb_substr($slice, 0, $last_space) : substr($slice, 0, $last_space);
    }

    $slice = trim($slice);
    if ($slice === '') {
        $slice = function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
    }

    return trim($slice);
}

/**
 * Prepare a snippet by sanitising and optionally clamping to a limit.
 */
function hr_sa_ai_prepare_snippet($text, int $limit = 0): string
{
    $snippet = is_string($text) ? $text : '';
    $snippet = hr_sa_ai_sanitize_plain($snippet);

    if ($limit > 0) {
        $snippet = hr_sa_ai_clamp_length($snippet, $limit);
    }

    return $snippet;
}

/**
 * Sanitize a stored AI meta value.
 */
function hr_sa_ai_sanitize_meta_value($value, string $field): string
{
    $value = hr_sa_ai_prepare_snippet(is_string($value) ? $value : '');

    switch ($field) {
        case 'title':
            $value = hr_sa_ai_clamp_length($value, hr_sa_ai_get_title_limit());
            break;
        case 'description':
            $value = hr_sa_ai_clamp_length($value, hr_sa_ai_get_description_limit());
            break;
        case 'keywords':
            $value = str_replace(';', ',', $value);
            $value = (string) preg_replace('/\s*,\s*/', ', ', $value);
            $value = hr_sa_ai_clamp_length($value, 512);
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

    $title       = hr_sa_ai_prepare_snippet(get_the_title($post_id), 180);
    $excerpt_raw = (string) get_post_field('post_excerpt', $post_id);

    if ($excerpt_raw === '') {
        $excerpt_raw = wp_trim_words(strip_shortcodes((string) get_post_field('post_content', $post_id)), 55, '');
    }

    $excerpt = hr_sa_ai_prepare_snippet($excerpt_raw, 320);

    $content_raw = strip_shortcodes((string) get_post_field('post_content', $post_id));
    $content     = hr_sa_ai_prepare_snippet($content_raw, 1200);

    $permalink = get_permalink($post_id);
    $site_name = hr_sa_ai_prepare_snippet((string) hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name')), 120);

    $context = [
        'post_id'       => $post_id,
        'post_type'     => hr_sa_ai_prepare_snippet($post->post_type, 60),
        'title'         => $title,
        'excerpt'       => $excerpt,
        'content'       => $content,
        'canonical_url' => $permalink ? esc_url_raw($permalink) : '',
        'permalink'     => $permalink ? esc_url_raw($permalink) : '',
        'site_name'     => $site_name,
        'brand_name'    => $site_name,
    ];

    if ($post->post_type === 'trip') {
        $context['trip'] = hr_sa_ai_prepare_trip_context($post_id);
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
 * Prepare trip-specific context values for prompts.
 *
 * @return array<string, string>
 */
function hr_sa_ai_prepare_trip_context(int $post_id): array
{
    $trip_details = [
        'destinations' => '',
        'duration'     => '',
        'price_range'  => '',
        'highlights'   => '',
        'seasonality'  => '',
    ];

    if (function_exists('hr_sa_resolve_trip_countries')) {
        $trip_details['destinations'] = hr_sa_ai_prepare_snippet(hr_sa_resolve_trip_countries($post_id), 240);
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
            $duration = hr_sa_ai_prepare_snippet($duration_meta, 120);
        }
    }

    if ($duration === '') {
        $duration_meta = get_post_meta($post_id, 'trip_duration', true);
        if (is_string($duration_meta) && $duration_meta !== '') {
            $duration = hr_sa_ai_prepare_snippet($duration_meta, 120);
        }
    }

    if ($duration === '') {
        $duration_meta = get_post_meta($post_id, 'duration_days', true);
        if (is_string($duration_meta) && $duration_meta !== '') {
            $duration = hr_sa_ai_prepare_snippet($duration_meta, 120);
        }
    }

    $trip_details['duration'] = $duration !== '' ? $duration : '';

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
                $duration = hr_sa_ai_prepare_snippet($value, 120);
                break;
            }
        }
    }

    $price_range = '';
    if (isset($product['offers']) && is_array($product['offers'])) {
        $offers   = $product['offers'];
        $currency = isset($offers['priceCurrency']) ? hr_sa_ai_prepare_snippet((string) $offers['priceCurrency'], 8) : '';
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
            $price_range = hr_sa_ai_prepare_snippet($price_meta, 120);
        }
    }

    $trip_details['price_range'] = $price_range;

    $trip_details['highlights'] = hr_sa_ai_prepare_trip_highlights($post_id);
    $trip_details['seasonality'] = hr_sa_ai_prepare_trip_seasonality($post_id);

    return array_filter($trip_details, static function ($value) {
        return is_string($value) && $value !== '';
    });
}

/**
 * Resolve highlights for a trip if they exist in meta/ACF fields.
 */
function hr_sa_ai_prepare_trip_highlights(int $post_id): string
{
    $candidate_keys = ['trip_highlights', 'highlights', 'key_highlights', 'highlights_list'];

    foreach ($candidate_keys as $meta_key) {
        $value = null;
        if (function_exists('get_field')) {
            $value = get_field($meta_key, $post_id);
        }

        if ($value === null) {
            $value = get_post_meta($post_id, $meta_key, true);
        }

        if ($value === null || $value === '') {
            continue;
        }

        if (is_array($value)) {
            $value = array_filter(array_map('wp_strip_all_tags', $value));
            $value = implode(', ', $value);
        }

        $value = is_string($value) ? hr_sa_ai_prepare_snippet($value, 240) : '';
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Resolve seasonality/best time details for a trip.
 */
function hr_sa_ai_prepare_trip_seasonality(int $post_id): string
{
    $candidate_keys = ['seasons', 'seasonality', 'best_time', 'best_time_to_visit', 'trip_season'];

    foreach ($candidate_keys as $meta_key) {
        $value = null;
        if (function_exists('get_field')) {
            $value = get_field($meta_key, $post_id);
        }

        if ($value === null) {
            $value = get_post_meta($post_id, $meta_key, true);
        }

        if ($value === null || $value === '') {
            continue;
        }

        if (is_array($value)) {
            $value = array_filter(array_map('wp_strip_all_tags', $value));
            $value = implode(', ', $value);
        }

        $value = is_string($value) ? hr_sa_ai_prepare_snippet($value, 200) : '';
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/**
 * Build chat completion messages for a given generation request.
 *
 * @param array<string, mixed> $context
 *
 * @return array<int, array<string, string>>
 */
function hr_sa_ai_build_messages(string $type, array $context): array
{
    $title_limit       = hr_sa_ai_get_title_limit();
    $description_limit = hr_sa_ai_get_description_limit();

    $system_message = 'You are a senior SEO copy assistant for a Himalayan adventure travel website. Return clean plain text with no quotes, HTML, or Markdown.';

    switch ($type) {
        case 'title':
            $policy_message = sprintf(
                '≤ %d characters, succinct, compelling, include focus topic early, no brand name unless provided.',
                $title_limit
            );
            $user_message = hr_sa_ai_prompt_template_title($context, $title_limit);
            break;
        case 'description':
            $policy_message = sprintf(
                '≤ %d characters, persuasive, active voice, include unique value, no trailing ellipsis.',
                $description_limit
            );
            $user_message = hr_sa_ai_prompt_template_description($context, $description_limit);
            break;
        case 'keywords':
        default:
            $policy_message = '5–8 comma-separated keywords/phrases; no hashtags, no duplicates, no stop words.';
            $user_message   = hr_sa_ai_prompt_template_keywords($context);
            break;
    }

    $style_instruction = '';
    $ai_settings       = hr_sa_get_ai_settings();
    if (!empty($ai_settings['hr_sa_ai_instruction'])) {
        $style_instruction = hr_sa_ai_prepare_snippet((string) $ai_settings['hr_sa_ai_instruction'], 400);
    }

    $messages = [
        [
            'role'    => 'system',
            'content' => $system_message,
        ],
        [
            'role'    => 'system',
            'content' => $policy_message,
        ],
    ];

    if ($style_instruction !== '') {
        $messages[] = [
            'role'    => 'system',
            'content' => sprintf(__('Style guidance: %s', HR_SA_TEXT_DOMAIN), $style_instruction),
        ];
    }

    $messages[] = [
        'role'    => 'user',
        'content' => $user_message,
    ];

    $filter_name = 'hr_sa_ai_prompt_' . $type;
    $messages    = apply_filters($filter_name, $messages, $context);

    return array_values(array_filter(array_map(static function ($message) {
        if (!is_array($message) || empty($message['role']) || empty($message['content'])) {
            return null;
        }

        return [
            'role'    => (string) $message['role'],
            'content' => (string) $message['content'],
        ];
    }, $messages)));
}

/**
 * Prompt template for title generation.
 */
function hr_sa_ai_prompt_template_title(array $context, int $title_limit): string
{
    $post_type  = hr_sa_ai_prepare_snippet((string) ($context['post_type'] ?? 'post'), 60);
    $post_title = hr_sa_ai_prepare_snippet((string) ($context['title'] ?? ''), 200);
    $excerpt    = hr_sa_ai_prepare_snippet((string) ($context['excerpt'] ?? ''), 320);
    $content    = hr_sa_ai_prepare_snippet((string) ($context['content'] ?? ''), 400);
    $canonical  = hr_sa_ai_prepare_snippet((string) ($context['canonical_url'] ?? ''), 200);
    $site_name  = hr_sa_ai_prepare_snippet((string) ($context['site_name'] ?? ''), 120);

    $key_points = $content !== '' ? $content : $excerpt;

    $lines = [
        sprintf('Task: Write an SEO title for a %s.', $post_type !== '' ? $post_type : 'post'),
        sprintf('Constraints: <= %d characters; compelling; lead with the main topic; no quotes or emojis; neutral punctuation; no brand unless provided.', $title_limit),
        'Context:',
        '- Title: ' . ($post_title !== '' ? $post_title : 'N/A'),
    ];

    if ($excerpt !== '') {
        $lines[] = '- Excerpt: ' . $excerpt;
    }

    if ($key_points !== '') {
        $lines[] = '- Key points: ' . $key_points;
    }

    if ($content !== '' && $content !== $key_points) {
        $lines[] = '- Content summary: ' . $content;
    }

    $trip_context = hr_sa_ai_format_trip_context($context['trip'] ?? []);
    if ($trip_context !== '') {
        $lines[] = '- Trip data (if applicable): ' . $trip_context;
    }

    if ($site_name !== '') {
        $lines[] = '- Site/Brand: ' . $site_name;
    }

    if ($canonical !== '') {
        $lines[] = '- Canonical URL: ' . $canonical;
    }

    $lines[] = 'Output: plain text, single line.';

    return implode("\n", array_filter($lines));
}

/**
 * Prompt template for description generation.
 */
function hr_sa_ai_prompt_template_description(array $context, int $description_limit): string
{
    $post_type  = hr_sa_ai_prepare_snippet((string) ($context['post_type'] ?? 'post'), 60);
    $post_title = hr_sa_ai_prepare_snippet((string) ($context['title'] ?? ''), 200);
    $excerpt    = hr_sa_ai_prepare_snippet((string) ($context['excerpt'] ?? ''), 320);
    $content    = hr_sa_ai_prepare_snippet((string) ($context['content'] ?? ''), 600);
    $canonical  = hr_sa_ai_prepare_snippet((string) ($context['canonical_url'] ?? ''), 200);
    $site_name  = hr_sa_ai_prepare_snippet((string) ($context['site_name'] ?? ''), 120);

    $lines = [
        sprintf('Task: Write an SEO meta description for a %s.', $post_type !== '' ? $post_type : 'post'),
        sprintf('Constraints: 140–160 characters (<= %d); persuasive, active voice; highlight unique value; avoid generic fluff; no quotes or emojis.', $description_limit),
        'Context:',
        '- Title: ' . ($post_title !== '' ? $post_title : 'N/A'),
    ];

    if ($excerpt !== '') {
        $lines[] = '- Excerpt: ' . $excerpt;
    }

    if ($content !== '') {
        $lines[] = '- Summary: ' . $content;
    }

    $trip_context = hr_sa_ai_format_trip_context($context['trip'] ?? []);
    if ($trip_context !== '') {
        $lines[] = '- Trip data (if applicable): ' . $trip_context;
    }

    if ($site_name !== '') {
        $lines[] = '- Site/Brand: ' . $site_name;
    }

    if ($canonical !== '') {
        $lines[] = '- Canonical URL: ' . $canonical;
    }

    $lines[] = 'Output: plain text, single line (or two short sentences).';

    return implode("\n", array_filter($lines));
}

/**
 * Prompt template for keyword generation.
 */
function hr_sa_ai_prompt_template_keywords(array $context): string
{
    $post_type = hr_sa_ai_prepare_snippet((string) ($context['post_type'] ?? 'post'), 60);
    $title     = hr_sa_ai_prepare_snippet((string) ($context['title'] ?? ''), 200);
    $excerpt   = hr_sa_ai_prepare_snippet((string) ($context['excerpt'] ?? ''), 320);

    $parts = array_filter([
        $title,
        $excerpt,
        is_array($context['trip'] ?? null) && !empty($context['trip']['destinations']) ? hr_sa_ai_prepare_snippet((string) $context['trip']['destinations'], 200) : '',
    ]);

    $context_line = implode(' | ', $parts);

    $lines = [
        sprintf('Task: Provide SEO keywords for a %s.', $post_type !== '' ? $post_type : 'post'),
        'Constraints: 3-8 concise comma-separated keywords/phrases; no hashtags; no duplication; avoid stop-words.',
        'Context: ' . ($context_line !== '' ? $context_line : 'No additional context provided'),
        'Output: plain text, comma-separated.'
    ];

    return implode("\n", $lines);
}

/**
 * Format trip context for inclusion in prompt templates.
 *
 * @param array<string, string> $trip
 */
function hr_sa_ai_format_trip_context($trip): string
{
    if (!is_array($trip) || !$trip) {
        return '';
    }

    $parts = [];
    $labels = [
        'duration'    => 'duration',
        'destinations'=> 'destinations',
        'price_range' => 'price_range',
        'highlights'  => 'highlights',
        'seasonality' => 'seasonality',
    ];

    foreach ($labels as $key => $label) {
        if (empty($trip[$key])) {
            continue;
        }

        $value = hr_sa_ai_prepare_snippet((string) $trip[$key], 200);
        if ($value === '') {
            continue;
        }

        $parts[] = sprintf('%s=%s', $label, $value);
    }

    return implode(', ', $parts);
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

    $type = sanitize_key($type);

    if (!hr_sa_ai_is_enabled()) {
        $message = __('AI assistance is currently disabled. Enable it in the settings page.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'post_id'     => isset($context['post_id']) ? (int) $context['post_id'] : 0,
            'model'       => '',
            'temperature' => null,
            'max_tokens'  => null,
            'tokens_used' => null,
        ]);

        return new WP_Error('hr_sa_ai_disabled', $message);
    }

    $allowed_types = ['title', 'description', 'keywords'];
    if (!in_array($type, $allowed_types, true)) {
        return new WP_Error('hr_sa_ai_invalid_type', __('Unsupported AI generation type.', HR_SA_TEXT_DOMAIN));
    }

    $api_key = isset($settings['hr_sa_ai_api_key']) ? trim((string) $settings['hr_sa_ai_api_key']) : '';
    if ($api_key === '') {
        $message = __('Add an API key before generating AI content.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'post_id'     => isset($context['post_id']) ? (int) $context['post_id'] : 0,
            'model'       => '',
            'temperature' => null,
            'max_tokens'  => null,
            'tokens_used' => null,
        ]);

        return new WP_Error('hr_sa_ai_missing_key', $message);
    }

    $model = isset($settings['hr_sa_ai_model']) && $settings['hr_sa_ai_model'] !== ''
        ? sanitize_text_field((string) $settings['hr_sa_ai_model'])
        : 'gpt-4o-mini';

    $temperature = isset($settings['hr_sa_ai_temperature'])
        ? (float) $settings['hr_sa_ai_temperature']
        : 0.7;
    $temperature = max(0.0, min(2.0, $temperature));

    $max_tokens_setting = isset($settings['hr_sa_ai_max_tokens'])
        ? (int) $settings['hr_sa_ai_max_tokens']
        : 512;
    if ($max_tokens_setting <= 0) {
        $max_tokens_setting = 512;
    }

    $messages = hr_sa_ai_build_messages($type, $context);
    if (!$messages) {
        return new WP_Error('hr_sa_ai_invalid_prompt', __('We could not prepare enough context for AI generation.', HR_SA_TEXT_DOMAIN));
    }

    $endpoint = apply_filters('hr_sa_ai_http_endpoint', 'https://api.openai.com/v1/chat/completions', $type, $context, $settings);

    $payload = [
        'model'       => $model,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens_setting,
        'messages'    => $messages,
    ];

    $body = wp_json_encode($payload);
    if (!is_string($body) || $body === '') {
        return new WP_Error('hr_sa_ai_payload_error', __('Failed to encode AI request.', HR_SA_TEXT_DOMAIN));
    }

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => $body,
        'timeout' => 20,
    ]);

    $post_id = isset($context['post_id']) ? (int) $context['post_id'] : 0;

    if (is_wp_error($response)) {
        $message = $response->get_error_message();
        $message = $message !== '' ? $message : __('Unable to reach the AI service.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'model'       => $model,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens_setting,
            'post_id'     => $post_id,
            'tokens_used' => $tokens_used,
        ]);

        return new WP_Error('hr_sa_http_error', $message);
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);

    $data = json_decode($raw_body, true);
    if (!is_array($data)) {
        $message = __('The AI service returned an invalid response.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'model'       => $model,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens_setting,
            'post_id'     => $post_id,
            'tokens_used' => null,
        ]);

        return new WP_Error('hr_sa_ai_invalid_response', $message);
    }

    $tokens_used = isset($data['usage']['total_tokens']) ? (int) $data['usage']['total_tokens'] : null;

    if ($status < 200 || $status >= 300) {
        $error_message = '';
        if (isset($data['error']['message'])) {
            $error_message = hr_sa_ai_prepare_snippet((string) $data['error']['message'], 200);
        }

        $message = $error_message !== '' ? $error_message : __('The AI service responded with an error.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'model'       => $model,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens_setting,
            'post_id'     => $post_id,
            'tokens_used' => $tokens_used,
        ]);

        return new WP_Error('hr_sa_http_error', $message);
    }

    $choice = $data['choices'][0]['message']['content'] ?? '';
    if (!is_string($choice) || $choice === '') {
        $message = __('The AI response did not include any content.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'model'       => $model,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens_setting,
            'post_id'     => $post_id,
            'tokens_used' => null,
        ]);

        return new WP_Error('hr_sa_ai_empty', $message);
    }

    $output = hr_sa_ai_prepare_snippet($choice);

    $keyword_count = 0;

    switch ($type) {
        case 'title':
            $output = hr_sa_ai_clamp_length($output, hr_sa_ai_get_title_limit());
            $output = preg_replace('/[\p{Zs}\s]+/u', ' ', $output ?? '');
            $output = trim((string) $output);
            $output = (string) preg_replace('/([!?.,])\1+$/u', '$1', $output);
            break;
        case 'description':
            $output = hr_sa_ai_clamp_length($output, hr_sa_ai_get_description_limit());
            $output = preg_replace('/\s+/u', ' ', $output ?? '');
            $output = trim((string) $output);
            break;
        case 'keywords':
            $raw_items = array_map('trim', explode(',', $output));
            $unique_map = [];

            foreach ($raw_items as $raw_item) {
                $keyword = hr_sa_ai_prepare_snippet($raw_item, 120);
                if ($keyword === '') {
                    continue;
                }

                $lower = function_exists('mb_strtolower')
                    ? mb_strtolower($keyword, 'UTF-8')
                    : strtolower($keyword);

                if (isset($unique_map[$lower])) {
                    continue;
                }

                $unique_map[$lower] = $keyword;
            }

            $normalized = array_values($unique_map);

            $keywords_min = hr_sa_ai_get_keywords_min();
            $keywords_max = hr_sa_ai_get_keywords_max();

            if (count($normalized) < $keywords_min) {
                $message = __('The AI service did not return enough keywords. Please try again.', HR_SA_TEXT_DOMAIN);
                hr_sa_ai_store_last_request_summary([
                    'timestamp'   => time(),
                    'type'        => $type,
                    'status'      => 'error',
                    'message'     => $message,
                    'model'       => $model,
                    'temperature' => $temperature,
                    'max_tokens'  => $max_tokens_setting,
                    'post_id'     => $post_id,
                    'tokens_used' => $tokens_used,
                ]);

                return new WP_Error('hr_sa_ai_keywords_insufficient', $message);
            }

            if (count($normalized) > $keywords_max) {
                $normalized = array_slice($normalized, 0, $keywords_max);
            }

            $keyword_count = count($normalized);
            $output = implode(', ', $normalized);
            break;
    }

    if (!is_string($output) || $output === '') {
        $message = __('The AI response was empty after sanitization. Please try again.', HR_SA_TEXT_DOMAIN);
        hr_sa_ai_store_last_request_summary([
            'timestamp'   => time(),
            'type'        => $type,
            'status'      => 'error',
            'message'     => $message,
            'model'       => $model,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens_setting,
            'post_id'     => $post_id,
            'tokens_used' => $tokens_used,
        ]);

        return new WP_Error('hr_sa_ai_empty', $message);
    }

    $summary_message = '';
    switch ($type) {
        case 'title':
            $summary_message = sprintf(__('Generated title (%d characters).', HR_SA_TEXT_DOMAIN), function_exists('mb_strlen') ? mb_strlen($output) : strlen($output));
            break;
        case 'description':
            $summary_message = sprintf(__('Generated description (%d characters).', HR_SA_TEXT_DOMAIN), function_exists('mb_strlen') ? mb_strlen($output) : strlen($output));
            break;
        case 'keywords':
            $summary_message = sprintf(__('Generated keywords (%d items).', HR_SA_TEXT_DOMAIN), $keyword_count);
            break;
    }

    hr_sa_ai_store_last_request_summary([
        'timestamp'   => time(),
        'type'        => $type,
        'status'      => 'success',
        'message'     => $summary_message,
        'model'       => $model,
        'temperature' => $temperature,
        'max_tokens'  => $max_tokens_setting,
        'post_id'     => $post_id,
        'tokens_used' => $tokens_used,
    ]);

    return $output;
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
        'timestamp'   => time(),
        'type'        => 'generic',
        'status'      => '',
        'message'     => '',
        'display_notice' => false,
        'model'       => '',
        'temperature' => null,
        'max_tokens'  => null,
        'post_id'     => 0,
        'tokens_used' => null,
    ];

    $summary = array_merge($defaults, array_intersect_key($summary, $defaults));

    $summary['timestamp'] = is_numeric($summary['timestamp']) ? (int) $summary['timestamp'] : time();
    if ($summary['timestamp'] <= 0) {
        $summary['timestamp'] = time();
    }

    $summary['type'] = $summary['type'] !== '' ? sanitize_key((string) $summary['type']) : 'generic';
    $summary['status'] = $summary['status'] !== '' ? sanitize_key((string) $summary['status']) : '';
    $summary['message'] = hr_sa_ai_prepare_snippet((string) $summary['message'], 240);
    $summary['display_notice'] = (bool) $summary['display_notice'];
    $summary['model'] = $summary['model'] !== '' ? sanitize_text_field((string) $summary['model']) : '';
    $summary['temperature'] = $summary['temperature'] !== null ? (float) $summary['temperature'] : null;
    $summary['max_tokens'] = $summary['max_tokens'] !== null ? max(0, (int) $summary['max_tokens']) : null;
    $summary['post_id'] = (int) $summary['post_id'];
    $summary['tokens_used'] = $summary['tokens_used'] !== null ? max(0, (int) $summary['tokens_used']) : null;

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
