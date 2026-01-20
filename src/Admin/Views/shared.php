<?php
/**
 * PhotoVault - Shared Items Page Template
 * File: templates/admin-shared.php
 * 
 * This page shows items that have been shared with the current user
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap photovault-wrap">
    <h1>
        <span class="dashicons dashicons-share"></span>
        <?php esc_html_e('Shared With Me', 'photovault'); ?>
    </h1>

    <!-- Filter Tabs -->
    <div class="pv-shared-tabs">
        <button class="pv-tab-btn active" data-type="all">
            <?php esc_html_e('All', 'photovault'); ?>
            <span class="pv-tab-count" id="pv-count-all">0</span>
        </button>
        <button class="pv-tab-btn" data-type="album">
            <?php esc_html_e('Albums', 'photovault'); ?>
            <span class="pv-tab-count" id="pv-count-albums">0</span>
        </button>
        <button class="pv-tab-btn" data-type="image">
            <?php esc_html_e('Images', 'photovault'); ?>
            <span class="pv-tab-count" id="pv-count-images">0</span>
        </button>
    </div>

    <!-- Shared Items Container -->
    <div class="pv-shared-container">
        <div id="pv-shared-grid" class="pv-shared-grid"></div>
        <div id="pv-shared-loading" class="pv-loading" style="display:none;">
            <span class="spinner is-active"></span>
            <p><?php esc_html_e('Loading shared items...', 'photovault'); ?></p>
        </div>
    </div>
</div>

<!-- Shared Item Detail Modal -->
<div id="pv-shared-detail-modal" class="pv-modal" style="display:none;">
    <div class="pv-modal-content pv-shared-detail-content">
        <span class="pv-modal-close">&times;</span>
        
        <div class="pv-shared-header">
            <h2 id="pv-shared-item-name"></h2>
            <div class="pv-shared-meta">
                <span class="pv-shared-type">
                    <span class="dashicons" id="pv-shared-type-icon"></span>
                    <span id="pv-shared-type-text"></span>
                </span>
                <span class="pv-shared-permission">
                    <span class="dashicons dashicons-admin-users"></span>
                    <span id="pv-shared-permission-text"></span>
                </span>
                <span class="pv-shared-owner">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php esc_html_e('Owner:', 'photovault'); ?>
                    <strong id="pv-shared-owner-name"></strong>
                </span>
            </div>
        </div>

        <div class="pv-shared-actions-bar">
            <button class="button button-primary" id="pv-view-shared-item">
                <span class="dashicons dashicons-visibility"></span>
                <?php esc_html_e('View', 'photovault'); ?>
            </button>
            <button class="button" id="pv-download-shared-item" style="display:none;">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Download', 'photovault'); ?>
            </button>
        </div>

        <div id="pv-shared-item-preview" class="pv-shared-preview">
            <!-- Preview content will be loaded here -->
        </div>
    </div>
</div>