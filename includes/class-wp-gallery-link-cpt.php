
public function create_album_from_google($album_data) {
    // Check if album already exists
    $existing_album = get_posts(array(
        'post_type' => 'gphoto_album',
        'meta_key' => '_gphoto_album_id',
        'meta_value' => $album_data['id'],
        'posts_per_page' => 1
    ));

    if (!empty($existing_album)) {
        error_log('WP Gallery Link CPT: Album with Google ID ' . $album_data['id'] . ' already exists');
        return new WP_Error('album_exists', 'Album already imported', array('post_id' => $existing_album[0]->ID));
    }

    // Create post for the album
    $post_args = array(
        'post_title' => sanitize_text_field($album_data['title']),
        'post_type' => 'gphoto_album', // Ensuring we use gphoto_album consistently
        'post_status' => 'publish'
    );

    $post_id = wp_insert_post($post_args);

    if (is_wp_error($post_id)) {
        error_log('WP Gallery Link CPT: Failed to create album post - ' . $post_id->get_error_message());
        return $post_id;
    }

    // Add meta information
    update_post_meta($post_id, '_gphoto_album_id', $album_data['id']);
    update_post_meta($post_id, '_gphoto_album_cover_url', $album_data['coverPhotoBaseUrl'] ?? '');
    update_post_meta($post_id, '_gphoto_album_photo_count', $album_data['mediaItemsCount'] ?? 0);
    
    // Add more detailed log
    error_log('WP Gallery Link CPT: Created album post - ID ' . $post_id . ', Google Album ID ' . $album_data['id'] . ', Post Type: gphoto_album');

    return $post_id;
}
