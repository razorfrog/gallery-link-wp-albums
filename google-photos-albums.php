
<?php
/**
 * Plugin Name: Google Photos Albums
 * Plugin URI: https://example.com/google-photos-albums
 * Description: Connect your WordPress site with Google Photos albums, import album details, and organize them with categories.
 * Version: 1.0.7
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wp-gallery-link
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants with an auto-incrementing version
$current_version = '1.0.7';
$build_number = time(); // Use timestamp to ensure unique version

define('WP_GALLERY_LINK_VERSION', $current_version . '.' . $build_number);
define('WP_GALLERY_LINK_PATH', plugin_dir_path(__FILE__));
define('WP_GALLERY_LINK_URL', plugin_dir_url(__FILE__));

// Enable debug mode. To disable, comment this line or set to false.
define('WP_GALLERY_LINK_DEBUG', true);

// Include the main plugin file
require_once WP_GALLERY_LINK_PATH . 'wp-gallery-link.php';

/**
 * Initialize the plugin - this is the main plugin bootstrap function
 * but is renamed to avoid conflicts
 */
function wpgl_initialize() {
    return WP_Gallery_Link::get_instance();
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'wpgl_activate');
register_deactivation_hook(__FILE__, 'wpgl_deactivate');

/**
 * Activation function
 */
function wpgl_activate() {
    // Create custom post types
    require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-cpt.php';
    $cpt = new WP_Gallery_Link_CPT();
    $cpt->register_post_type();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Set initial version in options
    update_option('wp_gallery_link_version', WP_GALLERY_LINK_VERSION);
    
    // Log activation with version
    if (WP_GALLERY_LINK_DEBUG) {
        error_log('WP Gallery Link: Plugin activated version ' . WP_GALLERY_LINK_VERSION);
    }
}

/**
 * Deactivation function
 */
function wpgl_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    if (WP_GALLERY_LINK_DEBUG) {
        error_log('WP Gallery Link: Plugin deactivated');
    }
}

/**
 * Register plugin scripts and styles
 */
function wpgl_register_scripts() {
    // Register admin styles
    wp_register_style('wpgl-admin', WP_GALLERY_LINK_URL . 'assets/css/admin.css', array(), WP_GALLERY_LINK_VERSION);
    
    // Register admin scripts
    wp_register_script('wpgl-admin', WP_GALLERY_LINK_URL . 'assets/js/admin.js', array('jquery'), WP_GALLERY_LINK_VERSION, true);
}
add_action('admin_enqueue_scripts', 'wpgl_register_scripts');

// Make sure admin class is initialized
add_action('plugins_loaded', function() {
    require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-admin.php';
    new WP_Gallery_Link_Admin();
});

// Initialize shortcode functionality
add_action('plugins_loaded', function() {
    require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-shortcode.php';
    new WP_Gallery_Link_Shortcode();
});

