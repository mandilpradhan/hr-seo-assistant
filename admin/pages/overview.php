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

    $version    = HR_SA_VERSION;
    $settings   = admin_url('admin.php?page=hr-sa-settings');
    $modules    = admin_url('admin.php?page=hr-sa-modules');
    $debug_link = admin_url('admin.php?page=hr-sa-debug');
    $debug_on   = hr_sa_is_debug_enabled();
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php echo esc_html__('HR SEO Assistant', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="hr-sa-intro">
            <?php esc_html_e('Centralized controls for Himalayan Rides SEO metadata, structured data, and diagnostics.', HR_SA_TEXT_DOMAIN); ?>
        </p>
        <div class="hr-sa-card-grid">
            <div class="hr-sa-card">
                <h2><?php esc_html_e('Plugin Status', HR_SA_TEXT_DOMAIN); ?></h2>
                <p><?php echo esc_html(sprintf(__('Version %s', HR_SA_TEXT_DOMAIN), $version)); ?></p>
                <ul>
                    <li><?php echo esc_html__('JSON-LD Emitters', HR_SA_TEXT_DOMAIN) . ': ' . (hr_sa_is_jsonld_enabled() ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN)); ?></li>
                    <li><?php echo esc_html__('Open Graph Tags', HR_SA_TEXT_DOMAIN) . ': ' . (hr_sa_is_og_enabled() ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN)); ?></li>
                    <li><?php echo esc_html__('Twitter Cards', HR_SA_TEXT_DOMAIN) . ': ' . (hr_sa_is_twitter_enabled() ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN)); ?></li>
                    <li><?php echo esc_html__('Debug Mode', HR_SA_TEXT_DOMAIN) . ': ' . ($debug_on ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN)); ?></li>
                </ul>
            </div>
            <div class="hr-sa-card">
                <h2><?php esc_html_e('Quick Links', HR_SA_TEXT_DOMAIN); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url($settings); ?>"><?php esc_html_e('Settings', HR_SA_TEXT_DOMAIN); ?></a></li>
                    <li><a href="<?php echo esc_url($modules); ?>"><?php esc_html_e('Modules', HR_SA_TEXT_DOMAIN); ?></a></li>
                    <li>
                        <?php if ($debug_on) : ?>
                            <a href="<?php echo esc_url($debug_link); ?>"><?php esc_html_e('Debug Tools', HR_SA_TEXT_DOMAIN); ?></a>
                        <?php else : ?>
                            <span class="description"><?php esc_html_e('Enable Debug Mode in settings to access Debug Tools.', HR_SA_TEXT_DOMAIN); ?></span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
