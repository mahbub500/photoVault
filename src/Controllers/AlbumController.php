<?php
/**
 * Album Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

use PhotoVault\Models\Album;

class AlbumController {
    
    private $album_model;
    
    public function __construct() {
        $this->album_model = new Album();
    }
    
    /**
     * Create album
     */
    public function create() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
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
        
        $result = $this->album_model->get_albums([
            'user_id' => get_current_user_id()
        ]);
        
        wp_send_json_success($result);
    }
    
    /**
     * Update album
     */
    public function update() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
        ];
        
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
        
        if (!$this->album_model->user_owns_album($album_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        if ($this->album_model->delete($album_id)) {
            wp_send_json_success(['message' => __('Album deleted successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete album', 'photovault')]);
        }
    }
}