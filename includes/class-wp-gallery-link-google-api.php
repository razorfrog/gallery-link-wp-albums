
<?php
/**
 * Google Photos API functionality
 */
class WP_Gallery_Link_Google_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_wpgl_auth_google', array($this, 'handle_auth_callback'));
        add_action('wp_ajax_wpgl_fetch_albums', array($this, 'ajax_fetch_albums'));
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
        add_action('wp_ajax_wpgl_refresh_token', array($this, 'ajax_refresh_token'));
        add_action('wp_ajax_wpgl_test_api', array($this, 'ajax_test_api'));
    }
    
    /**
     * Check if we have API credentials
     */
    public function has_credentials() {
        $client_id = get_option('wpgl_google_client_id');
        $client_secret = get_option('wpgl_google_client_secret');
        
        return !empty($client_id) && !empty($client_secret);
    }
    
    /**
     * Check if we're connected to Google Photos
     */
    public function is_connected() {
        $access_token = get_option('wpgl_google_access_token');
        $expires = get_option('wpgl_google_token_expires', 0);
        
        wp_gallery_link()->log('Authorization check: ' . (!empty($access_token) ? 'Has token' : 'No token'), 'info');
        
        if (!empty($access_token) && $expires > time()) {
            wp_gallery_link()->log('Token expires in: ' . human_time_diff(time(), $expires), 'info');
            return true;
        }
        
        wp_gallery_link()->log('Not connected or token expired', 'info');
        return false;
    }
    
    /**
     * Check if we're authorized with Google Photos
     */
    public function is_authorized() {
        $access_token = get_option('wpgl_google_access_token');
        $refresh_token = get_option('wpgl_google_refresh_token');
        
        if (empty($access_token) && empty($refresh_token)) {
            wp_gallery_link()->log('Not authorized: No tokens present', 'info');
            return false;
        }
        
        $expires = get_option('wpgl_google_token_expires', 0);
        
        // If we have a refresh token, we're authorized even if the access token is expired
        if (!empty($refresh_token)) {
            wp_gallery_link()->log('Authorized with refresh token', 'info');
            
            // Try to refresh if the token is expired
            if ($expires <= time() && $this->has_credentials()) {
                $this->refresh_access_token();
            }
            
            return true;
        }
        
        // Check if access token is still valid
        if ($expires > time()) {
            wp_gallery_link()->log('Authorized with valid access token', 'info');
            return true;
        }
        
        wp_gallery_link()->log('Not authorized: Token expired', 'info');
        return false;
    }
    
    /**
     * Get the auth URL
     */
    public function get_auth_url() {
        $client_id = get_option('wpgl_google_client_id');
        
        if (empty($client_id)) {
            return '';
        }
        
        $redirect_uri = admin_url('admin-ajax.php') . '?action=wpgl_auth_google';
        
        $params = array(
            'client_id' => $client_id,
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
        $code = isset($_GET['code']) ? $_GET['code'] : '';
        
        if (empty($code)) {
            wp_gallery_link()->log('Auth callback error: No authorization code', 'error');
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=auth'));
            exit;
        }
        
        $client_id = get_option('wpgl_google_client_id');
        $client_secret = get_option('wpgl_google_client_secret');
        $redirect_uri = admin_url('admin-ajax.php') . '?action=wpgl_auth_google';
        
        // Exchange code for tokens
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_gallery_link()->log('Auth token error: ' . $response->get_error_message(), 'error');
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=token'));
            exit;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_gallery_link()->log('Auth token error: ' . $body['error_description'], 'error', $body);
            wp_redirect(admin_url('admin.php?page=wp-gallery-link&error=token'));
            exit;
        }
        
        // Save tokens
        update_option('wpgl_google_access_token', $body['access_token']);
        update_option('wpgl_google_token_expires', time() + $body['expires_in']);
        
        if (isset($body['refresh_token'])) {
            update_option('wpgl_google_refresh_token', $body['refresh_token']);
        }
        
        wp_gallery_link()->log('Authorization successful', 'info');
        
        wp_redirect(admin_url('admin.php?page=wp-gallery-link&connected=1'));
        exit;
    }
    
    /**
     * Refresh access token
     */
    public function refresh_access_token() {
        $refresh_token = get_option('wpgl_google_refresh_token');
        
        if (empty($refresh_token)) {
            wp_gallery_link()->log('Cannot refresh: No refresh token', 'error');
            return false;
        }
        
        $client_id = get_option('wpgl_google_client_id');
        $client_secret = get_option('wpgl_google_client_secret');
        
        // Exchange refresh token for new access token
        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_gallery_link()->log('Token refresh error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_gallery_link()->log('Token refresh error: ' . $body['error_description'], 'error', $body);
            return false;
        }
        
        // Save new access token
        update_option('wpgl_google_access_token', $body['access_token']);
        update_option('wpgl_google_token_expires', time() + $body['expires_in']);
        
        wp_gallery_link()->log('Token refreshed successfully', 'info');
        
        return true;
    }
    
    /**
     * AJAX: Fetch albums
     */
    public function ajax_fetch_albums() {
        // Check nonce - using wpgl_nonce instead of wpgl_debug
        $valid_nonce = check_ajax_referer('wpgl_nonce', 'nonce', false);
        
        if (!$valid_nonce) {
            wp_gallery_link()->log('Album fetch nonce check failed', 'error', $_REQUEST);
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'wp-gallery-link')));
            return;
        }
        
        // Check authorization
        if (!$this->is_authorized()) {
            if (!$this->refresh_access_token()) {
                wp_send_json_error(array('message' => __('Not authorized with Google Photos. Please reconnect your account.', 'wp-gallery-link')));
                return;
            }
        }
        
        // Get page token from request
        $page_token = isset($_POST['pageToken']) ? $_POST['pageToken'] : '';
        
        // Log that we're making the request with the current tokens
        wp_gallery_link()->log('Fetching albums with token expiring at: ' . get_option('wpgl_google_token_expires', 0), 'info');
        
        // Build API request
        $url = 'https://photoslibrary.googleapis.com/v1/albums';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . get_option('wpgl_google_access_token'),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30 // Increased timeout for slower connections
        );
        
        if (!empty($page_token)) {
            $url = add_query_arg('pageToken', $page_token, $url);
            wp_gallery_link()->log('Using page token: ' . $page_token, 'info');
        } else {
            wp_gallery_link()->log('Fetching first page of albums (no page token)', 'info');
        }
        
        // Set a larger page size
        $url = add_query_arg('pageSize', '50', $url);
        
        // Make API request
        wp_gallery_link()->log('Fetching albums from Google Photos', 'info', array('url' => $url));
        $response = wp_remote_get($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            wp_gallery_link()->log('Album fetch error: ' . $response->get_error_message(), 'error');
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        // Parse response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log the raw response for debugging
        wp_gallery_link()->log('Album fetch raw response', 'debug', array(
            'status' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'body_sample' => substr(wp_remote_retrieve_body($response), 0, 1000) . '...' // Log partial body to avoid huge logs
        ));
        
        // Check for API errors
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error', 'wp-gallery-link');
            wp_gallery_link()->log('Album fetch API error: ' . $error_message, 'error', $body);
            
            // Try to refresh token if unauthorized
            if ($status_code === 401) {
                if ($this->refresh_access_token()) {
                    wp_gallery_link()->log('Token refreshed, retrying album fetch', 'info');
                    $this->ajax_fetch_albums();
                    return;
                }
            }
            
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        // Format the albums
        $albums = array();
        if (isset($body['albums']) && is_array($body['albums'])) {
            wp_gallery_link()->log('Albums found: ' . count($body['albums']), 'info');
            
            foreach ($body['albums'] as $album) {
                $albums[] = array(
                    'id' => $album['id'],
                    'title' => $album['title'],
                    'mediaItemsCount' => isset($album['mediaItemsCount']) ? $album['mediaItemsCount'] : 0,
                    'coverPhotoBaseUrl' => isset($album['coverPhotoBaseUrl']) ? $album['coverPhotoBaseUrl'] : '',
                    'creationTime' => isset($album['mediaItemsContainerInfo']['creationTime']) ? $album['mediaItemsContainerInfo']['creationTime'] : ''
                );
            }
        } else {
            wp_gallery_link()->log('No albums found in response', 'warning', $body);
        }
        
        // Check if there's a next page token and log it
        $next_page_token = isset($body['nextPageToken']) ? $body['nextPageToken'] : '';
        if (!empty($next_page_token)) {
            wp_gallery_link()->log('Next page token found: ' . substr($next_page_token, 0, 10) . '...', 'info');
        } else {
            wp_gallery_link()->log('No next page token found - this is the last page', 'info');
        }
        
        // Send response
        wp_gallery_link()->log('Successfully returning ' . count($albums) . ' albums', 'info');
        wp_send_json_success(array(
            'albums' => $albums,
            'nextPageToken' => $next_page_token
        ));
    }
    
    /**
     * AJAX: Import album
     */
    public function ajax_import_album() {
        // Check nonce
        if (!check_ajax_referer('wpgl_nonce', 'nonce', false)) {
            wp_gallery_link()->log('Import album nonce check failed', 'error', $_REQUEST);
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'wp-gallery-link')));
            return;
        }
        
        // Get album data
        $album_id = isset($_POST['album_id']) ? sanitize_text_field($_POST['album_id']) : '';
        
        if (empty($album_id)) {
            wp_gallery_link()->log('Import error: No album ID', 'error');
            wp_send_json_error(array('message' => __('No album ID provided', 'wp-gallery-link')));
            return;
        }
        
        // Log the import attempt with request details
        wp_gallery_link()->log('Album import requested', 'info', array(
            'album_id' => $album_id,
            'is_bulk' => isset($_POST['bulk']) ? 'yes' : 'no',
            'timestamp' => current_time('mysql'),
            'cache_buster' => isset($_POST['_nocache']) ? $_POST['_nocache'] : 'not_set'
        ));
        
        // Check authorization
        if (!$this->is_authorized()) {
            if (!$this->refresh_access_token()) {
                wp_gallery_link()->log('Album import error: Not authorized', 'error');
                wp_send_json_error(array('message' => __('Not authorized with Google Photos. Please reconnect your account.', 'wp-gallery-link')));
                return;
            }
        }
        
        // Make a direct API call to get album details using albums.get endpoint
        $album_details = $this->get_album_details_direct($album_id);
        
        if (is_wp_error($album_details)) {
            wp_gallery_link()->log('Album details fetch error: ' . $album_details->get_error_message(), 'error');
            wp_send_json_error(array('message' => $album_details->get_error_message()));
            return;
        }
        
        // IMPORTANT: Log the raw album details we got from the direct API call
        wp_gallery_link()->log('Raw album details from direct API call', 'debug', $album_details);
        error_log('WP Gallery Link direct albums.get API response: ' . wp_json_encode($album_details));
        
        // Create album post
        $post_id = $this->create_album_post($album_details);
        
        if (is_wp_error($post_id)) {
            wp_gallery_link()->log('Album post creation error: ' . $post_id->get_error_message(), 'error');
            wp_send_json_error(array('message' => $post_id->get_error_message()));
            return;
        }
        
        wp_gallery_link()->log('Album imported successfully: ' . $album_details['title'], 'info', array('post_id' => $post_id));
        
        // Return success with more detailed information and include the raw creation time
        wp_send_json_success(array(
            'message' => sprintf(__('Album "%s" imported successfully', 'wp-gallery-link'), $album_details['title']),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'url'),
            'view_url' => get_permalink($post_id),
            'album_title' => $album_details['title'],
            'album_date_raw' => $album_details['creationTime'], // Include raw date for debugging
            'album_date_saved' => get_post_meta($post_id, '_gphoto_album_date', true), // Include what was actually saved
            'album_id' => $album_id,
            'timestamp' => current_time('mysql'),
            'debug_redirect' => 'false',
            'raw_api_response' => $album_details // Include the full API response
        ));
    }
    
    /**
     * Get album details directly from the albums.get endpoint
     * 
     * @param string $album_id
     * @return array|WP_Error Album details or error
     */
    public function get_album_details_direct($album_id) {
        // Check authorization
        if (!$this->is_authorized()) {
            if (!$this->refresh_access_token()) {
                return new WP_Error('not_authorized', __('Not authorized with Google Photos', 'wp-gallery-link'));
            }
        }
        
        // Build API request for the albums.get endpoint
        $url = 'https://photoslibrary.googleapis.com/v1/albums/' . urlencode($album_id);
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . get_option('wpgl_google_access_token'),
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30 // Longer timeout for API requests
        );
        
        // Make API request
        wp_gallery_link()->log('Fetching album details directly using albums.get: ' . $album_id, 'info');
        error_log('WP Gallery Link: Making direct albums.get API call for album ID: ' . $album_id);
        
        $response = wp_remote_get($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            wp_gallery_link()->log('Direct album details fetch error: ' . $response->get_error_message(), 'error');
            return new WP_Error('api_error', $response->get_error_message());
        }
        
        // Parse response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log the complete raw response for debugging
        error_log('DIRECT GOOGLE PHOTOS API RESPONSE: ' . wp_json_encode($body));
        wp_gallery_link()->log('Direct album.get response (status: ' . $status_code . ')', 'debug', $body);
        
        // Check for API errors
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error', 'wp-gallery-link');
            wp_gallery_link()->log('Direct album details API error: ' . $error_message . ' (code ' . $status_code . ')', 'error', $body);
            return new WP_Error('api_error', $error_message);
        }
        
        // Extract creation date - Check multiple places
        $creation_time = '';
        
        if (!empty($body['creationTime'])) {
            $creation_time = $body['creationTime'];
            error_log('Using direct creationTime from albums.get: ' . $creation_time);
            wp_gallery_link()->log('Found creationTime in direct API call', 'info', $creation_time);
        }
        elseif (!empty($body['mediaItemsContainerInfo']['creationTime'])) {
            $creation_time = $body['mediaItemsContainerInfo']['creationTime'];
            error_log('Using mediaItemsContainerInfo.creationTime from albums.get: ' . $creation_time);
            wp_gallery_link()->log('Found creationTime in mediaItemsContainerInfo', 'info', $creation_time);
        }
        
        // Fall back to searching the title for date information
        if (empty($creation_time)) {
            error_log('No creation date found in API. Attempting to extract from title: ' . $body['title']);
            
            // Try to extract date from title using common date formats
            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $body['title'], $matches)) {
                $month = $matches[1];
                $day = $matches[2];
                $year = $matches[3];
                
                // Format year properly if it's 2-digit
                if (strlen($year) == 2) {
                    $year = '20' . $year; // Assume 2000s
                }
                
                $extracted_date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $creation_time = $extracted_date . 'T00:00:00Z';
                
                error_log('Extracted date from title: ' . $creation_time);
                wp_gallery_link()->log('Extracted date from title', 'info', array(
                    'title' => $body['title'],
                    'extracted_date' => $creation_time
                ));
            }
        }
        
        // Prepare album data with creation time
        return array(
            'id' => $body['id'],
            'title' => $body['title'],
            'mediaItemsCount' => isset($body['mediaItemsCount']) ? $body['mediaItemsCount'] : 0,
            'coverPhotoBaseUrl' => isset($body['coverPhotoBaseUrl']) ? $body['coverPhotoBaseUrl'] : '',
            'productUrl' => isset($body['productUrl']) ? $body['productUrl'] : '',
            'isWriteable' => isset($body['isWriteable']) ? $body['isWriteable'] : false,
            'creationTime' => $creation_time,
            '_raw_response' => $body // Include full raw response for debugging
        );
    }
    
    /**
     * Get album details from API
     * 
     * @param string $album_id
     * @return array|WP_Error Album details or error
     */
    public function get_album_details($album_id) {
        // This method is kept for backward compatibility
        // We now use get_album_details_direct for more accurate data
        return $this->get_album_details_direct($album_id);
    }
    
    /**
     * Create album post
     * 
     * @param array $album_data
     * @return int|WP_Error Post ID or error
     */
    public function create_album_post($album_data) {
        // Check if album already exists
        $existing = get_posts(array(
            'post_type' => 'gphoto_album',
            'meta_key' => '_gphoto_album_id',
            'meta_value' => $album_data['id'],
            'posts_per_page' => 1
        ));

        if (!empty($existing)) {
            wp_gallery_link()->log('Album already exists in database', 'info', array(
                'post_id' => $existing[0]->ID,
                'album_id' => $album_data['id']
            ));
            return $existing[0]->ID;
        }

        // Create new post with the actual album title
        $post_id = wp_insert_post(array(
            'post_title' => $album_data['title'],
            'post_type' => 'gphoto_album',
            'post_status' => 'publish'
        ));

        if (is_wp_error($post_id)) {
            wp_gallery_link()->log('Album post creation error: ' . $post_id->get_error_message(), 'error');
            return $post_id;
        }

        // Save all metadata with proper prefix
        update_post_meta($post_id, '_gphoto_album_id', $album_data['id']);
        
        // Set default order value of 0
        update_post_meta($post_id, '_gphoto_album_order', 0);
        
        if (!empty($album_data['productUrl'])) {
            update_post_meta($post_id, '_gphoto_album_url', $album_data['productUrl']);
        }
        
        if (!empty($album_data['coverPhotoBaseUrl'])) {
            update_post_meta($post_id, '_gphoto_album_cover_url', $album_data['coverPhotoBaseUrl']);
            
            // Try to set the cover photo as featured image
            $this->set_featured_image($post_id, $album_data['coverPhotoBaseUrl'], $album_data['title']);
        }
        
        if (!empty($album_data['mediaItemsCount'])) {
            update_post_meta($post_id, '_gphoto_photo_count', intval($album_data['mediaItemsCount']));
        }
        
        // IMPROVED DATE HANDLING - Log everything and be more explicit
        error_log('DATE PROCESSING FOR ALBUM: ' . $album_data['title']);
        wp_gallery_link()->log('Date processing details', 'debug', array(
            'raw_creation_time' => isset($album_data['creationTime']) ? $album_data['creationTime'] : 'NOT SET',
            'album_id' => $album_data['id'],
            'album_title' => $album_data['title']
        ));
        
        // Always store the original date format as metadata
        if (!empty($album_data['creationTime'])) {
            update_post_meta($post_id, '_gphoto_album_raw_date', $album_data['creationTime']);
            error_log('Saved raw date: ' . $album_data['creationTime']);
        }
        
        // Try to parse the date from multiple sources with improved approach
        if (!empty($album_data['creationTime'])) {
            $creation_time = $album_data['creationTime'];
            $parsed_timestamp = false;
            
            // Try with DateTime first (most reliable)
            try {
                $date = new DateTime($creation_time);
                $parsed_timestamp = $date->getTimestamp();
                $formatted_date = $date->format('Y-m-d');
                error_log('DateTime parsed successfully: ' . $formatted_date);
                wp_gallery_link()->log('DateTime parsing successful', 'info', $formatted_date);
            } catch (Exception $e) {
                error_log('DateTime parsing failed: ' . $e->getMessage());
                wp_gallery_link()->log('DateTime parsing failed', 'warning', $e->getMessage());
                
                // Fallback to strtotime
                $parsed_timestamp = strtotime($creation_time);
                if ($parsed_timestamp !== false) {
                    $formatted_date = date('Y-m-d', $parsed_timestamp);
                    error_log('strtotime parsed successfully: ' . $formatted_date);
                    wp_gallery_link()->log('strtotime parsing successful', 'info', $formatted_date);
                } else {
                    error_log('strtotime parsing failed');
                    wp_gallery_link()->log('strtotime parsing failed', 'warning');
                }
            }
            
            // If all parsing failed, try manual extraction
            if ($parsed_timestamp === false) {
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $creation_time, $matches)) {
                    $formatted_date = $matches[1];
                    error_log('Manual regex extraction successful: ' . $formatted_date);
                    wp_gallery_link()->log('Manual regex extraction successful', 'info', $formatted_date);
                } else {
                    // Last resort: try to extract from title
                    if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $album_data['title'], $matches)) {
                        $month = $matches[1];
                        $day = $matches[2];
                        $year = $matches[3];
                        
                        // Format year properly if it's 2-digit
                        if (strlen($year) == 2) {
                            $year = '20' . $year; // Assume 2000s
                        }
                        
                        $formatted_date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        error_log('Extracted date from title: ' . $formatted_date);
                        wp_gallery_link()->log('Extracted date from title', 'info', $formatted_date);
                    } else {
                        // Last resort: use today's date
                        $formatted_date = date('Y-m-d');
                        error_log('All parsing failed, using today\'s date: ' . $formatted_date);
                        wp_gallery_link()->log('All parsing failed, using current date', 'warning');
                    }
                }
            }
            
            // Save the date to post meta
            update_post_meta($post_id, '_gphoto_album_date', $formatted_date);
            error_log('Final saved album date: ' . $formatted_date);
            wp_gallery_link()->log('Saved album date', 'info', $formatted_date);
            
        } else {
            // Try to extract date from title if no creation time available
            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})/', $album_data['title'], $matches)) {
                $month = $matches[1];
                $day = $matches[2];
                $year = $matches[3];
                
                // Format year properly if it's 2-digit
                if (strlen($year) == 2) {
                    $year = '20' . $year; // Assume 2000s
                }
                
                $formatted_date = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                error_log('No creation date in API, extracted from title: ' . $formatted_date);
                wp_gallery_link()->log('No creation date in API, extracted from title', 'info', $formatted_date);
                
                update_post_meta($post_id, '_gphoto_album_date', $formatted_date);
            } else {
                // No creation time available and couldn't extract from title, use current date
                error_log('No creation date found in API response or title, using today\'s date');
                wp_gallery_link()->log('No creation date found in API or title, using current date', 'warning');
                update_post_meta($post_id, '_gphoto_album_date', date('Y-m-d'));
            }
        }

        return $post_id;
    }
    
    /**
     * Set featured image from URL
     */
    public function set_featured_image($post_id, $image_url, $title) {
        // Add size parameter for Google Photos URL
        $image_url = $image_url . '=w800-h600';
        
        // Get the file
        $response = wp_remote_get($image_url);
        
        if (is_wp_error($response)) {
            wp_gallery_link()->log('Featured image fetch error: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            wp_gallery_link()->log('Empty image data for featured image', 'error');
            return new WP_Error('empty_image', __('Empty image data', 'wp-gallery-link'));
        }
        
        // Upload the image
        $upload = wp_upload_bits(sanitize_file_name($title . '.jpg'), null, $image_data);
        
        if ($upload['error']) {
            wp_gallery_link()->log('Featured image upload error: ' . $upload['error'], 'error');
            return new WP_Error('upload_error', $upload['error']);
        }
        
        // Create attachment
        $filename = $upload['file'];
        $filetype = wp_check_filetype(basename($filename), null);
        
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attach_id = wp_insert_attachment($attachment, $filename, $post_id);
        
        if (is_wp_error($attach_id)) {
            wp_gallery_link()->log('Attachment creation error: ' . $attach_id->get_error_message(), 'error');
            return $attach_id;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        // Set as featured image
        set_post_thumbnail($post_id, $attach_id);
        
        wp_gallery_link()->log('Featured image set for album', 'info', array('post_id' => $post_id, 'image_id' => $attach_id));
        
        return $attach_id;
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_api() {
        // Check nonce - using wpgl_nonce instead of wpgl_debug
        $valid_nonce = check_ajax_referer('wpgl_nonce', 'nonce', false);
        
        if (!$valid_nonce) {
            wp_gallery_link()->log('API test nonce check failed', 'error', $_REQUEST);
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'wp-gallery-link')));
            return;
        }
        
        // Check authorization
        if (!$this->is_authorized()) {
            if (!$this->refresh_access_token()) {
                wp_send_json_error(array('message' => __('Not authorized with Google Photos. Please reconnect your account.', 'wp-gallery-link')));
                return;
            }
        }
        
        // Log token information
        $expires = get_option('wpgl_google_token_expires', 0);
        $access_token = get_option('wpgl_google_access_token', '');
        $token_snippet = substr($access_token, 0, 10) . '...';
        
        wp_gallery_link()->log('Testing API with token: ' . $token_snippet, 'info', array(
            'expires_in' => human_time_diff(time(), $expires),
            'expired' => ($expires <= time() ? 'Yes' : 'No')
        ));
        
        // Build API request
        $url = 'https://photoslibrary.googleapis.com/v1/albums?pageSize=1';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        // Make API request
        wp_gallery_link()->log('Testing API connection', 'info');
        $response = wp_remote_get($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            wp_gallery_link()->log('API test error: ' . $response->get_error_message(), 'error');
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        // Parse response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Log raw response
        wp_gallery_link()->log('API test raw response', 'debug', array(
            'status' => $status_code,
            'headers' => wp_remote_retrieve_headers($response),
            'body' => wp_remote_retrieve_body($response)
        ));
        
        // Check for API errors
        if ($status_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error', 'wp-gallery-link');
            wp_gallery_link()->log('API test error: ' . $error_message, 'error', $body);
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        wp_gallery_link()->log('API test successful', 'info', array('status_code' => $status_code));
        
        // Send success response
        wp_send_json_success(array(
            'message' => __('API connection successful', 'wp-gallery-link'),
            'albums_count' => isset($body['albums']) ? count($body['albums']) : 0
        ));
    }
    
    /**
     * AJAX: Refresh token
     */
    public function ajax_refresh_token() {
        // Check nonce - using wpgl_nonce instead of wpgl_debug
        if (!check_ajax_referer('wpgl_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }
        
        if ($this->refresh_access_token()) {
            wp_send_json_success(array('message' => __('Token refreshed successfully', 'wp-gallery-link')));
        } else {
            wp_send_json_error(array('message' => __('Failed to refresh token', 'wp-gallery-link')));
        }
    }
}

