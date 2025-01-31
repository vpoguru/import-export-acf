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

        $file = $_FILES['import_file'];
        
        // Basic file checks
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_message = $this->get_upload_error_message($file['error']);
            wp_die(esc_html($error_message));
        }

        // Check file size
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            wp_die(sprintf(
                /* translators: %s: Maximum allowed file size in MB */
                esc_html__('File is too large. Maximum allowed size is %s.', 'acf-import-export'),
                size_format($max_size)
            ));
        }

        // Verify file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'csv') {
            wp_die(esc_html__('Only CSV files are supported. Please export your data in CSV format and try again.', 'acf-import-export'));
        }

        // Upload the file to WordPress media directory
        $upload = wp_upload_bits(
            $file['name'],
            null,
            file_get_contents($file['tmp_name'])
        );

        if ($upload['error']) {
            wp_die(sprintf(
                /* translators: %s: Upload error message */
                esc_html__('Failed to upload file: %s', 'acf-import-export'),
                esc_html($upload['error'])
            ));
        }

        $file_path = $upload['file'];

        try {
            // Verify CSV format
            $handle = fopen($file_path, 'r');
            if ($handle === false) {
                wp_die(esc_html__('Error opening the CSV file. Please try again.', 'acf-import-export'));
            }

            // Try to read the first line to verify CSV format
            $first_line = fgetcsv($handle);
            if ($first_line === false || count($first_line) < 1) {
                fclose($handle);
                unlink($file_path); // Clean up the uploaded file
                wp_die(esc_html__('The uploaded file appears to be empty or not properly formatted as CSV.', 'acf-import-export'));
            }
            fclose($handle);

            $import_type = isset($_POST['import_type']) ? sanitize_text_field(wp_unslash($_POST['import_type'])) : 'structure';
            
            if ($import_type === 'content') {
                $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
                if (empty($post_type)) {
                    unlink($file_path); // Clean up the uploaded file
                    wp_die(esc_html__('No post type selected for content import.', 'acf-import-export'));
                }
                
                $results = $this->import_post_type_content($file_path, $post_type);
            } else {
                $import_data = $this->parse_csv_file($file_path);
                if (empty($import_data)) {
                    unlink($file_path); // Clean up the uploaded file
                    wp_die(esc_html__('No valid data found in the CSV file. Please verify the file format matches the export format.', 'acf-import-export'));
                }

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

            // Clean up - delete the uploaded file
            unlink($file_path);

            // Redirect back with results
            $redirect_url = add_query_arg(array(
                'page' => 'acf-import-export',
                'import_status' => 'complete',
                'success_count' => count($results['success']),
                'error_count' => count($results['errors'])
            ), admin_url('edit.php?post_type=acf-field-group'));

            wp_safe_redirect($redirect_url);
            exit;

        } catch (Exception $e) {
            // Clean up on error
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            wp_die(sprintf(
                /* translators: %s: Error message */
                esc_html__('Error processing the import: %s', 'acf-import-export'),
                esc_html($e->getMessage())
            ));
        }
    }

    private function parse_csv_file($file_path) {
        if (!file_exists($file_path)) {
            throw new Exception(__('Import file not found.', 'acf-import-export'));
        }

        if (!is_readable($file_path)) {
            throw new Exception(__('Import file is not readable.', 'acf-import-export'));
        }

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            throw new Exception(__('Failed to open the CSV file.', 'acf-import-export'));
        }

        // Set CSV reading options
        setlocale(LC_ALL, 'en_US.UTF-8');

        $import_data = array(
            'field_groups' => array(),
            'post_types' => array(),
            'taxonomies' => array()
        );

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false || count($headers) < 4) {
            fclose($handle);
            throw new Exception(__('Invalid CSV format. The file must contain at least 4 columns: Type, Key, Title, and Fields.', 'acf-import-export'));
        }

        // Verify header format
        $required_headers = array('Type', 'Key', 'Title', 'Fields');
        $missing_headers = array_diff($required_headers, $headers);
        if (!empty($missing_headers)) {
            fclose($handle);
            throw new Exception(sprintf(
                /* translators: %s: List of missing headers */
                __('Missing required columns in CSV: %s', 'acf-import-export'),
                implode(', ', $missing_headers)
            ));
        }

        // Process each row
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4) {
                continue;
            }

            // Trim whitespace from data
            $data = array_map('trim', $data);
            
            list($type, $key, $title, $fields_str) = $data;
            
            switch ($type) {
                case 'field_group':
                    $fields = array();
                    if (!empty($fields_str)) {
                        $field_parts = explode('|', $fields_str);
                        foreach ($field_parts as $field_part) {
                            $field_data = explode(':', $field_part);
                            if (count($field_data) >= 2) {
                                list($name, $type) = $field_data;
                                $fields[] = array(
                                    'key' => 'field_' . uniqid(),
                                    'name' => trim($name),
                                    'type' => trim($type),
                                    'label' => ucfirst(trim($name))
                                );
                            }
                        }
                    }
                    
                    $import_data['field_groups'][] = array(
                        'key' => $key,
                        'title' => $title,
                        'fields' => $fields
                    );
                    break;
                    
                case 'post_type':
                    $supports = !empty($fields_str) ? explode('|', $fields_str) : array();
                    $supports = array_map('trim', $supports);
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
                    $object_types = !empty($fields_str) ? explode('|', $fields_str) : array();
                    $object_types = array_map('trim', $object_types);
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

        fclose($handle);
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
        if (!post_type_exists($post_type)) {
            throw new Exception(sprintf(
                __('Post type "%s" does not exist.', 'acf-import-export'),
                $post_type
            ));
        }

        $results = array(
            'success' => array(),
            'errors' => array()
        );

        // Read the CSV file
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            throw new Exception(__('Failed to open the CSV file.', 'acf-import-export'));
        }

        // Read and validate headers
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new Exception(__('Failed to read CSV headers.', 'acf-import-export'));
        }

        // Clean headers
        $headers = array_map('trim', $headers);

        // Track row number for error reporting
        $row_number = 1;

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;

            try {
                if (empty($row) || count($row) !== count($headers)) {
                    throw new Exception(sprintf(
                        __('Invalid number of columns in row %d', 'acf-import-export'),
                        $row_number
                    ));
                }

                // Create associative array from headers and row data
                $row_data = array_combine($headers, array_map('trim', $row));

                // Prepare post data
                $post_data = array(
                    'post_type' => $post_type,
                    'post_status' => 'publish'
                );

                // Map WordPress core fields
                $wp_fields_map = array(
                    'Title' => 'post_title',
                    'Content' => 'post_content',
                    'Excerpt' => 'post_excerpt',
                    'Status' => 'post_status',
                    'Date' => 'post_date',
                    'Author' => 'post_author',
                    'Slug' => 'post_name'
                );

                foreach ($wp_fields_map as $csv_field => $wp_field) {
                    if (!empty($row_data[$csv_field])) {
                        $post_data[$wp_field] = $row_data[$csv_field];
                    }
                }

                // Handle existing post update if ID is provided
                if (!empty($row_data['ID'])) {
                    $existing_post = get_post($row_data['ID']);
                    if ($existing_post && $existing_post->post_type === $post_type) {
                        $post_data['ID'] = $row_data['ID'];
                    }
                }

                // Insert or update post
                $post_id = wp_insert_post($post_data, true);
                if (is_wp_error($post_id)) {
                    throw new Exception($post_id->get_error_message());
                }

                // Process ACF fields
                $field_groups = acf_get_field_groups(array('post_type' => $post_type));
                
                foreach ($field_groups as $field_group) {
                    $fields = acf_get_fields($field_group);
                    
                    foreach ($fields as $field) {
                        $field_key = $field['name'];
                        $field_label = $field['label'];
                        
                        // Try both the field name and label as column headers
                        $value = isset($row_data[$field_key]) ? $row_data[$field_key] : (isset($row_data[$field_label]) ? $row_data[$field_label] : null);
                        
                        if ($value !== null) {
                            switch ($field['type']) {
                                case 'repeater':
                                case 'group':
                                case 'flexible_content':
                                    $json_value = json_decode($value, true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        update_field($field_key, $json_value, $post_id);
                                    }
                                    break;

                                case 'taxonomy':
                                    if (!empty($value)) {
                                        $terms = array_map('trim', explode(',', $value));
                                        $term_ids = array();
                                        
                                        foreach ($terms as $term_name) {
                                            $term = get_term_by('name', $term_name, $field['taxonomy']);
                                            if (!$term) {
                                                $new_term = wp_insert_term($term_name, $field['taxonomy']);
                                                if (!is_wp_error($new_term)) {
                                                    $term_ids[] = $new_term['term_id'];
                                                }
                                            } else {
                                                $term_ids[] = $term->term_id;
                                            }
                                        }
                                        
                                        if (!empty($term_ids)) {
                                            update_field($field_key, $term_ids, $post_id);
                                        }
                                    }
                                    break;

                                case 'relationship':
                                case 'post_object':
                                    if (!empty($value)) {
                                        $related_posts = array_map('trim', explode(',', $value));
                                        $post_ids = array();
                                        
                                        foreach ($related_posts as $post_title) {
                                            $related_post = get_page_by_title($post_title, OBJECT, $field['post_type']);
                                            if ($related_post) {
                                                $post_ids[] = $related_post->ID;
                                            }
                                        }
                                        
                                        if (!empty($post_ids)) {
                                            if (!empty($field['multiple'])) {
                                                update_field($field_key, $post_ids, $post_id);
                                            } else {
                                                update_field($field_key, $post_ids[0], $post_id);
                                            }
                                        }
                                    }
                                    break;

                                default:
                                    if (!empty($field['multiple']) && strpos($value, ',') !== false) {
                                        $value = array_map('trim', explode(',', $value));
                                    }
                                    update_field($field_key, $value, $post_id);
                                    break;
                            }
                        }
                    }
                }

                // Process taxonomies
                foreach ($headers as $header) {
                    if (strpos($header, 'tax_') === 0) {
                        $taxonomy = substr($header, 4);
                        if (taxonomy_exists($taxonomy) && !empty($row_data[$header])) {
                            $term_paths = array_filter(array_map('trim', explode('|', $row_data[$header])));
                            $term_ids = array();
                            
                            foreach ($term_paths as $term_path) {
                                $path_parts = array_filter(array_map('trim', explode('/', $term_path)));
                                $parent_id = 0;
                                
                                // Create or get terms maintaining hierarchy
                                foreach ($path_parts as $term_name) {
                                    $args = array(
                                        'name' => $term_name,
                                        'parent' => $parent_id,
                                        'taxonomy' => $taxonomy
                                    );
                                    
                                    // Try to find the term at this level of hierarchy
                                    $term = get_terms(array(
                                        'name' => $term_name,
                                        'parent' => $parent_id,
                                        'taxonomy' => $taxonomy,
                                        'hide_empty' => false,
                                        'number' => 1
                                    ));
                                    
                                    if (empty($term)) {
                                        // Term doesn't exist, create it
                                        $new_term = wp_insert_term($term_name, $taxonomy, array('parent' => $parent_id));
                                        if (!is_wp_error($new_term)) {
                                            $parent_id = $new_term['term_id'];
                                            $term_ids[] = $parent_id;
                                        }
                                    } else {
                                        // Term exists, use it
                                        $parent_id = $term[0]->term_id;
                                        $term_ids[] = $parent_id;
                                    }
                                }
                            }
                            
                            if (!empty($term_ids)) {
                                wp_set_object_terms($post_id, array_unique($term_ids), $taxonomy);
                            }
                        }
                    }
                }

                $results['success'][] = sprintf(
                    __('%s post: %s (ID: %d)', 'acf-import-export'),
                    empty($post_data['ID']) ? 'Imported' : 'Updated',
                    $post_data['post_title'],
                    $post_id
                );

            } catch (Exception $e) {
                $results['errors'][] = sprintf(
                    __('Row %d: %s', 'acf-import-export'),
                    $row_number,
                    $e->getMessage()
                );
            }
        }

        fclose($handle);

        // If no successful imports and we have errors, throw an exception
        if (empty($results['success']) && !empty($results['errors'])) {
            throw new Exception(sprintf(
                __('Import failed. Errors: %s', 'acf-import-export'),
                implode(', ', $results['errors'])
            ));
        }

        return $results;
    }

    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'acf-import-export');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.', 'acf-import-export');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded. Please try again.', 'acf-import-export');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded. Please select a file to import.', 'acf-import-export');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder. Please contact your hosting provider.', 'acf-import-export');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk. Please check folder permissions or contact your hosting provider.', 'acf-import-export');
            case UPLOAD_ERR_EXTENSION:
                return __('File upload stopped by extension. Please contact your hosting provider.', 'acf-import-export');
            default:
                return __('Unknown upload error occurred. Please try again or contact support.', 'acf-import-export');
        }
    }
} 