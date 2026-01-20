<?php
/**
 * PhotoVault - Albums Admin Page Template with Cover Image Upload
 * File: templates/admin-albums.php
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <h1>
        <?php esc_html_e('Albums', 'photovault'); ?>
        <button class="page-title-action" id="pv-create-album-btn">
            <span class="dashicons dashicons-plus"></span> 
            <?php esc_html_e('Create Album', 'photovault'); ?>
        </button>
    </h1>

    <!-- Search Bar -->
    <div class="pv-search-bar">
        <input 
            type="text" 
            id="pv-album-search" 
            placeholder="<?php esc_attr_e('Search albums...', 'photovault'); ?>"
        >
    </div>

    <!-- Albums Container -->
    <div class="pv-albums-container">
        <div id="pv-albums-grid" class="pv-albums-grid"></div>
        <div id="pv-albums-loading" class="pv-loading" style="display:none;">
            <span class="spinner is-active"></span>
        </div>
    </div>
</div>

<!-- Create/Edit Album Modal with Cover Image Upload -->
<div id="pv-album-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content">
        <span class="pv-modal-close">&times;</span>
        <h2 id="pv-album-modal-title">
            <?php esc_html_e('Create New Album', 'photovault'); ?>
        </h2>
        
        <!-- Cover Image Upload Section -->
        <div class="pv-form-field pv-cover-upload-section">
            <label><?php esc_html_e('Album Cover Image', 'photovault'); ?></label>
            
            <div id="pv-cover-preview-container" class="pv-cover-preview-container" style="display:none;">
                <img id="pv-cover-preview" src="" alt="Album cover">
                <button type="button" class="pv-remove-cover" id="pv-remove-cover-btn">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <div id="pv-cover-upload-area" class="pv-cover-upload-area">
                <div class="pv-upload-placeholder">
                    <span class="dashicons dashicons-format-image"></span>
                    <p><?php esc_html_e('Click to upload or drag and drop', 'photovault'); ?></p>
                    <span class="pv-upload-hint">
                        <?php esc_html_e('JPG, PNG, GIF or WebP (Max 5MB)', 'photovault'); ?>
                    </span>
                </div>
                <input 
                    type="file" 
                    id="pv-cover-image-input" 
                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                    style="display:none;"
                >
                <input type="hidden" id="pv-cover-attachment-id" value="">
            </div>
        </div>

        <div class="pv-form-field">
            <label for="pv-album-name">
                <?php esc_html_e('Album Name', 'photovault'); ?> 
                <span class="required">*</span>
            </label>
            <input 
                type="text" 
                id="pv-album-name" 
                placeholder="<?php esc_attr_e('Enter album name', 'photovault'); ?>"
                required
            >
        </div>

        <div class="pv-form-field">
            <label for="pv-album-description">
                <?php esc_html_e('Description', 'photovault'); ?>
            </label>
            <textarea 
                id="pv-album-description" 
                rows="4"
                placeholder="<?php esc_attr_e('Enter album description (optional)', 'photovault'); ?>"
            ></textarea>
        </div>

        <div class="pv-form-field">
            <label for="pv-album-visibility">
                <?php esc_html_e('Visibility', 'photovault'); ?>
            </label>
            <select id="pv-album-visibility">
                <option value="private">
                    <?php esc_html_e('🔒 Private - Only you can see', 'photovault'); ?>
                </option>
                <option value="shared">
                    <?php esc_html_e('👥 Shared - People you share with', 'photovault'); ?>
                </option>
                <option value="public">
                    <?php esc_html_e('🌍 Public - Anyone can view', 'photovault'); ?>
                </option>
            </select>
        </div>

        <div class="pv-modal-actions">
            <button class="button" id="pv-cancel-album">
                <?php esc_html_e('Cancel', 'photovault'); ?>
            </button>
            <button class="button button-primary" id="pv-save-album">
                <?php esc_html_e('Save Album', 'photovault'); ?>
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
                <span id="pv-album-detail-visibility"></span>
                <span id="pv-album-detail-date"></span>
            </div>
        </div>

        <div class="pv-album-actions-bar">
            <button class="button" id="pv-add-images-to-album">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_html_e('Add Images', 'photovault'); ?>
            </button>
            <button class="button" id="pv-edit-album">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e('Edit', 'photovault'); ?>
            </button>
            <button class="button" id="pv-duplicate-album">
                <span class="dashicons dashicons-admin-page"></span>
                <?php esc_html_e('Duplicate', 'photovault'); ?>
            </button>
            <button class="button" id="pv-share-album">
                <span class="dashicons dashicons-share"></span>
                <?php esc_html_e('Share', 'photovault'); ?>
            </button>
            <button class="button button-link-delete" id="pv-delete-album">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Delete', 'photovault'); ?>
            </button>
        </div>

        <div id="pv-album-images" class="pv-album-images-grid">
            <!-- Images will be loaded here dynamically -->
        </div>
    </div>
</div>