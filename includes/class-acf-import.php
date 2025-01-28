<?php
class ACF_Import {
    public function __construct() {
        add_action('admin_post_acf_import_data', array($this, 'handle_import'));
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'acf-import-export'));
        }

        if (!isset($_POST['acf_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['acf_import_nonce'])), 'acf_import_nonce')) {
            wp_die(esc_html__('Invalid nonce specified', 'acf-import-export'));
        }

        if (!isset($_FILES['import_file'])) {
            wp_die(esc_html__('No file was uploaded.', 'acf-import-export'));
        }

        $file = array_map('sanitize_text_field', wp_unslash($_FILES['import_file']));
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_die(esc_html__('Error uploading file.', 'acf-import-export'));
        }

        $file_extension = strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION));
        
        if ($file_extension !== 'csv') {
            wp_die(esc_html__('Only CSV files are supported.', 'acf-import-export'));
        }

        $import_type = isset($_POST['import_type']) ? sanitize_text_field(wp_unslash($_POST['import_type'])) : 'structure';
        
        if ($import_type === 'content') {
            $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
            if (empty($post_type)) {
                wp_die(esc_html__('No post type selected for content import.', 'acf-import-export'));
            }
            
            $results = $this->import_post_type_content($file['tmp_name'], $post_type);
        } else {
            $import_data = $this->parse_csv_file($file['tmp_name']);

            $results = array(
                'success' => array(),
                'errors' => array()
            );

            // Import field groups
            if (isset($import_data['field_groups'])) {
                $results['field_groups'] = $this->import_field_groups($import_data['field_groups']);
            }

            // Import post types
            if (isset($import_data['post_types'])) {
                $results['post_types'] = $this->import_post_types($import_data['post_types']);
            }

            // Import taxonomies
            if (isset($import_data['taxonomies'])) {
                $results['taxonomies'] = $this->import_taxonomies($import_data['taxonomies']);
            }
        }

        // Redirect back with results
        $redirect_url = add_query_arg(array(
            'page' => 'acf-import-export',
            'import_status' => 'complete',
            'success_count' => count($results['success']),
            'error_count' => count($results['errors'])
        ), admin_url('edit.php?post_type=acf-field-group'));

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function parse_csv_file($file_path) {
        global $wp_filesystem;
        WP_Filesystem();

        $import_data = array(
            'field_groups' => array(),
            'post_types' => array(),
            'taxonomies' => array()
        );

        $file_content = $wp_filesystem->get_contents($file_path);
        $rows = array_map('str_getcsv', explode("\n", $file_content));
        
        // Remove empty rows
        $rows = array_filter($rows);
        
        // Skip header row
        array_shift($rows);
        
        foreach ($rows as $data) {
            if (count($data) < 4) continue;
            
            list($type, $key, $title, $fields_str) = $data;
            
            switch ($type) {
                case 'field_group':
                    $fields = array();
                    $field_parts = explode('|', $fields_str);
                    foreach ($field_parts as $field_part) {
                        list($name, $type) = explode(':', $field_part);
                        $fields[] = array(
                            'key' => 'field_' . uniqid(),
                            'name' => $name,
                            'type' => $type,
                            'label' => ucfirst($name)
                        );
                    }
                    
                    $import_data['field_groups'][] = array(
                        'key' => $key,
                        'title' => $title,
                        'fields' => $fields
                    );
                    break;
                    
                case 'post_type':
                    $supports = explode('|', $fields_str);
                    $import_data['post_types'][$key] = array(
                        'labels' => (object)array('name' => $title),
                        'args' => array(
                            'public' => true,
                            'show_ui' => true
                        ),
                        'supports' => array_combine($supports, array_fill(0, count($supports), true)),
                        'taxonomies' => array()
                    );
                    break;
                    
                case 'taxonomy':
                    $object_types = explode('|', $fields_str);
                    $import_data['taxonomies'][$key] = array(
                        'labels' => (object)array('name' => $title),
                        'args' => array(
                            'public' => true,
                            'show_ui' => true
                        ),
                        'object_type' => $object_types
                    );
                    break;
            }
        }

        return $import_data;
    }

    private function import_field_groups($field_groups) {
        $results = array(
            'success' => array(),
            'errors' => array()
        );

        foreach ($field_groups as $field_group) {
            // Remove any existing field group with the same key
            $existing = acf_get_field_group($field_group['key']);
            if ($existing) {
                acf_delete_field_group($existing['ID']);
            }

            // Import the field group
            $field_group = acf_import_field_group($field_group);
            
            if ($field_group) {
                $results['success'][] = sprintf(
                    /* translators: %s: The title of the ACF field group that was successfully imported */
                    esc_html__('Imported field group: %s', 'acf-import-export'),
                    esc_html($field_group['title'])
                );
            } else {
                $results['errors'][] = sprintf(
                    /* translators: %s: The title of the ACF field group that failed to import */
                    esc_html__('Failed to import field group: %s', 'acf-import-export'),
                    esc_html($field_group['title'])
                );
            }
        }

        return $results;
    }

    private function import_post_types($post_types) {
        $results = array(
            'success' => array(),
            'errors' => array()
        );

        foreach ($post_types as $post_type => $args) {
            // Prepare post type arguments
            $post_type_args = array_merge($args['args'], array(
                'labels' => $args['labels'],
                'supports' => array_keys($args['supports']),
                'taxonomies' => $args['taxonomies']
            ));

            // Register the post type
            $registered = register_post_type($post_type, $post_type_args);

            if (!is_wp_error($registered)) {
                $results['success'][] = sprintf(
                    /* translators: %s: The name of the custom post type that was successfully imported */
                    esc_html__('Imported post type: %s', 'acf-import-export'),
                    esc_html($post_type)
                );
            } else {
                $results['errors'][] = sprintf(
                    /* translators: %s: The name of the custom post type that failed to import */
                    esc_html__('Failed to import post type: %s', 'acf-import-export'),
                    esc_html($post_type)
                );
            }
        }

        return $results;
    }

    private function import_taxonomies($taxonomies) {
        $results = array(
            'success' => array(),
            'errors' => array()
        );

        foreach ($taxonomies as $taxonomy => $data) {
            // Prepare taxonomy arguments
            $tax_args = array_merge($data['args'], array(
                'labels' => $data['labels'],
                'object_type' => $data['object_type']
            ));

            // Register the taxonomy
            $registered = register_taxonomy($taxonomy, $data['object_type'], $tax_args);

            if (!is_wp_error($registered)) {
                $results['success'][] = sprintf(
                    /* translators: %s: The name of the taxonomy that was successfully imported */
                    esc_html__('Imported taxonomy: %s', 'acf-import-export'),
                    esc_html($taxonomy)
                );
            } else {
                $results['errors'][] = sprintf(
                    /* translators: %s: The name of the taxonomy that failed to import */
                    esc_html__('Failed to import taxonomy: %s', 'acf-import-export'),
                    esc_html($taxonomy)
                );
            }
        }

        return $results;
    }

    private function import_post_type_content($file_path, $post_type) {
        global $wp_filesystem;
        WP_Filesystem();

        $results = array(
            'success' => array(),
            'errors' => array()
        );

        $file_content = $wp_filesystem->get_contents($file_path);
        $rows = array_map('str_getcsv', explode("\n", $file_content));
        
        // Remove empty rows
        $rows = array_filter($rows);
        
        $headers = array_shift($rows);
        $data = array();
        
        foreach ($rows as $row) {
            if (!empty($row)) {
                $data[] = array_combine($headers, $row);
            }
        }

        foreach ($data as $row) {
            $post_data = array(
                'post_type' => $post_type,
                'post_status' => 'publish'
            );

            // Map standard WordPress fields
            $wp_fields = array(
                'ID' => 'ID',
                'Title' => 'post_title',
                'Content' => 'post_content',
                'Excerpt' => 'post_excerpt',
                'Status' => 'post_status',
                'Date' => 'post_date',
                'Author' => 'post_author'
            );

            foreach ($wp_fields as $header => $wp_field) {
                if (isset($row[$header])) {
                    $post_data[$wp_field] = $row[$header];
                }
            }

            // Check if post exists
            $existing_id = isset($row['ID']) ? intval($row['ID']) : 0;
            if ($existing_id) {
                $existing_post = get_post($existing_id);
                if ($existing_post && $existing_post->post_type === $post_type) {
                    $post_data['ID'] = $existing_id;
                }
            }

            // Insert or update post
            $post_id = wp_insert_post($post_data, true);

            if (is_wp_error($post_id)) {
                $results['errors'][] = sprintf(
                    /* translators: 1: The title of the post that failed to import, 2: The error message explaining why it failed */
                    esc_html__('Failed to import post: %1$s. Error: %2$s', 'acf-import-export'),
                    esc_html($row['Title']),
                    esc_html($post_id->get_error_message())
                );
                continue;
            }

            // Handle taxonomies
            foreach ($headers as $header) {
                if (strpos($header, 'tax_') === 0) {
                    $tax_name = substr($header, 4);
                    if (isset($row[$header]) && taxonomy_exists($tax_name)) {
                        $term_paths = explode(',', $row[$header]);
                        $term_ids = array();
                        
                        foreach ($term_paths as $term_path) {
                            $term_names = explode('|', $term_path);
                            $parent_id = 0;
                            
                            // Create or get terms maintaining hierarchy
                            foreach ($term_names as $term_name) {
                                $term = get_term_by('name', $term_name, $tax_name);
                                
                                if (!$term) {
                                    // Create new term
                                    $term_data = array(
                                        'name' => $term_name,
                                        'parent' => $parent_id
                                    );
                                    $new_term = wp_insert_term($term_name, $tax_name, $term_data);
                                    if (!is_wp_error($new_term)) {
                                        $parent_id = $new_term['term_id'];
                                        $term_ids[] = $parent_id;
                                    }
                                } else {
                                    $parent_id = $term->term_id;
                                    $term_ids[] = $parent_id;
                                }
                            }
                        }
                        
                        if (!empty($term_ids)) {
                            wp_set_object_terms($post_id, array_unique($term_ids), $tax_name);
                        }
                    }
                }
            }

            // Handle ACF fields
            $field_groups = acf_get_field_groups(array('post_type' => $post_type));
            foreach ($field_groups as $field_group) {
                $fields = acf_get_fields($field_group);
                foreach ($fields as $field) {
                    if (isset($row[$field['label']])) {
                        $value = $row[$field['label']];
                        
                        // Handle different field types
                        switch ($field['type']) {
                            case 'taxonomy':
                                // Handle taxonomy fields similar to regular taxonomies
                                $term_paths = explode(',', $value);
                                $term_ids = array();
                                
                                foreach ($term_paths as $term_path) {
                                    $term_names = explode('|', $term_path);
                                    $parent_id = 0;
                                    
                                    foreach ($term_names as $term_name) {
                                        $term = get_term_by('name', $term_name, $field['taxonomy']);
                                        
                                        if (!$term) {
                                            $term_data = array(
                                                'name' => $term_name,
                                                'parent' => $parent_id
                                            );
                                            $new_term = wp_insert_term($term_name, $field['taxonomy'], $term_data);
                                            if (!is_wp_error($new_term)) {
                                                $parent_id = $new_term['term_id'];
                                                $term_ids[] = $parent_id;
                                            }
                                        } else {
                                            $parent_id = $term->term_id;
                                            $term_ids[] = $parent_id;
                                        }
                                    }
                                }
                                
                                if (!empty($term_ids)) {
                                    update_field($field['name'], array_unique($term_ids), $post_id);
                                }
                                break;
                                
                            case 'relationship':
                            case 'post_object':
                                // Handle related posts with hierarchy
                                $post_paths = explode(',', $value);
                                $post_ids = array();
                                
                                foreach ($post_paths as $post_path) {
                                    $post_titles = explode('|', $post_path);
                                    $last_title = end($post_titles);
                                    
                                    // Find the post by its title using WP_Query
                                    $query = new WP_Query(array(
                                        'post_type' => $field['post_type'],
                                        'title' => $last_title,
                                        'posts_per_page' => 1,
                                        'post_status' => 'any',
                                        'fields' => 'ids'
                                    ));
                                    
                                    if ($query->have_posts()) {
                                        $post_ids[] = $query->posts[0];
                                    }
                                }
                                
                                if (!empty($post_ids)) {
                                    if ($field['multiple']) {
                                        update_field($field['name'], $post_ids, $post_id);
                                    } else {
                                        update_field($field['name'], $post_ids[0], $post_id);
                                    }
                                }
                                break;
                                
                            case 'repeater':
                            case 'group':
                            case 'flexible_content':
                                // For complex fields, parse the JSON structure
                                $complex_value = json_decode($value, true);
                                if ($complex_value !== null) {
                                    update_field($field['name'], $complex_value, $post_id);
                                }
                                break;
                                
                            default:
                                // For other field types, handle comma-separated values
                                if (strpos($value, ',') !== false && $field['multiple']) {
                                    $value = explode(',', $value);
                                }
                                update_field($field['name'], $value, $post_id);
                        }
                    }
                }
            }

            $results['success'][] = sprintf(
                /* translators: 1: The import status (either "Updated" or "Imported"), 2: The title of the post that was imported or updated */
                esc_html__('%1$s post: %2$s', 'acf-import-export'),
                $existing_id ? esc_html__('Updated', 'acf-import-export') : esc_html__('Imported', 'acf-import-export'),
                esc_html($row['Title'])
            );
        }

        return $results;
    }
} 