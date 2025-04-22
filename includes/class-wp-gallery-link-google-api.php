<?php
/**
 * Google Photos API Integration
 */
class WP_Gallery_Link_Google_API {
    
    /**
     * API client credentials
     * @var array
     */
    private $credentials;
    
    /**
     * Access token for API requests
     * @var string
     */
    private $access_token;
    
    /**
     * Debug mode
     * @var boolean
     */
    private $debug = true;
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'initialize_api'));
        add_action('wp_ajax_wpgl_fetch_albums', array($this, 'ajax_fetch_albums'));
    }
    
    /**
     * Log debug message
     * 
     * @param mixed $message The message to log
     * @param string $level The log level (info, error, warning)
     */
    private function log($message, $level = 'info') {
        if (!$this->debug) {
            return;
        }
        
        // Format message for array/object
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        
        // Add timestamp
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] [{$level}] {$message}";
        
        // Log to PHP error log
        error_log('WP Gallery Link: ' . $formatted_message);
        
        // Store in transient for admin display (keep last 100 messages)
        $log_messages = get_transient('wpgl_debug_log') ?: array();
        array_unshift($log_messages, $formatted_message);
        $log_messages = array_slice($log_messages, 0, 100);
        set_transient('wpgl_debug_log', $log_messages, 24 * HOUR_IN_SECONDS);
    }
    
    /**
     * Initialize API with credentials
     */
    public function initialize_api() {
        $this->credentials = array(
            'client_id' => get_option('wpgl_google_client_id'),
            'client_secret' => get_option('wpgl_google_client_secret'),
            'redirect_uri' => admin_url('admin.php?page=wp-gallery-link-auth')
        );
        
        $this->access_token = get_option('wpgl_google_access_token');
        
        if (empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            $this->log('API not initialized: Missing credentials', 'warning');
        } else if (empty($this->access_token)) {
            $this->log('API initialized but not authorized: Missing access token', 'warning');
        } else {
            $this->log('API initialized successfully with credentials and token');
        }
    }
    
    /**
     * Check if API is authorized
     * 
     * @return bool True if authorized, false otherwise
     */
    public function is_authorized() {
        $authorized = !empty($this->access_token);
        $this->log('Authorization check: ' . ($authorized ? 'Authorized' : 'Not authorized'));
        return $authorized;
    }
    
    /**
     * Get authorization URL
     * 
     * @return string Authorization URL
     */
    public function get_auth_url() {
        if (empty($this->credentials['client_id'])) {
            $this->log('Cannot generate auth URL: Missing client ID', 'error');
            return '';
        }
        
        $params = array(
            'client_id' => $this->credentials['client_id'],
            'redirect_uri' => $this->credentials['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );
        
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $this->log('Generated auth URL: ' . $auth_url);
        
        return $auth_url;
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @return bool True if successful, false otherwise
     */
    public function exchange_code_for_token($code) {
        $this->log('Exchanging auth code for token');
        
        if (empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            $this->log('Token exchange failed: Missing credentials', 'error');
            return false;
        }
        
        $request_params = array(
            'body' => array(
                'client_id' => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $this->credentials['redirect_uri'],
                'grant_type' => 'authorization_code'
            )
        );
        
        $this->log('Token exchange request: ' . wp_json_encode($request_params));
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', $request_params);
        
        if (is_wp_error($response)) {
            $this->log('Token exchange failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log('Token response received: ' . wp_json_encode($body));
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('wpgl_google_access_token', $this->access_token);
            
            if (isset($body['refresh_token'])) {
                update_option('wpgl_google_refresh_token', $body['refresh_token']);
                $this->log('Refresh token saved');
            }
            
            $this->log('Access token saved successfully');
            return true;
        }
        
        $this->log('Token exchange failed: Invalid response', 'error');
        return false;
    }
    
    /**
     * Refresh access token using refresh token
     * 
     * @return bool True if successful, false otherwise
     */
    public function refresh_access_token() {
        $refresh_token = get_option('wpgl_google_refresh_token');
        $this->log('Attempting to refresh access token');
        
        if (empty($refresh_token)) {
            $this->log('Token refresh failed: No refresh token available', 'error');
            return false;
        }
        
        if (empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            $this->log('Token refresh failed: Missing credentials', 'error');
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log('Token refresh failed: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log('Token refresh response: ' . wp_json_encode($body));
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('wpgl_google_access_token', $this->access_token);
            $this->log('Access token refreshed successfully');
            return true;
        }
        
        $this->log('Token refresh failed: Invalid response', 'error');
        return false;
    }
    
    /**
     * Fetch albums from Google Photos
     * 
     * @return array|WP_Error Albums or error object
     */
    public function fetch_albums() {
        $this->log('Fetching albums from Google Photos');
        
        if (!$this->is_authorized()) {
            $this->log('Not authorized, attempting to refresh token');
            if (!$this->refresh_access_token()) {
                $error = new WP_Error('not_authorized', __('Not authorized with Google Photos', 'wp-gallery-link'));
                $this->log('Failed to fetch albums: ' . $error->get_error_message(), 'error');
                return $error;
            }
        }
        
        $this->log('Making API request to fetch albums');
        $response = wp_remote_get('https://photoslibrary.googleapis.com/v1/albums', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log('API request failed: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $this->log('API response status: ' . $status_code);
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $this->log('API response body: ' . wp_json_encode($body));
        
        if (isset($body['error'])) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            $error_status = isset($body['error']['status']) ? $body['error']['status'] : '';
            
            $this->log('API error: ' . $error_message . ' (status: ' . $error_status . ')', 'error');
            
            // Try refreshing token if unauthorized
            if ($error_status === 'UNAUTHENTICATED' || $status_code === 401) {
                $this->log('Attempting to refresh token due to authentication error');
                if ($this->refresh_access_token()) {
                    $this->log('Token refreshed, retrying album fetch');
                    return $this->fetch_albums();
                }
            }
            
            return new WP_Error('api_error', $error_message);
        }
        
        if (!isset($body['albums'])) {
            $this->log('No albums found in API response');
            return array();
        }
        
        $this->log('Successfully retrieved ' . count($body['albums']) . ' albums');
        return $body['albums'];
    }
    
    /**
     * Handle AJAX request to fetch albums
     */
    public function ajax_fetch_albums() {
        $this->log('AJAX request received: wpgl_fetch_albums');
        
        // Check nonce for security
        if (!check_ajax_referer('wpgl_fetch_albums', 'nonce', false)) {
            $this->log('Security check failed: Invalid nonce', 'error');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            $this->log('Permission denied: User cannot edit posts', 'error');
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        // For demo purposes, provide demo albums if requested
        if (isset($_GET['demo']) && $_GET['demo'] === 'true') {
            $this->log('Demo mode enabled, returning demo albums');
            $demo_albums = array(
                array(
                    'id' => 'demo1',
                    'title' => 'Summer Vacation',
                    'mediaItemsCount' => 42,
                    'coverPhotoBaseUrl' => plugin_dir_url(dirname(__FILE__)) . 'assets/images/default-album.png'
                ),
                array(
                    'id' => 'demo2',
                    'title' => 'Family Gathering',
                    'mediaItemsCount' => 78,
                    'coverPhotoBaseUrl' => plugin_dir_url(dirname(__FILE__)) . 'assets/images/default-album.png'
                ),
                array(
                    'id' => 'demo3',
                    'title' => 'Nature Photography',
                    'mediaItemsCount' => 53,
                    'coverPhotoBaseUrl' => plugin_dir_url(dirname(__FILE__)) . 'assets/images/default-album.png'
                )
            );
            
            wp_send_json_success(array('albums' => $demo_albums));
            return;
        }
        
        $albums = $this->fetch_albums();
        
        if (is_wp_error($albums)) {
            $this->log('Error fetching albums: ' . $albums->get_error_message(), 'error');
            wp_send_json_error(array('message' => $albums->get_error_message()));
            return;
        }
        
        // Check for existing albums to mark as imported
        if (!empty($albums) && is_array($albums)) {
            foreach ($albums as &$album) {
                // Check if album is already imported
                $existing = get_posts(array(
                    'post_type' => 'gphoto_album',
                    'meta_key' => '_gphoto_album_id',
                    'meta_value' => $album['id'],
                    'posts_per_page' => 1
                ));
                
                if (!empty($existing)) {
                    $album['imported'] = true;
                    $album['editLink'] = get_edit_post_link($existing[0]->ID, 'raw');
                    $album['viewLink'] = get_permalink($existing[0]->ID);
                }
            }
        }
        
        $this->log('Sending ' . count($albums) . ' albums as AJAX response');
        wp_send_json_success(array('albums' => $albums));
    }
    
    /**
     * Import album as post
     * 
     * @param array $album Album data
     * @return int|WP_Error Post ID or error object
     */
    public function import_album($album) {
        $this->log('Importing album: ' . wp_json_encode($album));
        
        if (!isset($album['id'], $album['title'])) {
            $this->log('Invalid album data for import', 'error');
            return new WP_Error('invalid_album', __('Invalid album data', 'wp-gallery-link'));
        }
        
        // Check if album already exists
        $existing = get_posts(array(
            'post_type' => 'gphoto_album',
            'meta_key' => '_gphoto_album_id',
            'meta_value' => $album['id'],
            'posts_per_page' => 1
        ));
        
        if (!empty($existing)) {
            $this->log('Album already imported: ' . $album['id'], 'warning');
            return new WP_Error('album_exists', __('Album already imported', 'wp-gallery-link'));
        }
        
        // Create post
        $post_data = array(
            'post_title' => sanitize_text_field($album['title']),
            'post_content' => isset($album['description']) ? sanitize_textarea_field($album['description']) : '',
            'post_status' => 'publish',
            'post_type' => 'gphoto_album'
        );
        
        $this->log('Creating post with data: ' . wp_json_encode($post_data));
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->log('Error creating post: ' . $post_id->get_error_message(), 'error');
            return $post_id;
        }
        
        // Save meta data
        update_post_meta($post_id, '_gphoto_album_id', sanitize_text_field($album['id']));
        
        if (isset($album['productUrl'])) {
            update_post_meta($post_id, '_gphoto_album_url', esc_url_raw($album['productUrl']));
        }
        
        if (isset($album['mediaItemsCount'])) {
            update_post_meta($post_id, '_gphoto_photo_count', intval($album['mediaItemsCount']));
        }
        
        // Set default display order
        update_post_meta($post_id, '_gphoto_album_order', 0);
        
        // Set album date if available or use current date
        $album_date = isset($album['creationTime']) ? date('Y-m-d', strtotime($album['creationTime'])) : date('Y-m-d');
        update_post_meta($post_id, '_gphoto_album_date', $album_date);
        
        // Set cover image if available
        if (isset($album['coverPhotoBaseUrl']) && !empty($album['coverPhotoBaseUrl'])) {
            $this->log('Setting album cover image from URL: ' . $album['coverPhotoBaseUrl']);
            $cover_id = $this->set_album_cover($post_id, $album['coverPhotoBaseUrl']);
            
            if (is_wp_error($cover_id)) {
                $this->log('Error setting cover image: ' . $cover_id->get_error_message(), 'warning');
            } else {
                $this->log('Cover image set successfully: Attachment ID ' . $cover_id);
            }
        }
        
        $this->log('Album imported successfully: Post ID ' . $post_id);
        return $post_id;
    }
    
    /**
     * Set album cover by downloading from Google Photos
     * 
     * @param int $post_id Post ID
     * @param string $image_url Image URL
     * @return int|WP_Error Attachment ID or error object
     */
    private function set_album_cover($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $this->log('Setting album cover: Post ID ' . $post_id . ', URL: ' . $image_url);
        
        // Add access token to URL if needed
        if (strpos($image_url, 'access_token') === false && $this->access_token) {
            $image_url .= (strpos($image_url, '?') === false ? '?' : '&') . 'access_token=' . $this->access_token;
            $this->log('Added access token to image URL');
        }
        
        // Get image from URL
        $this->log('Downloading image from URL');
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            $this->log('Error downloading image: ' . $temp_file->get_error_message(), 'error');
            return $temp_file;
        }
        
        // Prepare file data
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $temp_file
        );
        
        // Check file type
        $file_type = wp_check_filetype($file_array['name'], null);
        if (empty($file_type['ext'])) {
            @unlink($temp_file);
            $this->log('Invalid image type', 'error');
            return new WP_Error('invalid_image', __('Invalid image type', 'wp-gallery-link'));
        }
        
        // Use a proper file name with extension
        $file_array['name'] = 'album-' . $post_id . '-cover.' . $file_type['ext'];
        
        // Upload file to media library
        $this->log('Uploading image to media library');
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            $this->log('Error uploading image: ' . $attachment_id->get_error_message(), 'error');
            return $attachment_id;
        }
        
        // Set as featured image
        $this->log('Setting image as featured image');
        set_post_thumbnail($post_id, $attachment_id);
        
        $this->log('Album cover set successfully: Attachment ID ' . $attachment_id);
        return $attachment_id;
    }
    
    /**
     * Get debug log
     * 
     * @return array Log messages
     */
    public function get_debug_log() {
        return get_transient('wpgl_debug_log') ?: array();
    }
    
    /**
     * Clear debug log
     * 
     * @return void
     */
    public function clear_debug_log() {
        delete_transient('wpgl_debug_log');
        $this->log('Debug log cleared');
    }
}
