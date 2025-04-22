
<?php
/**
 * Custom Post Type for Google Photos Albums
 */
class WP_Gallery_Link_CPT {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }
    
    /**
     * Register the custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x('Albums', 'post type general name', 'wp-gallery-link'),
            'singular_name'      => _x('Album', 'post type singular name', 'wp-gallery-link'),
            'menu_name'          => _x('Photo Albums', 'admin menu', 'wp-gallery-link'),
            'name_admin_bar'     => _x('Album', 'add new on admin bar', 'wp-gallery-link'),
            'add_new'            => _x('Add New', 'album', 'wp-gallery-link'),
            'add_new_item'       => __('Add New Album', 'wp-gallery-link'),
            'new_item'           => __('New Album', 'wp-gallery-link'),
            'edit_item'          => __('Edit Album', 'wp-gallery-link'),
            'view_item'          => __('View Album', 'wp-gallery-link'),
            'all_items'          => __('All Albums', 'wp-gallery-link'),
            'search_items'       => __('Search Albums', 'wp-gallery-link'),
            'parent_item_colon'  => __('Parent Albums:', 'wp-gallery-link'),
            'not_found'          => __('No albums found.', 'wp-gallery-link'),
            'not_found_in_trash' => __('No albums found in Trash.', 'wp-gallery-link')
        );

        $args = array(
            'labels'             => $labels,
            'description'        => __('Photo albums from Google Photos', 'wp-gallery-link'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'photo-album'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-format-gallery',
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields')
        );

        register_post_type('gphoto_album', $args);
    }
    
    /**
     * Register taxonomy for Album categories
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('Album Categories', 'taxonomy general name', 'wp-gallery-link'),
            'singular_name'     => _x('Album Category', 'taxonomy singular name', 'wp-gallery-link'),
            'search_items'      => __('Search Album Categories', 'wp-gallery-link'),
            'all_items'         => __('All Album Categories', 'wp-gallery-link'),
            'parent_item'       => __('Parent Album Category', 'wp-gallery-link'),
            'parent_item_colon' => __('Parent Album Category:', 'wp-gallery-link'),
            'edit_item'         => __('Edit Album Category', 'wp-gallery-link'),
            'update_item'       => __('Update Album Category', 'wp-gallery-link'),
            'add_new_item'      => __('Add New Album Category', 'wp-gallery-link'),
            'new_item_name'     => __('New Album Category Name', 'wp-gallery-link'),
            'menu_name'         => __('Album Categories', 'wp-gallery-link'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'album-category'),
        );

        register_taxonomy('album_category', array('gphoto_album'), $args);
    }
    
    /**
     * Add meta boxes for album details
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gphoto_album_details',
            __('Album Details', 'wp-gallery-link'),
            array($this, 'render_meta_box_content'),
            'gphoto_album',
            'normal',
            'high'
        );
    }
    
    /**
     * Render Meta Box content
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box_content($post) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field('gphoto_album_meta_box', 'gphoto_album_meta_box_nonce');

        // Retrieve existing values
        $album_id = get_post_meta($post->ID, '_gphoto_album_id', true);
        $album_url = get_post_meta($post->ID, '_gphoto_album_url', true);
        $album_date = get_post_meta($post->ID, '_gphoto_album_date', true);
        $photo_count = get_post_meta($post->ID, '_gphoto_photo_count', true);
        $album_order = get_post_meta($post->ID, '_gphoto_album_order', true);
        
        ?>
        <div class="gphoto-album-meta-box">
            <div class="gphoto-meta-row">
                <label for="gphoto_album_id">
                    <?php _e('Google Photos Album ID:', 'wp-gallery-link'); ?>
                </label>
                <input type="text" id="gphoto_album_id" name="gphoto_album_id" 
                       value="<?php echo esc_attr($album_id); ?>" class="widefat" />
                <p class="description">
                    <?php _e('The unique identifier for this album in Google Photos', 'wp-gallery-link'); ?>
                </p>
            </div>
            
            <div class="gphoto-meta-row">
                <label for="gphoto_album_url">
                    <?php _e('Google Photos Album URL:', 'wp-gallery-link'); ?>
                </label>
                <input type="url" id="gphoto_album_url" name="gphoto_album_url" 
                       value="<?php echo esc_url($album_url); ?>" class="widefat" />
                <p class="description">
                    <?php _e('The direct URL to this album in Google Photos', 'wp-gallery-link'); ?>
                </p>
            </div>
            
            <div class="gphoto-meta-row">
                <label for="gphoto_album_date">
                    <?php _e('Album Date:', 'wp-gallery-link'); ?>
                </label>
                <input type="date" id="gphoto_album_date" name="gphoto_album_date" 
                       value="<?php echo esc_attr($album_date); ?>" class="widefat" />
            </div>
            
            <div class="gphoto-meta-row">
                <label for="gphoto_photo_count">
                    <?php _e('Photo Count:', 'wp-gallery-link'); ?>
                </label>
                <input type="number" id="gphoto_photo_count" name="gphoto_photo_count" 
                       value="<?php echo intval($photo_count); ?>" class="small-text" min="0" />
            </div>
            
            <div class="gphoto-meta-row">
                <label for="gphoto_album_order">
                    <?php _e('Display Order:', 'wp-gallery-link'); ?>
                </label>
                <input type="number" id="gphoto_album_order" name="gphoto_album_order" 
                       value="<?php echo intval($album_order); ?>" class="small-text" min="0" />
                <p class="description">
                    <?php _e('Lower numbers will be displayed first', 'wp-gallery-link'); ?>
                </p>
            </div>
        </div>
        <style>
            .gphoto-meta-row {
                margin-bottom: 15px;
            }
            .gphoto-meta-row label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .gphoto-meta-row .description {
                margin-top: 2px;
                color: #666;
                font-style: italic;
            }
        </style>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_meta_box_data($post_id) {
        // Check if our nonce is set.
        if (!isset($_POST['gphoto_album_meta_box_nonce'])) {
            return;
        }

        // Verify that the nonce is valid.
        if (!wp_verify_nonce($_POST['gphoto_album_meta_box_nonce'], 'gphoto_album_meta_box')) {
            return;
        }

        // If this is an autosave, we don't want to do anything.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sanitize and save the data
        if (isset($_POST['gphoto_album_id'])) {
            update_post_meta($post_id, '_gphoto_album_id', sanitize_text_field($_POST['gphoto_album_id']));
        }
        
        if (isset($_POST['gphoto_album_url'])) {
            update_post_meta($post_id, '_gphoto_album_url', esc_url_raw($_POST['gphoto_album_url']));
        }
        
        if (isset($_POST['gphoto_album_date'])) {
            update_post_meta($post_id, '_gphoto_album_date', sanitize_text_field($_POST['gphoto_album_date']));
        }
        
        if (isset($_POST['gphoto_photo_count'])) {
            update_post_meta($post_id, '_gphoto_photo_count', intval($_POST['gphoto_photo_count']));
        }
        
        if (isset($_POST['gphoto_album_order'])) {
            update_post_meta($post_id, '_gphoto_album_order', intval($_POST['gphoto_album_order']));
        }
    }
}
