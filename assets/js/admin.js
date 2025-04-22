
/**
 * WP Gallery Link Admin JavaScript
 */
jQuery(document).ready(function($) {
    // Album loading functionality
    var $loadButton = $('.wpgl-load-albums');
    var $loadingContainer = $('.wpgl-loading-container');
    var $albumsContainer = $('.wpgl-albums-container');
    var $albumsGrid = $('.wpgl-albums-grid');
    var $loadingText = $('.wpgl-loading-text');
    var $progressBar = $('.wpgl-progress-value');
    var $loadingLog = $('.wpgl-loading-log');
    
    /**
     * Custom template rendering for environments where wp.template might not be available
     * (e.g., in the simulated demo environment)
     */
    function renderTemplate(id, data) {
        // Try to use WordPress template function if available
        if (typeof wp !== 'undefined' && typeof wp.template === 'function') {
            var template = wp.template(id);
            return template(data);
        }
        
        // Simple fallback for templates (minimal implementation)
        var templateElement = $('#tmpl-' + id);
        if (templateElement.length === 0) {
            return ''; // Template not found
        }
        
        var html = templateElement.html();
        
        // Very simple template parsing
        html = html.replace(/\{\{ data\.([^\}]+) \}\}/g, function(match, key) {
            var value = data[key] || '';
            return value;
        });
        
        // Handle conditionals (very basic)
        html = html.replace(/<# if \(data\.([^\)]+)\) { #>([\s\S]*?)<# } #>/g, function(match, condition, content) {
            var parts = condition.split('.');
            var value = data[parts[0]];
            
            // Handle nested paths
            if (parts.length > 1) {
                for (var i = 1; i < parts.length; i++) {
                    if (value !== undefined && value !== null) {
                        value = value[parts[i]];
                    }
                }
            }
            
            return value ? content : '';
        });
        
        // Handle else conditionals
        html = html.replace(/<# if \(data\.([^\)]+)\) { #>([\s\S]*?)<# } else { #>([\s\S]*?)<# } #>/g, function(match, condition, ifContent, elseContent) {
            var parts = condition.split('.');
            var value = data[parts[0]];
            
            // Handle nested paths
            if (parts.length > 1) {
                for (var i = 1; i < parts.length; i++) {
                    if (value !== undefined && value !== null) {
                        value = value[parts[i]];
                    }
                }
            }
            
            return value ? ifContent : elseContent;
        });
        
        return html;
    }
    
    /**
     * Render albums
     */
    function renderAlbums(albums) {
        $albumsGrid.empty();
        
        if (albums.length === 0) {
            $albumsGrid.append('<div class="notice notice-info"><p>' + wpglAdmin.i18n.noAlbums + '</p></div>');
            return;
        }
        
        $.each(albums, function(index, album) {
            var html = renderTemplate('wpgl-album', album);
            $albumsGrid.append(html);
        });
        
        // Show albums container
        $albumsContainer.show();
    }
    
    /**
     * Update loading progress
     */
    function updateProgress(percent, message) {
        $progressBar.css('width', percent + '%');
        
        if (message) {
            addLog(message);
        }
    }
    
    /**
     * Add a log message
     */
    function addLog(message) {
        var time = new Date().toLocaleTimeString();
        $loadingLog.append('<div class="wpgl-log-entry">[' + time + '] ' + message + '</div>');
        $loadingLog.scrollTop($loadingLog[0].scrollHeight);
    }
    
    /**
     * Reset loading state
     */
    function resetLoadingState() {
        $progressBar.css('width', '0%');
        $loadingLog.empty();
        $albumsContainer.hide();
        $loadingText.text(wpglAdmin.i18n.loading);
    }
    
    /**
     * Load albums from Google Photos
     */
    $loadButton.on('click', function() {
        resetLoadingState();
        
        // Update progress
        updateProgress(10, wpglAdmin.i18n.loading);
        
        // Make AJAX request
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpgl_fetch_albums',
                nonce: wpglAdmin.nonce
            },
            success: function(response) {
                updateProgress(100, 'Albums loaded successfully');
                
                if (response.success && response.data.albums) {
                    renderAlbums(response.data.albums);
                } else {
                    addLog(wpglAdmin.i18n.error + ' ' + (response.data ? response.data.message : 'Unknown error'));
                    $albumsContainer.hide();
                }
            },
            error: function(xhr) {
                updateProgress(100, 'Error loading albums');
                addLog(wpglAdmin.i18n.error + ' ' + xhr.responseText || 'Server error');
                $albumsContainer.hide();
            }
        });
    });
    
    /**
     * Import album
     */
    $(document).on('click', '.wpgl-import-album', function() {
        var $button = $(this);
        var albumId = $button.data('id');
        var $albumElement = $('.wpgl-album[data-id="' + albumId + '"]');
        
        // Disable button
        $button.prop('disabled', true).text(wpglAdmin.i18n.importing);
        
        // Find album data
        var title = $albumElement.find('.wpgl-album-title').text();
        var albumData = {
            id: albumId,
            title: title,
            coverPhotoBaseUrl: $albumElement.find('.wpgl-album-thumbnail img').attr('src'),
            mediaItemsCount: $albumElement.find('.wpgl-album-count').text().split(' ')[0]
        };
        
        // Make AJAX request to import
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpgl_import_album',
                nonce: wpglAdmin.importNonce,
                album: albumData
            },
            success: function(response) {
                if (response.success) {
                    $button.text(wpglAdmin.i18n.imported)
                           .removeClass('button-primary')
                           .addClass('button-disabled');
                    
                    if (response.data.edit_url) {
                        var $editLink = $('<a href="' + response.data.edit_url + '" class="button button-small">Edit</a>');
                        $button.after(' ').after($editLink);
                    }
                } else {
                    $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                    alert(wpglAdmin.i18n.error + ' ' + (response.data ? response.data.message : 'Unknown error'));
                }
            },
            error: function() {
                $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                alert(wpglAdmin.i18n.error + ' Server error');
            }
        });
    });

    // For demo purposes - auto-load albums after a short delay
    if (window.location.href.indexOf('page=wp-gallery-link-import') > -1) {
        setTimeout(function() {
            // Create demo albums if they don't exist
            if ($albumsGrid.find('.wpgl-album').length === 0) {
                var demoAlbums = [
                    {
                        id: 'demo1',
                        title: 'Summer Vacation',
                        coverPhotoBaseUrl: WP_GALLERY_LINK_URL + 'assets/images/default-album.png',
                        mediaItemsCount: '42'
                    },
                    {
                        id: 'demo2',
                        title: 'Family Gathering',
                        coverPhotoBaseUrl: WP_GALLERY_LINK_URL + 'assets/images/default-album.png',
                        mediaItemsCount: '78'
                    },
                    {
                        id: 'demo3',
                        title: 'Nature Photography',
                        coverPhotoBaseUrl: WP_GALLERY_LINK_URL + 'assets/images/default-album.png',
                        mediaItemsCount: '53'
                    }
                ];
                
                $loadButton.trigger('click');
                
                // Simulate loading
                var progress = 0;
                var loadingInterval = setInterval(function() {
                    progress += 10;
                    updateProgress(progress, 'Loading albums... ' + progress + '%');
                    
                    if (progress >= 100) {
                        clearInterval(loadingInterval);
                        renderAlbums(demoAlbums);
                        addLog('Albums loaded successfully');
                    }
                }, 300);
            }
        }, 1000);
    }
});
