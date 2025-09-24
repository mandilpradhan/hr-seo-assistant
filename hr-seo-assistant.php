<?php
/**
 * Plugin Name: HR SEO Assistant
 * Plugin URI:  https://github.com/mandilpradhan/hr-seo-assistant
 * Description: Provides SEO scaffolding, settings, feature flags, and JSON-LD emitters for Himalayan Rides.
 * Version:     0.3.0
 * Author:      Himalayan Rides
 * License:     GPL-2.0-or-later
 * Text Domain: hr-seo-assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const HR_SA_VERSION     = '0.3.0';
const HR_SA_PLUGIN_FILE = __FILE__;
const HR_SA_PLUGIN_DIR  = __DIR__ . '/';
const HR_SA_TEXT_DOMAIN = 'hr-seo-assistant';
const HR_SA_DB_VERSION  = '0.3.0';

define('HR_SA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HR_SA_PLUGIN_DIR . 'core/settings.php';
require_once HR_SA_PLUGIN_DIR . 'core/feature-flags.php';
require_once HR_SA_PLUGIN_DIR . 'core/context.php';
require_once HR_SA_PLUGIN_DIR . 'core/ai.php';
require_once HR_SA_PLUGIN_DIR . 'core/social.php';
require_once HR_SA_PLUGIN_DIR . 'core/upgrade.php';
require_once HR_SA_PLUGIN_DIR . 'core/compat.php';
require_once HR_SA_PLUGIN_DIR . 'integrations/media-help.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/settings.php';
require_once HR_SA_PLUGIN_DIR . 'admin/pages/debug.php';
require_once HR_SA_PLUGIN_DIR . 'admin/meta-boxes/social.php';
require_once HR_SA_PLUGIN_DIR . 'admin/meta-boxes/ai.php';
require_once HR_SA_PLUGIN_DIR . 'admin/ajax/ai.php';
require_once HR_SA_PLUGIN_DIR . 'admin/menu.php';
require_once HR_SA_PLUGIN_DIR . 'admin/admin-bar.php';
require_once HR_SA_PLUGIN_DIR . 'modules/meta/seo.php';
require_once HR_SA_PLUGIN_DIR . 'modules/jsonld/loader.php';
require_once HR_SA_PLUGIN_DIR . 'modules/og/loader.php';

/**
 * Plugin activation callback to prime default options.
 */
function hr_sa_activate(): void
{
    hr_sa_settings_initialize_defaults();
    hr_sa_feature_flags_initialize_defaults();
    update_option('hr_sa_db_version', HR_SA_DB_VERSION);
}

register_activation_hook(HR_SA_PLUGIN_FILE, 'hr_sa_activate');

/**
 * Load the plugin text domain.
 */
function hr_sa_load_textdomain(): void
{
    load_plugin_textdomain(HR_SA_TEXT_DOMAIN, false, dirname(plugin_basename(HR_SA_PLUGIN_FILE)) . '/languages/');
}
add_action('plugins_loaded', 'hr_sa_load_textdomain');
