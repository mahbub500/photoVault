<?php
/**
 * Album Controller - Compatible with existing database schema
 *
 * @package PhotoVault
 */
namespace PhotoVault\Controllers;

use PhotoVault\Models\Album;
use PhotoVault\Helpers\ImageHelper;

class AlbumController {
    
    private $album_model;
    
    public function __construct() {
        $this->album_model = new Album();
        $this->register_ajax_actions();
    }
    
    /**
     * Register AJAX actions
     */
    private function register_ajax_actions() {
        add_action('wp_ajax_pv_create_album', [$this, 'create']);
        add_action('wp_ajax_pv_get_albums', [$this, 'get_albums']);
        add_action('wp_ajax_pv_get_album_details', [$this, 'get_album_details']);
        add_action('wp_ajax_pv_update_album', [$this, 'update']);
        add_action('wp_ajax_pv_delete_album', [$this, 'delete']);
        add_action('wp_ajax_pv_remove_image_from_album', [$this, 'remove_image_from_album']);
        add_action('wp_ajax_pv_add_image_to_album', [$this, 'add_image_to_album']);
        add_action('wp_ajax_pv_set_album_cover', [$this, 'set_album_cover']);
        add_action('wp_ajax_pv_reorder_album_images', [$this, 'reorder_images']);
        add_action('wp_ajax_pv_get_album_images', [$this, 'get_album_images']);
    }
    
    /**
     * Helper: Get image URL
     */
    private function get_image_url($attachment_id, $size = 'medium') {
        if (class_exists('PhotoVault\\Helpers\\ImageHelper')) {
            return ImageHelper::get_image_url($attachment_id, $size);
        }
        
        // Fallback
        $url = wp_get_attachment_image_url($attachment_id, $size);
        return $url ?: wp_get_attachment_url($attachment_id);
    }
    
    /**
     * Helper: Process album data for response
     */
    private function process_album_data($album) {
        if (!$album) {
            return null;
        }
        
        // Ensure cover_image_url is set
        if (empty($album->cover_image_url) && !empty($album->cover_image_id)) {
            global $wpdb;
            $table_images = $wpdb->prefix . 'pv_images';
            
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT attachment_id FROM {$table_images} WHERE id = %d",
                $album->cover_image_id
            ));
            
            if ($attachment_id) {
                $album->cover_image_url = $this->get_image_url($attachment_id, 'medium');
            }
        }
        
        return $album;
    }
    
    /**
     * Helper: Process albums array
     */
    private function process_albums_data($albums) {
        if (!$albums || !is_array($albums)) {
            return [];
        }
        
        foreach ($albums as $album) {
            $this->process_album_data($album);
        }
        
        return $albums;
    }
    
    /**
     * Helper: Validate album access
     */
    private function validate_album_access($album_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$this->album_model->user_owns_album($album_id, $user_id)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Create album
     */
    public function create() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('photovault_manage_albums')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error(['message' => __('Album name is required', 'photovault')]);
        }
        
        $data = [
            'user_id' => get_current_user_id(),
            'name' => $name,
            'slug' => sanitize_title($name),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
        ];
        
        // Check for duplicate slug
        $existing = $this->album_model->get_by_slug($data['slug'], get_current_user_id());
        if ($existing) {
            $data['slug'] = $data['slug'] . '-' . time();
        }
        
        $album_id = $this->album_model->create($data);
        
        if ($album_id) {
            wp_send_json_success([
                'album_id' => $album_id,
                'message' => __('Album created successfully', 'photovault')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to create album', 'photovault')]);
        }
    }
    
    /**
     * Get albums
     */
    public function get_albums() {
        try {
            check_ajax_referer('photovault_nonce', 'nonce');
            
            $user_id = get_current_user_id();
            
            error_log('PhotoVault: Getting albums for user ' . $user_id);
            
            $params = [
                'user_id' => $user_id
            ];
            
            // Admin can see all albums if requested
            if (current_user_can('manage_options') && isset($_POST['all_albums'])) {
                unset($params['user_id']);
                error_log('PhotoVault: Admin viewing all albums');
            }
            
            $albums = $this->album_model->get_albums($params);
            
            error_log('PhotoVault: Found ' . count($albums) . ' albums');
            
            // Process albums to ensure all data is correct
            $albums = $this->process_albums_data($albums);
            
            if ($albums && count($albums) > 0) {
                error_log('PhotoVault: Returning albums - ' . json_encode($albums));
                wp_send_json_success($albums);
            } else {
                error_log('PhotoVault: No albums found, returning empty array');
                wp_send_json_success([]);
            }
            
        } catch (Exception $e) {
            error_log('PhotoVault Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Error loading albums: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get album details
     */
    public function get_album_details() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (!$album_id) {
            wp_send_json_error(['message' => __('Invalid album ID', 'photovault')]);
        }
        
        // Check permissions
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $album = $this->album_model->get_album_details($album_id);
        
        if ($album) {
            // Process album data
            $album = $this->process_album_data($album);
            wp_send_json_success($album);
        } else {
            wp_send_json_error(['message' => __('Album not found', 'photovault')]);
        }
    }
    
    /**
     * Get album images (separate endpoint for loading images)
     */
    public function get_album_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (!$album_id) {
            wp_send_json_error(['message' => __('Invalid album ID', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        global $wpdb;
        $table_images = $wpdb->prefix . 'pv_images';
        $table_image_album = $wpdb->prefix . 'pv_image_album';
        
        $images_data = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, ia.position 
             FROM {$table_image_album} ia
             LEFT JOIN {$table_images} i ON ia.image_id = i.id
             WHERE ia.album_id = %d
             ORDER BY ia.position ASC, ia.added_date DESC",
            $album_id
        ));
        
        $images = [];
        if ($images_data) {
            foreach ($images_data as $img) {
                $images[] = [
                    'id' => $img->id,
                    'attachment_id' => $img->attachment_id,
                    'title' => $img->title ?: 'Untitled',
                    'description' => $img->description,
                    'thumbnail_url' => $this->get_image_url($img->attachment_id, 'thumbnail'),
                    'medium_url' => $this->get_image_url($img->attachment_id, 'medium'),
                    'large_url' => $this->get_image_url($img->attachment_id, 'large'),
                    'full_url' => $this->get_image_url($img->attachment_id, 'full'),
                    'position' => $img->position
                ];
            }
        }
        
        wp_send_json_success($images);
    }
    
    /**
     * Update album
     */
    public function update() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (!$album_id) {
            wp_send_json_error(['message' => __('Invalid album ID', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $data = [];
        
        if (isset($_POST['name'])) {
            $data['name'] = sanitize_text_field($_POST['name']);
            $data['slug'] = sanitize_title($data['name']);
        }
        
        if (isset($_POST['description'])) {
            $data['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        if (isset($_POST['visibility'])) {
            $data['visibility'] = sanitize_text_field($_POST['visibility']);
        }
        
        if (isset($_POST['sort_order'])) {
            $data['sort_order'] = sanitize_text_field($_POST['sort_order']);
        }
        
        if (empty($data)) {
            wp_send_json_error(['message' => __('No data to update', 'photovault')]);
        }
        
        if ($this->album_model->update($album_id, $data)) {
            wp_send_json_success(['message' => __('Album updated successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update album', 'photovault')]);
        }
    }
    
    /**
     * Delete album
     */
    public function delete() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (!$album_id) {
            wp_send_json_error(['message' => __('Invalid album ID', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->delete($album_id)) {
            wp_send_json_success(['message' => __('Album deleted successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete album', 'photovault')]);
        }
    }
    
    /**
     * Add image to album
     */
    public function add_image_to_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);
        $position = intval($_POST['position'] ?? 0);
        
        if (!$album_id || !$image_id) {
            wp_send_json_error(['message' => __('Missing required data', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $result = $this->album_model->add_image($album_id, $image_id, $position);
        
        if ($result !== false) {
            // Get the image data to return
            global $wpdb;
            $table_images = $wpdb->prefix . 'pv_images';
            $image = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_images} WHERE id = %d",
                $image_id
            ));
            
            $image_data = null;
            if ($image) {
                $image_data = [
                    'id' => $image->id,
                    'attachment_id' => $image->attachment_id,
                    'title' => $image->title,
                    'thumbnail_url' => $this->get_image_url($image->attachment_id, 'thumbnail')
                ];
            }
            
            wp_send_json_success([
                'message' => __('Image added to album', 'photovault'),
                'image' => $image_data
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to add image or image already in album', 'photovault')]);
        }
    }
    
    /**
     * Remove image from album
     */
    public function remove_image_from_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$album_id || !$image_id) {
            wp_send_json_error(['message' => __('Missing required data', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->remove_image($album_id, $image_id)) {
            wp_send_json_success(['message' => __('Image removed from album', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove image', 'photovault')]);
        }
    }
    
    /**
     * Set album cover image
     */
    public function set_album_cover() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$album_id || !$image_id) {
            wp_send_json_error(['message' => __('Missing required data', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->set_cover_image($album_id, $image_id)) {
            wp_send_json_success(['message' => __('Cover image updated', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update cover image', 'photovault')]);
        }
    }
    
    /**
     * Reorder images in album
     */
    public function reorder_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        $image_order = json_decode(stripslashes($_POST['image_order'] ?? '[]'), true);
        
        if (!$album_id || !is_array($image_order)) {
            wp_send_json_error(['message' => __('Invalid data', 'photovault')]);
        }
        
        if (!$this->validate_album_access($album_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->reorder_images($album_id, $image_order)) {
            wp_send_json_success(['message' => __('Images reordered successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to reorder images', 'photovault')]);
        }
    }
}
