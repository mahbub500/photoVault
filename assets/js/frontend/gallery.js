/**
 * PhotoVault - Gallery Admin Page
 * File: assets/js/admin/gallery.js
 */

(function($) {
    'use strict';

    window.PhotoVaultAdmin = {
        images: [],
        currentPage: 1,
        perPage: 20,
        isLoading: false,
        hasMore: true,
        currentImageIndex: -1,
        filters: {
            search: '',
            album: '',
            tag: '',
            sort: 'date_desc'
        },

        init: function() {
            this.bindEvents();
            this.loadAlbums();
            this.loadTags();
            this.loadImages();
            this.updateStats();
        },

        bindEvents: function() {
            const self = this;

            // Search
            $('#pv-search').on('input', debounce(function() {
                self.filters.search = $(this).val();
                self.resetAndLoad();
            }, 500));

            // Album filter
            $('#pv-filter-album').on('change', function() {
                self.filters.album = $(this).val();
                self.resetAndLoad();
            });

            // Sort
            $('#pv-sort-by').on('change', function() {
                self.filters.sort = $(this).val();
                self.resetAndLoad();
            });

            // Clear filters
            $('#pv-clear-filters').on('click', function() {
                self.clearFilters();
            });

            // View mode toggle
            $('.pv-view-btn').on('click', function() {
                $('.pv-view-btn').removeClass('active');
                alert( 'test' );
                $(this).addClass('active');
                const view = $(this).data('view');
                self.setViewMode(view);
            });

            // Load more
            $('#pv-load-more-btn').on('click', function() {
                self.loadMore();
            });

            // Select all
            $('#pv-select-all').on('change', function() {
                $('.pv-image-checkbox').prop('checked', this.checked);
                self.updateBulkActions();
            });

            // Image checkbox change
            $(document).on('change', '.pv-image-checkbox', function() {
                self.updateBulkActions();
            });

            // Bulk delete
            $('#pv-bulk-delete').on('click', function() {
                self.bulkDelete();
            });

            // Bulk add to album
            $('#pv-bulk-add-album').on('click', function() {
                self.bulkAddToAlbum();
            });

            // Image card click
            $(document).on('click', '.pv-image-card', function(e) {
                if (!$(e.target).is('input[type="checkbox"]')) {
                    const imageId = $(this).data('id');
                    self.openImageDetail(imageId);
                }
            });

            // Create album from gallery
            $('#pv-create-album-btn').on('click', function() {
                if (window.PhotoVaultAlbums) {
                    window.PhotoVaultAlbums.openCreateModal();
                } else {
                    self.openCreateAlbumModal();
                }
            });

            // Image detail actions
            $('#pv-delete-image').on('click', function() {
                self.deleteImage();
            });

            $('#pv-download-image').on('click', function() {
                self.downloadImage();
            });

            $('#pv-add-tag-btn').on('click', function() {
                self.addTag();
            });

            $('#pv-share-btn').on('click', function() {
                self.shareImage();
            });

            // Image navigation
            $('#pv-prev-image').on('click', function() {
                self.navigateImage(-1);
            });

            $('#pv-next-image').on('click', function() {
                self.navigateImage(1);
            });

            // Tag filter click
            $(document).on('click', '.pv-tag-filter', function() {
                self.filters.tag = $(this).data('tag-id');
                self.resetAndLoad();
            });

            // Close modals
            $('.pv-modal-close').on('click', function() {
                $(this).closest('.pv-modal').fadeOut();
            });

            // Click outside modal
            $('.pv-modal').on('click', function(e) {
                if ($(e.target).hasClass('pv-modal')) {
                    $(this).fadeOut();
                }
            });
        },

        loadImages: function() {
            if (this.isLoading) return;
            
            const self = this;
            this.isLoading = true;
            $('#pv-loading').show();

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_images',
                    nonce: photoVault.nonce,
                    page: this.currentPage,
                    per_page: this.perPage,
                    search: this.filters.search,
                    album_id: this.filters.album,
                    tag_id: this.filters.tag,
                    sort: this.filters.sort
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        if (self.currentPage === 1) {
                            self.images = data.images || data || [];
                        } else {
                            self.images = self.images.concat(data.images || data || []);
                        }
                        
                        self.hasMore = data.has_more !== undefined ? data.has_more : self.images.length >= self.perPage;
                        
                        self.renderImages();
                        self.updateLoadMoreButton();
                    } else {
                        self.showError(response.data?.message || 'Failed to load images');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading images:', error);
                    self.showError('Failed to load images');
                },
                complete: function() {
                    self.isLoading = false;
                    $('#pv-loading').hide();
                }
            });
        },

        renderImages: function() {
            const $grid = $('#pv-images-grid');
            
            if (this.currentPage === 1) {
                $grid.empty();
            }

            if (this.images.length === 0) {
                $grid.html(`
                    <div class="pv-no-images">
                        <span class="dashicons dashicons-format-image" style="font-size: 64px; opacity: 0.3;"></span>
                        <p>${photoVault.i18n?.noImages || 'No images found'}</p>
                        <button class="button button-primary" id="pv-upload-btn-empty">
                            ${photoVault.i18n?.uploadFirst || 'Upload Your First Image'}
                        </button>
                    </div>
                `);
                return;
            }

            this.images.forEach((image, index) => {
                const thumbnail = image.thumbnail || image.url || '';
                const title = this.escapeHtml(image.title || 'Untitled');
                const date = image.upload_date ? new Date(image.upload_date).toLocaleDateString() : '';

                const $card = $(`
                    <div class="pv-image-card" data-id="${image.id}" data-index="${index}">
                        <div class="pv-image-checkbox-wrapper">
                            <input type="checkbox" class="pv-image-checkbox" value="${image.id}">
                        </div>
                        <div class="pv-image-thumbnail">
                            <img src="${thumbnail}" alt="${title}">
                        </div>
                        <div class="pv-image-info">
                            <h4>${title}</h4>
                            <span class="pv-image-date">${date}</span>
                        </div>
                    </div>
                `);
                
                $grid.append($card);
            });

            // Bind click for empty state button
            $('#pv-upload-btn-empty').on('click', function() {
                $('#pv-upload-btn').trigger('click');
            });
        },

        loadMore: function() {
            this.currentPage++;
            this.loadImages();
        },

        resetAndLoad: function() {
            this.currentPage = 1;
            this.images = [];
            this.loadImages();
        },

        updateLoadMoreButton: function() {
            const $btn = $('#pv-load-more-btn');
            if (this.hasMore && this.images.length > 0) {
                $btn.show();
            } else {
                $btn.hide();
            }
        },

        clearFilters: function() {
            this.filters = {
                search: '',
                album: '',
                tag: '',
                sort: 'date_desc'
            };
            
            $('#pv-search').val('');
            $('#pv-filter-album').val('');
            $('#pv-sort-by').val('date_desc');
            
            this.resetAndLoad();
        },

        setViewMode: function(mode) {
            const $grid = $('#pv-images-grid');
            $grid.removeClass('pv-grid-view pv-list-view');
            $grid.addClass('pv-' + mode + '-view');
        },

        loadAlbums: function() {
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_albums',
                    nonce: photoVault.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const $select = $('#pv-filter-album');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.forEach(function(album) {
                            $select.append(`<option value="${album.id}">${album.name}</option>`);
                        });
                    }
                }
            });
        },

        loadTags: function() {
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_tags',
                    nonce: photoVault.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        const $tagsList = $('#pv-tags-list');
                        $tagsList.empty();
                        
                        response.data.forEach(function(tag) {
                            $tagsList.append(`
                                <button class="pv-tag-filter" data-tag-id="${tag.id}">
                                    ${tag.name} (${tag.count || 0})
                                </button>
                            `);
                        });
                    }
                }
            });
        },

        updateStats: function() {
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_stats',
                    nonce: photoVault.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $('#pv-total-images').text(response.data.total_images || 0);
                        $('#pv-total-albums').text(response.data.total_albums || 0);
                    }
                }
            });
        },

        updateBulkActions: function() {
            const checked = $('.pv-image-checkbox:checked').length;
            
            if (checked > 0) {
                $('#pv-bulk-delete, #pv-bulk-add-album').show();
            } else {
                $('#pv-bulk-delete, #pv-bulk-add-album').hide();
            }
        },

        bulkDelete: function() {
            const imageIds = $('.pv-image-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (imageIds.length === 0) return;

            if (!confirm(`Delete ${imageIds.length} image(s)?`)) return;

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_bulk_delete',
                    nonce: photoVault.nonce,
                    image_ids: imageIds
                },
                success: function(response) {
                    if (response.success) {
                        window.PhotoVaultAdmin.resetAndLoad();
                        window.PhotoVaultAdmin.updateStats();
                    } else {
                        alert(response.data?.message || 'Failed to delete images');
                    }
                }
            });
        },

        bulkAddToAlbum: function() {
            const imageIds = $('.pv-image-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (imageIds.length === 0) return;

            const albumId = prompt('Enter album ID:');
            if (!albumId) return;

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_add_to_album',
                    nonce: photoVault.nonce,
                    image_ids: imageIds,
                    album_id: albumId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Images added to album');
                        window.PhotoVaultAdmin.resetAndLoad();
                    } else {
                        alert(response.data?.message || 'Failed to add images to album');
                    }
                }
            });
        },

        openImageDetail: function(imageId) {
            const self = this;
            this.currentImageIndex = this.images.findIndex(img => img.id == imageId);

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_image',
                    nonce: photoVault.nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderImageDetail(response.data);
                        $('#pv-detail-modal').fadeIn();
                    }
                }
            });
        },

        renderImageDetail: function(image) {
            $('#pv-detail-img').attr('src', image.url);
            $('#pv-detail-title').text(image.title || 'Untitled');
            $('#pv-detail-date').text(new Date(image.upload_date).toLocaleDateString());
            $('#pv-detail-visibility').text(image.visibility || 'private');
            
            // Render tags
            const $tagsList = $('#pv-detail-tags-list');
            $tagsList.empty();
            if (image.tags && image.tags.length > 0) {
                image.tags.forEach(tag => {
                    $tagsList.append(`<span class="pv-tag">${tag.name}</span>`);
                });
            }
            
            $('#pv-detail-modal').data('image-id', image.id);
        },

        navigateImage: function(direction) {
            this.currentImageIndex += direction;
            
            if (this.currentImageIndex < 0) {
                this.currentImageIndex = this.images.length - 1;
            } else if (this.currentImageIndex >= this.images.length) {
                this.currentImageIndex = 0;
            }
            
            const image = this.images[this.currentImageIndex];
            if (image) {
                this.openImageDetail(image.id);
            }
        },

        deleteImage: function() {
            if (!confirm('Delete this image?')) return;

            const imageId = $('#pv-detail-modal').data('image-id');

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_delete_image',
                    nonce: photoVault.nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        $('#pv-detail-modal').fadeOut();
                        window.PhotoVaultAdmin.resetAndLoad();
                        window.PhotoVaultAdmin.updateStats();
                    } else {
                        alert(response.data?.message || 'Failed to delete image');
                    }
                }
            });
        },

        downloadImage: function() {
            const imageUrl = $('#pv-detail-img').attr('src');
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = 'image.jpg';
            link.click();
        },

        addTag: function() {
            const tag = $('#pv-add-tag-input').val().trim();
            if (!tag) return;

            const imageId = $('#pv-detail-modal').data('image-id');

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_add_image_tag',
                    nonce: photoVault.nonce,
                    image_id: imageId,
                    tag: tag
                },
                success: function(response) {
                    if (response.success) {
                        $('#pv-add-tag-input').val('');
                        window.PhotoVaultAdmin.openImageDetail(imageId);
                    }
                }
            });
        },

        shareImage: function() {
            const imageId = $('#pv-detail-modal').data('image-id');
            const userId = $('#pv-share-user').val();

            if (!userId) {
                alert('Please select a user');
                return;
            }

            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_share_item',
                    nonce: photoVault.nonce,
                    item_type: 'image',
                    item_id: imageId,
                    share_with: userId,
                    permission: 'view'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Image shared successfully');
                    } else {
                        alert(response.data?.message || 'Failed to share image');
                    }
                }
            });
        },

        openCreateAlbumModal: function() {
            $('#pv-album-name').val('');
            $('#pv-album-description').val('');
            $('#pv-album-visibility').val('private');
            $('#pv-album-modal').data('album-id', '').fadeIn();
        },

        showError: function(message) {
            alert(message);
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        refresh: function() {
            this.resetAndLoad();
            this.updateStats();
            this.loadAlbums();
            this.loadTags();
        }
    };

    // Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Only initialize if we're on the gallery page
        if ($('#pv-images-grid').length > 0) {
            window.PhotoVaultAdmin.init();
        }
    });

})(jQuery);