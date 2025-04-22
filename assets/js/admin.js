
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
    
    // Debug mode
    var debugMode = true;
    
    /**
     * Log function with better debugging
     */
    function debugLog(message, data) {
        if (debugMode) {
            if (data) {
                console.log('[WP Gallery Link Debug]', message, data);
            } else {
                console.log('[WP Gallery Link Debug]', message);
            }
            addLog('Debug: ' + message);
        }
    }
    
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
            debugLog('No albums to render');
            addLog('No albums found to display');
            return;
        }
        
        debugLog('Rendering ' + albums.length + ' albums', albums);
        addLog('Rendering ' + albums.length + ' albums');
        
        $.each(albums, function(index, album) {
            debugLog('Rendering album:', album);
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
        debugLog('Progress: ' + percent + '%' + (message ? ' - ' + message : ''));
        
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
        debugLog('Reset loading state');
        addLog('Starting album loading process');
    }
    
    /**
     * ALTERNATIVE: Load albums from Google Photos using direct fetch
     * This is a fallback method when the regular AJAX call isn't working
     */
    function loadAlbumsDirect() {
        // Reset state before starting
        resetLoadingState();
        
        // Show we're using alternate method
        addLog('Using alternative loading method');
        updateProgress(10, 'Initializing direct album fetch');
        
        var ajaxUrl = wpglAdmin.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php';
        var nonce = wpglAdmin.nonce || '';
        
        // Log verbose data about our request
        debugLog('Direct fetch to: ' + ajaxUrl);
        debugLog('Using nonce: ' + (nonce ? 'Available' : 'Missing!'));
        addLog('Sending direct fetch to WordPress');
        
        // First check if API is working
        updateProgress(20, 'Testing API connection');
        
        // Create form data for the request
        var formData = new FormData();
        formData.append('action', 'wpgl_test_api');
        formData.append('nonce', nonce);
        
        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            debugLog('API test raw response', response);
            
            if (!response.ok) {
                throw new Error('API test failed with status: ' + response.status);
            }
            
            return response.json();
        })
        .then(function(data) {
            debugLog('API test response data', data);
            
            if (!data.success) {
                throw new Error(data.data?.message || 'API test failed');
            }
            
            addLog('API connection test successful');
            updateProgress(40, 'API connection verified, fetching albums');
            
            // Now fetch albums
            var albumFormData = new FormData();
            albumFormData.append('action', 'wpgl_fetch_albums');
            albumFormData.append('nonce', nonce);
            
            return fetch(ajaxUrl, {
                method: 'POST',
                body: albumFormData,
                credentials: 'same-origin'
            });
        })
        .then(function(response) {
            debugLog('Album fetch raw response', response);
            updateProgress(60, 'Album data received, processing');
            
            if (!response.ok) {
                // Add the raw response text for debugging
                return response.text().then(function(text) {
                    debugLog('Raw error response', text);
                    throw new Error('Album fetch failed with status: ' + response.status);
                });
            }
            
            return response.json();
        })
        .then(function(data) {
            // Log the entire response for debugging
            debugLog('Album fetch complete response', data);
            
            if (!data.success) {
                throw new Error(data.data?.message || 'Album fetch failed');
            }
            
            updateProgress(80, 'Processing ' + (data.data?.albums?.length || 0) + ' albums');
            
            // Verify we have albums
            var albums = data.data?.albums || [];
            if (albums.length === 0) {
                addLog('No albums found in Google Photos account');
            } else {
                addLog('Successfully retrieved ' + albums.length + ' albums');
            }
            
            // Render albums
            updateProgress(100, 'Albums loaded successfully');
            renderAlbums(albums);
        })
        .catch(function(error) {
            console.error('Album fetch error:', error);
            updateProgress(100, 'Error: ' + error.message);
            addLog('ERROR: ' + error.message);
            
            // Show error in UI
            $albumsGrid.html('<div class="notice notice-error"><p>Error loading albums: ' + error.message + '</p><p>Check the browser console and server logs for more details.</p></div>');
            $albumsContainer.show();
        });
    }
    
    /**
     * Load albums from Google Photos using AJAX
     * This is the default method
     */
    $loadButton.on('click', function() {
        debugLog('Load albums button clicked');
        
        // Use our direct fetch method instead of the standard AJAX
        loadAlbumsDirect();
        
        // Add analytics event if available
        if (typeof _paq !== 'undefined') {
            _paq.push(['trackEvent', 'Albums', 'Load', 'Google Photos']);
        }
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
        
        debugLog('Importing album:', albumId);
        addLog('Importing album ID: ' + albumId);
        
        // Find album data
        var title = $albumElement.find('.wpgl-album-title').text();
        
        // Use fetch for import as well
        var formData = new FormData();
        formData.append('action', 'wpgl_import_album');
        formData.append('nonce', wpglAdmin.nonce);
        formData.append('album_id', albumId);
        
        fetch(wpglAdmin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            debugLog('Import raw response', response);
            
            if (!response.ok) {
                return response.text().then(function(text) {
                    debugLog('Raw import error response', text);
                    throw new Error('Import failed with status: ' + response.status);
                });
            }
            
            return response.json();
        })
        .then(function(response) {
            debugLog('Import response:', response);
            if (response.success) {
                addLog('Album "' + title + '" imported successfully');
                $button.text(wpglAdmin.i18n.imported)
                       .removeClass('button-primary')
                       .addClass('button-disabled');
                
                if (response.data && response.data.edit_url) {
                    var $editLink = $('<a href="' + response.data.edit_url + '" class="button button-small">Edit</a>');
                    $button.after(' ').after($editLink);
                }
            } else {
                $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                var errorMsg = wpglAdmin.i18n.error + ' ' + (response.data && response.data.message ? response.data.message : 'Unknown error');
                console.error(errorMsg);
                addLog(errorMsg);
                alert(errorMsg);
            }
        })
        .catch(function(error) {
            console.error('Import Error:', error);
            $button.prop('disabled', false).text(wpglAdmin.i18n.import);
            
            var errorMsg = wpglAdmin.i18n.error + ' ' + error.message;
            addLog(errorMsg);
            alert(errorMsg);
        });
    });

    // Test API connectivity directly on page load
    function testApiConnection() {
        if (typeof wpglAdmin !== 'undefined' && wpglAdmin.ajaxUrl) {
            addLog('Testing API connection...');
            
            var formData = new FormData();
            formData.append('action', 'wpgl_test_api');
            formData.append('nonce', wpglAdmin.nonce);
            
            fetch(wpglAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                if (response.success) {
                    addLog('API connection test: SUCCESS');
                    addLog('API connection is working. You can now load albums.');
                } else {
                    addLog('API connection test: FAILED');
                    addLog('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
            })
            .catch(function(error) {
                addLog('API connection test: ERROR');
                addLog('API connection failed: ' + error.message);
            });
        }
    }
    
    // For auto-loading albums after a short delay
    if (window.location.href.indexOf('page=wp-gallery-link-import') > -1) {
        debugLog('On import page, running API test');
        setTimeout(function() {
            testApiConnection();
            
            setTimeout(function() {
                debugLog('Auto-clicking load button');
                addLog('Auto-loading albums');
                $loadButton.trigger('click');
            }, 1000);
        }, 500);
    }
});
