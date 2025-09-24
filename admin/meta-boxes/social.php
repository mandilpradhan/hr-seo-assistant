<?php
/**
 * Admin meta box for social overrides.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'hr_sa_register_social_meta_fields');
add_action('add_meta_boxes', 'hr_sa_register_social_meta_box');
add_action('save_post', 'hr_sa_save_social_meta_box', 10, 2);

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
 * Register the social overrides meta box.
 */
function hr_sa_register_social_meta_box(): void
{
    if (!current_user_can('edit_posts')) {
        return;
    }

    foreach (hr_sa_social_supported_post_types() as $post_type) {
        add_meta_box(
            'hr-sa-social-meta',
            __('HR SEO Assistant', HR_SA_TEXT_DOMAIN),
            'hr_sa_render_social_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}

/**
 * Render the social overrides meta box form.
 */
function hr_sa_render_social_meta_box(WP_Post $post): void
{
    $post_id     = (int) $post->ID;
    $image       = $post_id ? hr_sa_get_social_image_override($post_id) : '';
    $description = $post_id ? hr_sa_get_social_description_override($post_id) : '';
    ?>
    <div class="hr-sa-social-meta-box">
        <?php wp_nonce_field('hr_sa_social_meta_box', 'hr_sa_social_meta_box_nonce'); ?>
        <p class="description"><?php esc_html_e('Leave blank to use site defaults.', HR_SA_TEXT_DOMAIN); ?></p>

        <div class="hr-sa-social-field">
            <label for="_hr_sa_social_image_override"><strong><?php esc_html_e('Social Image Override', HR_SA_TEXT_DOMAIN); ?></strong></label>
            <input type="url" class="widefat" id="_hr_sa_social_image_override" name="_hr_sa_social_image_override" value="<?php echo esc_attr($image); ?>" placeholder="https://" />
            <p class="description"><?php esc_html_e('Absolute HTTPS URL used for Open Graph & Twitter Cards when provided.', HR_SA_TEXT_DOMAIN); ?></p>
        </div>

        <div class="hr-sa-social-field">
            <label for="_hr_sa_social_description_override"><strong><?php esc_html_e('Social Description Override', HR_SA_TEXT_DOMAIN); ?></strong></label>
            <textarea class="widefat" id="_hr_sa_social_description_override" name="_hr_sa_social_description_override" rows="3" maxlength="<?php echo esc_attr((string) hr_sa_ai_get_description_limit()); ?>"><?php echo esc_textarea($description); ?></textarea>
            <p class="description"><?php esc_html_e('140–160 characters recommended. Falls back to generated or excerpt values.', HR_SA_TEXT_DOMAIN); ?></p>
        </div>
    </div>
    <?php
}

/**
 * Persist social override meta values when the post is saved.
 */
function hr_sa_save_social_meta_box(int $post_id, WP_Post $post): void
{
    if (!isset($_POST['hr_sa_social_meta_box_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash((string) $_POST['hr_sa_social_meta_box_nonce']));
    if (!$nonce || !wp_verify_nonce($nonce, 'hr_sa_social_meta_box')) {
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

    $fields = [
        '_hr_sa_social_image_override'        => 'hr_sa_sanitize_social_image_override',
        '_hr_sa_social_description_override'  => 'hr_sa_sanitize_social_description_override',
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
