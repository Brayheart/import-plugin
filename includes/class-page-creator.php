<?php
/**
 * WordPress page creation
 */
class WCIP_Page_Creator {
    
    public function __construct() {
        // Initialize if needed
    }
    
    // From the original create_page() function
    public function create_page($title, $content) {
        // Create post object
        $post_data = array(
            'post_title'    => sanitize_text_field($title),
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_type'     => 'page',
        );
        
        // Insert the post into the database
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        return array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'preview_url' => get_preview_post_link($post_id)
        );
    }
}