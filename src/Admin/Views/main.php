<?php
/**
 * Admin Main Gallery View
 *
 * @package PhotoVault
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <h1><?php _e('PhotoVault Gallery', 'photovault'); ?>
        <button class="page-title-action pv-upload-btn" id="pv-upload-btn">
            <span class="dashicons dashicons-upload"></span> <?php _e('Upload Images', 'photovault'); ?>
        </button>
        <button class="page-title-action" id="pv-create-album-btn">
            <span class="dashicons dashicons-images-alt2"></span> <?php _e('Create Album', 'photovault'); ?>
        </button>
    </h1>

    <div class="photovault-container">
        <!-- Sidebar Filters -->
        <div class="pv-sidebar">
            <div class="pv-filter-section">
                <h3><?php _e('Filters', 'photovault'); ?></h3>
                
                <div class="pv-filter-group">
                    <label><?php _e('Search', 'photovault'); ?></label>
                    <input type="text" id="pv-search" placeholder="<?php _e('Search images...', 'photovault'); ?>">
                </div>

                <div class="pv-filter-group">
                    <label><?php _e('Album', 'photovault'); ?></label>
                    <select id="pv-filter-album">
                        <option value=""><?php _e('All Albums', 'photovault'); ?></option>
                    </select>
                </div>

                <div class="pv-filter-group">
                    <label><?php _e('Tags', 'photovault'); ?></label>
                    <div id="pv-tags-list"></div>
                </div>

                <div class="pv-filter-group">
                    <label><?php _e('View Mode', 'photovault'); ?></label>
                    <div class="pv-view-toggle">
                        <button class="pv-view-btn active" data-view="grid">
                            <span class="dashicons dashicons-grid-view"></span>
                        </button>
                        <button class="pv-view-btn" data-view="list">
                            <span class="dashicons dashicons-list-view"></span>
                        </button>
                    </div>
                </div>

                <button class="button" id="pv-clear-filters"><?php _e('Clear Filters', 'photovault'); ?></button>
            </div>

            <div class="pv-stats-section">
                <h3><?php _e('Statistics', 'photovault'); ?></h3>
                <div class="pv-stat-item">
                    <span class="pv-stat-label"><?php _e('Total Images', 'photovault'); ?></span>
                    <span class="pv-stat-value" id="pv-total-images">0</span>
                </div>
                <div class="pv-stat-item">
                    <span class="pv-stat-label"><?php _e('Total Albums', 'photovault'); ?></span>
                    <span class="pv-stat-value" id="pv-total-albums">0</span>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="pv-main-content">
            <div class="pv-toolbar">
                <div class="pv-bulk-actions">
                    <input type="checkbox" id="pv-select-all">
                    <label for="pv-select-all"><?php _e('Select All', 'photovault'); ?></label>
                    <button class="button" id="pv-bulk-delete" style="display:none;">
                        <?php _e('Delete Selected', 'photovault'); ?>
                    </button>
                    <button class="button" id="pv-bulk-add-album" style="display:none;">
                        <?php _e('Add to Album', 'photovault'); ?>
                    </button>
                </div>
                <div class="pv-sort">
                    <select id="pv-sort-by">
                        <option value="date_desc"><?php _e('Newest First', 'photovault'); ?></option>
                        <option value="date_asc"><?php _e('Oldest First', 'photovault'); ?></option>
                        <option value="title_asc"><?php _e('Title A-Z', 'photovault'); ?></option>
                        <option value="title_desc"><?php _e('Title Z-A', 'photovault'); ?></option>
                    </select>
                </div>
            </div>

            <!-- Image Grid -->
            <div id="pv-images-grid" class="pv-grid-view"></div>

            <!-- Loading Indicator -->
            <div id="pv-loading" class="pv-loading" style="display:none;">
                <span class="spinner is-active"></span>
            </div>

            <!-- Load More -->
            <div class="pv-load-more">
                <button class="button button-primary" id="pv-load-more-btn">
                    <?php _e('Load More', 'photovault'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div id="pv-upload-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content">
        <span class="pv-modal-close">&times;</span>
        <h2><?php _e('Upload Images', 'photovault'); ?></h2>
        
        <div class="pv-upload-area" id="pv-drop-zone">
            <div class="pv-upload-placeholder">
                <span class="dashicons dashicons-cloud-upload"></span>
                <p><?php _e('Drag & drop images here or click to select', 'photovault'); ?></p>
                <input type="file" id="pv-file-input" multiple accept="image/*" style="display:none;">
                <button class="button button-primary" id="pv-select-files-btn">
                    <?php _e('Select Files', 'photovault'); ?>
                </button>
            </div>
        </div>

        <div id="pv-upload-previews"></div>
        <div id="pv-upload-progress" style="display:none;">
            <div class="pv-progress-bar">
                <div class="pv-progress-fill"></div>
            </div>
            <div class="pv-progress-text">0%</div>
        </div>

        <div class="pv-upload-options">
            <div class="pv-upload-field">
                <label><?php _e('Add to Album', 'photovault'); ?></label>
                <select id="pv-upload-album">
                    <option value=""><?php _e('None', 'photovault'); ?></option>
                </select>
            </div>

            <div class="pv-upload-field">
                <label><?php _e('Tags (comma separated)', 'photovault'); ?></label>
                <input type="text" id="pv-upload-tags" placeholder="vacation, summer, beach">
            </div>

            <div class="pv-upload-field">
                <label><?php _e('Visibility', 'photovault'); ?></label>
                <select id="pv-upload-visibility">
                    <option value="private"><?php _e('Private', 'photovault'); ?></option>
                    <option value="shared"><?php _e('Shared', 'photovault'); ?></option>
                    <option value="public"><?php _e('Public', 'photovault'); ?></option>
                </select>
            </div>
        </div>

        <div class="pv-upload-actions">
            <button class="button button-primary" id="pv-start-upload">
                <?php _e('Upload', 'photovault'); ?>
            </button>
            <button class="button" id="pv-cancel-upload">
                <?php _e('Cancel', 'photovault'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Create Album Modal -->
<div id="pv-album-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content">
        <span class="pv-modal-close">&times;</span>
        <h2><?php _e('Create New Album', 'photovault'); ?></h2>
        
        <div class="pv-form-field">
            <label><?php _e('Album Name', 'photovault'); ?></label>
            <input type="text" id="pv-album-name" required>
        </div>

        <div class="pv-form-field">
            <label><?php _e('Description', 'photovault'); ?></label>
            <textarea id="pv-album-description" rows="4"></textarea>
        </div>

        <div class="pv-form-field">
            <label><?php _e('Visibility', 'photovault'); ?></label>
            <select id="pv-album-visibility">
                <option value="private"><?php _e('Private', 'photovault'); ?></option>
                <option value="shared"><?php _e('Shared', 'photovault'); ?></option>
                <option value="public"><?php _e('Public', 'photovault'); ?></option>
            </select>
        </div>

        <div class="pv-modal-actions">
            <button class="button button-primary" id="pv-save-album">
                <?php _e('Create Album', 'photovault'); ?>
            </button>
            <button class="button" id="pv-cancel-album">
                <?php _e('Cancel', 'photovault'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Image Detail Modal -->
<div id="pv-detail-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content pv-detail-modal">
        <span class="pv-modal-close">&times;</span>
        <div class="pv-detail-container">
            <div class="pv-detail-image">
                <img src="" alt="" id="pv-detail-img">
                <div class="pv-detail-nav">
                    <button class="pv-nav-btn" id="pv-prev-image">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>
                    <button class="pv-nav-btn" id="pv-next-image">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>
            <div class="pv-detail-sidebar">
                <h3 id="pv-detail-title"></h3>
                <div class="pv-detail-meta">
                    <p><strong><?php _e('Upload Date:', 'photovault'); ?></strong> <span id="pv-detail-date"></span></p>
                    <p><strong><?php _e('Visibility:', 'photovault'); ?></strong> <span id="pv-detail-visibility"></span></p>
                </div>
                <div class="pv-detail-tags">
                    <strong><?php _e('Tags:', 'photovault'); ?></strong>
                    <div id="pv-detail-tags-list"></div>
                    <input type="text" id="pv-add-tag-input" placeholder="<?php _e('Add tag...', 'photovault'); ?>">
                    <button class="button button-small" id="pv-add-tag-btn"><?php _e('Add', 'photovault'); ?></button>
                </div>
                <div class="pv-detail-share">
                    <strong><?php _e('Share with User:', 'photovault'); ?></strong>
                    <select id="pv-share-user">
                        <option value=""><?php _e('Select user...', 'photovault'); ?></option>
                        <?php
                        $users = get_users();
                        foreach ($users as $user) {
                            if ($user->ID != get_current_user_id()) {
                                echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <button class="button button-small" id="pv-share-btn"><?php _e('Share', 'photovault'); ?></button>
                </div>
                <div class="pv-detail-actions">
                    <button class="button" id="pv-download-image">
                        <span class="dashicons dashicons-download"></span> <?php _e('Download', 'photovault'); ?>
                    </button>
                    <button class="button button-link-delete" id="pv-delete-image">
                        <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'photovault'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>