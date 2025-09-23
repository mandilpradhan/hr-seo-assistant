<?php
/**
 * Admin settings page.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the settings page.
 */
function hr_sa_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', HR_SA_TEXT_DOMAIN));
    }

    $fallback      = (string) hr_sa_get_setting('hr_sa_fallback_image', '');
    $tpl_trip      = (string) hr_sa_get_setting('hr_sa_tpl_trip');
    $tpl_page      = (string) hr_sa_get_setting('hr_sa_tpl_page');
    $brand_suffix  = (bool) hr_sa_get_setting('hr_sa_tpl_page_brand_suffix');
    $locale        = (string) hr_sa_get_setting('hr_sa_locale');
    $site_name     = (string) hr_sa_get_setting('hr_sa_site_name', get_bloginfo('name'));
    $twitter       = (string) hr_sa_get_setting('hr_sa_twitter_handle');
    $og_enabled    = (bool) hr_sa_get_setting('hr_sa_og_enabled');
    $twitter_cards = (bool) hr_sa_get_setting('hr_sa_twitter_enabled');
    $image_preset  = (string) get_option('hr_sa_image_preset', hr_sa_get_settings_defaults()['hr_sa_image_preset']);
    $conflict_mode = hr_sa_get_conflict_mode();
    $debug_enabled = hr_sa_is_debug_enabled();
    ?>
    <div class="wrap hr-sa-wrap">
        <h1><?php esc_html_e('HR SEO Settings', HR_SA_TEXT_DOMAIN); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('hr_sa_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="hr_sa_fallback_image"><?php esc_html_e('Fallback Image (Sitewide)', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <div class="hr-sa-media-field">
                                <input type="url" class="regular-text" id="hr_sa_fallback_image" name="hr_sa_fallback_image" value="<?php echo esc_attr($fallback); ?>" placeholder="https://" />
                                <button type="button" class="button hr-sa-media-picker" data-target="hr_sa_fallback_image"><?php esc_html_e('Choose Image', HR_SA_TEXT_DOMAIN); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e('Used when no header image meta is provided. Must be an absolute HTTPS URL.', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Title Templates', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Title Templates', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_tpl_trip"><?php esc_html_e('Trips', HR_SA_TEXT_DOMAIN); ?></label>
                                <input type="text" class="regular-text" id="hr_sa_tpl_trip" name="hr_sa_tpl_trip" value="<?php echo esc_attr($tpl_trip); ?>" />
                                <p class="description"><?php esc_html_e('Available tags: {{trip_name}}, {{country}}', HR_SA_TEXT_DOMAIN); ?></p>
                                <label for="hr_sa_tpl_page" class="hr-sa-template-label"><?php esc_html_e('Pages', HR_SA_TEXT_DOMAIN); ?></label>
                                <input type="text" class="regular-text" id="hr_sa_tpl_page" name="hr_sa_tpl_page" value="<?php echo esc_attr($tpl_page); ?>" />
                                <label for="hr_sa_tpl_page_brand_suffix">
                                    <input type="checkbox" id="hr_sa_tpl_page_brand_suffix" name="hr_sa_tpl_page_brand_suffix" value="1" <?php checked($brand_suffix); ?> />
                                    <?php esc_html_e('Append brand suffix', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Social Meta Output', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Social Meta Output', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label for="hr_sa_og_enabled">
                                    <input type="checkbox" id="hr_sa_og_enabled" name="hr_sa_og_enabled" value="1" <?php checked($og_enabled); ?> />
                                    <?php esc_html_e('Enable Open Graph tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label for="hr_sa_twitter_enabled">
                                    <input type="checkbox" id="hr_sa_twitter_enabled" name="hr_sa_twitter_enabled" value="1" <?php checked($twitter_cards); ?> />
                                    <?php esc_html_e('Enable Twitter Card tags', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Uses the header image meta when available, falling back to the sitewide image.', HR_SA_TEXT_DOMAIN); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_locale"><?php esc_html_e('Locale', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_locale" name="hr_sa_locale" value="<?php echo esc_attr($locale); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Format: xx_XX (e.g., en_US).', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_site_name"><?php esc_html_e('Site Name', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_site_name" name="hr_sa_site_name" value="<?php echo esc_attr($site_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_twitter_handle"><?php esc_html_e('Twitter Handle', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_twitter_handle" name="hr_sa_twitter_handle" value="<?php echo esc_attr($twitter); ?>" class="regular-text" placeholder="@username" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_image_preset"><?php esc_html_e('Image Preset (CDN)', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <input type="text" id="hr_sa_image_preset" name="hr_sa_image_preset" value="<?php echo esc_attr($image_preset); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Passed to the image CDN when resizing assets.', HR_SA_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Conflict Mode', HR_SA_TEXT_DOMAIN); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Conflict Mode', HR_SA_TEXT_DOMAIN); ?></legend>
                                <label>
                                    <input type="radio" name="hr_sa_conflict_mode" value="respect" <?php checked($conflict_mode, 'respect'); ?> />
                                    <?php esc_html_e('Respect other SEO plugins', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="radio" name="hr_sa_conflict_mode" value="force" <?php checked($conflict_mode, 'force'); ?> />
                                    <?php esc_html_e('Force HR SEO output', HR_SA_TEXT_DOMAIN); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Respect mode will defer JSON-LD when another SEO plugin is detected.', HR_SA_TEXT_DOMAIN); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hr_sa_debug_enabled"><?php esc_html_e('Debug Mode', HR_SA_TEXT_DOMAIN); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="hr_sa_debug_enabled" name="hr_sa_debug_enabled" value="1" <?php checked($debug_enabled); ?> />
                                <?php esc_html_e('Enable debug tools and menu.', HR_SA_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
