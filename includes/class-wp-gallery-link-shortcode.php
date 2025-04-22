<?php
/**
 * Shortcode functionality
 */
class WP_Gallery_Link_Shortcode {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('wp_gallery_link', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-gallery-link-frontend',
            WP_GALLERY_LINK_URL . 'assets/css/frontend.css',
            array(),
            WP_GALLERY_LINK_VERSION
        );

        // Add inline script for detecting narrow containers
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                function checkAlbumWidth() {
                    $(".wpgl-album-inner").each(function() {
                        if ($(this).width() < 300) {
                            $(this).addClass("wpgl-narrow-container");
                        } else {
                            $(this).removeClass("wpgl-narrow-container");
                        }
                    });
                }
                
                // Check on page load
                checkAlbumWidth();
                
                // Check on window resize
                $(window).on("resize", function() {
                    checkAlbumWidth();
                });
            });
        ');
    }
    
    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'category' => '',
                'orderby' => 'custom',
                'order' => 'asc',
                'limit' => -1,
                'columns' => get_option('wpgl_shortcode_columns', 3),
                'show_categories' => 'true',
            ),
            $atts,
            'wp_gallery_link'
        );
        
        $show_categories = filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN);
        
        // Start output buffering
        ob_start();
        
        $query_args = array(
            'post_type' => 'gphoto_album',
            'posts_per_page' => $atts['limit'],
            'post_status' => 'publish'
        );
        
        // Add category filter
        if (!empty($atts['category'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'album_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }
        
        // Add ordering
        switch ($atts['orderby']) {
            case 'title':
                $query_args['orderby'] = 'title';
                $query_args['order'] = $atts['order'];
                break;
                
            case 'date':
                $query_args['orderby'] = 'meta_value';
                $query_args['meta_key'] = '_gphoto_album_date';
                $query_args['order'] = $atts['order'];
                break;
                
            case 'random':
                $query_args['orderby'] = 'rand';
                break;
                
            case 'custom':
            default:
                // Include all posts regardless of whether they have the custom order field
                // No meta_query needed - we'll just order by the meta value with a fallback to title
                $query_args['orderby'] = array(
                    'meta_value_num' => $atts['order'],
                    'title' => 'ASC' // Secondary sort by title for posts without order or with same order
                );
                $query_args['meta_key'] = '_gphoto_album_order';
                break;
        }
        
        $albums = new WP_Query($query_args);
        
        if ($albums->have_posts()) {
            $columns_class = 'wpgl-columns-' . intval($atts['columns']);
            ?>
            <div class="wp-gallery-link-container">
                <div class="wpgl-album-grid <?php echo esc_attr($columns_class); ?>">
                    <?php while ($albums->have_posts()): $albums->the_post(); ?>
                        <?php
                        $album_url = get_post_meta(get_the_ID(), '_gphoto_album_url', true);
                        $album_date = get_post_meta(get_the_ID(), '_gphoto_album_date', true);
                        $photo_count = get_post_meta(get_the_ID(), '_gphoto_photo_count', true);
                        ?>
                        <div class="wpgl-album">
                            <div class="wpgl-album-inner">
                                <a href="<?php echo esc_url($album_url ? $album_url : get_permalink()); ?>" class="wpgl-album-link" target="_blank">
                                    <div class="wpgl-album-thumbnail">
                                        <?php if (has_post_thumbnail()): ?>
                                            <?php the_post_thumbnail('medium'); ?>
                                        <?php else: ?>
                                            <div class="wpgl-no-thumbnail">
                                                <span class="dashicons dashicons-format-gallery"></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="wpgl-album-content">
                                        <h3 class="wpgl-album-title"><?php the_title(); ?></h3>
                                        
                                        <div class="wpgl-album-meta">
                                            <?php if ($photo_count): ?>
                                                <span class="wpgl-album-count"><?php echo intval($photo_count); ?> <?php _e('photos', 'wp-gallery-link'); ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($album_date): ?>
                                                <span class="wpgl-album-date">
                                                    <?php echo date_i18n(get_option('date_format'), strtotime($album_date)); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php
                                        if ($show_categories) {
                                            $categories = get_the_terms(get_the_ID(), 'album_category');
                                            if ($categories && !is_wp_error($categories)):
                                        ?>
                                            <div class="wpgl-album-categories">
                                                <?php
                                                $category_names = array();
                                                foreach ($categories as $category) {
                                                    $category_names[] = $category->name;
                                                }
                                                echo esc_html(implode(', ', $category_names));
                                                ?>
                                            </div>
                                        <?php 
                                            endif;
                                        } // show_categories
                                        ?>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php
            wp_reset_postdata();
        } else {
            ?>
            <div class="wp-gallery-link-container">
                <p class="wpgl-no-albums"><?php _e('No albums found.', 'wp-gallery-link'); ?></p>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
}
