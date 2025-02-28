jQuery(document).ready(function($) {
    // Toggle display of blog-specific options based on post type selection
    $('#post_type').on('change', function() {
        if ($(this).val() === 'post') {
            $('.blog-options').show();
            $('#create_content_button').text('Create Blog Post');
        } else {
            $('.blog-options').hide();
            $('#create_content_button').text('Create Page');
        }
    }).trigger('change'); // Trigger change on page load
    
    // Toggle batch blog options
    $('#batch_post_type').on('change', function() {
        if ($(this).val() === 'post') {
            $('.batch-blog-options').show();
        } else {
            $('.batch-blog-options').hide();
        }
    }).trigger('change'); // Trigger change on page load
    
    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show corresponding content
        var target = $(this).attr('href');
        $('.tab-content').hide();
        $(target).show();
    });
    
    $('#import_ajax_button').on('click', function() {
        var url = $('#website_url').val();
        var selector = $('#content_selector').val();
        var title = $('#page_title').val();
        var postType = $('#post_type').val();
        
        if (!url) {
            alert('Please enter a valid URL');
            return;
        }
        
        if (!title) {
            alert('Please enter a title');
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
                    $('#preview_post_type').val(postType);
                    
                    // If post type is 'post', also collect and store category and tags
                    if (postType === 'post') {
                        var category = $('#post_category').val();
                        var tags = $('#post_tags').val();
                        
                        $('#preview_category').val(category);
                        $('#preview_tags').val(tags);
                        $('#create_content_button').text('Create Blog Post');
                    } else {
                        $('#create_content_button').text('Create Page');
                    }
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