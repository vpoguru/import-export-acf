jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and content
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-pane').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        $($(this).attr('href')).addClass('active');
    });

    // Form validation
    $('#export-form').on('submit', function(e) {
        var checked = $('input[name="export_items[]"]:checked').length;
        if (checked === 0) {
            e.preventDefault();
            alert(acfIeAdmin.messages.select_items);
        }
    });

    $('#import-form').on('submit', function(e) {
        var fileInput = $('input[name="import_file"]');
        if (fileInput.val() === '') {
            e.preventDefault();
            alert(acfIeAdmin.messages.select_file);
        }
    });

    // Show success/error messages
    if (window.location.search.indexOf('import_status=complete') > -1) {
        var successCount = getUrlParameter('success_count');
        var errorCount = getUrlParameter('error_count');
        
        if (successCount > 0) {
            showNotice('success', successCount + ' items imported successfully.');
        }
        
        if (errorCount > 0) {
            showNotice('error', errorCount + ' items failed to import.');
        }
    }

    // Helper functions
    function showNotice(type, message) {
        var notice = $('<div class="notice notice-' + type + '"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
    }

    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
}); 