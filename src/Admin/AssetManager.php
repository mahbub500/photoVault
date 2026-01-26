<?php
/**
 * Asset Manager - Handles CSS and JS enqueuing
 *
 * @package PhotoVault
 */
namespace PhotoVault\Admin;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AssetManager {
    
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
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
        
        if (str_contains($hook, 'photovault-timeline') || str_contains($hook, 'photovault')) {
            wp_enqueue_script(
                'photovault-admin-albums',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/albums.js',
                ['jquery', 'photovault-admin-main'],
                PHOTOVAULT_VERSION,
                true
            );
        }

        if (str_contains($hook, 'photovault') ) {

            // Frontend CSS
            wp_enqueue_style(
                'photovault-admin',
                PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/gallery.css',
                [],
                PHOTOVAULT_VERSION
            );
            // Frontend JavaScript
            wp_enqueue_script(
                'photovault-admin',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/gallery.js',
                ['jquery'],
                PHOTOVAULT_VERSION,
                true
            );
        }

        if (str_contains($hook, 'photovault-videos') ) {

            // Frontend CSS
            wp_enqueue_style(
                'photovault-videos',
                PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/videos.css',
                [],
                PHOTOVAULT_VERSION
            );
            // Frontend JavaScript
            wp_enqueue_script(
                'photovault-videos',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/videos.js',
                ['jquery'],
                PHOTOVAULT_VERSION,
                true
            );

            // Localize script with data
            wp_localize_script('photovault-videos', 'photoVaultVideos', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('photovault_nonce'),
                'max_upload_size' => get_option('photovault_max_video_upload_size', 104857600),
                'allowed_types' => get_option('photovault_allowed_video_types', ['mp4', 'mov', 'avi', 'wmv', 'webm']),
                'default_thumbnail' => PHOTOVAULT_PLUGIN_URL . 'assets/images/default-video-thumbnail.png',
                'i18n' => [
                    'file_too_large' => __('File size exceeds maximum allowed size.', 'photovault'),
                    'invalid_file_type' => __('Invalid file type.', 'photovault'),
                    'upload_success' => __('Video uploaded successfully!', 'photovault'),
                    'upload_error' => __('Failed to upload video.', 'photovault'),
                    'delete_confirm' => __('Are you sure you want to delete this video?', 'photovault'),
                    'delete_success' => __('Video deleted successfully!', 'photovault'),
                    'delete_error' => __('Failed to delete video.', 'photovault'),
                    'update_success' => __('Video updated successfully!', 'photovault'),
                    'update_error' => __('Failed to update video.', 'photovault'),
                ]
            ]);
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
                'noAlbums' => __('No albums found. Create your first album!', 'photovault'),
                'errorLoadingAlbums' => __('Error loading albums. Please try again.', 'photovault'),
                'viewAlbum' => __('View Album', 'photovault'),
                'images' => __('images', 'photovault'),
                'createNewAlbum' => __('Create New Album', 'photovault'),
                'editAlbum' => __('Edit Album', 'photovault'),
                'albumNameRequired' => __('Album name is required', 'photovault'),
                'saving' => __('Saving...', 'photovault'),
                'saveAlbum' => __('Save Album', 'photovault'),
                'errorSavingAlbum' => __('Error saving album. Please try again.', 'photovault'),
                'errorLoadingAlbum' => __('Error loading album details.', 'photovault'),
                'deleteAlbumConfirm' => __('Are you sure you want to delete this album? This action cannot be undone.', 'photovault'),
                'errorDeletingAlbum' => __('Error deleting album. Please try again.', 'photovault'),
                'noImagesInAlbum' => __('No images in this album yet. Add some images to get started!', 'photovault'),
                'removeImageConfirm' => __('Remove this image from the album?', 'photovault'),
                'errorRemovingImage' => __('Error removing image. Please try again.', 'photovault'),
                'featureComingSoon' => __('This feature is coming soon!', 'photovault'),
            ]
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        
        
        wp_enqueue_style(
            'photovault-lightbox',
            PHOTOVAULT_PLUGIN_URL . 'assets/css/frontend/lightbox.css',
            ['photovault-frontend'],
            PHOTOVAULT_VERSION
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