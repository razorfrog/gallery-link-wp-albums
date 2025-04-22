
// Add this near the top of the file, after existing constants
define('WP_GALLERY_LINK_DEBUG', true);

// Modify the ajax_import_album method to log more details
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
    
    // Get album details from Google Photos
    $album_data = $this->google_api->get_album($album_id);
    
    if (is_wp_error($album_data)) {
        error_log('WP Gallery Link: Error retrieving album data - ' . $album_data->get_error_message());
        wp_send_json_error(array('message' => $album_data->get_error_message()));
        return;
    }
    
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
