
jQuery(document).ready(function($) {
    'use strict';
    
    // Debug mode for diagnostic information
    const DEBUG = typeof wpglAdmin !== 'undefined' && wpglAdmin.debugMode === true;
    
    // Global variables for album loading
    let isLoading = false;
    let cancelLoading = false;
    let albumsFound = [];
    let nextPageToken = '';
    let totalAlbums = 0;
    
    /**
     * Log to console if in debug mode
     */
    function logDebug(message, data) {
        if (DEBUG) {
            if (data) {
                console.log('WP Gallery Link:', message, data);
            } else {
                console.log('WP Gallery Link:', message);
            }
        }
    }
    
    /**
     * Add a message to the loading log
     */
    function addLoadingLog(message) {
        const $log = $('.wpgl-loading-log');
        if ($log.length) {
            const timestamp = new Date().toLocaleTimeString();
            $log.append(`<div>[${timestamp}] ${message}</div>`);
            $log.scrollTop($log[0].scrollHeight);
        }
        logDebug(message);
    }

    /**
     * Update the progress bar
     */
    function updateProgress(percent) {
        $('.wpgl-progress-value').css('width', percent + '%');
    }
    
    /**
     * Update the loading status text
     */
    function updateLoadingStatus(text) {
        $('.wpgl-loading-text').text(text);
    }
    
    /**
     * Add an album title to the list of found albums
     */
    function addAlbumToList(title) {
        const $list = $('#wpgl-albums-title-list');
        if ($list.length) {
            $list.append(`<li>${title}</li>`);
        }
    }
    
    /**
     * Render an album in the grid
     */
    function renderAlbum(album) {
        const $albumsGrid = $('.wpgl-albums-grid');
        
        if ($albumsGrid.length) {
            // Create a default cover image if one isn't provided
            const coverImageUrl = album.coverPhotoBaseUrl || 'https://via.placeholder.com/200x200?text=No+Cover';
            
            // Create album HTML directly without using a template
            const albumHtml = `
                <div class="wpgl-album" data-id="${album.id}">
                    <div class="wpgl-album-cover-container">
                        <img src="${coverImageUrl}" alt="${album.title}" class="wpgl-album-cover">
                    </div>
                    <div class="wpgl-album-info">
                        <h3 class="wpgl-album-title">${album.title}</h3>
                        <div class="wpgl-album-meta">
                            ${album.mediaItemsCount ? `<span class="wpgl-album-count">${album.mediaItemsCount} photos</span>` : ''}
                        </div>
                        <div class="wpgl-album-actions">
                            <button class="button button-primary wpgl-import-album" data-id="${album.id}">Import</button>
                        </div>
                    </div>
                </div>
            `;
            
            $albumsGrid.append(albumHtml);
            logDebug('Album rendered:', album.title);
        } else {
            logDebug('ERROR: Album grid container not found!');
        }
    }
    
    /**
     * Show loading UI elements
     */
    function showLoadingUI() {
        $('.wpgl-loading-container').show();
        $('.wpgl-loading-log-container').show();
        $('.wpgl-albums-log-container').show();
        $('#wpgl-start-loading').hide();
        $('#wpgl-stop-loading').show();
    }
    
    /**
     * Hide loading UI elements
     */
    function hideLoadingUI() {
        $('.wpgl-loading-container').hide();
        $('#wpgl-stop-loading').hide();
        $('#wpgl-start-loading').show();
    }
    
    /**
     * Reset album loading state
     */
    function resetAlbumLoading() {
        isLoading = false;
        cancelLoading = false;
        albumsFound = [];
        nextPageToken = '';
        totalAlbums = 0;
        
        // Clear UI elements
        $('.wpgl-loading-log').empty();
        $('#wpgl-albums-title-list').empty();
        $('.wpgl-albums-grid').empty(); // Clear the albums grid
        updateProgress(0);
    }
    
    /**
     * Load albums from the API
     */
    function loadAlbums() {
        if (cancelLoading) {
            addLoadingLog('Album loading canceled by user.');
            hideLoadingUI();
            return;
        }
        
        const loadAllAlbums = typeof wpglAdmin !== 'undefined' && wpglAdmin.loadAllAlbums === true;
        
        if (isLoading) {
            logDebug('Already loading albums, skipping duplicate request');
            return;
        }
        
        isLoading = true;
        showLoadingUI();
        
        const data = {
            action: 'wpgl_fetch_albums',
            nonce: wpglAdmin ? wpglAdmin.nonce : '' // Use the nonce from wpglAdmin if available
        };
        
        if (nextPageToken) {
            data.pageToken = nextPageToken;
        }
        
        addLoadingLog('Fetching albums from Google Photos API...');
        updateLoadingStatus('Fetching albums...');
        updateProgress(10);
        
        // Log the AJAX URL and request data for debugging
        logDebug('AJAX request data:', data);
        
        if (typeof wpglAdmin === 'undefined' || !wpglAdmin.ajaxUrl) {
            logDebug('ERROR: wpglAdmin.ajaxUrl is not defined!');
            addLoadingLog('Error: WordPress AJAX URL not found. Is the plugin properly loaded?');
            isLoading = false;
            hideLoadingUI();
            return;
        }
        
        logDebug('AJAX URL:', wpglAdmin.ajaxUrl);
        
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                logDebug('Albums API response:', response);
                
                if (response.success) {
                    const albums = response.data.albums || [];
                    nextPageToken = response.data.nextPageToken || '';
                    
                    addLoadingLog(`Found ${albums.length} albums in this batch.`);
                    
                    totalAlbums += albums.length;
                    updateProgress(50);
                    
                    // Show the albums container
                    $('.wpgl-albums-container').show();
                    
                    // Render each album
                    albums.forEach(function(album) {
                        albumsFound.push(album);
                        addAlbumToList(album.title);
                        renderAlbum(album);
                        logDebug('Album processed:', album.title);
                    });
                    
                    updateProgress(75);
                    
                    // If there's a next page token and we're loading all albums, continue loading
                    if (nextPageToken && loadAllAlbums) {
                        addLoadingLog('Loading next batch of albums...');
                        setTimeout(function() {
                            loadAlbums();
                        }, 1000);
                    } else {
                        addLoadingLog(`Finished loading ${totalAlbums} albums.`);
                        updateProgress(100);
                        isLoading = false;
                        
                        if (!nextPageToken) {
                            addLoadingLog('No more albums available.');
                        } else if (!loadAllAlbums) {
                            addLoadingLog('Limited album loading completed. Click "Load Albums" again for more.');
                        }
                        
                        hideLoadingUI();
                    }
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    addLoadingLog(`Error: ${errorMsg}`);
                    isLoading = false;
                    hideLoadingUI();
                    updateProgress(0);
                    
                    logDebug('API Error:', response);
                }
            },
            error: function(xhr, status, error) {
                addLoadingLog(`AJAX Error: ${error}`);
                logDebug('AJAX Error Details:', { xhr: xhr, status: status, error: error });
                
                // Try to get more detailed error information
                let errorDetails = 'No additional details available.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.data && response.data.message) {
                        errorDetails = response.data.message;
                    }
                } catch (e) {
                    errorDetails = xhr.responseText || 'Could not parse error response.';
                }
                
                addLoadingLog(`Error details: ${errorDetails}`);
                
                isLoading = false;
                hideLoadingUI();
                updateProgress(0);
            },
            complete: function() {
                logDebug('Album loading AJAX request completed');
            }
        });
    }
    
    /**
     * Handle album import
     */
    $(document).on('click', '.wpgl-import-album', function() {
        const $button = $(this);
        const albumId = $button.data('id');
        
        if (!albumId) {
            logDebug('No album ID found for import button');
            return;
        }
        
        $button.prop('disabled', true).text(wpglAdmin.i18n.importing);
        
        logDebug('Importing album with ID:', albumId);
        
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpgl_import_album',
                album_id: albumId,
                nonce: wpglAdmin.nonce // Use the nonce from wpglAdmin
            },
            success: function(response) {
                logDebug('Album import response:', response);
                
                if (response.success) {
                    // Success UI updates - show message and button changes
                    $button.removeClass('button-primary')
                          .addClass('button-secondary')
                          .text(wpglAdmin.i18n.imported);
                    
                    // Show the album ID and info in the console for debugging
                    console.log('Successfully imported album:', albumId, response);
                    
                    // Redirect to the edit page for the newly created album post
                    if (response.data && response.data.post_id) {
                        // Add a short delay before redirect to show the success message
                        setTimeout(function() {
                            window.location.href = response.data.edit_url || 
                                `post.php?post=${response.data.post_id}&action=edit`;
                        }, 1000);
                    } else {
                        // If no post ID in response, refresh the album list page
                        setTimeout(function() {
                            window.location.href = 'edit.php?post_type=gphoto_album';
                        }, 1000);
                    }
                    
                    // Show success message
                    alert(wpglAdmin.i18n.import_success);
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    alert(`${wpglAdmin.i18n.import_error} ${errorMsg}`);
                    $button.prop('disabled', false).text(wpglAdmin.i18n.import);
                }
            },
            error: function(xhr, status, error) {
                logDebug('Album import error:', { xhr: xhr, status: status, error: error });
                alert(`${wpglAdmin.i18n.import_error} ${error}`);
                $button.prop('disabled', false).text(wpglAdmin.i18n.import);
            }
        });
    });
    
    // Start loading albums button
    $('#wpgl-start-loading').on('click', function() {
        logDebug('Start loading button clicked');
        resetAlbumLoading();
        loadAlbums();
    });
    
    // Alternative click handler for the start button (in case of event binding issues)
    document.getElementById('wpgl-start-loading')?.addEventListener('click', function() {
        logDebug('Start loading button clicked (via addEventListener)');
        resetAlbumLoading();
        loadAlbums();
    });
    
    // Stop loading albums button
    $('#wpgl-stop-loading').on('click', function() {
        cancelLoading = true;
        addLoadingLog('Stopping album loading process...');
    });
    
    // Debug button for demo loading
    $('.wpgl-demo-mode a').on('click', function(e) {
        logDebug('Demo mode requested');
        if (!e.ctrlKey) {
            logDebug('Demo mode active - showing sample albums');
            // Let the link work normally to load demo albums
        }
    });
    
    // Run diagnostic check on page load
    function runDiagnostic() {
        logDebug('Running diagnostic check...');
        
        // Debug information for templates
        logDebug('Checking for album template elements');
        if ($('.wpgl-album-template').length) {
            logDebug('Found album template element');
        } else {
            logDebug('No album template element found, will use direct HTML rendering');
        }
        
        // Check if we have the wpglAdmin object
        if (typeof wpglAdmin === 'undefined') {
            console.error('WP Gallery Link: wpglAdmin object not found. Script localization might have failed.');
            return;
        }
        
        // Check if we can find the start button
        const startBtn = $('#wpgl-start-loading');
        if (startBtn.length === 0) {
            console.error('WP Gallery Link: Start button not found in DOM.');
        } else {
            logDebug('Start button found with ID:', startBtn.attr('id'));
        }
        
        // Check if the album grid container exists
        const albumsGrid = $('.wpgl-albums-grid');
        if (albumsGrid.length === 0) {
            console.error('WP Gallery Link: Albums grid container not found in DOM.');
        } else {
            logDebug('Albums grid found with class:', albumsGrid.attr('class'));
        }
        
        logDebug('Diagnostic completed');
    }
    
    // Run diagnostic on page load if in debug mode
    if (DEBUG) {
        runDiagnostic();
        // Add immediate diagnostic info to console
        console.log('WP Gallery Link: Script loaded and ready');
        console.log('WP Gallery Link: Debug mode is active');
        if (typeof wpglAdmin !== 'undefined') {
            console.log('WP Gallery Link: wpglAdmin object is available', wpglAdmin);
        } else {
            console.error('WP Gallery Link: wpglAdmin object is not available!');
        }
    }
    
    // Explicitly trigger rendering of demo albums for testing
    if (typeof wpglAdmin !== 'undefined' && wpglAdmin.demoMode === true) {
        logDebug('Demo mode is active, will load sample albums automatically');
        setTimeout(function() {
            if ($('#wpgl-start-loading').length) {
                $('#wpgl-start-loading').trigger('click');
            } else {
                logDebug('Could not find start loading button to auto-trigger');
                // Try to manually start loading
                resetAlbumLoading();
                loadAlbums();
            }
        }, 500);
    }
});
