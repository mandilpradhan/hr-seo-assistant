<?php
/**
 * Admin overview screen.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the overview page.
 */
function hr_sa_render_overview_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $modules    = HRSA\Modules::all();
    $legacy_url = admin_url('admin.php?page=hr-sa-settings');
    $version    = HR_SA_VERSION;
    ?>
    <div class="wrap hrui-wrap">
        <h1><?php esc_html_e('HR SEO Assistant', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description"><?php esc_html_e('Toggle modules and review their status. Legacy settings remain available for reference.', HR_SA_TEXT_DOMAIN); ?></p>
        <p><strong><?php echo esc_html(sprintf(__('Version %s', HR_SA_TEXT_DOMAIN), $version)); ?></strong></p>
        <div class="hrui-grid">
            <?php foreach ($modules as $module) :
                if (!is_array($module)) {
                    continue;
                }

                $slug        = (string) ($module['slug'] ?? '');
                $label       = (string) ($module['label'] ?? $slug);
                $description = (string) ($module['description'] ?? '');
                $enabled     = HRSA\Modules::is_enabled($slug);
                $badge_class = $enabled ? 'hrui-badge enabled' : 'hrui-badge disabled';
                $badge_text  = $enabled ? __('Enabled', HR_SA_TEXT_DOMAIN) : __('Disabled', HR_SA_TEXT_DOMAIN);
                $submenu     = 'hr-sa-module-' . sanitize_title($slug);
                $settings_url = admin_url('admin.php?page=' . $submenu);
                ?>
                <div class="hrui-card" data-module-card="<?php echo esc_attr($slug); ?>">
                    <div class="meta">
                        <h3><?php echo esc_html($label); ?></h3>
                        <p><?php echo esc_html($description); ?></p>
                        <div class="hrui-badges">
                            <span class="<?php echo esc_attr($badge_class); ?>" data-status-badge>
                                <span class="screen-reader-text"><?php esc_html_e('Status:', HR_SA_TEXT_DOMAIN); ?></span>
                                <?php echo esc_html($badge_text); ?>
                            </span>
                        </div>
                    </div>
                    <div class="hrui-actions">
                        <label class="hrui-toggle">
                            <span class="screen-reader-text"><?php echo esc_html(sprintf(__('Toggle %s module', HR_SA_TEXT_DOMAIN), $label)); ?></span>
                            <input type="checkbox" data-module-toggle data-slug="<?php echo esc_attr($slug); ?>" <?php checked($enabled); ?> />
                        </label>
                        <a class="hrui-settings-link" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Settings', HR_SA_TEXT_DOMAIN); ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <a href="<?php echo esc_url($legacy_url); ?>"><?php esc_html_e('Open Legacy Settings', HR_SA_TEXT_DOMAIN); ?></a>
        </p>
    </div>
    <?php
}
