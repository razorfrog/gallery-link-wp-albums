
<?php
/**
 * Google API Integration
 */
class WP_Gallery_Link_Google_API {
    
    /**
     * Google API client ID
     *
     * @var string
     */
    private $client_id;
    
    /**
     * Google API client secret
     *
     * @var string
     */
    private $client_secret;
    
    /**
     * Google API access token
     *
     * @var string
     */
    private $access_token;
    
    /**
     * Google API refresh token
     *
     * @var string
     */
    private $refresh_token;
    
    /**
     * Google API token expiration
     *
     * @var int
     */
    private $token_expires;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get API credentials from options
        $this->client_id = get_option('wpgl_google_client_id', '');
        $this->client_secret = get_option('wpgl_google_client_secret', '');
        $this->access_token = get_option('wpgl_google_access_token', '');
        $this->refresh_token = get_option('wpgl_google_refresh_token', '');
        $this->token_expires = get_option('wpgl_google_token_expires', 0);
        
        // Add AJAX handlers
        add_action('wp_ajax_wpgl_auth_google', array($this, 'handle_auth_callback'));
        add_action('wp_ajax_wpgl_fetch_albums', array($this, 'ajax_fetch_albums'));
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }
    
    /**
     * Check if the plugin is connected to Google API
     *
     * @return bool True if connected, false otherwise
     */
    public function is_connected() {
        return !empty($this->client_id) && !empty($this->client_secret) && !empty($this->refresh_token);
    }
    
    /**
     * Get the Google auth URL
     *
     * @return string The auth URL
     */
    public function get_auth_url() {
        if (empty($this->client_id)) {
            return '';
        }
        
        $redirect_uri = admin_url('admin-ajax.php') . '?action=wpgl_auth_google';
        
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/photoslibrary.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Handle the auth callback
     */
    public function handle_auth_callback() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-gallery-link'));
        }
        
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            
            // Exchange code for tokens
            $response = $this->request_token($code);
            
            if (is_wp_error($response)) {
                wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=token'));
                exit;
            }
            
            // Save tokens
            $this->save_tokens($response);
            
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&connected=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=auth'));
            exit;
        }
    }
    
    /**
     * Request token from Google API
     *
     * @param string $code The authorization code
     * @return array|WP_Error The response or an error
     */
    private function request_token($code) {
        $redirect_uri = admin_url('admin-ajax.php') . '?action=wpgl_auth_google';
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body) || isset($body['error'])) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from Google API.', 'wp-gallery-link'),
                $body
            );
        }
        
        return $body;
    }
    
    /**
     * Save tokens to options
     *
     * @param array $tokens The tokens to save
     */
    private function save_tokens($tokens) {
        if (isset($tokens['access_token'])) {
            update_option('wpgl_google_access_token', $tokens['access_token']);
            $this->access_token = $tokens['access_token'];
        }
        
        if (isset($tokens['refresh_token'])) {
            update_option('wpgl_google_refresh_token', $tokens['refresh_token']);
            $this->refresh_token = $tokens['refresh_token'];
        }
        
        if (isset($tokens['expires_in'])) {
            $expires = time() + intval($tokens['expires_in']);
            update_option('wpgl_google_token_expires', $expires);
            $this->token_expires = $expires;
        }
    }
    
    /**
     * Refresh the access token
     *
     * @return bool True on success, false on failure
     */
    private function refresh_token() {
        if (empty($this->client_id) || empty($this->client_secret) || empty($this->refresh_token)) {
            return false;
        }
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body) || isset($body['error'])) {
            return false;
        }
        
        $this->save_tokens($body);
        
        return true;
    }
    
    /**
     * Get a valid access token
     *
     * @return string|bool The access token or false on failure
     */
    private function get_access_token() {
        if (empty($this->access_token) || time() >= $this->token_expires) {
            if (!$this->refresh_token()) {
                return false;
            }
        }
        
        return $this->access_token;
    }
    
    /**
     * Make a request to the Google Photos API
     *
     * @param string $endpoint API endpoint
     * @param array $args Request arguments
     * @param string $method HTTP method (GET or POST)
     * @return array|WP_Error The response or an error
     */
    private function make_request($endpoint, $args = array(), $method = 'GET') {
        $access_token = $this->get_access_token();
        
        if (!$access_token) {
            return new WP_Error(
                'invalid_token',
                __('Invalid or expired access token.', 'wp-gallery-link')
            );
        }
        
        $request_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 15
        );
        
        $url = 'https://photoslibrary.googleapis.com/v1/' . $endpoint;
        
        if ($method === 'GET' && !empty($args)) {
            $url = add_query_arg($args, $url);
        } elseif ($method === 'POST' && !empty($args)) {
            $request_args['body'] = json_encode($args);
        }
        
        $response = ($method === 'GET') 
            ? wp_remote_get($url, $request_args)
            : wp_remote_post($url, $request_args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body)) {
            return new WP_Error(
                'empty_response',
                __('Empty response from Google Photos API.', 'wp-gallery-link')
            );
        }
        
        if (isset($body['error'])) {
            return new WP_Error(
                'api_error',
                $body['error']['message'] ?? __('Unknown API error.', 'wp-gallery-link'),
                $body['error']
            );
        }
        
        return $body;
    }
    
    /**
     * Fetch albums from Google Photos API
     *
     * @param int $page_size Number of albums per page
     * @param string $page_token Page token for pagination
     * @return array|WP_Error The albums or an error
     */
    public function fetch_albums($page_size = 50, $page_token = '') {
        $args = array('pageSize' => $page_size);
        
        if (!empty($page_token)) {
            $args['pageToken'] = $page_token;
        }
        
        return $this->make_request('albums', $args);
    }
    
    /**
     * AJAX handler for fetching albums
     */
    public function ajax_fetch_albums() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'wp-gallery-link'));
        }
        
        $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : '';
        $albums = $this->fetch_albums(50, $page_token);
        
        if (is_wp_error($albums)) {
            wp_send_json_error($albums->get_error_message());
        }
        
        wp_send_json_success($albums);
    }
    
    /**
     * Get album details from Google Photos API
     *
     * @param string $album_id The album ID
     * @return array|WP_Error The album details or an error
     */
    public function get_album($album_id) {
        return $this->make_request('albums/' . $album_id);
    }
    
    /**
     * AJAX handler for importing an album
     */
    public function ajax_import_album() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have sufficient permissions.', 'wp-gallery-link'));
        }
        
        if (!isset($_POST['album_id']) || empty($_POST['album_id'])) {
            wp_send_json_error(__('No album ID provided.', 'wp-gallery-link'));
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        
        // Get album details from Google Photos API
        $album = $this->get_album($album_id);
        
        if (is_wp_error($album)) {
            wp_send_json_error($album->get_error_message());
        }
        
        // Check if album already exists
        $existing = new WP_Query(array(
            'post_type' => 'gphoto_album',
            'meta_query' => array(
                array(
                    'key' => '_gphoto_album_id',
                    'value' => $album_id
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        if ($existing->have_posts()) {
            // Update existing album
            $post_id = $existing->posts[0];
            $post = array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($album['title'] ?? __('Untitled Album', 'wp-gallery-link')),
                'post_content' => wp_kses_post($album['description'] ?? ''),
                'post_status' => 'publish'
            );
            
            wp_update_post($post);
        } else {
            // Create new album
            $post_id = wp_insert_post(array(
                'post_title' => sanitize_text_field($album['title'] ?? __('Untitled Album', 'wp-gallery-link')),
                'post_content' => wp_kses_post($album['description'] ?? ''),
                'post_type' => 'gphoto_album',
                'post_status' => 'publish'
            ));
        }
        
        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }
        
        // Save album metadata
        update_post_meta($post_id, '_gphoto_album_id', $album_id);
        update_post_meta($post_id, '_gphoto_album_url', $album['productUrl'] ?? '');
        
        // Get album date
        $album_date = '';
        if (isset($album['mediaItemsCount']) && $album['mediaItemsCount'] > 0) {
            // Use the first media item date as album date
            // Note: In a real implementation, you'd need to fetch album media items
            // and get the earliest date, but that's beyond the scope of this demo
            $album_date = date('Y-m-d');
        }
        update_post_meta($post_id, '_gphoto_album_date', $album_date);
        
        // Set featured image if cover photo is available
        if (isset($album['coverPhotoBaseUrl']) && !empty($album['coverPhotoBaseUrl'])) {
            $cover_url = $album['coverPhotoBaseUrl'];
            
            if (isset($album['coverPhotoMediaItemId'])) {
                // Append size parameters to URL
                $cover_url .= '=w800-h600';
            }
            
            // Download and set featured image
            $this->set_featured_image_from_url($post_id, $cover_url, $album['title'] ?? __('Album Cover', 'wp-gallery-link'));
        }
        
        wp_send_json_success(array(
            'post_id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'view_link' => get_permalink($post_id),
            'title' => get_the_title($post_id)
        ));
    }
    
    /**
     * Set featured image from URL
     *
     * @param int $post_id The post ID
     * @param string $image_url The image URL
     * @param string $title The image title
     * @return int|false The attachment ID or false on failure
     */
    private function set_featured_image_from_url($post_id, $image_url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download file to temp location
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Create attachment data
        $file_array = array(
            'name' => sanitize_file_name($title . '.jpg'),
            'tmp_name' => $temp_file
        );
        
        // Add file to media library
        $attachment_id = media_handle_sideload($file_array, $post_id, $title);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return false;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
}
