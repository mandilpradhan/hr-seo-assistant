<?php
/**
 * Admin settings page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the settings page.
 */
function hr_sa_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $fallback      = (string) hr_sa_get_setting('hr_sa_fallback_image', '');
    $og_enabled    = hr_sa_is_flag_enabled('hr_sa_og_enabled', false);
    $twitter_cards = hr_sa_is_flag_enabled('hr_sa_twitter_enabled', false);
    $tpl_trip      = (string) hr_sa_get_setting('hr_sa_tpl_trip');
    $tpl_page      = (string) hr_sa_get_setting('hr_sa_tpl_page');
    $brand_suffix  = (bool) hr_sa_get_setting('hr_sa_tpl_page_brand_suffix');
    $locale        = (string) hr_sa_get_setting('hr_sa_locale');
    $locale_choices = hr_sa_get_locale_choices();
    if ($locale !== '' && !array_key_exists($locale, $locale_choices)) {
        $locale_choices = [$locale => sprintf(__('Current (custom): %s', HR_SA_TEXT_DOMAIN), $locale)] + $locale_choices;
    }
    $site_name     = (string) hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name'));
    $twitter       = (string) hr_sa_get_setting('hr_sa_twitter_handle');
    $image_replace_enabled = (bool) hr_sa_get_setting('hr_sa_image_url_replace_enabled');
    $image_prefix_find     = (string) hr_sa_get_setting('hr_sa_image_url_prefix_find');
    $image_prefix_replace  = (string) hr_sa_get_setting('hr_sa_image_url_prefix_replace');
    $image_suffix_find     = (string) hr_sa_get_setting('hr_sa_image_url_suffix_find');
    $image_suffix_replace  = (string) hr_sa_get_setting('hr_sa_image_url_suffix_replace');
    $conflict_mode = hr_sa_get_conflict_mode();
    $debug_enabled = hr_sa_is_debug_enabled();
    $ai_settings   = hr_sa_get_ai_settings();
    $ai_enabled    = (bool) $ai_settings['hr_sa_ai_enabled'];
    $ai_model      = (string) $ai_settings['hr_sa_ai_model'];
    $ai_temperature = (float) $ai_settings['hr_sa_ai_temperature'];
    $ai_max_tokens = (int) $ai_settings['hr_sa_ai_max_tokens'];
    $ai_key_masked = hr_sa_mask_api_key_for_display($ai_settings['hr_sa_ai_api_key']);
    $ai_has_key    = $ai_settings['hr_sa_ai_api_key'] !== '';

    $updated_flag = isset($_GET['settings-updated'])
        ? sanitize_text_field(wp_unslash((string) $_GET['settings-updated']))
        : '';

    if ($updated_flag === 'true' || $updated_flag === '1') {
        add_settings_error('hr_sa_settings', 'hr_sa_settings_saved', __('Settings saved.', HR_SA_TEXT_DOMAIN), 'updated');
    } elseif ($updated_flag === 'false' || $updated_flag === '0') {
        add_settings_error(
            'hr_sa_settings',
            'hr_sa_settings_failed',
            __('Settings could not be saved. Please review the fields below and try again.', HR_SA_TEXT_DOMAIN),
            'error'
        );
    }
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('HR SEO Settings', HR_SA_TEXT_DOMAIN); ?></h1>
        <?php settings_errors('hr_sa_settings'); ?>
        <form method="post" action="options.php">
            <?php settings_fields('hr_sa_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="hr_sa_fallback_image"><?php esc_html_e('Fallback Image (Sitewide)', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <div class="hr-sa-media-field">
                                <input type="url" class="regular-text" id="hr_sa_fallback_image" name="hr_sa_fallback_image" value="<?php echo esc_attr($fallback); ?>" placeholder="https://" />
                                <button type="button" class="button hr-sa-media-picker" data-target="hr_sa_fallback_image"><?php esc_html_e('Choose Image', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Used when no hero image is provided. Must be an absolute HTTPS URL.', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Social Metadata', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Social Metadata Toggles', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_og_enabled">
                                    <input type="checkbox" id="hr_sa_og_enabled" name="hr_sa_og_enabled" value="1" <?php checked($og_enabled); ?> />
                                    <?php esc_html_e('Enable Open Graph tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label for="hr_sa_twitter_enabled">
                                    <input type="checkbox" id="hr_sa_twitter_enabled" name="hr_sa_twitter_enabled" value="1" <?php checked($twitter_cards); ?> />
                                    <?php esc_html_e('Enable Twitter Card tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Twitter Cards reuse Open Graph data and require a large hero image or fallback.', HR_SA_TEXT_DOMAIN); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Assistance', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('AI Assistance', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_ai_enabled">
                                    <input type="checkbox" id="hr_sa_ai_enabled" name="hr_sa_ai_enabled" value="1" <?php checked($ai_enabled); ?> />
                                    <?php esc_html_e('Enable AI assistance for administrators', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description hr-sa-ai-hint"><?php esc_html_e('Admin-only. No front-end API calls will ever occur.', HR_SA_TEXT_DOMAIN); ?></p>
                                <div class="hr-sa-ai-settings">
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_api_key"><?php esc_html_e('API Key', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="password" id="hr_sa_ai_api_key" name="hr_sa_ai_api_key" value="<?php echo esc_attr($ai_has_key ? $ai_key_masked : ''); ?>" class="regular-text" autocomplete="off" />
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_model"><?php esc_html_e('Model', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" id="hr_sa_ai_model" name="hr_sa_ai_model" value="<?php echo esc_attr($ai_model); ?>" class="regular-text" />
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_temperature"><?php esc_html_e('Temperature', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_temperature" name="hr_sa_ai_temperature" value="<?php echo esc_attr(number_format($ai_temperature, 2, '.', '')); ?>" step="0.1" min="0" max="2" />
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_max_tokens"><?php esc_html_e('Max Tokens', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_max_tokens" name="hr_sa_ai_max_tokens" value="<?php echo esc_attr((string) $ai_max_tokens); ?>" min="1" max="4096" />
                                    </div>
                                </div>
                                <button type="button" class="button hr-sa-ai-test"><?php esc_html_e('Test Connection', HR_SA_TEXT_DOMAIN); ?></button>
                                <span class="hr-sa-ai-test-result" data-hr-sa-ai-result></span>
                                <?php if ($ai_enabled && !$ai_has_key) : ?>
                                    <div class="notice notice-warning inline hr-sa-ai-warning">
                                        <p><?php esc_html_e('AI assistance is enabled, but no API key has been provided yet.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Title Templates', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Title Templates', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_tpl_trip"><?php esc_html_e('Trips', HR_SA_TEXT_DOMAIN); ?></label>
                                <input type="text" class="regular-text" id="hr_sa_tpl_trip" name="hr_sa_tpl_trip" value="<?php echo esc_attr($tpl_trip); ?>" />
                                <p class="description"><?php esc_html_e('Available tags: {{trip_name}}, {{country}}', HR_SA_TEXT_DOMAIN); ?></p>
                                <label for="hr_sa_tpl_page" class="hr-sa-template-label"><?php esc_html_e('Pages', HR_SA_TEXT_DOMAIN); ?></label>
                                <input type="text" class="regular-text" id="hr_sa_tpl_page" name="hr_sa_tpl_page" value="<?php echo esc_attr($tpl_page); ?>" />
                                <label for="hr_sa_tpl_page_brand_suffix">
                                    <input type="checkbox" id="hr_sa_tpl_page_brand_suffix" name="hr_sa_tpl_page_brand_suffix" value="1" <?php checked($brand_suffix); ?> />
                                    <?php esc_html_e('Append brand suffix', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_locale_selector"><?php esc_html_e('Locale', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <select id="hr_sa_locale_selector" class="hr-sa-locale-selector" name="hr_sa_locale">
                                <?php foreach ($locale_choices as $code => $label) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"<?php selected($locale, $code); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Choose the locale used for generated metadata (e.g., en_US).', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_site_name"><?php esc_html_e('Site Name', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_site_name" name="hr_sa_site_name" value="<?php echo esc_attr($site_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_twitter_handle"><?php esc_html_e('Twitter Handle', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_twitter_handle" name="hr_sa_twitter_handle" value="<?php echo esc_attr($twitter); ?>" class="regular-text" placeholder="@username" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Image URL Prefix/Suffix Replace', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Image URL Prefix and Suffix Replacement', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_image_url_replace_enabled">
                                    <input type="checkbox" id="hr_sa_image_url_replace_enabled" name="hr_sa_image_url_replace_enabled" value="1" <?php checked($image_replace_enabled); ?> />
                                    <?php esc_html_e('Enable prefix/suffix replacement', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Rewrite Open Graph image URLs using the rules below.', HR_SA_TEXT_DOMAIN); ?></p>
                                <div class="hr-sa-image-replace-grid">
                                    <div class="hr-sa-image-replace-field">
                                        <label for="hr_sa_image_url_prefix_find"><?php esc_html_e('Prefix Find', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_prefix_find" name="hr_sa_image_url_prefix_find" value="<?php echo esc_attr($image_prefix_find); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label for="hr_sa_image_url_prefix_replace"><?php esc_html_e('Prefix Replace', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_prefix_replace" name="hr_sa_image_url_prefix_replace" value="<?php echo esc_attr($image_prefix_replace); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label for="hr_sa_image_url_suffix_find"><?php esc_html_e('Suffix Find', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_suffix_find" name="hr_sa_image_url_suffix_find" value="<?php echo esc_attr($image_suffix_find); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label for="hr_sa_image_url_suffix_replace"><?php esc_html_e('Suffix Replace', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_suffix_replace" name="hr_sa_image_url_suffix_replace" value="<?php echo esc_attr($image_suffix_replace); ?>" />
                                    </div>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Conflict Mode', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Conflict Mode', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label>
                                    <input type="radio" name="hr_sa_conflict_mode" value="respect" <?php checked($conflict_mode, 'respect'); ?> />
                                    <?php esc_html_e('Respect other SEO plugins', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="radio" name="hr_sa_conflict_mode" value="force" <?php checked($conflict_mode, 'force'); ?> />
                                    <?php esc_html_e('Force HR SEO output', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="radio" name="hr_sa_conflict_mode" value="block_og" <?php checked($conflict_mode, 'block_og'); ?> />
                                    <?php esc_html_e('Block other OG insertions', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Respect mode will defer JSON-LD when another SEO plugin is detected.', HR_SA_TEXT_DOMAIN); ?></p>
                                <p class="description"><?php esc_html_e("Block mode removes other plugins' OG/Twitter meta so HR SEO Assistant is the single source of truth.", HR_SA_TEXT_DOMAIN); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_debug_enabled"><?php esc_html_e('Debug Mode', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="hr_sa_debug_enabled" name="hr_sa_debug_enabled" value="1" <?php checked($debug_enabled); ?> />
                                <?php esc_html_e('Enable debug tools and menu.', HR_SA_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
