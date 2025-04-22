<?php
/**
 * Main plugin class for Google Photos Albums
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
    public $google_api; // Public to allow access from other classes
    
    /**
     * Instance of the CPT class
     */
    public $cpt; // Changed from private to public to prevent access issues
    
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
        
        // Verify CPT class availability with detailed debugging
        if (!class_exists('WP_Gallery_Link_CPT')) {
            $this->log('WP_Gallery_Link_CPT class does not exist yet. Currently loaded classes: ' . implode(', ', get_declared_classes()), 'error');
            
            // Try to explicitly include the CPT file
            $possible_cpt_paths = array(
                WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-cpt.php',
                WP_GALLERY_LINK_PATH . 'class-wp-gallery-link-cpt.php'
            );
            
            foreach ($possible_cpt_paths as $path) {
                if (file_exists($path)) {
                    $this->log('Attempting to manually include CPT class from: ' . $path, 'info');
                    include_once $path;
                    break;
                }
            }
        }
        
        // Initialize CPT with additional error checking
        if (class_exists('WP_Gallery_Link_CPT')) {
            $this->cpt = new WP_Gallery_Link_CPT();
            $this->log('CPT class successfully initialized', 'info');
        } else {
            $this->log('WP Gallery Link: CPT class not found after manual inclusion attempts', 'error');
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Google Photos Albums: Critical error - Could not find CPT class.</p></div>';
            });
        }
        
        // Load Google API class
        $google_api_loaded = false;
        
        // Try different possible file paths
        $possible_api_paths = array(
            WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-google-api.php',
            WP_GALLERY_LINK_PATH . 'src/includes/class-wp-gallery-link-google-api.php'
        );
        
        foreach ($possible_api_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $this->log('WP Gallery Link: Google API class file included from ' . $path, 'info');
                
                // Initialize Google API
                if (class_exists('WP_Gallery_Link_Google_API')) {
                    $this->google_api = new WP_Gallery_Link_Google_API();
                    $google_api_loaded = true;
                }
                break;
            }
        }
        
        if (!$google_api_loaded) {
            // Create a stub Google API class for development purposes
            $this->log('WP Gallery Link: Using stub Google API class', 'info');
            $this->google_api = new stdClass();
            $this->google_api->is_connected = function() { return false; };
            $this->google_api->get_auth_url = function() { return '#'; };
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_wpgl_fetch_albums', array($this, 'ajax_fetch_albums'));
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }
    
    /**
     * Add a log entry
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
            } else {
                error_log('[WP Gallery Link] [' . strtoupper($level) . '] ' . $message);
            }
        }
    }
    
    /**
     * Get the debug log
     */
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    /**
     * AJAX handler for fetching albums
     */
    public function ajax_fetch_albums() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpgl_nonce')) {
            $this->log('WP Gallery Link: Nonce verification failed', 'error');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }

        $this->log('WP Gallery Link: Fetching albums via AJAX', 'info');

        // Check if we're in demo mode
        $demo_mode = false;
        
        // Check for explicit demo parameter
        if (isset($_POST['demo']) && $_POST['demo'] === 'true') {
            $demo_mode = true;
            $this->log('WP Gallery Link: Demo mode enabled via parameter', 'info');
        }
        
        // Also check for the filter (which can be set via URL)
        if (!$demo_mode) {
            $demo_mode = apply_filters('wpgl_is_demo_mode', false);
            if ($demo_mode) {
                $this->log('WP Gallery Link: Demo mode enabled via filter', 'info');
            }
        }
        
        // If we're not in demo mode and we have a Google API instance, use it
        if (!$demo_mode && isset($this->google_api) && method_exists($this->google_api, 'ajax_fetch_albums')) {
            $this->log('WP Gallery Link: Using Google API to fetch albums', 'info');
            $this->google_api->ajax_fetch_albums();
            return;
        }

        // Otherwise fall back to demo mode
        $this->log('WP Gallery Link: Using demo mode for albums', 'info');

        // Accept perPage as an argument, default 6 if not given (so UI can override)
        $per_page = isset($_POST['perPage']) ? intval($_POST['perPage']) : 6;

        // Accept "pageToken" as a base64-encoded offset (simulate real tokens)
        $page_token = isset($_POST['pageToken']) ? sanitize_text_field($_POST['pageToken']) : '';
        $offset = 0;
        if ($page_token) {
            // Decode a real offset if pagetoken present (simulate real tokens)
            $decoded = base64_decode($page_token);
            if ($decoded !== false && is_numeric($decoded)) {
                $offset = intval($decoded);
            }
        }

        // Demo: Generate full sample albums array (as before)
        $all_sample_albums = array(
            array(
                'id' => 'album1',
                'title' => 'Sample Album 1',
                'productUrl' => 'https://photos.google.com/album/sample1',
                'mediaItemsCount' => 25,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Album1',
                'creationTime' => '2023-01-15T10:30:00Z'
            ),
            array(
                'id' => 'album2',
                'title' => 'Sample Album 2',
                'productUrl' => 'https://photos.google.com/album/sample2',
                'mediaItemsCount' => 15,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Album2',
                'creationTime' => '2023-02-20T14:45:00Z'
            ),
            array(
                'id' => 'album3',
                'title' => 'Sample Album 3',
                'productUrl' => 'https://photos.google.com/album/sample3',
                'mediaItemsCount' => 42,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Album3',
                'creationTime' => '2023-03-10T09:15:00Z'
            ),
            array(
                'id' => 'album4',
                'title' => 'Sample Album 4',
                'productUrl' => 'https://photos.google.com/album/sample4',
                'mediaItemsCount' => 8,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Album4',
                'creationTime' => '2023-04-05T16:20:00Z'
            ),
            array(
                'id' => 'album5',
                'title' => 'Family Vacation 2023',
                'productUrl' => 'https://photos.google.com/album/family',
                'mediaItemsCount' => 120,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Family',
                'creationTime' => '2023-07-15T08:30:00Z'
            ),
            array(
                'id' => 'album6',
                'title' => 'Birthday Party',
                'productUrl' => 'https://photos.google.com/album/birthday',
                'mediaItemsCount' => 65,
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Birthday',
                'creationTime' => '2023-09-22T18:45:00Z'
            )
        );

        // More demo albums for MANY pages
        for ($i = 7; $i <= 48; $i++) {
            $all_sample_albums[] = array(
                'id' => 'album' . $i,
                'title' => 'Sample Album ' . $i,
                'productUrl' => 'https://photos.google.com/album/sample' . $i,
                'mediaItemsCount' => rand(5, 100),
                'coverPhotoBaseUrl' => 'https://via.placeholder.com/200x200?text=Album' . $i,
                'creationTime' => '2023-' . sprintf('%02d', rand(1, 12)) . '-' . sprintf('%02d', rand(1, 28)) . 'T' . rand(10, 23) . ':' . rand(10, 59) . ':00Z'
            );
        }

        $total = count($all_sample_albums);

        // Correctly slice the array for pagination
        $albums = array_slice($all_sample_albums, $offset, $per_page);

        // Next page token (encode offset + per_page if more remain)
        $new_offset = $offset + $per_page;
        if ($new_offset < $total) {
            $next_page_token = base64_encode((string)$new_offset);
        } else {
            $next_page_token = '';
        }

        $this->log('WP Gallery Link: Returning ' . count($albums) . ' demo albums (offset=' . $offset . ', per_page=' . $per_page . ') of ' . $total, 'info');

        wp_send_json_success(array(
            'albums' => $albums,
            'nextPageToken' => $next_page_token
        ));
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
        $is_bulk = isset($_POST['bulk']) && $_POST['bulk'] == true;
        
        $this->log('WP Gallery Link: Attempting to import album ID ' . $album_id . ($is_bulk ? ' (bulk import)' : ''), 'info');
        
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
        
        // Create album post if CPT class is available
        if (isset($this->cpt) && method_exists($this->cpt, 'create_album_from_google')) {
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
                        'status' => 'exists'
                    ));
                } else {
                    wp_send_json_error(array('message' => $post_id->get_error_message()));
                }
                return;
            }
            
            // Log successful album import
            $this->log('WP Gallery Link: Successfully imported album - Post ID ' . $post_id, 'info');
            
            // Success response (different handling for bulk imports)
            if ($is_bulk) {
                wp_send_json_success(array(
                    'message' => __('Album imported successfully', 'wp-gallery-link'),
                    'post_id' => $post_id,
                    'status' => 'imported'
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('Album imported successfully', 'wp-gallery-link'),
                    'post_id' => $post_id,
                    'edit_url' => get_edit_post_link($post_id, ''),
                    'view_url' => get_permalink($post_id),
                    'status' => 'imported'
                ));
            }
        } else {
            $this->log('WP Gallery Link: CPT class or method not available', 'error');
            if (!isset($this->cpt)) {
                $this->log('WP Gallery Link: $this->cpt is not set', 'error');
            }
            if (isset($this->cpt) && !method_exists($this->cpt, 'create_album_from_google')) {
                $this->log('WP Gallery Link: create_album_from_google method not found in CPT class', 'error');
            }
            wp_send_json_error(array('message' => __('Plugin error: Custom post type handler not available', 'wp-gallery-link')));
        }
    }
}
