
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

// Include the necessary files - make sure the classes are loaded before the main plugin file
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-cpt.php';
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-admin.php'; // Include admin class
require_once WP_GALLERY_LINK_PATH . 'wp-gallery-link.php';

// Initialize admin functionality
if (is_admin()) {
    $wp_gallery_link_admin = new WP_Gallery_Link_Admin();
}
