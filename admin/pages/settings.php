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
 * Provide a reusable view model of settings values for module pages.
 *
 * @return array<string, mixed>
 */
function hr_sa_get_settings_view_model(): array
{
    $og_enabled    = hr_sa_is_flag_enabled('hr_sa_og_enabled', false);
    $twitter_cards = hr_sa_is_flag_enabled('hr_sa_twitter_enabled', false);
    $conflict_mode = hr_sa_get_conflict_mode();
    $debug_enabled = hr_sa_is_debug_enabled();
    $ai_settings   = hr_sa_get_ai_settings();
    $ai_enabled    = (bool) $ai_settings['hr_sa_ai_enabled'];
    $ai_model      = (string) $ai_settings['hr_sa_ai_model'];
    $ai_temperature = (float) $ai_settings['hr_sa_ai_temperature'];
    $ai_max_tokens = (int) $ai_settings['hr_sa_ai_max_tokens'];
    $ai_global_instructions = (string) $ai_settings['hr_sa_ai_global_instructions'];
    $ai_key_masked = hr_sa_mask_api_key_for_display($ai_settings['hr_sa_ai_api_key']);
    $ai_has_key    = $ai_settings['hr_sa_ai_api_key'] !== '';

    return [
        'og_enabled'             => $og_enabled,
        'twitter_cards'          => $twitter_cards,
        'hrdf_only'              => hr_sa_is_hrdf_only_mode(),
        'conflict_mode'          => $conflict_mode,
        'debug_enabled'          => $debug_enabled,
        'ai_enabled'             => $ai_enabled,
        'ai_model'               => $ai_model,
        'ai_temperature'         => $ai_temperature,
        'ai_max_tokens'          => $ai_max_tokens,
        'ai_global_instructions' => $ai_global_instructions,
        'ai_key_masked'          => $ai_key_masked,
        'ai_has_key'             => $ai_has_key,
    ];
}

/**
 * Render the settings page.
 */
function hr_sa_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $view          = hr_sa_get_settings_view_model();
    $og_enabled    = (bool) $view['og_enabled'];
    $twitter_cards = (bool) $view['twitter_cards'];
    $conflict_mode = (string) $view['conflict_mode'];
    $debug_enabled = (bool) $view['debug_enabled'];
    $ai_enabled    = (bool) $view['ai_enabled'];
    $ai_model      = (string) $view['ai_model'];
    $ai_temperature = (float) $view['ai_temperature'];
    $ai_max_tokens = (int) $view['ai_max_tokens'];
    $ai_global_instructions = (string) $view['ai_global_instructions'];
    $ai_key_masked = (string) $view['ai_key_masked'];
    $ai_has_key    = (bool) $view['ai_has_key'];
    $hrdf_locked   = defined('HR_SA_HRDF_ONLY');
    $hrdf_default  = $hrdf_locked ? (bool) HR_SA_HRDF_ONLY : (bool) $view['hrdf_only'];

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
                        <th scope="row"><?php esc_html_e('Data Source', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Data Source', HR_SA_TEXT_DOMAIN); ?></legend>
                                <?php if ($hrdf_locked) : ?>
                                    <input type="hidden" name="hr_sa_hrdf_only_mode" value="<?php echo esc_attr($hrdf_default ? '1' : '0'); ?>" />
                                <?php endif; ?>
                                <label for="hr_sa_hrdf_only_mode">
                                    <input type="checkbox" id="hr_sa_hrdf_only_mode" name="hr_sa_hrdf_only_mode" value="1" <?php checked($hrdf_default); ?> <?php disabled($hrdf_locked); ?> />
                                    <?php esc_html_e('Use HRDF-only mode (no legacy data sources)', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('When enabled, JSON-LD and Open Graph data is read directly from HRDF with minimal WordPress fallbacks.', HR_SA_TEXT_DOMAIN); ?></p>
                                <?php if ($hrdf_locked) : ?>
                                    <p class="description"><?php esc_html_e('This option is locked by the HR_SA_HRDF_ONLY constant.', HR_SA_TEXT_DOMAIN); ?></p>
                                <?php endif; ?>
                            </fieldset>
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
                                <p class="description"><?php esc_html_e('Twitter Cards reuse Open Graph values and require an HRDF hero image or fallback.', HR_SA_TEXT_DOMAIN); ?></p>
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
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_api_key"><?php esc_html_e('API Key', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="password" id="hr_sa_ai_api_key" name="hr_sa_ai_api_key" value="<?php echo esc_attr($ai_has_key ? $ai_key_masked : ''); ?>" class="regular-text" autocomplete="off" />
                                    </div>
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_global_instructions"><?php esc_html_e('Global AI Instructions', HR_SA_TEXT_DOMAIN); ?></label>
                                        <textarea id="hr_sa_ai_global_instructions" name="hr_sa_ai_global_instructions" rows="5" class="large-text code"><?php echo esc_textarea($ai_global_instructions); ?></textarea>
                                        <p class="description"><?php esc_html_e('Optional guidance sent with every AI request. Use this to enforce tone, disclaimers, or topics to avoid.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_model"><?php esc_html_e('Model', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" id="hr_sa_ai_model" name="hr_sa_ai_model" value="<?php echo esc_attr($ai_model); ?>" class="regular-text" />
                                        <p class="description"><?php esc_html_e('Provider-specific identifier (e.g., gpt-4o-mini). Choose a model that supports chat completions.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_temperature"><?php esc_html_e('Temperature', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_temperature" name="hr_sa_ai_temperature" value="<?php echo esc_attr(number_format($ai_temperature, 2, '.', '')); ?>" step="0.1" min="0" max="2" />
                                        <p class="description"><?php esc_html_e('Controls creativity. Lower values are conservative; higher values allow more varied suggestions.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_max_tokens"><?php esc_html_e('Max Tokens', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_max_tokens" name="hr_sa_ai_max_tokens" value="<?php echo esc_attr((string) $ai_max_tokens); ?>" min="1" max="4096" />
                                        <p class="description"><?php esc_html_e('Upper limit for each response. Lower values keep answers brief and reduce costs.', HR_SA_TEXT_DOMAIN); ?></p>
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
