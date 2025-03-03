<?php
/**
 * Plugin Name: Website Content Importer Pro
 * Plugin URI: https://yourwebsite.com/website-content-importer-pro
 * Description: An improved plugin that allows users to import content from external websites and create new WordPress pages with preserved content order.
 * Version: 1.0.0
 * Author: Tyler Bray
 * Author URI: https://yourwebsite.com
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCIP_VERSION', '1.0.0');
define('WCIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCIP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WCIP_PLUGIN_DIR . 'includes/class-admin.php';
require_once WCIP_PLUGIN_DIR . 'includes/class-importer.php';
require_once WCIP_PLUGIN_DIR . 'includes/class-page-creator.php';
require_once WCIP_PLUGIN_DIR . 'includes/class-shortcodes.php';

// Initialize the plugin components
function wcip_init() {
    $admin = new WCIP_Admin();
    $importer = new WCIP_Importer();
    $page_creator = new WCIP_Page_Creator();
    $shortcodes = new WCIP_Shortcodes($importer);
}

add_action('plugins_loaded', 'wcip_init');