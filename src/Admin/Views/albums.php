<?php
/**
 * PhotoVault - Albums Admin Page Template
 * File: templates/admin-albums.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <h1><?php _e('Albums', 'photovault'); ?>
        <button class="page-title-action" id="pv-create-album-btn">
            <span class="dashicons dashicons-plus"></span> <?php _e('Create Album', 'photovault'); ?>
        </button>
    </h1>

    <div class="pv-albums-container">
        <!-- Albums Grid -->
        <div id="pv-albums-grid" class="pv-albums-grid"></div>

        <!-- Loading Indicator -->
        <div id="pv-albums-loading" class="pv-loading" style="display:none;">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<!-- Create/Edit Album Modal -->
<div id="pv-album-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content">
        <span class="pv-modal-close">&times;</span>
        <h2 id="pv-album-modal-title"><?php _e('Create New Album', 'photovault'); ?></h2>
        
        <div class="pv-form-field">
            <label><?php _e('Album Name', 'photovault'); ?> *</label>
            <input type="text" id="pv-album-name" required>
        </div>

        <div class="pv-form-field">
            <label><?php _e('Description', 'photovault'); ?></label>
            <textarea id="pv-album-description" rows="4"></textarea>
        </div>

        <div class="pv-form-field">
            <label><?php _e('Visibility', 'photovault'); ?></label>
            <select id="pv-album-visibility">
                <option value="private"><?php _e('Private - Only you can see', 'photovault'); ?></option>
                <option value="shared"><?php _e('Shared - People you share with', 'photovault'); ?></option>
                <option value="public"><?php _e('Public - Anyone can view', 'photovault'); ?></option>
            </select>
        </div>

        <div class="pv-modal-actions">
            <button class="button button-primary" id="pv-save-album">
                <?php _e('Save Album', 'photovault'); ?>
            </button>
            <button class="button" id="pv-cancel-album">
                <?php _e('Cancel', 'photovault'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Album Detail Modal -->
<div id="pv-album-detail-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content pv-album-detail-modal">
        <span class="pv-modal-close">&times;</span>
        
        <div class="pv-album-header">
            <h2 id="pv-album-detail-name"></h2>
            <p id="pv-album-detail-description"></p>
            <div class="pv-album-meta">
                <span id="pv-album-detail-count"></span>
                <span id="pv-album-detail-date"></span>
            </div>
        </div>

        <div class="pv-album-actions-bar">
            <button class="button" id="pv-add-images-to-album">
                <span class="dashicons dashicons-plus"></span> <?php _e('Add Images', 'photovault'); ?>
            </button>
            <button class="button" id="pv-edit-album">
                <span class="dashicons dashicons-edit"></span> <?php _e('Edit', 'photovault'); ?>
            </button>
            <button class="button" id="pv-share-album">
                <span class="dashicons dashicons-share"></span> <?php _e('Share', 'photovault'); ?>
            </button>
            <button class="button button-link-delete" id="pv-delete-album">
                <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'photovault'); ?>
            </button>
        </div>

        <div id="pv-album-images" class="pv-album-images-grid"></div>
    </div>
</div>

<script>
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
            $('#pv-album-modal-title').text('<?php _e('Create New Album', 'photovault'); ?>');
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
                if (response.success) {
                    AlbumsPage.renderAlbums(response.data);
                }
                $('#pv-albums-loading').hide();
            });
        },

        renderAlbums: function(albums) {
            const $grid = $('#pv-albums-grid');
            $grid.empty();

            if (albums.length === 0) {
                $grid.html('<div class="pv-no-albums"><p><?php _e('No albums yet. Create your first album!', 'photovault'); ?></p></div>');
                return;
            }

            albums.forEach(album => {
                const coverImage = album.cover_url || '<?php echo admin_url('images/media-button-image.gif'); ?>';
                const $card = $(`
                    <div class="pv-album-card" data-id="${album.id}">
                        <div class="pv-album-cover">
                            <img src="${coverImage}" alt="${album.name}">
                            <div class="pv-album-overlay">
                                <span class="pv-album-count">${album.image_count} <?php _e('images', 'photovault'); ?></span>
                            </div>
                        </div>
                        <div class="pv-album-info">
                            <h3>${album.name}</h3>
                            <p>${album.description || ''}</p>
                            <div class="pv-album-meta-footer">
                                <span class="pv-album-visibility">
                                    <span class="dashicons dashicons-${album.visibility === 'public' ? 'visibility' : 'lock'}"></span>
                                    ${album.visibility}
                                </span>
                                <span class="pv-album-date">${new Date(album.created_date).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </div>
                `);
                $grid.append($card);
            });
        },

        openAlbumDetail: function() {
            const albumId = $(this).data('id');
            
            // Load album details and images
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_albums',
                nonce: photoVault.nonce
            }, function(response) {
                if (response.success) {
                    const album = response.data.find(a => a.id == albumId);
                    if (album) {
                        $('#pv-album-detail-name').text(album.name);
                        $('#pv-album-detail-description').text(album.description || '');
                        $('#pv-album-detail-count').text(`${album.image_count} images`);
                        $('#pv-album-detail-date').text(`Created ${new Date(album.created_date).toLocaleDateString()}`);
                        $('#pv-album-detail-modal').data('album-id', albumId).fadeIn();
                        
                        // Load images in this album
                        AlbumsPage.loadAlbumImages(albumId);
                    }
                }
            });
        },

        loadAlbumImages: function(albumId) {
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_images',
                nonce: photoVault.nonce,
                album_id: albumId
            }, function(response) {
                if (response.success) {
                    const $imagesGrid = $('#pv-album-images');
                    $imagesGrid.empty();
                    
                    response.data.forEach(image => {
                        $imagesGrid.append(`
                            <div class="pv-album-image-item">
                                <img src="${image.thumbnail || image.url}" alt="${image.title}">
                            </div>
                        `);
                    });
                }
            });
        },

        saveAlbum: function() {
            const name = $('#pv-album-name').val().trim();
            
            if (!name) {
                alert('<?php _e('Please enter an album name', 'photovault'); ?>');
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
                if (response.success) {
                    $('#pv-album-modal').fadeOut();
                    AlbumsPage.loadAlbums();
                } else {
                    alert('<?php _e('Failed to save album', 'photovault'); ?>');
                }
            });
        },

        editAlbum: function() {
            const albumId = $('#pv-album-detail-modal').data('album-id');
            
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_albums',
                nonce: photoVault.nonce
            }, function(response) {
                if (response.success) {
                    const album = response.data.find(a => a.id == albumId);
                    if (album) {
                        $('#pv-album-modal-title').text('<?php _e('Edit Album', 'photovault'); ?>');
                        $('#pv-album-name').val(album.name);
                        $('#pv-album-description').val(album.description);
                        $('#pv-album-visibility').val(album.visibility);
                        $('#pv-album-modal').data('album-id', albumId).fadeIn();
                        $('#pv-album-detail-modal').fadeOut();
                    }
                }
            });
        },

        deleteAlbum: function() {
            if (!confirm('<?php _e('Are you sure you want to delete this album? Images will not be deleted.', 'photovault'); ?>')) {
                return;
            }

            const albumId = $('#pv-album-detail-modal').data('album-id');

            $.post(photoVault.ajaxUrl, {
                action: 'pv_delete_album',
                nonce: photoVault.nonce,
                album_id: albumId
            }, function(response) {
                if (response.success) {
                    $('#pv-album-detail-modal').fadeOut();
                    AlbumsPage.loadAlbums();
                } else {
                    alert('<?php _e('Failed to delete album', 'photovault'); ?>');
                }
            });
        },

        shareAlbum: function() {
            const albumId = $('#pv-album-detail-modal').data('album-id');
            const userId = prompt('<?php _e('Enter user ID to share with:', 'photovault'); ?>');
            
            if (!userId) return;

            $.post(photoVault.ajaxUrl, {
                action: 'pv_share_item',
                nonce: photoVault.nonce,
                item_type: 'album',
                item_id: albumId,
                share_with: userId,
                permission: 'view'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Album shared successfully', 'photovault'); ?>');
                } else {
                    alert('<?php _e('Failed to share album', 'photovault'); ?>');
                }
            });
        }
    };

    AlbumsPage.init();
});
</script>

<style>
.pv-albums-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.pv-album-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
}

.pv-album-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.pv-album-cover {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.pv-album-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pv-album-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.7));
    padding: 15px;
    color: #fff;
}

.pv-album-count {
    font-size: 14px;
    font-weight: 500;
}

.pv-album-info {
    padding: 15px;
}

.pv-album-info h3 {
    margin: 0 0 8px;
    font-size: 16px;
}

.pv-album-info p {
    margin: 0 0 10px;
    font-size: 13px;
    color: #666;
    line-height: 1.4;
}

.pv-album-meta-footer {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #999;
}

.pv-album-visibility {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pv-no-albums {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.pv-album-detail-modal {
    max-width: 900px;
}

.pv-album-header {
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.pv-album-header h2 {
    margin: 0 0 10px;
}

.pv-album-meta {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    font-size: 14px;
    color: #666;
}

.pv-album-actions-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.pv-album-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.pv-album-image-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 4px;
}
</style>