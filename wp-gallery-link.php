
<?php
/**
 * Main plugin file for Google Photos Albums
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Make sure the necessary constants are available
if (!defined('WP_GALLERY_LINK_DEBUG')) {
    define('WP_GALLERY_LINK_DEBUG', true);
}

/**
 * Main plugin class
 */
class WP_Gallery_Link {
    /**
     * Instance of the Google API class
     */
    public $google_api; // Changed from private to public
    
    /**
     * Instance of the CPT class
     */
    private $cpt;
    
    /**
     * Debug log
     */
    private $debug_log = array();
    
    /**
     * Get instance of main plugin class
     */
    public static function get_instance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new self();
        }
        return $instance;
    }
    
    /**
     * Initialize the plugin
     */
    public function __construct() {
        if (WP_GALLERY_LINK_DEBUG) {
            $this->log('WP Gallery Link: Main class initialized', 'info');
        }
        
        // Check if CPT class exists, if not include it
        if (!class_exists('WP_Gallery_Link_CPT')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-wp-gallery-link-cpt.php';
            if (WP_GALLERY_LINK_DEBUG) {
                $this->log('WP Gallery Link: CPT class file included', 'info');
            }
        }
        
        // Initialize CPT
        $this->cpt = new WP_Gallery_Link_CPT();
        
        // Check if Google API class exists, if not include it
        if (!class_exists('WP_Gallery_Link_Google_API')) { 
            if (file_exists(plugin_dir_path(__FILE__) . 'includes/class-wp-gallery-link-google-api.php')) {
                require_once plugin_dir_path(__FILE__) . 'includes/class-wp-gallery-link-google-api.php';
                if (WP_GALLERY_LINK_DEBUG) {
                    $this->log('WP Gallery Link: Google API class file included', 'info');
                }
                // Initialize Google API
                $this->google_api = new WP_Gallery_Link_Google_API();
            } else {
                // Create a stub Google API class for development purposes
                $this->google_api = new stdClass();
                $this->google_api->is_connected = function() { return false; };
                $this->google_api->get_auth_url = function() { return '#'; };
                if (WP_GALLERY_LINK_DEBUG) {
                    $this->log('WP Gallery Link: Using stub Google API class', 'info');
                }
            }
        }
        
        // Setup AJAX hooks
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }
    
    /**
     * Add a log entry
     *
     * @param string $message The log message
     * @param string $level The log level (debug, info, warning, error)
     * @param mixed $context Additional context data
     */
    public function log($message, $level = 'debug', $context = null) {
        if (WP_GALLERY_LINK_DEBUG) {
            $this->debug_log[] = array(
                'time' => current_time('mysql'),
                'message' => $message,
                'level' => $level,
                'context' => $context
            );
            
            if ($level === 'error') {
                error_log('[WP Gallery Link] [ERROR] ' . $message);
            }
        }
    }
    
    /**
     * Get the debug log
     *
     * @return array The debug log entries
     */
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    /**
     * AJAX handler for album import
     */
    public function ajax_import_album() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpgl_nonce')) {
            $this->log('WP Gallery Link: Nonce verification failed', 'error');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }
        
        // Check for album ID
        if (empty($_POST['album_id'])) {
            $this->log('WP Gallery Link: No album ID provided', 'error');
            wp_send_json_error(array('message' => __('No album ID provided', 'wp-gallery-link')));
            return;
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        $this->log('WP Gallery Link: Attempting to import album ID ' . $album_id, 'info');
        
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
        
        $this->log('WP Gallery Link: Album data prepared - Title: ' . $album_data['title'], 'info');
        
        // Create album post
        $post_id = $this->cpt->create_album_from_google($album_data);
        
        if (is_wp_error($post_id)) {
            $this->log('WP Gallery Link: Error creating album post - ' . $post_id->get_error_message(), 'error');
            
            if ($post_id->get_error_code() === 'album_exists') {
                $error_data = $post_id->get_error_data();
                $edit_url = get_edit_post_link($error_data['post_id'], '');
                $this->log('WP Gallery Link: Album already exists - Post ID ' . $error_data['post_id'], 'info');
                
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
        $this->log('WP Gallery Link: Successfully imported album - Post ID ' . $post_id, 'info');
        
        // Success response
        wp_send_json_success(array(
            'message' => __('Album imported successfully', 'wp-gallery-link'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, ''),
            'view_url' => get_permalink($post_id),
        ));
    }
}

/**
 * Helper function to access the main plugin instance
 */
function wp_gallery_link() {
    return WP_Gallery_Link::get_instance();
}

// Initialize plugin
wp_gallery_link();
