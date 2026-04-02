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
            
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
            
            // Handle chunked upload
            if (isset($_POST['chunk_index']) && isset($_POST['total_chunks'])) {
                $this->handle_chunked_upload();
                return;
            }
            
            // Standard upload - $_FILES is validated by WordPress upload handler
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload handled by WordPress upload handler
            $upload_result = $this->uploader->upload($_FILES['file']);
            
            if (is_wp_error($upload_result)) {
                wp_send_json_error(['message' => $upload_result->get_error_message()]);
            }
            
            // Process image (create thumbnails, extract EXIF, etc.)
            $processed = $this->processor->process($upload_result['attachment_id']);

            // Determine upload date based on setting
            $upload_date = current_time('mysql');
            
            // Check if we should use image creation date from EXIF
            $use_exif_date = get_option('photovault_use_image_creation_date', false);
            
            // Log for debugging (remove in production)
            error_log('PhotoVault: use_image_creation_date setting = ' . ($use_exif_date ? 'true' : 'false'));
            
            if ($use_exif_date && !empty($processed['exif']['date_taken'])) {
                $exif_date = $processed['exif']['date_taken'];
                error_log('PhotoVault: EXIF date_taken = ' . $exif_date);
                
                // EXIF date format is "YYYY:MM:DD HH:MM:SS"
                // Parse it properly
                $exif_parts = explode(' ', $exif_date);
                if (count($exif_parts) === 2) {
                    // Replace colons in date part only: "2023:01:15" -> "2023-01-15"
                    $date_part = str_replace(':', '-', $exif_parts[0]);
                    $time_part = $exif_parts[1];
                    $exif_date_formatted = $date_part . ' ' . $time_part;
                    
                    error_log('PhotoVault: Formatted date = ' . $exif_date_formatted);
                    
                    // Try to parse and format the date properly
                    $timestamp = @strtotime($exif_date_formatted);
                    if ($timestamp !== false) {
                        $upload_date = date('Y-m-d H:i:s', $timestamp);
                        error_log('PhotoVault: Using EXIF upload date = ' . $upload_date);
                    } else {
                        error_log('PhotoVault: Failed to parse EXIF date, using current time');
                    }
                } else {
                    error_log('PhotoVault: Invalid EXIF date format');
                }
            } else {
                if (!$use_exif_date) {
                    error_log('PhotoVault: Setting disabled, using current time');
                }
                if (empty($processed['exif']['date_taken'])) {
                    error_log('PhotoVault: No EXIF date_taken found');
                }
            }

            // Save to database
            $image_data = [
                'attachment_id' => $upload_result['attachment_id'],
                'user_id' => get_current_user_id(),
                'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
                'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
                'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash($_POST['visibility'])) : 'private',
                'upload_date' => $upload_date,
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
                $tags = [];
                if (is_array($_POST['tags'])) {
                    $tags = array_map('sanitize_text_field', wp_unslash($_POST['tags']));
                } else {
                    $tags_string = sanitize_text_field(wp_unslash($_POST['tags']));
                    $tags = array_map('trim', explode(',', $tags_string));
                }
                $this->image_model->add_tags($image_id, $tags);
            }
            
            // Handle album
            if (!empty($_POST['album_id'])) {
                $this->image_model->add_to_album($image_id, intval($_POST['album_id']));
            }
            
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
        // Nonce already verified in upload() method before calling this
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in parent upload() method
        
        $chunk_index = isset($_POST['chunk_index']) ? intval($_POST['chunk_index']) : 0;
        $total_chunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 0;
        $unique_id = isset($_POST['unique_id']) ? sanitize_text_field(wp_unslash($_POST['unique_id'])) : '';
        
        if (empty($unique_id)) {
            wp_send_json_error(['message' => __('Invalid upload ID', 'photovault')]);
        }
        
        // Validate $_FILES exists
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file chunk uploaded', 'photovault')]);
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File upload handled by WordPress upload handler
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

            // Determine upload date based on setting
            $upload_date = current_time('mysql');
            
            // Check if we should use image creation date from EXIF
            $use_exif_date = get_option('photovault_use_image_creation_date', false);
            
            if ($use_exif_date && !empty($processed['exif']['date_taken'])) {
                $exif_date = $processed['exif']['date_taken'];
                
                // EXIF date format is "YYYY:MM:DD HH:MM:SS"
                // Parse it properly
                $exif_parts = explode(' ', $exif_date);
                if (count($exif_parts) === 2) {
                    // Replace colons in date part only: "2023:01:15" -> "2023-01-15"
                    $date_part = str_replace(':', '-', $exif_parts[0]);
                    $time_part = $exif_parts[1];
                    $exif_date_formatted = $date_part . ' ' . $time_part;
                    
                    // Try to parse and format the date properly
                    $timestamp = @strtotime($exif_date_formatted);
                    if ($timestamp !== false) {
                        $upload_date = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }

            $image_data = [
                'attachment_id' => $result['attachment_id'],
                'user_id' => get_current_user_id(),
                'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
                'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
                'visibility' => isset($_POST['visibility']) ? sanitize_text_field(wp_unslash($_POST['visibility'])) : 'private',
                'upload_date' => $upload_date,
                'file_size' => $processed['file_size'],
                'width' => $processed['width'],
                'height' => $processed['height'],
                'mime_type' => $processed['mime_type'],
            ];
            
            $image_id = $this->image_model->create($image_data);
            
            if (!empty($_POST['tags'])) {
                $tags = [];
                if (is_array($_POST['tags'])) {
                    $tags = array_map('sanitize_text_field', wp_unslash($_POST['tags']));
                } else {
                    $tags_string = sanitize_text_field(wp_unslash($_POST['tags']));
                    $tags = array_map('trim', explode(',', $tags_string));
                }
                $this->image_model->add_tags($image_id, $tags);
            }
            
            if (!empty($_POST['album_id'])) {
                $this->image_model->add_to_album($image_id, intval($_POST['album_id']));
            }
            
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
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
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
        
        $result = $this->image_model->get_images($params);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get single image
     */
    public function get_image() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
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
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $user_id = get_current_user_id();
        
        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
        }
        
        // Verify ownership
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
     * Delete image
     */
    public function delete() {
        check_ajax_referer('photovault_nonce', 'nonce');

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        
        $user_id = get_current_user_id();

        if (!$image_id) {
            wp_send_json_error(['message' => __('Invalid image ID', 'photovault')]);
            return;
        }

        // Verify ownership
        if (!$this->image_model->user_owns_image($image_id, $user_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'photovault')]);
            return;
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

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
        $image_ids = [];
        if (isset($_POST['image_ids']) && is_array($_POST['image_ids'])) {
            $image_ids = array_map('intval', wp_unslash($_POST['image_ids']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $user_id = get_current_user_id();

        if (empty($image_ids)) {
            wp_send_json_error(['message' => __('No images selected', 'photovault')]);
            return;
        }

        $deleted_count = 0;
        $failed_count = 0;

        foreach ($image_ids as $image_id) {
            if ($this->image_model->user_owns_image($image_id, $user_id)) {
                if ($this->image_model->delete($image_id)) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            } else {
                $failed_count++;
            }
        }

        if ($deleted_count > 0) {
            wp_send_json_success([
                'message'       => sprintf(
                    /* translators: %1$d is the number of images deleted. */
                    __('%1$d image(s) deleted successfully', 'photovault'),
                    $deleted_count
                ),
                'deleted_count' => $deleted_count,
                'failed_count'  => $failed_count,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete images. Check permissions.', 'photovault'),
                'failed_count' => $failed_count,
            ]);
        }
    }
    
    /**
     * Add images to album
     */
    public function add_to_album() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_ajax_referer()
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
            'message'      => sprintf(
                // translators: %1$d is the number of images added to the album.
                __('%1$d images added to album', 'photovault'),
                $added_count
            ),
            'added_count'  => $added_count,
        ]);
    }

    /**
     * Get statistics (total images, total albums)
     */
    public function get_stats() {
        check_ajax_referer('photovault_nonce', 'nonce');

        global $wpdb;
        $user_id = get_current_user_id();

        // Get total images for current user
        $total_images = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pv_images WHERE user_id = %d",
            $user_id
        ));

        // Get total albums for current user
        $total_albums = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pv_albums WHERE user_id = %d",
            $user_id
        ));

        // Admin can see all counts
        if (current_user_can('manage_options')) {
            $total_images = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pv_images");
            $total_albums = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pv_albums");
        }

        wp_send_json_success([
            'total_images' => (int) $total_images,
            'total_albums' => (int) $total_albums,
        ]);
    }
}