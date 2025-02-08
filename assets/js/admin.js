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