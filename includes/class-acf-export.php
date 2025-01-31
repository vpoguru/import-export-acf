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
            $tax_columns['tax_' . $tax->name] = $tax->labels->name;
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
                $headers[] = $tax_columns[$column];
            } elseif (isset($acf_fields[$column])) {
                $headers[] = $acf_fields[$column]['label'];
            }
        }

        // Build data rows
        foreach ($posts as $post) {
            $row = array();
            foreach ($selected_columns as $column) {
                $value = '';
                
                // Handle WordPress core fields
                if (isset($wp_columns[$column])) {
                    switch ($column) {
                        case 'ID':
                            $value = $post->ID;
                            break;
                        case 'post_title':
                            $value = $post->post_title;
                            break;
                        case 'post_content':
                            $value = $post->post_content;
                            break;
                        case 'post_excerpt':
                            $value = $post->post_excerpt;
                            break;
                        case 'post_status':
                            $value = $post->post_status;
                            break;
                        case 'post_date':
                            $value = $post->post_date;
                            break;
                        case 'post_author':
                            $author = get_user_by('id', $post->post_author);
                            $value = $author ? $author->display_name : '';
                            break;
                    }
                }
                // Handle taxonomies
                elseif (strpos($column, 'tax_') === 0) {
                    $taxonomy = substr($column, 4);
                    $terms = wp_get_object_terms($post->ID, $taxonomy, array('orderby' => 'parent'));
                    if (!is_wp_error($terms)) {
                        $term_paths = array();
                        foreach ($terms as $term) {
                            $term_path = array($term->name);
                            $parent_id = $term->parent;
                            
                            // Build the full hierarchical path
                            while ($parent_id) {
                                $parent = get_term($parent_id, $taxonomy);
                                if ($parent && !is_wp_error($parent)) {
                                    array_unshift($term_path, $parent->name);
                                    $parent_id = $parent->parent;
                                } else {
                                    break;
                                }
                            }
                            $term_paths[] = implode('/', $term_path);
                        }
                        $value = implode('|', $term_paths);
                    }
                }
                // Handle ACF fields
                elseif (isset($acf_fields[$column])) {
                    $field = $acf_fields[$column];
                    $field_value = get_field($field['name'], $post->ID);
                    
                    switch ($field['type']) {
                        case 'taxonomy':
                            if (!empty($field_value)) {
                                if (is_array($field_value)) {
                                    $term_names = array();
                                    foreach ($field_value as $term) {
                                        if (is_object($term)) {
                                            $term_names[] = $term->name;
                                        } elseif (is_numeric($term)) {
                                            $term_obj = get_term($term);
                                            if ($term_obj && !is_wp_error($term_obj)) {
                                                $term_names[] = $term_obj->name;
                                            }
                                        }
                                    }
                                    $value = implode(',', $term_names);
                                }
                            }
                            break;
                            
                        case 'relationship':
                        case 'post_object':
                            if (!empty($field_value)) {
                                if (is_array($field_value)) {
                                    $post_titles = array();
                                    foreach ($field_value as $related_post) {
                                        if (is_object($related_post)) {
                                            $post_titles[] = $related_post->post_title;
                                        } elseif (is_numeric($related_post)) {
                                            $post_obj = get_post($related_post);
                                            if ($post_obj) {
                                                $post_titles[] = $post_obj->post_title;
                                            }
                                        }
                                    }
                                    $value = implode(',', $post_titles);
                                } elseif (is_object($field_value)) {
                                    $value = $field_value->post_title;
                                } elseif (is_numeric($field_value)) {
                                    $post_obj = get_post($field_value);
                                    if ($post_obj) {
                                        $value = $post_obj->post_title;
                                    }
                                }
                            }
                            break;
                            
                        case 'repeater':
                        case 'group':
                        case 'flexible_content':
                            $value = json_encode($field_value);
                            break;
                            
                        default:
                            if (is_array($field_value)) {
                                $value = implode(',', $field_value);
                            } else {
                                $value = $field_value;
                            }
                    }
                }
                
                $row[] = $value;
            }
            $data[] = $row;
        }

        // Create temporary file
        $filename = sanitize_file_name($post_type . '-export-' . gmdate('Y-m-d') . '.csv');
        $upload_dir = wp_upload_dir();
        $temp_file = trailingslashit($upload_dir['path']) . $filename;

        // Write headers and data
        $csv_content = $this->generate_csv_row($headers);
        foreach ($data as $row) {
            $csv_content .= $this->generate_csv_row(array_map('esc_html', $row));
        }

        // Write to file using WP_Filesystem
        global $wp_filesystem;
        WP_Filesystem();
        $wp_filesystem->put_contents($temp_file, $csv_content);

        // Send headers
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output file contents
        if (!$wp_filesystem->exists($temp_file)) {
            wp_die(esc_html__('Error: Temporary file not found.', 'acf-import-export'));
        }
        
        echo $wp_filesystem->get_contents($temp_file);

        // Clean up
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