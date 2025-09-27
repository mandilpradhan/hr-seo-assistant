<?php
/**
 * Open Graph & Twitter Cards module admin page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use HRSA\Modules;

/**
 * Build the Open Graph preview selector options.
 *
 * @return array{items: array<int, array{id:int,label:string,type:string}>, capped: bool}
 */
function hr_sa_open_graph_preview_build_targets(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $items = [
        [
            'id'    => 0,
            'label' => __('Home', HR_SA_TEXT_DOMAIN),
            'type'  => __('Front Page', HR_SA_TEXT_DOMAIN),
        ],
    ];

    $capped = false;

    $allowed_post_types = ['post', 'page', 'trip'];
    $post_type_objects  = [];
    $post_types         = [];

    foreach ($allowed_post_types as $post_type_slug) {
        if (!post_type_exists($post_type_slug)) {
            continue;
        }

        $post_types[] = $post_type_slug;

        $post_type_object = get_post_type_object($post_type_slug);
        if ($post_type_object instanceof WP_Post_Type) {
            $post_type_objects[$post_type_slug] = $post_type_object;
        }
    }

    if ($post_types) {
        $query = new WP_Query([
            'post_type'           => $post_types,
            'post_status'         => 'publish',
            'posts_per_page'      => 1000,
            'orderby'             => 'title',
            'order'               => 'ASC',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => false,
            'suppress_filters'    => true,
        ]);

        if ($query->have_posts()) {
            foreach ($query->posts as $post_object) {
                if (!$post_object instanceof WP_Post) {
                    continue;
                }

                $type_object = $post_type_objects[$post_object->post_type] ?? null;
                $type_label  = $type_object && isset($type_object->labels->singular_name)
                    ? (string) $type_object->labels->singular_name
                    : ucfirst((string) $post_object->post_type);
                $type_label = wp_strip_all_tags($type_label, true);
                if ($type_label === '') {
                    $type_label = ucfirst((string) $post_object->post_type);
                }

                $title = get_the_title($post_object);
                $title = is_string($title) ? wp_strip_all_tags($title, true) : '';

                if ($title === '') {
                    $title = sprintf(
                        /* translators: %s: Content type label. */
                        __('(Untitled %s)', HR_SA_TEXT_DOMAIN),
                        $type_label
                    );
                }

                $items[] = [
                    'id'    => (int) $post_object->ID,
                    'label' => $title,
                    'type'  => $type_label,
                ];
            }
        }

        $capped = (int) $query->found_posts > 1000;
        wp_reset_postdata();
    }

    $cache = [
        'items'  => $items,
        'capped' => $capped,
    ];

    return $cache;
}

/**
 * Render the Open Graph module screen.
 */
function hr_sa_render_module_open_graph_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    if (!function_exists('hr_sa_get_settings_view_model')) {
        require_once HR_SA_PLUGIN_DIR . 'admin/pages/settings.php';
    }

    $view         = hr_sa_get_settings_view_model();
    $targets_data = hr_sa_open_graph_preview_build_targets();
    $module_state = class_exists(Modules::class) ? Modules::is_enabled('open-graph') : true;
    $overview_url = admin_url('admin.php?page=hr-sa-overview');
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('Open Graph & Twitter Cards', HR_SA_TEXT_DOMAIN); ?></h1>
        <p class="description"><?php esc_html_e('Controls social metadata output and fallbacks.', HR_SA_TEXT_DOMAIN); ?></p>
        <?php if (!$module_state) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e('This module is disabled. Enable it from the HR SEO Overview to resume social metadata.', HR_SA_TEXT_DOMAIN); ?></p></div>
        <?php endif; ?>
        <form method="post" action="options.php">
            <?php settings_fields('hr_sa_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="hr_sa_fallback_image"><?php esc_html_e('Fallback Image (Sitewide)', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <div class="hr-sa-media-field">
                                <input type="url" class="regular-text" id="hr_sa_fallback_image" name="hr_sa_fallback_image" value="<?php echo esc_attr($view['fallback']); ?>" placeholder="https://" />
                                <button type="button" class="button hr-sa-media-picker" data-target="hr_sa_fallback_image"><?php esc_html_e('Choose Image', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Used when no hero image is provided. Must be an absolute HTTPS URL.', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Social Metadata', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Social Metadata Toggles', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_og_enabled">
                                    <input type="checkbox" id="hr_sa_og_enabled" name="hr_sa_og_enabled" value="1" <?php checked($view['og_enabled']); ?> />
                                    <?php esc_html_e('Enable Open Graph tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label for="hr_sa_twitter_enabled">
                                    <input type="checkbox" id="hr_sa_twitter_enabled" name="hr_sa_twitter_enabled" value="1" <?php checked($view['twitter_cards']); ?> />
                                    <?php esc_html_e('Enable Twitter Card tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Twitter Cards reuse Open Graph data and require a large hero image or fallback.', HR_SA_TEXT_DOMAIN); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_site_name"><?php esc_html_e('Site Name', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_site_name" name="hr_sa_site_name" value="<?php echo esc_attr($view['site_name']); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_twitter_handle"><?php esc_html_e('Twitter Handle', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_twitter_handle" name="hr_sa_twitter_handle" value="<?php echo esc_attr($view['twitter']); ?>" class="regular-text" placeholder="@username" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Image URL Prefix/Suffix Replace', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Image URL Prefix and Suffix Replacement', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_image_url_replace_enabled">
                                    <input type="checkbox" id="hr_sa_image_url_replace_enabled" name="hr_sa_image_url_replace_enabled" value="1" <?php checked($view['image_replace_enabled']); ?> />
                                    <?php esc_html_e('Enable prefix/suffix replacement', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Rewrite Open Graph image URLs using the rules below.', HR_SA_TEXT_DOMAIN); ?></p>
                                <div class="hr-sa-image-replace-fields">
                                    <div class="hr-sa-image-replace-field">
                                        <label class="hr-sa-image-replace-field__label" for="hr_sa_image_url_prefix_find"><?php esc_html_e('Prefix Find', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_prefix_find" name="hr_sa_image_url_prefix_find" value="<?php echo esc_attr($view['image_prefix_find']); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label class="hr-sa-image-replace-field__label" for="hr_sa_image_url_prefix_replace"><?php esc_html_e('Prefix Replace', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_prefix_replace" name="hr_sa_image_url_prefix_replace" value="<?php echo esc_attr($view['image_prefix_replace']); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label class="hr-sa-image-replace-field__label" for="hr_sa_image_url_suffix_find"><?php esc_html_e('Suffix Find', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_suffix_find" name="hr_sa_image_url_suffix_find" value="<?php echo esc_attr($view['image_suffix_find']); ?>" />
                                    </div>
                                    <div class="hr-sa-image-replace-field">
                                        <label class="hr-sa-image-replace-field__label" for="hr_sa_image_url_suffix_replace"><?php esc_html_e('Suffix Replace', HR_SA_TEXT_DOMAIN); ?></label>
                                        <input type="text" class="regular-text" id="hr_sa_image_url_suffix_replace" name="hr_sa_image_url_suffix_replace" value="<?php echo esc_attr($view['image_suffix_replace']); ?>" />
                                    </div>
                                </div>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php
            $preview_items  = $targets_data['items'];
            $preview_capped = $targets_data['capped'];
            ?>
            <div class="hr-sa-og-preview" data-hr-sa-og-preview>
                <h2 class="hr-sa-og-preview__title"><?php esc_html_e('Preview', HR_SA_TEXT_DOMAIN); ?></h2>
                <div class="hr-sa-og-preview__controls">
                    <label for="hr_sa_og_preview_target" class="hr-sa-og-preview__label"><?php esc_html_e('Preview content', HR_SA_TEXT_DOMAIN); ?></label>
                    <select id="hr_sa_og_preview_target" class="hr-sa-og-preview__select">
                        <?php foreach ($preview_items as $item) : ?>
                            <?php
                            $option_text = sprintf(
                                /* translators: 1: Content title, 2: Content type label. */
                                __('%1$s — %2$s', HR_SA_TEXT_DOMAIN),
                                $item['label'],
                                $item['type']
                            );
                            ?>
                            <option value="<?php echo esc_attr((string) $item['id']); ?>" <?php selected((int) $item['id'], 0); ?>>
                                <?php echo esc_html($option_text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($preview_capped) : ?>
                        <p class="description hr-sa-og-preview__description"><?php esc_html_e('Showing first 1000 items.', HR_SA_TEXT_DOMAIN); ?></p>
                    <?php endif; ?>
                </div>
                <div class="hr-sa-og-preview__status" aria-live="polite"></div>
                <figure class="hr-sa-og-preview__image" data-hr-sa-og-preview-image>
                    <img src="" alt="" loading="lazy" width="600" height="315" />
                    <figcaption class="hr-sa-og-preview__image-caption"><?php esc_html_e('Preview image (600×315)', HR_SA_TEXT_DOMAIN); ?></figcaption>
                </figure>
                <table class="widefat fixed striped hr-sa-og-preview__table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Property', HR_SA_TEXT_DOMAIN); ?></th>
                            <th scope="col"><?php esc_html_e('Value', HR_SA_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody data-hr-sa-og-preview-table>
                        <tr>
                            <td colspan="2"><?php esc_html_e('Select content to load a preview.', HR_SA_TEXT_DOMAIN); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php submit_button(__('Save Changes', HR_SA_TEXT_DOMAIN)); ?>
        </form>
        <p>
            <a class="button" href="<?php echo esc_url($overview_url); ?>"><?php esc_html_e('Back to Overview', HR_SA_TEXT_DOMAIN); ?></a>
        </p>
    </div>
    <?php
}
