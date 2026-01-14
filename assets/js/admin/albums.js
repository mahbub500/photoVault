jQuery(document).ready(function($) {
    let currentAlbumId = null;
    let isEditMode = false;

    // Load albums on page load
    loadAlbums();

    /**
     * Load all albums
     */
    function loadAlbums() {
        $('#pv-albums-loading').show();
        $('#pv-albums-grid').empty();

        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pv_get_albums',
                nonce: photoVaultAlbum.nonce
            },
            success: function(response) {
                $('#pv-albums-loading').hide();
                
                if (response.success && response.data) {
                    displayAlbums(response.data);
                } else {
                    $('#pv-albums-grid').html('<div class="pv-empty-state"><p>' + photoVaultAlbum.i18n.noAlbums + '</p></div>');
                }
            },
            error: function() {
                $('#pv-albums-loading').hide();
                alert(photoVaultAlbum.i18n.errorLoadingAlbums);
            }
        });
    }

    /**
     * Display albums in grid
     */
    function displayAlbums(albums) {
        const $grid = $('#pv-albums-grid');
        $grid.empty();

        if (!albums || albums.length === 0) {
            $grid.html('<div class="pv-empty-state"><p>' + photoVaultAlbum.i18n.noAlbums + '</p></div>');
            return;
        }

        albums.forEach(function(album) {
            const coverImage = album.cover_image_url || photoVaultAlbum.defaultCover;
            const imageCount = album.image_count || 0;
            const createdDate = album.created_at || '';

            const html = `
                <div class="pv-album-card" data-album-id="${album.id}">
                    <div class="pv-album-cover">
                        <img src="${coverImage}" alt="${album.name}">
                        <div class="pv-album-overlay">
                            <button class="button button-primary pv-view-album" data-album-id="${album.id}">
                                ${photoVaultAlbum.i18n.viewAlbum}
                            </button>
                        </div>
                    </div>
                    <div class="pv-album-info">
                        <h3 class="pv-album-name">${album.name}</h3>
                        <p class="pv-album-description">${album.description || ''}</p>
                        <div class="pv-album-meta">
                            <span class="pv-album-count">${imageCount} ${photoVaultAlbum.i18n.images}</span>
                            <span class="pv-album-visibility">${album.visibility}</span>
                        </div>
                    </div>
                </div>
            `;
            $grid.append(html);
        });
    }

    /**
     * Open create album modal
     */
    $('#pv-create-album-btn').on('click', function(e) {
        e.preventDefault();
        isEditMode = false;
        currentAlbumId = null;
        
        $('#pv-album-modal-title').text(photoVaultAlbum.i18n.createNewAlbum);
        $('#pv-album-name').val('');
        $('#pv-album-description').val('');
        $('#pv-album-visibility').val('private');
        
        $('#pv-album-modal').fadeIn(300);
    });

    /**
     * View album details
     */
    $(document).on('click', '.pv-view-album', function(e) {
        e.preventDefault();
        const albumId = $(this).data('album-id');
        viewAlbumDetails(albumId);
    });

    /**
     * View album details function
     */
    function viewAlbumDetails(albumId) {
        currentAlbumId = albumId;
        
        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pv_get_album_details',
                nonce: photoVaultAlbum.nonce,
                album_id: albumId
            },
            success: function(response) {
                if (response.success && response.data) {
                    displayAlbumDetails(response.data);
                    $('#pv-album-detail-modal').fadeIn(300);
                } else {
                    alert(response.data.message || photoVaultAlbum.i18n.errorLoadingAlbum);
                }
            },
            error: function() {
                alert(photoVaultAlbum.i18n.errorLoadingAlbum);
            }
        });
    }

    /**
     * Display album details
     */
    function displayAlbumDetails(album) {
        $('#pv-album-detail-name').text(album.name);
        $('#pv-album-detail-description').text(album.description || '');
        $('#pv-album-detail-count').text(album.image_count + ' ' + photoVaultAlbum.i18n.images);
        $('#pv-album-detail-date').text(album.created_at);

        const $imagesGrid = $('#pv-album-images');
        $imagesGrid.empty();

        if (album.images && album.images.length > 0) {
            album.images.forEach(function(image) {
                const html = `
                    <div class="pv-album-image" data-image-id="${image.id}">
                        <img src="${image.thumbnail_url}" alt="${image.title}">
                        <button class="pv-remove-image-from-album" data-image-id="${image.id}">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                `;
                $imagesGrid.append(html);
            });
        } else {
            $imagesGrid.html('<div class="pv-empty-state"><p>' + photoVaultAlbum.i18n.noImagesInAlbum + '</p></div>');
        }
    }

    /**
     * Save album (create or update)
     */
    $('#pv-save-album').on('click', function() {
        const name = $('#pv-album-name').val().trim();
        const description = $('#pv-album-description').val().trim();
        const visibility = $('#pv-album-visibility').val();

        if (!name) {
            alert(photoVaultAlbum.i18n.albumNameRequired);
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text(photoVaultAlbum.i18n.saving);

        const action = isEditMode ? 'pv_update_album' : 'pv_create_album';
        const data = {
            action: action,
            nonce: photoVaultAlbum.nonce,
            name: name,
            description: description,
            visibility: visibility
        };

        if (isEditMode && currentAlbumId) {
            data.album_id = currentAlbumId;
        }

        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#pv-album-modal').fadeOut(300);
                    loadAlbums();
                    
                    if (response.data.album_id) {
                        currentAlbumId = response.data.album_id;
                    }
                } else {
                    alert(response.data.message || photoVaultAlbum.i18n.errorSavingAlbum);
                }
                $btn.prop('disabled', false).text(photoVaultAlbum.i18n.saveAlbum);
            },
            error: function() {
                alert(photoVaultAlbum.i18n.errorSavingAlbum);
                $btn.prop('disabled', false).text(photoVaultAlbum.i18n.saveAlbum);
            }
        });
    });

    /**
     * Edit album
     */
    $('#pv-edit-album').on('click', function() {
        if (!currentAlbumId) return;

        isEditMode = true;
        
        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pv_get_album_details',
                nonce: photoVaultAlbum.nonce,
                album_id: currentAlbumId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const album = response.data;
                    
                    $('#pv-album-modal-title').text(photoVaultAlbum.i18n.editAlbum);
                    $('#pv-album-name').val(album.name);
                    $('#pv-album-description').val(album.description || '');
                    $('#pv-album-visibility').val(album.visibility);
                    
                    $('#pv-album-detail-modal').fadeOut(300);
                    $('#pv-album-modal').fadeIn(300);
                }
            },
            error: function() {
                alert(photoVaultAlbum.i18n.errorLoadingAlbum);
            }
        });
    });

    /**
     * Delete album
     */
    $('#pv-delete-album').on('click', function() {
        if (!currentAlbumId) return;

        if (!confirm(photoVaultAlbum.i18n.deleteAlbumConfirm)) {
            return;
        }

        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pv_delete_album',
                nonce: photoVaultAlbum.nonce,
                album_id: currentAlbumId
            },
            success: function(response) {
                if (response.success) {
                    $('#pv-album-detail-modal').fadeOut(300);
                    loadAlbums();
                    currentAlbumId = null;
                } else {
                    alert(response.data.message || photoVaultAlbum.i18n.errorDeletingAlbum);
                }
            },
            error: function() {
                alert(photoVaultAlbum.i18n.errorDeletingAlbum);
            }
        });
    });

    /**
     * Add images to album
     */
    $('#pv-add-images-to-album').on('click', function() {
        if (!currentAlbumId) return;
        
        // This would open an image selector modal
        // Implementation depends on your image selection system
        alert(photoVaultAlbum.i18n.featureComingSoon);
    });

    /**
     * Share album
     */
    $('#pv-share-album').on('click', function() {
        if (!currentAlbumId) return;
        
        // This would open a sharing modal
        // Implementation depends on your sharing system
        alert(photoVaultAlbum.i18n.featureComingSoon);
    });

    /**
     * Remove image from album
     */
    $(document).on('click', '.pv-remove-image-from-album', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!confirm(photoVaultAlbum.i18n.removeImageConfirm)) {
            return;
        }

        const imageId = $(this).data('image-id');
        const $imageItem = $(this).closest('.pv-album-image');

        $.ajax({
            url: photoVaultAlbum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pv_remove_image_from_album',
                nonce: photoVaultAlbum.nonce,
                album_id: currentAlbumId,
                image_id: imageId
            },
            success: function(response) {
                if (response.success) {
                    $imageItem.fadeOut(300, function() {
                        $(this).remove();
                        
                        if ($('#pv-album-images .pv-album-image').length === 0) {
                            $('#pv-album-images').html('<div class="pv-empty-state"><p>' + photoVaultAlbum.i18n.noImagesInAlbum + '</p></div>');
                        }
                    });
                } else {
                    alert(response.data.message || photoVaultAlbum.i18n.errorRemovingImage);
                }
            },
            error: function() {
                alert(photoVaultAlbum.i18n.errorRemovingImage);
            }
        });
    });

    /**
     * Cancel album modal
     */
    $('#pv-cancel-album').on('click', function() {
        $('#pv-album-modal').fadeOut(300);
    });

    /**
     * Close modals
     */
    $('.pv-modal-close').on('click', function() {
        $(this).closest('.pv-modal').fadeOut(300);
    });

    /**
     * Close modal on overlay click
     */
    $('.pv-modal').on('click', function(e) {
        if ($(e.target).hasClass('pv-modal')) {
            $(this).fadeOut(300);
        }
    });

    /**
     * Close modal on ESC key
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.pv-modal').fadeOut(300);
        }
    });
});