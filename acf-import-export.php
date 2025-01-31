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
    if (!class_exists('ACF')) {
        add_action('admin_notices', 'acf_import_export_missing_acf_notice');
        return;
    }
    new ACF_Import_Export();
}
add_action('plugins_loaded', 'acf_import_export_init');

// Display notice if ACF is not active
function acf_import_export_missing_acf_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('ACF Import Export requires Advanced Custom Fields to be installed and activated.', 'acf-import-export'); ?></p>
    </div>
    <?php
}

// Activation hook
register_activation_hook(__FILE__, 'acf_import_export_activate');
function acf_import_export_activate() {
    if (!class_exists('ACF')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('This plugin requires Advanced Custom Fields to be installed and activated.', 'acf-import-export'),
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'acf_import_export_deactivate');
function acf_import_export_deactivate() {
    // Deactivation code here
} 