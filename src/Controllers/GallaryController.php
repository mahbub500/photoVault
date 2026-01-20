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

class GallaryController {
    
    private $image_model;
    private $uploader;
    private $processor;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->image_model = new Image();
        $this->uploader = new ImageUploader();
        $this->processor = new ImageProcessor();
    }   
    
    
    // ==========================================
    // UPLOAD OPERATIONS
    // ==========================================
    
    /**
     * Upload image via AJAX
     */
    public function upload() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        try {
            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => __('No file uploaded', 'photovault')]);
            }
            
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
            
            // Handle chunked upload
            if (isset($_POST['chunk_index']) && isset($_POST['total_chunks'])) {
                $this->handle_chunked_upload();
                return;
            }
            
            // Standard upload
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $upload_result = $this->uploader->upload($_FILES['file']);
            
            if (is_wp_error($upload_result)) {
                wp_send_json_error(['message' => $upload_result->get_error_message()]);
            }
            
            // Process and save image
            $processed = $this->processor->process($upload_result['attachment_id']);
            $image_id = $this->save_image_data($upload_result, $processed);
            
            if (!$image_id) {
                wp_send_json_error(['message' => __('Failed to save image', 'photovault')]);
            }
            
            // Handle tags and albums
            $this->handle_tags($image_id);
            $this->handle_album($image_id);
            
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            
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
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in parent
        
        $chunk_index = isset($_POST['chunk_index']) ? intval($_POST['chunk_index']) : 0;
        $total_chunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 0;
        $unique_id = isset($_POST['unique_id']) ? sanitize_text_field(wp_unslash($_POST['unique_id'])) : '';
        
        if (empty($unique_id)) {
            wp_send_json_error(['message' => __('Invalid upload ID', 'photovault')]);
        }
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file chunk uploaded', 'photovault')]);
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $result = $this->uploader->chunked_upload($_FILES['file'], $chunk_index, $total_chunks);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        // Last chunk - process complete file
        if ($chunk_index == $total_chunks - 1 && isset($result['attachment_id'])) {
            $processed = $this->processor->process($result['attachment_id']);
            $image_id = $this->save_image_data($result, $processed);
            
            $this->handle_tags($image_id);
            $this->handle_album($image_id);
            
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            
            wp_send_json_success([
                'image_id' => $image_id,
                'attachment_id' => $result['attachment_id'],
                'url' => $result['url'],
                'thumbnail' => $processed['thumbnail'],
                'complete' => true
            ]);
        } else {
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            
            wp_send_json_success([
                'chunk' => $chunk_index,
                'total' => $total_chunks,
                'status' => 'chunk_received'
            ]);
        }
    }
    
    /**
     * Save image data to database
     */
    private function save_image_data($upload_result, $processed) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in parent
        $image_data = [
            'attachment_id' => $upload_result['attachment_id'],
            'user_id' => get_current_user_id(),
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash($_POST['visibility'])) : 'private',
            'file_size' => $processed['file_size'],
            'width' => $processed['width'],
            'height' => $processed['height'],
            'mime_type' => $processed['mime_type'],
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        return $this->image_model->create($image_data);
    }
    
    /**
     * Handle tags assignment
     */
    private function handle_tags($image_id) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in parent
        if (empty($_POST['tags'])) {
            return;
        }
        
        $tags = [];
        if (is_array($_POST['tags'])) {
            $tags = array_map('sanitize_text_field', wp_unslash($_POST['tags']));
        } else {
            $tags_string = sanitize_text_field(wp_unslash($_POST['tags']));
            $tags = array_map('trim', explode(',', $tags_string));
        }
        
        $this->image_model->add_tags($image_id, $tags);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
    
    /**
     * Handle album assignment
     */
    private function handle_album($image_id) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in parent
        if (!empty($_POST['album_id'])) {
            $this->image_model->add_to_album($image_id, intval($_POST['album_id']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
    
    // ==========================================
    // READ OPERATIONS
    // ==========================================
    
    /**
     * Get images with filters
     */
    public function get_images() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $params = [
            'user_id' => get_current_user_id(),
            'album_id' => isset($_POST['album_id']) ? intval($_POST['album_id']) : 0,
            'tag_id' => isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0,
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '',
            'page' => isset($_POST['page']) ? intval($_POST['page']) : 1,
            'per_page' => isset($_POST['per_page']) ? intval($_POST['per_page']) : 20,
            'sort' => isset($_POST['sort']) ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'date_desc',
            'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash($_POST['visibility'])) : '',
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        $result = $this->build_images_query($params);
        wp_send_json_success($result);
    }
    
    /**
     * Get single image
     */
    public function get_image() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        $image = $this->image_model->get($image_id);
        
        if (!$image) {
            wp_send_json_error(['message' => __('Image not found', 'photovault')]);
        }
        
        // Check access permissions
        if (!$this->user_has_image_access($image)) {
            wp_send_json_error(['message' => __('Access denied', 'photovault')]);
        }
        
        wp_send_json_success($image);
    }
    
    /**
     * Get statistics
     */
    public function get_stats() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_images = $wpdb->prefix . 'pv_images';
        $table_albums = $wpdb->prefix . 'pv_albums';
        
        $total_images = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_images} WHERE user_id = %d",
            $user_id
        ));
        
        $total_albums = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_albums} WHERE user_id = %d",
            $user_id
        ));
        
        $storage_used = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(file_size) FROM {$table_images} WHERE user_id = %d",
            $user_id
        ));
        
        wp_send_json_success([
            'total_images' => (int) $total_images,
            'total_albums' => (int) $total_albums,
            'storage_used' => round(($storage_used / 1024 / 1024), 2),
            'recent_uploads' => $this->get_recent_uploads_count($user_id)
        ]);
    }
    
    /**
     * Get tags
     */
    public function get_tags() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        global $wpdb;
        $table_tags = $wpdb->prefix . 'pv_tags';
        
        $tags = $wpdb->get_results(
            "SELECT id, name, slug, color, count 
             FROM {$table_tags} 
             WHERE count > 0 
             ORDER BY count DESC, name ASC 
             LIMIT 50"
        );
        
        wp_send_json_success($tags);
    }
    
    // ==========================================
    // UPDATE OPERATIONS
    // ==========================================
    
    /**
     * Update image
     */
    public function update() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        if (!$this->image_model->user_owns_image($image_id, $user_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $data = [
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash($_POST['visibility'])) : 'private',
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        $updated = $this->image_model->update($image_id, $data);
        
        if ($updated) {
            wp_send_json_success(['message' => __('Image updated successfully', 'photovault')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update image', 'photovault')]);
        }
    }
    
    /**
     * Add image tag
     */
    public function add_image_tag() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $tag = isset($_POST['tag']) ? sanitize_text_field(wp_unslash($_POST['tag'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if (!$image_id || !$tag) {
            wp_send_json_error(['message' => __('Invalid parameters', 'photovault')]);
        }
        
        if (!$this->image_model->user_owns_image($image_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
        }
        
        $this->add_tag_to_image($image_id, $tag);
        wp_send_json_success(['message' => __('Tag added', 'photovault')]);
    }
    
    /**
     * Add images to album
     */
    public function add_to_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_ids = [];
        if (isset($_POST['image_ids']) && is_array($_POST['image_ids'])) {
            $image_ids = array_map('intval', wp_unslash($_POST['image_ids']));
        }
        
        $album_id = isset($_POST['album_id']) ? intval($_POST['album_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
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
            'message' => sprintf(
                // translators: %1$d is the number of images added
                __('%1$d images added to album', 'photovault'),
                $added_count
            ),
            'added_count' => $added_count,
        ]);
    }
    
    // ==========================================
    // DELETE OPERATIONS
    // ==========================================
    
    /**
     * Delete image
     */
    public function delete() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        if (!$this->image_model->user_owns_image($image_id, get_current_user_id())) {
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
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above
        $image_ids = [];
        if (isset($_POST['image_ids']) && is_array($_POST['image_ids'])) {
            $image_ids = array_map('intval', wp_unslash($_POST['image_ids']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        if (empty($image_ids)) {
            wp_send_json_error(['message' => __('No images selected', 'photovault')]);
        }
        
        $deleted_count = 0;
        $user_id = get_current_user_id();
        
        foreach ($image_ids as $image_id) {
            if ($this->image_model->user_owns_image($image_id, $user_id)) {
                if ($this->image_model->delete($image_id)) {
                    $deleted_count++;
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                // translators: %1$d is the number of images deleted
                __('%1$d images deleted successfully', 'photovault'),
                $deleted_count
            ),
            'deleted_count' => $deleted_count,
        ]);
    }
    
    // ==========================================
    // HELPER METHODS
    // ==========================================
    
    /**
     * Check if user has access to image
     */
    private function user_has_image_access($image) {
        $user_id = get_current_user_id();
        
        if ($image->user_id == $user_id || current_user_can('manage_options')) {
            return true;
        }
        
        // Additional logic for shared images can be added here
        return false;
    }
    
    /**
     * Get recent uploads count (last 7 days)
     */
    private function get_recent_uploads_count($user_id) {
        global $wpdb;
        $table_images = $wpdb->prefix . 'pv_images';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_images} 
             WHERE user_id = %d 
             AND upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));
    }
    
    /**
     * Build images query with pagination
     */
    private function build_images_query($params = []) {
        global $wpdb;
        $table_images = $wpdb->prefix . 'pv_images';
        
        $defaults = [
            'user_id' => get_current_user_id(),
            'album_id' => 0,
            'tag_id' => 0,
            'search' => '',
            'visibility' => '',
            'page' => 1,
            'per_page' => 20,
            'sort' => 'date_desc'
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        // Build WHERE clause
        $where = ['1=1'];
        $values = [];
        
        if ($params['user_id']) {
            $where[] = 'i.user_id = %d';
            $values[] = $params['user_id'];
        }
        
        if (!empty($params['search'])) {
            $where[] = '(i.title LIKE %s OR i.description LIKE %s)';
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }
        
        if (!empty($params['visibility'])) {
            $where[] = 'i.visibility = %s';
            $values[] = $params['visibility'];
        }
        
        if ($params['album_id']) {
            $table_image_album = $wpdb->prefix . 'pv_image_album';
            $where[] = "i.id IN (SELECT image_id FROM {$table_image_album} WHERE album_id = %d)";
            $values[] = $params['album_id'];
        }
        
        if ($params['tag_id']) {
            $table_image_tag = $wpdb->prefix . 'pv_image_tag';
            $where[] = "i.id IN (SELECT image_id FROM {$table_image_tag} WHERE tag_id = %d)";
            $values[] = $params['tag_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Sort order
        $order_by = match($params['sort']) {
            'date_asc' => 'i.upload_date ASC',
            'title_asc' => 'i.title ASC',
            'title_desc' => 'i.title DESC',
            default => 'i.upload_date DESC'
        };
        
        // Pagination
        $offset = ($params['page'] - 1) * $params['per_page'];
        $limit = $params['per_page'] + 1;
        
        // Build and execute query
        $query = "SELECT i.*, 
                  DATE_FORMAT(i.upload_date, '%%M %%d, %%Y') as formatted_date
                  FROM {$table_images} i
                  WHERE {$where_clause}
                  ORDER BY {$order_by}
                  LIMIT %d OFFSET %d";
        
        $values[] = $limit;
        $values[] = $offset;
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Check for more results
        $has_more = count($results) > $params['per_page'];
        if ($has_more) {
            array_pop($results);
        }
        
        // Enhance results with URLs and tags
        foreach ($results as $image) {
            $image->thumbnail = wp_get_attachment_image_url($image->attachment_id, 'thumbnail');
            $image->url = wp_get_attachment_image_url($image->attachment_id, 'large');
            $image->full_url = wp_get_attachment_url($image->attachment_id);
            $image->tags = $this->get_image_tags($image->id);
        }
        
        return [
            'images' => $results,
            'has_more' => $has_more,
            'page' => $params['page'],
            'per_page' => $params['per_page']
        ];
    }
    
    /**
     * Get image tags
     */
    private function get_image_tags($image_id) {
        global $wpdb;
        $table_tags = $wpdb->prefix . 'pv_tags';
        $table_image_tag = $wpdb->prefix . 'pv_image_tag';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$table_tags} t
             INNER JOIN {$table_image_tag} it ON t.id = it.tag_id
             WHERE it.image_id = %d
             ORDER BY t.name ASC",
            $image_id
        ));
    }
    
    /**
     * Add tag to image
     */
    private function add_tag_to_image($image_id, $tag) {
        global $wpdb;
        $table_tags = $wpdb->prefix . 'pv_tags';
        $table_image_tag = $wpdb->prefix . 'pv_image_tag';
        
        // Get or create tag
        $tag_slug = sanitize_title($tag);
        $existing_tag = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_tags} WHERE slug = %s",
            $tag_slug
        ));
        
        if ($existing_tag) {
            $tag_id = $existing_tag->id;
        } else {
            $wpdb->insert($table_tags, [
                'name' => $tag,
                'slug' => $tag_slug,
                'count' => 0
            ]);
            $tag_id = $wpdb->insert_id;
        }
        
        // Add tag to image
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_image_tag} 
             WHERE image_id = %d AND tag_id = %d",
            $image_id, $tag_id
        ));
        
        if (!$exists) {
            $wpdb->insert($table_image_tag, [
                'image_id' => $image_id,
                'tag_id' => $tag_id,
                'added_date' => current_time('mysql')
            ]);
            
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_tags} SET count = count + 1 WHERE id = %d",
                $tag_id
            ));
        }
    }
}