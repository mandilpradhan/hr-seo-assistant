<?php
/**
 * AI Assist module admin page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use HRSA\Modules;

/**
 * Render the AI Assist module screen.
 */
function hr_sa_render_module_ai_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    if (!function_exists('hr_sa_get_settings_view_model')) {
        require_once HR_SA_PLUGIN_DIR . 'admin/pages/settings.php';
    }

    $view         = hr_sa_get_settings_view_model();
    $ai_settings  = hr_sa_get_ai_settings();
    $global_instructions = isset($ai_settings['hr_sa_ai_global_instructions'])
        ? (string) $ai_settings['hr_sa_ai_global_instructions']
        : '';
    $module_state = class_exists(Modules::class) ? Modules::is_enabled('ai-assist') : true;
    $overview_url = admin_url('admin.php?page=hr-sa-overview');
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('AI Assist', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description"><?php esc_html_e('Admin-only helpers for SEO titles, descriptions, and keywords.', HR_SA_TEXT_DOMAIN); ?></p>
        <?php if (!$module_state) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e('This module is disabled. Enable it from the HR SEO Overview to use AI assistance.', HR_SA_TEXT_DOMAIN); ?></p></div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields('hr_sa_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Assistance', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('AI Assistance', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_ai_enabled">
                                    <input type="checkbox" id="hr_sa_ai_enabled" name="hr_sa_ai_enabled" value="1" <?php checked($ai_settings['hr_sa_ai_enabled']); ?> />
                                    <?php esc_html_e('Enable AI assistance for administrators', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description hr-sa-ai-hint"><?php esc_html_e('Admin-only. No front-end API calls will ever occur.', HR_SA_TEXT_DOMAIN); ?></p>
                                <div class="hr-sa-ai-settings">
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_api_key"><?php esc_html_e('API Key', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="password" id="hr_sa_ai_api_key" name="hr_sa_ai_api_key" value="<?php echo esc_attr($view['ai_key_masked']); ?>" class="regular-text" autocomplete="off" />
                                    </div>
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_global_instructions"><?php esc_html_e('Global AI Instructions', HR_SA_TEXT_DOMAIN); ?></label>
                                        <textarea id="hr_sa_ai_global_instructions" name="hr_sa_ai_global_instructions" rows="5" class="large-text code"><?php echo esc_textarea($global_instructions); ?></textarea>
                                        <p class="description"><?php esc_html_e('Optional guidance sent with every AI request. Use this to enforce tone, disclaimers, or topics to avoid.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field hr-sa-ai-settings__field--full">
                                        <label for="hr_sa_ai_model"><?php esc_html_e('Model', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" id="hr_sa_ai_model" name="hr_sa_ai_model" value="<?php echo esc_attr($ai_settings['hr_sa_ai_model']); ?>" class="regular-text" />
                                        <p class="description"><?php esc_html_e('Provider-specific identifier (e.g., gpt-4o-mini). Choose a model that supports chat completions.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_temperature"><?php esc_html_e('Temperature', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_temperature" name="hr_sa_ai_temperature" value="<?php echo esc_attr(number_format($ai_settings['hr_sa_ai_temperature'], 2, '.', '')); ?>" step="0.1" min="0" max="2" />
                                        <p class="description"><?php esc_html_e('Controls creativity. Lower values are conservative; higher values allow more varied suggestions.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                    <div class="hr-sa-ai-settings__field">
                                        <label for="hr_sa_ai_max_tokens"><?php esc_html_e('Max Tokens', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="number" id="hr_sa_ai_max_tokens" name="hr_sa_ai_max_tokens" value="<?php echo esc_attr((string) $ai_settings['hr_sa_ai_max_tokens']); ?>" min="1" max="4096" />
                                        <p class="description"><?php esc_html_e('Upper limit for each response. Lower values keep answers brief and reduce costs.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                </div>
                                <button type="button" class="button hr-sa-ai-test"><?php esc_html_e('Test Connection', HR_SA_TEXT_DOMAIN); ?></button>
                                <span class="hr-sa-ai-test-result" data-hr-sa-ai-result></span>
                                <?php if ($ai_settings['hr_sa_ai_enabled'] && !$view['ai_has_key']) : ?>
                                    <div class="notice notice-warning inline hr-sa-ai-warning">
                                        <p><?php esc_html_e('AI assistance is enabled, but no API key has been provided yet.', HR_SA_TEXT_DOMAIN); ?></p>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(__('Save Changes', HR_SA_TEXT_DOMAIN)); ?>
        </form>
        <p>
            <a class="button" href="<?php echo esc_url($overview_url); ?>"><?php esc_html_e('Back to Overview', HR_SA_TEXT_DOMAIN); ?></a>
        </p>
    </div>
    <?php
}
