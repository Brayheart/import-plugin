<?php
/**
 * Admin functionality
 */
class WCIP_Admin {
    
    private $importer;
    private $page_creator;
    
    public function __construct() {
        $this->importer = new WCIP_Importer();
        $this->page_creator = new WCIP_Page_Creator();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX actions
        add_action('wp_ajax_import_website_content', array($this, 'handle_ajax_import'));
        add_action('wp_ajax_nopriv_import_website_content', array($this, 'handle_ajax_import'));
        
        // Admin post actions
        add_action('admin_post_import_website_content', array($this, 'handle_form_submission'));
        add_action('admin_post_create_imported_page', array($this, 'handle_page_creation'));

        add_action('admin_post_batch_import_content', array($this, 'handle_batch_import'));
    }

    // Add to the WCIP_Admin class
    public function handle_batch_import() {
        // Verify nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'website_importer_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get form data
        $urls = isset($_POST['batch_urls']) ? sanitize_textarea_field($_POST['batch_urls']) : '';
        $post_type = isset($_POST['batch_post_type']) ? sanitize_text_field($_POST['batch_post_type']) : 'page';
        $selector = isset($_POST['batch_content_selector']) ? sanitize_text_field($_POST['batch_content_selector']) : '';
        $category_id = ($post_type === 'post' && isset($_POST['batch_post_category'])) ? intval($_POST['batch_post_category']) : 0;
        $tags = ($post_type === 'post' && isset($_POST['batch_post_tags'])) ? sanitize_text_field($_POST['batch_post_tags']) : '';
        
        // Process the URLs
        $urls_array = array_filter(explode("\n", $urls));
        
        if (empty($urls_array)) {
            wp_die('No valid URLs provided');
        }
        
        $results = array();
        $errors = array();
        
        foreach ($urls_array as $url) {
            $url = trim(esc_url_raw($url));
            
            if (empty($url)) {
                continue;
            }
            
            // Use the importer to get content
            $content = $this->importer->get_website_content($url, $selector);
            
            if (is_wp_error($content)) {
                $errors[] = array(
                    'url' => $url,
                    'error' => $content->get_error_message()
                );
                continue;
            }
            
            // Extract title from URL or content
            $title = $this->extract_title_from_content($content) ?: parse_url($url, PHP_URL_PATH);
            
            // Create the post or page
            if ($post_type === 'post') {
                $result = $this->page_creator->create_post($title, $content, $category_id, $tags);
            } else {
                $result = $this->page_creator->create_page($title, $content);
            }
            
            if (is_wp_error($result)) {
                $errors[] = array(
                    'url' => $url,
                    'error' => $result->get_error_message()
                );
            } else {
                $results[] = array(
                    'url' => $url,
                    'post_id' => $result['post_id'],
                    'edit_url' => $result['edit_url']
                );
            }
        }
        
        // Store results in transient for display
        set_transient('wcip_batch_import_results', array(
            'success' => $results,
            'errors' => $errors
        ), 60 * 60); // 1 hour expiration
        
        // Redirect to results page
        wp_redirect(admin_url('admin.php?page=website-content-importer-pro&action=batch_results'));
        exit;
    }

    public function display_batch_results() {
        $results = get_transient('wcip_batch_import_results');
        
        if (!$results) {
            echo '<div class="notice notice-error"><p>No results found or results have expired.</p></div>';
            return;
        }
        
        // Display successful imports
        if (!empty($results['success'])) {
            echo '<h3>Successfully Imported Pages/Posts</h3>';
            echo '<ul class="wcip-batch-results">';
            
            foreach ($results['success'] as $item) {
                echo '<li>';
                echo '<span class="dashicons dashicons-yes"></span> ';
                echo esc_html($item['url']) . ' - ';
                echo '<a href="' . esc_url($item['edit_url']) . '" target="_blank">Edit</a>';
                echo '</li>';
            }
            
            echo '</ul>';
        }
        
        // Display errors
        if (!empty($results['errors'])) {
            echo '<h3>Failed Imports</h3>';
            echo '<ul class="wcip-batch-results-errors">';
            
            foreach ($results['errors'] as $error) {
                echo '<li>';
                echo '<span class="dashicons dashicons-no"></span> ';
                echo esc_html($error['url']) . ' - ';
                echo esc_html($error['error']);
                echo '</li>';
            }
            
            echo '</ul>';
        }
        
        // Clear the transient
        delete_transient('wcip_batch_import_results');
    }

    private function extract_title_from_content($content) {
        // Try to find an h1 tag
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $content, $matches)) {
            return strip_tags($matches[1]);
        }
        
        // Try to find an h2 tag
        if (preg_match('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches)) {
            return strip_tags($matches[1]);
        }
        
        // Try title tag (if the full HTML was imported)
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $matches)) {
            return strip_tags($matches[1]);
        }
        
        return '';
    }
    
    // From the original add_admin_menu() function
    public function add_admin_menu() {
        add_menu_page(
            'Website Content Importer Pro',
            'Website Importer Pro',
            'manage_options',
            'website-content-importer-pro',
            array($this, 'admin_page'),
            'dashicons-admin-site',
            20
        );
    }
    
    // From the original enqueue_scripts() function
    public function enqueue_scripts($hook) {
        if ($hook != 'toplevel_page_website-content-importer-pro') {
            return;
        }
        
        wp_enqueue_script('website-importer-js', WCIP_PLUGIN_URL . 'assets/js/website-importer.js', array('jquery'), WCIP_VERSION, true);
        wp_localize_script('website-importer-js', 'website_importer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('website_importer_nonce')
        ));
        
        wp_enqueue_style('website-importer-css', WCIP_PLUGIN_URL . 'assets/css/website-importer.css', array(), WCIP_VERSION);
    }
    
    // New method that handles AJAX imports
    public function handle_ajax_import() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'website_importer_nonce')) {
            wp_send_json_error('Security check failed');
            exit;
        }
        
        // Get URL and selector from the AJAX request
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $selector = isset($_POST['selector']) ? sanitize_text_field($_POST['selector']) : '';
        
        if (empty($url)) {
            wp_send_json_error('URL is required');
            exit;
        }
        
        // Use the importer to get content
        $content = $this->importer->get_website_content($url, $selector);
        
        if (is_wp_error($content)) {
            wp_send_json_error($content->get_error_message());
            exit;
        }
        
        // Return the content as JSON
        wp_send_json_success(array(
            'content' => $content
        ));
        exit;
    }
    
    // New method for handling form submissions
    public function handle_form_submission() {
        // Verify nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'website_importer_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get form data
        $url = isset($_POST['website_url']) ? esc_url_raw($_POST['website_url']) : '';
        $selector = isset($_POST['content_selector']) ? sanitize_text_field($_POST['content_selector']) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
        
        if (empty($url)) {
            wp_die('URL is required');
        }
        
        // Use the importer to get content
        $content = $this->importer->get_website_content($url, $selector);
        
        if (is_wp_error($content)) {
            wp_die($content->get_error_message());
        }
        
        // If page title is provided, create the page directly
        if (!empty($page_title)) {
            $result = $this->page_creator->create_page($page_title, $content);
            
            if (is_wp_error($result)) {
                wp_die($result->get_error_message());
            }
            
            wp_redirect(admin_url('post.php?post=' . $result['post_id'] . '&action=edit'));
            exit;
        } else {
            // If no title, show preview page
            include(WCIP_PLUGIN_DIR . 'templates/preview.php');
            exit;
        }
    }
    
    // New method for handling page creation
    public function handle_page_creation() {
        // Verify nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'website_importer_nonce')) {
            wp_die('Security check failed');
        }
        
        // Get title and content
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        
        if (empty($title) || empty($content)) {
            wp_die('Title and content are required');
        }
        
        // Use the page creator to create a page
        $result = $this->page_creator->create_page($title, $content);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Redirect to the edit page
        wp_redirect(admin_url('post.php?post=' . $result['post_id'] . '&action=edit'));
        exit;
    }
    
    // From the original admin_page() function
    public function admin_page() {
        // Check if we're displaying batch results
        if (isset($_GET['action']) && $_GET['action'] === 'batch_results') {
            echo '<div class="wrap">';
            echo '<h1>Batch Import Results</h1>';
            echo '<a href="' . admin_url('admin.php?page=website-content-importer-pro') . '" class="button">Back to Importer</a>';
            $this->display_batch_results();
            echo '</div>';
            return;
        }
        
        // Include regular admin template file
        include WCIP_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
}