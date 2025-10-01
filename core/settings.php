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
        'hr_sa_hrdf_only_mode'        => '1',
        'hr_sa_og_enabled'            => '0',
        'hr_sa_twitter_enabled'       => '0',
        'hr_sa_conflict_mode'         => 'respect',
        'hr_sa_debug_enabled'         => '0',
        'hr_sa_ai_enabled'            => '0',
        'hr_sa_ai_api_key'            => '',
        'hr_sa_ai_model'              => 'gpt-4o-mini',
        'hr_sa_ai_temperature'        => '0.7',
        'hr_sa_ai_max_tokens'         => '256',
        'hr_sa_ai_global_instructions'=> '',
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
    register_setting('hr_sa_settings', 'hr_sa_hrdf_only_mode', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_hrdf_only_mode'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_og_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_og_enabled'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_twitter_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_twitter_enabled'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_ai_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_enabled'],
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

    register_setting('hr_sa_settings', 'hr_sa_ai_global_instructions', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_ai_global_instructions',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_ai_global_instructions'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_conflict_mode', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_conflict_mode',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_conflict_mode'],
    ]);

    register_setting('hr_sa_settings', 'hr_sa_debug_enabled', [
        'type'              => 'boolean',
        'sanitize_callback' => 'hr_sa_sanitize_checkbox',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_debug_enabled'],
    ]);
}

/**
 * Sanitize checkbox input to the string values '1' or '0'.
 */
function hr_sa_sanitize_checkbox($value): string
{
    return !empty($value) && $value !== '0' ? '1' : '0';
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
    $raw    = is_string($value) ? str_replace(',', '.', $value) : $value;
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
 * Sanitize the global AI instruction textarea input.
 */
function hr_sa_sanitize_ai_global_instructions($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = sanitize_textarea_field($value);

    return trim($value);
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
    $mode    = in_array($mode, $allowed, true) ? $mode : 'respect';

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
    $default  = $default ?? ($defaults[$option] ?? '');
    $value    = get_option($option, $default);

    if (in_array($option, ['hr_sa_hrdf_only_mode', 'hr_sa_debug_enabled', 'hr_sa_og_enabled', 'hr_sa_twitter_enabled', 'hr_sa_ai_enabled'], true)) {
        return $value === '1' || $value === 1 || $value === true;
    }

    return $value;
}

/**
 * Retrieve the AI-related settings with normalized types.
 *
 * @return array{
 *     hr_sa_ai_enabled: bool,
 *     hr_sa_ai_api_key: string,
 *     hr_sa_ai_model: string,
 *     hr_sa_ai_temperature: float,
 *     hr_sa_ai_max_tokens: int,
 *     hr_sa_ai_global_instructions: string
 * }
 */
function hr_sa_get_ai_settings(): array
{
    $defaults = hr_sa_get_settings_defaults();

    $enabled     = hr_sa_get_setting('hr_sa_ai_enabled', $defaults['hr_sa_ai_enabled']);
    $api_key     = get_option('hr_sa_ai_api_key', $defaults['hr_sa_ai_api_key']);
    $model       = get_option('hr_sa_ai_model', $defaults['hr_sa_ai_model']);
    $temperature = get_option('hr_sa_ai_temperature', $defaults['hr_sa_ai_temperature']);
    $max_tokens  = get_option('hr_sa_ai_max_tokens', $defaults['hr_sa_ai_max_tokens']);
    $global_instructions = get_option('hr_sa_ai_global_instructions', $defaults['hr_sa_ai_global_instructions']);

    $settings = [
        'hr_sa_ai_enabled'     => (bool) $enabled,
        'hr_sa_ai_api_key'     => is_string($api_key) ? trim($api_key) : '',
        'hr_sa_ai_model'       => is_string($model) ? sanitize_text_field($model) : (string) $defaults['hr_sa_ai_model'],
        'hr_sa_ai_temperature' => is_numeric($temperature) ? (float) $temperature : (float) $defaults['hr_sa_ai_temperature'],
        'hr_sa_ai_max_tokens'  => is_numeric($max_tokens) ? (int) $max_tokens : (int) $defaults['hr_sa_ai_max_tokens'],
        'hr_sa_ai_global_instructions' => is_string($global_instructions)
            ? hr_sa_sanitize_ai_global_instructions($global_instructions)
            : '',
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

    $settings['hr_sa_og_enabled']      = hr_sa_is_flag_enabled('hr_sa_og_enabled');
    $settings['hr_sa_twitter_enabled'] = hr_sa_is_flag_enabled('hr_sa_twitter_enabled');
    $settings['hr_sa_debug_enabled']   = hr_sa_get_setting('hr_sa_debug_enabled');
    $settings['hr_sa_hrdf_only_mode']  = hr_sa_get_setting('hr_sa_hrdf_only_mode');

    $ai_settings = hr_sa_get_ai_settings();
    $settings['hr_sa_ai_enabled']              = $ai_settings['hr_sa_ai_enabled'];
    $settings['hr_sa_ai_model']                = $ai_settings['hr_sa_ai_model'];
    $settings['hr_sa_ai_temperature']          = $ai_settings['hr_sa_ai_temperature'];
    $settings['hr_sa_ai_max_tokens']           = $ai_settings['hr_sa_ai_max_tokens'];
    $settings['hr_sa_ai_api_key']              = hr_sa_mask_api_key_for_display($ai_settings['hr_sa_ai_api_key']);
    $settings['hr_sa_ai_global_instructions']  = $ai_settings['hr_sa_ai_global_instructions'];

    return $settings;
}
