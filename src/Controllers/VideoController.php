<?php
/**
 * Video Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

use PhotoVault\Models\Video as VideoModel;

class VideoController {
    
    private $video_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->video_model = new VideoModel();
    }
    
    /**
     * Upload video (AJAX handler)
     */
    public function upload() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('photovault_upload_videos')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }
        
        if (empty($_FILES['video'])) {
            wp_send_json_error(['message' => 'No video file uploaded']);
            return;
        }
        
        $file = $_FILES['video'];
        
        // Validate file
        $validation = $this->validate_video_file($file);
        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['message']]);
            return;
        }
        
        // Upload to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_handle_upload('video', 0);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            return;
        }
        
        // Get video metadata
        $file_path = get_attached_file($attachment_id);
        $metadata = $this->get_video_metadata($file_path);
        
        // Create video record
        $video_data = [
            'attachment_id' => $attachment_id,
            'user_id' => get_current_user_id(),
            'title' => sanitize_text_field($_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME)),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'visibility' => sanitize_text_field($_POST['visibility'] ?? get_option('photovault_default_visibility', 'private')),
            'file_size' => filesize($file_path),
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0,
            'duration' => $metadata['duration'] ?? 0,
            'mime_type' => $file['type'],
        ];
        
        $video_id = $this->video_model->create($video_data);
        
        if (!$video_id) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(['message' => 'Failed to create video record']);
            return;
        }
        
        // Generate thumbnail
        $thumbnail_id = $this->generate_video_thumbnail($file_path, $video_id);
        if ($thumbnail_id) {
            $this->video_model->update($video_id, ['thumbnail_attachment_id' => $thumbnail_id]);
        }
        
        // Add tags if provided
        if (!empty($_POST['tags'])) {
            $tags = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', $_POST['tags']);
            $this->video_model->add_tags($video_id, $tags);
        }
        
        // Add to album if provided
        if (!empty($_POST['album_id'])) {
            $this->video_model->add_to_album($video_id, intval($_POST['album_id']));
        }
        
        $video = $this->video_model->get($video_id);
        
        wp_send_json_success([
            'message' => 'Video uploaded successfully',
            'video' => $video
        ]);
    }
    
    /**
     * Get videos (AJAX handler)
     */
    public function get_videos() {
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
        
        $result = $this->video_model->get_videos($params);
        
        wp_send_json_success($result);
    }
    
    /**
     * Delete video (AJAX handler)
     */
    public function delete() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('photovault_delete_videos')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }
        
        $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
        
        if (!$video_id) {
            wp_send_json_error(['message' => 'Invalid video ID']);
            return;
        }
        
        // Check ownership
        if (!$this->video_model->user_owns_video($video_id, get_current_user_id())) {
            wp_send_json_error(['message' => 'You do not have permission to delete this video']);
            return;
        }
        
        $success = $this->video_model->delete($video_id);
        
        if ($success) {
            wp_send_json_success(['message' => 'Video deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete video']);
        }
    }
    
    /**
     * Update video (AJAX handler)
     */
    public function update() {
        check_ajax_referer('photovault_nonce', 'nonce');
        
        if (!current_user_can('photovault_edit_videos')) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }
        
        $video_id = isset($_POST['video_id']) ? intval($_POST['video_id']) : 0;
        
        if (!$video_id) {
            wp_send_json_error(['message' => 'Invalid video ID']);
            return;
        }
        
        // Check ownership
        if (!$this->video_model->user_owns_video($video_id, get_current_user_id())) {
            wp_send_json_error(['message' => 'You do not have permission to edit this video']);
            return;
        }
        
        $update_data = [];
        
        if (isset($_POST['title'])) {
            $update_data['title'] = sanitize_text_field($_POST['title']);
        }
        
        if (isset($_POST['description'])) {
            $update_data['description'] = sanitize_textarea_field($_POST['description']);
        }
        
        if (isset($_POST['visibility'])) {
            $update_data['visibility'] = sanitize_text_field($_POST['visibility']);
        }
        
        $success = $this->video_model->update($video_id, $update_data);
        
        if ($success) {
            $video = $this->video_model->get($video_id);
            wp_send_json_success([
                'message' => 'Video updated successfully',
                'video' => $video
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to update video']);
        }
    }
    
    /**
     * Validate video file
     *
     * @param array $file Uploaded file data
     * @return array Validation result
     */
    private function validate_video_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => 'File upload error: ' . $file['error']
            ];
        }
        
        // Check file size
        $max_size = get_option('photovault_max_video_upload_size', 104857600);
        if ($file['size'] > $max_size) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed size'
            ];
        }
        
        // Check file type
        $allowed_types = get_option('photovault_allowed_video_types', ['mp4', 'mov', 'avi', 'wmv', 'webm']);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            return [
                'valid' => false,
                'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Get video metadata
     *
     * @param string $file_path Video file path
     * @return array Metadata
     */
    private function get_video_metadata($file_path) {
        $metadata = [];
        
        // Try to get metadata using getID3 if available
        if (class_exists('getID3')) {
            $getID3 = new \getID3();
            $file_info = $getID3->analyze($file_path);
            
            if (isset($file_info['video'])) {
                $metadata['width'] = $file_info['video']['resolution_x'] ?? 0;
                $metadata['height'] = $file_info['video']['resolution_y'] ?? 0;
            }
            
            if (isset($file_info['playtime_seconds'])) {
                $metadata['duration'] = intval($file_info['playtime_seconds']);
            }
        }
        
        // Fallback: try using wp_read_video_metadata
        if (empty($metadata)) {
            $wp_metadata = wp_read_video_metadata($file_path);
            if ($wp_metadata) {
                $metadata['width'] = $wp_metadata['width'] ?? 0;
                $metadata['height'] = $wp_metadata['height'] ?? 0;
                $metadata['duration'] = $wp_metadata['length'] ?? 0;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Generate video thumbnail
     *
     * @param string $video_path Video file path
     * @param int $video_id Video ID
     * @return int|false Attachment ID or false
     */
    private function generate_video_thumbnail($video_path, $video_id) {
        // Only generate if enabled
        if (!get_option('photovault_video_auto_generate_thumbnail', true)) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $thumbnail_dir = $upload_dir['basedir'] . '/photovault/video-thumbnails';
        
        if (!file_exists($thumbnail_dir)) {
            wp_mkdir_p($thumbnail_dir);
        }
        
        $thumbnail_path = $thumbnail_dir . '/' . $video_id . '.jpg';
        
        // Try to generate thumbnail using FFmpeg if available
        if (function_exists('exec')) {
            $ffmpeg_path = $this->get_ffmpeg_path();
            if ($ffmpeg_path) {
                $command = sprintf(
                    '%s -i %s -ss 00:00:01 -vframes 1 -q:v 2 %s 2>&1',
                    escapeshellarg($ffmpeg_path),
                    escapeshellarg($video_path),
                    escapeshellarg($thumbnail_path)
                );
                
                exec($command, $output, $return_var);
                
                if ($return_var === 0 && file_exists($thumbnail_path)) {
                    // Upload thumbnail to media library
                    $attachment_id = $this->upload_thumbnail_to_media($thumbnail_path, $video_id);
                    return $attachment_id;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get FFmpeg path
     *
     * @return string|false FFmpeg path or false
     */
    private function get_ffmpeg_path() {
        $possible_paths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg' // System PATH
        ];
        
        foreach ($possible_paths as $path) {
            if (function_exists('exec')) {
                exec(escapeshellarg($path) . ' -version', $output, $return_var);
                if ($return_var === 0) {
                    return $path;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Upload thumbnail to media library
     *
     * @param string $thumbnail_path Thumbnail file path
     * @param int $video_id Video ID
     * @return int|false Attachment ID or false
     */
    private function upload_thumbnail_to_media($thumbnail_path, $video_id) {
        $filename = basename($thumbnail_path);
        
        $wp_filetype = wp_check_filetype($filename, null);
        
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => 'Video Thumbnail - ' . $video_id,
            'post_content' => '',
            'post_status' => 'inherit'
        ];
        
        $attach_id = wp_insert_attachment($attachment, $thumbnail_path);
        
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $thumbnail_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }
        
        return false;
    }
}