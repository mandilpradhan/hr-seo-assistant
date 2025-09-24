<?php
/**
 * Database upgrade routines.
 *
 * @package HR_SEO_Assistant
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'hr_sa_upgrade_run', 20);

/**
 * Run pending upgrade routines based on the stored DB version.
 */
function hr_sa_upgrade_run(): void
{
    $stored_version = get_option('hr_sa_db_version', '0.0.0');

    if (version_compare($stored_version, '0.3.0', '<')) {
        hr_sa_upgrade_to_030();
    }

    if ($stored_version !== HR_SA_DB_VERSION) {
        update_option('hr_sa_db_version', HR_SA_DB_VERSION);
    }
}

/**
 * Apply upgrades introduced in v0.3.0.
 */
function hr_sa_upgrade_to_030(): void
{
    $defaults = [
        'hr_sa_jsonld_enabled' => '1',
        'hr_sa_og_enabled'     => '1',
        'hr_sa_ai_enabled'     => '0',
        'hr_sa_debug_enabled'  => '0',
    ];

    foreach ($defaults as $option => $value) {
        if (get_option($option, null) === null) {
            add_option($option, $value);
        }
    }

    if (get_option('hr_sa_ai_instruction', null) === null) {
        add_option('hr_sa_ai_instruction', '');
    }

    $conflict_mode = (string) get_option('hr_sa_conflict_mode', 'respect');
    if ($conflict_mode === 'block_og' || $conflict_mode === 'block_others') {
        $conflict_mode = 'force';
    }
    if (!in_array($conflict_mode, ['respect', 'force'], true)) {
        $conflict_mode = 'respect';
    }

    update_option('hr_sa_conflict_mode', $conflict_mode);
    update_option('hr_sa_respect_other_seo', $conflict_mode === 'respect' ? '1' : '0');

    // Remove deprecated Twitter flag option if present.
    if (get_option('hr_sa_twitter_enabled', null) !== null) {
        delete_option('hr_sa_twitter_enabled');
    }
}
