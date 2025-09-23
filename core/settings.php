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
        'hr_sa_image_preset'             => 'w=1200,fit=cover,gravity=auto,format=auto,quality=75',
        'hr_sa_conflict_mode'            => 'respect',
        'hr_sa_debug_enabled'            => '0',
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

    register_setting('hr_sa_settings', 'hr_sa_image_preset', [
        'type'              => 'string',
        'sanitize_callback' => 'hr_sa_sanitize_text',
        'default'           => hr_sa_get_settings_defaults()['hr_sa_image_preset'],
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
 * Normalize the conflict mode string and mirror it into the feature flag option.
 */
function hr_sa_sanitize_conflict_mode($value): string
{
    $mode = strtolower(is_string($value) ? trim($value) : '');
    $mode = in_array($mode, ['respect', 'force'], true) ? $mode : 'respect';

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

    if (in_array($option, ['hr_sa_tpl_page_brand_suffix', 'hr_sa_debug_enabled'], true)) {
        return $value === '1' || $value === 1 || $value === true;
    }

    return $value;
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
    $settings['hr_sa_debug_enabled'] = hr_sa_get_setting('hr_sa_debug_enabled');

    return $settings;
}

/**
 * Add a success notice when settings are updated.
 */
function hr_sa_settings_admin_notices(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
    if ($page !== 'hr-sa-settings') {
        return;
    }

    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        add_settings_error('hr_sa_settings', 'hr_sa_settings_saved', __('Settings saved.', HR_SA_TEXT_DOMAIN), 'updated');
    }

    settings_errors('hr_sa_settings');
}
add_action('admin_notices', 'hr_sa_settings_admin_notices');

/**
 * Retrieve the configured image preset value with filter support.
 */
function hr_sa_get_image_preset(): string
{
    $preset = (string) get_option('hr_sa_image_preset', hr_sa_get_settings_defaults()['hr_sa_image_preset']);
    if ($preset === '') {
        $preset = hr_sa_get_settings_defaults()['hr_sa_image_preset'];
    }

    return (string) apply_filters('hr_sa_image_preset', $preset);
}
