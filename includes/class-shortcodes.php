<?php
/**
 * Shortcode implementation
 */
class WCIP_Shortcodes {
    
    private $importer;
    
    public function __construct($importer) {
        $this->importer = $importer;
        
        // Register shortcode
        add_shortcode('import_website', array($this, 'import_website_shortcode'));
    }
    
    // From the original import_website_shortcode() function
    public function import_website_shortcode($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'selector' => '',
        ), $atts, 'import_website');
        
        if (empty($atts['url'])) {
            return '<p>Error: URL is required</p>';
        }
        
        $content = $this->get_website_content($atts['url'], $atts['selector']);
        
        if (is_wp_error($content)) {
            return '<p>Error: ' . $content->get_error_message() . '</p>';
        }
        
        return $content;
    }
}