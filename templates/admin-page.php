<?php
/**
 * Admin page template
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <!-- The rest of the admin form HTML --><?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Website Content Importer Pro</h1>
    <p>Use this tool to import content from external websites and create new WordPress pages with preserved content order.</p>
    
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="website-importer-form">
        <?php wp_nonce_field('website_importer_nonce'); ?>
        <input type="hidden" name="action" value="import_website_content">
        
        <div class="form-group">
            <label for="website_url">Website URL:</label>
            <input type="url" id="website_url" name="website_url" class="regular-text" placeholder="https://example.com" required>
        </div>
        
        <div class="form-group">
            <label for="page_title">New Page Title:</label>
            <input type="text" id="page_title" name="page_title" class="regular-text" placeholder="My Imported Page" required>
        </div>
        
        <div class="form-group">
            <label for="content_selector">CSS Selector (optional):</label>
            <input type="text" id="content_selector" name="content_selector" class="regular-text" placeholder="article, .content, #main-content">
            <p class="description">Specify a CSS selector to target specific content. Leave empty to import the entire body content.</p>
            <p class="selector-examples">Examples: <code>article</code>, <code>.content</code>, <code>#main-content</code>, <code>.elementor-widget-container</code></p>
        </div>
        
        <div class="form-group">
            <button type="submit" class="button button-primary">Import Content</button>
            <button id="import_ajax_button" type="button" class="button button-secondary">Preview Content</button>
        </div>
    </form>
    
    <div class="import-status hidden"></div>
    
    <div id="import_result" class="hidden">
        <h2>Preview:</h2>
        <div id="content_preview"></div>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('website_importer_nonce'); ?>
            <input type="hidden" name="action" value="create_imported_page">
            <input type="hidden" id="preview_title" name="title" value="">
            <input type="hidden" id="preview_content" name="content" value="">
            <button type="submit" class="button button-primary">Create Page with This Content</button>
        </form>
    </div>
</div>
</div>