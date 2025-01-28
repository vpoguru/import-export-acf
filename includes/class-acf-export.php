<?php
class ACF_Export {
    public function __construct() {
        add_action('admin_post_acf_export_data', array($this, 'handle_export'));
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'acf-import-export'));
        }

        if (!isset($_POST['acf_export_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['acf_export_nonce'])), 'acf_export_nonce')) {
            wp_die(esc_html__('Invalid nonce specified', 'acf-import-export'));
        }

        $export_type = isset($_POST['export_type']) ? sanitize_text_field(wp_unslash($_POST['export_type'])) : 'structure';
        
        if ($export_type === 'content') {
            $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
            $selected_columns = isset($_POST['columns']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['columns'])) : array();
            
            if (empty($post_type)) {
                wp_die(esc_html__('No post type selected for content export.', 'acf-import-export'));
            }
            
            $this->export_post_type_content($post_type, $selected_columns);
            exit;
        }

        // Original structure export code
        $export_data = array();
        $export_items = isset($_POST['export_items']) ? array_map('sanitize_text_field', wp_unslash((array)$_POST['export_items'])) : array();

        if (in_array('field_groups', $export_items)) {
            $export_data['field_groups'] = $this->export_field_groups();
        }

        if (in_array('post_types', $export_items)) {
            $export_data['post_types'] = $this->export_post_types();
        }

        if (in_array('taxonomies', $export_items)) {
            $export_data['taxonomies'] = $this->export_taxonomies();
        }

        $this->download_csv_file($export_data);
    }

    private function export_field_groups() {
        $field_groups = acf_get_field_groups();
        $export_field_groups = array();

        foreach ($field_groups as $field_group) {
            $field_group['fields'] = acf_get_fields($field_group);
            $export_field_groups[] = $field_group;
        }

        return $export_field_groups;
    }

    private function export_post_types() {
        $post_types = get_post_types(array('_builtin' => false), 'objects');
        $export_post_types = array();

        foreach ($post_types as $post_type) {
            $export_post_types[$post_type->name] = array(
                'labels' => get_post_type_labels($post_type),
                'args' => get_post_type_object($post_type)->rewrite,
                'supports' => get_all_post_type_supports($post_type->name),
                'taxonomies' => get_object_taxonomies($post_type->name),
            );
        }

        return $export_post_types;
    }

    private function export_taxonomies() {
        $taxonomies = get_taxonomies(array('_builtin' => false), 'objects');
        $export_taxonomies = array();

        foreach ($taxonomies as $taxonomy) {
            $export_taxonomies[$taxonomy->name] = array(
                'labels' => get_taxonomy_labels($taxonomy),
                'args' => get_taxonomy($taxonomy->name),
                'object_type' => $taxonomy->object_type,
            );
        }

        return $export_taxonomies;
    }

    private function export_post_type_content($post_type, $selected_columns) {
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'any'
        );

        $posts = get_posts($args);
        $data = array();
        $headers = array();
        $acf_fields = array();

        // Get all ACF fields for this post type
        $field_groups = acf_get_field_groups(array('post_type' => $post_type));
        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            foreach ($fields as $field) {
                $acf_fields[$field['name']] = $field;
            }
        }

        // Build available columns
        $wp_columns = array(
            'ID' => 'ID',
            'post_title' => 'Title',
            'post_content' => 'Content',
            'post_excerpt' => 'Excerpt',
            'post_status' => 'Status',
            'post_date' => 'Date',
            'post_author' => 'Author'
        );

        // Get taxonomies
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $tax_columns = array();
        foreach ($taxonomies as $tax) {
            $tax_columns['tax_' . $tax->name] = $tax->label;
        }

        // If no columns selected, use all
        if (empty($selected_columns)) {
            $selected_columns = array_merge(
                array_keys($wp_columns),
                array_keys($tax_columns),
                array_keys($acf_fields)
            );
        }

        // Build headers
        foreach ($selected_columns as $column) {
            if (isset($wp_columns[$column])) {
                $headers[] = $wp_columns[$column];
            } elseif (isset($tax_columns[$column])) {
                $headers[] = $tax_columns[substr($column, 4)];
            } elseif (isset($acf_fields[$column])) {
                $headers[] = $acf_fields[$column]['label'];
            }
        }

        // Build data rows
        foreach ($posts as $post) {
            $row = array();
            foreach ($selected_columns as $column) {
                if (isset($wp_columns[$column])) {
                    // WordPress core fields
                    $row[] = $post->$column;
                } elseif (strpos($column, 'tax_') === 0) {
                    // Taxonomy terms with hierarchy
                    $tax_name = substr($column, 4);
                    $terms = wp_get_post_terms($post->ID, $tax_name, array('orderby' => 'parent'));
                    
                    // Build hierarchical term paths
                    $term_paths = array();
                    foreach ($terms as $term) {
                        $term_path = array($term->name);
                        $parent_id = $term->parent;
                        
                        // Build the full hierarchical path
                        while ($parent_id) {
                            $parent = get_term($parent_id, $tax_name);
                            if ($parent && !is_wp_error($parent)) {
                                array_unshift($term_path, $parent->name);
                                $parent_id = $parent->parent;
                            } else {
                                break;
                            }
                        }
                        
                        $term_paths[] = implode('|', $term_path);
                    }
                    
                    $row[] = implode(',', $term_paths);
                } else {
                    // ACF fields
                    $field = $acf_fields[$column];
                    $value = get_field($column, $post->ID);
                    
                    // Handle different ACF field types
                    switch ($field['type']) {
                        case 'taxonomy':
                            // Handle taxonomy fields similar to regular taxonomies
                            if (is_array($value)) {
                                $term_paths = array();
                                foreach ($value as $term) {
                                    $term_path = array($term->name);
                                    $parent_id = $term->parent;
                                    while ($parent_id) {
                                        $parent = get_term($parent_id, $field['taxonomy']);
                                        if ($parent && !is_wp_error($parent)) {
                                            array_unshift($term_path, $parent->name);
                                            $parent_id = $parent->parent;
                                        } else {
                                            break;
                                        }
                                    }
                                    $term_paths[] = implode('|', $term_path);
                                }
                                $value = implode(',', $term_paths);
                            }
                            break;
                            
                        case 'relationship':
                        case 'post_object':
                            // Handle related posts with hierarchy
                            if (is_array($value)) {
                                $post_paths = array();
                                foreach ($value as $related_post) {
                                    $post_path = array($related_post->post_title);
                                    $parent_id = $related_post->post_parent;
                                    while ($parent_id) {
                                        $parent = get_post($parent_id);
                                        if ($parent) {
                                            array_unshift($post_path, $parent->post_title);
                                            $parent_id = $parent->post_parent;
                                        } else {
                                            break;
                                        }
                                    }
                                    $post_paths[] = implode('|', $post_path);
                                }
                                $value = implode(',', $post_paths);
                            } elseif (is_object($value)) {
                                $post_path = array($value->post_title);
                                $parent_id = $value->post_parent;
                                while ($parent_id) {
                                    $parent = get_post($parent_id);
                                    if ($parent) {
                                        array_unshift($post_path, $parent->post_title);
                                        $parent_id = $parent->post_parent;
                                    } else {
                                        break;
                                    }
                                }
                                $value = implode('|', $post_path);
                            }
                            break;
                            
                        case 'repeater':
                        case 'group':
                        case 'flexible_content':
                            // For complex fields, serialize the data
                            $value = maybe_serialize($value);
                            break;
                            
                        default:
                            // For other field types, convert arrays to comma-separated values
                            if (is_array($value)) {
                                $value = implode(',', $value);
                            }
                    }
                    
                    $row[] = $value;
                }
            }
            $data[] = $row;
        }

        // Create temporary file
        global $wp_filesystem;
        WP_Filesystem();

        $filename = sanitize_file_name($post_type . '-export-' . gmdate('Y-m-d') . '.csv');
        $upload_dir = wp_upload_dir();
        $temp_file = trailingslashit($upload_dir['path']) . $filename;

        // Write headers and data
        $csv_content = $this->generate_csv_row($headers);
        foreach ($data as $row) {
            $csv_content .= $this->generate_csv_row(array_map('esc_html', $row));
        }

        $wp_filesystem->put_contents($temp_file, $csv_content);

        // Send headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file contents using WP_Filesystem
        if (!$wp_filesystem->exists($temp_file)) {
            wp_die(esc_html__('Error: Temporary file not found.', 'acf-import-export'));
        }
        
        // For CSV files, we need to preserve the exact content
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data should not be escaped to maintain format
        echo $wp_filesystem->get_contents($temp_file);

        // Clean up using WP_Filesystem
        $wp_filesystem->delete($temp_file);
        exit;
    }

    private function generate_csv_row($fields) {
        $escaped_fields = array_map(function($field) {
            $field = str_replace('"', '""', $field);
            return '"' . $field . '"';
        }, $fields);
        return implode(',', $escaped_fields) . "\n";
    }

    private function download_csv_file($export_data) {
        global $wp_filesystem;
        WP_Filesystem();

        $filename = 'acf-export-' . gmdate('Y-m-d') . '.csv';
        $upload_dir = wp_upload_dir();
        $temp_file = trailingslashit($upload_dir['path']) . $filename;
        
        $csv_data = array();
        $csv_data[] = array('Type', 'Key', 'Title', 'Fields');
        
        // Write field groups
        if (isset($export_data['field_groups'])) {
            foreach ($export_data['field_groups'] as $group) {
                $fields = array();
                if (isset($group['fields'])) {
                    foreach ($group['fields'] as $field) {
                        $fields[] = $field['name'] . ':' . $field['type'];
                    }
                }
                $csv_data[] = array(
                    'field_group',
                    $group['key'],
                    $group['title'],
                    implode('|', $fields)
                );
            }
        }
        
        // Write post types
        if (isset($export_data['post_types'])) {
            foreach ($export_data['post_types'] as $key => $post_type) {
                $csv_data[] = array(
                    'post_type',
                    $key,
                    $post_type['labels']->name,
                    implode('|', $post_type['supports'])
                );
            }
        }
        
        // Write taxonomies
        if (isset($export_data['taxonomies'])) {
            foreach ($export_data['taxonomies'] as $key => $taxonomy) {
                $csv_data[] = array(
                    'taxonomy',
                    $key,
                    $taxonomy['labels']->name,
                    implode('|', $taxonomy['object_type'])
                );
            }
        }

        // Convert data to CSV string
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= $this->generate_csv_row($row);
        }

        // Write to temporary file
        $wp_filesystem->put_contents($temp_file, $csv_content);

        // Send headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file contents using WP_Filesystem
        if (!$wp_filesystem->exists($temp_file)) {
            wp_die(esc_html__('Error: Temporary file not found.', 'acf-import-export'));
        }
        
        // For CSV files, we need to preserve the exact content
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data should not be escaped to maintain format
        echo $wp_filesystem->get_contents($temp_file);

        // Clean up using WP_Filesystem
        $wp_filesystem->delete($temp_file);
        exit;
    }
} 