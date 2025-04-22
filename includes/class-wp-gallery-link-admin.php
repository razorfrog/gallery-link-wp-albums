<?php
/**
 * Admin settings and functionality
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class WP_Gallery_Link_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link Admin: Initialized');
        }
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP Gallery Link', 'wp-gallery-link'),
            __('WP Gallery Link', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link',
            array($this, 'render_settings_page'),
            'dashicons-format-gallery',
            30
        );
        
        add_submenu_page(
            'wp-gallery-link',
            __('Settings', 'wp-gallery-link'),
            __('Settings', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'wp-gallery-link',
            __('Import Albums', 'wp-gallery-link'),
            __('Import Albums', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link-import',
            array($this, 'render_import_page')
        );
        
        // Add debug page if debug mode is enabled
        if (defined('WP_GALLERY_LINK_DEBUG') && WP_GALLERY_LINK_DEBUG) {
            add_submenu_page(
                'wp-gallery-link',
                __('Debug', 'wp-gallery-link'),
                __('Debug', 'wp-gallery-link'),
                'manage_options',
                'wp-gallery-link-debug',
                array($this, 'render_debug_page')
            );
        }
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link Admin: Menu items added');
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_wp-gallery-link', 'wp-gallery-link_page_wp-gallery-link-import'))) {
            return;
        }
        
        wp_enqueue_style(
            'wp-gallery-link-admin',
            WP_GALLERY_LINK_URL . 'assets/css/admin.css',
            array(),
            WP_GALLERY_LINK_VERSION
        );
        
        wp_enqueue_script(
            'wp-gallery-link-admin',
            WP_GALLERY_LINK_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_GALLERY_LINK_VERSION,
            true
        );
        
        wp_localize_script('wp-gallery-link-admin', 'wpglAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpgl_nonce'),
            'debugMode' => WP_GALLERY_LINK_DEBUG,
            'loadAllAlbums' => true,
            'i18n' => array(
                'importing' => __('Importing...', 'wp-gallery-link'),
                'imported' => __('Imported', 'wp-gallery-link'),
                'import' => __('Import', 'wp-gallery-link'),
                'import_success' => __('Album imported successfully!', 'wp-gallery-link'),
                'import_error' => __('Error importing album:', 'wp-gallery-link'),
                'loading_albums' => __('Loading albums...', 'wp-gallery-link'),
                'load_more' => __('Load more', 'wp-gallery-link'),
                'no_more_albums' => __('No more albums to load.', 'wp-gallery-link'),
                'error_loading' => __('Error loading albums:', 'wp-gallery-link')
            )
        ));
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link Admin: Scripts and styles enqueued for ' . $hook);
        }
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wpgl_settings', 'wpgl_google_client_id');
        register_setting('wpgl_settings', 'wpgl_google_client_secret');
        
        add_settings_section(
            'wpgl_settings_section',
            __('Google API Settings', 'wp-gallery-link'),
            array($this, 'settings_section_callback'),
            'wpgl_settings'
        );
        
        add_settings_field(
            'wpgl_google_client_id',
            __('Client ID', 'wp-gallery-link'),
            array($this, 'client_id_callback'),
            'wpgl_settings',
            'wpgl_settings_section'
        );
        
        add_settings_field(
            'wpgl_google_client_secret',
            __('Client Secret', 'wp-gallery-link'),
            array($this, 'client_secret_callback'),
            'wpgl_settings',
            'wpgl_settings_section'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . __('To use WP Gallery Link, you need to create a project in the Google Cloud Console and enable the Google Photos Library API.', 'wp-gallery-link') . '</p>';
        echo '<ol>';
        echo '<li>' . __('Go to the <a href="https://console.cloud.google.com/apis/dashboard" target="_blank">Google Cloud Console</a>.', 'wp-gallery-link') . '</li>';
        echo '<li>' . __('Create a new project or select an existing one.', 'wp-gallery-link') . '</li>';
        echo '<li>' . __('Enable the "Google Photos Library API".', 'wp-gallery-link') . '</li>';
        echo '<li>' . __('Go to "Credentials" and create an OAuth client ID.', 'wp-gallery-link') . '</li>';
        echo '<li>' . __('Set the application type to "Web application".', 'wp-gallery-link') . '</li>';
        echo '<li>' . __('Add the following authorized redirect URI:', 'wp-gallery-link') . '<br><code>' . admin_url('admin-ajax.php') . '?action=wpgl_auth_google</code></li>';
        echo '<li>' . __('Copy the Client ID and Client Secret and paste them below.', 'wp-gallery-link') . '</li>';
        echo '</ol>';
    }
    
    /**
     * Client ID field callback
     */
    public function client_id_callback() {
        $client_id = get_option('wpgl_google_client_id', '');
        echo '<input type="text" id="wpgl_google_client_id" name="wpgl_google_client_id" value="' . esc_attr($client_id) . '" class="regular-text">';
    }
    
    /**
     * Client Secret field callback
     */
    public function client_secret_callback() {
        $client_secret = get_option('wpgl_google_client_secret', '');
        echo '<input type="password" id="wpgl_google_client_secret" name="wpgl_google_client_secret" value="' . esc_attr($client_secret) . '" class="regular-text">';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get google_api safely - check if the property exists
        $main = wp_gallery_link();
        $is_connected = false;
        $auth_url = '#';
        
        if (isset($main->google_api)) {
            if (method_exists($main->google_api, 'is_connected')) {
                $is_connected = $main->google_api->is_connected();
            }
            if (method_exists($main->google_api, 'get_auth_url')) {
                $auth_url = $main->google_api->get_auth_url();
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (isset($_GET['error']) && $_GET['error'] === 'auth'): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Authentication failed. Please try again.', 'wp-gallery-link'); ?></p>
                </div>
            <?php elseif (isset($_GET['error']) && $_GET['error'] === 'token'): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php _e('Failed to retrieve access token. Please check your client ID and secret.', 'wp-gallery-link'); ?></p>
                </div>
            <?php elseif (isset($_GET['connected']) && $_GET['connected'] === '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Successfully connected to Google Photos!', 'wp-gallery-link'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="wpgl-container">
                <div class="wpgl-section">
                    <h2><?php _e('API Settings', 'wp-gallery-link'); ?></h2>
                    
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wpgl_settings');
                        do_settings_sections('wpgl_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="wpgl-section">
                    <h2><?php _e('Google Photos Connection', 'wp-gallery-link'); ?></h2>
                    
                    <?php if ($is_connected): ?>
                        <p class="wpgl-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Connected to Google Photos', 'wp-gallery-link'); ?>
                        </p>
                        
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-gallery-link-import')); ?>" class="button button-primary">
                                <?php _e('Import Albums', 'wp-gallery-link'); ?>
                            </a>
                            
                            <a href="<?php echo esc_url($auth_url); ?>" class="button">
                                <?php _e('Reconnect', 'wp-gallery-link'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p class="wpgl-not-connected">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php _e('Not connected to Google Photos', 'wp-gallery-link'); ?>
                        </p>
                        
                        <?php if (!empty($auth_url) && $auth_url !== '#'): ?>
                            <p>
                                <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary">
                                    <?php _e('Connect to Google Photos', 'wp-gallery-link'); ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p>
                                <?php _e('Please enter your Google API credentials first.', 'wp-gallery-link'); ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="wpgl-section">
                    <h2><?php _e('Shortcode Usage', 'wp-gallery-link'); ?></h2>
                    
                    <p><?php _e('Use the following shortcode to display albums:', 'wp-gallery-link'); ?></p>
                    
                    <pre><code>[wp_gallery_link]</code></pre>
                    
                    <p><?php _e('Shortcode parameters:', 'wp-gallery-link'); ?></p>
                    
                    <ul>
                        <li><code>category</code>: <?php _e('Display albums from a specific category (use the category slug).', 'wp-gallery-link'); ?></li>
                        <li><code>orderby</code>: <?php _e('Order albums by "title", "date", "custom" (default), or "random".', 'wp-gallery-link'); ?></li>
                        <li><code>order</code>: <?php _e('Sort order, "asc" (default) or "desc".', 'wp-gallery-link'); ?></li>
                        <li><code>limit</code>: <?php _e('Number of albums to display (default: -1, all albums).', 'wp-gallery-link'); ?></li>
                        <li><code>columns</code>: <?php _e('Number of columns (default: 3).', 'wp-gallery-link'); ?></li>
                    </ul>
                    
                    <p><?php _e('Example:', 'wp-gallery-link'); ?></p>
                    
                    <pre><code>[wp_gallery_link category="vacation" orderby="date" order="desc" limit="6" columns="4"]</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render import page
     */
    public function render_import_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get google_api safely
        $main = wp_gallery_link();
        $is_connected = false;
        
        if (isset($main->google_api) && method_exists($main->google_api, 'is_connected')) {
            $is_connected = $main->google_api->is_connected();
        }
        
        // Let's not redirect away just yet for debugging purposes
        if (!$is_connected && !isset($_GET['demo'])) {
            // For now, just show a notice instead of redirecting
            // wp_redirect(admin_url('admin.php?page=wp-gallery-link'));
            // exit;
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Import Albums from Google Photos', 'wp-gallery-link'); ?></h1>
            
            <?php if (!$is_connected && !isset($_GET['demo'])): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('You are not connected to Google Photos. Some features may not work properly.', 'wp-gallery-link'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-gallery-link')); ?>" class="button button-small">
                            <?php _e('Go to Settings', 'wp-gallery-link'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="wpgl-import-container">
                <div class="wpgl-import-header">
                    <p>
                        <?php _e('Select albums to import from your Google Photos account. You can then edit them, assign categories, and customize their display.', 'wp-gallery-link'); ?>
                    </p>
                    <div class="wpgl-button-group">
                        <button id="wpgl-start-loading" class="button button-primary wpgl-load-albums">
                            <?php _e('Start Loading Albums', 'wp-gallery-link'); ?>
                        </button>
                        <button id="wpgl-stop-loading" class="button" style="display:none;">
                            <?php _e('Stop Loading', 'wp-gallery-link'); ?>
                        </button>
                    </div>
                </div>

                <div class="wpgl-loading-container" style="display: none;">
                    <div class="wpgl-loading-status">
                        <span class="spinner is-active"></span>
                        <span class="wpgl-loading-text"><?php _e('Loading albums...', 'wp-gallery-link'); ?></span>
                    </div>
                    <div class="wpgl-progress">
                        <div class="wpgl-progress-bar">
                            <div class="wpgl-progress-value" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <div class="wpgl-loading-log-container" style="margin-top:20px; display: none;">
                    <h3><?php _e('Loading Log', 'wp-gallery-link'); ?></h3>
                    <div class="wpgl-loading-log" style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px; background:#f9f9f9;"></div>
                </div>

                <div class="wpgl-albums-log-container" style="margin-top:20px; display:none;">
                    <h3><?php _e('Albums Being Found', 'wp-gallery-link'); ?></h3>
                    <ul id="wpgl-albums-title-list" style="max-height:200px;overflow:auto;margin:0;padding-left:1em; border:1px solid #ddd; padding:10px; background:#f9f9f9;"></ul>
                </div>

                <div class="wpgl-albums-container" style="display: none; margin-top: 20px;">
                    <h2><?php _e('Available Albums', 'wp-gallery-link'); ?></h2>
                    <div class="wpgl-albums-grid"></div>
                </div>

                <div class="wpgl-demo-mode" style="margin-top: 30px;">
                    <p>
                        <strong><?php _e('Demo Mode:', 'wp-gallery-link'); ?></strong>
                        <?php _e('Not seeing any albums? Try demo mode to see how the plugin works.', 'wp-gallery-link'); ?>
                    </p>
                    <a href="?page=wp-gallery-link-import&demo=true" class="button"><?php _e('Load Demo Albums', 'wp-gallery-link'); ?></a>
                    
                    <?php if (isset($_GET['demo']) && $_GET['demo'] === 'true'): ?>
                        <p class="wpgl-demo-notice">
                            <em><?php _e('Demo mode is active. The albums shown are for demonstration purposes only.', 'wp-gallery-link'); ?></em>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <script type="text/html" id="tmpl-wpgl-album-item">
            <div class="wpgl-album-item" data-id="{{ data.id }}">
                <div class="wpgl-album-thumbnail">
                    <# if (data.coverPhotoBaseUrl) { #>
                        <img src="{{ data.coverPhotoBaseUrl }}=w200-h200" alt="{{ data.title }}">
                    <# } else { #>
                        <div class="wpgl-no-thumbnail"><?php _e('No Cover', 'wp-gallery-link'); ?></div>
                    <# } #>
                </div>
                
                <div class="wpgl-album-details">
                    <h3 class="wpgl-album-title">{{ data.title }}</h3>
                    
                    <div class="wpgl-album-meta">
                        <# if (data.mediaItemsCount) { #>
                            <span class="wpgl-album-count">
                                {{ data.mediaItemsCount }} <?php _e('items', 'wp-gallery-link'); ?>
                            </span>
                        <# } #>
                    </div>
                    
                    <div class="wpgl-album-actions">
                        <# if (data.imported) { #>
                            <a href="{{ data.editLink }}" class="button button-secondary">
                                <?php _e('Edit', 'wp-gallery-link'); ?>
                            </a>
                            <a href="{{ data.viewLink }}" class="button button-secondary" target="_blank">
                                <?php _e('View', 'wp-gallery-link'); ?>
                            </a>
                        <# } else { #>
                            <button class="button button-primary wpgl-import-album" data-id="{{ data.id }}">
                                <?php _e('Import', 'wp-gallery-link'); ?>
                            </button>
                        <# } #>
                    </div>
                </div>
            </div>
        </script>
        <?php
    }
    
    /**
     * Render debug page
     */
    public function render_debug_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Debug Information', 'wp-gallery-link'); ?></h1>
            
            <div class="wpgl-debug-container">
                <h2><?php _e('Plugin Configuration', 'wp-gallery-link'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Plugin Version', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo WP_GALLERY_LINK_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Debug Mode', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo WP_GALLERY_LINK_DEBUG ? 'Enabled' : 'Disabled'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Plugin Path', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo WP_GALLERY_LINK_PATH; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Plugin URL', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo WP_GALLERY_LINK_URL; ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php _e('WordPress Environment', 'wp-gallery-link'); ?></h2>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('WordPress Version', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Active Theme', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo wp_get_theme()->get('Name'); ?> (<?php echo wp_get_theme()->get('Version'); ?>)</td>
                        </tr>
                    </tbody>
                </table>
                
                <h2><?php _e('Post Type Status', 'wp-gallery-link'); ?></h2>
                <?php
                $album_args = array(
                    'post_type' => 'gphoto_album',
                    'posts_per_page' => -1,
                );
                $albums = get_posts($album_args);
                ?>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php _e('Album Post Type', 'wp-gallery-link'); ?></strong></td>
                            <td>gphoto_album</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Registered Albums', 'wp-gallery-link'); ?></strong></td>
                            <td><?php echo count($albums); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (count($albums) > 0): ?>
                <h2><?php _e('Albums in Database', 'wp-gallery-link'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Title', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Google Photos ID', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Status', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Date', 'wp-gallery-link'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($albums as $album): ?>
                        <tr>
                            <td><?php echo $album->ID; ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($album->ID); ?>"><?php echo $album->post_title; ?></a>
                            </td>
                            <td><?php echo get_post_meta($album->ID, 'wpgl_album_id', true); ?></td>
                            <td><?php echo $album->post_status; ?></td>
                            <td><?php echo get_the_date('', $album->ID); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <h2><?php _e('Check for Post Type Mismatches', 'wp-gallery-link'); ?></h2>
                <?php
                global $wpdb;
                $mismatched_albums = $wpdb->get_results(
                    "SELECT ID, post_title, post_type 
                     FROM {$wpdb->posts} 
                     WHERE post_type = 'wp_gallery_album'"
                );
                ?>
                <?php if (count($mismatched_albums) > 0): ?>
                <div class="notice notice-error">
                    <p>
                        <?php 
                        printf(
                            _n(
                                'Found %d album with incorrect post type (wp_gallery_album). These need to be fixed.',
                                'Found %d albums with incorrect post type (wp_gallery_album). These need to be fixed.',
                                count($mismatched_albums),
                                'wp-gallery-link'
                            ),
                            count($mismatched_albums)
                        );
                        ?>
                    </p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wpgl_fix_post_types" />
                        <?php wp_nonce_field('wpgl_fix_post_types', 'wpgl_nonce'); ?>
                        <p>
                            <button type="submit" class="button button-primary">
                                <?php _e('Fix Post Types', 'wp-gallery-link'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Title', 'wp-gallery-link'); ?></th>
                            <th><?php _e('Current Post Type', 'wp-gallery-link'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($mismatched_albums as $album): ?>
                        <tr>
                            <td><?php echo $album->ID; ?></td>
                            <td><?php echo $album->post_title; ?></td>
                            <td><?php echo $album->post_type; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="notice notice-success">
                    <p><?php _e('No post type mismatches found.', 'wp-gallery-link'); ?></p>
                </div>
                <?php endif; ?>
                
                <h2><?php _e('System Information', 'wp-gallery-link'); ?></h2>
                <textarea readonly="readonly" class="large-text code" rows="10"><?php
                    echo 'WordPress Version: ' . get_bloginfo('version') . "\n";
                    echo 'PHP Version: ' . phpversion() . "\n";
                    echo 'Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . "\n";
                    echo 'User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\n";
                    echo 'WP Memory Limit: ' . WP_MEMORY_LIMIT . "\n";
                    echo 'WP Debug Mode: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . "\n";
                    echo 'WP Debug Log: ' . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'Enabled' : 'Disabled') . "\n";
                    echo 'WP Debug Display: ' . (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? 'Enabled' : 'Disabled') . "\n";
                    echo 'PHP Post Max Size: ' . ini_get('post_max_size') . "\n";
                    echo 'PHP Upload Max Size: ' . ini_get('upload_max_filesize') . "\n";
                    echo 'PHP Max Execution Time: ' . ini_get('max_execution_time') . "\n";
                    echo 'PHP Max Input Vars: ' . ini_get('max_input_vars') . "\n";
                ?></textarea>
            </div>
        </div>
        <?php
    }
}

// Add action handler for fixing post types
add_action('admin_post_wpgl_fix_post_types', 'wpgl_fix_post_types_handler');

/**
 * Handler for the fix post types action
 */
function wpgl_fix_post_types_handler() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'wp-gallery-link'));
    }
    
    // Verify nonce
    if (!isset($_POST['wpgl_nonce']) || !wp_verify_nonce($_POST['wpgl_nonce'], 'wpgl_fix_post_types')) {
        wp_die(__('Security check failed.', 'wp-gallery-link'));
    }
    
    // Get posts with incorrect post type
    global $wpdb;
    $mismatched_albums = $wpdb->get_results(
        "SELECT ID, post_title 
         FROM {$wpdb->posts} 
         WHERE post_type = 'wp_gallery_album'"
    );
    
    $updated_count = 0;
    foreach ($mismatched_albums as $album) {
        // Update post type
        $result = $wpdb->update(
            $wpdb->posts,
            ['post_type' => 'gphoto_album'],
            ['ID' => $album->ID]
        );
        
        if ($result !== false) {
            $updated_count++;
        }
    }
    
    // Redirect back to debug page with status
    wp_redirect(add_query_arg(
        array(
            'page' => 'wp-gallery-link-debug',
            'updated' => $updated_count,
            'total' => count($mismatched_albums)
        ),
        admin_url('admin.php')
    ));
    exit;
}
