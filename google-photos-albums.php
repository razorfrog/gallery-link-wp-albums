
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

// Create a function that initializes the plugin admin interface
function wpgl_init_admin() {
    if (is_admin()) {
        // Initialize admin functionality
        $wp_gallery_link_admin = new WP_Gallery_Link_Admin();
        
        // Add action to enable demo mode via URL parameter
        add_action('admin_init', 'wpgl_check_demo_mode');
    }
}

// Function to check if demo mode is enabled via URL
function wpgl_check_demo_mode() {
    if (isset($_GET['demo']) && $_GET['demo'] === 'true' && 
        isset($_GET['page']) && $_GET['page'] === 'wp-gallery-link-import') {
        // Do nothing here, we'll handle this in the admin.js script
    }
}

// Initialize admin functionality
wpgl_init_admin();

// Initialize shortcode functionality
$wp_gallery_link_shortcode = new WP_Gallery_Link_Shortcode();

// Add action to prevent template not found errors in AJAX responses
add_action('wp_ajax_wpgl_fetch_albums', 'wpgl_ajax_fetch_albums_wrapper');
function wpgl_ajax_fetch_albums_wrapper() {
    $main = wp_gallery_link();
    if (method_exists($main, 'ajax_fetch_albums')) {
        $main->ajax_fetch_albums();
    } else {
        wp_send_json_error(array('message' => 'Method ajax_fetch_albums not found in main plugin class'));
    }
}

// Add action to handle album import
add_action('wp_ajax_wpgl_import_album', 'wpgl_ajax_import_album_wrapper');
function wpgl_ajax_import_album_wrapper() {
    $main = wp_gallery_link();
    if (method_exists($main, 'ajax_import_album')) {
        $main->ajax_import_album();
    } else {
        wp_send_json_error(array('message' => 'Method ajax_import_album not found in main plugin class'));
    }
}
