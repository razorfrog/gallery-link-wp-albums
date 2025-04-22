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
define('WP_GALLERY_LINK_DEBUG', true); // Enable debugging

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
     * Debug log array
     *
     * @var array
     */
    private $debug_log = array();

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
        // Initialize debug log
        $this->init_debug_log();
        
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
        
        // Add debug actions
        add_action('wp_ajax_wpgl_get_debug_log', array($this, 'ajax_get_debug_log'));
        add_action('wp_ajax_wpgl_clear_debug_log', array($this, 'ajax_clear_debug_log'));
        
        // Add a debug tab in admin
        add_action('admin_menu', array($this, 'add_debug_menu'));
        
        // Add our script localization
        add_action('admin_enqueue_scripts', array($this, 'localize_admin_scripts'), 99);
    }

    /**
     * Localize admin scripts with proper nonces and data
     */
    public function localize_admin_scripts($hook) {
        // Only on our plugin pages
        if (strpos($hook, 'wp-gallery-link') === false) {
            return;
        }
        
        // Create a debug nonce
        $debug_nonce = wp_create_nonce('wpgl_debug');
        
        // Add as a global variable for easier access
        wp_localize_script('wp-gallery-link-admin', 'wpglAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => $debug_nonce,
            'version' => WP_GALLERY_LINK_VERSION,
            'debugMode' => WP_GALLERY_LINK_DEBUG,
            'i18n' => array(
                'loading' => __('Loading...', 'wp-gallery-link'),
                'error' => __('Error:', 'wp-gallery-link'),
                'noAlbums' => __('No albums found in your Google Photos account.', 'wp-gallery-link'),
                'importing' => __('Importing...', 'wp-gallery-link'),
                'imported' => __('Imported', 'wp-gallery-link'),
                'import' => __('Import', 'wp-gallery-link'),
                'error_loading' => __('Error loading albums', 'wp-gallery-link'),
                'import_error' => __('Error importing album', 'wp-gallery-link'),
                'import_success' => __('Imported successfully', 'wp-gallery-link')
            )
        ));
        
        // Log that we've added the localization
        $this->log('Admin scripts localized with nonce: ' . substr($debug_nonce, 0, 5) . '...', 'debug');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('wp-gallery-link', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Add frontend debugging (if enabled)
        if (WP_DEBUG && current_user_can('manage_options')) {
            add_action('wp_footer', array($this, 'add_frontend_debug'));
        }
        
        // Add debugging to enqueued scripts if needed
        if (WP_GALLERY_LINK_DEBUG) {
            add_filter('script_loader_tag', array($this, 'add_debug_attributes'), 10, 2);
        }
        
        $this->log('Plugin initialized', 'info');
        
        // Initialize AJAX handlers
        add_action('wp_ajax_wpgl_fetch_albums', array($this->google_api, 'ajax_fetch_albums'));
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
        add_action('wp_ajax_wpgl_refresh_token', array($this->google_api, 'ajax_refresh_token'));
        add_action('wp_ajax_wpgl_test_api', array($this->google_api, 'ajax_test_api'));
    }
    
    /**
     * Initialize debug log
     */
    private function init_debug_log() {
        // Get stored log or create new one
        $this->debug_log = get_option('wpgl_debug_log', array());
        
        // Cap log size to prevent bloat
        if (count($this->debug_log) > 500) {
            $this->debug_log = array_slice($this->debug_log, -500);
            update_option('wpgl_debug_log', $this->debug_log);
        }
    }
    
    /**
     * Log a message for debugging
     * 
     * @param string $message The message to log
     * @param string $level The log level (debug, info, warning, error)
     * @param mixed $context Additional context data
     */
    public function log($message, $level = 'debug', $context = null) {
        if (!WP_GALLERY_LINK_DEBUG && $level == 'debug') {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'level' => $level,
            'context' => $context ? wp_json_encode($context) : null
        );
        
        // Add to internal log
        $this->debug_log[] = $log_entry;
        update_option('wpgl_debug_log', $this->debug_log);
        
        // Also log to WordPress error log for critical issues
        if (in_array($level, array('error', 'warning')) || WP_DEBUG) {
            error_log(sprintf(
                '[WP Gallery Link] [%s] %s %s',
                $level,
                $message,
                $context ? ' Context: ' . wp_json_encode($context) : ''
            ));
        }
    }
    
    /**
     * Get the debug log
     *
     * @return array The debug log
     */
    public function get_debug_log() {
        return $this->debug_log;
    }
    
    /**
     * Clear debug log
     */
    public function clear_debug_log() {
        $this->debug_log = array();
        update_option('wpgl_debug_log', array());
        $this->log('Debug log cleared', 'info');
    }
    
    /**
     * Add debug attributes to scripts
     *
     * @param string $tag Script HTML tag
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public function add_debug_attributes($tag, $handle) {
        if (strpos($handle, 'wp-gallery-link') !== false) {
            $tag = str_replace(' src', ' data-debug="true" src', $tag);
        }
        return $tag;
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Register CPT on activation so rewrite rules can be flushed
        $this->cpt->register_post_type();
        flush_rewrite_rules();
        
        // Log activation
        $this->log('Plugin activated', 'info');
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
        
        // Log deactivation
        $this->log('Plugin deactivated', 'info');
    }
    
    /**
     * Add frontend debugging
     */
    public function add_frontend_debug() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add console debugging
        ?>
        <script>
        console.log('WP Gallery Link: Debug information');
        console.log('Plugin URL: <?php echo WP_GALLERY_LINK_URL; ?>');
        console.log('Version: <?php echo WP_GALLERY_LINK_VERSION; ?>');
        console.log('API Authorized: <?php echo $this->google_api->is_authorized() ? 'Yes' : 'No'; ?>');
        console.log('Debug mode: <?php echo WP_GALLERY_LINK_DEBUG ? 'Enabled' : 'Disabled'; ?>');
        
        // Create a debug object for easier console access
        window.wpGalleryLinkDebug = {
            getLog: function() {
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wpgl_get_debug_log',
                    nonce: '<?php echo wp_create_nonce('wpgl_debug'); ?>'
                }, function(response) {
                    console.table(response.data.log);
                });
            },
            refreshAuth: function() {
                console.log('Refreshing Google API authorization...');
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wpgl_refresh_token',
                    nonce: '<?php echo wp_create_nonce('wpgl_debug'); ?>'
                }, function(response) {
                    console.log('Token refresh response:', response);
                });
            },
            testApi: function() {
                console.log('Testing API connection...');
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'wpgl_test_api',
                    nonce: '<?php echo wp_create_nonce('wpgl_debug'); ?>'
                }, function(response) {
                    console.log('API test response:', response);
                });
            }
        };
        
        console.log('Type wpGalleryLinkDebug.getLog() to view the debug log in console');
        console.log('Type wpGalleryLinkDebug.refreshAuth() to refresh the auth token');
        console.log('Type wpGalleryLinkDebug.testApi() to test the API connection');
        </script>
        <?php
    }
    
    /**
     * Add debug menu
     */
    public function add_debug_menu() {
        if (WP_GALLERY_LINK_DEBUG && current_user_can('manage_options')) {
            add_submenu_page(
                'wp-gallery-link',
                __('Debug', 'wp-gallery-link'),
                __('Debug', 'wp-gallery-link'),
                'manage_options',
                'wp-gallery-link-debug',
                array($this, 'render_debug_page')
            );
        }
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $api = $this->google_api;
        // Create a new nonce for this page
        $debug_nonce = wp_create_nonce('wpgl_debug');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Debug mode is enabled.', 'wp-gallery-link'); ?></strong> <?php _e('This page shows technical information about WP Gallery Link for troubleshooting purposes.', 'wp-gallery-link'); ?></p>
            </div>
            
            <div class="metabox-holder">
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('API Status', 'wp-gallery-link'); ?></span></h2>
                    <div class="inside">
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <th><?php _e('API Connected', 'wp-gallery-link'); ?></th>
                                    <td>
                                        <?php if ($api->is_connected()): ?>
                                            <span style="color: green;"><?php _e('Yes', 'wp-gallery-link'); ?></span>
                                        <?php else: ?>
                                            <span style="color: red;"><?php _e('No', 'wp-gallery-link'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('API Authorized', 'wp-gallery-link'); ?></th>
                                    <td>
                                        <?php if ($api->is_authorized()): ?>
                                            <span style="color: green;"><?php _e('Yes', 'wp-gallery-link'); ?></span>
                                        <?php else: ?>
                                            <span style="color: red;"><?php _e('No', 'wp-gallery-link'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Token Expires', 'wp-gallery-link'); ?></th>
                                    <td>
                                        <?php 
                                        $expires = get_option('wpgl_google_token_expires', 0);
                                        if ($expires > time()) {
                                            echo date('Y-m-d H:i:s', $expires);
                                            echo ' (' . human_time_diff(time(), $expires) . ' remaining)';
                                        } else {
                                            _e('Expired or not set', 'wp-gallery-link');
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Actions', 'wp-gallery-link'); ?></th>
                                    <td>
                                        <a href="<?php echo esc_url($api->get_auth_url()); ?>" class="button"><?php _e('Re-authorize', 'wp-gallery-link'); ?></a>
                                        <button id="wpgl-refresh-token" class="button" data-nonce="<?php echo esc_attr($debug_nonce); ?>"><?php _e('Refresh Token', 'wp-gallery-link'); ?></button>
                                        <button id="wpgl-test-api" class="button" data-nonce="<?php echo esc_attr($debug_nonce); ?>"><?php _e('Test API Connection', 'wp-gallery-link'); ?></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Debug Log', 'wp-gallery-link'); ?></span></h2>
                    <div class="inside">
                        <p>
                            <button id="wpgl-refresh-log" class="button"><?php _e('Refresh Log', 'wp-gallery-link'); ?></button>
                            <button id="wpgl-clear-log" class="button"><?php _e('Clear Log', 'wp-gallery-link'); ?></button>
                            <select id="wpgl-log-level">
                                <option value="all"><?php _e('All Levels', 'wp-gallery-link'); ?></option>
                                <option value="info"><?php _e('Info & Above', 'wp-gallery-link'); ?></option>
                                <option value="warning"><?php _e('Warnings & Errors', 'wp-gallery-link'); ?></option>
                                <option value="error"><?php _e('Errors Only', 'wp-gallery-link'); ?></option>
                            </select>
                            <input type="text" id="wpgl-log-search" placeholder="<?php esc_attr_e('Search logs...', 'wp-gallery-link'); ?>">
                        </p>
                        
                        <div id="wpgl-log-viewer" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 10px; background-color: #f8f9fa;">
                            <table class="widefat" id="wpgl-log-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Time', 'wp-gallery-link'); ?></th>
                                        <th><?php _e('Level', 'wp-gallery-link'); ?></th>
                                        <th><?php _e('Message', 'wp-gallery-link'); ?></th>
                                        <th><?php _e('Context', 'wp-gallery-link'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->get_debug_log() as $entry): ?>
                                    <tr class="log-level-<?php echo esc_attr($entry['level']); ?>">
                                        <td><?php echo esc_html($entry['timestamp']); ?></td>
                                        <td><?php echo esc_html(strtoupper($entry['level'])); ?></td>
                                        <td><?php echo esc_html($entry['message']); ?></td>
                                        <td><?php echo !empty($entry['context']) ? esc_html($entry['context']) : ''; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('System Information', 'wp-gallery-link'); ?></span></h2>
                    <div class="inside">
                        <table class="widefat">
                            <tbody>
                                <tr>
                                    <th><?php _e('WordPress Version', 'wp-gallery-link'); ?></th>
                                    <td><?php echo get_bloginfo('version'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('PHP Version', 'wp-gallery-link'); ?></th>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Plugin Version', 'wp-gallery-link'); ?></th>
                                    <td><?php echo WP_GALLERY_LINK_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Debug Mode', 'wp-gallery-link'); ?></th>
                                    <td><?php echo WP_DEBUG ? __('Enabled', 'wp-gallery-link') : __('Disabled', 'wp-gallery-link'); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _e('Plugin Path', 'wp-gallery-link'); ?></th>
                                    <td><?php echo WP_GALLERY_LINK_PATH; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            var debugNonce = '<?php echo esc_js($debug_nonce); ?>';
            
            $('#wpgl-refresh-log').on('click', function() {
                $.post(ajaxUrl, {
                    action: 'wpgl_get_debug_log',
                    nonce: debugNonce
                }, function(response) {
                    if (response.success) {
                        refreshLogTable(response.data.log);
                    } else {
                        alert('<?php _e('Failed to refresh log', 'wp-gallery-link'); ?>');
                    }
                });
            });
            
            $('#wpgl-clear-log').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to clear the log?', 'wp-gallery-link'); ?>')) {
                    $.post(ajaxUrl, {
                        action: 'wpgl_clear_debug_log',
                        nonce: debugNonce
                    }, function(response) {
                        if (response.success) {
                            $('#wpgl-log-table tbody').empty();
                            alert('<?php _e('Debug log cleared', 'wp-gallery-link'); ?>');
                        } else {
                            alert('<?php _e('Failed to clear log', 'wp-gallery-link'); ?>');
                        }
                    });
                }
            });
            
            $('#wpgl-refresh-token').on('click', function() {
                $(this).prop('disabled', true).text('<?php _e('Refreshing...', 'wp-gallery-link'); ?>');
                
                $.post(ajaxUrl, {
                    action: 'wpgl_refresh_token',
                    nonce: debugNonce
                }, function(response) {
                    $('#wpgl-refresh-token').prop('disabled', false).text('<?php _e('Refresh Token', 'wp-gallery-link'); ?>');
                    
                    if (response.success) {
                        alert('<?php _e('Token refreshed successfully', 'wp-gallery-link'); ?>');
                        location.reload();
                    } else {
                        alert('<?php _e('Failed to refresh token', 'wp-gallery-link'); ?>: ' + (response.data ? response.data.message : ''));
                    }
                });
            });
            
            $('#wpgl-test-api').on('click', function() {
                $(this).prop('disabled', true).text('<?php _e('Testing...', 'wp-gallery-link'); ?>');
                
                $.post(ajaxUrl, {
                    action: 'wpgl_test_api',
                    nonce: debugNonce
                }, function(response) {
                    $('#wpgl-test-api').prop('disabled', false).text('<?php _e('Test API Connection', 'wp-gallery-link'); ?>');
                    
                    if (response.success) {
                        alert('<?php _e('API connection successful', 'wp-gallery-link'); ?>');
                    } else {
                        alert('<?php _e('API connection failed', 'wp-gallery-link'); ?>: ' + (response.data ? response.data.message : ''));
                    }
                }).fail(function(xhr, status, error) {
                    $('#wpgl-test-api').prop('disabled', false).text('<?php _e('Test API Connection', 'wp-gallery-link'); ?>');
                    alert('<?php _e('Request failed', 'wp-gallery-link'); ?>: ' + error);
                    console.error('AJAX error:', xhr.responseText);
                });
            });
            
            $('#wpgl-log-level, #wpgl-log-search').on('change keyup', filterLogs);
            
            function filterLogs() {
                var level = $('#wpgl-log-level').val();
                var search = $('#wpgl-log-search').val().toLowerCase();
                
                $('#wpgl-log-table tbody tr').each(function() {
                    var $row = $(this);
                    var rowLevel = $row.attr('class').replace('log-level-', '');
                    var rowText = $row.text().toLowerCase();
                    var showByLevel = level === 'all' || 
                        (level === 'info' && (rowLevel === 'info' || rowLevel === 'warning' || rowLevel === 'error')) ||
                        (level === 'warning' && (rowLevel === 'warning' || rowLevel === 'error')) ||
                        (level === 'error' && rowLevel === 'error');
                    
                    var showBySearch = search === '' || rowText.indexOf(search) > -1;
                    
                    if (showByLevel && showBySearch) {
                        $row.show();
                    } else {
                        $row.hide();
                    }
                });
            }
            
            function refreshLogTable(log) {
                var $tbody = $('#wpgl-log-table tbody');
                $tbody.empty();
                
                $.each(log, function(i, entry) {
                    var $row = $('<tr class="log-level-' + entry.level + '">');
                    $row.append($('<td>').text(entry.timestamp));
                    $row.append($('<td>').text(entry.level.toUpperCase()));
                    $row.append($('<td>').text(entry.message));
                    $row.append($('<td>').text(entry.context || ''));
                    
                    $tbody.append($row);
                });
                
                filterLogs();
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for getting debug log
     */
    public function ajax_get_debug_log() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_debug', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        // Get log
        $log = $this->get_debug_log();
        
        wp_send_json_success(array('log' => $log));
    }
    
    /**
     * AJAX handler for clearing debug log
     */
    public function ajax_clear_debug_log() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_debug', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        // Clear log
        $this->clear_debug_log();
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for album import
     */
    public function ajax_import_album() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpgl_nonce')) {
            error_log('WP Gallery Link: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
            return;
        }
        
        // Check for album ID
        if (empty($_POST['album_id'])) {
            error_log('WP Gallery Link: No album ID provided');
            wp_send_json_error(array('message' => __('No album ID provided', 'wp-gallery-link')));
            return;
        }
        
        $album_id = sanitize_text_field($_POST['album_id']);
        error_log('WP Gallery Link: Attempting to import album ID ' . $album_id);
        
        // Get album details from Google Photos
        $album_data = $this->google_api->get_album($album_id);
        
        if (is_wp_error($album_data)) {
            error_log('WP Gallery Link: Error retrieving album data - ' . $album_data->get_error_message());
            wp_send_json_error(array('message' => $album_data->get_error_message()));
            return;
        }
        
        // Debug album data
        error_log('WP Gallery Link: Album data retrieved - Title: ' . $album_data['title']);
        
        // Create album post - Ensure we're using the CPT instance
        $post_id = $this->cpt->create_album_from_google($album_data);
        
        if (is_wp_error($post_id)) {
            error_log('WP Gallery Link: Error creating album post - ' . $post_id->get_error_message());
            
            if ($post_id->get_error_code() === 'album_exists') {
                $error_data = $post_id->get_error_data();
                $edit_url = get_edit_post_link($error_data['post_id'], '');
                error_log('WP Gallery Link: Album already exists - Post ID ' . $error_data['post_id']);
                
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
        
        // Log successful album import
        error_log('WP Gallery Link: Successfully imported album - Post ID ' . $post_id . ' with post_type: gphoto_album');
        
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
