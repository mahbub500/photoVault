<?php
/**
 * Admin Timeline View
 *
 * @package PhotoVault
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <h1><?php _e('Timeline', 'photovault'); ?></h1>

    <div class="pv-timeline-filters">
        <select id="pv-timeline-view">
            <option value="month"><?php _e('By Month', 'photovault'); ?></option>
            <option value="year"><?php _e('By Year', 'photovault'); ?></option>
            <option value="day"><?php _e('By Day', 'photovault'); ?></option>
        </select>
        
        <select id="pv-timeline-sort">
            <option value="desc"><?php _e('Newest First', 'photovault'); ?></option>
            <option value="asc"><?php _e('Oldest First', 'photovault'); ?></option>
        </select>
    </div>

    <div class="pv-timeline-container">
        <div id="pv-timeline"></div>
        
        <!-- Loading Indicator -->
        <div id="pv-timeline-loading" class="pv-loading" style="display:none;">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const TimelinePage = {
        timelineData: [],
        
        init: function() {
            this.loadTimeline();
            this.bindEvents();
        },

        bindEvents: function() {
            $('#pv-timeline-view, #pv-timeline-sort').on('change', this.loadTimeline);
            $(document).on('click', '.pv-timeline-date-header', this.toggleDateGroup);
            $(document).on('click', '.pv-timeline-image', this.openImageDetail);
        },

        loadTimeline: function() {
            $('#pv-timeline-loading').show();
            
            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_timeline',
                nonce: photoVault.nonce
            }, function(response) {
                if (response.success) {
                    TimelinePage.timelineData = response.data;
                    TimelinePage.renderTimeline();
                }
                $('#pv-timeline-loading').hide();
            });
        },

        renderTimeline: function() {
            const view = $('#pv-timeline-view').val();
            const sort = $('#pv-timeline-sort').val();
            const $timeline = $('#pv-timeline');
            $timeline.empty();

            if (this.timelineData.length === 0) {
                $timeline.html('<div class="pv-no-timeline"><p><?php _e('No images in timeline yet.', 'photovault'); ?></p></div>');
                return;
            }

            const grouped = this.groupTimelineData(view);
            const sortedKeys = Object.keys(grouped).sort((a, b) => {
                return sort === 'desc' ? b.localeCompare(a) : a.localeCompare(b);
            });

            sortedKeys.forEach(key => {
                const group = grouped[key];
                const $dateGroup = this.createDateGroup(key, group, view);
                $timeline.append($dateGroup);
            });
        },

        groupTimelineData: function(view) {
            const grouped = {};
            
            this.timelineData.forEach(item => {
                const date = new Date(item.date);
                let key;
                
                switch(view) {
                    case 'year':
                        key = date.getFullYear().toString();
                        break;
                    case 'month':
                        key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                        break;
                    case 'day':
                    default:
                        key = item.date;
                        break;
                }
                
                if (!grouped[key]) {
                    grouped[key] = [];
                }
                grouped[key].push(item);
            });
            
            return grouped;
        },

        createDateGroup: function(key, items, view) {
            const totalCount = items.reduce((sum, item) => sum + parseInt(item.count), 0);
            const displayDate = this.formatDateKey(key, view);
            
            const $group = $(`
                <div class="pv-timeline-group">
                    <div class="pv-timeline-date-header">
                        <h2>
                            ${displayDate}
                            <span class="pv-timeline-count">${totalCount} <?php _e('images', 'photovault'); ?></span>
                        </h2>
                        <span class="dashicons dashicons-arrow-down-alt2 pv-toggle-icon"></span>
                    </div>
                    <div class="pv-timeline-images" data-group="${key}"></div>
                </div>
            `);

            this.loadImagesForDateGroup(key, view, $group.find('.pv-timeline-images'));
            
            return $group;
        },

        formatDateKey: function(key, view) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                              'July', 'August', 'September', 'October', 'November', 'December'];
            
            switch(view) {
                case 'year':
                    return key;
                case 'month':
                    const [year, month] = key.split('-');
                    return `${monthNames[parseInt(month) - 1]} ${year}`;
                case 'day':
                default:
                    return new Date(key).toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
            }
        },

        loadImagesForDateGroup: function(key, view, $container) {
            let startDate, endDate;
            
            switch(view) {
                case 'year':
                    startDate = `${key}-01-01`;
                    endDate = `${key}-12-31`;
                    break;
                case 'month':
                    const [year, month] = key.split('-');
                    const lastDay = new Date(year, month, 0).getDate();
                    startDate = `${key}-01`;
                    endDate = `${key}-${lastDay}`;
                    break;
                case 'day':
                default:
                    startDate = endDate = key;
                    break;
            }

            $.post(photoVault.ajaxUrl, {
                action: 'pv_get_images',
                nonce: photoVault.nonce,
                start_date: startDate,
                end_date: endDate
            }, function(response) {
                if (response.success && response.data.images && response.data.images.length > 0) {
                    response.data.images.forEach(image => {
                        $container.append(`
                            <div class="pv-timeline-image" data-id="${image.id}">
                                <img src="${image.thumbnail || image.url}" alt="${image.title || ''}">
                                <div class="pv-timeline-image-overlay">
                                    <span class="pv-timeline-image-title">${image.title || 'Untitled'}</span>
                                </div>
                            </div>
                        `);
                    });
                } else {
                    $container.html('<p class="pv-no-images"><?php _e('No images for this period', 'photovault'); ?></p>');
                }
            });
        },

        toggleDateGroup: function() {
            const $group = $(this).closest('.pv-timeline-group');
            const $images = $group.find('.pv-timeline-images');
            const $icon = $(this).find('.pv-toggle-icon');
            
            $images.slideToggle(300);
            $icon.toggleClass('pv-rotated');
        },

        openImageDetail: function() {
            const imageId = $(this).data('id');
            console.log('Open image detail:', imageId);
        }
    };

    TimelinePage.init();
});
</script>

<style>
.pv-timeline-filters {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.pv-timeline-filters select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.pv-timeline-container {
    margin-top: 20px;
}

.pv-timeline-group {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
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

.pv-timeline-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
    padding: 20px;
}

.pv-timeline-image {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
}

.pv-timeline-image:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}

.pv-timeline-image img {
    width: 100%;
    height: 180px;
    object-fit: cover;
    display: block;
}

.pv-timeline-image-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    padding: 12px;
    opacity: 0;
    transition: opacity 0.3s;
}

.pv-timeline-image:hover .pv-timeline-image-overlay {
    opacity: 1;
}

.pv-timeline-image-title {
    color: #fff;
    font-size: 13px;
    font-weight: 500;
}

.pv-no-timeline,
.pv-no-images {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.pv-loading {
    text-align: center;
    padding: 40px;
}

@media (max-width: 768px) {
    .pv-timeline-images {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        padding: 15px;
    }
    
    .pv-timeline-date-header h2 {
        font-size: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .pv-timeline-count {
        font-size: 12px;
    }
}

.pv-timeline-group {
    animation: slideIn 0.5s ease-out;
}

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