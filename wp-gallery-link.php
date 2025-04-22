
<?php
/**
 * Main plugin file for Google Photos Albums
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Make sure constants are defined; you may already set these in the main plugin file elsewhere
if (!defined('WP_GALLERY_LINK_DEBUG')) {
    define('WP_GALLERY_LINK_DEBUG', true);
}

// Define plugin paths if not already defined by loader
if (!defined('WP_GALLERY_LINK_PATH')) {
    define('WP_GALLERY_LINK_PATH', plugin_dir_path(__FILE__));
}

if (!defined('WP_GALLERY_LINK_URL')) {
    define('WP_GALLERY_LINK_URL', plugin_dir_url(__FILE__));
}

if (!defined('WP_GALLERY_LINK_VERSION')) {
    define('WP_GALLERY_LINK_VERSION', '1.0.0');
}

// Load main plugin class and helpers
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link.php';
require_once WP_GALLERY_LINK_PATH . 'includes/functions-wp-gallery-link.php';

// Initialize plugin
add_action('plugins_loaded', 'wp_gallery_link');
