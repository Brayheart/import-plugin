<?php
/**
 * WordPress content creation
 */
class WCIP_Page_Creator {
    
    public function __construct() {
        // Initialize if needed
    }
    
    /**
     * Create a new WordPress page
     * 
     * @param string $title The page title
     * @param string $content The page content
     * @return array|WP_Error The result of the page creation
     */
    public function create_page($title, $content) {
        return $this->create_content($title, $content, 'page');
    }

    /**
     * Create a new WordPress post
     * 
     * @param string $title The post title
     * @param string $content The post content
     * @param int $category_id Optional category ID
     * @param string $tags Optional comma-separated tags
     * @return array|WP_Error The result of the post creation
     */
    public function create_post($title, $content, $category_id = 0, $tags = '') {
        $result = $this->create_content($title, $content, 'post');
        
        if (!is_wp_error($result)) {
            // Set category if provided
            if (!empty($category_id)) {
                wp_set_post_categories($result['post_id'], array((int)$category_id));
            }
            
            // Set tags if provided
            if (!empty($tags)) {
                wp_set_post_tags($result['post_id'], $tags);
            }
        }
        
        return $result;
    }
    
    /**
     * Create content (shared logic for posts and pages)
     * 
     * @param string $title The content title
     * @param string $content The content body
     * @param string $post_type The post type (post, page, etc.)
     * @return array|WP_Error The result of the content creation
     */
    private function create_content($title, $content, $post_type = 'page') {
        // Create post object
        $post_data = array(
            'post_title'    => sanitize_text_field($title),
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_type'     => $post_type,
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