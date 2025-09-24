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
    $flags        = [
        'jsonld'  => hr_sa_is_jsonld_enabled(),
        'og'      => hr_sa_is_og_enabled(),
        'twitter' => hr_sa_is_twitter_enabled(),
        'debug'   => hr_sa_is_debug_enabled(),
    ];
    $hero_url     = hr_sa_get_media_help_hero_url();
    $has_hero     = $hero_url !== null;
    $other_seo    = hr_sa_other_seo_active();
    $emitters     = function_exists('hr_sa_jsonld_get_active_emitters') ? hr_sa_jsonld_get_active_emitters() : [];
    $social_snapshot = function_exists('hr_sa_get_social_tag_snapshot')
        ? hr_sa_get_social_tag_snapshot()
        : [
            'og_enabled'      => false,
            'twitter_enabled' => false,
            'blocked'         => false,
            'og'              => [],
            'twitter'         => [],
            'fields'          => [
                'title'          => '',
                'description'    => '',
                'url'            => '',
                'image'          => '',
                'site_name'      => '',
                'locale'         => '',
                'twitter_handle' => '',
            ],
        ];
    $social_fields = is_array($social_snapshot['fields'] ?? null) ? $social_snapshot['fields'] : [];
    $field_labels  = [
        'title'          => __('Title', HR_SA_TEXT_DOMAIN),
        'description'    => __('Description', HR_SA_TEXT_DOMAIN),
        'url'            => __('URL', HR_SA_TEXT_DOMAIN),
        'image'          => __('Image', HR_SA_TEXT_DOMAIN),
        'site_name'      => __('Site Name', HR_SA_TEXT_DOMAIN),
        'locale'         => __('Locale', HR_SA_TEXT_DOMAIN),
        'twitter_handle' => __('Twitter Handle', HR_SA_TEXT_DOMAIN),
    ];
    $copy_payload = [
        'context'  => $context,
        'settings' => $settings,
        'flags'    => $flags,
    ];
    $copy_json    = wp_json_encode($copy_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($copy_json) || $copy_json === '') {
        $copy_json = '{}';
    }
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
            <h2><?php esc_html_e('Social Metadata', HR_SA_TEXT_DOMAIN); ?></h2>
            <ul class="hr-sa-meta-status">
                <li><?php esc_html_e('Open Graph', HR_SA_TEXT_DOMAIN); ?>: <?php echo !empty($social_snapshot['og_enabled']) ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN); ?></li>
                <li><?php esc_html_e('Twitter Cards', HR_SA_TEXT_DOMAIN); ?>: <?php echo !empty($social_snapshot['twitter_enabled']) ? esc_html__('Enabled', HR_SA_TEXT_DOMAIN) : esc_html__('Disabled', HR_SA_TEXT_DOMAIN); ?></li>
            </ul>
            <?php if (!empty($social_snapshot['blocked'])) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Output suppressed because another SEO plugin is active while Conflict Mode is set to Respect.', HR_SA_TEXT_DOMAIN); ?></p>
                </div>
            <?php endif; ?>
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($field_labels as $field_key => $label) : ?>
                        <?php $value = isset($social_fields[$field_key]) ? (string) $social_fields[$field_key] : ''; ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($label); ?></th>
                            <td>
                                <?php if ($value === '') : ?>
                                    <span class="description"><?php esc_html_e('Not available', HR_SA_TEXT_DOMAIN); ?></span>
                                <?php elseif (in_array($field_key, ['url', 'image'], true)) : ?>
                                    <a href="<?php echo esc_url($value); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html($value); ?></code></a>
                                <?php else : ?>
                                    <code><?php echo esc_html($value); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3><?php esc_html_e('Open Graph Tags', HR_SA_TEXT_DOMAIN); ?></h3>
            <?php if (!empty($social_snapshot['og'])) : ?>
                <ul class="hr-sa-meta-list">
                    <?php foreach ($social_snapshot['og'] as $property => $value) : ?>
                        <li><code><?php echo esc_html($property); ?></code> <code><?php echo esc_html($value); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('Open Graph tags are disabled or missing required values.', HR_SA_TEXT_DOMAIN); ?></p>
            <?php endif; ?>

            <h3><?php esc_html_e('Twitter Card Tags', HR_SA_TEXT_DOMAIN); ?></h3>
            <?php if (!empty($social_snapshot['twitter'])) : ?>
                <ul class="hr-sa-meta-list">
                    <?php foreach ($social_snapshot['twitter'] as $name => $value) : ?>
                        <li><code><?php echo esc_html($name); ?></code> <code><?php echo esc_html($value); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description"><?php esc_html_e('Twitter Card tags are disabled or missing required values.', HR_SA_TEXT_DOMAIN); ?></p>
            <?php endif; ?>
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
            <textarea id="hr_sa_copy_payload" class="hr-sa-copy-source" readonly aria-hidden="true" tabindex="-1" hidden><?php echo esc_textarea($copy_json); ?></textarea>
            <button type="button" class="button button-primary hr-sa-copy-json" data-source="hr_sa_copy_payload">
                <?php esc_html_e('Copy Context & Settings JSON', HR_SA_TEXT_DOMAIN); ?>
            </button>
            <p class="description"><?php esc_html_e('Copies context, settings, and flags to the clipboard for support.', HR_SA_TEXT_DOMAIN); ?></p>
        </section>
    </div>
    <?php
}
