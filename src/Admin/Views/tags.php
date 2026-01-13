<?php
/**
 * Tags Admin View with Modal Image Display
 * File: src/Admin/Views/tags.php
 *
 * @package PhotoVault
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get all tags
use PhotoVault\Models\Tag;
$tag_model = new Tag();
$all_tags = $tag_model->get_tags();
?>

<div class="wrap photovault-tags">
    <h1 class="wp-heading-inline"><?php _e('Tags', 'photovault'); ?></h1>
    <a href="#" id="pv-add-new-tag" class="page-title-action"><?php _e('Add New', 'photovault'); ?></a>
    <hr class="wp-header-end">

    <?php if (empty($all_tags)): ?>
        <div class="pv-empty-state">
            <span class="dashicons dashicons-tag"></span>
            <h2><?php _e('No Tags Yet', 'photovault'); ?></h2>
            <p><?php _e('Tags help organize your images. Create your first tag to get started.', 'photovault'); ?></p>
            <button class="button button-primary" id="pv-create-first-tag">
                <?php _e('Create Your First Tag', 'photovault'); ?>
            </button>
        </div>
    <?php else: ?>
        <div class="pv-tags-grid">
            <?php foreach ($all_tags as $tag): ?>
                <div class="pv-tag-card" data-tag-id="<?php echo esc_attr($tag->id); ?>">
                    <div class="pv-tag-header" style="background-color: <?php echo esc_attr($tag->color); ?>">
                        <span class="pv-tag-name"><?php echo esc_html($tag->name); ?></span>
                        <span class="pv-tag-count"><?php echo esc_html($tag->usage_count); ?> <?php _e('images', 'photovault'); ?></span>
                    </div>
                    <div class="pv-tag-actions">
                        <button class="button button-primary button-small pv-view-tag-images" 
                                data-tag-id="<?php echo esc_attr($tag->id); ?>"
                                data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                data-tag-color="<?php echo esc_attr($tag->color); ?>">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <?php _e('View Images', 'photovault'); ?>
                        </button>
                        <button class="button button-small pv-assign-images" 
                                data-tag-id="<?php echo esc_attr($tag->id); ?>"
                                data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                data-tag-color="<?php echo esc_attr($tag->color); ?>">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php _e('Assign Images', 'photovault'); ?>
                        </button>
                        <button class="button button-small pv-edit-tag" 
                                data-tag-id="<?php echo esc_attr($tag->id); ?>"
                                data-tag-name="<?php echo esc_attr($tag->name); ?>"
                                data-tag-color="<?php echo esc_attr($tag->color); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Edit', 'photovault'); ?>
                        </button>
                        <button class="button button-small button-link-delete pv-delete-tag" 
                                data-tag-id="<?php echo esc_attr($tag->id); ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Delete', 'photovault'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Tag Modal -->
<div id="pv-tag-modal" class="pv-modal" style="display: none;">
    <div class="pv-modal-overlay"></div>
    <div class="pv-modal-content">
        <div class="pv-modal-header">
            <h2 id="pv-modal-title"><?php _e('Add New Tag', 'photovault'); ?></h2>
            <button class="pv-modal-close">&times;</button>
        </div>
        <div class="pv-modal-body">
            <form id="pv-tag-form">
                <input type="hidden" id="pv-tag-id" value="">
                
                <div class="pv-form-group">
                    <label for="pv-tag-name-input"><?php _e('Tag Name', 'photovault'); ?></label>
                    <input type="text" id="pv-tag-name-input" class="regular-text" required>
                </div>
                
                <div class="pv-form-group">
                    <label for="pv-tag-color-input"><?php _e('Color', 'photovault'); ?></label>
                    <input type="color" id="pv-tag-color-input" value="#667eea">
                </div>
                
                <div class="pv-form-actions">
                    <button type="submit" class="button button-primary" id="pv-save-tag">
                        <?php _e('Save Tag', 'photovault'); ?>
                    </button>
                    <button type="button" class="button pv-cancel-tag">
                        <?php _e('Cancel', 'photovault'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Images Modal -->
<div id="pv-images-modal" class="pv-modal" style="display: none;">
    <div class="pv-modal-overlay"></div>
    <div class="pv-modal-content pv-images-modal-content">
        <div class="pv-modal-header">
            <h2 id="pv-images-modal-title">
                <span class="pv-tag-badge-inline" id="pv-modal-tag-badge"></span>
                <span id="pv-modal-tag-name"></span>
            </h2>
            <button class="pv-modal-close">&times;</button>
        </div>
        <div class="pv-modal-body">
            <div id="pv-images-loading" style="text-align: center; padding: 40px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <p><?php _e('Loading images...', 'photovault'); ?></p>
            </div>
            <div id="pv-images-grid" class="pv-modal-images-grid" style="display: none;"></div>
            <div id="pv-images-empty" style="display: none; text-align: center; padding: 40px; color: #666;">
                <span class="dashicons dashicons-images-alt2" style="font-size: 48px; width: 48px; height: 48px; opacity: 0.5;"></span>
                <p><?php _e('No images found with this tag.', 'photovault'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div id="pv-lightbox-modal" class="pv-modal" style="display: none;">
    <div class="pv-modal-overlay"></div>
    <div class="pv-modal-content pv-lightbox-content">
        <button class="pv-modal-close">&times;</button>
        <div class="pv-lightbox-image-container">
            <img id="pv-lightbox-image" src="" alt="">
        </div>
        <div class="pv-lightbox-info">
            <h3 id="pv-lightbox-title"></h3>
            <p id="pv-lightbox-date"></p>
        </div>
    </div>
</div>

<!-- Assign Images Modal -->
<div id="pv-assign-images-modal" class="pv-modal" style="display: none;">
    <div class="pv-modal-overlay"></div>
    <div class="pv-modal-content pv-assign-modal-content">
        <div class="pv-modal-header">
            <h2>
                <?php _e('Assign Images to', 'photovault'); ?>
                <span class="pv-tag-badge-inline" id="pv-assign-tag-badge"></span>
                <span id="pv-assign-tag-name"></span>
            </h2>
            <button class="pv-modal-close">&times;</button>
        </div>
        <div class="pv-modal-body">
            <!-- Search and Filter -->
            <div class="pv-assign-filters">
                <input type="text" 
                       id="pv-assign-search" 
                       class="regular-text" 
                       placeholder="<?php _e('Search images...', 'photovault'); ?>">
                <button class="button" id="pv-assign-select-all">
                    <?php _e('Select All', 'photovault'); ?>
                </button>
                <button class="button" id="pv-assign-deselect-all">
                    <?php _e('Deselect All', 'photovault'); ?>
                </button>
            </div>
            
            <!-- Selected Count -->
            <div class="pv-assign-status">
                <span id="pv-selected-count">0</span> <?php _e('images selected', 'photovault'); ?>
            </div>
            
            <!-- Loading State -->
            <div id="pv-assign-loading" style="text-align: center; padding: 40px; display: none;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <p><?php _e('Loading images...', 'photovault'); ?></p>
            </div>
            
            <!-- Images Grid -->
            <div id="pv-assign-images-grid" class="pv-assign-images-grid"></div>
            
            <!-- Empty State -->
            <div id="pv-assign-empty" style="display: none; text-align: center; padding: 40px; color: #666;">
                <span class="dashicons dashicons-images-alt2" style="font-size: 48px; width: 48px; height: 48px; opacity: 0.5;"></span>
                <p><?php _e('No images available to assign.', 'photovault'); ?></p>
            </div>
            
            <!-- Actions -->
            <div class="pv-assign-actions">
                <button class="button button-primary button-large" id="pv-assign-submit">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Assign Selected Images', 'photovault'); ?>
                </button>
                <button class="button button-large pv-cancel-assign">
                    <?php _e('Cancel', 'photovault'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.photovault-tags {
    padding: 20px 0;
}

.pv-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f9fafb;
    border-radius: 8px;
    border: 2px dashed #d1d5db;
    margin-top: 30px;
}

.pv-empty-state .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #9ca3af;
    margin-bottom: 20px;
}

.pv-empty-state h2 {
    color: #374151;
    margin-bottom: 10px;
}

.pv-empty-state p {
    color: #6b7280;
    margin-bottom: 20px;
}

.pv-tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.pv-tag-card {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
}

.pv-tag-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.pv-tag-header {
    padding: 20px;
    color: white;
    text-align: center;
}

.pv-tag-name {
    display: block;
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.pv-tag-count {
    display: block;
    font-size: 14px;
    opacity: 0.9;
}

.pv-tag-actions {
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pv-tag-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.pv-tag-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Modal Styles */
.pv-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
}

.pv-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
}

.pv-modal-content {
    position: relative;
    max-width: 500px;
    margin: 100px auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.pv-images-modal-content {
    max-width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.pv-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.pv-modal-header h2 {
    margin: 0;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pv-tag-badge-inline {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    color: white;
    font-size: 14px;
    font-weight: 600;
}

.pv-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.pv-modal-close:hover {
    background: #f3f4f6;
    color: #111827;
}

.pv-modal-body {
    padding: 20px;
}

.pv-form-group {
    margin-bottom: 20px;
}

.pv-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

.pv-form-group input[type="text"],
.pv-form-group input[type="color"] {
    width: 100%;
}

.pv-form-group input[type="color"] {
    height: 40px;
    cursor: pointer;
}

.pv-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

/* Images Grid in Modal */
.pv-modal-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.pv-image-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
}

.pv-image-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.pv-image-wrapper {
    position: relative;
    padding-bottom: 75%;
    overflow: hidden;
    background: #f0f0f0;
}

.pv-image-wrapper img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pv-remove-tag-from-image {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.9);
    border: none;
    color: white;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    padding: 0;
    z-index: 5;
}

.pv-remove-tag-from-image:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.pv-remove-tag-from-image .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.pv-image-item:hover .pv-remove-tag-from-image {
    display: flex;
}

.pv-image-info {
    padding: 10px;
}

.pv-image-title {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pv-image-date {
    font-size: 12px;
    color: #6b7280;
}

/* Lightbox */
.pv-lightbox-content {
    max-width: 90%;
    max-height: 90%;
    margin: 5% auto;
}

.pv-lightbox-content .pv-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(255, 255, 255, 0.9);
    z-index: 10;
}

.pv-lightbox-image-container {
    max-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
}

.pv-lightbox-image-container img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
}

.pv-lightbox-info {
    padding: 20px;
}

.pv-lightbox-info h3 {
    margin: 0 0 10px 0;
}
</style>

<style>
/* Assign Images Modal */
.pv-assign-modal-content {
    max-width: 95%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pv-assign-modal-content .pv-modal-body {
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.pv-assign-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.pv-assign-filters input {
    flex: 1;
}

.pv-assign-status {
    padding: 10px 15px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    margin-bottom: 20px;
    color: #1e40af;
    font-weight: 600;
}

#pv-selected-count {
    font-size: 18px;
    color: #1e3a8a;
}

.pv-assign-images-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
    flex: 1;
}

.pv-assign-image-item {
    position: relative;
    background: white;
    border: 3px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
}

.pv-assign-image-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.pv-assign-image-item.selected {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.pv-assign-image-item.already-tagged {
    opacity: 0.5;
    cursor: not-allowed;
}

.pv-assign-image-item.already-tagged::after {
    content: '✓ Tagged';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(16, 185, 129, 0.95);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 12px;
}

.pv-assign-image-wrapper {
    position: relative;
    padding-bottom: 100%;
    overflow: hidden;
    background: #f0f0f0;
}

.pv-assign-image-wrapper img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pv-assign-checkbox {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 24px;
    height: 24px;
    background: white;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 5;
    transition: all 0.2s;
}

.pv-assign-image-item.selected .pv-assign-checkbox {
    background: #3b82f6;
    border-color: #3b82f6;
}

.pv-assign-checkbox .dashicons {
    color: white;
    font-size: 16px;
    width: 16px;
    height: 16px;
    display: none;
}

.pv-assign-image-item.selected .pv-assign-checkbox .dashicons {
    display: block;
}

.pv-assign-image-title {
    padding: 8px;
    font-size: 12px;
    font-weight: 500;
    color: #374151;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    background: white;
}

.pv-assign-actions {
    display: flex;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    position: sticky;
    bottom: 0;
    background: white;
}

.pv-assign-actions .button {
    display: flex;
    align-items: center;
    gap: 8px;
}

#pv-assign-submit {
    flex: 1;
}
</style>

<script>
jQuery(document).ready(function($) {
    let isEditMode = false;
    
    // Open modal for new tag
    $('#pv-add-new-tag, #pv-create-first-tag').on('click', function(e) {
        e.preventDefault();
        isEditMode = false;
        $('#pv-modal-title').text('<?php _e('Add New Tag', 'photovault'); ?>');
        $('#pv-tag-id').val('');
        $('#pv-tag-name-input').val('');
        $('#pv-tag-color-input').val('#667eea');
        $('#pv-tag-modal').fadeIn(300);
    });
    
    // Open modal for edit
    $('.pv-edit-tag').on('click', function(e) {
        e.preventDefault();
        isEditMode = true;
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        $('#pv-modal-title').text('<?php _e('Edit Tag', 'photovault'); ?>');
        $('#pv-tag-id').val(tagId);
        $('#pv-tag-name-input').val(tagName);
        $('#pv-tag-color-input').val(tagColor);
        $('#pv-tag-modal').fadeIn(300);
    });
    
    // Close modals
    $('.pv-modal-close, .pv-cancel-tag, .pv-cancel-assign').on('click', function() {
        $(this).closest('.pv-modal').fadeOut(300);
    });
    
    $('.pv-modal-overlay').on('click', function() {
        $(this).closest('.pv-modal').fadeOut(300);
    });
    
    // Save tag
    $('#pv-tag-form').on('submit', function(e) {
        e.preventDefault();
        
        const tagId = $('#pv-tag-id').val();
        const tagName = $('#pv-tag-name-input').val().trim();
        const tagColor = $('#pv-tag-color-input').val();
        
        if (!tagName) {
            alert('<?php _e('Please enter a tag name', 'photovault'); ?>');
            return;
        }
        
        const action = isEditMode ? 'update_tag' : 'add_tag';
        const data = {
            action: action,
            nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
            name: tagName,
            color: tagColor
        };
        
        if (isEditMode) {
            data.tag_id = tagId;
        } else {
            data.tag_name = tagName;
            data.image_id = 0;
        }
        
        $('#pv-save-tag').prop('disabled', true).text('<?php _e('Saving...', 'photovault'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('Error saving tag', 'photovault'); ?>');
                    $('#pv-save-tag').prop('disabled', false).text('<?php _e('Save Tag', 'photovault'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error saving tag', 'photovault'); ?>');
                $('#pv-save-tag').prop('disabled', false).text('<?php _e('Save Tag', 'photovault'); ?>');
            }
        });
    });
    
    // Delete tag
    $('.pv-delete-tag').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to delete this tag? This will remove it from all images.', 'photovault'); ?>')) {
            return;
        }
        
        const tagId = $(this).data('tag-id');
        const $card = $(this).closest('.pv-tag-card');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_tag',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                tag_id: tagId
            },
            success: function(response) {
                if (response.success) {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        if ($('.pv-tag-card').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || '<?php _e('Error deleting tag', 'photovault'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error deleting tag', 'photovault'); ?>');
            }
        });
    });
    
    // View images by tag
    $('.pv-view-tag-images').on('click', function(e) {
        e.preventDefault();
        
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        // Update modal title
        $('#pv-modal-tag-name').text(tagName);
        $('#pv-modal-tag-badge').css('background-color', tagColor).text(tagName);
        
        // Show modal and loading state
        $('#pv-images-modal').fadeIn(300);
        $('#pv-images-loading').show();
        $('#pv-images-grid').hide().empty();
        $('#pv-images-empty').hide();
        
        // Load images
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_images_by_tag',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                tag_id: tagId,
                limit: 50,
                offset: 0
            },
            success: function(response) {
                $('#pv-images-loading').hide();
                
                if (response.success && response.data.images && response.data.images.length > 0) {
                    const images = response.data.images;
                    const $grid = $('#pv-images-grid');
                    
                    images.forEach(function(image) {
                        const html = `
                            <div class="pv-image-item" data-image-id="${image.id}" data-full-url="${image.full_url || image.thumbnail_url}">
                                <div class="pv-image-wrapper">
                                    <img src="${image.thumbnail_url}" alt="${image.title}" loading="lazy">
                                    <button class="pv-remove-tag-from-image" 
                                            data-image-id="${image.id}" 
                                            data-tag-id="${tagId}"
                                            title="<?php _e('Remove tag from this image', 'photovault'); ?>">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                                <div class="pv-image-info">
                                    <div class="pv-image-title">${image.title}</div>
                                    <div class="pv-image-date">${image.formatted_date}</div>
                                </div>
                            </div>
                        `;
                        $grid.append(html);
                    });
                    
                    $grid.show();
                } else {
                    $('#pv-images-empty').show();
                }
            },
            error: function(xhr, status, error) {
                $('#pv-images-loading').hide();
                $('#pv-images-empty').html(`
                    <p style="color: #dc2626;">
                        <?php _e('Error loading images. Please try again.', 'photovault'); ?>
                    </p>
                `).show();
                console.error('AJAX Error:', error, xhr.responseText);
            }
        });
    });
    
    // View image in lightbox
    $(document).on('click', '.pv-image-item', function(e) {
        // Don't open lightbox if clicking remove button
        if ($(e.target).closest('.pv-remove-tag-from-image').length) {
            return;
        }
        
        const fullUrl = $(this).data('full-url');
        const title = $(this).find('.pv-image-title').text();
        const date = $(this).find('.pv-image-date').text();
        
        $('#pv-lightbox-image').attr('src', fullUrl);
        $('#pv-lightbox-title').text(title);
        $('#pv-lightbox-date').text(date);
        $('#pv-lightbox-modal').fadeIn(300);
    });
    
    // Remove tag from image (in tag images modal)
    $(document).on('click', '.pv-remove-tag-from-image', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!confirm('<?php _e('Remove this tag from the image?', 'photovault'); ?>')) {
            return;
        }
        
        const $btn = $(this);
        const $item = $btn.closest('.pv-image-item');
        const imageId = $btn.data('image-id');
        const tagId = $btn.data('tag-id');
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_tag',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                image_id: imageId,
                tag_id: tagId
            },
            success: function(response) {
                if (response.success) {
                    // Remove image from grid with animation
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if grid is empty
                        if ($('#pv-images-grid .pv-image-item').length === 0) {
                            $('#pv-images-grid').hide();
                            $('#pv-images-empty').show();
                        }
                    });
                    
                    // Update tag count in main view
                    const $tagCard = $('.pv-tag-card[data-tag-id="' + tagId + '"]');
                    const $countSpan = $tagCard.find('.pv-tag-count');
                    const currentCount = parseInt($countSpan.text());
                    const newCount = currentCount - 1;
                    
                    if (newCount > 0) {
                        $countSpan.text(newCount + ' <?php _e('images', 'photovault'); ?>');
                    } else {
                        $countSpan.text('0 <?php _e('images', 'photovault'); ?>');
                    }
                } else {
                    alert(response.data.message || '<?php _e('Failed to remove tag', 'photovault'); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php _e('Error removing tag', 'photovault'); ?>');
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Assign images to tag
    let selectedImages = [];
    let currentAssignTagId = null;
    
    $('.pv-assign-images').on('click', function(e) {
        e.preventDefault();
        
        const tagId = $(this).data('tag-id');
        const tagName = $(this).data('tag-name');
        const tagColor = $(this).data('tag-color');
        
        currentAssignTagId = tagId;
        selectedImages = [];
        
        // Update modal title
        $('#pv-assign-tag-name').text(tagName);
        $('#pv-assign-tag-badge').css('background-color', tagColor).text(tagName);
        $('#pv-selected-count').text('0');
        
        // Show modal and loading
        $('#pv-assign-images-modal').fadeIn(300);
        $('#pv-assign-loading').show();
        $('#pv-assign-images-grid').hide().empty();
        $('#pv-assign-empty').hide();
        
        // Load all user images
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pv_get_user_images',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                tag_id: tagId
            },
            success: function(response) {
                $('#pv-assign-loading').hide();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayAssignImages(response.data, tagId);
                    $('#pv-assign-images-grid').show();
                } else {
                    $('#pv-assign-empty').show();
                }
            },
            error: function() {
                $('#pv-assign-loading').hide();
                $('#pv-assign-empty').html('<p style="color: #dc2626;">Error loading images.</p>').show();
            }
        });
    });
    
    // Display images in assign modal
    function displayAssignImages(images, tagId) {
        const $grid = $('#pv-assign-images-grid');
        $grid.empty();
        
        images.forEach(function(image) {
            const alreadyTagged = image.has_tag ? 'already-tagged' : '';
            const html = `
                <div class="pv-assign-image-item ${alreadyTagged}" data-image-id="${image.id}">
                    <div class="pv-assign-image-wrapper">
                        <img src="${image.thumbnail_url}" alt="${image.title}" loading="lazy">
                        ${!image.has_tag ? `
                            <div class="pv-assign-checkbox">
                                <span class="dashicons dashicons-yes"></span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="pv-assign-image-title">${image.title}</div>
                </div>
            `;
            $grid.append(html);
        });
    }
    
    // Toggle image selection
    $(document).on('click', '.pv-assign-image-item:not(.already-tagged)', function() {
        const imageId = $(this).data('image-id');
        
        $(this).toggleClass('selected');
        
        if ($(this).hasClass('selected')) {
            if (!selectedImages.includes(imageId)) {
                selectedImages.push(imageId);
            }
        } else {
            selectedImages = selectedImages.filter(id => id !== imageId);
        }
        
        $('#pv-selected-count').text(selectedImages.length);
    });
    
    // Select all
    $('#pv-assign-select-all').on('click', function() {
        $('.pv-assign-image-item:not(.already-tagged)').addClass('selected');
        selectedImages = [];
        $('.pv-assign-image-item.selected').each(function() {
            selectedImages.push($(this).data('image-id'));
        });
        $('#pv-selected-count').text(selectedImages.length);
    });
    
    // Deselect all
    $('#pv-assign-deselect-all').on('click', function() {
        $('.pv-assign-image-item').removeClass('selected');
        selectedImages = [];
        $('#pv-selected-count').text('0');
    });
    
    // Search images
    $('#pv-assign-search').on('input', function() {
        const query = $(this).val().toLowerCase();
        
        $('.pv-assign-image-item').each(function() {
            const title = $(this).find('.pv-assign-image-title').text().toLowerCase();
            if (title.includes(query)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Submit assignment
    $('#pv-assign-submit').on('click', function() {
        if (selectedImages.length === 0) {
            alert('<?php _e('Please select at least one image', 'photovault'); ?>');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span><?php _e('Assigning...', 'photovault'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pv_assign_images_to_tag',
                nonce: '<?php echo wp_create_nonce('photovault_nonce'); ?>',
                tag_id: currentAssignTagId,
                image_ids: selectedImages
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    
                    // Update tag count in main view
                    const $tagCard = $('.pv-tag-card[data-tag-id="' + currentAssignTagId + '"]');
                    const $countSpan = $tagCard.find('.pv-tag-count');
                    const newCount = response.data.new_count;
                    $countSpan.text(newCount + ' <?php _e('images', 'photovault'); ?>');
                    
                    // Close modal
                    $('#pv-assign-images-modal').fadeOut(300);
                    selectedImages = [];
                } else {
                    alert(response.data.message || '<?php _e('Error assigning images', 'photovault'); ?>');
                }
                
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span><?php _e('Assign Selected Images', 'photovault'); ?>');
            },
            error: function() {
                alert('<?php _e('Error assigning images', 'photovault'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span><?php _e('Assign Selected Images', 'photovault'); ?>');
            }
        });
    });
});
</script>