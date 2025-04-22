
<?php
/**
 * Admin settings and functionality
 */
class WP_Gallery_Link_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
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
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_wp-gallery-link', 'wp-gallery-link_page_wp-gallery-link-import', 'wp-gallery-link_page_wp-gallery-link-debug'))) {
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
            'nonce' => wp_create_nonce('wpgl_debug'),
            'i18n' => array(
                'importing' => __('Importing...', 'wp-gallery-link'),
                'imported' => __('Imported', 'wp-gallery-link'),
                'import' => __('Import', 'wp-gallery-link'),
                'import_success' => __('Album imported successfully!', 'wp-gallery-link'),
                'import_error' => __('Error importing album:', 'wp-gallery-link'),
                'loading' => __('Loading albums...', 'wp-gallery-link'),
                'start' => __('Start Loading Albums', 'wp-gallery-link'),
                'stop' => __('Stop Loading', 'wp-gallery-link'),
                'albums_fetched' => __('Albums fetched:', 'wp-gallery-link'),
                'load_more' => __('Load more', 'wp-gallery-link'),
                'noAlbums' => __('No albums found.', 'wp-gallery-link'),
                'error' => __('Error:', 'wp-gallery-link'),
                'stopped' => __('Loading stopped.', 'wp-gallery-link')
            )
        ));
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
        
        $google_api = wp_gallery_link()->google_api;
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
                    
                    <?php if ($google_api->is_connected()): ?>
                        <p class="wpgl-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Connected to Google Photos', 'wp-gallery-link'); ?>
                        </p>
                        
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-gallery-link-import')); ?>" class="button button-primary">
                                <?php _e('Import Albums', 'wp-gallery-link'); ?>
                            </a>
                            
                            <a href="<?php echo esc_url($google_api->get_auth_url()); ?>" class="button">
                                <?php _e('Reconnect', 'wp-gallery-link'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p class="wpgl-not-connected">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php _e('Not connected to Google Photos', 'wp-gallery-link'); ?>
                        </p>
                        
                        <?php if (!empty($google_api->get_auth_url())): ?>
                            <p>
                                <a href="<?php echo esc_url($google_api->get_auth_url()); ?>" class="button button-primary">
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

        $google_api = wp_gallery_link()->google_api;

        if (!$google_api->is_connected()) {
            wp_redirect(admin_url('admin.php?page=wp-gallery-link'));
            exit;
        }

        // Check if we've just imported an album
        $album_imported = isset($_GET['imported']) ? absint($_GET['imported']) : 0;
        ?>
        <div class="wrap">
            <h1><?php _e('Import Albums from Google Photos', 'wp-gallery-link'); ?></h1>

            <?php if ($album_imported > 0): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Album imported successfully!', 'wp-gallery-link'); ?></p>
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
            </div>
        </div>

        <script type="text/template" id="tmpl-wpgl-album">
            <div class="wpgl-album" data-id="{{ data.id }}">
                <div class="wpgl-album-img">
                    <# if (data.coverPhotoBaseUrl) { #>
                        <img src="{{ data.coverPhotoBaseUrl }}=w300-h200" alt="{{ data.title }}">
                    <# } else { #>
                        <div class="wpgl-no-image"><?php _e('No Cover Image', 'wp-gallery-link'); ?></div>
                    <# } #>
                </div>
                <div class="wpgl-album-info">
                    <h3 class="wpgl-album-title">{{ data.title }}</h3>
                    <div class="wpgl-album-meta">
                        <span class="wpgl-album-count">{{ data.mediaItemsCount }} <?php _e('photos', 'wp-gallery-link'); ?></span>
                    </div>
                    <div class="wpgl-album-actions">
                        <button class="button button-primary wpgl-import-album" data-id="{{ data.id }}">
                            <?php _e('Import Album', 'wp-gallery-link'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </script>
        <?php
    }
}
