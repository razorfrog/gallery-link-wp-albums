
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

// Explicitly check for CPT class first to debug this specific issue
$cpt_file_paths = array(
    WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-cpt.php',
    WP_GALLERY_LINK_PATH . 'class-wp-gallery-link-cpt.php',
    WP_GALLERY_LINK_PATH . 'src/includes/class-wp-gallery-link-cpt.php'
);

$cpt_loaded = false;
foreach ($cpt_file_paths as $cpt_path) {
    if (file_exists($cpt_path)) {
        require_once $cpt_path;
        error_log('CPT class loaded from: ' . $cpt_path);
        $cpt_loaded = true;
        break;
    }
}

if (!$cpt_loaded) {
    error_log('CRITICAL ERROR: Could not find the CPT class at any of these locations: ' . implode(', ', $cpt_file_paths));
    
    // Add an admin notice if CPT class cannot be found
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Google Photos Albums: Critical error - Could not find CPT class. Check plugin file structure.</p></div>';
    });
}

// Include the main file first to prevent class not found errors
wpgl_include_file('wp-gallery-link.php', $include_paths);

// Include the admin and shortcode class files
wpgl_include_file('class-wp-gallery-link-admin.php', $include_paths);
wpgl_include_file('class-wp-gallery-link-shortcode.php', $include_paths);

// Create a function that initializes the plugin admin interface
function wpgl_init_admin() {
    if (is_admin()) {
        // Initialize admin functionality if the class exists
        if (class_exists('WP_Gallery_Link_Admin')) {
            $wp_gallery_link_admin = new WP_Gallery_Link_Admin();
            error_log('Admin class initialized successfully');
        } else {
            error_log('CRITICAL ERROR: Admin class not found');
        }
        
        // Add action to enable demo mode via URL parameter
        add_action('admin_init', 'wpgl_check_demo_mode');
    }
}

// Function to check if demo mode is enabled via URL
function wpgl_check_demo_mode() {
    if (isset($_GET['demo']) && $_GET['demo'] === 'true' && 
        isset($_GET['page']) && $_GET['page'] === 'wp-gallery-link-import') {
        // Force demo mode to be true
        add_filter('wpgl_is_demo_mode', function() { return true; });
        error_log('Demo mode enabled via URL parameter');
    }
}

// Initialize admin functionality
wpgl_init_admin();

// Initialize shortcode functionality if class exists
if (class_exists('WP_Gallery_Link_Shortcode')) {
    $wp_gallery_link_shortcode = new WP_Gallery_Link_Shortcode();
    error_log('Shortcode class initialized successfully');
} else {
    error_log('CRITICAL ERROR: Shortcode class not found');
}

// Add action to prevent template not found errors in AJAX responses
add_action('wp_ajax_wpgl_fetch_albums', 'wpgl_ajax_fetch_albums_wrapper');
function wpgl_ajax_fetch_albums_wrapper() {
    error_log('Handling AJAX request: wpgl_fetch_albums');
    
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
    error_log('Handling AJAX request: wpgl_import_album');
    
    $main = wp_gallery_link();
    if (method_exists($main, 'ajax_import_album')) {
        $main->ajax_import_album();
    } else {
        wp_send_json_error(array('message' => 'Method ajax_import_album not found in main plugin class'));
    }
}

// Log plugin initialization completion
if (WP_GALLERY_LINK_DEBUG) {
    error_log('Google Photos Albums plugin initialization completed');
}
