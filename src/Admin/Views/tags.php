<?php
/**
 * Tags Admin View - Example showing how to add View Images links
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
                        <a href="<?php echo esc_url(admin_url('admin.php?page=photovault-tags&tag_id=' . $tag->id)); ?>" 
                           class="button button-primary button-small">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <?php _e('View Images', 'photovault'); ?>
                        </a>
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

.pv-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.pv-modal-header h2 {
    margin: 0;
    font-size: 20px;
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
    
    // Close modal
    $('.pv-modal-close, .pv-cancel-tag, .pv-modal-overlay').on('click', function() {
        $('#pv-tag-modal').fadeOut(300);
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
            data.image_id = 0; // Not associated with any image yet
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
                        
                        // Check if no tags left
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
});
</script>