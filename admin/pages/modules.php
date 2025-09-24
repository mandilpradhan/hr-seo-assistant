<?php
/**
 * Admin modules page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reset module toggles to their default values.
 */
function hr_sa_modules_reset_to_defaults(): void
{
    update_option('hr_sa_jsonld_enabled', '1');
    update_option('hr_sa_og_enabled', '1');
    update_option('hr_sa_ai_enabled', '0');
    update_option('hr_sa_debug_enabled', '0');
}

/**
 * Render the modules management page.
 */
function hr_sa_render_modules_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $conflict_mode = hr_sa_get_conflict_mode();
    $other_seo     = hr_sa_other_seo_active();
    $og_locked     = ($conflict_mode === 'respect' && $other_seo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('hr_sa_modules_update', 'hr_sa_modules_nonce');

        if (isset($_POST['hr_sa_modules_reset'])) {
            hr_sa_modules_reset_to_defaults();
            add_settings_error('hr_sa_modules', 'hr_sa_modules_reset', __('Modules reset to defaults.', HR_SA_TEXT_DOMAIN), 'updated');
        } else {
            $jsonld_enabled = !empty($_POST['hr_sa_jsonld_enabled']) ? '1' : '0';
            $og_enabled     = !empty($_POST['hr_sa_og_enabled']) ? '1' : '0';
            $ai_enabled     = !empty($_POST['hr_sa_ai_enabled']) ? '1' : '0';
            $debug_enabled  = !empty($_POST['hr_sa_debug_enabled']) ? '1' : '0';

            update_option('hr_sa_jsonld_enabled', $jsonld_enabled);
            update_option('hr_sa_ai_enabled', $ai_enabled);
            update_option('hr_sa_debug_enabled', $debug_enabled);

            if (!$og_locked) {
                update_option('hr_sa_og_enabled', $og_enabled);
            }

            add_settings_error('hr_sa_modules', 'hr_sa_modules_saved', __('Modules updated.', HR_SA_TEXT_DOMAIN), 'updated');
        }

        // Re-evaluate lock state after updates.
        $conflict_mode = hr_sa_get_conflict_mode();
        $other_seo     = hr_sa_other_seo_active();
        $og_locked     = ($conflict_mode === 'respect' && $other_seo);
    }

    $modules = [
        [
            'id'          => 'hr_sa_jsonld_enabled',
            'name'        => __('JSON-LD Emitters', HR_SA_TEXT_DOMAIN),
            'description' => __('Outputs Schema.org JSON-LD for trips, FAQs, itinerary, organization, and vehicles.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_jsonld_enabled(),
            'locked'      => false,
            'tooltip'     => '',
        ],
        [
            'id'          => 'hr_sa_og_enabled',
            'name'        => __('Open Graph & Twitter Cards', HR_SA_TEXT_DOMAIN),
            'description' => __('Emits social meta tags for sharing (title, description, image).', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_og_enabled(),
            'locked'      => $og_locked,
            'tooltip'     => $og_locked
                ? __('Disabled by Conflict Mode (Respect). Another SEO plugin is active and may already output OG/Twitter tags. To override, change Conflict Mode to “Force” in Settings.', HR_SA_TEXT_DOMAIN)
                : '',
        ],
        [
            'id'          => 'hr_sa_ai_enabled',
            'name'        => __('AI Assist', HR_SA_TEXT_DOMAIN),
            'description' => __('Admin-only tools to suggest SEO titles, descriptions, and keywords using OpenAI.', HR_SA_TEXT_DOMAIN),
            'enabled'     => (bool) hr_sa_get_setting('hr_sa_ai_enabled'),
            'locked'      => false,
            'tooltip'     => '',
        ],
        [
            'id'          => 'hr_sa_debug_enabled',
            'name'        => __('Debug Mode', HR_SA_TEXT_DOMAIN),
            'description' => __('Shows a debug page with current context, sources, and module status.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_debug_enabled(),
            'locked'      => false,
            'tooltip'     => '',
        ],
    ];

    ?>
    <div class="wrap hr-sa-wrap hr-sa-modules-wrap">
        <h1><?php esc_html_e('HR SEO Modules', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description"><?php esc_html_e('Enable or disable HR SEO Assistant modules. Changes apply immediately after saving.', HR_SA_TEXT_DOMAIN); ?></p>

        <?php settings_errors('hr_sa_modules'); ?>

        <form method="post" action="">
            <?php wp_nonce_field('hr_sa_modules_update', 'hr_sa_modules_nonce'); ?>
            <table class="widefat fixed striped hr-sa-modules-table" role="presentation">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Module', HR_SA_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php esc_html_e('Status', HR_SA_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php esc_html_e('Toggle', HR_SA_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module) :
                        $is_enabled = !empty($module['enabled']);
                        $chip_class = $is_enabled ? 'hr-sa-chip--success' : 'hr-sa-chip--neutral';
                        $chip_label = $is_enabled ? __('Enabled', HR_SA_TEXT_DOMAIN) : __('Disabled', HR_SA_TEXT_DOMAIN);

                        if (!empty($module['locked'])) {
                            $chip_class = 'hr-sa-chip--warning';
                            $chip_label = __('Disabled by Conflict Mode', HR_SA_TEXT_DOMAIN);
                        }
                        ?>
                        <tr class="hr-sa-module-row">
                            <th scope="row">
                                <span class="hr-sa-module-name"><?php echo esc_html($module['name']); ?></span>
                                <p class="description"><?php echo esc_html($module['description']); ?></p>
                            </th>
                            <td class="hr-sa-module-status">
                                <span class="hr-sa-chip <?php echo esc_attr($chip_class); ?>"<?php echo !empty($module['tooltip']) ? ' title="' . esc_attr($module['tooltip']) . '"' : ''; ?>><?php echo esc_html($chip_label); ?></span>
                            </td>
                            <td class="hr-sa-module-toggle">
                                <label class="hr-sa-switch">
                                    <input type="checkbox" name="<?php echo esc_attr($module['id']); ?>" value="1" <?php checked($is_enabled); ?> <?php disabled(!empty($module['locked'])); ?> />
                                    <span class="hr-sa-switch__slider" aria-hidden="true"></span>
                                    <span class="screen-reader-text"><?php printf(esc_html__('Toggle %s', HR_SA_TEXT_DOMAIN), esc_html($module['name'])); ?></span>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit hr-sa-modules-actions">
                <button type="submit" class="button button-primary"><?php esc_html_e('Save Modules', HR_SA_TEXT_DOMAIN); ?></button>
                <button type="submit" name="hr_sa_modules_reset" value="1" class="button"><?php esc_html_e('Reset to Defaults', HR_SA_TEXT_DOMAIN); ?></button>
            </p>
        </form>

        <p class="hr-sa-conflict-note">
            <?php
            if ($conflict_mode === 'respect') {
                if ($other_seo) {
                    esc_html_e('Conflict Mode is set to Respect and another SEO plugin is active, so Open Graph & Twitter Cards stay disabled unless you switch Conflict Mode to Force.', HR_SA_TEXT_DOMAIN);
                } else {
                    esc_html_e('Conflict Mode is set to Respect. HR SEO Assistant yields to other SEO plugins when detected.', HR_SA_TEXT_DOMAIN);
                }
            } else {
                esc_html_e('Conflict Mode is set to Force. HR SEO Assistant outputs Open Graph & Twitter Cards regardless of other SEO plugins.', HR_SA_TEXT_DOMAIN);
            }
            ?>
        </p>
    </div>
    <?php
}
