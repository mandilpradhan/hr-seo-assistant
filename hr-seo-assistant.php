<?php
/**
 * Plugin Name: HR SEO Assistant
 * Plugin URI:  https://github.com/mandilpradhan/hr-seo-assistant
 * Description: Provides SEO scaffolding, settings, feature flags, and JSON-LD emitters for Himalayan Rides.
 * Version:     0.2.0
 * Author:      Himalayan Rides
 * License:     GPL-2.0-or-later
 * Text Domain: hr-seo-assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const HR_SA_VERSION     = '0.2.0';
const HR_SA_PLUGIN_FILE = __FILE__;
const HR_SA_PLUGIN_DIR  = __DIR__ . '/';
const HR_SA_TEXT_DOMAIN = 'hr-seo-assistant';

define('HR_SA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HR_SA_PLUGIN_DIR . 'core/settings.php';
require_once HR_SA_PLUGIN_DIR . 'core/feature-flags.php';
require_once HR_SA_PLUGIN_DIR . 'core/context.php';
require_once HR_SA_PLUGIN_DIR . 'core/compat.php';
require_once HR_SA_PLUGIN_DIR . 'integrations/media-help.php';
require_once HR_SA_PLUGIN_DIR . 'admin/menu.php';
require_once HR_SA_PLUGIN_DIR . 'modules/jsonld/loader.php';
require_once HR_SA_PLUGIN_DIR . 'modules/og/loader.php';

/**
 * Plugin activation callback to prime default options.
 */
function hr_sa_activate(): void
{
    hr_sa_settings_initialize_defaults();
    hr_sa_feature_flags_initialize_defaults();
    update_option('hr_sa_plugin_version', HR_SA_VERSION);
}

register_activation_hook(HR_SA_PLUGIN_FILE, 'hr_sa_activate');

add_action('plugins_loaded', 'hr_sa_maybe_run_upgrades', 1);

/**
 * Run incremental upgrade routines when the stored version lags behind.
 */
function hr_sa_maybe_run_upgrades(): void
{
    $stored_version = (string) get_option('hr_sa_plugin_version', '0.0.0');

    if (version_compare($stored_version, HR_SA_VERSION, '>=')) {
        return;
    }

    if (version_compare($stored_version, '0.2.0', '<')) {
        hr_sa_upgrade_to_020();
    }

    update_option('hr_sa_plugin_version', HR_SA_VERSION);
}

/**
 * Upgrade routine for version 0.2.0.
 */
function hr_sa_upgrade_to_020(): void
{
    $defaults = hr_sa_get_settings_defaults();

    $og_value = get_option('hr_sa_og_enabled', null);
    if ($og_value === null || $og_value === '0') {
        update_option('hr_sa_og_enabled', $defaults['hr_sa_og_enabled']);
    }

    $twitter_value = get_option('hr_sa_twitter_enabled', null);
    if ($twitter_value === null || $twitter_value === '0') {
        update_option('hr_sa_twitter_enabled', $defaults['hr_sa_twitter_enabled']);
    }
}

/**
 * Load the plugin text domain.
 */
function hr_sa_load_textdomain(): void
{
    load_plugin_textdomain(HR_SA_TEXT_DOMAIN, false, dirname(plugin_basename(HR_SA_PLUGIN_FILE)) . '/languages/');
}
add_action('plugins_loaded', 'hr_sa_load_textdomain');
