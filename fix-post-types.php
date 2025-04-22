
<?php
/**
 * Script to fix post types from 'wp_gallery_album' to 'gphoto_album' and update meta keys
 * 
 * This is a temporary script that should be run once to fix database inconsistencies.
 * Please delete after use.
 */

// Bootstrap WordPress
require_once( dirname( dirname( dirname( __FILE__ ) ) ) . '/wp-load.php' );

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You do not have sufficient permissions to access this page.');
}

// Start conversion
echo '<h1>Converting post types and updating metadata</h1>';

// Get posts with incorrect post type
$posts = get_posts([
    'post_type' => 'wp_gallery_album',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);

echo '<p>Found ' . count($posts) . ' posts to convert.</p>';

// Convert each post
$updated_count = 0;
foreach ($posts as $post) {
    // Update post type
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->posts,
        ['post_type' => 'gphoto_album'],
        ['ID' => $post->ID]
    );
    
    if ($result !== false) {
        $updated_count++;
        echo '<p>Updated post type for #' . $post->ID . ': ' . $post->post_title . '</p>';
        
        // Fix meta keys too - make sure they all use _gphoto_ prefix
        $old_keys = [
            'wp_gallery_album_id' => '_gphoto_album_id',
            'wp_gallery_album_url' => '_gphoto_album_url',
            'wp_gallery_album_date' => '_gphoto_album_date',
            'wp_gallery_album_order' => '_gphoto_album_order',
            'wp_gallery_photo_count' => '_gphoto_photo_count',
            'wp_gallery_album_cover_url' => '_gphoto_album_cover_url'
        ];
        
        foreach ($old_keys as $old_key => $new_key) {
            $old_value = get_post_meta($post->ID, $old_key, true);
            if (!empty($old_value)) {
                update_post_meta($post->ID, $new_key, $old_value);
                delete_post_meta($post->ID, $old_key);
                echo '<p>- Updated meta key ' . $old_key . ' → ' . $new_key . '</p>';
            }
            
            // Also check for keys without the wp_ prefix
            $short_old_key = str_replace('wp_', '', $old_key);
            $short_value = get_post_meta($post->ID, $short_old_key, true);
            if (!empty($short_value)) {
                update_post_meta($post->ID, $new_key, $short_value);
                delete_post_meta($post->ID, $short_old_key);
                echo '<p>- Updated meta key ' . $short_old_key . ' → ' . $new_key . '</p>';
            }
        }
    } else {
        echo '<p>Failed to update post #' . $post->ID . '</p>';
    }
}

echo '<h2>Conversion complete. Updated ' . $updated_count . ' posts.</h2>';
echo '<p>Please delete this script after use.</p>';
