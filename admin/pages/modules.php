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
 * Render the modules status page.
 */
function hr_sa_render_modules_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $modules = [
        [
            'name'        => __('JSON-LD Emitters', HR_SA_TEXT_DOMAIN),
            'description' => __('Structured data graphs for Organization, Trips, Itineraries, FAQ, and Vehicles.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_jsonld_enabled(),
        ],
        [
            'name'        => __('Open Graph Tags', HR_SA_TEXT_DOMAIN),
            'description' => __('Generates Open Graph metadata for social sharing.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_og_enabled(),
        ],
        [
            'name'        => __('Twitter Cards', HR_SA_TEXT_DOMAIN),
            'description' => __('Outputs summary_large_image cards aligned with Open Graph values.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_twitter_enabled(),
        ],
        [
            'name'        => __('Debug Tools', HR_SA_TEXT_DOMAIN),
            'description' => __('Admin-only diagnostics for context, connectors, and settings.', HR_SA_TEXT_DOMAIN),
            'enabled'     => hr_sa_is_debug_enabled(),
        ],
    ];
    $conflict_mode = hr_sa_get_conflict_mode();
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('HR SEO Modules', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description"><?php esc_html_e('Module toggles are read-only in Phase 0. Future phases will allow enabling/disabling from here.', HR_SA_TEXT_DOMAIN); ?></p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Module', HR_SA_TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Status', HR_SA_TEXT_DOMAIN); ?></th>
                    <th scope="col"><?php esc_html_e('Details', HR_SA_TEXT_DOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module) : ?>
                    <tr>
                        <td><?php echo esc_html($module['name']); ?></td>
                        <td>
                            <?php if ($module['enabled']) : ?>
                                <span class="hr-sa-status hr-sa-status--on"><?php esc_html_e('Enabled', HR_SA_TEXT_DOMAIN); ?></span>
                            <?php else : ?>
                                <span class="hr-sa-status hr-sa-status--off"><?php esc_html_e('Disabled', HR_SA_TEXT_DOMAIN); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($module['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="hr-sa-conflict-note">
            <?php
            if ($conflict_mode === 'respect') {
                esc_html_e('Conflict mode is set to Respect, so HR SEO will yield when another SEO plugin is detected.', HR_SA_TEXT_DOMAIN);
            } elseif ($conflict_mode === 'block_og') {
                esc_html_e('Conflict mode is set to Block, so third-party Open Graph tags will be removed before HR SEO runs.', HR_SA_TEXT_DOMAIN);
            } else {
                esc_html_e('Conflict mode is set to Force. HR SEO output will run regardless of other SEO plugins.', HR_SA_TEXT_DOMAIN);
            }
            ?>
        </p>
    </div>
    <?php
}
