
/**
 * Admin JavaScript for WP Gallery Link
 */
(function($) {
    'use strict';
    
    // Variables
    var albums = [];
    var pageToken = '';
    var isLoading = false;
    
    // DOM elements
    var $albumsContainer = $('#wpgl-albums-container');
    var $loadingIndicator = $('#wpgl-albums-loading');
    var $loadMoreButton = $('#wpgl-load-more');
    
    /**
     * Initialize the admin JavaScript
     */
    function init() {
        // Only run on the import page
        if (!$albumsContainer.length) {
            return;
        }
        
        // Load albums
        loadAlbums();
        
        // Event handlers
        $loadMoreButton.on('click', loadAlbums);
        
        // Delegate events for dynamically created elements
        $albumsContainer.on('click', '.wpgl-import-album', importAlbum);
    }
    
    /**
     * Load albums from Google Photos API
     */
    function loadAlbums() {
        if (isLoading) {
            return;
        }
        
        isLoading = true;
        $loadingIndicator.show();
        $loadMoreButton.hide();
        
        $.ajax({
            url: wpgl_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpgl_fetch_albums',
                page_token: pageToken,
                nonce: wpgl_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Store the next page token
                    pageToken = data.nextPageToken || '';
                    
                    // Render albums
                    if (data.albums && data.albums.length > 0) {
                        renderAlbums(data.albums);
                    }
                    
                    // Show/hide load more button
                    if (pageToken) {
                        $loadMoreButton.show();
                    }
                } else {
                    showError(response.data || wpgl_vars.i18n.error_loading);
                }
            },
            error: function(xhr, status, error) {
                showError(wpgl_vars.i18n.error_loading + ' ' + error);
            },
            complete: function() {
                isLoading = false;
                $loadingIndicator.hide();
            }
        });
    }
    
    /**
     * Render albums on the page
     * 
     * @param {Array} newAlbums Albums to render
     */
    function renderAlbums(newAlbums) {
        var template = wp.template('wpgl-album-item');
        
        // Check for existing albums
        checkExistingAlbums(newAlbums).then(function(checkedAlbums) {
            // Store albums
            albums = albums.concat(checkedAlbums);
            
            // Render albums
            var html = '';
            
            $.each(checkedAlbums, function(i, album) {
                html += template(album);
            });
            
            if ($albumsContainer.children().length === 0) {
                $albumsContainer.html(html);
            } else {
                $albumsContainer.append(html);
            }
        });
    }
    
    /**
     * Check if albums already exist in WordPress
     * 
     * @param {Array} newAlbums Albums to check
     * @return {Promise} Promise resolving with checked albums
     */
    function checkExistingAlbums(newAlbums) {
        return new Promise(function(resolve) {
            // In a real implementation, we would check the database
            // For this demo, we'll just resolve with the albums
            resolve(newAlbums);
        });
    }
    
    /**
     * Import an album
     */
    function importAlbum() {
        var $button = $(this);
        var albumId = $button.data('id');
        var $albumItem = $button.closest('.wpgl-album-item');
        
        // Disable the button and show loading
        $button.prop('disabled', true).addClass('wpgl-importing');
        $button.html('<span class="spinner is-active"></span>' + wpgl_vars.i18n.importing);
        
        $.ajax({
            url: wpgl_vars.ajaxurl,
            type: 'POST',
            data: {
                action: 'wpgl_import_album',
                album_id: albumId,
                nonce: wpgl_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the button to show success
                    $button.removeClass('wpgl-importing button-primary')
                        .addClass('button-secondary')
                        .html(wpgl_vars.i18n.import_success)
                        .delay(1500)
                        .queue(function(next) {
                            // Replace with edit/view buttons
                            var html = '<a href="' + response.data.edit_link + '" class="button button-secondary">' +
                                       '<?php _e("Edit", "wp-gallery-link"); ?></a> ' +
                                       '<a href="' + response.data.view_link + '" class="button button-secondary" target="_blank">' +
                                       '<?php _e("View", "wp-gallery-link"); ?></a>';
                            
                            $(this).replaceWith(html);
                            next();
                        });
                    
                    // Update album data
                    for (var i = 0; i < albums.length; i++) {
                        if (albums[i].id === albumId) {
                            albums[i].imported = true;
                            albums[i].editLink = response.data.edit_link;
                            albums[i].viewLink = response.data.view_link;
                            break;
                        }
                    }
                } else {
                    showError(response.data || wpgl_vars.i18n.import_error);
                    
                    // Reset the button
                    $button.prop('disabled', false).removeClass('wpgl-importing');
                    $button.html('<?php _e("Import", "wp-gallery-link"); ?>');
                }
            },
            error: function(xhr, status, error) {
                showError(wpgl_vars.i18n.import_error + ' ' + error);
                
                // Reset the button
                $button.prop('disabled', false).removeClass('wpgl-importing');
                $button.html('<?php _e("Import", "wp-gallery-link"); ?>');
            }
        });
    }
    
    /**
     * Show an error message
     * 
     * @param {string} message Error message
     */
    function showError(message) {
        // Create error notice
        var $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        
        // Insert at the top of the page
        $('.wrap > h1').after($notice);
        
        // Initialize WordPress dismissible notices
        if (typeof wp !== 'undefined' && wp.notices && wp.notices.render) {
            wp.notices.render();
        }
    }
    
    // Initialize on DOM ready
    $(document).ready(init);
    
})(jQuery);
