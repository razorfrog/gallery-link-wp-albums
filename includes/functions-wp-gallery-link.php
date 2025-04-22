
<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Helper function to access the main plugin instance
 */
function wp_gallery_link() {
    return WP_Gallery_Link::get_instance();
}
