jQuery(document).ready(function($) {
    $('#import_ajax_button').on('click', function() {
        var url = $('#website_url').val();
        var selector = $('#content_selector').val();
        var title = $('#page_title').val();
        
        if (!url) {
            alert('Please enter a valid URL');
            return;
        }
        
        if (!title) {
            alert('Please enter a page title');
            return;
        }
        
        $(this).attr('disabled', true).text('Importing...');
        
        $.ajax({
            url: website_importer.ajax_url,
            type: 'POST',
            data: {
                action: 'import_website_content',
                nonce: website_importer.nonce,
                url: url,
                selector: selector
            },
            success: function(response) {
                $('#import_ajax_button').attr('disabled', false).text('Preview Content');
                
                if (response.success) {
                    $('#content_preview').html(response.data.content);
                    $('#import_result').removeClass('hidden');
                    $('#preview_title').val(title);
                    $('#preview_content').val(response.data.content);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                $('#import_ajax_button').attr('disabled', false).text('Preview Content');
                alert('An error occurred. Please try again.');
            }
        });
    });
});