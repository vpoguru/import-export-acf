jQuery(document).ready(function($) {
    console.log('ACF Import/Export admin.js loaded');

    // Handle post type selection change
    $('#export_post_type').on('change', function() {
        var postType = $(this).val();
        console.log('Post type changed to:', postType);
        
        if (postType) {
            // Show loading indicator
            $('.taxonomy-list').html('<p>' + acf_import_export.loading + '</p>');
            
            console.log('Making AJAX call to:', acf_import_export.ajaxurl);
            
            // Make AJAX call to get taxonomies
            $.ajax({
                url: acf_import_export.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_post_type_taxonomies',
                    post_type: postType,
                    nonce: acf_import_export.nonce
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        // Update taxonomy list
                        var taxonomyList = '';
                        $.each(response.data, function(index, taxonomy) {
                            taxonomyList += '<label style="display: block; margin-bottom: 5px;">' +
                                '<input type="checkbox" name="specific_taxonomies[]" value="' + taxonomy.name + '">' +
                                taxonomy.label +
                                '</label>';
                        });
                        $('.taxonomy-list').html(taxonomyList);
                    } else {
                        $('.taxonomy-list').html('<p>' + acf_import_export.error + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    $('.taxonomy-list').html('<p>' + acf_import_export.error + '</p>');
                }
            });
        } else {
            $('.taxonomy-list').html('<p>Please select a post type first.</p>');
        }
    });

    // Handle radio button changes for taxonomy selection
    $('input[name="taxonomy_selection"]').change(function() {
        console.log('Taxonomy selection changed:', $(this).val());
        $('.taxonomy-list').toggle($(this).val() === 'selected');
    });

    // Handle radio button changes for ACF selection
    $('input[name="acf_selection"]').change(function() {
        console.log('ACF selection changed:', $(this).val());
        $('.acf-list').toggle($(this).val() === 'selected');
    });

    // Handle main option toggles
    $('.toggle-section').change(function() {
        var section = $(this).data('section');
        console.log('Toggle section:', section);
        $('.' + section).toggle(this.checked);
    });

    // Initialize state
    $('.toggle-section').each(function() {
        var section = $(this).data('section');
        $('.' + section).toggle(this.checked);
    });

    // Log initial state
    console.log('Initial post type:', $('#export_post_type').val());
    console.log('Initial taxonomy selection:', $('input[name="taxonomy_selection"]:checked').val());
    console.log('Initial ACF selection:', $('input[name="acf_selection"]:checked').val());
}); 