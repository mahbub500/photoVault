/**
 * PhotoVault - Enhanced Albums Management
 */
(function($) {
    'use strict';

    const AlbumManager = {
        currentAlbumId: null,
        isEditMode: false,
        selectedImages: [],
        currentView: 'grid',

        init: function() {
            this.bindEvents();
            this.loadAlbums();
        },

        bindEvents: function() {
            const self = this;
            
            // Create album button
            if ($('#pv-create-album-btn').length) {
                $('#pv-create-album-btn').on('click', this.openCreateModal.bind(this));
            }
            
            // Save and cancel buttons
            if ($('#pv-save-album').length) {
                $('#pv-save-album').on('click', this.saveAlbum.bind(this));
            }
            
            if ($('#pv-cancel-album').length) {
                $('#pv-cancel-album').on('click', function() {
                    self.closeModal('#pv-album-modal');
                });
            }
            
            // Delegated events for dynamically added elements
            $(document).on('click', '.pv-view-album', this.viewAlbumDetails.bind(this));
            $(document).on('click', '.pv-album-card', this.handleAlbumCardClick.bind(this));
            $(document).on('click', '.pv-album-action', this.handleQuickAction.bind(this));
            
            // Album detail modal actions
            if ($('#pv-edit-album').length) {
                $('#pv-edit-album').on('click', this.editAlbum.bind(this));
            }
            
            if ($('#pv-delete-album').length) {
                $('#pv-delete-album').on('click', this.deleteAlbum.bind(this));
            }
            
            if ($('#pv-duplicate-album').length) {
                $('#pv-duplicate-album').on('click', this.duplicateAlbum.bind(this));
            }
            
            if ($('#pv-add-images-to-album').length) {
                $('#pv-add-images-to-album').on('click', this.openImageSelector.bind(this));
            }
            
            if ($('#pv-share-album').length) {
                $('#pv-share-album').on('click', this.shareAlbum.bind(this));
            }
            
            // Image actions (delegated)
            $(document).on('click', '.pv-remove-image-from-album', this.removeImageFromAlbum.bind(this));
            $(document).on('click', '.pv-set-cover-image', this.setCoverImage.bind(this));
            
            // Modal close buttons
            $('.pv-modal-close').on('click', function() {
                $(this).closest('.pv-modal').fadeOut(300);
            });
            
            // Click outside modal to close
            $('.pv-modal').on('click', function(e) {
                if ($(e.target).hasClass('pv-modal')) {
                    $(this).fadeOut(300);
                }
            });
            
            // Keyboard events
            $(document).on('keydown', this.handleKeyPress.bind(this));
            
            // Search functionality (if search box exists)
            if ($('#pv-album-search').length) {
                $('#pv-album-search').on('input', this.debounce(this.searchAlbums.bind(this), 300));
            }
            
            // View toggle (if exists)
            if ($('.pv-view-toggle').length) {
                $('.pv-view-toggle').on('click', this.toggleView.bind(this));
            }
            
            // Bulk actions (if exists)
            if ($('#pv-bulk-delete').length) {
                $('#pv-bulk-delete').on('click', this.bulkDeleteAlbums.bind(this));
            }
        },

        loadAlbums: function() {
            this.showLoading(true);
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_albums',
                    nonce: photoVault.nonce
                },
                success: (response) => {
                    this.showLoading(false);
                    
                    if (response.success && response.data) {
                        this.displayAlbums(response.data);
                    } else {
                        this.showEmptyState();
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showNotification(photoVault.i18n.errorLoadingAlbums, 'error');
                }
            });
        },

        displayAlbums: function(albums) {
            const $grid = $('#pv-albums-grid');
            $grid.empty();

            if (!albums || albums.length === 0) {
                this.showEmptyState();
                return;
            }

            albums.forEach((album) => {
                const coverImage = album.cover_image_url || photoVault.defaultCover;
                const imageCount = album.image_count || 0;

                const html = `
                    <div class="pv-album-card" data-album-id="${album.id}">
                        <div class="pv-album-cover">
                            <img src="${coverImage}" alt="${this.escapeHtml(album.name)}" loading="lazy">
                            <div class="pv-album-overlay">
                                <button class="button button-primary pv-view-album" data-album-id="${album.id}">
                                    <span class="dashicons dashicons-visibility"></span>
                                    ${photoVault.i18n.viewAlbum}
                                </button>
                            </div>
                            <div class="pv-album-badge">
                                <span class="dashicons dashicons-${this.getVisibilityIcon(album.visibility)}"></span>
                            </div>
                        </div>
                        <div class="pv-album-info">
                            <h3 class="pv-album-name">${this.escapeHtml(album.name)}</h3>
                            <p class="pv-album-description">${this.escapeHtml(album.description || '')}</p>
                            <div class="pv-album-meta">
                                <span class="pv-album-count">
                                    <span class="dashicons dashicons-format-gallery"></span>
                                    ${imageCount} ${photoVault.i18n.images}
                                </span>
                                <span class="pv-album-date">${album.created_at || ''}</span>
                            </div>
                        </div>
                        <div class="pv-album-actions">
                            <button class="pv-album-action" data-action="edit" data-album-id="${album.id}" title="${photoVault.i18n.edit}">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="pv-album-action" data-action="duplicate" data-album-id="${album.id}" title="${photoVault.i18n.duplicate}">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                            <button class="pv-album-action pv-danger" data-action="delete" data-album-id="${album.id}" title="${photoVault.i18n.delete}">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                `;
                $grid.append(html);
            });
        },

        handleQuickAction: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const action = $(e.currentTarget).data('action');
            const albumId = $(e.currentTarget).data('album-id');
            
            this.currentAlbumId = albumId;
            
            switch(action) {
                case 'edit':
                    this.editAlbum();
                    break;
                case 'duplicate':
                    this.duplicateAlbum();
                    break;
                case 'delete':
                    this.deleteAlbum();
                    break;
            }
        },

        handleAlbumCardClick: function(e) {
            // Don't trigger if clicking on actions or view button
            if ($(e.target).closest('.pv-album-actions, .pv-view-album').length) {
                return;
            }
            
            const albumId = $(e.currentTarget).data('album-id');
            if (albumId) {
                this.viewAlbumDetails({currentTarget: {dataset: {albumId: albumId}}});
            }
        },

        viewAlbumDetails: function(e) {
            const albumId = e.currentTarget.dataset.albumId;
            this.currentAlbumId = albumId;
            
            this.showLoading(true);
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_album_details',
                    nonce: photoVault.nonce,
                    album_id: albumId
                },
                success: (response) => {
                    this.showLoading(false);
                    
                    if (response.success && response.data) {
                        this.displayAlbumDetails(response.data);
                        this.loadAlbumImages(albumId);
                        $('#pv-album-detail-modal').fadeIn(300);
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorLoadingAlbum, 'error');
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showNotification(photoVault.i18n.errorLoadingAlbum, 'error');
                }
            });
        },

        displayAlbumDetails: function(album) {
            $('#pv-album-detail-name').text(album.name);
            $('#pv-album-detail-description').text(album.description || '');
            $('#pv-album-detail-count').html(`
                <span class="dashicons dashicons-format-gallery"></span>
                ${album.image_count} ${photoVault.i18n.images}
            `);
            $('#pv-album-detail-date').text(album.created_at);
            
            // Only update visibility if element exists
            if ($('#pv-album-detail-visibility').length) {
                $('#pv-album-detail-visibility').html(`
                    <span class="dashicons dashicons-${this.getVisibilityIcon(album.visibility)}"></span>
                    ${album.visibility}
                `);
            }
        },

        loadAlbumImages: function(albumId) {
            const $imagesGrid = $('#pv-album-images');
            $imagesGrid.html('<div class="pv-loading"><span class="spinner is-active"></span></div>');
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_album_images',
                    nonce: photoVault.nonce,
                    album_id: albumId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.displayAlbumImages(response.data);
                    } else {
                        $imagesGrid.html('<div class="pv-empty-state"><p>' + photoVault.i18n.noImagesInAlbum + '</p></div>');
                    }
                },
                error: () => {
                    $imagesGrid.html('<div class="pv-error-state"><p>' + photoVault.i18n.errorLoadingImages + '</p></div>');
                }
            });
        },

        displayAlbumImages: function(images) {
            const $imagesGrid = $('#pv-album-images');
            $imagesGrid.empty();

            if (!images || images.length === 0) {
                $imagesGrid.html('<div class="pv-empty-state"><p>' + photoVault.i18n.noImagesInAlbum + '</p></div>');
                return;
            }

            images.forEach((image) => {
                const html = `
                    <div class="pv-album-image" data-image-id="${image.id}" draggable="true">
                        <img src="${image.thumbnail_url}" alt="${this.escapeHtml(image.title)}" loading="lazy">
                        <div class="pv-image-overlay">
                            <button class="pv-image-action pv-set-cover-image" data-image-id="${image.id}" title="${photoVault.i18n.setCover}">
                                <span class="dashicons dashicons-star-filled"></span>
                            </button>
                            <button class="pv-image-action pv-view-image" data-image-id="${image.id}" title="${photoVault.i18n.view}">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button class="pv-image-action pv-remove-image-from-album" data-image-id="${image.id}" title="${photoVault.i18n.remove}">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                        <div class="pv-image-title">${this.escapeHtml(image.title)}</div>
                    </div>
                `;
                $imagesGrid.append(html);
            });

            this.initImageSortable();
        },

        openCreateModal: function(e) {
            e.preventDefault();
            this.isEditMode = false;
            this.currentAlbumId = null;
            
            $('#pv-album-modal-title').text(photoVault.i18n.createNewAlbum);
            $('#pv-album-name').val('');
            $('#pv-album-description').val('');
            $('#pv-album-visibility').val('private');
            
            $('#pv-album-modal').fadeIn(300);
            $('#pv-album-name').focus();
        },

        saveAlbum: function() {
            const name = $('#pv-album-name').val().trim();
            const description = $('#pv-album-description').val().trim();
            const visibility = $('#pv-album-visibility').val();

            if (!name) {
                this.showNotification(photoVault.i18n.albumNameRequired, 'warning');
                $('#pv-album-name').focus();
                return;
            }

            const $btn = $('#pv-save-album');
            const originalText = $btn.text();
            $btn.prop('disabled', true).html('<span class="spinner is-active"></span> ' + photoVault.i18n.saving);

            const action = this.isEditMode ? 'pv_update_album' : 'pv_create_album';
            const data = {
                action: action,
                nonce: photoVault.nonce,
                name: name,
                description: description,
                visibility: visibility
            };

            if (this.isEditMode && this.currentAlbumId) {
                data.album_id = this.currentAlbumId;
            }

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.closeModal('#pv-album-modal');
                        this.loadAlbums();
                        this.showNotification(response.data.message, 'success');
                        
                        if (response.data.album_id) {
                            this.currentAlbumId = response.data.album_id;
                        }
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorSavingAlbum, 'error');
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorSavingAlbum, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        editAlbum: function() {
            if (!this.currentAlbumId) return;

            this.isEditMode = true;
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_album_details',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        const album = response.data;
                        
                        $('#pv-album-modal-title').text(photoVault.i18n.editAlbum);
                        $('#pv-album-name').val(album.name);
                        $('#pv-album-description').val(album.description || '');
                        $('#pv-album-visibility').val(album.visibility);
                        
                        $('#pv-album-detail-modal').fadeOut(300);
                        $('#pv-album-modal').fadeIn(300);
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorLoadingAlbum, 'error');
                }
            });
        },

        deleteAlbum: function() {
            if (!this.currentAlbumId) return;

            if (!confirm(photoVault.i18n.deleteAlbumConfirm)) {
                return;
            }

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_delete_album',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId
                },
                success: (response) => {
                    if (response.success) {
                        this.closeModal('#pv-album-detail-modal');
                        this.loadAlbums();
                        this.showNotification(response.data.message, 'success');
                        this.currentAlbumId = null;
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorDeletingAlbum, 'error');
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorDeletingAlbum, 'error');
                }
            });
        },

        duplicateAlbum: function() {
            if (!this.currentAlbumId) return;

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_duplicate_album',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadAlbums();
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorDuplicatingAlbum, 'error');
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorDuplicatingAlbum, 'error');
                }
            });
        },

        removeImageFromAlbum: function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!confirm(photoVault.i18n.removeImageConfirm)) {
                return;
            }

            const imageId = $(e.currentTarget).data('image-id');
            const $imageItem = $(e.currentTarget).closest('.pv-album-image');

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_remove_image_from_album',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId,
                    image_id: imageId
                },
                success: (response) => {
                    if (response.success) {
                        $imageItem.fadeOut(300, function() {
                            $(this).remove();
                            
                            if ($('#pv-album-images .pv-album-image').length === 0) {
                                $('#pv-album-images').html('<div class="pv-empty-state"><p>' + photoVault.i18n.noImagesInAlbum + '</p></div>');
                            }
                        });
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorRemovingImage, 'error');
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorRemovingImage, 'error');
                }
            });
        },

        setCoverImage: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const imageId = $(e.currentTarget).data('image-id');

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_set_album_cover',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId,
                    image_id: imageId
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(response.data?.message || photoVault.i18n.errorSettingCover, 'error');
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorSettingCover, 'error');
                }
            });
        },

        initImageSortable: function() {
            const $grid = $('#pv-album-images');
            
            // Check if jQuery UI sortable is available
            if (typeof $.fn.sortable !== 'function') {
                return;
            }
            
            $grid.sortable({
                items: '.pv-album-image',
                cursor: 'move',
                opacity: 0.7,
                update: () => {
                    this.saveImageOrder();
                }
            });
        },

        saveImageOrder: function() {
            const imageOrder = [];
            $('#pv-album-images .pv-album-image').each(function() {
                imageOrder.push($(this).data('image-id'));
            });

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_reorder_album_images',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId,
                    image_order: JSON.stringify(imageOrder)
                }
            });
        },

        searchAlbums: function(e) {
            const searchTerm = $(e.target).val().trim();
            
            if (searchTerm.length < 2) {
                this.loadAlbums();
                return;
            }

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_search_albums',
                    nonce: photoVault.nonce,
                    search: searchTerm
                },
                success: (response) => {
                    if (response.success) {
                        this.displayAlbums(response.data);
                    }
                }
            });
        },

        openImageSelector: function() {
            this.showNotification(photoVault.i18n.featureComingSoon, 'info');
        },

        shareAlbum: function() {
            this.showNotification(photoVault.i18n.featureComingSoon, 'info');
        },

        toggleView: function() {
            // Placeholder for view toggle functionality
        },

        bulkDeleteAlbums: function() {
            // Placeholder for bulk delete functionality
        },

        closeModal: function(selector) {
            $(selector).fadeOut(300);
        },

        showLoading: function(show) {
            if (show) {
                $('#pv-albums-loading').show();
            } else {
                $('#pv-albums-loading').hide();
            }
        },

        showEmptyState: function() {
            const self = this;
            $('#pv-albums-grid').html(`
                <div class="pv-empty-state">
                    <span class="dashicons dashicons-format-gallery"></span>
                    <h3>${photoVault.i18n.noAlbums}</h3>
                    <p>${photoVault.i18n.createFirstAlbum || 'Create your first album to get started'}</p>
                    <button class="button button-primary" id="pv-create-first-album">
                        ${photoVault.i18n.createNewAlbum}
                    </button>
                </div>
            `);
            
            $('#pv-create-first-album').on('click', function(e) {
                self.openCreateModal(e);
            });
        },

        showNotification: function(message, type) {
            type = type || 'info';
            
            const iconMap = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };
            
            const $notification = $(`
                <div class="pv-notification pv-notification-${type}">
                    <span class="dashicons dashicons-${iconMap[type]}"></span>
                    <span class="pv-notification-message">${this.escapeHtml(message)}</span>
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.addClass('pv-notification-show');
            }, 10);
            
            setTimeout(() => {
                $notification.removeClass('pv-notification-show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        },

        getVisibilityIcon: function(visibility) {
            const icons = {
                private: 'lock',
                shared: 'groups',
                public: 'visibility'
            };
            return icons[visibility] || 'lock';
        },

        handleKeyPress: function(e) {
            if (e.key === 'Escape') {
                $('.pv-modal:visible').fadeOut(300);
            }
        },

        escapeHtml: function(text) {
            if (!text) return '';
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        AlbumManager.init();
    });

})(jQuery);