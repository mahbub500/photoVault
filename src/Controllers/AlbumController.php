<?php
/**
 * Album Controller - Compatible with existing database schema
 *
 * @package PhotoVault
 */
namespace PhotoVault\Controllers;

use PhotoVault\Models\Album;

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
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $params = [
            'user_id' => get_current_user_id()
        ];
        
        // Admin can see all albums if requested
        if (current_user_can('manage_options') && isset($_POST['all_albums'])) {
            unset($params['user_id']);
        }
        
        $albums = $this->album_model->get_albums($params);
        
        if ($albums) {
            wp_send_json_success($albums);
        } else {
            wp_send_json_success([]);
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
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $album = $this->album_model->get_album_details($album_id);
        
        if ($album) {
            wp_send_json_success($album);
        } else {
            wp_send_json_error(['message' => __('Album not found', 'photovault')]);
        }
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $result = $this->album_model->add_image($album_id, $image_id, $position);
        
        if ($result !== false) {
            wp_send_json_success(['message' => __('Image added to album', 'photovault')]);
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->reorder_images($album_id, $image_order)) {
            wp_send_json_success(['message' => __('Images reordered successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to reorder images', 'photovault')]);
        }
    }
}