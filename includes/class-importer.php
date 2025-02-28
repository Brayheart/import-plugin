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

         // Convert to Gutenberg blocks
         $content = $this->convert_to_gutenberg_blocks($content);
        
        return $content;
    }

    private function convert_to_gutenberg_blocks($html) {
        // Create a DOMDocument to parse the HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Start with empty blocks
        $blocks = '';
        
        // Get the body or documentElement as starting point
        $body = $dom->getElementsByTagName('body')->item(0) ?: $dom->documentElement;
        
        // Process each child node recursively
        if ($body) {
            foreach ($body->childNodes as $node) {
                $blocks .= $this->process_node($node);
            }
        }
        
        return $blocks;
    }

    private function process_node($node) {
        // Skip text nodes with only whitespace
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = trim($node->textContent);
            if (empty($text)) {
                return '';
            }
            // Text nodes with content should be wrapped in paragraphs
            return '<!-- wp:paragraph --><p>' . esc_html($text) . '</p><!-- /wp:paragraph -->';
        }
        
        // Skip comment nodes
        if ($node->nodeType === XML_COMMENT_NODE) {
            return '';
        }
        
        // Process element nodes based on tag name
        if ($node->nodeType === XML_ELEMENT_NODE) {
            switch ($node->nodeName) {
                case 'h1':
                    return '<!-- wp:heading {"level":1} --><h1>' . $this->get_inner_html($node) . '</h1><!-- /wp:heading -->';
                
                case 'h2':
                    return '<!-- wp:heading {"level":2} --><h2>' . $this->get_inner_html($node) . '</h2><!-- /wp:heading -->';
                
                case 'h3':
                    return '<!-- wp:heading {"level":3} --><h3>' . $this->get_inner_html($node) . '</h3><!-- /wp:heading -->';
                
                case 'h4':
                    return '<!-- wp:heading {"level":4} --><h4>' . $this->get_inner_html($node) . '</h4><!-- /wp:heading -->';
                
                case 'h5':
                    return '<!-- wp:heading {"level":5} --><h5>' . $this->get_inner_html($node) . '</h5><!-- /wp:heading -->';
                
                case 'h6':
                    return '<!-- wp:heading {"level":6} --><h6>' . $this->get_inner_html($node) . '</h6><!-- /wp:heading -->';
                
                case 'p':
                    return '<!-- wp:paragraph --><p>' . $this->get_inner_html($node) . '</p><!-- /wp:paragraph -->';
                
                case 'ul':
                    return '<!-- wp:list --><ul>' . $this->get_inner_html($node) . '</ul><!-- /wp:list -->';
                
                case 'ol':
                    return '<!-- wp:list {"ordered":true} --><ol>' . $this->get_inner_html($node) . '</ol><!-- /wp:list -->';
                
                case 'blockquote':
                    return '<!-- wp:quote --><blockquote class="wp-block-quote">' . $this->get_inner_html($node) . '</blockquote><!-- /wp:quote -->';
                
                case 'img':
                    $src = $node->getAttribute('src');
                    $alt = $node->getAttribute('alt');
                    $className = $node->getAttribute('class');
                    
                    // Process the image (download and add to media library)
                    $processed_image = $this->process_image($src, $alt);
                    
                    if ($processed_image && !is_wp_error($processed_image)) {
                        // If image was successfully processed, use the new WordPress media URL
                        return '<!-- wp:image {"id":' . $processed_image['id'] . ',"sizeSlug":"large"} -->' .
                                '<figure class="wp-block-image size-large">' .
                                '<img src="' . esc_url($processed_image['url']) . '" ' .
                                'alt="' . esc_attr($alt) . '" ' .
                                'class="wp-image-' . $processed_image['id'] . ' ' . esc_attr($className) . '"/>' .
                                '</figure>' .
                                '<!-- /wp:image -->';
                    } else {
                        // If processing failed, use the original URL
                        return '<!-- wp:image {"sizeSlug":"large"} -->' .
                                '<figure class="wp-block-image size-large">' .
                                '<img src="' . esc_url($src) . '" ' .
                                'alt="' . esc_attr($alt) . '" ' .
                                'class="' . esc_attr($className) . '"/>' .
                                '</figure>' .
                                '<!-- /wp:image -->';
                    }

                case 'table':
                    return '<!-- wp:table --><figure class="wp-block-table"><table>' . $this->get_inner_html($node) . '</table></figure><!-- /wp:table -->';
                
                case 'div':
                case 'section':
                case 'article':
                case 'main':
                case 'aside':
                case 'figure':
                    // For container elements, process all children and combine
                    $inner_blocks = '';
                    foreach ($node->childNodes as $child) {
                        $inner_blocks .= $this->process_node($child);
                    }
                    return $inner_blocks;
                
                default:
                    // For any other element, try to maintain it inside a paragraph
                    // or recursively process its children if it has any
                    if ($node->hasChildNodes()) {
                        $inner_content = '';
                        foreach ($node->childNodes as $child) {
                            $inner_content .= $this->process_node($child);
                        }
                        return $inner_content;
                    }
                    
                    // If no children and not handled specifically, wrap in paragraph
                    $html = $this->get_outer_html($node);
                    if (!empty(trim($html))) {
                        return '<!-- wp:paragraph --><p>' . $html . '</p><!-- /wp:paragraph -->';
                    }
                    return '';
            }
        }
        
        return '';
    }

    private function process_image($url, $alt = '') {
        // Skip processing if URL is empty or already local
        if (empty($url) || $this->is_local_url($url)) {
            return false;
        }
        
        // Make sure the URL is absolute
        if (strpos($url, 'http') !== 0) {
            // Handle relative URLs
            if (strpos($url, '//') === 0) {
                // Protocol-relative URL
                $url = 'https:' . $url;
            } else {
                // Might be a relative URL - skip for now as we'd need the base URL
                return false;
            }
        }
        
        // Get file info
        $file_info = wp_check_filetype(basename($url));
        if (empty($file_info['ext'])) {
            // Can't determine file type, skip
            return false;
        }
        
        // Generate a unique filename
        $filename = wp_unique_filename(
            wp_upload_dir()['path'],
            sanitize_file_name(basename($url))
        );
        
        // Download the image
        $response = wp_remote_get($url, [
            'timeout'     => 30,
            'redirection' => 5,
            'sslverify'   => false,
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $image_contents = wp_remote_retrieve_body($response);
        if (empty($image_contents)) {
            return false;
        }
        
        // Create the upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $image_path = $upload_dir['path'] . '/' . $filename;
        
        // Save the image file
        if (!file_put_contents($image_path, $image_contents)) {
            return false;
        }
        
        // Prepare attachment data
        $attachment = [
            'post_mime_type' => $file_info['type'],
            'post_title'     => sanitize_text_field($alt ?: pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        
        // Insert the attachment
        $attach_id = wp_insert_attachment($attachment, $image_path);
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }
        
        // Generate metadata for the attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        return [
            'id'  => $attach_id,
            'url' => wp_get_attachment_url($attach_id),
        ];
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

    private function is_local_url($url) {
        $site_url = get_site_url();
        return strpos($url, $site_url) === 0;
    }
}