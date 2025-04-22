
<?php
/**
 * Plugin Name: Google Photos Albums
 * Plugin URI: https://example.com/google-photos-albums
 * Description: Connect your WordPress site with Google Photos albums, import album details, and organize them with categories.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wp-gallery-link
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WP_GALLERY_LINK_VERSION', '1.0.0');
define('WP_GALLERY_LINK_PATH', plugin_dir_path(__FILE__));
define('WP_GALLERY_LINK_URL', plugin_dir_url(__FILE__));
define('WP_GALLERY_LINK_DEBUG', true);

// Log plugin initialization for debugging
if (WP_GALLERY_LINK_DEBUG) {
    error_log('Google Photos Albums plugin initialized with path: ' . WP_GALLERY_LINK_PATH);
}

// Include the necessary files - search in multiple locations to support different folder structures
$include_paths = array(
    WP_GALLERY_LINK_PATH . 'includes/',
    WP_GALLERY_LINK_PATH . 'src/includes/',
    WP_GALLERY_LINK_PATH
);

// Function to include file from first available path
function wpgl_include_file($filename, $paths) {
    foreach ($paths as $path) {
        $full_path = $path . $filename;
        if (file_exists($full_path)) {
            require_once $full_path;
            if (WP_GALLERY_LINK_DEBUG) {
                error_log('Successfully included: ' . $full_path);
            }
            return true;
        }
    }
    error_log('Critical error: ' . $filename . ' not found in any include path');
    return false;
}

// Include the necessary class files
wpgl_include_file('class-wp-gallery-link-cpt.php', $include_paths);
wpgl_include_file('class-wp-gallery-link-admin.php', $include_paths);
wpgl_include_file('class-wp-gallery-link-shortcode.php', $include_paths);

// Load the main plugin file
wpgl_include_file('wp-gallery-link.php', $include_paths);

// Initialize admin functionality
if (is_admin()) {
    $wp_gallery_link_admin = new WP_Gallery_Link_Admin();
}

// Initialize shortcode functionality
$wp_gallery_link_shortcode = new WP_Gallery_Link_Shortcode();
