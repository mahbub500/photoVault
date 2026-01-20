/**
 * PhotoVault - Albums Management System
 * Organized and optimized code structure
 * 
 * @package PhotoVault
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // ============================================================================
    // ALBUM MANAGER - Main Module
    // ============================================================================
    
    const AlbumManager = {
        // Properties
        currentAlbumId: null,
        isEditMode: false,
        selectedImages: [],
        currentView: 'grid',

        /**
         * Initialize Album Manager
         */
        init: function() {
            this.bindEvents();
            this.loadAlbums();
        },

        // ========================================================================
        // EVENT BINDING
        // ========================================================================

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            const self = this;
            
            // Album creation
            this._bindElement('#pv-create-album-btn', 'click', this.openCreateModal);
            
            // Modal actions
            this._bindElement('#pv-save-album', 'click', this.saveAlbum);
            this._bindElement('#pv-cancel-album', 'click', () => this.closeModal('#pv-album-modal'));
            
            // Album detail actions
            this._bindElement('#pv-edit-album', 'click', this.editAlbum);
            this._bindElement('#pv-delete-album', 'click', this.deleteAlbum);
            this._bindElement('#pv-duplicate-album', 'click', this.duplicateAlbum);
            this._bindElement('#pv-add-images-to-album', 'click', this.openImageSelector);
            this._bindElement('#pv-share-album', 'click', this.shareAlbum);
            
            // Delegated events for dynamic content
            $(document)
                .on('click', '.pv-view-album', this.viewAlbumDetails.bind(this))
                .on('click', '.pv-album-card', this.handleAlbumCardClick.bind(this))
                .on('click', '.pv-album-action', this.handleQuickAction.bind(this))
                .on('click', '.pv-remove-image-from-album', this.removeImageFromAlbum.bind(this))
                .on('click', '.pv-set-cover-image', this.setCoverImage.bind(this))
                .on('keydown', this.handleKeyPress.bind(this));
            
            // Modal close handlers
            $('.pv-modal-close').on('click', function() {
                $(this).closest('.pv-modal').fadeOut(300);
            });
            
            $('.pv-modal').on('click', function(e) {
                if ($(e.target).hasClass('pv-modal')) {
                    $(this).fadeOut(300);
                }
            });
            
            // Search functionality
            this._bindElement('#pv-album-search', 'input', 
                this.debounce(this.searchAlbums, 300));
            
            // Optional features
            this._bindElement('.pv-view-toggle', 'click', this.toggleView);
            this._bindElement('#pv-bulk-delete', 'click', this.bulkDeleteAlbums);
        },

        /**
         * Helper: Bind element if it exists
         */
        _bindElement: function(selector, event, handler) {
            const $element = $(selector);
            if ($element.length) {
                $element.on(event, handler.bind(this));
            }
        },

        // ========================================================================
        // ALBUM DISPLAY & MANAGEMENT
        // ========================================================================

        /**
         * Load all albums from server
         */
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

        /**
         * Display albums in grid
         */
        displayAlbums: function(albums) {
            const $grid = $('#pv-albums-grid');
            $grid.empty();

            if (!albums || albums.length === 0) {
                this.showEmptyState();
                return;
            }

            albums.forEach((album) => {
                $grid.append(this._createAlbumCard(album));
            });
        },

        /**
         * Create album card HTML
         */
        _createAlbumCard: function(album) {
            const coverImage = album.cover_image_url || photoVault.defaultCover;
            const imageCount = album.image_count || 0;

            return `
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
                        ${this._createQuickActions(album.id)}
                    </div>
                </div>
            `;
        },

        /**
         * Create quick action buttons
         */
        _createQuickActions: function(albumId) {
            return `
                <button class="pv-album-action" data-action="edit" data-album-id="${albumId}" 
                        title="${photoVault.i18n.edit}">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button class="pv-album-action" data-action="duplicate" data-album-id="${albumId}" 
                        title="${photoVault.i18n.duplicate}">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>
                <button class="pv-album-action pv-danger" data-action="delete" data-album-id="${albumId}" 
                        title="${photoVault.i18n.delete}">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            `;
        },

        // ========================================================================
        // ALBUM ACTIONS
        // ========================================================================

        /**
         * Handle quick action buttons
         */
        handleQuickAction: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const action = $(e.currentTarget).data('action');
            const albumId = $(e.currentTarget).data('album-id');
            
            this.currentAlbumId = albumId;
            
            const actions = {
                edit: this.editAlbum,
                duplicate: this.duplicateAlbum,
                delete: this.deleteAlbum
            };
            
            if (actions[action]) {
                actions[action].call(this);
            }
        },

        /**
         * Handle album card click
         */
        handleAlbumCardClick: function(e) {
            if ($(e.target).closest('.pv-album-actions, .pv-view-album').length) {
                return;
            }
            
            const albumId = $(e.currentTarget).data('album-id');
            if (albumId) {
                this.viewAlbumDetails({currentTarget: {dataset: {albumId: albumId}}});
            }
        },

        /**
         * View album details
         */
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
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorLoadingAlbum, 
                            'error'
                        );
                    }
                },
                error: () => {
                    this.showLoading(false);
                    this.showNotification(photoVault.i18n.errorLoadingAlbum, 'error');
                }
            });
        },

        /**
         * Display album details in modal
         */
        displayAlbumDetails: function(album) {
            $('#pv-album-detail-name').text(album.name);
            $('#pv-album-detail-description').text(album.description || '');
            $('#pv-album-detail-count').html(`
                <span class="dashicons dashicons-format-gallery"></span>
                ${album.image_count} ${photoVault.i18n.images}
            `);
            $('#pv-album-detail-date').text(album.created_at);
            
            const $visibility = $('#pv-album-detail-visibility');
            if ($visibility.length) {
                $visibility.html(`
                    <span class="dashicons dashicons-${this.getVisibilityIcon(album.visibility)}"></span>
                    ${album.visibility}
                `);
            }
        },

        // ========================================================================
        // ALBUM CRUD OPERATIONS
        // ========================================================================

        /**
         * Open create album modal
         */
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

        /**
         * Save album (create or update)
         */
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
            $btn.prop('disabled', true)
                .html('<span class="spinner is-active"></span> ' + photoVault.i18n.saving);

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
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorSavingAlbum, 
                            'error'
                        );
                    }
                    $btn.prop('disabled', false).text(originalText);
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorSavingAlbum, 'error');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Edit album
         */
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

        /**
         * Delete album
         */
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
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorDeletingAlbum, 
                            'error'
                        );
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorDeletingAlbum, 'error');
                }
            });
        },

        /**
         * Duplicate album
         */
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
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorDuplicatingAlbum, 
                            'error'
                        );
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorDuplicatingAlbum, 'error');
                }
            });
        },

        // ========================================================================
        // IMAGE MANAGEMENT
        // ========================================================================

        /**
         * Load album images
         */
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
                        $imagesGrid.html(
                            '<div class="pv-empty-state"><p>' + 
                            photoVault.i18n.noImagesInAlbum + 
                            '</p></div>'
                        );
                    }
                },
                error: () => {
                    $imagesGrid.html(
                        '<div class="pv-error-state"><p>' + 
                        photoVault.i18n.errorLoadingImages + 
                        '</p></div>'
                    );
                }
            });
        },

        /**
         * Display album images
         */
        displayAlbumImages: function(images) {
            const $imagesGrid = $('#pv-album-images');
            $imagesGrid.empty();

            if (!images || images.length === 0) {
                $imagesGrid.html(
                    '<div class="pv-empty-state"><p>' + 
                    photoVault.i18n.noImagesInAlbum + 
                    '</p></div>'
                );
                return;
            }

            images.forEach((image) => {
                $imagesGrid.append(this._createImageCard(image));
            });

            this.initImageSortable();
        },

        /**
         * Create image card HTML
         */
        _createImageCard: function(image) {
            return `
                <div class="pv-album-image" data-image-id="${image.id}" draggable="true">
                    <img src="${image.thumbnail_url}" 
                         alt="${this.escapeHtml(image.title)}" 
                         loading="lazy">
                    <div class="pv-image-overlay">
                        <button class="pv-image-action pv-set-cover-image" 
                                data-image-id="${image.id}" 
                                title="${photoVault.i18n.setCover}">
                            <span class="dashicons dashicons-star-filled"></span>
                        </button>
                        <button class="pv-image-action pv-view-image" 
                                data-image-id="${image.id}" 
                                title="${photoVault.i18n.view}">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button class="pv-image-action pv-remove-image-from-album" 
                                data-image-id="${image.id}" 
                                title="${photoVault.i18n.remove}">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    <div class="pv-image-title">${this.escapeHtml(image.title)}</div>
                </div>
            `;
        },

        /**
         * Remove image from album
         */
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
                                $('#pv-album-images').html(
                                    '<div class="pv-empty-state"><p>' + 
                                    photoVault.i18n.noImagesInAlbum + 
                                    '</p></div>'
                                );
                            }
                        });
                        this.showNotification(response.data.message, 'success');
                    } else {
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorRemovingImage, 
                            'error'
                        );
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorRemovingImage, 'error');
                }
            });
        },

        /**
         * Set album cover image
         */
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
                        this.showNotification(
                            response.data?.message || photoVault.i18n.errorSettingCover, 
                            'error'
                        );
                    }
                },
                error: () => {
                    this.showNotification(photoVault.i18n.errorSettingCover, 'error');
                }
            });
        },

        /**
         * Initialize image sortable
         */
        initImageSortable: function() {
            const $grid = $('#pv-album-images');
            
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

        /**
         * Save image order
         */
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

        // ========================================================================
        // SEARCH & FILTER
        // ========================================================================

        /**
         * Search albums
         */
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

        // ========================================================================
        // UI HELPERS
        // ========================================================================

        /**
         * Open image selector (handled by ImageSelector module)
         */
        openImageSelector: function(e) {
            console.log('openImageSelector called', this.currentAlbumId);
            
            if (e) e.preventDefault();
            
            if (!this.currentAlbumId) {
                this.showNotification('Please select an album first', 'warning');
                return;
            }
            
            if (window.ImageSelector) {
                console.log('Calling ImageSelector.open');
                window.ImageSelector.open(this.currentAlbumId);
            } else {
                console.error('ImageSelector not available');
                this.showNotification('Image selector not available', 'error');
            }
        },

        /**
         * Share album (placeholder)
         */
        shareAlbum: function() {
            this.showNotification(photoVault.i18n.featureComingSoon, 'info');
        },

        /**
         * Toggle view (placeholder)
         */
        toggleView: function() {
            // Placeholder for future grid/list view toggle
        },

        /**
         * Bulk delete albums (placeholder)
         */
        bulkDeleteAlbums: function() {
            // Placeholder for future bulk delete functionality
        },

        /**
         * Close modal
         */
        closeModal: function(selector) {
            $(selector).fadeOut(300);
        },

        /**
         * Show/hide loading indicator
         */
        showLoading: function(show) {
            $('#pv-albums-loading').toggle(show);
        },

        /**
         * Show empty state
         */
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

        /**
         * Show notification toast
         */
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
            
            setTimeout(() => $notification.addClass('pv-notification-show'), 10);
            setTimeout(() => {
                $notification.removeClass('pv-notification-show');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyPress: function(e) {
            if (e.key === 'Escape') {
                $('.pv-modal:visible').fadeOut(300);
            }
        },

        // ========================================================================
        // UTILITY FUNCTIONS
        // ========================================================================

        /**
         * Get visibility icon
         */
        getVisibilityIcon: function(visibility) {
            const icons = {
                private: 'lock',
                shared: 'groups',
                public: 'visibility'
            };
            return icons[visibility] || 'lock';
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, (m) => map[m]);
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        }
    };

    // Make AlbumManager globally available
    window.AlbumManager = AlbumManager;

    // ============================================================================
    // IMAGE SELECTOR MODULE
    // ============================================================================
    
    const ImageSelector = {
        // Properties
        currentAlbumId: null,
        selectedImageIds: [],
        allImages: [],
        currentPage: 1,
        perPage: 20,
        totalImages: 0,

        /**
         * Initialize image selector
         */
        init: function(albumId) {
            this.currentAlbumId = albumId;
            this.selectedImageIds = [];
            this.currentPage = 1;
            // Don't load images here, load after modal is created
        },

        /**
         * Open image selector modal
         */
        open: function(albumId) {
            console.log('Opening image selector for album:', albumId);
            
            this.currentAlbumId = albumId;
            this.selectedImageIds = [];
            this.currentPage = 1;
            
            if (!$('#pv-image-selector-modal').length) {
                console.log('Creating image selector modal...');
                this.createModal();
            }
            
            // Load images after modal exists
            this.loadAvailableImages();
            
            $('#pv-image-selector-modal').fadeIn(300);
        },

        /**
         * Create modal HTML
         */
        createModal: function() {
            const modalHtml = `
                <div id="pv-image-selector-modal" class="pv-modal" style="display:none;">
                    <div class="pv-modal-content pv-image-selector-content">
                        <span class="pv-modal-close">&times;</span>
                        <h2>${photoVault.i18n.selectImages || 'Select Images'}</h2>
                        
                        <div class="pv-image-selector-toolbar">
                            <input type="text" id="pv-image-selector-search" 
                                   placeholder="${photoVault.i18n.searchImages || 'Search images...'}"
                                   class="pv-search-input">
                            <div class="pv-image-selector-stats">
                                <span id="pv-selected-count">0 selected</span>
                            </div>
                        </div>
                        
                        <div id="pv-image-selector-grid" class="pv-image-selector-grid">
                            <div class="pv-loading">
                                <span class="spinner is-active"></span>
                                <p>Loading images...</p>
                            </div>
                        </div>
                        
                        <div class="pv-image-selector-pagination">
                            <button id="pv-prev-page" class="button" disabled>
                                <span class="dashicons dashicons-arrow-left-alt2"></span> Previous
                            </button>
                            <span id="pv-page-info">Page 1</span>
                            <button id="pv-next-page" class="button" disabled>
                                Next <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                        
                        <div class="pv-modal-actions">
                            <button class="button" id="pv-cancel-image-selection">
                                ${photoVault.i18n.cancel || 'Cancel'}
                            </button>
                            <button class="button button-primary" id="pv-add-selected-images">
                                <span class="dashicons dashicons-plus"></span>
                                ${photoVault.i18n.addSelected || 'Add Selected Images'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            this.bindModalEvents();
        },

        /**
         * Bind modal events
         */
        bindModalEvents: function() {
            const self = this;
            
            // Close handlers
            $('#pv-image-selector-modal .pv-modal-close, #pv-cancel-image-selection')
                .on('click', function() {
                    $('#pv-image-selector-modal').fadeOut(300);
                    self.selectedImageIds = [];
                });
            
            // Add images
            $('#pv-add-selected-images').on('click', () => this.addSelectedImages());
            
            // Image selection (delegated)
            $(document).on('click', '.pv-selector-image', function() {
                self.toggleImageSelection($(this));
            });
            
            // Search
            $('#pv-image-selector-search').on('input', this.debounce(function() {
                self.currentPage = 1;
                self.loadAvailableImages();
            }, 300));
            
            // Pagination
            $('#pv-prev-page').on('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadAvailableImages();
                }
            });
            
            $('#pv-next-page').on('click', () => {
                const totalPages = Math.ceil(this.totalImages / this.perPage);
                if (this.currentPage < totalPages) {
                    this.currentPage++;
                    this.loadAvailableImages();
                }
            });
            
            // Close on outside click
            $('#pv-image-selector-modal').on('click', function(e) {
                if ($(e.target).is('#pv-image-selector-modal')) {
                    $(this).fadeOut(300);
                    self.selectedImageIds = [];
                }
            });
        },

        /**
         * Load available images
         */
        loadAvailableImages: function() {
            const $grid = $('#pv-image-selector-grid');
            
            if (!$grid.length) {
                console.error('Image selector grid not found');
                return;
            }
            
            $grid.html('<div class="pv-loading"><span class="spinner is-active"></span></div>');
            
            const $searchInput = $('#pv-image-selector-search');
            const searchTerm = $searchInput.length ? $searchInput.val().trim() : '';
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_available_images',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: searchTerm
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.allImages = response.data.images || [];
                        this.totalImages = response.data.total || 0;
                        this.displayImages();
                        this.updatePagination();
                    } else {
                        $grid.html('<div class="pv-empty-state"><p>No images available</p></div>');
                    }
                },
                error: () => {
                    $grid.html('<div class="pv-error-state"><p>Error loading images</p></div>');
                }
            });
        },

        /**
         * Display images
         */
        displayImages: function() {
            const $grid = $('#pv-image-selector-grid');
            $grid.empty();
            
            if (this.allImages.length === 0) {
                $grid.html('<div class="pv-empty-state"><p>No images available</p></div>');
                return;
            }
            
            this.allImages.forEach((image) => {
                const isSelected = this.selectedImageIds.includes(image.id);
                const html = `
                    <div class="pv-selector-image ${isSelected ? 'pv-image-selected' : ''}" 
                         data-image-id="${image.id}">
                        <img src="${image.thumbnail_url}" 
                             alt="${this.escapeHtml(image.title)}" 
                             loading="lazy">
                        <div class="pv-selector-overlay">
                            <span class="pv-selector-checkbox ${isSelected ? 'checked' : ''}">
                                <span class="dashicons dashicons-yes"></span>
                            </span>
                        </div>
                        <div class="pv-selector-title">${this.escapeHtml(image.title)}</div>
                    </div>
                `;
                $grid.append(html);
            });
        },

        /**
         * Toggle image selection
         */
        toggleImageSelection: function($element) {
            const imageId = parseInt($element.data('image-id'));
            const index = this.selectedImageIds.indexOf(imageId);
            
            if (index > -1) {
                this.selectedImageIds.splice(index, 1);
                $element.removeClass('pv-image-selected');
                $element.find('.pv-selector-checkbox').removeClass('checked');
            } else {
                this.selectedImageIds.push(imageId);
                $element.addClass('pv-image-selected');
                $element.find('.pv-selector-checkbox').addClass('checked');
            }
            
            this.updateSelectedCount();
        },

        /**
         * Update selected count
         */
        updateSelectedCount: function() {
            const count = this.selectedImageIds.length;
            const text = count === 1 ? '1 image selected' : `${count} images selected`;
            $('#pv-selected-count').text(text);
            $('#pv-add-selected-images').prop('disabled', count === 0);
        },

        /**
         * Update pagination
         */
        updatePagination: function() {
            const totalPages = Math.ceil(this.totalImages / this.perPage);
            $('#pv-prev-page').prop('disabled', this.currentPage <= 1);
            $('#pv-next-page').prop('disabled', this.currentPage >= totalPages);
            $('#pv-page-info').text(`Page ${this.currentPage} of ${totalPages}`);
        },

        /**
         * Add selected images to album
         */
        addSelectedImages: function() {
            if (this.selectedImageIds.length === 0) return;
            
            const $btn = $('#pv-add-selected-images');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner is-active"></span> Adding...');
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_add_multiple_images',
                    nonce: photoVault.nonce,
                    album_id: this.currentAlbumId,
                    image_ids: this.selectedImageIds
                },
                success: (response) => {
                    if (response.success) {
                        $('#pv-image-selector-modal').fadeOut(300);
                        
                        if (window.AlbumManager) {
                            window.AlbumManager.showNotification(response.data.message, 'success');
                            if (window.AlbumManager.currentAlbumId) {
                                window.AlbumManager.loadAlbumImages(window.AlbumManager.currentAlbumId);
                            }
                        }
                        
                        this.selectedImageIds = [];
                    } else {
                        alert(response.data?.message || 'Error adding images');
                    }
                    $btn.prop('disabled', false).html(originalHtml);
                },
                error: () => {
                    alert('Error adding images to album');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        /**
         * Utility: Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
            return String(text).replace(/[&<>"']/g, (m) => map[m]);
        },

        /**
         * Utility: Debounce
         */
        debounce: function(func, wait) {
            let timeout;
            return function() {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, arguments), wait);
            };
        }
    };

    // Make ImageSelector globally available
    window.ImageSelector = ImageSelector;

    // ============================================================================
    // INITIALIZATION
    // ============================================================================
    
    $(document).ready(function() {
        AlbumManager.init();
    });

})(jQuery);