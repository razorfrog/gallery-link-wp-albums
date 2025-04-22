
<?php
/**
 * Admin functionality
 */
class WP_Gallery_Link_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Handle authorization callback
        add_action('admin_init', array($this, 'handle_auth_callback'));
        
        // AJAX import album action
        add_action('wp_ajax_wpgl_import_album', array($this, 'ajax_import_album'));
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('WP Gallery Link', 'wp-gallery-link'),
            __('Gallery Link', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link',
            array($this, 'render_main_page'),
            'dashicons-format-gallery',
            30
        );
        
        add_submenu_page(
            'wp-gallery-link',
            __('Dashboard', 'wp-gallery-link'),
            __('Dashboard', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'wp-gallery-link',
            __('Import Albums', 'wp-gallery-link'),
            __('Import Albums', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link-import',
            array($this, 'render_import_page')
        );
        
        add_submenu_page(
            'wp-gallery-link',
            __('Settings', 'wp-gallery-link'),
            __('Settings', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link-settings',
            array($this, 'render_settings_page')
        );
        
        // Hidden page for auth callback
        add_submenu_page(
            null,
            __('Google Authorization', 'wp-gallery-link'),
            __('Google Authorization', 'wp-gallery-link'),
            'manage_options',
            'wp-gallery-link-auth',
            array($this, 'render_auth_callback_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'wp-gallery-link') === false) {
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
            'nonce' => wp_create_nonce('wpgl_fetch_albums'),
            'importNonce' => wp_create_nonce('wpgl_import_album'),
            'i18n' => array(
                'loading' => __('Loading albums...', 'wp-gallery-link'),
                'error' => __('Error:', 'wp-gallery-link'),
                'noAlbums' => __('No albums found.', 'wp-gallery-link'),
                'import' => __('Import', 'wp-gallery-link'),
                'importing' => __('Importing...', 'wp-gallery-link'),
                'imported' => __('Imported', 'wp-gallery-link'),
                'photos' => __('photos', 'wp-gallery-link')
            )
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wpgl_settings', 'wpgl_google_client_id');
        register_setting('wpgl_settings', 'wpgl_google_client_secret');
        register_setting('wpgl_settings', 'wpgl_shortcode_columns', array(
            'default' => 3,
            'sanitize_callback' => 'absint'
        ));
    }
    
    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        // Get album stats
        $album_count = wp_count_posts('gphoto_album');
        $published_albums = $album_count->publish ?? 0;
        
        // Get categories
        $categories = get_terms(array(
            'taxonomy' => 'album_category',
            'hide_empty' => false
        ));
        $category_count = is_wp_error($categories) ? 0 : count($categories);
        
        // Check authorization status
        $google_api = wp_gallery_link()->google_api;
        $is_authorized = $google_api->is_authorized();
        $client_id = get_option('wpgl_google_client_id');
        $client_secret = get_option('wpgl_google_client_secret');
        $has_credentials = !empty($client_id) && !empty($client_secret);
        
        ?>
        <div class="wrap wpgl-admin">
            <h1><?php _e('WP Gallery Link Dashboard', 'wp-gallery-link'); ?></h1>
            
            <div class="wpgl-dashboard-grid">
                <div class="wpgl-dashboard-card">
                    <div class="wpgl-card-header">
                        <h2><?php _e('Overview', 'wp-gallery-link'); ?></h2>
                    </div>
                    <div class="wpgl-card-content">
                        <div class="wpgl-stat-item">
                            <span class="wpgl-stat-value"><?php echo intval($published_albums); ?></span>
                            <span class="wpgl-stat-label"><?php _e('Published Albums', 'wp-gallery-link'); ?></span>
                        </div>
                        <div class="wpgl-stat-item">
                            <span class="wpgl-stat-value"><?php echo intval($category_count); ?></span>
                            <span class="wpgl-stat-label"><?php _e('Categories', 'wp-gallery-link'); ?></span>
                        </div>
                    </div>
                    <div class="wpgl-card-footer">
                        <a href="<?php echo admin_url('edit.php?post_type=gphoto_album'); ?>" class="button button-secondary">
                            <?php _e('Manage Albums', 'wp-gallery-link'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="wpgl-dashboard-card">
                    <div class="wpgl-card-header">
                        <h2><?php _e('Google Photos Connection', 'wp-gallery-link'); ?></h2>
                    </div>
                    <div class="wpgl-card-content">
                        <?php if (!$has_credentials): ?>
                            <div class="wpgl-connection-status wpgl-status-warning">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('API credentials not configured', 'wp-gallery-link'); ?>
                            </div>
                            <p>
                                <?php _e('Please configure your Google API credentials in the settings page.', 'wp-gallery-link'); ?>
                            </p>
                        <?php elseif (!$is_authorized): ?>
                            <div class="wpgl-connection-status wpgl-status-error">
                                <span class="dashicons dashicons-no"></span>
                                <?php _e('Not connected to Google Photos', 'wp-gallery-link'); ?>
                            </div>
                            <p>
                                <?php _e('Please authorize the application to access your Google Photos.', 'wp-gallery-link'); ?>
                            </p>
                        <?php else: ?>
                            <div class="wpgl-connection-status wpgl-status-success">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Connected to Google Photos', 'wp-gallery-link'); ?>
                            </div>
                            <p>
                                <?php _e('Your site is authorized to access your Google Photos albums.', 'wp-gallery-link'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="wpgl-card-footer">
                        <?php if ($has_credentials): ?>
                            <?php if ($is_authorized): ?>
                                <a href="<?php echo admin_url('admin.php?page=wp-gallery-link-import'); ?>" class="button button-primary">
                                    <?php _e('Import Albums', 'wp-gallery-link'); ?>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url($google_api->get_auth_url()); ?>" class="button button-primary">
                                    <?php _e('Connect to Google Photos', 'wp-gallery-link'); ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo admin_url('admin.php?page=wp-gallery-link-settings'); ?>" class="button button-primary">
                                <?php _e('Configure API Settings', 'wp-gallery-link'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="wpgl-dashboard-card">
                    <div class="wpgl-card-header">
                        <h2><?php _e('Quick Usage Guide', 'wp-gallery-link'); ?></h2>
                    </div>
                    <div class="wpgl-card-content">
                        <ol class="wpgl-steps">
                            <li><?php _e('Configure your Google API credentials in the settings', 'wp-gallery-link'); ?></li>
                            <li><?php _e('Connect to Google Photos', 'wp-gallery-link'); ?></li>
                            <li><?php _e('Import albums from Google Photos', 'wp-gallery-link'); ?></li>
                            <li><?php _e('Organize albums with categories', 'wp-gallery-link'); ?></li>
                            <li><?php _e('Display albums using the shortcode', 'wp-gallery-link'); ?></li>
                        </ol>
                        <div class="wpgl-shortcode-example">
                            <code>[wp_gallery_link]</code>
                            <span class="wpgl-shortcode-copy" 
                                  onclick="navigator.clipboard.writeText('[wp_gallery_link]')">
                                <span class="dashicons dashicons-clipboard"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .wpgl-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .wpgl-dashboard-card {
                border: 1px solid #ccd0d4;
                background-color: #fff;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                display: flex;
                flex-direction: column;
            }
            .wpgl-card-header {
                border-bottom: 1px solid #ccd0d4;
                padding: 12px 15px;
            }
            .wpgl-card-header h2 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
            }
            .wpgl-card-content {
                padding: 15px;
                flex-grow: 1;
            }
            .wpgl-card-footer {
                border-top: 1px solid #ccd0d4;
                padding: 12px 15px;
                background-color: #f8f9fa;
                text-align: right;
            }
            .wpgl-stat-item {
                margin-bottom: 15px;
            }
            .wpgl-stat-value {
                display: block;
                font-size: 32px;
                font-weight: bold;
                line-height: 1.2;
            }
            .wpgl-stat-label {
                font-size: 14px;
                color: #646970;
            }
            .wpgl-connection-status {
                padding: 10px;
                border-radius: 3px;
                margin-bottom: 15px;
                font-weight: 500;
                display: flex;
                align-items: center;
            }
            .wpgl-connection-status .dashicons {
                margin-right: 8px;
            }
            .wpgl-status-success {
                background-color: #ecf7ed;
                color: #0c5460;
            }
            .wpgl-status-warning {
                background-color: #fff8e5;
                color: #856404;
            }
            .wpgl-status-error {
                background-color: #f8d7da;
                color: #721c24;
            }
            .wpgl-steps {
                margin-left: 18px;
            }
            .wpgl-steps li {
                margin-bottom: 8px;
            }
            .wpgl-shortcode-example {
                background: #f0f0f1;
                padding: 10px 15px;
                border-radius: 3px;
                position: relative;
                margin-top: 15px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .wpgl-shortcode-copy {
                cursor: pointer;
            }
            .wpgl-shortcode-copy:hover {
                color: #007cba;
            }
        </style>
        <?php
    }
    
    /**
     * Render import page
     */
    public function render_import_page() {
        $google_api = wp_gallery_link()->google_api;
        $is_authorized = $google_api->is_authorized();
        $has_credentials = !empty(get_option('wpgl_google_client_id')) && !empty(get_option('wpgl_google_client_secret'));
        
        ?>
        <div class="wrap wpgl-admin">
            <h1><?php _e('Import Albums from Google Photos', 'wp-gallery-link'); ?></h1>
            
            <?php if (!$has_credentials): ?>
                <div class="notice notice-error">
                    <p>
                        <?php _e('Google API credentials are not configured.', 'wp-gallery-link'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wp-gallery-link-settings'); ?>">
                            <?php _e('Configure API Settings', 'wp-gallery-link'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif (!$is_authorized): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('Not connected to Google Photos.', 'wp-gallery-link'); ?>
                        <a href="<?php echo esc_url($google_api->get_auth_url()); ?>" class="button button-small">
                            <?php _e('Connect Now', 'wp-gallery-link'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <p><?php _e('Select albums from your Google Photos account to import into WordPress.', 'wp-gallery-link'); ?></p>
                
                <div class="wpgl-import-container">
                    <div class="wpgl-loading-container">
                        <div class="wpgl-loading-status">
                            <span class="spinner is-active"></span>
                            <span class="wpgl-loading-text"><?php _e('Loading albums...', 'wp-gallery-link'); ?></span>
                        </div>
                        <div class="wpgl-progress-bar">
                            <div class="wpgl-progress-value"></div>
                        </div>
                        <div class="wpgl-loading-log"></div>
                    </div>
                    
                    <div class="wpgl-albums-container">
                        <div class="wpgl-albums-grid"></div>
                    </div>
                    
                    <button class="button button-primary wpgl-load-albums">
                        <?php _e('Load Albums', 'wp-gallery-link'); ?>
                    </button>
                </div>
                
                <!-- Album template -->
                <script type="text/html" id="tmpl-wpgl-album">
                    <div class="wpgl-album" data-id="{{ data.id }}">
                        <div class="wpgl-album-inner">
                            <div class="wpgl-album-thumbnail">
                                <# if (data.coverPhotoBaseUrl) { #>
                                    <img src="{{ data.coverPhotoBaseUrl }}=w400-h300" alt="{{ data.title }}">
                                <# } else { #>
                                    <div class="wpgl-no-thumbnail">
                                        <span class="dashicons dashicons-format-gallery"></span>
                                    </div>
                                <# } #>
                            </div>
                            
                            <div class="wpgl-album-content">
                                <h3 class="wpgl-album-title">{{ data.title }}</h3>
                                <div class="wpgl-album-meta">
                                    <# if (data.mediaItemsCount) { #>
                                        <span class="wpgl-album-count">{{ data.mediaItemsCount }} <?php _e('photos', 'wp-gallery-link'); ?></span>
                                    <# } #>
                                </div>
                            </div>
                            
                            <div class="wpgl-album-actions">
                                <button class="button wpgl-import-album" data-id="{{ data.id }}">
                                    <?php _e('Import', 'wp-gallery-link'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </script>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap wpgl-admin">
            <h1><?php _e('WP Gallery Link Settings', 'wp-gallery-link'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpgl_settings'); ?>
                <?php do_settings_sections('wpgl_settings'); ?>
                
                <div class="wpgl-settings-section">
                    <h2><?php _e('Google API Settings', 'wp-gallery-link'); ?></h2>
                    <p class="description">
                        <?php _e('To use this plugin, you need to create a project in the Google API Console and configure OAuth credentials.', 'wp-gallery-link'); ?>
                        <a href="https://console.developers.google.com/" target="_blank">
                            <?php _e('Google API Console', 'wp-gallery-link'); ?> <span class="dashicons dashicons-external"></span>
                        </a>
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wpgl_google_client_id"><?php _e('Client ID', 'wp-gallery-link'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="wpgl_google_client_id"
                                       name="wpgl_google_client_id"
                                       value="<?php echo esc_attr(get_option('wpgl_google_client_id')); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Your Google OAuth Client ID', 'wp-gallery-link'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpgl_google_client_secret"><?php _e('Client Secret', 'wp-gallery-link'); ?></label>
                            </th>
                            <td>
                                <input type="password" 
                                       id="wpgl_google_client_secret"
                                       name="wpgl_google_client_secret"
                                       value="<?php echo esc_attr(get_option('wpgl_google_client_secret')); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Your Google OAuth Client Secret', 'wp-gallery-link'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('Redirect URI', 'wp-gallery-link'); ?>
                            </th>
                            <td>
                                <code><?php echo admin_url('admin.php?page=wp-gallery-link-auth'); ?></code>
                                <p class="description">
                                    <?php _e('Use this URL in your Google API Console as the authorized redirect URI', 'wp-gallery-link'); ?>
                                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo admin_url('admin.php?page=wp-gallery-link-auth'); ?>')">
                                        <?php _e('Copy', 'wp-gallery-link'); ?>
                                    </button>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="wpgl-settings-section">
                    <h2><?php _e('Display Settings', 'wp-gallery-link'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wpgl_shortcode_columns"><?php _e('Default Columns', 'wp-gallery-link'); ?></label>
                            </th>
                            <td>
                                <select id="wpgl_shortcode_columns" name="wpgl_shortcode_columns">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected(get_option('wpgl_shortcode_columns', 3), $i); ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Default number of columns for the gallery display', 'wp-gallery-link'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render authorization callback page
     */
    public function render_auth_callback_page() {
        $success = isset($_GET['success']) ? filter_var($_GET['success'], FILTER_VALIDATE_BOOLEAN) : false;
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        
        ?>
        <div class="wrap wpgl-admin">
            <h1><?php _e('Google Photos Authorization', 'wp-gallery-link'); ?></h1>
            
            <div class="wpgl-auth-result">
                <?php if ($success): ?>
                    <div class="wpgl-auth-success">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <div class="wpgl-auth-message">
                            <h2><?php _e('Authorization Successful!', 'wp-gallery-link'); ?></h2>
                            <p><?php _e('Your site is now connected to Google Photos.', 'wp-gallery-link'); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="wpgl-auth-error">
                        <span class="dashicons dashicons-no"></span>
                        <div class="wpgl-auth-message">
                            <h2><?php _e('Authorization Failed', 'wp-gallery-link'); ?></h2>
                            <?php if ($error): ?>
                                <p><?php echo esc_html($error); ?></p>
                            <?php else: ?>
                                <p><?php _e('An error occurred during the authorization process.', 'wp-gallery-link'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="wpgl-auth-actions">
                    <?php if ($success): ?>
                        <a href="<?php echo admin_url('admin.php?page=wp-gallery-link-import'); ?>" class="button button-primary">
                            <?php _e('Import Albums', 'wp-gallery-link'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo admin_url('admin.php?page=wp-gallery-link-settings'); ?>" class="button button-secondary">
                            <?php _e('Check API Settings', 'wp-gallery-link'); ?>
                        </a>
                        <?php 
                        $google_api = wp_gallery_link()->google_api;
                        if ($google_api->get_auth_url()): 
                        ?>
                            <a href="<?php echo esc_url($google_api->get_auth_url()); ?>" class="button button-primary">
                                <?php _e('Try Again', 'wp-gallery-link'); ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <a href="<?php echo admin_url('admin.php?page=wp-gallery-link'); ?>" class="button button-secondary">
                        <?php _e('Back to Dashboard', 'wp-gallery-link'); ?>
                    </a>
                </div>
            </div>
        </div>
        <style>
            .wpgl-auth-result {
                max-width: 600px;
                margin: 50px auto;
                text-align: center;
                padding: 30px;
                background: #fff;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,.1);
            }
            .wpgl-auth-success, .wpgl-auth-error {
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 30px;
            }
            .wpgl-auth-success .dashicons, .wpgl-auth-error .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                margin-right: 20px;
            }
            .wpgl-auth-success .dashicons {
                color: #46b450;
            }
            .wpgl-auth-error .dashicons {
                color: #dc3232;
            }
            .wpgl-auth-message {
                text-align: left;
            }
            .wpgl-auth-message h2 {
                margin-top: 0;
            }
            .wpgl-auth-actions {
                margin-top: 30px;
            }
            .wpgl-auth-actions .button {
                margin: 0 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Handle authorization callback
     */
    public function handle_auth_callback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-gallery-link-auth') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-gallery-link'));
        }
        
        // Handle authorization code
        if (isset($_GET['code'])) {
            $google_api = wp_gallery_link()->google_api;
            $success = $google_api->exchange_code_for_token($_GET['code']);
            
            if ($success) {
                wp_redirect(admin_url('admin.php?page=wp-gallery-link-auth&success=1'));
                exit;
            } else {
                wp_redirect(admin_url('admin.php?page=wp-gallery-link-auth&success=0&error=' . urlencode(__('Failed to obtain access token', 'wp-gallery-link'))));
                exit;
            }
        }
        
        // Handle authorization errors
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            wp_redirect(admin_url('admin.php?page=wp-gallery-link-auth&success=0&error=' . urlencode($error)));
            exit;
        }
    }
    
    /**
     * AJAX handler for importing an album
     */
    public function ajax_import_album() {
        // Check nonce for security
        if (!check_ajax_referer('wpgl_import_album', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed', 'wp-gallery-link')));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'wp-gallery-link')));
        }
        
        // Check for required parameters
        if (!isset($_POST['album']) || !is_array($_POST['album'])) {
            wp_send_json_error(array('message' => __('Missing album data', 'wp-gallery-link')));
        }
        
        $album = $_POST['album'];
        
        // Import album
        $google_api = wp_gallery_link()->google_api;
        $result = $google_api->import_album($album);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Album imported successfully', 'wp-gallery-link'),
            'post_id' => $result,
            'edit_url' => get_edit_post_link($result, 'raw')
        ));
    }
}
