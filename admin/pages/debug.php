<?php
/**
 * Admin debug page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the debug interface.
 */
function hr_sa_render_debug_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    if (!hr_sa_is_debug_enabled()) {
        wp_die(esc_html__('Debug mode is disabled. Enable it from the settings page.', HR_SA_TEXT_DOMAIN));
    }

    $post_id      = get_queried_object_id();
    $post_type    = $post_id ? get_post_type($post_id) : '';
    $template     = $post_id ? get_page_template_slug($post_id) : '';
    $context      = hr_sa_get_context();
    $settings     = hr_sa_get_all_settings();
    $conflict     = hr_sa_get_conflict_mode();
    $og_enabled   = hr_sa_is_og_enabled();
    $twitter_on   = hr_sa_is_twitter_enabled();
    $flags        = [
        'jsonld'  => hr_sa_is_jsonld_enabled(),
        'og'      => $og_enabled,
        'twitter' => $twitter_on,
        'debug'   => hr_sa_is_debug_enabled(),
    ];
    $hero_url     = hr_sa_get_media_help_hero_url();
    $has_hero     = $hero_url !== null;
    $social_image = function_exists('hr_sa_resolve_social_image_url') ? hr_sa_resolve_social_image_url($context) : null;
    $og_tags      = $og_enabled && function_exists('hr_sa_build_og_tags') ? hr_sa_build_og_tags($context, $social_image) : [];
    $twitter_tags = $twitter_on && function_exists('hr_sa_build_twitter_tags') ? hr_sa_build_twitter_tags($context, $social_image) : [];
    $og_type      = function_exists('hr_sa_resolve_og_type') ? hr_sa_resolve_og_type($context) : ($context['type'] ?? 'page');
    $other_seo    = hr_sa_other_seo_active();
    $emitters     = function_exists('hr_sa_jsonld_get_active_emitters') ? hr_sa_jsonld_get_active_emitters() : [];
    $copy_payload = [
        'context'  => $context,
        'settings' => $settings,
        'flags'    => $flags,
    ];
    $copy_json    = wp_json_encode($copy_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    ?>
    <div class="wrap hr-sa-wrap hr-sa-debug-wrap">
        <h1><?php esc_html_e('HR SEO Debug', HR_SA_TEXT_DOMAIN); ?></h1>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Environment', HR_SA_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post ID', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $post_id ? esc_html((string) $post_id) : esc_html__('N/A (admin)', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post Type', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $post_type ? esc_html($post_type) : esc_html__('N/A', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Template', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $template ? esc_html($template) : esc_html__('N/A', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Current URL', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><code><?php echo esc_html($context['url']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Conflict Mode', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo esc_html(ucfirst($conflict)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Detected SEO Plugins', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $other_seo ? esc_html__('Yes', HR_SA_TEXT_DOMAIN) : esc_html__('No', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Flags', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <ul>
                                <li><?php esc_html_e('JSON-LD', HR_SA_TEXT_DOMAIN); ?>: <?php echo $flags['jsonld'] ? esc_html__('On', HR_SA_TEXT_DOMAIN) : esc_html__('Off', HR_SA_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Open Graph', HR_SA_TEXT_DOMAIN); ?>: <?php echo $flags['og'] ? esc_html__('On', HR_SA_TEXT_DOMAIN) : esc_html__('Off', HR_SA_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Twitter Cards', HR_SA_TEXT_DOMAIN); ?>: <?php echo $flags['twitter'] ? esc_html__('On', HR_SA_TEXT_DOMAIN) : esc_html__('Off', HR_SA_TEXT_DOMAIN); ?></li>
                                <li><?php esc_html_e('Debug', HR_SA_TEXT_DOMAIN); ?>: <?php echo $flags['debug'] ? esc_html__('On', HR_SA_TEXT_DOMAIN) : esc_html__('Off', HR_SA_TEXT_DOMAIN); ?></li>
                            </ul>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Social Meta', HR_SA_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Open Graph Enabled', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $og_enabled ? esc_html__('Yes', HR_SA_TEXT_DOMAIN) : esc_html__('No', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Twitter Cards Enabled', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo $twitter_on ? esc_html__('Yes', HR_SA_TEXT_DOMAIN) : esc_html__('No', HR_SA_TEXT_DOMAIN); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('OG Type', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo esc_html((string) $og_type); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resolved Title', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo esc_html($context['title']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resolved Description', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo esc_html($context['description']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resolved URL', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><code><?php echo esc_html($context['url']); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resolved Site Name', HR_SA_TEXT_DOMAIN); ?></th>
                        <td><?php echo esc_html($context['site_name']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Resolved Image', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php if ($social_image) : ?>
                                <code><?php echo esc_html($social_image); ?></code>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e('No image resolved (hero and fallback empty).', HR_SA_TEXT_DOMAIN); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($og_tags) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('OG Tag Preview', HR_SA_TEXT_DOMAIN); ?></th>
                            <td><code><?php echo esc_html(wp_json_encode($og_tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($twitter_tags) : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Twitter Tag Preview', HR_SA_TEXT_DOMAIN); ?></th>
                            <td><code><?php echo esc_html(wp_json_encode($twitter_tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Context', HR_SA_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($context as $key => $value) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($key); ?></th>
                            <td><?php echo is_scalar($value) ? esc_html((string) $value) : '<code>' . esc_html(wp_json_encode($value)) . '</code>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Connectors', HR_SA_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Media Help Hero URL', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <?php if ($has_hero) : ?>
                                <code><?php echo esc_html((string) $hero_url); ?></code>
                            <?php else : ?>
                                <span class="description"><?php esc_html_e('Not provided', HR_SA_TEXT_DOMAIN); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Settings Snapshot', HR_SA_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($settings as $key => $value) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($key); ?></th>
                            <td><?php echo is_scalar($value) ? esc_html((string) $value) : '<code>' . esc_html(wp_json_encode($value)) . '</code>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('JSON-LD Emitters', HR_SA_TEXT_DOMAIN); ?></h2>
            <?php if ($emitters) : ?>
                <ul class="hr-sa-emitter-list">
                    <?php foreach ($emitters as $emitter) : ?>
                        <li><?php echo esc_html($emitter); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('No emitters registered or JSON-LD disabled.', HR_SA_TEXT_DOMAIN); ?></p>
            <?php endif; ?>
        </section>

        <section class="hr-sa-section">
            <h2><?php esc_html_e('Export', HR_SA_TEXT_DOMAIN); ?></h2>
            <button type="button" class="button button-primary hr-sa-copy-json" data-json="<?php echo esc_attr($copy_json ?: '{}'); ?>">
                <?php esc_html_e('Copy Context & Settings JSON', HR_SA_TEXT_DOMAIN); ?>
            </button>
            <p class="description"><?php esc_html_e('Copies context, settings, and flags to the clipboard for support.', HR_SA_TEXT_DOMAIN); ?></p>
        </section>
    </div>
    <?php
}
