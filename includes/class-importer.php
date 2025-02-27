<?php
/**
 * Content importing functionality
 */
class WCIP_Importer {
    
    public function __construct() {
        // Initialize if needed
    }
    
    // From the original get_website_content() function
    public function get_website_content($url, $selector = '') {
        // Use WordPress HTTP API to fetch the content
        $response = wp_remote_get($url, array(
            'timeout'     => 30,
            'redirection' => 5,
            'sslverify'   => false,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36',
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return new WP_Error('empty_content', 'No content found at the provided URL.');
        }
        
        // If no selector is provided, get the body content
        if (empty($selector)) {
            // Basic extraction of body content
            preg_match('/<body.*?>(.*?)<\/body>/is', $body, $matches);
            $content = isset($matches[1]) ? $matches[1] : $body;
        } else {
            // Use DOMDocument and DOMXPath for more precise extraction
            $dom = new DOMDocument();
            
            // Suppress errors for malformed HTML
            libxml_use_internal_errors(true);
            $dom->loadHTML($body);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Try to extract content using selector
            $content = $this->extract_content_by_selector($xpath, $selector, $dom);
            
            if (empty($content)) {
                return new WP_Error('selector_not_found', 'The specified CSS selector did not match any content. Please try a different selector.');
            }
        }
        
        // Clean up the content but don't convert to blocks
        $content = $this->clean_content($content);
        
        return $content;
    }
    
    // From the original extract_content_by_selector() function
    private function extract_content_by_selector($xpath, $selector, $dom) {
        $content = '';
        $nodes = null;
        
        // Try different selector types
        $selectors = [
            // Simple element selector
            "//{$selector}",
            // Class selector (.classname)
            "//*[contains(@class, '" . str_replace('.', '', $selector) . "')]",
            // ID selector (#idname)
            "//*[@id='" . str_replace('#', '', $selector) . "']",
        ];
        
        // Add support for comma-separated selectors
        if (strpos($selector, ',') !== false) {
            $multiSelectors = explode(',', $selector);
            $xpathQueries = [];
            
            foreach ($multiSelectors as $sel) {
                $sel = trim($sel);
                
                if (strpos($sel, '.') === 0) {
                    // Class selector
                    $class = substr($sel, 1);
                    $xpathQueries[] = "//*[contains(@class, '{$class}')]";
                } 
                else if (strpos($sel, '#') === 0) {
                    // ID selector
                    $id = substr($sel, 1);
                    $xpathQueries[] = "//*[@id='{$id}']";
                }
                else {
                    // Element selector
                    $xpathQueries[] = "//{$sel}";
                }
            }
            
            $selectors[] = implode('|', $xpathQueries);
        }
        
        // Try each selector type until one works
        foreach ($selectors as $xpathQuery) {
            $nodes = $xpath->query($xpathQuery);
            
            if ($nodes && $nodes instanceof DOMNodeList && $nodes->length > 0) {
                // Extract content from matched nodes
                foreach ($nodes as $node) {
                    $content .= $dom->saveHTML($node) . "\n";
                }
                
                // If we found content, stop looking
                if (!empty($content)) {
                    break;
                }
            }
        }
        
        return $content;
    }
    
    // From the original clean_content() function
    public function clean_content($content) {
        // Remove scripts
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        
        // Remove styles
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        
        // Remove head
        $content = preg_replace('/<head\b[^>]*>(.*?)<\/head>/is', '', $content);
        
        // Remove comments
        $content = preg_replace('/<!--(.*?)-->/s', '', $content);
        
        // Allow a wider range of tags for better content preservation
        $allowed_tags = '<p><a><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><img><div><span><br><strong><em><i><b><figure><figcaption><table><tr><td><th><thead><tbody><tfoot>';
        $content = strip_tags($content, $allowed_tags);
        
        return $content;
    }
    
    // From the original get_inner_html() function
    private function get_inner_html($node) {
        $innerHTML = '';
        $children = $node->childNodes;
        
        foreach ($children as $child) {
            $innerHTML .= $node->ownerDocument->saveHTML($child);
        }
        
        return $innerHTML;
    }
    
    // From the original get_outer_html() function
    private function get_outer_html($node) {
        return $node->ownerDocument->saveHTML($node);
    }
}