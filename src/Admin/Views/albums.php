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

