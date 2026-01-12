<?php
/**
 * Asset Manager - Handles CSS and JS enqueuing
 *
 * @package PhotoVault
 */

namespace PhotoVault\Admin;

class AssetManager {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on PhotoVault pages
        if (strpos($hook, 'photovault') === false) {
            return;
        }
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        // Admin CSS
        wp_enqueue_style(
            'photovault-admin-main',
            PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/main.css',
            [],
            PHOTOVAULT_VERSION
        );
        
        if (strpos($hook, 'photovault-albums') !== false) {
            wp_enqueue_style(
                'photovault-admin-albums',
                PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/albums.css',
                ['photovault-admin-main'],
                PHOTOVAULT_VERSION
            );
        }
        
        if (strpos($hook, 'photovault-timeline') !== false) {
            wp_enqueue_style(
                'photovault-admin-timeline',
                PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/timeline.css',
                ['photovault-admin-main'],
                PHOTOVAULT_VERSION
            );
        }
        
        // Admin JavaScript
        wp_enqueue_script(
            'photovault-admin-main',
            PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/main.js',
            ['jquery', 'wp-util'],
            PHOTOVAULT_VERSION,
            true
        );
        
        wp_enqueue_script(
            'photovault-admin-upload',
            PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/upload.js',
            ['jquery', 'photovault-admin-main'],
            PHOTOVAULT_VERSION,
            true
        );
        
        if (strpos($hook, 'photovault-albums') !== false) {
            wp_enqueue_script(
                'photovault-admin-albums',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/albums.js',
                ['jquery', 'photovault-admin-main'],
                PHOTOVAULT_VERSION,
                true
            );
        }
        
        if (strpos($hook, 'photovault-timeline') !== false) {
            wp_enqueue_script(
                'photovault-admin-timeline',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/timeline.js',
                ['jquery', 'photovault-admin-main'],
                PHOTOVAULT_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('photovault-admin-main', 'photoVault', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('photovault_nonce'),
            'uploadUrl' => admin_url('async-upload.php'),
            'maxFileSize' => get_option('photovault_max_upload_size', 10485760),
            'allowedTypes' => get_option('photovault_allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']),
            'chunkSize' => 1024 * 1024, // 1MB chunks
            'i18n' => [
                'uploading' => __('Uploading...', 'photovault'),
                'uploadSuccess' => __('Upload successful', 'photovault'),
                'uploadError' => __('Upload failed', 'photovault'),
                'confirmDelete' => __('Are you sure you want to delete this?', 'photovault'),
                'selectFiles' => __('Select files', 'photovault'),
                'dragDrop' => __('Drag & drop images here', 'photovault'),
                'processing' => __('Processing...', 'photovault'),
            ]
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Frontend CSS
        wp_enqueue_style(
            'photovault-frontend',
            PHOTOVAULT_PLUGIN_URL . 'assets/css/frontend/gallery.css',
            [],
            PHOTOVAULT_VERSION
        );
        
        wp_enqueue_style(
            'photovault-lightbox',
            PHOTOVAULT_PLUGIN_URL . 'assets/css/frontend/lightbox.css',
            ['photovault-frontend'],
            PHOTOVAULT_VERSION
        );
        
        // Frontend JavaScript
        wp_enqueue_script(
            'photovault-frontend',
            PHOTOVAULT_PLUGIN_URL . 'assets/js/frontend/gallery.js',
            ['jquery'],
            PHOTOVAULT_VERSION,
            true
        );
        
        wp_enqueue_script(
            'photovault-lightbox',
            PHOTOVAULT_PLUGIN_URL . 'assets/js/frontend/lightbox.js',
            ['jquery', 'photovault-frontend'],
            PHOTOVAULT_VERSION,
            true
        );
        
        // Localize frontend script
        wp_localize_script('photovault-frontend', 'photoVault', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('photovault_nonce'),
            'i18n' => [
                'loading' => __('Loading...', 'photovault'),
                'loadMore' => __('Load More', 'photovault'),
                'noImages' => __('No images found', 'photovault'),
                'error' => __('An error occurred', 'photovault'),
            ]
        ]);
    }
}