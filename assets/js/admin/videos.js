/**
 * PhotoVault Videos Page JavaScript
 *
 * @package PhotoVault
 */

(function($) {
    'use strict';

    // Global variables
    let currentPage = 1;
    let currentFilters = {
        search: '',
        album_id: '',
        tag_id: '',
        visibility: '',
        sort: 'date_desc'
    };
    let isLoading = false;
    let uploadQueue = [];

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initializeEventHandlers();
        loadVideos();
        loadAlbums();
        loadTags();
    });

    /**
     * Initialize all event handlers
     */
    function initializeEventHandlers() {
        // Upload button
        $('.pv-upload-video-btn').on('click', openUploadModal);

        // Modal close buttons
        $('.pv-modal-close, .pv-modal-cancel').on('click', closeModals);

        // Click outside modal to close
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('pv-modal')) {
                closeModals();
            }
        });

        // Upload form submit
        $('#pv-upload-video-form').on('submit', handleVideoUpload);

        // Edit form submit
        $('#pv-edit-video-form').on('submit', handleVideoEdit);

        // Filter changes
        $('#pv-video-search').on('keyup', debounce(handleSearchChange, 500));
        $('#pv-video-album-filter, #pv-video-tag-filter, #pv-video-visibility-filter, #pv-video-sort').on('change', handleFilterChange);
        $('.pv-filter-reset').on('click', resetFilters);

        // File input change
        $('#pv-video-file').on('change', handleFileSelect);
    }

    /**
     * Open upload modal
     */
    function openUploadModal() {
        $('#pv-upload-video-modal').fadeIn(300);
        $('#pv-upload-video-form')[0].reset();
        $('.pv-upload-progress').hide();
        $('.pv-progress-fill').css('width', '0%');
        $('.pv-progress-text').text('0%');
    }

    /**
     * Close all modals
     */
    function closeModals() {
        $('.pv-modal').fadeOut(300);
    }

    /**
     * Handle file selection
     */
    function handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Auto-fill title if empty
        if (!$('#pv-video-title').val()) {
            const filename = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
            $('#pv-video-title').val(filename);
        }

        // Validate file size
        const maxSize = photoVaultVideos.max_upload_size || 104857600; // 100MB default
        if (file.size > maxSize) {
            alert(photoVaultVideos.i18n.file_too_large || 'File size exceeds maximum allowed size.');
            e.target.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = photoVaultVideos.allowed_types || ['mp4', 'mov', 'avi', 'wmv', 'webm'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(fileExtension)) {
            alert(photoVaultVideos.i18n.invalid_file_type || 'Invalid file type. Allowed types: ' + allowedTypes.join(', '));
            e.target.value = '';
            return;
        }
    }

    /**
     * Handle video upload
     */
    function handleVideoUpload(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'pv_upload_video');
        formData.append('nonce', photoVaultVideos.nonce);

        // Show progress bar
        $('.pv-upload-progress').show();
        $('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        updateProgress(percentComplete);
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || 'Video uploaded successfully!');
                    closeModals();
                    loadVideos();
                } else {
                    showNotice('error', response.data.message || 'Failed to upload video.');
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', 'Upload failed: ' + error);
            },
            complete: function() {
                $('.pv-upload-progress').hide();
                $('button[type="submit"]').prop('disabled', false);
            }
        });
    }

    /**
     * Update upload progress
     */
    function updateProgress(percent) {
        $('.pv-progress-fill').css('width', percent + '%');
        $('.pv-progress-text').text(percent + '%');
    }

    /**
     * Load videos
     */
    function loadVideos() {
        if (isLoading) return;
        isLoading = true;

        const data = {
            action: 'pv_get_videos',
            nonce: photoVaultVideos.nonce,
            page: currentPage,
            per_page: 20,
            ...currentFilters
        };

        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: data,
            beforeSend: function() {
                if (currentPage === 1) {
                    $('#pv-videos-container').html('<div class="pv-loading"><span class="spinner is-active"></span><p>Loading videos...</p></div>');
                }
            },
            success: function(response) {
                if (response.success) {
                    renderVideos(response.data.videos);
                    renderPagination(response.data);
                } else {
                    showNotice('error', response.data.message || 'Failed to load videos.');
                }
            },
            error: function() {
                showNotice('error', 'Failed to load videos.');
            },
            complete: function() {
                isLoading = false;
            }
        });
    }

    /**
     * Render videos grid
     */
    function renderVideos(videos) {
        const container = $('#pv-videos-container');

        if (!videos || videos.length === 0) {
            container.html('<div class="pv-no-results"><p>No videos found.</p></div>');
            return;
        }

        let html = '';
        videos.forEach(function(video) {
            html += renderVideoCard(video);
        });

        container.html(html);

        // Attach event handlers to new elements
        attachVideoCardHandlers();
    }

    /**
     * Render single video card
     */
    function renderVideoCard(video) {
        const thumbnailUrl = video.thumbnail || photoVaultVideos.default_thumbnail || '';
        const title = escapeHtml(video.title || 'Untitled');
        const duration = video.formatted_duration || '00:00';
        const uploadDate = new Date(video.upload_date).toLocaleDateString();
        const fileSize = formatFileSize(video.file_size);

        return `
            <div class="pv-video-item" data-video-id="${video.id}">
                <div class="pv-video-thumbnail">
                    ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="${title}">` : '<div class="pv-no-thumbnail">No Thumbnail</div>'}
                    <span class="pv-video-duration">${duration}</span>
                    <div class="pv-video-overlay">
                        <button class="pv-play-btn" title="Play Video">
                            <span class="dashicons dashicons-controls-play"></span>
                        </button>
                    </div>
                </div>
                <div class="pv-video-info">
                    <h3 class="pv-video-title">${title}</h3>
                    <div class="pv-video-meta">
                        <span class="pv-meta-item">
                            <span class="dashicons dashicons-calendar"></span> ${uploadDate}
                        </span>
                        <span class="pv-meta-item">
                            <span class="dashicons dashicons-media-archive"></span> ${fileSize}
                        </span>
                    </div>
                    ${video.tags && video.tags.length > 0 ? renderTags(video.tags) : ''}
                    <div class="pv-video-actions">
                        <button class="button button-small pv-view-video" title="View Details">
                            <span class="dashicons dashicons-visibility"></span> View
                        </button>
                        <button class="button button-small pv-edit-video" title="Edit">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button class="button button-small pv-delete-video" title="Delete">
                            <span class="dashicons dashicons-trash"></span> Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render tags
     */
    function renderTags(tags) {
        let html = '<div class="pv-video-tags">';
        tags.forEach(function(tag) {
            html += `<span class="pv-tag" style="background-color: ${tag.color || '#667eea'}">${escapeHtml(tag.name)}</span>`;
        });
        html += '</div>';
        return html;
    }

    /**
     * Attach event handlers to video cards
     */
    function attachVideoCardHandlers() {
        $('.pv-view-video, .pv-play-btn').off('click').on('click', function() {
            const videoId = $(this).closest('.pv-video-item').data('video-id');
            viewVideoDetails(videoId);
        });

        $('.pv-edit-video').off('click').on('click', function() {
            const videoId = $(this).closest('.pv-video-item').data('video-id');
            editVideo(videoId);
        });

        $('.pv-delete-video').off('click').on('click', function() {
            const videoId = $(this).closest('.pv-video-item').data('video-id');
            deleteVideo(videoId);
        });
    }

    /**
     * View video details
     */
    function viewVideoDetails(videoId) {
        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: {
                action: 'pv_get_videos',
                nonce: photoVaultVideos.nonce,
                video_id: videoId
            },
            beforeSend: function() {
                $('#pv-video-details-content').html('<div class="pv-loading"><span class="spinner is-active"></span><p>Loading video...</p></div>');
                $('#pv-video-details-modal').fadeIn(300);
            },
            success: function(response) {
                if (response.success && response.data.videos && response.data.videos.length > 0) {
                    const video = response.data.videos[0];
                    renderVideoPlayer(video);
                } else {
                    $('#pv-video-details-content').html('<div class="pv-error"><p>Failed to load video details.</p></div>');
                }
            },
            error: function() {
                $('#pv-video-details-content').html('<div class="pv-error"><p>Failed to load video details.</p></div>');
            }
        });
    }

    /**
     * Render video player
     */
    function renderVideoPlayer(video) {
        const videoUrl = video.url || '';
        const title = escapeHtml(video.title || 'Untitled');
        const description = escapeHtml(video.description || '');
        const uploadDate = new Date(video.upload_date).toLocaleDateString();
        const fileSize = formatFileSize(video.file_size);
        const duration = video.formatted_duration || '00:00';
        const dimensions = video.width && video.height ? `${video.width} × ${video.height}` : 'Unknown';
        
        let html = `
            <div class="pv-video-player-container">
                <div class="pv-video-player-wrapper">
                    <video id="pv-main-video-player" class="pv-video-player" controls preload="metadata">
                        <source src="${videoUrl}" type="${video.mime_type || 'video/mp4'}">
                        Your browser does not support the video tag.
                    </video>
                    <div class="pv-keyboard-shortcuts-hint">
                        <button class="pv-shortcuts-btn" title="Keyboard Shortcuts">
                            <span class="dashicons dashicons-keyboard-hide"></span>
                        </button>
                    </div>
                </div>
                
                <div class="pv-video-details-info">
                    <h2>${title}</h2>
                    
                    ${description ? `<p class="pv-video-description">${description}</p>` : ''}
                    
                    <div class="pv-video-stats">
                        <div class="pv-stat-item">
                            <span class="dashicons dashicons-clock"></span>
                            <strong>Duration:</strong> ${duration}
                        </div>
                        <div class="pv-stat-item">
                            <span class="dashicons dashicons-calendar"></span>
                            <strong>Uploaded:</strong> ${uploadDate}
                        </div>
                        <div class="pv-stat-item">
                            <span class="dashicons dashicons-media-archive"></span>
                            <strong>Size:</strong> ${fileSize}
                        </div>
                        <div class="pv-stat-item">
                            <span class="dashicons dashicons-desktop"></span>
                            <strong>Resolution:</strong> ${dimensions}
                        </div>
                        <div class="pv-stat-item">
                            <span class="dashicons dashicons-visibility"></span>
                            <strong>Visibility:</strong> ${video.visibility}
                        </div>
                    </div>
                    
                    ${video.tags && video.tags.length > 0 ? `
                        <div class="pv-video-tags-section">
                            <strong>Tags:</strong>
                            ${renderTags(video.tags)}
                        </div>
                    ` : ''}
                    
                    ${video.albums && video.albums.length > 0 ? `
                        <div class="pv-video-albums-section">
                            <strong>Albums:</strong>
                            <div class="pv-album-list">
                                ${video.albums.map(album => `<span class="pv-album-badge">${escapeHtml(album.name)}</span>`).join('')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="pv-video-actions-full">
                        <button class="button button-primary pv-download-video" data-url="${videoUrl}" data-title="${title}">
                            <span class="dashicons dashicons-download"></span> Download
                        </button>
                        <button class="button pv-edit-video-from-player" data-video-id="${video.id}">
                            <span class="dashicons dashicons-edit"></span> Edit
                        </button>
                        <button class="button pv-delete-video-from-player" data-video-id="${video.id}">
                            <span class="dashicons dashicons-trash"></span> Delete
                        </button>
                        <button class="button pv-share-video" data-video-id="${video.id}">
                            <span class="dashicons dashicons-share"></span> Share
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="pv-shortcuts-overlay" style="display: none;">
                <div class="pv-shortcuts-content">
                    <h3>Keyboard Shortcuts</h3>
                    <div class="pv-shortcuts-grid">
                        <div class="pv-shortcut-item">
                            <kbd>Space</kbd>
                            <span>Play / Pause</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>←</kbd>
                            <span>Rewind 5s</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>→</kbd>
                            <span>Forward 5s</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>↑</kbd>
                            <span>Volume Up</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>↓</kbd>
                            <span>Volume Down</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>M</kbd>
                            <span>Mute / Unmute</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>F</kbd>
                            <span>Fullscreen</span>
                        </div>
                        <div class="pv-shortcut-item">
                            <kbd>Esc</kbd>
                            <span>Close</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#pv-video-details-content').html(html);
        
        // Attach event handlers
        attachPlayerHandlers();
        
        // Shortcuts button
        $('.pv-shortcuts-btn').on('click', function(e) {
            e.stopPropagation();
            $('.pv-shortcuts-overlay').fadeToggle(200);
        });
        
        $('.pv-shortcuts-overlay').on('click', function() {
            $(this).fadeOut(200);
        });
        
        $('.pv-shortcuts-content').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Attach handlers to player actions
     */
    function attachPlayerHandlers() {
        // Download video
        $('.pv-download-video').off('click').on('click', function() {
            const url = $(this).data('url');
            const title = $(this).data('title');
            downloadVideo(url, title);
        });
        
        // Edit from player
        $('.pv-edit-video-from-player').off('click').on('click', function() {
            const videoId = $(this).data('video-id');
            closeModals();
            editVideo(videoId);
        });
        
        // Delete from player
        $('.pv-delete-video-from-player').off('click').on('click', function() {
            const videoId = $(this).data('video-id');
            closeModals();
            deleteVideo(videoId);
        });
        
        // Share video
        $('.pv-share-video').off('click').on('click', function() {
            const videoId = $(this).data('video-id');
            shareVideo(videoId);
        });
        
        // Play video automatically
        const videoPlayer = document.getElementById('pv-main-video-player');
        if (videoPlayer) {
            // Attempt autoplay
            videoPlayer.play().catch(function(error) {
                console.log('Autoplay prevented:', error);
            });
            
            // Add keyboard controls
            addVideoKeyboardControls(videoPlayer);
            
            // Add fullscreen double-click
            videoPlayer.addEventListener('dblclick', function() {
                if (videoPlayer.requestFullscreen) {
                    videoPlayer.requestFullscreen();
                } else if (videoPlayer.webkitRequestFullscreen) {
                    videoPlayer.webkitRequestFullscreen();
                } else if (videoPlayer.mozRequestFullScreen) {
                    videoPlayer.mozRequestFullScreen();
                } else if (videoPlayer.msRequestFullscreen) {
                    videoPlayer.msRequestFullscreen();
                }
            });
            
            // Track video events
            videoPlayer.addEventListener('play', function() {
                console.log('Video playing');
            });
            
            videoPlayer.addEventListener('pause', function() {
                console.log('Video paused');
            });
            
            videoPlayer.addEventListener('ended', function() {
                console.log('Video ended');
            });
        }
    }

    /**
     * Add keyboard controls to video player
     */
    function addVideoKeyboardControls(videoPlayer) {
        $(document).off('keydown.videoplayer').on('keydown.videoplayer', function(e) {
            // Only if modal is open
            if (!$('#pv-video-details-modal').is(':visible')) {
                return;
            }
            
            switch(e.keyCode) {
                case 32: // Space - Play/Pause
                    e.preventDefault();
                    if (videoPlayer.paused) {
                        videoPlayer.play();
                    } else {
                        videoPlayer.pause();
                    }
                    break;
                    
                case 37: // Left Arrow - Rewind 5 seconds
                    e.preventDefault();
                    videoPlayer.currentTime = Math.max(0, videoPlayer.currentTime - 5);
                    break;
                    
                case 39: // Right Arrow - Forward 5 seconds
                    e.preventDefault();
                    videoPlayer.currentTime = Math.min(videoPlayer.duration, videoPlayer.currentTime + 5);
                    break;
                    
                case 38: // Up Arrow - Volume Up
                    e.preventDefault();
                    videoPlayer.volume = Math.min(1, videoPlayer.volume + 0.1);
                    break;
                    
                case 40: // Down Arrow - Volume Down
                    e.preventDefault();
                    videoPlayer.volume = Math.max(0, videoPlayer.volume - 0.1);
                    break;
                    
                case 77: // M - Mute/Unmute
                    e.preventDefault();
                    videoPlayer.muted = !videoPlayer.muted;
                    break;
                    
                case 70: // F - Fullscreen
                    e.preventDefault();
                    if (videoPlayer.requestFullscreen) {
                        videoPlayer.requestFullscreen();
                    } else if (videoPlayer.webkitRequestFullscreen) {
                        videoPlayer.webkitRequestFullscreen();
                    } else if (videoPlayer.mozRequestFullScreen) {
                        videoPlayer.mozRequestFullScreen();
                    }
                    break;
            }
        });
        
        // Remove keyboard listener when modal closes
        $('#pv-video-details-modal .pv-modal-close').on('click', function() {
            $(document).off('keydown.videoplayer');
        });
    }

    /**
     * Download video
     */
    function downloadVideo(url, title) {
        const link = document.createElement('a');
        link.href = url;
        link.download = title || 'video';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    /**
     * Share video
     */
    function shareVideo(videoId) {
        // Create share URL
        const shareUrl = window.location.origin + '?pv_video=' + videoId;
        
        // Copy to clipboard
        if (navigator.clipboard) {
            navigator.clipboard.writeText(shareUrl).then(function() {
                showNotice('success', 'Share link copied to clipboard!');
            }).catch(function() {
                promptShareUrl(shareUrl);
            });
        } else {
            promptShareUrl(shareUrl);
        }
    }

    /**
     * Prompt share URL (fallback)
     */
    function promptShareUrl(url) {
        prompt('Copy this link to share:', url);
    }

    /**
     * Edit video
     */
    function editVideo(videoId) {
        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: {
                action: 'pv_get_videos',
                nonce: photoVaultVideos.nonce,
                video_id: videoId
            },
            success: function(response) {
                if (response.success && response.data.videos && response.data.videos.length > 0) {
                    const video = response.data.videos[0];
                    
                    $('#pv-edit-video-id').val(video.id);
                    $('#pv-edit-video-title').val(video.title);
                    $('#pv-edit-video-description').val(video.description);
                    $('#pv-edit-video-visibility').val(video.visibility);
                    
                    $('#pv-edit-video-modal').fadeIn(300);
                } else {
                    showNotice('error', 'Failed to load video details.');
                }
            },
            error: function() {
                showNotice('error', 'Failed to load video details.');
            }
        });
    }

    /**
     * Handle video edit
     */
    function handleVideoEdit(e) {
        e.preventDefault();

        const formData = $(this).serialize();

        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: formData + '&action=pv_update_video&nonce=' + photoVaultVideos.nonce,
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || 'Video updated successfully!');
                    closeModals();
                    loadVideos();
                } else {
                    showNotice('error', response.data.message || 'Failed to update video.');
                }
            },
            error: function() {
                showNotice('error', 'Failed to update video.');
            }
        });
    }

    /**
     * Delete video
     */
    function deleteVideo(videoId) {
        if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: {
                action: 'pv_delete_video',
                nonce: photoVaultVideos.nonce,
                video_id: videoId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || 'Video deleted successfully!');
                    loadVideos();
                } else {
                    showNotice('error', response.data.message || 'Failed to delete video.');
                }
            },
            error: function() {
                showNotice('error', 'Failed to delete video.');
            }
        });
    }

    /**
     * Load albums for filter
     */
    function loadAlbums() {
        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: {
                action: 'pv_get_albums',
                nonce: photoVaultVideos.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const select = $('#pv-video-album-filter, #pv-video-album');
                    response.data.forEach(function(album) {
                        select.append(`<option value="${album.id}">${escapeHtml(album.name)}</option>`);
                    });
                }
            }
        });
    }

    /**
     * Load tags for filter
     */
    function loadTags() {
        $.ajax({
            url: photoVaultVideos.ajax_url,
            type: 'POST',
            data: {
                action: 'pv_get_tags',
                nonce: photoVaultVideos.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const select = $('#pv-video-tag-filter');
                    response.data.forEach(function(tag) {
                        select.append(`<option value="${tag.id}">${escapeHtml(tag.name)}</option>`);
                    });
                }
            }
        });
    }

    /**
     * Handle search change
     */
    function handleSearchChange() {
        currentFilters.search = $(this).val();
        currentPage = 1;
        loadVideos();
    }

    /**
     * Handle filter change
     */
    function handleFilterChange() {
        currentFilters.album_id = $('#pv-video-album-filter').val();
        currentFilters.tag_id = $('#pv-video-tag-filter').val();
        currentFilters.visibility = $('#pv-video-visibility-filter').val();
        currentFilters.sort = $('#pv-video-sort').val();
        currentPage = 1;
        loadVideos();
    }

    /**
     * Reset filters
     */
    function resetFilters() {
        $('#pv-video-search').val('');
        $('#pv-video-album-filter').val('');
        $('#pv-video-tag-filter').val('');
        $('#pv-video-visibility-filter').val('');
        $('#pv-video-sort').val('date_desc');
        
        currentFilters = {
            search: '',
            album_id: '',
            tag_id: '',
            visibility: '',
            sort: 'date_desc'
        };
        currentPage = 1;
        loadVideos();
    }

    /**
     * Render pagination
     */
    function renderPagination(data) {
        const container = $('#pv-videos-pagination');
        
        if (data.total_pages <= 1) {
            container.html('');
            return;
        }

        let html = '<div class="pv-pagination-wrapper">';
        html += `<span class="pv-pagination-info">Page ${data.page} of ${data.total_pages} (${data.total} videos)</span>`;
        html += '<div class="pv-pagination-buttons">';

        // Previous button
        if (data.page > 1) {
            html += `<button class="button pv-page-btn" data-page="${data.page - 1}">Previous</button>`;
        }

        // Page numbers
        for (let i = 1; i <= data.total_pages; i++) {
            if (i === 1 || i === data.total_pages || (i >= data.page - 2 && i <= data.page + 2)) {
                const activeClass = i === data.page ? 'button-primary' : '';
                html += `<button class="button pv-page-btn ${activeClass}" data-page="${i}">${i}</button>`;
            } else if (i === data.page - 3 || i === data.page + 3) {
                html += '<span class="pv-pagination-ellipsis">...</span>';
            }
        }

        // Next button
        if (data.page < data.total_pages) {
            html += `<button class="button pv-page-btn" data-page="${data.page + 1}">Next</button>`;
        }

        html += '</div></div>';
        container.html(html);

        // Attach pagination handlers
        $('.pv-page-btn').on('click', function() {
            currentPage = parseInt($(this).data('page'));
            loadVideos();
            $('html, body').animate({ scrollTop: 0 }, 300);
        });
    }

    /**
     * Show notice
     */
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);

        $('.wrap h1').after(notice);

        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });

        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

})(jQuery);