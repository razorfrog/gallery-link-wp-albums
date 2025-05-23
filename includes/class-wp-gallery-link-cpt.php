<?php
/**
 * Custom Post Type for Google Photo Albums
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
        add_filter('manage_gphoto_album_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_gphoto_album_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link CPT: Class initialized');
        }
    }
    
    /**
     * Register the custom post type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => _x('Google Photo Albums', 'post type general name', 'wp-gallery-link'),
            'singular_name'      => _x('Album', 'post type singular name', 'wp-gallery-link'),
            'menu_name'          => _x('GP Albums', 'admin menu', 'wp-gallery-link'),
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
            'description'        => __('Google Photo Albums', 'wp-gallery-link'),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'photo-album'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-format-gallery',
            'supports'           => array('title', 'editor', 'thumbnail', 'excerpt')
        );

        register_post_type('gphoto_album', $args);
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link CPT: Registered post type gphoto_album');
        }
    }
    
    /**
     * Alias for register_post_type to maintain backward compatibility
     */
    public function register_post_types() {
        return $this->register_post_type();
    }
    
    /**
     * Register taxonomy for album categories
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
     * Add meta boxes to the album post type
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gphoto_album_details',
            __('Album Details', 'wp-gallery-link'),
            array($this, 'render_meta_box'),
            'gphoto_album',
            'normal',
            'high'
        );
    }
    
    /**
     * Render the album details meta box
     */
    public function render_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('gphoto_album_meta_box', 'gphoto_album_meta_box_nonce');
        
        // Get saved values
        $album_id = get_post_meta($post->ID, '_gphoto_album_id', true);
        $album_url = get_post_meta($post->ID, '_gphoto_album_url', true);
        $album_date = get_post_meta($post->ID, '_gphoto_album_date', true);
        $custom_order = get_post_meta($post->ID, '_gphoto_album_order', true);
        
        // Output fields
        ?>
        <div class="wp-gallery-link-meta-box">
            <style>
                .wp-gallery-link-meta-box .form-field {
                    margin: 1em 0;
                }
                .wp-gallery-link-meta-box label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: bold;
                }
                .wp-gallery-link-meta-box input[type="text"],
                .wp-gallery-link-meta-box input[type="url"],
                .wp-gallery-link-meta-box input[type="number"] {
                    width: 100%;
                    max-width: 400px;
                }
                .wp-gallery-link-meta-box .description {
                    color: #666;
                    font-style: italic;
                    margin-top: 5px;
                }
            </style>
            
            <div class="form-field">
                <label for="gphoto_album_id">
                    <?php _e('Google Photos Album ID', 'wp-gallery-link'); ?>
                </label>
                <input type="text" id="gphoto_album_id" name="gphoto_album_id" 
                       value="<?php echo esc_attr($album_id); ?>" />
                <p class="description">
                    <?php _e('The unique identifier for the Google Photos album.', 'wp-gallery-link'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="gphoto_album_url">
                    <?php _e('Album URL', 'wp-gallery-link'); ?>
                </label>
                <input type="url" id="gphoto_album_url" name="gphoto_album_url" 
                       value="<?php echo esc_url($album_url); ?>" />
                <p class="description">
                    <?php _e('The URL to the Google Photos album.', 'wp-gallery-link'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="gphoto_album_date">
                    <?php _e('Album Date', 'wp-gallery-link'); ?>
                </label>
                <input type="date" id="gphoto_album_date" name="gphoto_album_date" 
                       value="<?php echo esc_attr($album_date); ?>" />
                <p class="description">
                    <?php _e('The date when the album was created.', 'wp-gallery-link'); ?>
                </p>
            </div>
            
            <div class="form-field">
                <label for="gphoto_album_order">
                    <?php _e('Custom Order', 'wp-gallery-link'); ?>
                </label>
                <input type="number" id="gphoto_album_order" name="gphoto_album_order" 
                       value="<?php echo intval($custom_order); ?>" step="1" min="0" />
                <p class="description">
                    <?php _e('Set a custom order for this album (lower numbers appear first).', 'wp-gallery-link'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save the meta box data
     */
    public function save_meta_box_data($post_id) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['gphoto_album_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['gphoto_album_meta_box_nonce'], 'gphoto_album_meta_box')) {
            return;
        }
        
        // Check user permissions
        if (isset($_POST['post_type']) && 'gphoto_album' === $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
        
        // Don't save during autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save album ID
        if (isset($_POST['gphoto_album_id'])) {
            update_post_meta(
                $post_id,
                '_gphoto_album_id',
                sanitize_text_field($_POST['gphoto_album_id'])
            );
        }
        
        // Save album URL
        if (isset($_POST['gphoto_album_url'])) {
            update_post_meta(
                $post_id,
                '_gphoto_album_url',
                esc_url_raw($_POST['gphoto_album_url'])
            );
        }
        
        // Save album date
        if (isset($_POST['gphoto_album_date'])) {
            update_post_meta(
                $post_id,
                '_gphoto_album_date',
                sanitize_text_field($_POST['gphoto_album_date'])
            );
        }
        
        // Save custom order
        if (isset($_POST['gphoto_album_order'])) {
            update_post_meta(
                $post_id,
                '_gphoto_album_order',
                intval($_POST['gphoto_album_order'])
            );
        }
    }
    
    /**
     * Set custom columns for the album list
     */
    public function set_custom_columns($columns) {
        $new_columns = array();
        
        // Add thumbnail after checkbox but before title
        $new_columns['cb'] = $columns['cb'];
        $new_columns['thumbnail'] = __('Thumbnail', 'wp-gallery-link');
        $new_columns['title'] = $columns['title'];
        
        // Add our custom columns
        $new_columns['album_url'] = __('Album URL', 'wp-gallery-link');
        $new_columns['album_date'] = __('Album Date', 'wp-gallery-link');
        $new_columns['custom_order'] = __('Order', 'wp-gallery-link');
        
        // Add remaining columns
        if (isset($columns['taxonomy-album_category'])) {
            $new_columns['taxonomy-album_category'] = $columns['taxonomy-album_category'];
        }
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Display custom column content
     */
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'thumbnail':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(50, 50));
                } else {
                    echo '<img src="' . WP_GALLERY_LINK_URL . 'assets/images/default-album.png" width="50" height="50" />';
                }
                break;
                
            case 'album_url':
                $album_url = get_post_meta($post_id, '_gphoto_album_url', true);
                if ($album_url) {
                    echo '<a href="' . esc_url($album_url) . '" target="_blank">' . __('View Album', 'wp-gallery-link') . '</a>';
                } else {
                    echo '—';
                }
                break;
                
            case 'album_date':
                $album_date = get_post_meta($post_id, '_gphoto_album_date', true);
                echo $album_date ? date_i18n(get_option('date_format'), strtotime($album_date)) : '—';
                break;
                
            case 'custom_order':
                $custom_order = get_post_meta($post_id, '_gphoto_album_order', true);
                echo $custom_order !== '' ? intval($custom_order) : '—';
                break;
        }
    }
    
    /**
     * Create a new album from Google Photos data
     * 
     * @param array $album_data Album data from Google Photos API
     * @return int|WP_Error The post ID on success, WP_Error on failure
     */
    public function create_album_from_google($album_data) {
        if (empty($album_data['id']) || empty($album_data['title'])) {
            error_log('WP Gallery Link CPT: Invalid album data provided');
            return new WP_Error('invalid_album_data', __('Invalid album data provided', 'wp-gallery-link'));
        }
        
        // Check if an album with this ID already exists
        $existing_albums = get_posts(array(
            'post_type' => 'gphoto_album',
            'meta_key' => '_gphoto_album_id',
            'meta_value' => $album_data['id'],
            'posts_per_page' => 1,
        ));
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link CPT: Checking for existing album with Google ID ' . $album_data['id']);
            error_log('WP Gallery Link CPT: Found ' . count($existing_albums) . ' existing albums');
        }
        
        if (!empty($existing_albums)) {
            return new WP_Error('album_exists', __('Album already exists', 'wp-gallery-link'), array('post_id' => $existing_albums[0]->ID));
        }
        
        // Create post object
        $post_args = array(
            'post_title'   => sanitize_text_field($album_data['title']),
            'post_content' => isset($album_data['description']) ? sanitize_textarea_field($album_data['description']) : '',
            'post_status'  => 'publish',
            'post_type'    => 'gphoto_album',
        );
        
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link CPT: Creating new album post with post_type: ' . $post_args['post_type']);
        }
        
        // Insert the post into the database
        $post_id = wp_insert_post($post_args);
        
        if (is_wp_error($post_id)) {
            error_log('WP Gallery Link CPT: Error creating album post - ' . $post_id->get_error_message());
            return $post_id;
        }
        
        // Verify the post was created with the correct post type
        $created_post = get_post($post_id);
        if (WP_GALLERY_LINK_DEBUG) {
            error_log('WP Gallery Link CPT: Created post ID ' . $post_id . ' with post_type: ' . $created_post->post_type);
        }
        
        // Save album metadata
        update_post_meta($post_id, '_gphoto_album_id', sanitize_text_field($album_data['id']));
        
        // If album has a URL
        if (!empty($album_data['productUrl'])) {
            update_post_meta($post_id, '_gphoto_album_url', esc_url_raw($album_data['productUrl']));
        }
        
        // If album has a creation date
        if (!empty($album_data['creationTime'])) {
            $date = date('Y-m-d', strtotime($album_data['creationTime']));
            update_post_meta($post_id, '_gphoto_album_date', $date);
        }
        
        // If album has a photo count
        if (isset($album_data['mediaItemsCount'])) {
            update_post_meta($post_id, '_gphoto_photo_count', intval($album_data['mediaItemsCount']));
        }
        
        // Save cover photo URL if available
        if (!empty($album_data['coverPhotoBaseUrl'])) {
            update_post_meta($post_id, '_gphoto_album_cover_url', esc_url_raw($album_data['coverPhotoBaseUrl']));
            
            // Try to set as featured image if media_sideload_image exists
            if (function_exists('media_sideload_image')) {
                $cover_url = $album_data['coverPhotoBaseUrl'] . '=w800-h800';
                $tmp = download_url($cover_url);
                
                if (!is_wp_error($tmp)) {
                    $file_array = array(
                        'name' => sanitize_title($album_data['title']) . '-cover.jpg',
                        'tmp_name' => $tmp
                    );
                    
                    // Use media_handle_sideload to add it to the media library and attach it to the post
                    $thumbnail_id = media_handle_sideload($file_array, $post_id);
                    
                    if (!is_wp_error($thumbnail_id)) {
                        set_post_thumbnail($post_id, $thumbnail_id);
                        if (WP_GALLERY_LINK_DEBUG) {
                            error_log('WP Gallery Link CPT: Set featured image for album - ID ' . $thumbnail_id);
                        }
                    } else {
                        error_log('WP Gallery Link CPT: Error setting featured image - ' . $thumbnail_id->get_error_message());
                    }
                }
            }
        }
        
        return $post_id;
    }
}
