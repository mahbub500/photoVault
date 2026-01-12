<?php
/**
 * Image Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

use PhotoVault\Models\Image;
use PhotoVault\Services\ImageUploader;
use PhotoVault\Services\ImageProcessor;

class ImageController {
    
    private $image_model;
    private $uploader;
    private $processor;
    
    public function __construct() {
        $this->image_model = new Image();
        $this->uploader = new ImageUploader();
        $this->processor = new ImageProcessor();
    }
    
    /**
     * Upload image via AJAX
     */
    public function upload() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        try {
            // Check if file exists
            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => __('No file uploaded', 'photovault')]);
            }
            
            // Handle chunked upload
            if (isset($_POST['chunk_index']) && isset($_POST['total_chunks'])) {
                $this->handle_chunked_upload();
                return;
            }
            
            // Standard upload
            $upload_result = $this->uploader->upload($_FILES['file']);
            
            if (is_wp_error($upload_result)) {
                wp_send_json_error(['message' => $upload_result->get_error_message()]);
            }
            
            // Process image (create thumbnails, extract EXIF, etc.)
            $processed = $this->processor->process($upload_result['attachment_id']);
            
            // Save to database
            $image_data = [
                'attachment_id' => $upload_result['attachment_id'],
                'user_id' => get_current_user_id(),
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
                'file_size' => $processed['file_size'],
                'width' => $processed['width'],
                'height' => $processed['height'],
                'mime_type' => $processed['mime_type'],
            ];
            
            $image_id = $this->image_model->create($image_data);
            
            if (!$image_id) {
                wp_send_json_error(['message' => __('Failed to save image', 'photovault')]);
            }
            
            // Handle tags
            if (!empty($_POST['tags'])) {
                $tags = is_array($_POST['tags']) 
                    ? $_POST['tags'] 
                    : explode(',', $_POST['tags']);
                $this->image_model->add_tags($image_id, array_map('trim', $tags));
            }
            
            // Handle album
            if (!empty($_POST['album_id'])) {
                $this->image_model->add_to_album($image_id, intval($_POST['album_id']));
            }
            
            wp_send_json_success([
                'image_id' => $image_id,
                'attachment_id' => $upload_result['attachment_id'],
                'url' => $upload_result['url'],
                'thumbnail' => $processed['thumbnail'],
                'metadata' => $processed
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handle chunked upload
     */
    private function handle_chunked_upload() {
        $chunk_index = intval($_POST['chunk_index']);
        $total_chunks = intval($_POST['total_chunks']);
        $unique_id = sanitize_text_field($_POST['unique_id'] ?? '');
        
        if (empty($unique_id)) {
            wp_send_json_error(['message' => __('Invalid upload ID', 'photovault')]);
        }
        
        $result = $this->uploader->chunked_upload(
            $_FILES['file'],
            $chunk_index,
            $total_chunks
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // If this is the last chunk, process the complete file
        if ($chunk_index == $total_chunks - 1 && isset($result['attachment_id'])) {
            // Process like a normal upload
            $processed = $this->processor->process($result['attachment_id']);
            
            $image_data = [
                'attachment_id' => $result['attachment_id'],
                'user_id' => get_current_user_id(),
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
                'file_size' => $processed['file_size'],
                'width' => $processed['width'],
                'height' => $processed['height'],
                'mime_type' => $processed['mime_type'],
            ];
            
            $image_id = $this->image_model->create($image_data);
            
            if (!empty($_POST['tags'])) {
                $tags = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', $_POST['tags']);
                $this->image_model->add_tags($image_id, array_map('trim', $tags));
            }
            
            if (!empty($_POST['album_id'])) {
                $this->image_model->add_to_album($image_id, intval($_POST['album_id']));
            }
            
            wp_send_json_success([
                'image_id' => $image_id,
                'attachment_id' => $result['attachment_id'],
                'url' => $result['url'],
                'thumbnail' => $processed['thumbnail'],
                'complete' => true
            ]);
        } else {
            // Chunk received, waiting for more
            wp_send_json_success([
                'chunk' => $chunk_index,
                'total' => $total_chunks,
                'status' => 'chunk_received'
            ]);
        }
    }
    
    /**
     * Get images with filters
     */
    public function get_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $params = [
            'user_id' => get_current_user_id(),
            'album_id' => isset($_POST['album_id']) ? intval($_POST['album_id']) : 0,
            'tag_id' => isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
            'sort' => isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'date_desc',
            'visibility' => isset($_POST['visibility']) ? sanitize_text_field($_POST['visibility']) : '',
        ];
        
        $result = $this->image_model->get_images($params);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get single image
     */
    public function get_image() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        $image = $this->image_model->get($image_id);
        
        if (!$image) {
            wp_send_json_error(['message' => __('Image not found', 'photovault')]);
        }
        
        // Check if user has access
        $user_id = get_current_user_id();
        if ($image->user_id != $user_id && !current_user_can('manage_options')) {
            // Check if shared with user
            // This would require Share model - simplified for now
            wp_send_json_error(['message' => __('Access denied', 'photovault')]);
        }
        
        wp_send_json_success($image);
    }
    
    /**
     * Update image
     */
    public function update() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        // Verify ownership
        if (!$this->image_model->user_owns_image($image_id, $user_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $data = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
        ];
        
        $updated = $this->image_model->update($image_id, $data);
        
        if ($updated) {
            wp_send_json_success(['message' => __('Image updated successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update image', 'photovault')]);
        }
    }
    
    /**
     * Delete image
     */
    public function delete() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        // Verify ownership
        if (!$this->image_model->user_owns_image($image_id, $user_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $deleted = $this->image_model->delete($image_id);
        
        if ($deleted) {
            wp_send_json_success(['message' => __('Image deleted successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete image', 'photovault')]);
        }
    }
    
    /**
     * Bulk delete images
     */
    public function bulk_delete() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : [];
        $user_id = get_current_user_id();
        
        if (empty($image_ids)) {
            wp_send_json_error(['message' => __('No images selected', 'photovault')]);
        }
        
        $deleted_count = 0;
        
        foreach ($image_ids as $image_id) {
            if ($this->image_model->user_owns_image($image_id, $user_id)) {
                if ($this->image_model->delete($image_id)) {
                    $deleted_count++;
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(__('%d images deleted successfully', 'photovault'), $deleted_count),
            'deleted_count' => $deleted_count
        ]);
    }
    
    /**
     * Add images to album
     */
    public function add_to_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        $image_ids = isset($_POST['image_ids']) ? array_map('intval', $_POST['image_ids']) : [];
        $album_id = intval($_POST['album_id'] ?? 0);
        
        if (empty($image_ids) || !$album_id) {
            wp_send_json_error(['message' => __('Invalid parameters', 'photovault')]);
        }
        
        $added_count = 0;
        
        foreach ($image_ids as $image_id) {
            if ($this->image_model->add_to_album($image_id, $album_id)) {
                $added_count++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(__('%d images added to album', 'photovault'), $added_count),
            'added_count' => $added_count
        ]);
    }
}