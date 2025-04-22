
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
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'initialize_api'));
        add_action('wp_ajax_wpgl_fetch_albums', array($this, 'ajax_fetch_albums'));
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
    }
    
    /**
     * Check if API is authorized
     * 
     * @return bool True if authorized, false otherwise
     */
    public function is_authorized() {
        return !empty($this->access_token);
    }
    
    /**
     * Get authorization URL
     * 
     * @return string Authorization URL
     */
    public function get_auth_url() {
        if (empty($this->credentials['client_id'])) {
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
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Exchange authorization code for access token
     * 
     * @param string $code Authorization code
     * @return bool True if successful, false otherwise
     */
    public function exchange_code_for_token($code) {
        if (empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $this->credentials['redirect_uri'],
                'grant_type' => 'authorization_code'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('wpgl_google_access_token', $this->access_token);
            
            if (isset($body['refresh_token'])) {
                update_option('wpgl_google_refresh_token', $body['refresh_token']);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Refresh access token using refresh token
     * 
     * @return bool True if successful, false otherwise
     */
    public function refresh_access_token() {
        $refresh_token = get_option('wpgl_google_refresh_token');
        
        if (empty($refresh_token) || empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
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
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            update_option('wpgl_google_access_token', $this->access_token);
            return true;
        }
        
        return false;
    }
    
    /**
     * Fetch albums from Google Photos
     * 
     * @return array|WP_Error Albums or error object
     */
    public function fetch_albums() {
        if (!$this->is_authorized()) {
            if (!$this->refresh_access_token()) {
                return new WP_Error('not_authorized', __('Not authorized with Google Photos', 'wp-gallery-link'));
            }
        }
        
        $response = wp_remote_get('https://photoslibrary.googleapis.com/v1/albums', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            // Try refreshing token if unauthorized
            if ($body['error']['status'] === 'UNAUTHENTICATED' || $body['error']['status'] === '401') {
                if ($this->refresh_access_token()) {
                    return $this->fetch_albums();
                }
            }
            
            return new WP_Error('api_error', $body['error']['message']);
        }
        
        if (!isset($body['albums'])) {
            return array();
        }
        
        return $body['albums'];
    }
    
    /**
     * Handle AJAX request to fetch albums
     */
    public function ajax_fetch_albums() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_fetch_albums', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        $albums = $this->fetch_albums();
        
        if (is_wp_error($albums)) {
            wp_send_json_error(array('message' => $albums->get_error_message()));
        }
        
        wp_send_json_success(array('albums' => $albums));
    }
    
    /**
     * Import album as post
     * 
     * @param array $album Album data
     * @return int|WP_Error Post ID or error object
     */
    public function import_album($album) {
        if (!isset($album['id'], $album['title'])) {
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
            return new WP_Error('album_exists', __('Album already imported', 'wp-gallery-link'));
        }
        
        // Create post
        $post_data = array(
            'post_title' => sanitize_text_field($album['title']),
            'post_content' => isset($album['description']) ? sanitize_textarea_field($album['description']) : '',
            'post_status' => 'publish',
            'post_type' => 'gphoto_album'
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
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
            $this->set_album_cover($post_id, $album['coverPhotoBaseUrl']);
        }
        
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
        
        // Add access token to URL if needed
        if (strpos($image_url, 'access_token') === false && $this->access_token) {
            $image_url .= (strpos($image_url, '?') === false ? '?' : '&') . 'access_token=' . $this->access_token;
        }
        
        // Get image from URL
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
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
            return new WP_Error('invalid_image', __('Invalid image type', 'wp-gallery-link'));
        }
        
        // Use a proper file name with extension
        $file_array['name'] = 'album-' . $post_id . '-cover.' . $file_type['ext'];
        
        // Upload file to media library
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
}
