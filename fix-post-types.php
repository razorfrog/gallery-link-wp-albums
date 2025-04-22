
<?php
/**
 * Script to fix post types from 'wp_gallery_album' to 'gphoto_album'
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
echo '<h1>Converting post types from wp_gallery_album to gphoto_album</h1>';

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
        echo '<p>Updated post #' . $post->ID . ': ' . $post->post_title . '</p>';
    } else {
        echo '<p>Failed to update post #' . $post->ID . '</p>';
    }
}

echo '<h2>Conversion complete. Updated ' . $updated_count . ' posts.</h2>';
echo '<p>Please delete this script after use.</p>';
