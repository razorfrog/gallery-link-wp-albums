
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
        add_action('wp_ajax_wpgl_refresh_token', array($this, 'ajax_refresh_token'));
        add_action('wp_ajax_wpgl_test_api', array($this, 'ajax_test_api'));
        
        $this->log('API initialized successfully with credentials and token', 'info', array(
            'has_client_id' => !empty($this->client_id),
            'has_client_secret' => !empty($this->client_secret),
            'has_access_token' => !empty($this->access_token),
            'has_refresh_token' => !empty($this->refresh_token),
            'token_expires' => $this->token_expires,
        ));
    }
    
    /**
     * Log a message for debugging
     * 
     * @param string $message The message to log
     * @param string $level The log level (debug, info, warning, error)
     * @param mixed $context Additional context data
     */
    private function log($message, $level = 'debug', $context = null) {
        if (function_exists('wp_gallery_link')) {
            wp_gallery_link()->log('[Google API] ' . $message, $level, $context);
        } else {
            // Fallback if main plugin instance is not available
            error_log('[WP Gallery Link] [Google API] [' . $level . '] ' . $message);
        }
    }
    
    /**
     * Check if the plugin is connected to Google API
     *
     * @return bool True if connected, false otherwise
     */
    public function is_connected() {
        $is_connected = !empty($this->client_id) && !empty($this->client_secret);
        $this->log('Connection check: ' . ($is_connected ? 'Connected' : 'Not connected'), 'debug');
        return $is_connected;
    }
    
    /**
     * Check if the API is authorized (has valid tokens)
     *
     * @return bool True if authorized, false otherwise
     */
    public function is_authorized() {
        $is_authorized = !empty($this->refresh_token);
        $this->log('Authorization check: ' . ($is_authorized ? 'Authorized' : 'Not authorized'), 'info');
        return $is_authorized;
    }
    
    /**
     * Get the Google auth URL
     *
     * @return string The auth URL
     */
    public function get_auth_url() {
        if (empty($this->client_id)) {
            $this->log('Cannot generate auth URL: Missing client ID', 'warning');
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
        
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $this->log('Generated auth URL', 'debug', array('redirect_uri' => $redirect_uri));
        
        return $url;
    }
    
    /**
     * Handle the auth callback
     */
    public function handle_auth_callback() {
        if (!current_user_can('manage_options')) {
            $this->log('Auth callback: Insufficient permissions', 'warning');
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-gallery-link'));
        }
        
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $this->log('Auth callback: Received authorization code', 'info');
            
            // Exchange code for tokens
            $response = $this->request_token($code);
            
            if (is_wp_error($response)) {
                $this->log('Auth callback: Token request failed', 'error', array(
                    'error' => $response->get_error_message()
                ));
                wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=token'));
                exit;
            }
            
            // Save tokens
            $this->save_tokens($response);
            $this->log('Auth callback: Tokens saved successfully', 'info');
            
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&connected=1'));
            exit;
        } else {
            $this->log('Auth callback: No authorization code received', 'error');
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
        
        $this->log('Requesting token with authorization code', 'debug');
        
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
            $this->log('Token request failed', 'error', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);
        
        $this->log('Token request response', 'debug', array(
            'status' => $status,
            'has_access_token' => isset($body['access_token']),
            'has_refresh_token' => isset($body['refresh_token']),
            'has_error' => isset($body['error'])
        ));
        
        if ($status !== 200 || empty($body) || isset($body['error'])) {
            $error_message = isset($body['error_description']) ? $body['error_description'] : 'Unknown error';
            $this->log('Invalid token response', 'error', array(
                'status' => $status,
                'error' => isset($body['error']) ? $body['error'] : 'Unknown error',
                'description' => $error_message
            ));
            
            return new WP_Error(
                'invalid_response',
                __('Invalid response from Google API.', 'wp-gallery-link'),
                $body
            );
        }
        
        $this->log('Token request successful', 'info');
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
            $this->log('Access token saved', 'debug');
        }
        
        if (isset($tokens['refresh_token'])) {
            update_option('wpgl_google_refresh_token', $tokens['refresh_token']);
            $this->refresh_token = $tokens['refresh_token'];
            $this->log('Refresh token saved', 'info');
        }
        
        if (isset($tokens['expires_in'])) {
            $expires = time() + intval($tokens['expires_in']);
            update_option('wpgl_google_token_expires', $expires);
            $this->token_expires = $expires;
            $this->log('Token expiration set', 'debug', array(
                'expires_in' => $tokens['expires_in'],
                'expires_at' => date('Y-m-d H:i:s', $expires)
            ));
        }
    }
    
    /**
     * Refresh the access token
     *
     * @return bool True on success, false on failure
     */
    private function refresh_token() {
        if (empty($this->client_id) || empty($this->client_secret) || empty($this->refresh_token)) {
            $this->log('Cannot refresh token: Missing credentials', 'warning', array(
                'has_client_id' => !empty($this->client_id),
                'has_client_secret' => !empty($this->client_secret),
                'has_refresh_token' => !empty($this->refresh_token)
            ));
            return false;
        }
        
        $this->log('Refreshing token', 'info');
        
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log('Token refresh request failed', 'error', array(
                'error' => $response->get_error_message()
            ));
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status !== 200 || empty($body) || isset($body['error'])) {
            $this->log('Invalid token refresh response', 'error', array(
                'status' => $status,
                'error' => isset($body['error']) ? $body['error'] : 'Unknown error',
                'description' => isset($body['error_description']) ? $body['error_description'] : 'Unknown error'
            ));
            return false;
        }
        
        $this->log('Token refreshed successfully', 'info');
        $this->save_tokens($body);
        
        return true;
    }
    
    /**
     * AJAX handler for refreshing token
     */
    public function ajax_refresh_token() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_debug', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        if ($this->refresh_token()) {
            wp_send_json_success(array('message' => __('Token refreshed successfully', 'wp-gallery-link')));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh token', 'wp-gallery-link')));
        }
    }
    
    /**
     * Get a valid access token
     *
     * @return string|bool The access token or false on failure
     */
    private function get_access_token() {
        // Check if token is expired or will expire in the next 5 minutes
        if (empty($this->access_token) || time() + 300 >= $this->token_expires) {
            $this->log('Access token expired or will expire soon, refreshing', 'debug', array(
                'expires_at' => date('Y-m-d H:i:s', $this->token_expires),
                'now' => date('Y-m-d H:i:s', time())
            ));
            
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
            $this->log('API request failed: Invalid or expired access token', 'error');
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
        
        $this->log('Making API request', 'debug', array(
            'url' => $url,
            'method' => $method,
            'args' => $args
        ));
        
        $start_time = microtime(true);
        
        $response = ($method === 'GET') 
            ? wp_remote_get($url, $request_args)
            : wp_remote_post($url, $request_args);
        
        $request_time = microtime(true) - $start_time;
        
        if (is_wp_error($response)) {
            $this->log('API request failed', 'error', array(
                'url' => $url,
                'error' => $response->get_error_message(),
                'time' => round($request_time, 2) . 's'
            ));
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        $this->log('API request completed', 'debug', array(
            'url' => $url,
            'status' => $status_code,
            'time' => round($request_time, 2) . 's',
            'response_size' => strlen(wp_remote_retrieve_body($response))
        ));
        
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            $this->log('API error response', 'error', array(
                'status' => $status_code,
                'message' => $error_message,
                'error' => isset($body['error']) ? $body['error'] : 'Unknown error'
            ));
            
            return new WP_Error(
                'api_error',
                $error_message,
                $body['error'] ?? null
            );
        }
        
        if (empty($body)) {
            $this->log('Empty API response', 'warning', array(
                'url' => $url,
                'status' => $status_code
            ));
            
            return new WP_Error(
                'empty_response',
                __('Empty response from Google Photos API.', 'wp-gallery-link')
            );
        }
        
        // Log successful request details
        if ($endpoint === 'albums') {
            $this->log('Albums retrieved successfully', 'info', array(
                'count' => isset($body['albums']) ? count($body['albums']) : 0,
                'has_next_page' => isset($body['nextPageToken'])
            ));
        }
        
        return $body;
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_debug', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        // Test API connection by fetching albums
        $result = $this->fetch_albums(1);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array(
                'message' => __('API connection successful', 'wp-gallery-link'),
                'response' => $result
            ));
        }
    }
    
    /**
     * Get debug log specifically for Google API
     *
     * @return array The debug log entries
     */
    public function get_debug_log() {
        if (function_exists('wp_gallery_link')) {
            $log = wp_gallery_link()->get_debug_log();
            
            // Filter for Google API logs only
            $api_log = array();
            
            foreach ($log as $entry) {
                if (strpos($entry['message'], '[Google API]') !== false) {
                    $api_log[] = $entry;
                }
            }
            
            return $api_log;
        }
        
        return array();
    }
    
    /**
     * Fetch albums from Google Photos API
     *
     * @param int $page_size Number of albums per page
     * @param string $page_token Page token for pagination
     * @return array|WP_Error The albums or an error
     */
    public function fetch_albums($page_size = 50, $page_token = '') {
        $this->log('Fetching albums from Google Photos API', 'info', array(
            'page_size' => $page_size,
            'has_page_token' => !empty($page_token)
        ));
        
        $args = array('pageSize' => $page_size);
        
        if (!empty($page_token)) {
            $args['pageToken'] = $page_token;
            $this->log('Using pagination token', 'debug', array('token' => $page_token));
        }
        
        return $this->make_request('albums', $args);
    }
    
    /**
     * AJAX handler for fetching albums
     */
    public function ajax_fetch_albums() {
        // Debug step 1: Log request received
        $this->log('AJAX fetch albums request received', 'info', array(
            'ajax_action' => 'wpgl_fetch_albums',
            'post_data' => $_POST
        ));
        
        // Check nonce for security
        if (!check_ajax_referer('wp-gallery-link-admin', 'nonce', false)) {
            $this->log('AJAX fetch albums: Nonce check failed', 'error');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->log('AJAX fetch albums: Insufficient permissions', 'error');
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wp-gallery-link')));
        }
        
        // Debug step 2: Log authorization status
        $this->log('AJAX fetch albums: Authorization status check', 'debug', array(
            'is_authorized' => $this->is_authorized(),
            'token_expires' => date('Y-m-d H:i:s', $this->token_expires),
        ));
        
        // Debug step 3: Show demo albums if requested
        if (isset($_POST['demo']) && $_POST['demo'] === 'true') {
            $this->log('AJAX fetch albums: Serving demo albums', 'info');
            
            $demo_albums = array(
                'albums' => array(
                    array(
                        'id' => 'demo1',
                        'title' => 'Summer Vacation (Demo)',
                        'mediaItemsCount' => 42,
                        'coverPhotoBaseUrl' => WP_GALLERY_LINK_URL . 'assets/images/default-album.png'
                    ),
                    array(
                        'id' => 'demo2',
                        'title' => 'Family Gathering (Demo)',
                        'mediaItemsCount' => 78,
                        'coverPhotoBaseUrl' => WP_GALLERY_LINK_URL . 'assets/images/default-album.png'
                    ),
                    array(
                        'id' => 'demo3',
                        'title' => 'Nature Photography (Demo)',
                        'mediaItemsCount' => 53,
                        'coverPhotoBaseUrl' => WP_GALLERY_LINK_URL . 'assets/images/default-album.png'
                    )
                ),
                'nextPageToken' => ''
            );
            
            wp_send_json_success($demo_albums);
            return;
        }
        
        $page_token = isset($_POST['page_token']) ? sanitize_text_field($_POST['page_token']) : '';
        
        // Debug step 4: Get albums from API
        $this->log('AJAX fetch albums: Fetching albums from API', 'info', array(
            'page_token' => $page_token
        ));
        
        $albums = $this->fetch_albums(50, $page_token);
        
        if (is_wp_error($albums)) {
            $this->log('AJAX fetch albums: API error', 'error', array(
                'error_code' => $albums->get_error_code(),
                'error_message' => $albums->get_error_message()
            ));
            
            // Check for specific error types
            if ($albums->get_error_code() === 'invalid_token') {
                $this->log('AJAX fetch albums: Invalid token, attempting refresh', 'warning');
                
                if ($this->refresh_token()) {
                    // Try again with fresh token
                    $this->log('AJAX fetch albums: Token refreshed, retrying', 'info');
                    $albums = $this->fetch_albums(50, $page_token);
                    
                    if (is_wp_error($albums)) {
                        $this->log('AJAX fetch albums: Second attempt failed', 'error', array(
                            'error_code' => $albums->get_error_code(),
                            'error_message' => $albums->get_error_message()
                        ));
                        wp_send_json_error(array('message' => $albums->get_error_message()));
                        return;
                    }
                } else {
                    $this->log('AJAX fetch albums: Token refresh failed', 'error');
                    wp_send_json_error(array('message' => __('Failed to refresh authentication token.', 'wp-gallery-link')));
                    return;
                }
            } else {
                wp_send_json_error(array('message' => $albums->get_error_message()));
                return;
            }
        }
        
        // Debug step 5: Log successful response
        $album_count = isset($albums['albums']) ? count($albums['albums']) : 0;
        $this->log('AJAX fetch albums: Success', 'info', array(
            'album_count' => $album_count,
            'has_next_page' => isset($albums['nextPageToken'])
        ));
        
        // If no albums found, provide a helpful message
        if ($album_count === 0) {
            $this->log('AJAX fetch albums: No albums found', 'warning');
            $albums['message'] = __('No albums found in your Google Photos account.', 'wp-gallery-link');
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
        $this->log('Fetching album details', 'info', array('album_id' => $album_id));
        return $this->make_request('albums/' . $album_id);
    }
    
    /**
     * AJAX handler for importing an album
     */
    public function ajax_import_album() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_import', 'nonce', false)) {
            $this->log('AJAX import album: Nonce check failed', 'error');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->log('AJAX import album: Insufficient permissions', 'error');
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wp-gallery-link')));
        }
        
        // Demo mode
        if (isset($_POST['album']) && !isset($_POST['album_id'])) {
            $album_data = $_POST['album'];
            $this->log('AJAX import album: Demo import', 'info', array('album_data' => $album_data));
            
            // Create post for demo album
            $post_id = wp_insert_post(array(
                'post_title' => sanitize_text_field($album_data['title']),
                'post_content' => '',
                'post_type' => 'gphoto_album',
                'post_status' => 'publish'
            ));
            
            if (is_wp_error($post_id)) {
                $this->log('AJAX import album: Demo post creation failed', 'error', array(
                    'error' => $post_id->get_error_message()
                ));
                wp_send_json_error(array('message' => $post_id->get_error_message()));
            } else {
                update_post_meta($post_id, '_gphoto_album_id', sanitize_text_field($album_data['id']));
                
                $this->log('AJAX import album: Demo album imported successfully', 'info', array('post_id' => $post_id));
                
                wp_send_json_success(array(
                    'post_id' => $post_id,
                    'edit_url' => get_edit_post_link($post_id, 'raw'),
                    'view_url' => get_permalink($post_id),
                    'title' => get_the_title($post_id)
                ));
            }
            
            return;
        }
        
        if (!isset($_POST['album_id']) || empty($_POST['album_id'])) {
            $this->log('AJAX import album: No album ID provided', 'error');
            wp_send_json_error(array('message' => __('No album ID provided.', 'wp-gallery-link')));
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        
        // Get album details from Google Photos API
        $album = $this->get_album($album_id);
        
        if (is_wp_error($album)) {
            $this->log('AJAX import album: Failed to get album details', 'error', array(
                'album_id' => $album_id,
                'error' => $album->get_error_message()
            ));
            wp_send_json_error(array('message' => $album->get_error_message()));
        }
        
        $this->log('AJAX import album: Album details retrieved', 'info', array(
            'album_id' => $album_id,
            'title' => isset($album['title']) ? $album['title'] : 'Untitled Album'
        ));
        
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
            $this->log('AJAX import album: Updating existing album post', 'info', array(
                'post_id' => $post_id
            ));
            
            $post = array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($album['title'] ?? __('Untitled Album', 'wp-gallery-link')),
                'post_content' => wp_kses_post($album['description'] ?? ''),
                'post_status' => 'publish'
            );
            
            $update_result = wp_update_post($post);
            
            if (is_wp_error($update_result)) {
                $this->log('AJAX import album: Update failed', 'error', array(
                    'error' => $update_result->get_error_message()
                ));
            }
        } else {
            // Create new album
            $this->log('AJAX import album: Creating new album post', 'info');
            
            $post_id = wp_insert_post(array(
                'post_title' => sanitize_text_field($album['title'] ?? __('Untitled Album', 'wp-gallery-link')),
                'post_content' => wp_kses_post($album['description'] ?? ''),
                'post_type' => 'gphoto_album',
                'post_status' => 'publish'
            ));
        }
        
        if (is_wp_error($post_id)) {
            $this->log('AJAX import album: Post creation failed', 'error', array(
                'error' => $post_id->get_error_message()
            ));
            wp_send_json_error(array('message' => $post_id->get_error_message()));
        }
        
        // Save album metadata
        update_post_meta($post_id, '_gphoto_album_id', $album_id);
        update_post_meta($post_id, '_gphoto_album_url', $album['productUrl'] ?? '');
        
        // Get album date
        $album_date = '';
        if (isset($album['mediaItemsCount']) && $album['mediaItemsCount'] > 0) {
            // Use the current date as album date for demo purposes
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
            
            $this->log('AJAX import album: Setting featured image', 'debug', array(
                'cover_url' => $cover_url
            ));
            
            // Download and set featured image
            $attachment_id = $this->set_featured_image_from_url($post_id, $cover_url, $album['title'] ?? __('Album Cover', 'wp-gallery-link'));
            
            if (!$attachment_id) {
                $this->log('AJAX import album: Failed to set featured image', 'warning');
            }
        }
        
        $this->log('AJAX import album: Album imported successfully', 'info', array('post_id' => $post_id));
        
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
        
        $this->log('Setting featured image from URL', 'debug', array(
            'post_id' => $post_id,
            'image_url' => $image_url
        ));
        
        // Download file to temp location
        $temp_file = download_url($image_url);
        
        if (is_wp_error($temp_file)) {
            $this->log('Failed to download image', 'error', array(
                'error' => $temp_file->get_error_message()
            ));
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
            $this->log('Failed to create attachment', 'error', array(
                'error' => $attachment_id->get_error_message()
            ));
            return false;
        }
        
        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);
        $this->log('Featured image set successfully', 'info', array(
            'attachment_id' => $attachment_id
        ));
        
        return $attachment_id;
    }
}
