<?php
/**
 * JSON-LD module admin page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the JSON-LD module screen.
 */
function hr_sa_render_module_jsonld_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $module_enabled = class_exists('HRSA\\Modules') ? HRSA\Modules::is_enabled('json-ld') : true;
    $emitters       = function_exists('hr_sa_jsonld_get_active_emitters') ? hr_sa_jsonld_get_active_emitters() : [];
    $legacy_url     = admin_url('admin.php?page=hr-sa-settings');
    $overview_url   = admin_url('admin.php?page=hr-sa-overview');
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('JSON-LD Emitters', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description">
            <?php esc_html_e('Structured data emitters for Organization, Trips, Itineraries, FAQ, and Vehicles.', HR_SA_TEXT_DOMAIN); ?>
        </p>
        <?php if (!$module_enabled) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e('This module is disabled. Enable it from the HR SEO Overview to resume schema output.', HR_SA_TEXT_DOMAIN); ?></p></div>
        <?php endif; ?>
        <h2><?php esc_html_e('Active Emitters (last request)', HR_SA_TEXT_DOMAIN); ?></h2>
        <?php if (empty($emitters)) : ?>
            <p><?php esc_html_e('No emitters have produced nodes during the last run or JSON-LD is disabled.', HR_SA_TEXT_DOMAIN); ?></p>
        <?php else : ?>
            <ul class="hr-sa-emitter-list">
                <?php foreach ($emitters as $emitter) : ?>
                    <li><?php echo esc_html($emitter); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($overview_url); ?>">
                <?php esc_html_e('Back to Overview', HR_SA_TEXT_DOMAIN); ?>
            </a>
            <a class="button" href="<?php echo esc_url($legacy_url); ?>">
                <?php esc_html_e('Legacy Settings', HR_SA_TEXT_DOMAIN); ?>
            </a>
        </p>
    </div>
    <?php
}
