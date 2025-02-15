<?php
/**
 * Plugin Name: Import/Export for Advanced Custom Fields
 * Plugin URI: https://vanshbordia.pages.dev/import-export-acf
 * Description: Import and export ACF fields, post types, taxonomies and related data
 * Version: 1.0.0
 * Author: Vansh Bordia
 * Author URI: https://vanshbordia.pages.dev/
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: import-export-acf
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ACF_IMPORT_EXPORT_VERSION', '1.0.0');
define('ACF_IMPORT_EXPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACF_IMPORT_EXPORT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if ACF is active
function acf_import_export_has_acf() {
    return class_exists('ACF');
}

// Admin notice for missing ACF dependency
function acf_import_export_admin_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('ACF Import Export requires Advanced Custom Fields to be installed and activated.', 'import-export-acf'); ?></p>
    </div>
    <?php
}

// Initialize plugin
function acf_import_export_init() {
    if (!acf_import_export_has_acf()) {
        add_action('admin_notices', 'acf_import_export_admin_notice');
        return;
    }
    
    // Initialize the main plugin class
    new ACF_Import_Export();
}
add_action('plugins_loaded', 'acf_import_export_init');

class ACF_Import_Export {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'), 100);
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_import'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_get_post_type_taxonomies', array($this, 'ajax_get_post_type_taxonomies'));
    }

    public function add_admin_menu() {
        if (!function_exists('acf_get_setting')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=acf-field-group',
            __('ACF Import/Export', 'import-export-acf'),
            __('Import/Export', 'import-export-acf'),
            'manage_options',
            'import-export-acf',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        if ('acf-field-group_page_import-export-acf' !== $hook) {
            return;
        }

        // Register and enqueue the CSS
        wp_register_style(
            'import-export-acf-styles',
            ACF_IMPORT_EXPORT_PLUGIN_URL . 'assets/css/style.css',
            array(),
            ACF_IMPORT_EXPORT_VERSION
        );
        wp_enqueue_style('import-export-acf-styles');
    }

    /**
     * AJAX handler to get taxonomies for a post type
     */
    public function ajax_get_post_type_taxonomies() {
        // Check nonce
        if (!check_ajax_referer('acf_import_export_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        // Check if post type is provided
        if (empty($_POST['post_type'])) {
            wp_send_json_error('No post type provided');
            return;
        }

        // Get and sanitize the post type
        $post_type = sanitize_key($_POST['post_type']);

        // Get taxonomies for this post type
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        
        if (empty($taxonomies)) {
            wp_send_json_error('No taxonomies found');
            return;
        }

        // Format taxonomies for response
        $formatted_taxonomies = array();
        foreach ($taxonomies as $taxonomy) {
            $formatted_taxonomies[] = array(
                'name' => $taxonomy->name,
                'label' => $taxonomy->label
            );
        }

        wp_send_json_success($formatted_taxonomies);
    }

    public function render_admin_page() {
        // Verify nonce if form was submitted
        if (!empty($_POST)) {
            if (!isset($_POST['acf_export_nonce']) || !wp_verify_nonce($_POST['acf_export_nonce'], 'acf_export_nonce')) {
                wp_die(
                    esc_html__('Invalid nonce specified', 'import-export-acf'), 
                    esc_html__('Error', 'import-export-acf'), 
                    array(
                        'response' => 403,
                        'back_link' => true,
                    )
                );
            }
        }

        // Get all post types including built-in ones except revisions and menus
        $post_types = get_post_types(array(
            'show_ui' => true
        ), 'objects');
        
        // Remove unwanted post types
        unset($post_types['revision']);
        unset($post_types['nav_menu_item']);
        unset($post_types['custom_css']);
        unset($post_types['customize_changeset']);

        // Get selected post type if any
        $selected_post_type = isset($_POST['export_post_type']) ? sanitize_key($_POST['export_post_type']) : '';
        
        // Get taxonomies for selected post type
        $post_type_taxonomies = array();
        if ($selected_post_type) {
            $post_type_taxonomies = get_object_taxonomies($selected_post_type, 'objects');
        }
        
        // Get all ACF field groups
        $all_field_groups = array();
        if (function_exists('acf_get_field_groups')) {
            $all_field_groups = acf_get_field_groups();
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Import/Export for Advanced Custom Fields', 'import-export-acf'); ?></h1>
            
            <!-- Export Section -->
            <div class="card">
                <h2><?php esc_html_e('Export', 'import-export-acf'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('acf_export_nonce', 'acf_export_nonce'); ?>
                    
                    <div class="export-section">
                        <h3><?php esc_html_e('Select Post Type to Export', 'import-export-acf'); ?></h3>
                        <select name="export_post_type" id="export_post_type" onchange="this.form.submit()" required>
                            <option value=""><?php esc_html_e('Select a post type...', 'import-export-acf'); ?></option>
                            <?php foreach ($post_types as $post_type): ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>" <?php selected($selected_post_type, $post_type->name); ?>>
                                <?php echo esc_html($post_type->label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($selected_post_type): ?>
                        <h3><?php esc_html_e('Export Options', 'import-export-acf'); ?></h3>
                        
                        <!-- Taxonomy Options -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="taxonomies" class="toggle-section" data-section="taxonomy-options" checked>
                                <?php esc_html_e('Include Taxonomies', 'import-export-acf'); ?>
                            </label>
                            <div class="sub-options taxonomy-options" style="margin-left: 20px;">
                                <label>
                                    <input type="radio" name="taxonomy_selection" value="all" checked>
                                    <?php esc_html_e('Export All Taxonomies', 'import-export-acf'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="taxonomy_selection" value="selected">
                                    <?php esc_html_e('Select Specific Taxonomies', 'import-export-acf'); ?>
                                </label>
                                <div class="taxonomy-list" style="margin: 10px 0 10px 20px;">
                                    <?php if (!empty($post_type_taxonomies)): ?>
                                        <?php foreach ($post_type_taxonomies as $taxonomy): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="specific_taxonomies[]" value="<?php echo esc_attr($taxonomy->name); ?>">
                                            <?php echo esc_html($taxonomy->label); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p><?php esc_html_e('No taxonomies found for this post type.', 'import-export-acf'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ACF Fields Options -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="acf_fields" class="toggle-section" data-section="acf-options" checked>
                                <?php esc_html_e('Include ACF Fields', 'import-export-acf'); ?>
                            </label>
                            <div class="sub-options acf-options" style="margin-left: 20px;">
                                <label>
                                    <input type="radio" name="acf_selection" value="all" checked>
                                    <?php esc_html_e('Export All ACF Fields', 'import-export-acf'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="acf_selection" value="selected">
                                    <?php esc_html_e('Select Specific Field Groups', 'import-export-acf'); ?>
                                </label>
                                <div class="acf-list" style="margin: 10px 0 10px 20px;">
                                    <?php if (!empty($all_field_groups)): ?>
                                        <?php foreach ($all_field_groups as $field_group): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="specific_acf_groups[]" value="<?php echo esc_attr($field_group['key']); ?>">
                                            <?php echo esc_html($field_group['title']); ?>
                                        </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p><?php esc_html_e('No ACF field groups found.', 'import-export-acf'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Featured Image Option -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="featured_image" checked>
                                <?php esc_html_e('Include Featured Image URL', 'import-export-acf'); ?>
                            </label>
                        </div>

                        <p class="submit">
                            <input type="submit" name="acf_export" class="button button-primary" value="<?php esc_attr_e('Export to CSV', 'import-export-acf'); ?>">
                        </p>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Import Section -->
            <div class="card">
                <h2>Import</h2>
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field('acf_import_nonce', 'acf_import_nonce'); ?>
                    <p>
                        <label>Select CSV file to import:</label><br>
                        <input type="file" name="import_file" accept=".csv" required>
                    </p>
                    <p>
                        <input type="submit" name="acf_import" class="button button-primary" value="Import from CSV">
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_export() {
        // Verify nonce and user capabilities first
        if (!isset($_POST['acf_export']) || 
            !check_admin_referer('acf_export_nonce', 'acf_export_nonce') || 
            !current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['export_post_type'])) {
            $this->add_admin_notice(
                esc_html__('Please select a post type to export.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Sanitize and validate post type
        $post_type = sanitize_key(wp_unslash($_POST['export_post_type']));
        if (!post_type_exists($post_type)) {
            $this->add_admin_notice(
                esc_html__('Invalid post type selected.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Sanitize export options
        $export_options = isset($_POST['export_options']) ? 
            array_map('sanitize_key', wp_unslash($_POST['export_options'])) : 
            array();
        
        // Validate and sanitize taxonomy selection
        $taxonomy_selection = isset($_POST['taxonomy_selection']) ? 
            sanitize_key(wp_unslash($_POST['taxonomy_selection'])) : 
            'all';
        if (!in_array($taxonomy_selection, array('all', 'selected'), true)) {
            $taxonomy_selection = 'all';
        }

        // Sanitize specific taxonomies
        $specific_taxonomies = isset($_POST['specific_taxonomies']) ? 
            array_map('sanitize_key', wp_unslash($_POST['specific_taxonomies'])) : 
            array();
        
        // Validate and sanitize ACF selection
        $acf_selection = isset($_POST['acf_selection']) ? 
            sanitize_key(wp_unslash($_POST['acf_selection'])) : 
            'all';
        if (!in_array($acf_selection, array('all', 'selected'), true)) {
            $acf_selection = 'all';
        }

        // Sanitize specific ACF groups
        $specific_acf_groups = isset($_POST['specific_acf_groups']) ? 
            array_map('sanitize_key', wp_unslash($_POST['specific_acf_groups'])) : 
            array();

        // Get all posts of the selected type
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));

        if (empty($posts)) {
            $this->add_admin_notice(
                sprintf(
                    /* translators: %s: Post type name */
                    esc_html__('No posts found for the post type: %s', 'import-export-acf'),
                    esc_html($post_type)
                ),
                'error'
            );
            return;
        }

        // Get taxonomies based on selection
        $taxonomies = array();
        if (in_array('taxonomies', $export_options, true)) {
            if ($taxonomy_selection === 'all') {
                $taxonomies = get_object_taxonomies($post_type, 'objects');
            } else {
                // Only include specifically selected taxonomies
                foreach ($specific_taxonomies as $tax_name) {
                    if (taxonomy_exists($tax_name)) {
                        $taxonomy = get_taxonomy($tax_name);
                        if ($taxonomy) {
                            $taxonomies[$tax_name] = $taxonomy;
                        }
                    }
                }
            }
        }

        // Initialize ACF fields array based on selection
        $acf_fields = array();
        if (in_array('acf_fields', $export_options, true) && function_exists('acf_get_field_groups')) {
            if ($acf_selection === 'all') {
                // Get all field groups and their fields
                $field_groups = acf_get_field_groups();
                foreach ($field_groups as $field_group) {
                    $fields = acf_get_fields($field_group);
                    if ($fields) {
                        foreach ($fields as $field) {
                            $acf_fields[$field['name']] = $field['label'];
                        }
                    }
                }
            } else {
                // Only include fields from specifically selected field groups
                foreach ($specific_acf_groups as $group_key) {
                    $field_group = acf_get_field_group($group_key);
                    if ($field_group) {
                        $fields = acf_get_fields($field_group);
                        if ($fields) {
                            foreach ($fields as $field) {
                                $acf_fields[$field['name']] = $field['label'];
                            }
                        }
                    }
                }
            }
        }

        // Prepare headers with proper escaping
        $headers = array(
            esc_html__('ID', 'import-export-acf'),
            esc_html__('Post Title', 'import-export-acf'),
            esc_html__('Post Content', 'import-export-acf'),
            esc_html__('Post Excerpt', 'import-export-acf'),
            esc_html__('Post Status', 'import-export-acf'),
            esc_html__('Post Date', 'import-export-acf'),
            esc_html__('Post Modified', 'import-export-acf')
        );

        // Add taxonomy headers only for selected taxonomies
        if (in_array('taxonomies', $export_options, true) && !empty($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                $headers[] = esc_html($taxonomy->label);
            }
        }

        // Add ACF field headers only for selected fields
        if (in_array('acf_fields', $export_options, true) && !empty($acf_fields)) {
            foreach ($acf_fields as $field_name => $field_label) {
                $headers[] = esc_html($field_label);
            }
        }

        // Add featured image header if selected
        if (in_array('featured_image', $export_options, true)) {
            $headers[] = esc_html__('Featured Image URL', 'import-export-acf');
        }

        // Use WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Create temporary file with proper permissions
        $temp_file = wp_tempnam('acf-export');
        if (!$temp_file) {
            $this->add_admin_notice(
                esc_html__('Error creating temporary file for export.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Add BOM for Excel
        $wp_filesystem->put_contents($temp_file, "\xEF\xBB\xBF");

        // Prepare CSV content with proper escaping
        $csv_content = $this->array_to_csv($headers);

        // Process each post
        foreach ($posts as $post) {
            $row = array(
                absint($post->ID),
                sanitize_text_field($post->post_title),
                wp_kses_post($post->post_content),
                sanitize_textarea_field($post->post_excerpt),
                sanitize_key($post->post_status),
                sanitize_text_field($post->post_date),
                sanitize_text_field($post->post_modified)
            );

            // Add taxonomy values with proper escaping
            if (in_array('taxonomies', $export_options, true)) {
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_post_terms($post->ID, $taxonomy->name, array('fields' => 'names'));
                    $row[] = !is_wp_error($terms) ? 
                        implode(', ', array_map('sanitize_text_field', $terms)) : 
                        '';
                }
            }

            // Add ACF field values with proper escaping
            if (in_array('acf_fields', $export_options, true)) {
                foreach ($acf_fields as $field_name => $field_label) {
                    $value = get_field($field_name, $post->ID);
                    if (is_array($value)) {
                        $value = wp_json_encode($value);
                    }
                    $row[] = sanitize_text_field($value);
                }
            }

            // Add featured image with proper URL escaping
            if (in_array('featured_image', $export_options, true)) {
                $image_url = get_the_post_thumbnail_url($post->ID, 'full');
                $row[] = $image_url ? esc_url_raw($image_url) : '';
            }

            $csv_content .= $this->array_to_csv($row);
        }

        // Write content to file with proper permissions
        if (false === $wp_filesystem->put_contents($temp_file, $csv_content, FS_CHMOD_FILE)) {
            $this->add_admin_notice(
                esc_html__('Error writing export data to file.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Set proper headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header(sprintf(
            'Content-Disposition: attachment; filename="%s-export-%s.csv"',
            sanitize_file_name($post_type),
            sanitize_file_name(gmdate('Y-m-d'))
        ));
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output the file contents and delete temp file
        if ($wp_filesystem->exists($temp_file)) {
            // Use wp_kses_post for the final output to ensure it's safe
            echo wp_kses_post($wp_filesystem->get_contents($temp_file));
            $wp_filesystem->delete($temp_file);
        }
        exit;
    }

    /**
     * Convert array to CSV line
     *
     * @param array $fields Array of fields to convert to CSV
     * @return string CSV formatted line with proper escaping
     */
    private function array_to_csv($fields) {
        global $wp_filesystem;
        
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Create a temporary file
        $temp_file = wp_tempnam('csv-line');
        if (!$temp_file) {
            return '';
        }

        // Format the fields as CSV
        $output = array();
        foreach ($fields as $field) {
            $output[] = '"' . str_replace('"', '""', $field) . '"';
        }
        $csv_line = implode(',', $output) . "\n";

        // Write to temp file and read back
        $wp_filesystem->put_contents($temp_file, $csv_line);
        $result = $wp_filesystem->get_contents($temp_file);
        
        // Clean up
        $wp_filesystem->delete($temp_file);
        
        return $result;
    }

    private function get_attachment_id_from_url($image_url) {
        $cache_key = 'acf_ie_img_' . md5($image_url);
        $attachment_id = wp_cache_get($cache_key, 'import-export-acf');
        
        if (false === $attachment_id) {
            $upload_dir = wp_upload_dir();
            $site_url = get_site_url();
            
            if (strpos($image_url, $site_url) === 0) {
                // Use WordPress's built-in function to get attachment ID from URL
                $attachment_id = attachment_url_to_postid($image_url);
                
                if ($attachment_id) {
                    wp_cache_set($cache_key, $attachment_id, 'import-export-acf', HOUR_IN_SECONDS);
                }
            }
        }
        
        return $attachment_id;
    }

    public function handle_import() {
        // Verify nonce and user capabilities first
        if (!isset($_POST['acf_import']) || 
            !check_admin_referer('acf_import_nonce', 'acf_import_nonce') || 
            !current_user_can('manage_options')) {
            return;
        }

        // Validate file upload
        if (!isset($_FILES['import_file']) || 
            !isset($_FILES['import_file']['error']) || 
            UPLOAD_ERR_OK !== $_FILES['import_file']['error']) {
            
            $this->add_admin_notice(
                esc_html__('Please select a valid CSV file to import.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Sanitize and validate file data
        $file = array(
            'name'     => isset($_FILES['import_file']['name']) ? sanitize_file_name($_FILES['import_file']['name']) : '',
            'type'     => isset($_FILES['import_file']['type']) ? sanitize_mime_type($_FILES['import_file']['type']) : '',
            'tmp_name' => isset($_FILES['import_file']['tmp_name']) ? sanitize_text_field($_FILES['import_file']['tmp_name']) : '',
            'error'    => isset($_FILES['import_file']['error']) ? absint($_FILES['import_file']['error']) : 0,
            'size'     => isset($_FILES['import_file']['size']) ? absint($_FILES['import_file']['size']) : 0,
        );

        // Validate file type
        $allowed_types = array('text/csv', 'application/csv', 'application/octet-stream');
        if (!in_array($file['type'], $allowed_types, true)) {
            $this->add_admin_notice(
                esc_html__('Invalid file type. Please upload a CSV file.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ('csv' !== $file_extension) {
            $this->add_admin_notice(
                esc_html__('Invalid file extension. Please upload a CSV file.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            $this->add_admin_notice(
                esc_html__('File size exceeds maximum limit of 5MB.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Use WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Verify file exists and is readable
        $tmp_file = wp_normalize_path($file['tmp_name']);
        if (!$wp_filesystem->exists($tmp_file) || !$wp_filesystem->is_readable($tmp_file)) {
            $this->add_admin_notice(
                esc_html__('Error accessing the CSV file.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Read and validate file contents
        $content = $wp_filesystem->get_contents($tmp_file);
        if (false === $content) {
            $this->add_admin_notice(
                esc_html__('Error reading the CSV file.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Remove BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Process the CSV content
        $rows = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($rows);

        if (empty($headers)) {
            $this->add_admin_notice(
                esc_html__('Invalid CSV format: No headers found.', 'import-export-acf'),
                'error'
            );
            return;
        }

        // Sanitize headers
        $headers = array_map('sanitize_text_field', $headers);

        // Create a map of column positions
        $column_map = array_flip($headers);

        // Get all taxonomies and create a label to name mapping
        $all_taxonomies = get_taxonomies(array(), 'objects');
        $taxonomy_map = array();
        foreach ($all_taxonomies as $tax_name => $tax_object) {
            $taxonomy_map[sanitize_text_field($tax_object->label)] = sanitize_key($tax_name);
        }

        // Track import statistics
        $stats = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        );

        // Process each row
        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }

            // Sanitize row data
            $row = array_map('sanitize_text_field', $row);

            // Process post data
            $post_data = $this->prepare_post_data($row, $column_map);
            
            if (empty($post_data['post_title'])) {
                $stats['skipped']++;
                continue;
            }

            // Insert or update post
            $post_id = $this->insert_or_update_post($post_data, $stats);
            
            if (!$post_id) {
                continue;
            }

            // Process taxonomies and ACF fields
            $this->process_taxonomies_and_fields($post_id, $row, $headers, $column_map, $taxonomy_map);

            // Handle featured image
            if (isset($column_map['Featured Image URL']) && !empty($row[$column_map['Featured Image URL']])) {
                $image_url = esc_url_raw($row[$column_map['Featured Image URL']]);
                $this->set_featured_image($post_id, $image_url);
            }
        }

        // Show success message with stats
        $this->show_import_stats($stats);
    }

    /**
     * Helper function to add admin notices
     */
    private function add_admin_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr('notice notice-' . $type),
                wp_kses_post($message)
            );
        });
    }

    /**
     * Prepare post data from CSV row
     */
    private function prepare_post_data($row, $column_map) {
        return array(
            'ID'           => isset($row[$column_map['ID']]) ? absint($row[$column_map['ID']]) : 0,
            'post_title'   => isset($row[$column_map['Post Title']]) ? sanitize_text_field($row[$column_map['Post Title']]) : '',
            'post_content' => isset($row[$column_map['Post Content']]) ? wp_kses_post($row[$column_map['Post Content']]) : '',
            'post_excerpt' => isset($row[$column_map['Post Excerpt']]) ? sanitize_textarea_field($row[$column_map['Post Excerpt']]) : '',
            'post_status'  => isset($row[$column_map['Post Status']]) ? sanitize_key($row[$column_map['Post Status']]) : 'publish'
        );
    }

    /**
     * Insert or update post
     */
    private function insert_or_update_post($post_data, &$stats) {
        $post_id = 0;
        
        if ($post_data['ID'] > 0) {
            // Update existing post
            $post_id = wp_update_post($post_data, true);
            if (!is_wp_error($post_id)) {
                $stats['updated']++;
            } else {
                $stats['failed']++;
                $this->log_error('Post update failed', array(
                    'error' => $post_id->get_error_message(),
                    'post_data' => $post_data
                ));
                return 0;
            }
        } else {
            // Create new post
            unset($post_data['ID']);
            $post_id = wp_insert_post($post_data, true);
            if (!is_wp_error($post_id)) {
                $stats['created']++;
            } else {
                $stats['failed']++;
                $this->log_error('Post creation failed', array(
                    'error' => $post_id->get_error_message(),
                    'post_data' => $post_data
                ));
                return 0;
            }
        }

        return $post_id;
    }

    /**
     * Process taxonomies and ACF fields
     */
    private function process_taxonomies_and_fields($post_id, $row, $headers, $column_map, $taxonomy_map) {
        foreach ($headers as $index => $header) {
            if (!isset($row[$index]) || empty($row[$index])) {
                continue;
            }

            // Skip known post fields
            if (in_array($header, ['ID', 'Post Title', 'Post Content', 'Post Excerpt', 'Post Status', 'Post Date', 'Post Modified', 'Featured Image URL'], true)) {
                continue;
            }

            // Check if this header matches a taxonomy label
            $taxonomy_name = isset($taxonomy_map[$header]) ? $taxonomy_map[$header] : false;
            
            if ($taxonomy_name && taxonomy_exists($taxonomy_name)) {
                $this->process_taxonomy_terms($post_id, $taxonomy_name, explode(',', $row[$index]), 'import-export-acf');
            } else {
                // Process ACF field
                $field_key = $this->get_acf_field_key($header, $post_id);
                if ($field_key) {
                    $value = $row[$index];
                    if ($this->is_json($value)) {
                        $value = json_decode($value, true);
                    }
                    update_field($field_key, $value, $post_id);
                }
            }
        }
    }

    /**
     * Show import statistics
     */
    private function show_import_stats($stats) {
        $message = sprintf(
            '<div class="updated"><p>%s<br>%s<br>%s<br>%s<br>%s</p></div>',
            esc_html__('Import completed successfully:', 'import-export-acf'),
            /* translators: %d: Number of posts created */
            sprintf(esc_html__('Created: %d', 'import-export-acf'), absint($stats['created'])),
            /* translators: %d: Number of posts updated */
            sprintf(esc_html__('Updated: %d', 'import-export-acf'), absint($stats['updated'])),
            /* translators: %d: Number of posts skipped */
            sprintf(esc_html__('Skipped: %d', 'import-export-acf'), absint($stats['skipped'])),
            /* translators: %d: Number of posts that failed to import */
            sprintf(esc_html__('Failed: %d', 'import-export-acf'), absint($stats['failed']))
        );

        $this->add_admin_notice($message);
    }

    private function is_json($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    private function get_acf_field_key($field_label, $post_id) {
        // Cache ACF field keys
        $cache_key = 'acf_ie_field_' . md5($field_label . $post_id);
        $field_key = wp_cache_get($cache_key, 'import-export-acf');
        
        if (false === $field_key) {
            // Get all field groups for this post
            $field_groups = acf_get_field_groups(array('post_id' => $post_id));
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                foreach ($fields as $field) {
                    if ($field['label'] === $field_label) {
                        $field_key = $field['key'];
                        wp_cache_set($cache_key, $field_key, 'import-export-acf', HOUR_IN_SECONDS);
                        break 2;
                    }
                }
            }
        }
        
        return $field_key ? $field_key : false;
    }

    private function set_featured_image($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check if the image URL is from the same site
        $upload_dir = wp_upload_dir();
        $site_url = get_site_url();

        if (strpos($image_url, $site_url) === 0) {
            // Local image - get its ID from URL using cached function
            $attachment_id = $this->get_attachment_id_from_url($image_url);
        } else {
            // External image - download it
            // Cache the downloaded image ID
            $cache_key = 'acf_ie_ext_img_' . md5($image_url . $post_id);
            $attachment_id = wp_cache_get($cache_key, 'import-export-acf');
            
            if (false === $attachment_id) {
                $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
                if (!is_wp_error($attachment_id)) {
                    wp_cache_set($cache_key, $attachment_id, 'import-export-acf', HOUR_IN_SECONDS);
                }
            }
        }

        if (!is_wp_error($attachment_id) && $attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    private function get_cached_terms($taxonomy_name, $cache_group) {
        $cache_key = 'acf_ie_terms_' . md5($taxonomy_name);
        $terms = wp_cache_get($cache_key, $cache_group);
        
        if (false === $terms) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy_name,
                'hide_empty' => false,
                'fields' => 'id=>name'
            ));
            
            if (!is_wp_error($terms)) {
                wp_cache_set($cache_key, $terms, $cache_group, HOUR_IN_SECONDS);
            }
        }
        
        return $terms;
    }

    private function process_taxonomy_terms($post_id, $taxonomy_name, $term_names, $cache_group = 'import-export-acf') {
        // Get existing terms from cache
        $existing_terms = $this->get_cached_terms($taxonomy_name, $cache_group);
        
        // Process terms
        $term_ids = array();
        foreach ($term_names as $term_name) {
            $term_name = trim($term_name);
            if (empty($term_name)) {
                continue;
            }

            $term_id = array_search($term_name, $existing_terms);
            
            if (!$term_id) {
                // Term doesn't exist, create it
                $new_term = wp_insert_term($term_name, $taxonomy_name);
                if (!is_wp_error($new_term)) {
                    $term_id = $new_term['term_id'];
                    $existing_terms[$term_id] = $term_name;
                    // Update cache
                    wp_cache_set('acf_ie_terms_' . md5($taxonomy_name), $existing_terms, $cache_group, HOUR_IN_SECONDS);
                }
            }
            
            if ($term_id) {
                $term_ids[] = (int) $term_id;
            }
        }
        
        // Set terms for post
        if (!empty($term_ids)) {
            return wp_set_object_terms($post_id, $term_ids, $taxonomy_name);
        }
        
        return false;
    }

    private function log_error($message, $context = array()) {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        $log_message = sprintf(
            /* translators: 1: Error message, 2: Error context */
            esc_html__('ACF Import/Export Error: %1$s %2$s', 'import-export-acf'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        // Use wp_privacy_anonymize_data to ensure sensitive data is not logged
        $log_message = wp_privacy_anonymize_data($log_message, 'text');
        
        // Log using WordPress error handler
        if (function_exists('wp_debug_backtrace_summary')) {
            $backtrace = wp_debug_backtrace_summary();
            _doing_it_wrong(__FUNCTION__, esc_html($log_message), esc_html($backtrace));
        } else {
            _doing_it_wrong(__FUNCTION__, esc_html($log_message), '');
        }
    }
} 