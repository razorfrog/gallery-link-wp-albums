
<?php
/**
 * Main plugin file for Google Photos Albums
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('WP_GALLERY_LINK_VERSION', '1.0.10');

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

// Load main plugin class and helpers
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link.php';
require_once WP_GALLERY_LINK_PATH . 'includes/functions-wp-gallery-link.php';

// Initialize plugin - only after plugins_loaded
add_action('plugins_loaded', 'wpgl_initialize');

/**
 * Add action to clear browser cache on version update
 * This helps ensure JavaScript and CSS files are refreshed
 */
function wp_gallery_link_clear_cache() {
    $current_version = get_option('wp_gallery_link_version', '0');
    if (version_compare($current_version, WP_GALLERY_LINK_VERSION, '<')) {
        update_option('wp_gallery_link_version', WP_GALLERY_LINK_VERSION);
    }
}
add_action('admin_init', 'wp_gallery_link_clear_cache');

/**
 * Add version number to scripts and styles as query string
 * This forces browsers to load fresh versions
 */
function wp_gallery_link_script_version($src) {
    if (strpos($src, 'wp-gallery-link') !== false) {
        $src = add_query_arg('ver', WP_GALLERY_LINK_VERSION . '.' . time(), $src);
    }
    return $src;
}
add_filter('script_loader_src', 'wp_gallery_link_script_version');
add_filter('style_loader_src', 'wp_gallery_link_script_version');

