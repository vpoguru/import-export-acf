<?php
/**
 * Plugin Name: ACF Import Export
 * Plugin URI: https://vanshbordia.pages.dev/acf-import-export
 * Description: Import and export ACF fields, post types, taxonomies and related data
 * Version: 1.0.0
 * Author: Vansh Bordia
 * Author URI: https://vanshbordia.pages.dev/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: acf-import-export
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ACF_IE_VERSION', '1.0.0');
define('ACF_IE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACF_IE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once ACF_IE_PLUGIN_DIR . 'includes/class-acf-import-export.php';
require_once ACF_IE_PLUGIN_DIR . 'includes/class-acf-import.php';
require_once ACF_IE_PLUGIN_DIR . 'includes/class-acf-export.php';

// Initialize the plugin
function acf_import_export_init() {
    if (class_exists('ACF')) {
        new ACF_Import_Export();
    }
}
add_action('plugins_loaded', 'acf_import_export_init');

// Activation hook
register_activation_hook(__FILE__, 'acf_import_export_activate');
function acf_import_export_activate() {
    // Activation code here
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'acf_import_export_deactivate');
function acf_import_export_deactivate() {
    // Deactivation code here
} 