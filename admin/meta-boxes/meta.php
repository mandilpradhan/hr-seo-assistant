<?php
/**
 * Admin meta box for AI-assisted SEO fields and social overrides.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'hr_sa_register_ai_meta_fields');
add_action('init', 'hr_sa_register_social_meta_fields');
add_action('add_meta_boxes', 'hr_sa_register_hrseo_meta_box');
add_action('save_post', 'hr_sa_save_hrseo_meta_box', 10, 2);
add_action('admin_enqueue_scripts', 'hr_sa_enqueue_ai_meta_box_assets');

/**
 * Return the list of post types that support the AI meta box.
 *
 * @return array<int, string>
 */
function hr_sa_ai_supported_post_types(): array
{
    return ['post', 'page', 'trip'];
}

/**
 * Generate (and memoize) the nonce used by the AI meta box for saves and AJAX calls.
 */
function hr_sa_get_ai_meta_box_nonce(): string
{
    static $nonce = '';

    if ($nonce === '') {
        $nonce = wp_create_nonce('hr_sa_ai_meta_box');
    }

    return $nonce;
}

/**
 * Return the list of post types supporting social overrides.
 *
 * @return array<int, string>
 */
function hr_sa_social_supported_post_types(): array
{
    /**
     * Filter the supported post types for the social overrides meta box.
     *
     * @param array<int, string> $post_types
     */
    return apply_filters('hr_sa_social_meta_post_types', ['post', 'page', 'trip']);
}

/**
 * Return the post types that should expose the combined AI + Social meta box.
 *
 * @return array<int, string>
 */
function hr_sa_meta_box_supported_post_types(): array
{
    $ai_post_types     = hr_sa_ai_supported_post_types();
    $social_post_types = hr_sa_social_supported_post_types();

    $post_types = array_values(array_intersect($ai_post_types, $social_post_types));

    /**
     * Filter the supported post types for the combined meta box.
     *
     * @param array<int, string> $post_types
     */
    return apply_filters('hr_sa_meta_box_post_types', $post_types);
}

/**
 * Register the AI-related post meta fields with sanitization.
 */
function hr_sa_register_ai_meta_fields(): void
{
    foreach (hr_sa_ai_supported_post_types() as $post_type) {
        register_post_meta($post_type, '_hr_sa_title', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'hr_sa_ai_sanitize_meta_title',
        ]);

        register_post_meta($post_type, '_hr_sa_description', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'hr_sa_ai_sanitize_meta_description',
        ]);

        register_post_meta($post_type, '_hr_sa_keywords', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'hr_sa_ai_sanitize_meta_keywords',
        ]);
    }
}

/**
 * Register post meta for social overrides with sanitization.
 */
function hr_sa_register_social_meta_fields(): void
{
    foreach (hr_sa_social_supported_post_types() as $post_type) {
        register_post_meta($post_type, '_hr_sa_social_image_override', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'hr_sa_sanitize_social_image_override',
        ]);

        register_post_meta($post_type, '_hr_sa_social_description_override', [
            'show_in_rest'      => false,
            'single'            => true,
            'type'              => 'string',
            'sanitize_callback' => 'hr_sa_sanitize_social_description_override',
        ]);
    }
}

/**
 * Register the combined AI and social overrides meta box.
 */
function hr_sa_register_hrseo_meta_box(): void
{
    if (!current_user_can('edit_posts')) {
        return;
    }

    $post_types = hr_sa_meta_box_supported_post_types();

    foreach ($post_types as $post_type) {
        add_meta_box(
            'hrseo_meta',
            __('HR SEO — AI & Social', HR_SA_TEXT_DOMAIN),
            'hr_sa_render_hrseo_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}

/**
 * Render the combined meta box form.
 */
function hr_sa_render_hrseo_meta_box(WP_Post $post): void
{
    if (!current_user_can('edit_post', $post->ID)) {
        return;
    }

    $post_id      = (int) $post->ID;
    $can_use_ai   = current_user_can('manage_options');
    $ai_settings  = $can_use_ai ? hr_sa_ai_get_settings() : [];
    $nonce        = $can_use_ai ? hr_sa_get_ai_meta_box_nonce() : '';
    $title_limit  = $can_use_ai ? hr_sa_ai_get_title_limit() : 0;
    $desc_limit   = $can_use_ai ? hr_sa_ai_get_description_limit() : 0;
    $has_post_id  = $post_id > 0;
    $social_image = $post_id ? hr_sa_get_social_image_override($post_id) : '';
    $social_desc  = $post_id ? hr_sa_get_social_description_override($post_id) : '';
    ?>
    <div class="hr-sa-meta-box">
        <?php if ($can_use_ai) : ?>
            <details class="hr-sa-meta-section" open>
                <summary><strong><?php esc_html_e('AI Title & Description', HR_SA_TEXT_DOMAIN); ?></strong></summary>
                <div class="hr-sa-meta-section__content">
                    <div class="hr-sa-ai-meta-box">
                        <input type="hidden" name="hr_sa_ai_meta_box_nonce" id="hr_sa_ai_meta_box_nonce" value="<?php echo esc_attr($nonce); ?>" />
                        <?php wp_referer_field(); ?>
                        <?php if (!$has_post_id) : ?>
                            <div class="notice notice-info inline">
                                <p><?php esc_html_e('Save the post to enable AI suggestions.', HR_SA_TEXT_DOMAIN); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!$ai_settings['hr_sa_ai_enabled']) : ?>
                            <div class="notice notice-warning inline">
                                <p><?php esc_html_e('AI assistance is currently disabled. Enable it from the HR SEO settings page to use the Generate buttons.', HR_SA_TEXT_DOMAIN); ?></p>
                            </div>
                        <?php elseif (empty($ai_settings['hr_sa_ai_has_key'])) : ?>
                            <div class="notice notice-warning inline">
                                <p><?php esc_html_e('Add an AI API key in the HR SEO settings page to enable content generation.', HR_SA_TEXT_DOMAIN); ?></p>
                            </div>
                        <?php endif; ?>
                        <p class="hr-sa-ai-hint"><?php esc_html_e('Generation is admin-only; outputs are editable before publishing.', HR_SA_TEXT_DOMAIN); ?></p>

                        <?php
                        $title       = $post_id ? (string) get_post_meta($post_id, '_hr_sa_title', true) : '';
                        $description = $post_id ? (string) get_post_meta($post_id, '_hr_sa_description', true) : '';
                        $keywords    = $post_id ? (string) get_post_meta($post_id, '_hr_sa_keywords', true) : '';
                        ?>

                        <div class="hr-sa-ai-field">
                            <label for="_hr_sa_title"><strong><?php esc_html_e('SEO Title', HR_SA_TEXT_DOMAIN); ?></strong></label>
                            <input type="text" class="widefat" id="_hr_sa_title" name="_hr_sa_title" value="<?php echo esc_attr($title); ?>" maxlength="<?php echo esc_attr((string) $title_limit); ?>" />
                            <div class="hr-sa-ai-actions">
                                <button type="button" class="button hr-sa-ai-generate" data-hr-sa-ai-action="title" data-target="_hr_sa_title" <?php disabled(!$has_post_id); ?>><?php esc_html_e('Generate Title', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>

                        <div class="hr-sa-ai-field">
                            <label for="_hr_sa_description"><strong><?php esc_html_e('Meta Description', HR_SA_TEXT_DOMAIN); ?></strong></label>
                            <textarea class="widefat" id="_hr_sa_description" name="_hr_sa_description" rows="4" maxlength="<?php echo esc_attr((string) $desc_limit); ?>"><?php echo esc_textarea($description); ?></textarea>
                            <div class="hr-sa-ai-actions">
                                <button type="button" class="button hr-sa-ai-generate" data-hr-sa-ai-action="description" data-target="_hr_sa_description" <?php disabled(!$has_post_id); ?>><?php esc_html_e('Generate Description', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>

                        <div class="hr-sa-ai-field">
                            <label for="_hr_sa_keywords"><strong><?php esc_html_e('Keywords', HR_SA_TEXT_DOMAIN); ?></strong></label>
                            <textarea class="widefat" id="_hr_sa_keywords" name="_hr_sa_keywords" rows="3"><?php echo esc_textarea($keywords); ?></textarea>
                            <div class="hr-sa-ai-actions">
                                <button type="button" class="button hr-sa-ai-generate" data-hr-sa-ai-action="keywords" data-target="_hr_sa_keywords" <?php disabled(!$has_post_id); ?>><?php esc_html_e('Generate Keywords', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <details class="hr-sa-meta-section"<?php if (!$can_use_ai) : ?> open<?php endif; ?>>
            <summary><strong><?php esc_html_e('Social Overrides', HR_SA_TEXT_DOMAIN); ?></strong></summary>
            <div class="hr-sa-meta-section__content">
                <div class="hr-sa-social-meta-box">
                    <?php wp_nonce_field('hr_sa_social_meta_box', 'hr_sa_social_meta_box_nonce'); ?>
                    <p class="description"><?php esc_html_e('Leave blank to use site defaults.', HR_SA_TEXT_DOMAIN); ?></p>

                    <div class="hr-sa-social-field">
                        <label for="_hr_sa_social_image_override"><strong><?php esc_html_e('Social Image Override', HR_SA_TEXT_DOMAIN); ?></strong></label>
                        <input type="url" class="widefat" id="_hr_sa_social_image_override" name="_hr_sa_social_image_override" value="<?php echo esc_attr($social_image); ?>" placeholder="https://" />
                        <p class="description"><?php esc_html_e('Absolute HTTPS URL used for Open Graph & Twitter Cards when provided.', HR_SA_TEXT_DOMAIN); ?></p>
                    </div>

                    <div class="hr-sa-social-field">
                        <label for="_hr_sa_social_description_override"><strong><?php esc_html_e('Social Description Override', HR_SA_TEXT_DOMAIN); ?></strong></label>
                        <textarea class="widefat" id="_hr_sa_social_description_override" name="_hr_sa_social_description_override" rows="3" maxlength="<?php echo esc_attr((string) hr_sa_ai_get_description_limit()); ?>"><?php echo esc_textarea($social_desc); ?></textarea>
                        <p class="description"><?php esc_html_e('140–160 characters recommended. Falls back to generated or excerpt values.', HR_SA_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
            </div>
        </details>
    </div>
    <?php
}

/**
 * Persist meta box values when the post is saved.
 */
function hr_sa_save_hrseo_meta_box(int $post_id, WP_Post $post): void
{
    $ai_nonce     = '';
    $social_nonce = '';

    if (isset($_POST['hr_sa_ai_meta_box_nonce'])) {
        $ai_nonce = sanitize_text_field(wp_unslash((string) $_POST['hr_sa_ai_meta_box_nonce']));
    }

    if (isset($_POST['hr_sa_social_meta_box_nonce'])) {
        $social_nonce = sanitize_text_field(wp_unslash((string) $_POST['hr_sa_social_meta_box_nonce']));
    }

    $ai_nonce_valid     = $ai_nonce !== '' && wp_verify_nonce($ai_nonce, 'hr_sa_ai_meta_box');
    $social_nonce_valid = $social_nonce !== '' && wp_verify_nonce($social_nonce, 'hr_sa_social_meta_box');

    if (!$ai_nonce_valid && !$social_nonce_valid) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if ($ai_nonce_valid && current_user_can('manage_options')) {
        $fields = [
            '_hr_sa_title'       => 'title',
            '_hr_sa_description' => 'description',
            '_hr_sa_keywords'    => 'keywords',
        ];

        foreach ($fields as $meta_key => $field_type) {
            $value     = isset($_POST[$meta_key]) ? wp_unslash((string) $_POST[$meta_key]) : '';
            $sanitized = hr_sa_ai_sanitize_meta_value($value, $field_type);

            if ($sanitized === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $sanitized);
            }
        }
    }

    if ($social_nonce_valid) {
        $fields = [
            '_hr_sa_social_image_override'       => 'hr_sa_sanitize_social_image_override',
            '_hr_sa_social_description_override' => 'hr_sa_sanitize_social_description_override',
        ];

        foreach ($fields as $meta_key => $callback) {
            $raw_value = isset($_POST[$meta_key]) ? wp_unslash((string) $_POST[$meta_key]) : '';
            $sanitized = is_callable($callback) ? (string) call_user_func($callback, $raw_value) : '';

            if ($sanitized === '') {
                delete_post_meta($post_id, $meta_key);
            } else {
                update_post_meta($post_id, $meta_key, $sanitized);
            }
        }
    }
}

/**
 * Enqueue the assets required for the AI meta box.
 */
function hr_sa_enqueue_ai_meta_box_assets(string $hook_suffix): void
{
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, hr_sa_ai_supported_post_types(), true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_style(
        'hr-sa-admin',
        HR_SA_PLUGIN_URL . 'assets/admin.css',
        [],
        HR_SA_VERSION
    );

    wp_enqueue_script(
        'hr-sa-ai-meta-box',
        HR_SA_PLUGIN_URL . 'assets/meta-box-ai.js',
        ['wp-i18n'],
        HR_SA_VERSION,
        true
    );

    wp_set_script_translations('hr-sa-ai-meta-box', HR_SA_TEXT_DOMAIN);

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $nonce   = hr_sa_get_ai_meta_box_nonce();
    $ai      = hr_sa_ai_get_settings();

    wp_localize_script(
        'hr-sa-ai-meta-box',
        'hrSaAiMetaBox',
        [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => $nonce,
            'postId'     => $post_id,
            'aiEnabled'  => (bool) $ai['hr_sa_ai_enabled'],
            'messages'   => [
                'disabled'     => __('AI assistance is disabled. Enable it in the settings page to generate suggestions.', HR_SA_TEXT_DOMAIN),
                'missingPost'  => __('Save the post before requesting AI suggestions.', HR_SA_TEXT_DOMAIN),
                'requestError' => __('We could not generate content at this time. Please try again later.', HR_SA_TEXT_DOMAIN),
            ],
            'fields'     => [
                'title'       => '_hr_sa_title',
                'description' => '_hr_sa_description',
                'keywords'    => '_hr_sa_keywords',
            ],
        ]
    );
}
