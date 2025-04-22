
<?php
/**
 * Plugin Name: WP Gallery Link
 * Plugin URI: https://example.com/wp-gallery-link
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

// Include required files
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-cpt.php';
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-google-api.php';
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-admin.php';
require_once WP_GALLERY_LINK_PATH . 'includes/class-wp-gallery-link-shortcode.php';

/**
 * Main plugin class
 */
class WP_Gallery_Link {
    /**
     * Instance of this class
     *
     * @var object
     */
    private static $instance;

    /**
     * CPT instance
     *
     * @var WP_Gallery_Link_CPT
     */
    public $cpt;

    /**
     * Google API instance
     *
     * @var WP_Gallery_Link_Google_API
     */
    public $google_api;

    /**
     * Admin instance
     *
     * @var WP_Gallery_Link_Admin
     */
    public $admin;

    /**
     * Shortcode instance
     *
     * @var WP_Gallery_Link_Shortcode
     */
    public $shortcode;

    /**
     * Get an instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize components
        $this->cpt = new WP_Gallery_Link_CPT();
        $this->google_api = new WP_Gallery_Link_Google_API();
        $this->admin = new WP_Gallery_Link_Admin();
        $this->shortcode = new WP_Gallery_Link_Shortcode();
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register AJAX handlers
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('wp-gallery-link', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Register CPT on activation so rewrite rules can be flushed
        $this->cpt->register_post_type();
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Handle album import via AJAX
     */
    public function ajax_import_album() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpgl_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }
        
        // Check for album ID
        if (empty($_POST['album_id'])) {
            wp_send_json_error(array('message' => __('No album ID provided', 'wp-gallery-link')));
            return;
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        
        // Get album details from Google Photos
        $album_data = $this->google_api->get_album($album_id);
        
        if (is_wp_error($album_data)) {
            wp_send_json_error(array('message' => $album_data->get_error_message()));
            return;
        }
        
        // Create album post
        $post_id = $this->cpt->create_album_from_google($album_data);
        
        if (is_wp_error($post_id)) {
            if ($post_id->get_error_code() === 'album_exists') {
                $error_data = $post_id->get_error_data();
                $edit_url = get_edit_post_link($error_data['post_id'], '');
                wp_send_json_success(array(
                    'message' => __('Album already exists', 'wp-gallery-link'),
                    'post_id' => $error_data['post_id'],
                    'edit_url' => $edit_url,
                    'view_url' => get_permalink($error_data['post_id']),
                ));
            } else {
                wp_send_json_error(array('message' => $post_id->get_error_message()));
            }
            return;
        }
        
        // Success response
        wp_send_json_success(array(
            'message' => __('Album imported successfully', 'wp-gallery-link'),
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, ''),
            'view_url' => get_permalink($post_id),
        ));
    }
}

// Initialize the plugin
function wp_gallery_link() {
    return WP_Gallery_Link::get_instance();
}

// Start the plugin
wp_gallery_link();
