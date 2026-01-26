<?php
/**
 * Videos Admin Page
 *
 * @package PhotoVault
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-videos-page">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Videos', 'photovault'); ?>
    </h1>
    
    <button type="button" class="page-title-action pv-upload-video-btn">
        <?php esc_html_e('Upload Video', 'photovault'); ?>
    </button>
    
    <hr class="wp-header-end">
    
    <!-- Filters -->
    <div class="pv-filters">
        <div class="pv-filter-group">
            <input type="search" 
                   id="pv-video-search" 
                   class="pv-search-input" 
                   placeholder="<?php esc_attr_e('Search videos...', 'photovault'); ?>">
            
            <select id="pv-video-album-filter" class="pv-filter-select">
                <option value=""><?php esc_html_e('All Albums', 'photovault'); ?></option>
            </select>
            
            <select id="pv-video-tag-filter" class="pv-filter-select">
                <option value=""><?php esc_html_e('All Tags', 'photovault'); ?></option>
            </select>
            
            <select id="pv-video-visibility-filter" class="pv-filter-select">
                <option value=""><?php esc_html_e('All Visibility', 'photovault'); ?></option>
                <option value="private"><?php esc_html_e('Private', 'photovault'); ?></option>
                <option value="public"><?php esc_html_e('Public', 'photovault'); ?></option>
                <option value="shared"><?php esc_html_e('Shared', 'photovault'); ?></option>
            </select>
            
            <select id="pv-video-sort" class="pv-filter-select">
                <option value="date_desc"><?php esc_html_e('Newest First', 'photovault'); ?></option>
                <option value="date_asc"><?php esc_html_e('Oldest First', 'photovault'); ?></option>
                <option value="title_asc"><?php esc_html_e('Title A-Z', 'photovault'); ?></option>
                <option value="title_desc"><?php esc_html_e('Title Z-A', 'photovault'); ?></option>
                <option value="size_desc"><?php esc_html_e('Largest First', 'photovault'); ?></option>
                <option value="size_asc"><?php esc_html_e('Smallest First', 'photovault'); ?></option>
                <option value="duration_desc"><?php esc_html_e('Longest First', 'photovault'); ?></option>
                <option value="duration_asc"><?php esc_html_e('Shortest First', 'photovault'); ?></option>
            </select>
            
            <button type="button" class="button pv-filter-reset">
                <?php esc_html_e('Reset Filters', 'photovault'); ?>
            </button>
        </div>
    </div>
    
    <!-- Videos Grid -->
    <div id="pv-videos-container" class="pv-videos-grid">
        <div class="pv-loading">
            <span class="spinner is-active"></span>
            <p><?php esc_html_e('Loading videos...', 'photovault'); ?></p>
        </div>
    </div>
    
    <!-- Pagination -->
    <div id="pv-videos-pagination" class="pv-pagination"></div>
    
    <!-- Upload Modal -->
    <div id="pv-upload-video-modal" class="pv-modal" style="display: none;">
        <div class="pv-modal-content">
            <span class="pv-modal-close">&times;</span>
            <h2><?php esc_html_e('Upload Video', 'photovault'); ?></h2>
            
            <form id="pv-upload-video-form" enctype="multipart/form-data">
                <div class="pv-form-group">
                    <label for="pv-video-file">
                        <?php esc_html_e('Video File', 'photovault'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="file" 
                           id="pv-video-file" 
                           name="video" 
                           accept="video/*" 
                           required>
                    <p class="description">
                        <?php
                        $max_size = size_format(get_option('photovault_max_video_upload_size', 104857600));
                        $allowed_types = implode(', ', get_option('photovault_allowed_video_types', ['mp4', 'mov', 'avi', 'wmv', 'webm']));
                        printf(
                            esc_html__('Maximum file size: %s. Allowed types: %s', 'photovault'),
                            esc_html($max_size),
                            esc_html($allowed_types)
                        );
                        ?>
                    </p>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-video-title"><?php esc_html_e('Title', 'photovault'); ?></label>
                    <input type="text" id="pv-video-title" name="title" class="regular-text">
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-video-description"><?php esc_html_e('Description', 'photovault'); ?></label>
                    <textarea id="pv-video-description" 
                              name="description" 
                              rows="4" 
                              class="large-text"></textarea>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-video-visibility"><?php esc_html_e('Visibility', 'photovault'); ?></label>
                    <select id="pv-video-visibility" name="visibility">
                        <option value="private"><?php esc_html_e('Private', 'photovault'); ?></option>
                        <option value="public"><?php esc_html_e('Public', 'photovault'); ?></option>
                        <option value="shared"><?php esc_html_e('Shared', 'photovault'); ?></option>
                    </select>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-video-tags"><?php esc_html_e('Tags', 'photovault'); ?></label>
                    <input type="text" 
                           id="pv-video-tags" 
                           name="tags" 
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Separate tags with commas', 'photovault'); ?>">
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-video-album"><?php esc_html_e('Album', 'photovault'); ?></label>
                    <select id="pv-video-album" name="album_id">
                        <option value=""><?php esc_html_e('No Album', 'photovault'); ?></option>
                    </select>
                </div>
                
                <div class="pv-upload-progress" style="display: none;">
                    <div class="pv-progress-bar">
                        <div class="pv-progress-fill"></div>
                    </div>
                    <p class="pv-progress-text">0%</p>
                </div>
                
                <div class="pv-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Upload Video', 'photovault'); ?>
                    </button>
                    <button type="button" class="button pv-modal-cancel">
                        <?php esc_html_e('Cancel', 'photovault'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Video Details Modal -->
    <div id="pv-video-details-modal" class="pv-modal" style="display: none;">
        <div class="pv-modal-content pv-modal-xlarge">
            <span class="pv-modal-close">&times;</span>
            <div id="pv-video-details-content"></div>
        </div>
    </div>
    
    <!-- Edit Video Modal -->
    <div id="pv-edit-video-modal" class="pv-modal" style="display: none;">
        <div class="pv-modal-content">
            <span class="pv-modal-close">&times;</span>
            <h2><?php esc_html_e('Edit Video', 'photovault'); ?></h2>
            
            <form id="pv-edit-video-form">
                <input type="hidden" id="pv-edit-video-id" name="video_id">
                
                <div class="pv-form-group">
                    <label for="pv-edit-video-title"><?php esc_html_e('Title', 'photovault'); ?></label>
                    <input type="text" 
                           id="pv-edit-video-title" 
                           name="title" 
                           class="regular-text" 
                           required>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-edit-video-description"><?php esc_html_e('Description', 'photovault'); ?></label>
                    <textarea id="pv-edit-video-description" 
                              name="description" 
                              rows="4" 
                              class="large-text"></textarea>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-edit-video-visibility"><?php esc_html_e('Visibility', 'photovault'); ?></label>
                    <select id="pv-edit-video-visibility" name="visibility">
                        <option value="private"><?php esc_html_e('Private', 'photovault'); ?></option>
                        <option value="public"><?php esc_html_e('Public', 'photovault'); ?></option>
                        <option value="shared"><?php esc_html_e('Shared', 'photovault'); ?></option>
                    </select>
                </div>
                
                <div class="pv-form-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Changes', 'photovault'); ?>
                    </button>
                    <button type="button" class="button pv-modal-cancel">
                        <?php esc_html_e('Cancel', 'photovault'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.pv-videos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.pv-video-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    transition: box-shadow 0.3s;
}

.pv-video-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.pv-video-thumbnail {
    position: relative;
    width: 100%;
    padding-top: 56.25%; /* 16:9 aspect ratio */
    background: #f5f5f5;
    overflow: hidden;
}

.pv-video-thumbnail video,
.pv-video-thumbnail img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pv-video-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0,0,0,0.8);
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.pv-video-info {
    padding: 15px;
}

.pv-video-title {
    font-weight: 600;
    margin: 0 0 8px 0;
    font-size: 14px;
}

.pv-video-meta {
    color: #666;
    font-size: 12px;
    margin-bottom: 8px;
}

.pv-video-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.pv-video-actions button {
    flex: 1;
    padding: 6px 12px;
    font-size: 12px;
}

.pv-upload-progress {
    margin: 20px 0;
}

.pv-progress-bar {
    width: 100%;
    height: 24px;
    background: #f0f0f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 8px;
}

.pv-progress-fill {
    height: 100%;
    background: #0073aa;
    transition: width 0.3s;
    width: 0%;
}

.pv-progress-text {
    text-align: center;
    font-size: 14px;
    color: #666;
}
</style>