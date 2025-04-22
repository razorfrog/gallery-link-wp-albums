
<?php
/**
 * Main plugin file for Google Photos Albums
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main plugin class
 */
class WP_Gallery_Link {
    /**
     * Instance of the Google API class
     */
    private $google_api;
    
    /**
     * Instance of the CPT class
     */
    private $cpt;
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link: Main class initialized');
        }
        
        // Initialize CPT
        $this->cpt = new WP_Gallery_Link_CPT();
        
        // Initialize Google API if needed
        // $this->google_api = new WP_Gallery_Link_Google_API();
        
        // Setup AJAX hooks
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }
    
    /**
     * AJAX handler for album import
     */
    public function ajax_import_album() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpgl_nonce')) {
            error_log('WP Gallery Link: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }
        
        // Check for album ID
        if (empty($_POST['album_id'])) {
            error_log('WP Gallery Link: No album ID provided');
            wp_send_json_error(array('message' => __('No album ID provided', 'wp-gallery-link')));
            return;
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        error_log('WP Gallery Link: Attempting to import album ID ' . $album_id);
        
        // Mock album data for testing
        $album_data = array(
            'id' => $album_id,
            'title' => 'Test Album ' . time(),
            'productUrl' => 'https://photos.google.com/album/' . $album_id,
            'mediaItemsCount' => 10,
            'coverPhotoBaseUrl' => 'https://lh3.googleusercontent.com/sample-photo-url'
        );
        
        // In a real implementation, you would use the Google API to get the album data
        // $album_data = $this->google_api->get_album($album_id);
        
        error_log('WP Gallery Link: Album data prepared - Title: ' . $album_data['title']);
        
        // Create album post
        $post_id = $this->cpt->create_album_from_google($album_data);
        
        if (is_wp_error($post_id)) {
            error_log('WP Gallery Link: Error creating album post - ' . $post_id->get_error_message());
            
            if ($post_id->get_error_code() === 'album_exists') {
                $error_data = $post_id->get_error_data();
                $edit_url = get_edit_post_link($error_data['post_id'], '');
                error_log('WP Gallery Link: Album already exists - Post ID ' . $error_data['post_id']);
                
                wp_send_json_success(array(
                    'message' => __('Album already exists', 'wp-gallery-link'),
                    'post_id' => $error_data['post_id'],
                    'edit_url' => $edit_url,
                    'view_url' => get_permalink($error_data['post_id']),
                ));
            } else {
                wp_send_json_error(array('message' => $post_id->get_error_message()));
            }
            return;
        }
        
        // Log successful album import
        error_log('WP Gallery Link: Successfully imported album - Post ID ' . $post_id);
        
        // Success response
        wp_send_json_success(array(
            'message' => __('Album imported successfully', 'wp-gallery-link'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, ''),
            'view_url' => get_permalink($post_id),
        ));
    }
}

// Initialize plugin
new WP_Gallery_Link();
