<?php
class ACF_Import_Export {
    private $import;
    private $export;

    public function __construct() {
        // Initialize import and export classes
        $this->import = new ACF_Import();
        $this->export = new ACF_Export();

        // Add menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=acf-field-group',
            __('Import/Export ACF Data', 'acf-import-export'),
            __('Import/Export Data', 'acf-import-export'),
            'manage_options',
            'acf-import-export',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('acf-field-group_page_acf-import-export' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'acf-import-export-admin',
            ACF_IE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ACF_IE_VERSION
        );

        wp_enqueue_script(
            'acf-import-export-admin',
            ACF_IE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ACF_IE_VERSION,
            true
        );

        wp_localize_script('acf-import-export-admin', 'acfIeAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('acf_ie_nonce')
        ));
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ACF Import/Export', 'acf-import-export'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#export" class="nav-tab nav-tab-active"><?php esc_html_e('Export', 'acf-import-export'); ?></a>
                <a href="#import" class="nav-tab"><?php esc_html_e('Import', 'acf-import-export'); ?></a>
            </div>

            <div class="tab-content">
                <div id="export" class="tab-pane active">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="acf_export_data">
                        <?php wp_nonce_field('acf_export_nonce', 'acf_export_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Export Type', 'acf-import-export'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="radio" name="export_type" value="structure" checked class="export-type-radio">
                                        <?php esc_html_e('Structure (Field Groups, Post Types, Taxonomies)', 'acf-import-export'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="export_type" value="content" class="export-type-radio">
                                        <?php esc_html_e('Content (Post Data)', 'acf-import-export'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <div id="structure-options" class="export-options">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Select Data to Export', 'acf-import-export'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="export_items[]" value="field_groups" checked>
                                            <?php esc_html_e('Field Groups', 'acf-import-export'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="export_items[]" value="post_types">
                                            <?php esc_html_e('Post Types', 'acf-import-export'); ?>
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="export_items[]" value="taxonomies">
                                            <?php esc_html_e('Taxonomies', 'acf-import-export'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div id="content-options" class="export-options" style="display: none;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Post Type', 'acf-import-export'); ?></label>
                                    </th>
                                    <td>
                                        <select name="post_type" id="export-post-type">
                                            <?php
                                            $post_types = get_post_types(array('_builtin' => false), 'objects');
                                            foreach ($post_types as $pt) {
                                                printf(
                                                    '<option value="%s">%s</option>',
                                                    esc_attr($pt->name),
                                                    esc_html($pt->label)
                                                );
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Select Columns', 'acf-import-export'); ?></label>
                                    </th>
                                    <td id="column-options">
                                        <div class="column-section">
                                            <h4><?php esc_html_e('WordPress Fields', 'acf-import-export'); ?></h4>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="ID">
                                                <?php esc_html_e('ID', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_title" checked>
                                                <?php esc_html_e('Title', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_content">
                                                <?php esc_html_e('Content', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_excerpt">
                                                <?php esc_html_e('Excerpt', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_status">
                                                <?php esc_html_e('Status', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_date">
                                                <?php esc_html_e('Date', 'acf-import-export'); ?>
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="columns[]" value="post_author">
                                                <?php esc_html_e('Author', 'acf-import-export'); ?>
                                            </label>
                                        </div>
                                        
                                        <div class="column-section taxonomies">
                                            <h4><?php esc_html_e('Taxonomies', 'acf-import-export'); ?></h4>
                                            <?php
                                            $taxonomies = get_taxonomies(array('_builtin' => false), 'objects');
                                            foreach ($taxonomies as $tax) {
                                                printf(
                                                    '<label><input type="checkbox" name="columns[]" value="tax_%s"> %s</label><br>',
                                                    esc_attr($tax->name),
                                                    esc_html($tax->label)
                                                );
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="column-section acf-fields">
                                            <h4><?php esc_html_e('ACF Fields', 'acf-import-export'); ?></h4>
                                            <?php
                                            $field_groups = acf_get_field_groups();
                                            foreach ($field_groups as $group) {
                                                $fields = acf_get_fields($group);
                                                foreach ($fields as $field) {
                                                    printf(
                                                        '<label><input type="checkbox" name="columns[]" value="%s"> %s</label><br>',
                                                        esc_attr($field['name']),
                                                        esc_html($field['label'])
                                                    );
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Export Format', 'acf-import-export'); ?></label>
                                </th>
                                <td>
                                    <p class="description"><?php esc_html_e('Data will be exported in CSV format', 'acf-import-export'); ?></p>
                                    <input type="hidden" name="export_format" value="csv">
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(esc_html__('Export', 'acf-import-export')); ?>
                    </form>
                </div>

                <div id="import" class="tab-pane">
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="acf_import_data">
                        <?php wp_nonce_field('acf_import_nonce', 'acf_import_nonce'); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Import Type', 'acf-import-export'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="radio" name="import_type" value="structure" checked class="import-type-radio">
                                        <?php esc_html_e('Structure (Field Groups, Post Types, Taxonomies)', 'acf-import-export'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="import_type" value="content" class="import-type-radio">
                                        <?php esc_html_e('Content (Post Data)', 'acf-import-export'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr id="import-post-type-row" style="display: none;">
                                <th scope="row">
                                    <label><?php esc_html_e('Post Type', 'acf-import-export'); ?></label>
                                </th>
                                <td>
                                    <select name="post_type">
                                        <?php
                                        foreach ($post_types as $pt) {
                                            printf(
                                                '<option value="%s">%s</option>',
                                                esc_attr($pt->name),
                                                esc_html($pt->label)
                                            );
                                        }
                                        ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Select the post type for the data you are importing.', 'acf-import-export'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Import File', 'acf-import-export'); ?></label>
                                </th>
                                <td>
                                    <input type="file" name="import_file" accept=".csv">
                                    <p class="description">
                                        <?php esc_html_e('Select a CSV file to import.', 'acf-import-export'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(esc_html__('Import', 'acf-import-export')); ?>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Handle export type toggle
            $('.export-type-radio').change(function() {
                $('.export-options').hide();
                if ($(this).val() === 'structure') {
                    $('#structure-options').show();
                } else {
                    $('#content-options').show();
                }
            });

            // Handle import type toggle
            $('.import-type-radio').change(function() {
                if ($(this).val() === 'content') {
                    $('#import-post-type-row').show();
                } else {
                    $('#import-post-type-row').hide();
                }
            });

            // Handle post type change for export
            $('#export-post-type').change(function() {
                // You can add AJAX here to dynamically load fields for the selected post type
            });
        });
        </script>
        <?php
    }
} 