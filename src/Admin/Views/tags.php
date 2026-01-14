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