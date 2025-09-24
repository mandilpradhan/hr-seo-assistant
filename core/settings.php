<?php
/**
 * Settings registration and helpers.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the default settings map.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_settings_defaults(): array
{
    return [
        'hr_sa_fallback_image'           => '',
        'hr_sa_tpl_trip'                 => '{{trip_name}} | Motorcycle Tour in {{country}}',
        'hr_sa_tpl_page'                 => '{{page_title}}',
        // TODO: Confirm whether the brand suffix should default to enabled or disabled.
        'hr_sa_tpl_page_brand_suffix'    => '1',
        'hr_sa_locale'                   => 'en_US',
        'hr_sa_site_name'                => get_bloginfo('name'),
        'hr_sa_twitter_handle'           => '@himalayanrides',
        'hr_sa_image_url_replace_enabled' => '0',
        'hr_sa_image_url_prefix_find'    => '',
        'hr_sa_image_url_prefix_replace' => '',
        'hr_sa_image_url_suffix_find'    => '',
        'hr_sa_image_url_suffix_replace' => '',
        'hr_sa_conflict_mode'            => 'respect',
        'hr_sa_admin_bar_badge_enabled'  => '0',
        'hr_sa_debug_enabled'            => '0',
        'hr_sa_ai_enabled'               => '0',
        'hr_sa_ai_instruction'           => '',
        'hr_sa_ai_api_key'               => '',
        'hr_sa_ai_model'                 => 'gpt-4o-mini',
        'hr_sa_ai_temperature'           => '0.7',
        'hr_sa_ai_max_tokens'            => '256',
    ];
}

/**
 * Provide a curated list of common locale choices for the settings UI.
 *
 * @return array<string, string> Map of locale code to human readable label.
 */
function hr_sa_get_locale_choices(): array
{
    return [
        'en_US' => __('English (United States)', HR_SA_TEXT_DOMAIN),
        'en_GB' => __('English (United Kingdom)', HR_SA_TEXT_DOMAIN),
        'es_ES' => __('Spanish (Spain)', HR_SA_TEXT_DOMAIN),
        'fr_FR' => __('French (France)', HR_SA_TEXT_DOMAIN),
        'de_DE' => __('German (Germany)', HR_SA_TEXT_DOMAIN),
        'hi_IN' => __('Hindi (India)', HR_SA_TEXT_DOMAIN),
        'ne_NP' => __('Nepali (Nepal)', HR_SA_TEXT_DOMAIN),
        'it_IT' => __('Italian (Italy)', HR_SA_TEXT_DOMAIN),
        'th_TH' => __('Thai (Thailand)', HR_SA_TEXT_DOMAIN),
        'zh_CN' => __('Chinese (Simplified)', HR_SA_TEXT_DOMAIN),
    ];
}

/**
 * Seed default settings if they do not exist.
 */
function hr_sa_settings_initialize_defaults(): void
{
    foreach (hr_sa_get_settings_defaults() as $option => $default) {
        if (get_option($option, null) === null) {
            add_option($option, $default);
        }
    }
}

add_action('admin_init', 'hr_sa_register_settings');

/**
 * Register plugin settings within WordPress.
 */
function hr_sa_register_settings(): void
{
    register_setting('hr_sa_settings', 'hr_sa_fallback_image', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_https_url',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_fallback_image'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_tpl_trip', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_template_string',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_tpl_trip'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_tpl_page', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_template_string',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_tpl_page'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_tpl_page_brand_suffix', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_tpl_page_brand_suffix'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_locale', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_locale',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_locale'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_site_name', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_site_name'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_twitter_handle', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_twitter_handle',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_twitter_handle'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_image_url_replace_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_url_replace_enabled'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_image_url_prefix_find', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_url_prefix_find'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_image_url_prefix_replace', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_url_prefix_replace'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_image_url_suffix_find', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_url_suffix_find'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_image_url_suffix_replace', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_url_suffix_replace'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_instruction', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_ai_instruction',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_instruction'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_ai_api_key',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_api_key'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_model', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_ai_model',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_model'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_temperature', [
        'type'              => 'number',
        'sanitize_callback' => 'hr_sa_sanitize_ai_temperature',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_temperature'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_max_tokens', [
        'type'              => 'integer',
        'sanitize_callback' => 'hr_sa_sanitize_ai_max_tokens',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_max_tokens'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_conflict_mode', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_conflict_mode',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_conflict_mode'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_admin_bar_badge_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_admin_bar_badge_enabled'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_debug_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_debug_enabled'],
    ]);
}

/**
 * Generic text sanitization that trims and strips tags.
 */
function hr_sa_sanitize_text($value): string
{
    $value = is_string($value) ? $value : '';
    return sanitize_text_field($value);
}

/**
 * Ensure template strings are stored without tags and extraneous whitespace.
 */
function hr_sa_sanitize_template_string($value): string
{
    $value = hr_sa_sanitize_text($value);
    return $value === '' ? '' : preg_replace('/\s+/u', ' ', $value);
}

/**
 * Sanitize checkbox input to the string values '1' or '0'.
 */
function hr_sa_sanitize_checkbox($value): string
{
    return !empty($value) && $value !== '0' ? '1' : '0';
}

/**
 * Ensure stored URL is an absolute HTTPS link.
 */
function hr_sa_sanitize_https_url($value): string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }

    $url = esc_url_raw($value);
    if (!$url) {
        add_settings_error('hr_sa_settings', 'hr_sa_invalid_url', __('Fallback image must be a valid URL.', HR_SA_TEXT_DOMAIN));
        return '';
    }

    $parts = wp_parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host']) || strtolower((string) $parts['scheme']) !== 'https') {
        add_settings_error('hr_sa_settings', 'hr_sa_invalid_scheme', __('Fallback image must use HTTPS.', HR_SA_TEXT_DOMAIN));
        return '';
    }

    return $url;
}

/**
 * Validate locale strings in xx_XX format.
 */
function hr_sa_sanitize_locale($value): string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return 'en_US';
    }

    if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $value)) {
        add_settings_error('hr_sa_settings', 'hr_sa_invalid_locale', __('Locale must match the pattern xx_XX.', HR_SA_TEXT_DOMAIN));
        return 'en_US';
    }

    return $value;
}

/**
 * Normalize Twitter handles to always include @.
 */
function hr_sa_sanitize_twitter_handle($value): string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '';
    }

    if ($value[0] !== '@') {
        $value = '@' . ltrim($value, '@');
    }

    $value = preg_replace('/[^A-Za-z0-9_@]/', '', $value);

    return $value ?: '';
}

/**
 * Sanitize the stored AI instruction text.
 */
function hr_sa_sanitize_ai_instruction($value): string
{
    $value = is_string($value) ? $value : '';
    $value = wp_strip_all_tags($value, true);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, get_bloginfo('charset') ?: 'UTF-8');
    $value = (string) preg_replace('/\s+/u', ' ', $value);

    return trim($value);
}

/**
 * Sanitize the stored AI API key, preserving the existing value when a mask is submitted.
 */
function hr_sa_sanitize_ai_api_key($value): string
{
    $value = is_string($value) ? trim($value) : '';

    if ($value === '') {
        return '';
    }

    if (preg_match('/^[•\*]+$/u', $value) === 1) {
        $existing = get_option('hr_sa_ai_api_key', '');

        return is_string($existing) ? (string) $existing : '';
    }

    $collapsed = preg_replace('/\s+/u', '', $value);

    return sanitize_text_field((string) $collapsed);
}

/**
 * Sanitize the configured AI model string.
 */
function hr_sa_sanitize_ai_model($value): string
{
    $value = is_string($value) ? $value : '';

    return sanitize_text_field($value);
}

/**
 * Clamp the temperature value between 0 and 2.
 */
function hr_sa_sanitize_ai_temperature($value): string
{
    $raw = is_string($value) ? str_replace(',', '.', $value) : $value;
    $number = is_numeric($raw) ? (float) $raw : (float) hr_sa_get_settings_defaults()['hr_sa_ai_temperature'];

    $number = max(0.0, min(2.0, $number));
    $number = round($number, 2);

    return (string) $number;
}

/**
 * Sanitize the maximum token count for AI responses.
 */
function hr_sa_sanitize_ai_max_tokens($value): string
{
    $number = absint($value);
    if ($number <= 0) {
        $number = (int) hr_sa_get_settings_defaults()['hr_sa_ai_max_tokens'];
    }

    $number = min($number, 4096);

    return (string) $number;
}

/**
 * Normalize the conflict mode string and mirror it into the feature flag option.
 */
function hr_sa_sanitize_conflict_mode($value): string
{
    $mode = strtolower(is_string($value) ? trim($value) : '');

    if ($mode === 'block_others') {
        $mode = 'block_og';
    }

    $allowed = ['respect', 'force', 'block_og'];
    $mode = in_array($mode, $allowed, true) ? $mode : 'respect';

    update_option('hr_sa_respect_other_seo', $mode === 'respect' ? '1' : '0');

    return $mode;
}

/**
 * Retrieve a plugin setting with fallback to defaults.
 *
 * @param string     $option
 * @param mixed|null $default
 *
 * @return mixed
 */
function hr_sa_get_setting(string $option, $default = null)
{
    $defaults = hr_sa_get_settings_defaults();
    $default = $default ?? ($defaults[$option] ?? '');
    $value = get_option($option, $default);

    if (in_array($option, ['hr_sa_tpl_page_brand_suffix', 'hr_sa_admin_bar_badge_enabled', 'hr_sa_debug_enabled', 'hr_sa_og_enabled', 'hr_sa_image_url_replace_enabled', 'hr_sa_ai_enabled'], true)) {
        return $value === '1' || $value === 1 || $value === true;
    }

    return $value;
}

/**
 * Retrieve the AI-related settings with normalized types.
 *
 * @return array{hr_sa_ai_enabled: bool, hr_sa_ai_instruction: string, hr_sa_ai_api_key: string, hr_sa_ai_model: string, hr_sa_ai_temperature: float, hr_sa_ai_max_tokens: int}
 */
function hr_sa_get_ai_settings(): array
{
    $defaults = hr_sa_get_settings_defaults();

    $enabled     = hr_sa_get_setting('hr_sa_ai_enabled', $defaults['hr_sa_ai_enabled']);
    $instruction = get_option('hr_sa_ai_instruction', $defaults['hr_sa_ai_instruction']);
    $api_key     = get_option('hr_sa_ai_api_key', $defaults['hr_sa_ai_api_key']);
    $model       = get_option('hr_sa_ai_model', $defaults['hr_sa_ai_model']);
    $temperature = get_option('hr_sa_ai_temperature', $defaults['hr_sa_ai_temperature']);
    $max_tokens  = get_option('hr_sa_ai_max_tokens', $defaults['hr_sa_ai_max_tokens']);

    $settings = [
        'hr_sa_ai_enabled'     => (bool) $enabled,
        'hr_sa_ai_instruction' => is_string($instruction) ? hr_sa_sanitize_ai_instruction($instruction) : '',
        'hr_sa_ai_api_key'     => is_string($api_key) ? trim($api_key) : '',
        'hr_sa_ai_model'       => is_string($model) ? sanitize_text_field($model) : (string) $defaults['hr_sa_ai_model'],
        'hr_sa_ai_temperature' => is_numeric($temperature) ? (float) $temperature : (float) $defaults['hr_sa_ai_temperature'],
        'hr_sa_ai_max_tokens'  => is_numeric($max_tokens) ? (int) $max_tokens : (int) $defaults['hr_sa_ai_max_tokens'],
    ];

    $settings['hr_sa_ai_temperature'] = max(0.0, min(2.0, $settings['hr_sa_ai_temperature']));
    $settings['hr_sa_ai_max_tokens']  = max(1, min(4096, $settings['hr_sa_ai_max_tokens']));

    return $settings;
}

/**
 * Provide a masked representation of the API key for display purposes.
 */
function hr_sa_mask_api_key_for_display(string $key): string
{
    return $key !== '' ? '••••' : '';
}

/**
 * Get all settings as an associative array.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_all_settings(): array
{
    $settings = [];
    foreach (hr_sa_get_settings_defaults() as $key => $default) {
        $settings[$key] = get_option($key, $default);
    }

    $settings['hr_sa_tpl_page_brand_suffix'] = hr_sa_get_setting('hr_sa_tpl_page_brand_suffix');
    $settings['hr_sa_admin_bar_badge_enabled'] = hr_sa_get_setting('hr_sa_admin_bar_badge_enabled');
    $settings['hr_sa_debug_enabled'] = hr_sa_get_setting('hr_sa_debug_enabled');
    $settings['hr_sa_og_enabled'] = hr_sa_is_flag_enabled('hr_sa_og_enabled', true);
    $settings['hr_sa_image_url_replace_enabled'] = hr_sa_get_setting('hr_sa_image_url_replace_enabled');

    $ai_settings = hr_sa_get_ai_settings();
    $settings['hr_sa_ai_enabled'] = $ai_settings['hr_sa_ai_enabled'];
    $settings['hr_sa_ai_instruction'] = $ai_settings['hr_sa_ai_instruction'];
    $settings['hr_sa_ai_model'] = $ai_settings['hr_sa_ai_model'];
    $settings['hr_sa_ai_temperature'] = $ai_settings['hr_sa_ai_temperature'];
    $settings['hr_sa_ai_max_tokens'] = $ai_settings['hr_sa_ai_max_tokens'];
    $settings['hr_sa_ai_api_key'] = hr_sa_mask_api_key_for_display($ai_settings['hr_sa_ai_api_key']);

    return $settings;
}
