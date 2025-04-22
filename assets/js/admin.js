
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
    let processedPageTokens = []; // Track processed page tokens to prevent duplicates
    let loadedAlbumIds = new Set(); // Track album IDs to prevent duplicates
    
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
    
    // Immediately log that the script has loaded
    logDebug('Admin script initialized');
    console.log('WP Gallery Link admin.js loaded successfully');
    console.log('Checking for wpglAdmin object:', typeof wpglAdmin !== 'undefined' ? 'Available' : 'Not available');
    
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
        logDebug('Rendering album:', album);
        console.log('Rendering album with ID:', album.id, 'and title:', album.title);

        // Skip if we've already rendered this album
        if (loadedAlbumIds.has(album.id)) {
            logDebug('Skipping duplicate album ID:', album.id);
            return;
        }
        
        // Add to our set of loaded album IDs
        loadedAlbumIds.add(album.id);

        const $albumsGrid = $('.wpgl-albums-grid');
        
        if (!$albumsGrid.length) {
            console.error('WP Gallery Link: Albums grid container not found!');
            addLoadingLog('Error: Album grid container not found in the DOM.');
            return;
        }
            
        // Create a default cover image if one isn't provided
        const coverImageUrl = album.coverPhotoBaseUrl || 'https://via.placeholder.com/200x200?text=No+Cover';
        
        // Create album HTML directly
        const albumHtml = `
            <div class="wpgl-album" data-id="${album.id}">
                <div class="wpgl-album-cover-container">
                    <img src="${coverImageUrl}" alt="${album.title}" class="wpgl-album-cover">
                    <label class="wpgl-bulk-select">
                        <input type="checkbox" class="wpgl-album-checkbox" data-id="${album.id}" data-title="${album.title}">
                        <span class="wpgl-checkmark"></span>
                    </label>
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
        logDebug('Album HTML rendered successfully. Album ID:', album.id);
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
        
        // Show the bulk import controls if albums are found
        if (albumsFound.length > 0) {
            $('.wpgl-bulk-actions').show();
        }
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
        processedPageTokens = [];
        loadedAlbumIds.clear(); // Clear the set of loaded album IDs
        
        // Clear UI elements
        $('.wpgl-loading-log').empty();
        $('#wpgl-albums-title-list').empty();
        $('.wpgl-albums-grid').empty(); // Clear the albums grid
        $('.wpgl-bulk-actions').hide();
        updateProgress(0);
        
        // Remove load more button if it exists
        $('#wpgl-load-more').remove();
        $('#wpgl-import-all').remove();
    }
    
    /**
     * Load albums from the API
     */
    function loadAlbums() {
        logDebug('loadAlbums function called');
        console.log('WP Gallery Link: loadAlbums function called');
        
        if (cancelLoading) {
            addLoadingLog('Album loading canceled by user.');
            hideLoadingUI();
            return;
        }
        
        if (isLoading) {
            logDebug('Already loading albums, skipping duplicate request');
            return;
        }
        
        isLoading = true;
        showLoadingUI();
        
        // Generate a timestamp for cache busting
        const timestamp = new Date().getTime();
        
        const data = {
            action: 'wpgl_fetch_albums',
            nonce: wpglAdmin ? wpglAdmin.nonce : '',
            demo: 'false', // Always force real data
            _nocache: timestamp // Cache busting parameter
        };
        
        if (nextPageToken) {
            data.pageToken = nextPageToken;
            
            // Check if we've already processed this page token to prevent duplicates
            if (processedPageTokens.includes(nextPageToken)) {
                addLoadingLog('Warning: Page token already processed, preventing duplicate load.');
                isLoading = false;
                hideLoadingUI();
                return;
            }
            
            // Add this token to processed tokens
            processedPageTokens.push(nextPageToken);
        }
        
        addLoadingLog('Fetching albums from Google Photos API...');
        updateLoadingStatus('Fetching albums...');
        updateProgress(10);
        
        // Log all important variables for debugging
        console.log('AJAX request data:', data);
        console.log('wpglAdmin object:', wpglAdmin);
        
        if (typeof wpglAdmin === 'undefined' || !wpglAdmin.ajaxUrl) {
            console.error('WP Gallery Link: wpglAdmin.ajaxUrl is not defined!');
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
            cache: false, // Prevent caching
            success: function(response) {
                console.log('Album API response received:', response);
                
                if (response.success) {
                    const albums = response.data.albums || [];
                    nextPageToken = response.data.nextPageToken || '';
                    
                    console.log('Found', albums.length, 'albums in this batch');
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
                    });
                    
                    updateProgress(75);
                    
                    // If there's a next page token
                    if (nextPageToken) {
                        addLoadingLog('More albums available. Click "Load More" to fetch them.');
                        
                        // Show "Load More" button
                        if (!$('#wpgl-load-more').length) {
                            $('.wpgl-button-group').append(`
                                <button id="wpgl-load-more" class="button">
                                    ${wpglAdmin.i18n ? wpglAdmin.i18n.load_more : 'Load More Albums'}
                                </button>
                            `);
                        } else {
                            $('#wpgl-load-more').show();
                        }
                        
                        // Enable the load more button
                        $('#wpgl-load-more').prop('disabled', false).text(wpglAdmin.i18n ? wpglAdmin.i18n.load_more : 'Load More Albums');
                        
                    } else {
                        addLoadingLog('No more albums available.');
                        $('#wpgl-load-more').hide();
                    }
                    
                    // Add "Import All" button if it doesn't exist and we have albums
                    if (totalAlbums > 0 && !$('#wpgl-import-all').length) {
                        $('.wpgl-bulk-header').append(`
                            <button id="wpgl-import-all" class="button button-primary" style="margin-left: 10px;">
                                Import All Albums
                            </button>
                        `);
                    }
                    
                    addLoadingLog(`Finished loading ${totalAlbums} albums.`);
                    updateProgress(100);
                    isLoading = false;
                    hideLoadingUI();
                    
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    console.error('API Error:', errorMsg, response);
                    addLoadingLog(`Error: ${errorMsg}`);
                    isLoading = false;
                    hideLoadingUI();
                    updateProgress(0);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error, xhr.responseText);
                addLoadingLog(`AJAX Error: ${error}`);
                
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
            }
        });
    }
    
    /**
     * Handle album import
     */
    function handleImportAlbum(albumId) {
        logDebug('Handling import for album ID:', albumId);
        
        if (!albumId) {
            logDebug('No album ID provided');
            return;
        }
        
        // Find the button for this album
        const $button = $(`.wpgl-import-album[data-id="${albumId}"]`);
        if (!$button.length) {
            logDebug('Import button not found for album ID:', albumId);
            return;
        }
        
        // Prevent double clicks
        if ($button.prop('disabled')) {
            logDebug('Button already disabled, preventing duplicate import');
            return;
        }
        
        $button.prop('disabled', true).text(wpglAdmin.i18n ? wpglAdmin.i18n.importing : 'Importing...');
        
        logDebug('Importing album with ID:', albumId);
        console.log('WP Gallery Link: Importing album with ID:', albumId);
        
        // Add a timestamp to prevent caching
        const timestamp = new Date().getTime();
        
        $.ajax({
            url: wpglAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpgl_import_album',
                album_id: albumId,
                nonce: wpglAdmin.nonce,
                _nocache: timestamp // Cache-busting parameter
            },
            cache: false, // Prevent caching
            success: function(response) {
                logDebug('Album import response:', response);
                console.log('WP Gallery Link: Album import response:', response);
                
                if (response.success) {
                    // Success UI updates
                    $button.removeClass('button-primary')
                          .addClass('button-secondary')
                          .text(wpglAdmin.i18n ? wpglAdmin.i18n.imported : 'Imported');
                    
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
                    alert(wpglAdmin.i18n ? wpglAdmin.i18n.import_success : 'Album imported successfully!');
                } else {
                    const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                    alert(wpglAdmin.i18n ? `${wpglAdmin.i18n.import_error} ${errorMsg}` : `Import error: ${errorMsg}`);
                    $button.prop('disabled', false).text(wpglAdmin.i18n ? wpglAdmin.i18n.import : 'Import');
                    
                    console.error('Album import error:', errorMsg, response);
                }
            },
            error: function(xhr, status, error) {
                logDebug('Album import error:', { xhr: xhr, status: status, error: error });
                console.error('WP Gallery Link: AJAX error during import:', error, xhr.responseText);
                
                alert(wpglAdmin.i18n ? `${wpglAdmin.i18n.import_error} ${error}` : `Import error: ${error}`);
                $button.prop('disabled', false).text(wpglAdmin.i18n ? wpglAdmin.i18n.import : 'Import');
            }
        });
    }
    
    /**
     * Handle bulk album import
     */
    function bulkImportAlbums() {
        const selectedAlbums = [];
        $('.wpgl-album-checkbox:checked').each(function() {
            const $checkbox = $(this);
            selectedAlbums.push({
                id: $checkbox.data('id'),
                title: $checkbox.data('title')
            });
        });
        
        if (selectedAlbums.length === 0) {
            alert(wpglAdmin.i18n ? wpglAdmin.i18n.no_albums_selected : 'No albums selected. Please select at least one album.');
            return;
        }
        
        if (!confirm(wpglAdmin.i18n && wpglAdmin.i18n.confirm_bulk_import ? 
                    wpglAdmin.i18n.confirm_bulk_import.replace('%d', selectedAlbums.length) : 
                    `Are you sure you want to import ${selectedAlbums.length} selected albums?`)) {
            return;
        }
        
        // Show bulk import progress UI
        const $bulkProgress = $('.wpgl-bulk-progress');
        $bulkProgress.show();
        
        const totalToImport = selectedAlbums.length;
        let importedCount = 0;
        let failedCount = 0;
        
        addLoadingLog(`Starting bulk import of ${totalToImport} albums...`);
        
        // Function to update the bulk import progress
        function updateBulkProgress() {
            const percent = Math.round((importedCount + failedCount) / totalToImport * 100);
            $('.wpgl-bulk-progress-value').css('width', percent + '%');
            $('.wpgl-bulk-progress-text').text(`${importedCount + failedCount} of ${totalToImport} (${percent}%)`);
        }
        
        // Process albums one by one
        function processNextAlbum(index) {
            if (index >= selectedAlbums.length) {
                // All done
                addLoadingLog(`Bulk import completed: ${importedCount} imported, ${failedCount} failed.`);
                alert(wpglAdmin.i18n && wpglAdmin.i18n.bulk_import_complete ? 
                      wpglAdmin.i18n.bulk_import_complete
                        .replace('%d', importedCount)
                        .replace('%d', failedCount) : 
                      `Bulk import completed: ${importedCount} imported, ${failedCount} failed.`);
                
                // Refresh the page after a short delay
                setTimeout(function() {
                    window.location.href = 'edit.php?post_type=gphoto_album';
                }, 1000);
                return;
            }
            
            const album = selectedAlbums[index];
            addLoadingLog(`Importing album ${index+1}/${totalToImport}: "${album.title}"`);
            
            // Mark checkbox as in progress
            const $checkbox = $(`.wpgl-album-checkbox[data-id="${album.id}"]`);
            $checkbox.closest('.wpgl-album').addClass('importing');
            
            // Add timestamp for cache busting
            const timestamp = new Date().getTime();
            
            $.ajax({
                url: wpglAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpgl_import_album',
                    album_id: album.id,
                    nonce: wpglAdmin.nonce,
                    bulk: true,  // Flag to indicate this is part of bulk import
                    _nocache: timestamp // Cache-busting parameter
                },
                cache: false, // Prevent caching
                success: function(response) {
                    if (response.success) {
                        importedCount++;
                        $checkbox.closest('.wpgl-album').addClass('imported').removeClass('importing');
                        addLoadingLog(`Album "${album.title}" imported successfully.`);
                    } else {
                        failedCount++;
                        $checkbox.closest('.wpgl-album').addClass('failed').removeClass('importing');
                        const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        addLoadingLog(`Failed to import album "${album.title}": ${errorMsg}`);
                    }
                    
                    updateBulkProgress();
                    
                    // Process next album after a short delay
                    setTimeout(function() {
                        processNextAlbum(index + 1);
                    }, 500);
                },
                error: function(xhr, status, error) {
                    failedCount++;
                    $checkbox.closest('.wpgl-album').addClass('failed').removeClass('importing');
                    addLoadingLog(`Error importing album "${album.title}": ${error}`);
                    
                    updateBulkProgress();
                    
                    // Process next album after a short delay
                    setTimeout(function() {
                        processNextAlbum(index + 1);
                    }, 500);
                }
            });
        }
        
        // Start processing albums
        processNextAlbum(0);
    }
    
    // Bulk select/deselect all
    $(document).on('click', '#wpgl-select-all', function() {
        const isChecked = $(this).prop('checked');
        $('.wpgl-album-checkbox').prop('checked', isChecked);
        updateSelectedCount();
    });
    
    // Update selected count when individual checkboxes change
    $(document).on('change', '.wpgl-album-checkbox', function() {
        updateSelectedCount();
    });
    
    // Update selected count when the select all checkbox changes
    $(document).on('change', '#wpgl-select-all', function() {
        const isChecked = $(this).prop('checked');
        $('.wpgl-album-checkbox').prop('checked', isChecked);
        updateSelectedCount();
    });
    
    // Update the selected albums count
    function updateSelectedCount() {
        const count = $('.wpgl-album-checkbox:checked').length;
        $('.wpgl-selected-count').text(count);
        
        // Enable/disable bulk import button
        if (count > 0) {
            $('#wpgl-bulk-import').prop('disabled', false);
        } else {
            $('#wpgl-bulk-import').prop('disabled', true);
        }
    }
    
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
    
    // Load More button - using direct binding with event delegation
    $(document).on('click', '#wpgl-load-more', function(e) {
        e.preventDefault();
        console.log('Load more button clicked');
        $(this).prop('disabled', true).text('Loading...');
        loadAlbums();
    });
    
    // Bulk import button - using direct binding with event delegation
    $(document).on('click', '#wpgl-bulk-import', function(e) {
        e.preventDefault();
        console.log('Bulk import button clicked');
        bulkImportAlbums();
    });
    
    // Import all button - using direct binding with event delegation
    $(document).on('click', '#wpgl-import-all', function(e) {
        e.preventDefault();
        console.log('Import all button clicked');
        // Select all albums first
        $('#wpgl-select-all').prop('checked', true).trigger('change');
        // Then trigger bulk import
        $('#wpgl-bulk-import').trigger('click');
    });
    
    // Debug button for demo loading - DISABLED
    $('.wpgl-demo-mode a').on('click', function(e) {
        e.preventDefault(); // Prevent demo mode
        logDebug('Demo mode requested but blocked');
        alert('Demo mode has been disabled. The system will use real Google Photos API data.');
    });
    
    // Individual album import button click
    $(document).on('click', '.wpgl-import-album', function(e) {
        e.preventDefault();
        const albumId = $(this).data('id');
        console.log('Import button clicked for album ID:', albumId);
        handleImportAlbum(albumId);
    });
    
    // Run diagnostic on page load if in debug mode
    if (DEBUG) {
        console.log('WP Gallery Link: Debug mode is active');
        if (typeof wpglAdmin !== 'undefined') {
            console.log('WP Gallery Link: wpglAdmin object is available', wpglAdmin);
        } else {
            console.error('WP Gallery Link: wpglAdmin object is not available!');
        }
    }
    
    // Directly hook into document ready with additional initialization
    $(document).ready(function() {
        console.log('WP Gallery Link: Document ready event fired');
        
        // Force cache refresh on all AJAX requests
        $.ajaxSetup({
            cache: false
        });
        
        // Log all import buttons that exist on page load
        $('.wpgl-import-album').each(function() {
            console.log('Found import button with ID:', $(this).data('id'));
        });
    });
});
