
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
            console.log('Template not found: ' + id);
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
            console.log('No albums to render');
            addLog('No albums found to display');
            return;
        }
        
        console.log('Rendering ' + albums.length + ' albums', albums);
        addLog('Rendering ' + albums.length + ' albums');
        
        $.each(albums, function(index, album) {
            console.log('Rendering album:', album);
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
        console.log('Progress: ' + percent + '%' + (message ? ' - ' + message : ''));
        
        if (message) {
            addLog(message);
        }
    }
    
    /**
     * Add a log message
     */
    function addLog(message) {
        var time = new Date().toLocaleTimeString();
        var logEntry = '[' + time + '] ' + message;
        console.log(logEntry);
        $loadingLog.append('<div class="wpgl-log-entry">' + logEntry + '</div>');
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
        console.log('Reset loading state');
        addLog('Starting album loading process');
    }
    
    /**
     * Load albums from Google Photos
     */
    $loadButton.on('click', function() {
        console.log('Load albums button clicked');
        resetLoadingState();
        
        // Update progress
        updateProgress(10, wpglAdmin.i18n.loading);
        
        // Debug info
        console.log('AJAX URL:', wpglAdmin.ajaxUrl);
        console.log('Nonce:', wpglAdmin.nonce);
        addLog('Sending AJAX request to WordPress');
        
        // Make AJAX request
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wpgl_fetch_albums',
                nonce: wpglAdmin.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                updateProgress(100, 'Albums loaded successfully');
                
                if (response.success && response.data && response.data.albums) {
                    addLog('Albums received: ' + (response.data.albums ? response.data.albums.length : 0));
                    renderAlbums(response.data.albums);
                } else {
                    var errorMsg = 'Error: ' + (response.data ? response.data.message : 'Unknown error');
                    console.error(errorMsg);
                    addLog(errorMsg);
                    $albumsContainer.hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                updateProgress(100, 'Error loading albums');
                var errorDetails = '';
                
                try {
                    if (xhr.responseText) {
                        var jsonResponse = JSON.parse(xhr.responseText);
                        errorDetails = jsonResponse.message || xhr.responseText;
                    } else {
                        errorDetails = error || 'Server error';
                    }
                } catch (e) {
                    errorDetails = xhr.responseText || error || 'Server error';
                }
                
                console.error('Error details:', errorDetails);
                addLog(wpglAdmin.i18n.error + ' ' + errorDetails);
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
        
        console.log('Importing album:', albumId);
        addLog('Importing album ID: ' + albumId);
        
        // Find album data
        var title = $albumElement.find('.wpgl-album-title').text();
        var albumData = {
            id: albumId,
            title: title,
            coverPhotoBaseUrl: $albumElement.find('.wpgl-album-thumbnail img').attr('src'),
            mediaItemsCount: $albumElement.find('.wpgl-album-count').text().split(' ')[0]
        };
        
        console.log('Album data:', albumData);
        
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
                console.log('Import response:', response);
                if (response.success) {
                    addLog('Album "' + title + '" imported successfully');
                    $button.text(wpglAdmin.i18n.imported)
                           .removeClass('button-primary')
                           .addClass('button-disabled');
                    
                    if (response.data.edit_url) {
                        var $editLink = $('<a href="' + response.data.edit_url + '" class="button button-small">Edit</a>');
                        $button.after(' ').after($editLink);
                    }
                } else {
                    $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                    var errorMsg = wpglAdmin.i18n.error + ' ' + (response.data ? response.data.message : 'Unknown error');
                    console.error(errorMsg);
                    addLog(errorMsg);
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('Import AJAX Error:', {xhr: xhr, status: status, error: error});
                $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                var errorMsg = wpglAdmin.i18n.error + ' ' + (xhr.responseText || 'Server error');
                addLog(errorMsg);
                alert(errorMsg);
            }
        });
    });

    // For demo purposes - auto-load albums after a short delay
    if (window.location.href.indexOf('page=wp-gallery-link-import') > -1) {
        console.log('On import page, auto-loading albums');
        setTimeout(function() {
            // Create demo albums if they don't exist
            if ($albumsGrid.find('.wpgl-album').length === 0) {
                console.log('No existing albums found, preparing demo albums');
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
                
                console.log('Auto-clicking load button');
                addLog('Auto-loading albums in demo mode');
                $loadButton.trigger('click');
                
                // Simulate loading
                var progress = 0;
                var loadingInterval = setInterval(function() {
                    progress += 10;
                    updateProgress(progress, 'Loading albums... ' + progress + '%');
                    
                    if (progress >= 100) {
                        clearInterval(loadingInterval);
                        console.log('Demo albums:', demoAlbums);
                        renderAlbums(demoAlbums);
                        addLog('Demo albums loaded successfully');
                    }
                }, 300);
            }
        }, 1000);
    }
});
