jQuery(document).ready(function($) {
    const AlbumsPage = {
        init: function() {
            this.loadAlbums();
            this.bindEvents();
        },

        bindEvents: function() {
            $('#pv-create-album-btn').on('click', this.openCreateModal);
            $('#pv-save-album').on('click', this.saveAlbum);
            $('#pv-cancel-album').on('click', () => $('#pv-album-modal').fadeOut());
            $('.pv-modal-close').on('click', function() {
                $(this).closest('.pv-modal').fadeOut();
            });
            $(document).on('click', '.pv-album-card', this.openAlbumDetail);
            $('#pv-delete-album').on('click', this.deleteAlbum);
            $('#pv-edit-album').on('click', this.editAlbum);
            $('#pv-share-album').on('click', this.shareAlbum);
        },

        openCreateModal: function() {
            $('#pv-album-modal-title').text('Create New Album');
            $('#pv-album-name').val('');
            $('#pv-album-description').val('');
            $('#pv-album-visibility').val('private');
            $('#pv-album-modal').data('album-id', '').fadeIn();
        },

        loadAlbums: function() {
            $('#pv-albums-loading').show();
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_albums',
                nonce: photoVault.nonce
            }, function(response) {
                // console.log('Albums response:', response); // Debug log
                
                if (response.success && response.data) {
                    // Ensure data is an array
                    const albums = Array.isArray(response.data) ? response.data : [];
                    AlbumsPage.renderAlbums(albums);
                } else {
                    console.error('Invalid albums response:', response);
                    AlbumsPage.renderAlbums([]);
                }
                $('#pv-albums-loading').hide();
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading albums:', error);
                $('#pv-albums-loading').hide();
                AlbumsPage.renderAlbums([]);
            });
        },

        renderAlbums: function(albums) {
            const $grid = $('#pv-albums-grid');
            $grid.empty();

            if (!Array.isArray(albums) || albums.length === 0) {
                $grid.html('<p class="pv-no-albums">No albums yet. Create your first album!</p>');
                return;
            }

            albums.forEach(album => {
                const coverImage = album.cover_url || '';
                const imageCount = album.image_count || 0;
                const visibility = album.visibility || 'private';
                const createdDate = album.created_date ? new Date(album.created_date).toLocaleDateString() : '';
                
                const $card = $(`
                    <div class="pv-album-card" data-id="${album.id}">
                        <div class="pv-album-cover" style="background-image: url('${coverImage}')">
                            <div class="pv-album-count">${imageCount}</div>
                        </div>
                        <div class="pv-album-info">
                            <h3>${album.name}</h3>
                            <p>${album.description || ''}</p>
                            <div class="pv-album-meta">
                                <span class="pv-album-visibility">${visibility}</span>
                                <span class="pv-album-date">${createdDate}</span>
                            </div>
                        </div>
                    </div>
                `);
                $grid.append($card);
            });
        },

        addImagesToAlbum: function() {
		    const albumId = $('#pv-album-detail-modal').data('album-id');
		    if (!albumId) return;

		    // Open Media Library
		    const frame = wp.media({
		        title: 'Add Images to Album',
		        button: { text: 'Add to Album' },
		        multiple: true
		    });

		    frame.on('select', function() {
		        const attachments = frame.state().get('selection').toJSON();
		        const imageIds = attachments.map(a => a.id);

		        // AJAX save
		        $.post(photoVault.ajaxUrl, {
		            action: 'pv_add_images_to_album',
		            nonce: photoVault.nonce,
		            album_id: albumId,
		            image_ids: imageIds
		        }, function(response) {
		            if (response.success) {
		                AlbumsPage.loadAlbumImages(albumId);
		            } else {
		                alert(response.data || 'Error adding images');
		            }
		        });
		    });

		    frame.open();
		},


        openAlbumDetail: function() {
            const albumId = $(this).data('id');
            
            // Load album details and images
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_albums',
                nonce: photoVault.nonce
            }, function(response) {
                console.log('Album detail response:', response); // Debug log
                
                if (response.success && response.data) {
                    const albums = Array.isArray(response.data) ? response.data : [];
                    const album = albums.find(a => a.id == albumId);
                    
                    if (album) {
                        $('#pv-album-detail-name').text(album.name);
                        $('#pv-album-detail-description').text(album.description || '');
                        $('#pv-album-detail-count').text(`${album.image_count || 0} images`);
                        $('#pv-album-detail-date').text(`Created ${new Date(album.created_date).toLocaleDateString()}`);
                        $('#pv-album-detail-modal').data('album-id', albumId).fadeIn();
                        
                        // Load images in this album
                        AlbumsPage.loadAlbumImages(albumId);
                    } else {
                        console.error('Album not found:', albumId);
                        alert('Album not found');
                    }
                } else {
                    console.error('Invalid album detail response:', response);
                    alert('Error loading album details');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading album detail:', error);
                alert('Error loading album details');
            });
        },

        loadAlbumImages: function(albumId) {
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_images',
                nonce: photoVault.nonce,
                album_id: albumId
            }, function(response) {
                console.log('Album images response:', response); // Debug log
                
                const $imagesGrid = $('#pv-album-images');
                $imagesGrid.empty();
                
                if (response.success && response.data) {
                    const images = Array.isArray(response.data) ? response.data : [];
                    
                    if (images.length === 0) {
                        $imagesGrid.html('<p class="pv-no-images">No images in this album yet.</p>');
                        return;
                    }
                    
                    images.forEach(image => {
                        $imagesGrid.append(`
                            <div class="pv-image-thumb">
                                <img src="${image.thumbnail_url}" alt="${image.title || ''}">
                            </div>
                        `);
                    });
                } else {
                    console.error('Invalid images response:', response);
                    $imagesGrid.html('<p class="pv-no-images">Error loading images.</p>');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading images:', error);
                $('#pv-album-images').html('<p class="pv-no-images">Error loading images.</p>');
            });
        },

        saveAlbum: function() {
            const name = $('#pv-album-name').val().trim();
            if (!name) {
                alert('Please enter an album name');
                return;
            }

            const albumId = $('#pv-album-modal').data('album-id');
            const data = {
                action: albumId ? 'pv_update_album' : 'pv_create_album',
                nonce: photoVault.nonce,
                name: name,
                description: $('#pv-album-description').val(),
                visibility: $('#pv-album-visibility').val()
            };

            if (albumId) {
                data.album_id = albumId;
            }

            $.post(photoVault.ajaxUrl, data, function(response) {
                console.log('Save album response:', response); // Debug log
                
                if (response.success) {
                    $('#pv-album-modal').fadeOut();
                    AlbumsPage.loadAlbums();
                } else {
                    alert(response.data || 'Error saving album');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error saving album:', error);
                alert('Error saving album');
            });
        },

        editAlbum: function() {
            const albumId = $('#pv-album-detail-modal').data('album-id');
            
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_albums',
                nonce: photoVault.nonce
            }, function(response) {
                if (response.success && response.data) {
                    const albums = Array.isArray(response.data) ? response.data : [];
                    const album = albums.find(a => a.id == albumId);
                    
                    if (album) {
                        $('#pv-album-modal-title').text('Edit Album');
                        $('#pv-album-name').val(album.name);
                        $('#pv-album-description').val(album.description || '');
                        $('#pv-album-visibility').val(album.visibility || 'private');
                        $('#pv-album-modal').data('album-id', albumId).fadeIn();
                        $('#pv-album-detail-modal').fadeOut();
                    } else {
                        alert('Album not found');
                    }
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading album for edit:', error);
                alert('Error loading album');
            });
        },

        deleteAlbum: function() {
            if (!confirm('Are you sure you want to delete this album? This action cannot be undone.')) {
                return;
            }

            const albumId = $('#pv-album-detail-modal').data('album-id');
            
            $.post(photoVault.ajaxUrl, {
                action: 'pv_delete_album',
                nonce: photoVault.nonce,
                album_id: albumId
            }, function(response) {
                console.log('Delete album response:', response); // Debug log
                
                if (response.success) {
                    $('#pv-album-detail-modal').fadeOut();
                    AlbumsPage.loadAlbums();
                } else {
                    alert(response.data || 'Error deleting album');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error deleting album:', error);
                alert('Error deleting album');
            });
        },

        shareAlbum: function() {
            const albumId = $('#pv-album-detail-modal').data('album-id');
            const userId = prompt('Enter user ID to share with:');
            
            if (!userId) return;

            $.post(photoVault.ajaxUrl, {
                action: 'pv_share_item',
                nonce: photoVault.nonce,
                item_type: 'album',
                item_id: albumId,
                share_with: userId,
                permission: 'view'
            }, function(response) {
                console.log('Share album response:', response); // Debug log
                
                if (response.success) {
                    alert('Album shared successfully');
                } else {
                    alert(response.data || 'Error sharing album');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error sharing album:', error);
                alert('Error sharing album');
            });
        }
    };

    AlbumsPage.init();
});