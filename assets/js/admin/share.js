/**
 * PhotoVault - Album Sharing Module
 * Add this to your albums.js or create a separate share.js file
 */

(function($) {
    'use strict';

    const ShareManager = {
        currentItemType: null,
        currentItemId: null,
        currentShares: [],
        searchTimeout: null,

        /**
         * Open share modal
         */
        open: function(itemType, itemId, itemName) {
            this.currentItemType = itemType;
            this.currentItemId = itemId;
            
            if (!$('#pv-share-modal').length) {
                this.createModal();
            }
            
            $('#pv-share-modal-item-name').text(itemName);
            this.loadExistingShares();
            $('#pv-share-modal').fadeIn(300);
        },

        /**
         * Create share modal HTML
         */
        createModal: function() {
            const modalHtml = `
                <div id="pv-share-modal" class="pv-modal" style="display:none;">
                    <div class="pv-modal-content pv-share-modal-content">
                        <span class="pv-modal-close">&times;</span>
                        <h2>
                            <span class="dashicons dashicons-share"></span>
                            ${photoVault.i18n.shareAlbum || 'Share Album'}
                        </h2>
                        
                        <div class="pv-share-item-name">
                            <strong>${photoVault.i18n.sharing || 'Sharing'}:</strong>
                            <span id="pv-share-modal-item-name"></span>
                        </div>
                        
                        <div class="pv-share-add-section">
                            <h3>${photoVault.i18n.addPeople || 'Add People'}</h3>
                            <div class="pv-share-search-wrapper">
                                <input type="text" 
                                       id="pv-share-user-search" 
                                       placeholder="${photoVault.i18n.searchUsers || 'Search users by name or email...'}"
                                       autocomplete="off">
                                <div id="pv-share-user-results" class="pv-share-user-results"></div>
                            </div>
                        </div>
                        
                        <div class="pv-share-existing-section">
                            <h3>${photoVault.i18n.sharedWith || 'Shared With'}</h3>
                            <div id="pv-share-existing-list" class="pv-share-existing-list">
                                <div class="pv-loading">
                                    <span class="spinner is-active"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pv-modal-actions">
                            <button class="button" id="pv-close-share-modal">
                                ${photoVault.i18n.done || 'Done'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            this.bindEvents();
        },

        /**
         * Bind modal events
         */
        bindEvents: function() {
            const self = this;
            
            // Close modal
            $('#pv-share-modal .pv-modal-close, #pv-close-share-modal').on('click', function() {
                $('#pv-share-modal').fadeOut(300);
                $('#pv-share-user-search').val('');
                $('#pv-share-user-results').hide();
            });
            
            // User search
            $('#pv-share-user-search').on('input', function() {
                clearTimeout(self.searchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length < 2) {
                    $('#pv-share-user-results').hide();
                    return;
                }
                
                self.searchTimeout = setTimeout(() => {
                    self.searchUsers(searchTerm);
                }, 300);
            });
            
            // Select user from results (delegated)
            $(document).on('click', '.pv-share-user-item', function() {
                const userId = $(this).data('user-id');
                const userName = $(this).data('user-name');
                self.shareWithUser(userId, userName);
            });
            
            // Change permission (delegated)
            $(document).on('change', '.pv-share-permission-select', function() {
                const shareId = $(this).data('share-id');
                const permission = $(this).val();
                self.updatePermission(shareId, permission);
            });
            
            // Remove share (delegated)
            $(document).on('click', '.pv-share-remove-btn', function() {
                const shareId = $(this).data('share-id');
                const userName = $(this).data('user-name');
                self.removeShare(shareId, userName);
            });
            
            // Close on outside click
            $('#pv-share-modal').on('click', function(e) {
                if ($(e.target).is('#pv-share-modal')) {
                    $(this).fadeOut(300);
                }
            });
        },

        /**
         * Search users
         */
        searchUsers: function(searchTerm) {
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_search_users',
                    nonce: photoVault.nonce,
                    search: searchTerm
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.displayUserResults(response.data);
                    }
                },
                error: () => {
                    console.error('Error searching users');
                }
            });
        },

        /**
         * Display user search results
         */
        displayUserResults: function(users) {
            const $results = $('#pv-share-user-results');
            $results.empty();
            
            if (users.length === 0) {
                $results.html('<div class="pv-no-results">No users found</div>').show();
                return;
            }
            
            users.forEach((user) => {
                // Check if already shared
                const alreadyShared = this.currentShares.some(share => 
                    share.shared_with == user.id
                );
                
                if (alreadyShared) {
                    return; // Skip users already shared with
                }
                
                const html = `
                    <div class="pv-share-user-item" data-user-id="${user.id}" data-user-name="${this.escapeHtml(user.name)}">
                        <div class="pv-share-user-info">
                            <div class="pv-share-user-avatar">
                                ${this.getUserAvatar(user)}
                            </div>
                            <div class="pv-share-user-details">
                                <div class="pv-share-user-name">${this.escapeHtml(user.name)}</div>
                                <div class="pv-share-user-email">${this.escapeHtml(user.email)}</div>
                            </div>
                        </div>
                        <div class="pv-share-add-icon">
                            <span class="dashicons dashicons-plus-alt"></span>
                        </div>
                    </div>
                `;
                $results.append(html);
            });
            
            $results.show();
        },

        /**
         * Share with user
         */
        shareWithUser: function(userId, userName) {
            const $btn = $(`.pv-share-user-item[data-user-id="${userId}"]`);
            $btn.css('opacity', '0.5').css('pointer-events', 'none');
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_share_item',
                    nonce: photoVault.nonce,
                    item_type: this.currentItemType,
                    item_id: this.currentItemId,
                    share_with: userId,
                    permission: 'view'
                },
                success: (response) => {
                    if (response.success) {
                        $('#pv-share-user-search').val('');
                        $('#pv-share-user-results').hide();
                        this.loadExistingShares();
                        
                        if (window.AlbumManager) {
                            window.AlbumManager.showNotification(response.data.message, 'success');
                        }
                    } else {
                        alert(response.data?.message || 'Error sharing');
                        $btn.css('opacity', '1').css('pointer-events', 'auto');
                    }
                },
                error: () => {
                    alert('Error sharing');
                    $btn.css('opacity', '1').css('pointer-events', 'auto');
                }
            });
        },

        /**
         * Load existing shares
         */
        loadExistingShares: function() {
            const $list = $('#pv-share-existing-list');
            $list.html('<div class="pv-loading"><span class="spinner is-active"></span></div>');
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_get_item_shares',
                    nonce: photoVault.nonce,
                    item_type: this.currentItemType,
                    item_id: this.currentItemId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.currentShares = response.data;
                        this.displayExistingShares(response.data);
                    } else {
                        $list.html('<div class="pv-no-shares">Not shared with anyone yet</div>');
                    }
                },
                error: () => {
                    $list.html('<div class="pv-error">Error loading shares</div>');
                }
            });
        },

        /**
         * Display existing shares
         */
        displayExistingShares: function(shares) {
            const $list = $('#pv-share-existing-list');
            $list.empty();
            
            if (shares.length === 0) {
                $list.html('<div class="pv-no-shares">Not shared with anyone yet</div>');
                return;
            }
            
            shares.forEach((share) => {
                const html = `
                    <div class="pv-share-item" data-share-id="${share.id}">
                        <div class="pv-share-item-user">
                            <div class="pv-share-user-avatar">
                                ${this.getUserAvatarById(share.shared_with)}
                            </div>
                            <div class="pv-share-item-info">
                                <div class="pv-share-item-name">${this.escapeHtml(share.shared_with_name)}</div>
                                <div class="pv-share-item-date">
                                    Shared ${this.formatDate(share.shared_date)}
                                </div>
                            </div>
                        </div>
                        <div class="pv-share-item-actions">
                            <select class="pv-share-permission-select" data-share-id="${share.id}">
                                <option value="view" ${share.permission === 'view' ? 'selected' : ''}>Can View</option>
                                <option value="edit" ${share.permission === 'edit' ? 'selected' : ''}>Can Edit</option>
                            </select>
                            <button class="button pv-share-remove-btn" 
                                    data-share-id="${share.id}" 
                                    data-user-name="${this.escapeHtml(share.shared_with_name)}"
                                    title="Remove access">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                `;
                $list.append(html);
            });
        },

        /**
         * Update share permission
         */
        updatePermission: function(shareId, permission) {
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_update_share_permission',
                    nonce: photoVault.nonce,
                    share_id: shareId,
                    permission: permission
                },
                success: (response) => {
                    if (response.success) {
                        if (window.AlbumManager) {
                            window.AlbumManager.showNotification(response.data.message, 'success');
                        }
                    } else {
                        alert(response.data?.message || 'Error updating permission');
                        this.loadExistingShares();
                    }
                },
                error: () => {
                    alert('Error updating permission');
                    this.loadExistingShares();
                }
            });
        },

        /**
         * Remove share
         */
        removeShare: function(shareId, userName) {
            if (!confirm(`Remove access for ${userName}?`)) {
                return;
            }
            
            $.ajax({
                url: photoVault.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pv_unshare_item',
                    nonce: photoVault.nonce,
                    share_id: shareId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadExistingShares();
                        if (window.AlbumManager) {
                            window.AlbumManager.showNotification(response.data.message, 'success');
                        }
                    } else {
                        alert(response.data?.message || 'Error removing share');
                    }
                },
                error: () => {
                    alert('Error removing share');
                }
            });
        },

        /**
         * Get user avatar HTML
         */
        getUserAvatar: function(user) {
            return `<span class="dashicons dashicons-admin-users"></span>`;
        },

        /**
         * Get user avatar by ID
         */
        getUserAvatarById: function(userId) {
            return `<span class="dashicons dashicons-admin-users"></span>`;
        },

        /**
         * Format date
         */
        formatDate: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffTime = Math.abs(now - date);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) return 'today';
            if (diffDays === 1) return 'yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
            if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
            return `${Math.floor(diffDays / 365)} years ago`;
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
        }
    };

    // Make ShareManager globally available
    window.ShareManager = ShareManager;

    // Update AlbumManager's shareAlbum method if it exists
    $(document).ready(function() {
        if (window.AlbumManager) {
            window.AlbumManager.shareAlbum = function() {
                if (!this.currentAlbumId) {
                    this.showNotification('Please select an album first', 'warning');
                    return;
                }
                
                // Get album name from the detail modal
                const albumName = $('#pv-album-detail-name').text();
                ShareManager.open('album', this.currentAlbumId, albumName);
            };
        }
    });

})(jQuery);