<?php
/**
 * Enhanced Timeline View with Statistics
 *
 * @package PhotoVault
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <div class="pv-timeline-header">
        <h1><?php _e('Timeline', 'photovault'); ?></h1>
        
        <!-- Statistics Cards -->
        <div class="pv-timeline-stats" id="pv-timeline-stats">
            <div class="pv-stat-card">
                <div class="pv-stat-icon">
                    <span class="dashicons dashicons-format-gallery"></span>
                </div>
                <div class="pv-stat-info">
                    <div class="pv-stat-value" id="pv-stat-total">-</div>
                    <div class="pv-stat-label"><?php _e('Total Images', 'photovault'); ?></div>
                </div>
            </div>
            
            <div class="pv-stat-card">
                <div class="pv-stat-icon pv-stat-icon-blue">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="pv-stat-info">
                    <div class="pv-stat-value" id="pv-stat-months">-</div>
                    <div class="pv-stat-label"><?php _e('Active Months', 'photovault'); ?></div>
                </div>
            </div>
            
            <div class="pv-stat-card">
                <div class="pv-stat-icon pv-stat-icon-green">
                    <span class="dashicons dashicons-upload"></span>
                </div>
                <div class="pv-stat-info">
                    <div class="pv-stat-value" id="pv-stat-recent">-</div>
                    <div class="pv-stat-label"><?php _e('This Month', 'photovault'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="pv-timeline-filters">
        <div class="pv-filter-group">
            <label for="pv-timeline-view"><?php _e('Group by:', 'photovault'); ?></label>
            <select id="pv-timeline-view">
                <option value="day"><?php _e('By Day', 'photovault'); ?></option>
                <option value="month"><?php _e('By Month', 'photovault'); ?></option>
                <option value="year"><?php _e('By Year', 'photovault'); ?></option>
            </select>
        </div>
        
        <div class="pv-filter-group">
            <label for="pv-timeline-sort"><?php _e('Sort:', 'photovault'); ?></label>
            <select id="pv-timeline-sort">
                <option value="desc"><?php _e('Newest First', 'photovault'); ?></option>
                <option value="asc"><?php _e('Oldest First', 'photovault'); ?></option>
            </select>
        </div>
        
        <button class="button" id="pv-refresh-timeline">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh', 'photovault'); ?>
        </button>
    </div>

    <div class="pv-timeline-container">
        <div id="pv-timeline"></div>
        
        <!-- Loading Indicator -->
        <div id="pv-timeline-loading" class="pv-loading">
            <span class="spinner is-active"></span>
            <p><?php _e('Loading timeline...', 'photovault'); ?></p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const TimelinePage = {
        init: function() {
            this.loadStats();
            this.loadTimeline();
            this.bindEvents();
        },

        bindEvents: function() {
            $('#pv-timeline-view, #pv-timeline-sort').on('change', () => this.loadTimeline());
            $('#pv-refresh-timeline').on('click', () => {
                this.loadStats();
                this.loadTimeline();
            });
            
            $(document).on('click', '.pv-timeline-date-header', function() {
                const $group = $(this).closest('.pv-timeline-group');
                const $images = $group.find('.pv-timeline-images');
                const $icon = $(this).find('.pv-toggle-icon');
                
                $images.slideToggle(300);
                $icon.toggleClass('pv-rotated');
            });
            
            $(document).on('click', '.pv-timeline-image', function(e) {
                if (!$(e.target).closest('.pv-image-actions').length) {
                    const imageId = $(this).data('id');
                    const fullUrl = $(this).data('full-url');
                    TimelinePage.openImageModal(imageId, fullUrl, $(this));
                }
            });
            
            // View image button click
            $(document).on('click', '.pv-view-image', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $imageItem = $(this).closest('.pv-timeline-image');
                const imageId = $imageItem.data('id');
                const fullUrl = $imageItem.data('full-url');
                TimelinePage.openImageModal(imageId, fullUrl, $imageItem);
            });
        },

        loadStats: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pv_get_timeline_stats',
                    nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>'
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.displayStats(response.data);
                    }
                }
            });
        },

        displayStats: function(data) {
            $('#pv-stat-total').text(data.total_images || 0);
            $('#pv-stat-months').text(data.by_month ? data.by_month.length : 0);
            
            // Get this month's count
            const currentMonth = new Date().toISOString().slice(0, 7);
            const thisMonthData = data.by_month ? data.by_month.find(m => m.month === currentMonth) : null;
            $('#pv-stat-recent').text(thisMonthData ? thisMonthData.count : 0);
        },

        loadTimeline: function() {
            const view = $('#pv-timeline-view').val();
            const sort = $('#pv-timeline-sort').val();
            
            $('#pv-timeline-loading').show();
            $('#pv-timeline').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pv_get_timeline_images',
                    nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                    view: view,
                    sort: sort
                },
                success: (response) => {
                    $('#pv-timeline-loading').hide();
                    
                    if (response.success && response.data) {
                        this.renderTimeline(response.data, view, sort);
                        $('#pv-timeline').show();
                    } else {
                        $('#pv-timeline').html(this.getEmptyState()).show();
                    }
                },
                error: (xhr, status, error) => {
                    $('#pv-timeline-loading').hide();
                    console.error('Timeline loading error:', error);
                    $('#pv-timeline').html(this.getErrorState()).show();
                }
            });
        },

        renderTimeline: function(data, view, sort) {
            const $timeline = $('#pv-timeline');
            $timeline.empty();

            if (!data || Object.keys(data).length === 0) {
                $timeline.html(this.getEmptyState());
                return;
            }

            let sortedKeys = Object.keys(data).sort((a, b) => {
                return sort === 'desc' ? b.localeCompare(a) : a.localeCompare(b);
            });

            sortedKeys.forEach(key => {
                const group = data[key];
                if (group.images && group.images.length > 0) {
                    const $dateGroup = this.createDateGroup(key, group, view);
                    $timeline.append($dateGroup);
                }
            });
        },

        createDateGroup: function(key, group, view) {
            const images = group.images || [];
            const totalCount = images.length;
            const displayDate = this.formatDateKey(key, view);
            
            const $group = $(`
                <div class="pv-timeline-group">
                    <div class="pv-timeline-date-header">
                        <h2>
                            ${displayDate}
                            <span class="pv-timeline-count">${totalCount} ${totalCount === 1 ? '<?php _e('image', 'photovault'); ?>' : '<?php _e('images', 'photovault'); ?>'}</span>
                        </h2>
                        <span class="dashicons dashicons-arrow-down-alt2 pv-toggle-icon"></span>
                    </div>
                    <div class="pv-timeline-images"></div>
                </div>
            `);

            const $imagesContainer = $group.find('.pv-timeline-images');
            
            images.forEach(image => {
                const $imageEl = this.createImageElement(image);
                $imagesContainer.append($imageEl);
            });
            
            return $group;
        },

        createImageElement: function(image) {
            const thumbnailUrl = image.thumbnail_url || image.url || '';
            const fullUrl = image.url || image.thumbnail_url || '';
            const title = this.escapeHtml(image.title || 'Untitled');
            const date = image.formatted_date || '';
            
            return $(`
                <div class="pv-timeline-image" data-id="${image.id}" data-full-url="${fullUrl}">
                    <img src="${thumbnailUrl}" alt="${title}" loading="lazy">
                    <div class="pv-timeline-image-overlay">
                        <span class="pv-timeline-image-title">${title}</span>
                        <span class="pv-timeline-image-date">${date}</span>
                    </div>
                    <div class="pv-image-actions">
                        <button class="pv-btn-icon pv-view-image" title="<?php _e('View', 'photovault'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>
            `);
        },

        formatDateKey: function(key, view) {
            const monthNames = [
                '<?php _e('January', 'photovault'); ?>', '<?php _e('February', 'photovault'); ?>', 
                '<?php _e('March', 'photovault'); ?>', '<?php _e('April', 'photovault'); ?>', 
                '<?php _e('May', 'photovault'); ?>', '<?php _e('June', 'photovault'); ?>', 
                '<?php _e('July', 'photovault'); ?>', '<?php _e('August', 'photovault'); ?>', 
                '<?php _e('September', 'photovault'); ?>', '<?php _e('October', 'photovault'); ?>', 
                '<?php _e('November', 'photovault'); ?>', '<?php _e('December', 'photovault'); ?>'
            ];
            
            const dayNames = [
                '<?php _e('Sunday', 'photovault'); ?>', '<?php _e('Monday', 'photovault'); ?>',
                '<?php _e('Tuesday', 'photovault'); ?>', '<?php _e('Wednesday', 'photovault'); ?>',
                '<?php _e('Thursday', 'photovault'); ?>', '<?php _e('Friday', 'photovault'); ?>',
                '<?php _e('Saturday', 'photovault'); ?>'
            ];
            
            switch(view) {
                case 'year':
                    return key;
                    
                case 'month':
                    const [year, month] = key.split('-');
                    return `${monthNames[parseInt(month) - 1]} ${year}`;
                    
                case 'day':
                default:
                    const date = new Date(key);
                    return `${dayNames[date.getDay()]}, ${monthNames[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
            }
        },

        openImageModal: function(imageId, fullUrl, $imageEl) {
            const title = $imageEl.find('.pv-timeline-image-title').text();
            const date = $imageEl.find('.pv-timeline-image-date').text();
            
            // Create enhanced modal with navigation
            const $modal = $(`
                <div class="pv-image-modal">
                    <div class="pv-modal-backdrop"></div>
                    <div class="pv-modal-content-wrapper">
                        <button class="pv-modal-close" title="Close (Esc)">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                        
                        <div class="pv-modal-image-container">
                            <div class="pv-modal-loading">
                                <span class="spinner is-active"></span>
                            </div>
                            <img class="pv-modal-image" src="${fullUrl}" alt="${title}">
                        </div>
                        
                        <div class="pv-modal-info">
                            <div class="pv-modal-header">
                                <h3>${title}</h3>
                                <div class="pv-modal-actions">
                                    <button class="pv-modal-action-btn" title="<?php _e('Download', 'photovault'); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>
                                    <a href="${fullUrl}" target="_blank" class="pv-modal-action-btn" title="<?php _e('Open in new tab', 'photovault'); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                            </div>
                            <p class="pv-modal-date">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                ${date}
                            </p>
                            <p class="pv-modal-id">
                                <span class="dashicons dashicons-info"></span>
                                <?php _e('Image ID:', 'photovault'); ?> ${imageId}
                            </p>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append($modal);
            
            // Show loading state
            $modal.find('.pv-modal-loading').show();
            $modal.find('.pv-modal-image').hide();
            
            // When image loads, hide loading
            $modal.find('.pv-modal-image').on('load', function() {
                $modal.find('.pv-modal-loading').fadeOut(200);
                $(this).fadeIn(300);
            });
            
            // Show modal
            $modal.fadeIn(300);
            
            // Close handlers
            $modal.find('.pv-modal-close, .pv-modal-backdrop').on('click', function() {
                $modal.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Download handler
            $modal.find('.pv-modal-action-btn').first().on('click', function(e) {
                e.preventDefault();
                const link = document.createElement('a');
                link.href = fullUrl;
                link.download = title || 'image';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
            
            // Keyboard navigation
            $(document).on('keydown.imageModal', function(e) {
                if (e.key === 'Escape') {
                    $modal.find('.pv-modal-close').click();
                    $(document).off('keydown.imageModal');
                }
            });
        },

        getEmptyState: function() {
            return `
                <div class="pv-no-timeline">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <h3><?php _e('No Images Yet', 'photovault'); ?></h3>
                    <p><?php _e('Upload some images to see them in your timeline.', 'photovault'); ?></p>
                </div>
            `;
        },

        getErrorState: function() {
            return `
                <div class="pv-error-timeline">
                    <span class="dashicons dashicons-warning"></span>
                    <h3><?php _e('Error Loading Timeline', 'photovault'); ?></h3>
                    <p><?php _e('Please try refreshing the page.', 'photovault'); ?></p>
                </div>
            `;
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    TimelinePage.init();
});
</script>

<style>
.photovault-wrap {
    background: #f5f5f5;
    padding: 20px;
    min-height: calc(100vh - 32px);
}

.pv-timeline-header {
    margin-bottom: 20px;
}

.pv-timeline-header h1 {
    margin-bottom: 20px;
}

/* Statistics Cards */
.pv-timeline-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.pv-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.pv-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.pv-stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pv-stat-icon-blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.pv-stat-icon-green {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.pv-stat-icon .dashicons {
    color: white;
    font-size: 28px;
    width: 28px;
    height: 28px;
}

.pv-stat-info {
    flex: 1;
}

.pv-stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
    margin-bottom: 5px;
}

.pv-stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}

/* Filters */
.pv-timeline-filters {
    display: flex;
    gap: 20px;
    align-items: flex-end;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.pv-filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pv-filter-group label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.pv-timeline-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    min-width: 150px;
}

.pv-timeline-filters select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

#pv-refresh-timeline {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}

/* Timeline Groups */
.pv-timeline-container {
    margin-top: 20px;
}

.pv-timeline-group {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    animation: slideIn 0.4s ease-out;
}

.pv-timeline-date-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    cursor: pointer;
    transition: all 0.3s;
    user-select: none;
}

.pv-timeline-date-header:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a4091 100%);
}

.pv-timeline-date-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 15px;
}

.pv-timeline-count {
    font-size: 14px;
    font-weight: 400;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 12px;
}

.pv-toggle-icon {
    transition: transform 0.3s;
    color: #fff;
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.pv-toggle-icon.pv-rotated {
    transform: rotate(180deg);
}

/* Images Grid */
.pv-timeline-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    padding: 25px;
    background: #fafafa;
}

.pv-timeline-image {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.pv-timeline-image:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.pv-timeline-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}

.pv-timeline-image-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 60%, transparent 100%);
    padding: 15px 12px 12px;
    opacity: 0;
    transition: opacity 0.3s;
}

.pv-timeline-image:hover .pv-timeline-image-overlay {
    opacity: 1;
}

.pv-timeline-image-title {
    display: block;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pv-timeline-image-date {
    display: block;
    color: rgba(255,255,255,0.8);
    font-size: 12px;
}

.pv-image-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 8px;
    opacity: 0;
    transition: opacity 0.3s;
}

.pv-timeline-image:hover .pv-image-actions {
    opacity: 1;
}

.pv-btn-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.95);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.pv-btn-icon:hover {
    background: white;
    transform: scale(1.1);
}

.pv-btn-icon .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
    color: #333;
}

/* Empty/Error States */
.pv-no-timeline,
.pv-error-timeline {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 8px;
    border: 2px dashed #ddd;
}

.pv-no-timeline .dashicons,
.pv-error-timeline .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #9ca3af;
    margin-bottom: 20px;
}

.pv-no-timeline h3,
.pv-error-timeline h3 {
    margin: 0 0 10px 0;
    color: #374151;
}

.pv-no-timeline p,
.pv-error-timeline p {
    margin: 0;
    color: #6b7280;
}

.pv-error-timeline {
    border-color: #fecaca;
    background: #fef2f2;
}

.pv-error-timeline .dashicons {
    color: #dc2626;
}

.pv-error-timeline h3 {
    color: #dc2626;
}

/* Loading State */
.pv-loading {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.pv-loading p {
    margin-top: 10px;
    color: #666;
}

/* Image Modal */
.pv-image-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
    display: none;
}

.pv-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.95);
    animation: fadeIn 0.3s ease-out;
}

.pv-modal-content-wrapper {
    position: relative;
    max-width: 95%;
    max-height: 95vh;
    margin: 2.5vh auto;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease-out;
}

.pv-modal-close {
    position: absolute;
    top: 0px;
    right: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.pv-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.1);
}

.pv-modal-close .dashicons {
    color: white;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.pv-modal-image-container {
    position: relative;
    background: #000;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    max-height: calc(95vh - 150px);
}

.pv-modal-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: none;
}

.pv-modal-loading .spinner {
    float: none;
    margin: 0;
}

.pv-modal-image {
    max-width: 100%;
    max-height: calc(95vh - 150px);
    object-fit: contain;
    display: block;
    margin: 0 auto;
    border-radius: 8px 8px 0 0;
}

.pv-modal-info {
    background: white;
    padding: 20px 25px;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.pv-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.pv-modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #1f2937;
    flex: 1;
}

.pv-modal-actions {
    display: flex;
    gap: 8px;
}

.pv-modal-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    text-decoration: none;
}

.pv-modal-action-btn:hover {
    background: #667eea;
    border-color: #667eea;
    transform: translateY(-2px);
}

.pv-modal-action-btn:hover .dashicons {
    color: white;
}

.pv-modal-action-btn .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
    color: #6b7280;
    transition: color 0.2s;
}

.pv-modal-date,
.pv-modal-id {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
    color: #6b7280;
    font-size: 14px;
}

.pv-modal-date .dashicons,
.pv-modal-id .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #9ca3af;
}

/* Modal Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Modal */
@media (max-width: 768px) {
    .pv-modal-content-wrapper {
        max-width: 100%;
        margin: 0;
        max-height: 100vh;
    }
    
    .pv-modal-close {
        top: 10px;
        right: 10px;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.8);
        border-color: rgba(255, 255, 255, 0.2);
    }
    
    .pv-modal-image-container {
        border-radius: 0;
        max-height: calc(100vh - 200px);
    }
    
    .pv-modal-image {
        border-radius: 0;
        max-height: calc(100vh - 200px);
    }
    
    .pv-modal-info {
        border-radius: 0;
    }
    
    .pv-modal-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .pv-modal-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .pv-timeline-stats {
        grid-template-columns: 1fr;
    }
    
    .pv-timeline-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .pv-filter-group {
        width: 100%;
    }
    
    .pv-timeline-filters select {
        width: 100%;
    }
    
    #pv-refresh-timeline {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }
    
    .pv-timeline-images {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
        padding: 15px;
    }
    
    .pv-timeline-image img {
        height: 150px;
    }
    
    .pv-timeline-date-header {
        padding: 15px;
    }
    
    .pv-timeline-date-header h2 {
        font-size: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}

/* Animation */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>