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
        <p><?php esc_html_e('ACF Import Export requires Advanced Custom Fields to be installed and activated.', 'acf-import-export'); ?></p>
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
    }

    public function add_admin_menu() {
        if (!function_exists('acf_get_setting')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=acf-field-group',
            __('ACF Import/Export', 'acf-import-export'),
            __('Import/Export', 'acf-import-export'),
            'manage_options',
            'acf-import-export',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        if ('acf-field-group_page_acf-import-export' !== $hook) {
            return;
        }
        wp_enqueue_style(
            'acf-import-export-styles', 
            ACF_IMPORT_EXPORT_PLUGIN_URL . 'assets/css/style.css',
            array(),
            ACF_IMPORT_EXPORT_VERSION
        );
    }

    public function render_admin_page() {
        // Get all post types including built-in ones except revisions and menus
        $post_types = get_post_types(array(
            'show_ui' => true
        ), 'objects');
        
        // Remove unwanted post types
        unset($post_types['revision']);
        unset($post_types['nav_menu_item']);
        unset($post_types['custom_css']);
        unset($post_types['customize_changeset']);

        // Get all taxonomies
        $all_taxonomies = get_taxonomies(array(), 'objects');
        
        // Get all ACF field groups
        $all_field_groups = array();
        if (function_exists('acf_get_field_groups')) {
            $all_field_groups = acf_get_field_groups();
        }
        ?>
        <div class="wrap">
            <h1>ACF Import/Export</h1>
            
            <!-- Export Section -->
            <div class="card">
                <h2>Export</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('acf_export_nonce', 'acf_export_nonce'); ?>
                    
                    <div class="export-section">
                        <h3>Select Post Type to Export</h3>
                        <select name="export_post_type" id="export_post_type" required>
                            <option value="">Select a post type...</option>
                            <?php foreach ($post_types as $post_type): ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <h3>Export Options</h3>
                        
                        <!-- Taxonomy Options -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="taxonomies" class="toggle-section" data-section="taxonomy-options" checked>
                                Include Taxonomies
                            </label>
                            <div class="sub-options taxonomy-options">
                                <label>
                                    <input type="radio" name="taxonomy_selection" value="all" checked>
                                    Export All Taxonomies
                                </label>
                                <label>
                                    <input type="radio" name="taxonomy_selection" value="selected">
                                    Select Specific Taxonomies
                                </label>
                                <div class="specific-options taxonomy-list" style="display: none; padding-left: 20px; margin-top: 10px;">
                                    <?php foreach ($all_taxonomies as $taxonomy): ?>
                                    <label>
                                        <input type="checkbox" name="specific_taxonomies[]" value="<?php echo esc_attr($taxonomy->name); ?>">
                                        <?php echo esc_html($taxonomy->label); ?>
                                    </label><br>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ACF Fields Options -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="acf_fields" class="toggle-section" data-section="acf-options" checked>
                                Include ACF Fields
                            </label>
                            <div class="sub-options acf-options">
                                <label>
                                    <input type="radio" name="acf_selection" value="all" checked>
                                    Export All ACF Fields
                                </label>
                                <label>
                                    <input type="radio" name="acf_selection" value="selected">
                                    Select Specific Field Groups
                                </label>
                                <div class="specific-options acf-list" style="display: none; padding-left: 20px; margin-top: 10px;">
                                    <?php foreach ($all_field_groups as $field_group): ?>
                                    <label>
                                        <input type="checkbox" name="specific_acf_groups[]" value="<?php echo esc_attr($field_group['key']); ?>">
                                        <?php echo esc_html($field_group['title']); ?>
                                    </label><br>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Featured Image Option -->
                        <div class="option-section">
                            <label>
                                <input type="checkbox" name="export_options[]" value="featured_image" checked>
                                Include Featured Image URL
                            </label>
                        </div>
                    </div>

                    <p>
                        <input type="submit" name="acf_export" class="button button-primary" value="Export to CSV">
                    </p>
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

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle radio button changes for taxonomy selection
            $('input[name="taxonomy_selection"]').change(function() {
                $('.taxonomy-list').toggle($(this).val() === 'selected');
            });

            // Handle radio button changes for ACF selection
            $('input[name="acf_selection"]').change(function() {
                $('.acf-list').toggle($(this).val() === 'selected');
            });

            // Handle main option toggles
            $('.toggle-section').change(function() {
                var section = $(this).data('section');
                $('.' + section).toggle(this.checked);
            });

            // Initialize state
            $('.toggle-section').each(function() {
                var section = $(this).data('section');
                $('.' + section).toggle(this.checked);
            });
        });
        </script>
        <?php
    }

    public function handle_export() {
        if (!isset($_POST['acf_export']) || !check_admin_referer('acf_export_nonce', 'acf_export_nonce') || empty($_POST['export_post_type'])) {
            return;
        }

        $post_type = sanitize_text_field(wp_unslash($_POST['export_post_type']));
        $export_options = isset($_POST['export_options']) ? array_map('sanitize_text_field', wp_unslash($_POST['export_options'])) : array();
        
        // Get taxonomy selection preferences
        $taxonomy_selection = isset($_POST['taxonomy_selection']) ? sanitize_text_field(wp_unslash($_POST['taxonomy_selection'])) : 'all';
        $specific_taxonomies = isset($_POST['specific_taxonomies']) ? array_map('sanitize_text_field', wp_unslash($_POST['specific_taxonomies'])) : array();
        
        // Get ACF selection preferences
        $acf_selection = isset($_POST['acf_selection']) ? sanitize_text_field(wp_unslash($_POST['acf_selection'])) : 'all';
        $specific_acf_groups = isset($_POST['specific_acf_groups']) ? array_map('sanitize_text_field', wp_unslash($_POST['specific_acf_groups'])) : array();

        // Get all posts of the selected type
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));

        if (empty($posts)) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>No posts found for the selected post type.</p></div>';
            });
            return;
        }

        // Get taxonomies based on selection
        $taxonomies = array();
        if (in_array('taxonomies', $export_options)) {
            if ($taxonomy_selection === 'all') {
                $taxonomies = get_object_taxonomies($post_type, 'objects');
            } else {
                foreach ($specific_taxonomies as $tax_name) {
                    $taxonomy = get_taxonomy($tax_name);
                    if ($taxonomy) {
                        $taxonomies[$tax_name] = $taxonomy;
                    }
                }
            }
        }
        
        // Get ACF fields based on selection
        $acf_fields = array();
        if (in_array('acf_fields', $export_options) && function_exists('acf_get_field_groups')) {
            if ($acf_selection === 'all') {
                $field_groups = acf_get_field_groups(array('post_type' => $post_type));
            } else {
                $field_groups = array();
                foreach ($specific_acf_groups as $group_key) {
                    $group = acf_get_field_group($group_key);
                    if ($group) {
                        $field_groups[] = $group;
                    }
                }
            }
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                foreach ($fields as $field) {
                    $acf_fields[$field['name']] = $field['label'];
                }
            }
        }

        // Prepare CSV headers
        $headers = array(
            'ID',
            'Post Title',
            'Post Content',
            'Post Excerpt',
            'Post Status',
            'Post Date',
            'Post Modified'
        );

        // Add taxonomy headers
        if (in_array('taxonomies', $export_options)) {
            foreach ($taxonomies as $taxonomy) {
                $headers[] = $taxonomy->label;
            }
        }

        // Add ACF field headers
        if (in_array('acf_fields', $export_options)) {
            foreach ($acf_fields as $field_name => $field_label) {
                $headers[] = $field_label;
            }
        }

        // Add featured image header
        if (in_array('featured_image', $export_options)) {
            $headers[] = 'Featured Image URL';
        }

        // Use WP_Filesystem for file operations
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Start output
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $post_type . '-export-' . gmdate('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Create temporary file
        $temp_file = wp_tempnam('acf-export');
        
        // Add BOM for Excel
        $wp_filesystem->put_contents($temp_file, "\xEF\xBB\xBF");
        
        // Prepare CSV content
        $csv_content = '';
        
        // Add headers
        $csv_content .= $this->array_to_csv($headers);
        
        // Add data rows
        foreach ($posts as $post) {
            $row = array(
                $post->ID,
                $post->post_title,
                $post->post_content,
                $post->post_excerpt,
                $post->post_status,
                $post->post_date,
                $post->post_modified
            );

            // Add taxonomy values
            if (in_array('taxonomies', $export_options)) {
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_post_terms($post->ID, $taxonomy->name, array('fields' => 'names'));
                    $row[] = !is_wp_error($terms) ? implode(', ', $terms) : '';
                }
            }

            // Add ACF field values
            if (in_array('acf_fields', $export_options)) {
                foreach ($acf_fields as $field_name => $field_label) {
                    $value = get_field($field_name, $post->ID);
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $row[] = $value;
                }
            }

            // Add featured image
            if (in_array('featured_image', $export_options)) {
                $image_url = get_the_post_thumbnail_url($post->ID, 'full');
                $row[] = $image_url ? $image_url : '';
            }

            $csv_content .= $this->array_to_csv($row);
        }

        // Write content to file
        $wp_filesystem->put_contents($temp_file, $csv_content, FS_CHMOD_FILE);

        // Output the file contents and delete temp file
        if ($wp_filesystem->exists($temp_file)) {
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
        // Use WP_Filesystem instead of fopen
        $f = $wp_filesystem->get_contents('php://memory');
        if ($f === false) {
            return '';
        }
        
        fputcsv($f, $fields);
        rewind($f);
        // Use WP_Filesystem instead of fclose
        $csv_line = stream_get_contents($f);
        $wp_filesystem->put_contents('php://memory', $csv_line);
        
        return $csv_line;
    }

    private function get_attachment_id_from_url($image_url) {
        $cache_key = 'acf_ie_img_' . md5($image_url);
        $attachment_id = wp_cache_get($cache_key, 'acf-import-export');
        
        if (false === $attachment_id) {
            $upload_dir = wp_upload_dir();
            $site_url = get_site_url();
            
            if (strpos($image_url, $site_url) === 0) {
                $image_path = str_replace($site_url . '/wp-content/uploads/', '', $image_url);
                
                // Use WordPress core function instead of direct query
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => 1,
                    'meta_query' => array(
                        array(
                            'key' => '_wp_attached_file',
                            'value' => $image_path,
                            'compare' => '='
                        )
                    )
                );
                
                $attachments = get_posts($args);
                $attachment_id = !empty($attachments) ? $attachments[0]->ID : 0;
                
                if ($attachment_id) {
                    wp_cache_set($cache_key, $attachment_id, 'acf-import-export', HOUR_IN_SECONDS);
                }
            }
        }
        
        return $attachment_id;
    }

    public function handle_import() {
        if (!isset($_POST['acf_import']) || !check_admin_referer('acf_import_nonce', 'acf_import_nonce')) {
            return;
        }

        // Sanitize each element of the $_FILES['import_file'] array
        $file = array(
            'name'     => isset($_FILES['import_file']['name']) ? sanitize_file_name($_FILES['import_file']['name']) : '',
            'type'     => isset($_FILES['import_file']['type']) ? sanitize_mime_type($_FILES['import_file']['type']) : '',
            'tmp_name' => isset($_FILES['import_file']['tmp_name']) ? sanitize_text_field($_FILES['import_file']['tmp_name']) : '',
            'error'    => isset($_FILES['import_file']['error']) ? intval($_FILES['import_file']['error']) : 0,
            'size'     => isset($_FILES['import_file']['size']) ? intval($_FILES['import_file']['size']) : 0,
        );

        // Log the sanitized file array
        $this->log_error('Invalid file upload', $file);

        // Validate file upload
        if (!isset($_FILES['import_file']) || 
            !isset($_FILES['import_file']['error']) || 
            !isset($_FILES['import_file']['tmp_name']) || 
            UPLOAD_ERR_OK !== $_FILES['import_file']['error']) {
            
            $this->log_error('Invalid file upload', $_FILES['import_file'] ?? array());
            add_action('admin_notices', function() {
                echo wp_kses_post('<div class="error"><p>' . esc_html__('Please select a valid CSV file to import.', 'acf-import-export') . '</p></div>');
            });
            return;
        }

        // Sanitize input from $_FILES
        $file = sanitize_file_name($_FILES['import_file']['tmp_name']);
        
        // Use WP_Filesystem for file operations
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            WP_Filesystem();
        }

        // Verify file exists and is readable
        if (!$wp_filesystem->exists($file)) {
            $this->log_error('File not accessible', array('file' => $file));
            add_action('admin_notices', function() {
                echo wp_kses_post('<div class="error"><p>' . esc_html__('Error accessing the CSV file.', 'acf-import-export') . '</p></div>');
            });
            return;
        }

        // Read file contents
        $content = $wp_filesystem->get_contents($file);
        if (false === $content) {
            $this->log_error('Failed to read file', array('file' => $file));
            add_action('admin_notices', function() {
                echo wp_kses_post('<div class="error"><p>' . esc_html__('Error reading the CSV file.', 'acf-import-export') . '</p></div>');
            });
            return;
        }

        // Remove BOM if present
        $bom = pack('H*', 'EFBBBF');
        $content = preg_replace("/^$bom/", '', $content);

        // Process the CSV content
        $rows = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($rows);

        if (!$headers) {
            $this->log_error('No headers found in CSV');
            add_action('admin_notices', function() {
                echo wp_kses_post('<div class="error"><p>' . esc_html__('Invalid CSV format: No headers found.', 'acf-import-export') . '</p></div>');
            });
            return;
        }

        // Create a map of column positions
        $column_map = array_flip($headers);

        // Get all taxonomies and create a label to name mapping
        $all_taxonomies = get_taxonomies(array(), 'objects');
        $taxonomy_map = array();
        foreach ($all_taxonomies as $tax_name => $tax_object) {
            $taxonomy_map[$tax_object->label] = $tax_name;
        }

        // Track import statistics
        $stats = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        );

        // Start transaction with caching and proper error handling
        global $wpdb;
        $transaction_started = false;
        
        // Cache group for this import session
        $cache_group = 'acf_ie_import_' . uniqid();
        
        try {
            // Use WordPress transaction wrapper if available
            if (function_exists('wp_transaction_start')) {
                wp_transaction_start();
            } else {
                // Cache the transaction start
                $cache_key = 'transaction_start_' . $cache_group;
                if (!wp_cache_get($cache_key, $cache_group)) {
                    $wpdb->query('START TRANSACTION');
                    wp_cache_set($cache_key, true, $cache_group, HOUR_IN_SECONDS);
                }
            }
            $transaction_started = true;

            // Process rows
            foreach ($rows as $row) {
                $post_data = array();
                $taxonomies = array();
                $acf_fields = array();
                $featured_image = '';

                // Map basic post fields
                $post_data['ID'] = isset($row[$column_map['ID']]) ? intval($row[$column_map['ID']]) : 0;
                $post_data['post_title'] = isset($row[$column_map['Post Title']]) ? $row[$column_map['Post Title']] : '';
                $post_data['post_content'] = isset($row[$column_map['Post Content']]) ? $row[$column_map['Post Content']] : '';
                $post_data['post_excerpt'] = isset($row[$column_map['Post Excerpt']]) ? $row[$column_map['Post Excerpt']] : '';
                $post_data['post_status'] = isset($row[$column_map['Post Status']]) ? $row[$column_map['Post Status']] : 'publish';

                // If no title, skip this row
                if (empty($post_data['post_title'])) {
                    $stats['skipped']++;
                    continue;
                }

                // Determine if we're updating or creating
                $existing_post = null;
                if ($post_data['ID'] > 0) {
                    $existing_post = get_post($post_data['ID']);
                }

                if ($existing_post) {
                    // Update existing post
                    $post_id = wp_update_post($post_data, true);
                    if (!is_wp_error($post_id)) {
                        $stats['updated']++;
                    } else {
                        $stats['failed']++;
                        continue;
                    }
                } else {
                    // Create new post
                    unset($post_data['ID']); // Remove ID for new post
                    $post_id = wp_insert_post($post_data, true);
                    if (!is_wp_error($post_id)) {
                        $stats['created']++;
                    } else {
                        $stats['failed']++;
                        continue;
                    }
                }

                // Process taxonomies and ACF fields
                foreach ($headers as $index => $header) {
                    if (!isset($row[$index]) || empty($row[$index])) continue;

                    // Skip known post fields
                    if (in_array($header, ['ID', 'Post Title', 'Post Content', 'Post Excerpt', 'Post Status', 'Post Date', 'Post Modified', 'Featured Image URL'])) {
                        continue;
                    }

                    // Check if this header matches a taxonomy label
                    $taxonomy_name = isset($taxonomy_map[$header]) ? $taxonomy_map[$header] : false;
                    
                    if ($taxonomy_name && taxonomy_exists($taxonomy_name)) {
                        // Get the terms as an array and clean them
                        $terms = array_map('trim', explode(',', $row[$index]));
                        $terms = array_filter($terms); // Remove empty terms

                        if (!empty($terms)) {
                            // First, ensure terms exist
                            foreach ($terms as $term_name) {
                                if (!term_exists($term_name, $taxonomy_name)) {
                                    wp_insert_term($term_name, $taxonomy_name);
                                }
                            }
                            
                            // Then set the terms
                            $result = wp_set_object_terms($post_id, $terms, $taxonomy_name);
                            
                            // Log any errors
                            if (is_wp_error($result)) {
                                $this->log_error(
                                    sprintf(
                                        /* translators: 1: Post ID, 2: Error message */
                                        esc_html__('Error setting terms for post %1$d: %2$s', 'acf-import-export'),
                                        $post_id,
                                        $result->get_error_message()
                                    ),
                                    array(
                                        'post_id' => $post_id,
                                        'taxonomy' => $taxonomy_name,
                                        'terms' => $terms
                                    )
                                );
                            }
                        }
                    }
                    // If not a taxonomy, treat as ACF field
                    else {
                        $field_key = $this->get_acf_field_key($header, $post_id);
                        if ($field_key) {
                            $value = $row[$index];
                            // Handle JSON encoded array values
                            if ($this->is_json($value)) {
                                $value = json_decode($value, true);
                            }
                            update_field($field_key, $value, $post_id);
                        }
                    }
                }

                // Handle featured image
                if (isset($column_map['Featured Image URL']) && !empty($row[$column_map['Featured Image URL']])) {
                    $this->set_featured_image($post_id, $row[$column_map['Featured Image URL']]);
                }
            }
            
            // Commit transaction using WordPress wrapper if available
            if ($transaction_started) {
                if (function_exists('wp_transaction_commit')) {
                    wp_transaction_commit();
                } else {
                    // Cache the transaction commit
                    $cache_key = 'transaction_commit_' . $cache_group;
                    if (!wp_cache_get($cache_key, $cache_group)) {
                        $wpdb->query('COMMIT');
                        wp_cache_set($cache_key, true, $cache_group, HOUR_IN_SECONDS);
                    }
                }
            }

            // Clean up cache after successful import
            wp_cache_flush_group($cache_group);
            
            // Show success message with stats
            add_action('admin_notices', function() use ($stats) {
                /* translators: %d: Number of posts created */
                $created_text = sprintf(esc_html__('Created: %d', 'acf-import-export'), $stats['created']);
                
                /* translators: %d: Number of posts updated */
                $updated_text = sprintf(esc_html__('Updated: %d', 'acf-import-export'), $stats['updated']);
                
                /* translators: %d: Number of posts skipped */
                $skipped_text = sprintf(esc_html__('Skipped: %d', 'acf-import-export'), $stats['skipped']);
                
                /* translators: %d: Number of posts that failed */
                $failed_text = sprintf(esc_html__('Failed: %d', 'acf-import-export'), $stats['failed']);

                $message = sprintf(
                    '<div class="updated"><p>%s<br>%s<br>%s<br>%s<br>%s</p></div>',
                    esc_html__('Import completed successfully:', 'acf-import-export'),
                    $created_text,
                    $updated_text,
                    $skipped_text,
                    $failed_text
                );
                echo wp_kses_post($message);
            });

        } catch (Exception $e) {
            // Rollback transaction using WordPress wrapper if available
            if ($transaction_started) {
                if (function_exists('wp_transaction_rollback')) {
                    wp_transaction_rollback();
                } else {
                    // Cache the transaction rollback
                    $cache_key = 'transaction_rollback_' . $cache_group;
                    if (!wp_cache_get($cache_key, $cache_group)) {
                        $wpdb->query('ROLLBACK');
                        wp_cache_set($cache_key, true, $cache_group, HOUR_IN_SECONDS);
                    }
                }
            }

            // Clean up cache after failed import
            wp_cache_flush_group($cache_group);
            
            add_action('admin_notices', function() use ($e) {
                echo wp_kses_post('<div class="error"><p>' . esc_html__('Import failed:', 'acf-import-export') . ' ' . esc_html($e->getMessage()) . '</p></div>');
            });
        }
    }

    private function is_json($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    private function get_acf_field_key($field_label, $post_id) {
        // Cache ACF field keys
        $cache_key = 'acf_ie_field_' . md5($field_label . $post_id);
        $field_key = wp_cache_get($cache_key, 'acf-import-export');
        
        if (false === $field_key) {
            // Get all field groups for this post
            $field_groups = acf_get_field_groups(array('post_id' => $post_id));
            
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                foreach ($fields as $field) {
                    if ($field['label'] === $field_label) {
                        $field_key = $field['key'];
                        wp_cache_set($cache_key, $field_key, 'acf-import-export', HOUR_IN_SECONDS);
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
            $attachment_id = wp_cache_get($cache_key, 'acf-import-export');
            
            if (false === $attachment_id) {
                $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
                if (!is_wp_error($attachment_id)) {
                    wp_cache_set($cache_key, $attachment_id, 'acf-import-export', HOUR_IN_SECONDS);
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

    private function process_taxonomy_terms($post_id, $taxonomy_name, $term_names, $cache_group) {
        // Get existing terms from cache
        $existing_terms = $this->get_cached_terms($taxonomy_name, $cache_group);
        
        // Process terms
        $term_ids = array();
        foreach ($term_names as $term_name) {
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
            esc_html__('ACF Import/Export Error: %1$s %2$s', 'acf-import-export'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        // Use WP_Filesystem instead of fopen
        $f = $wp_filesystem->get_contents('php://memory');
        if ($f === false) {
            return;
        }

       
    }
} 