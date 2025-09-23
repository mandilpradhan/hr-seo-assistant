<?php
/**
 * Plugin Name: HR SEO Assistant
 * Plugin URI:  https://github.com/mandilpradhan/hr-seo-assistant
 * Description: Scaffold for HR SEO Assistant (Phase 0). Enables admin menu; modules coming next.
 * Version:     0.1.0
 * Author:      Himalayan Rides
 * License:     GPL-2.0-or-later
 */
if (!defined('ABSPATH')) exit;

define('HR_SA_VERSION', '0.1.0');

add_action('admin_menu', function () {
    add_menu_page(
        'HR SEO Assistant',
        'HR SEO',
        'manage_options',
        'hr-seo-assistant',
        function () {
            echo '<div class="wrap"><h1>HR SEO Assistant</h1><p>Scaffold installed. JSON-LD/OG modules will appear here once implemented.</p></div>';
        },
        'dashicons-chart-area',
        58
    );
});
