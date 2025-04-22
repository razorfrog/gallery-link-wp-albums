
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
    console.log('IMPORTANT: This version uses direct HTML rendering only - no templates');
    
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
     * Render an album in the grid - Using direct HTML creation ONLY
     */
    function renderAlbum(album) {
        logDebug('Rendering album using direct HTML creation:', album);
        console.log('Rendering album with ID:', album.id, 'and title:', album.title);

        const $albumsGrid = $('.wpgl-albums-grid');
        
        if (!$albumsGrid.length) {
            console.error('WP Gallery Link: Albums grid container not found!');
            addLoadingLog('Error: Album grid container not found in the DOM.');
            return;
        }
            
        // Create a default cover image if one isn't provided
        const coverImageUrl = album.coverPhotoBaseUrl || 'https://via.placeholder.com/200x200?text=No+Cover';
        
        // Create album HTML directly in JavaScript - NO TEMPLATES
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
        
        // Clear UI elements
        $('.wpgl-loading-log').empty();
        $('#wpgl-albums-title-list').empty();
        $('.wpgl-albums-grid').empty(); // Clear the albums grid
        $('.wpgl-bulk-actions').hide();
        updateProgress(0);
    }
    
    /**
     * Load albums from the API
     */
    function loadAlbums() {
        logDebug('loadAlbums function called');
        console.log('WP Gallery Link: loadAlbums function called - DIRECT HTML RENDERING VERSION');
        
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
        
        addLoadingLog('Fetching albums from Google Photos API using direct HTML rendering...');
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
                    
                    // Render each album directly with HTML generation
                    albums.forEach(function(album) {
                        albumsFound.push(album);
                        addAlbumToList(album.title);
                        renderAlbum(album);  // This uses direct HTML generation now
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
                            
                            // Show "Load More" button
                            if (!$('#wpgl-load-more').length) {
                                $('.wpgl-button-group').append(`
                                    <button id="wpgl-load-more" class="button">
                                        ${wpglAdmin.i18n.load_more}
                                    </button>
                                `);
                                
                                // Setup load more handler
                                $('#wpgl-load-more').on('click', function() {
                                    $(this).hide();
                                    loadAlbums();
                                });
                            } else {
                                $('#wpgl-load-more').show();
                            }
                        }
                        
                        hideLoadingUI();
                    }
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
            alert(wpglAdmin.i18n.no_albums_selected);
            return;
        }
        
        if (!confirm(wpglAdmin.i18n.confirm_bulk_import.replace('%d', selectedAlbums.length))) {
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
                alert(wpglAdmin.i18n.bulk_import_complete.replace('%d', importedCount).replace('%d', failedCount));
                
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
            
            $.ajax({
                url: wpglAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpgl_import_album',
                    album_id: album.id,
                    nonce: wpglAdmin.nonce,
                    bulk: true  // Flag to indicate this is part of bulk import
                },
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
    
    // Bulk import button
    $(document).on('click', '#wpgl-bulk-import', function() {
        bulkImportAlbums();
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
        console.log('WP Gallery Link: Running diagnostic check...');
        
        // Debug information for templates
        console.log('Checking for DOM elements...');
        
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
            console.log('WP Gallery Link: Start button found with ID:', startBtn.attr('id'));
        }
        
        // Check if the album grid container exists
        const albumsGrid = $('.wpgl-albums-grid');
        if (albumsGrid.length === 0) {
            console.error('WP Gallery Link: Albums grid container not found in DOM.');
        } else {
            console.log('WP Gallery Link: Albums grid found with class:', albumsGrid.attr('class'));
        }
        
        // Display DOM structure for debugging
        console.log('DOM structure of import page:');
        $('.wpgl-import-container').each(function() {
            console.log('Import container found');
            $(this).children().each(function() {
                console.log('- Child element:', this.tagName, this.className || '(no class)');
            });
        });
        
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
    
    // Explicitly trigger rendering of demo albums for testing when demo mode is active
    if (typeof wpglAdmin !== 'undefined' && wpglAdmin.demoMode === true) {
        console.log('WP Gallery Link: Demo mode is active, will load sample albums automatically');
        setTimeout(function() {
            console.log('WP Gallery Link: Auto-triggering album load in demo mode');
            if ($('#wpgl-start-loading').length) {
                $('#wpgl-start-loading').trigger('click');
            } else {
                console.error('WP Gallery Link: Could not find start loading button to auto-trigger');
                // Try to manually start loading
                resetAlbumLoading();
                loadAlbums();
            }
        }, 500);
    }
    
    // Direct trigger for start button click handlers
    $(document).on('ready', function() {
        console.log('WP Gallery Link: Document ready event fired');
        $('#wpgl-start-loading').on('click', function(e) {
            console.log('WP Gallery Link: Start loading button clicked');
            e.preventDefault();
            resetAlbumLoading();
            loadAlbums();
        });
    });
    
    // Add direct event listener for the start button
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('wpgl-start-loading')) {
            document.getElementById('wpgl-start-loading').addEventListener('click', function(e) {
                console.log('Start loading button clicked (via addEventListener)');
                e.preventDefault();
                resetAlbumLoading();
                loadAlbums();
            });
        }
        
        // Auto-start in demo mode
        if (typeof wpglAdmin !== 'undefined' && wpglAdmin.demoMode === true) {
            console.log('WP Gallery Link: Demo mode is active, auto-starting album load');
            setTimeout(function() {
                resetAlbumLoading();
                loadAlbums();
            }, 500);
        }
    });
});
