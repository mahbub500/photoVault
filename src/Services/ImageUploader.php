<?php
/**
 * Image Uploader Service
 *
 * @package PhotoVault
 */

namespace PhotoVault\Services;

class ImageUploader {
    
    private $allowed_types;
    private $max_file_size;
    
    public function __construct() {
        $this->allowed_types = get_option('photovault_allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->max_file_size = get_option('photovault_max_upload_size', 10485760); // 10MB default
    }
    
    /**
     * Upload image file
     *
     * @param array $file $_FILES array element
     * @return array|WP_Error Upload result or error
     */
    public function upload($file) {
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Set up file array for media_handle_sideload
        $file_array = [
            'name' => $file['name'],
            'type' => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'error' => $file['error'],
            'size' => $file['size']
        ];
        
        // Upload to WordPress media library
        $attachment_id = media_handle_sideload($file_array, 0, null, [
            'test_form' => false,
            'test_type' => false
        ]);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Get file info
        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        $file_type = wp_check_filetype($file_path);
        
        return [
            'attachment_id' => $attachment_id,
            'url' => $file_url,
            'path' => $file_path,
            'type' => $file_type['type'],
            'ext' => $file_type['ext']
        ];
    }
    
    /**
     * Upload from URL
     *
     * @param string $url Image URL
     * @return array|WP_Error
     */
    public function upload_from_url($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid URL provided', 'photovault'));
        }
        
        // Download file
        $tmp_file = download_url($url);
        
        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }
        
        // Get file extension from URL
        $parsed_url = wp_parse_url($url);
        $file_ext = pathinfo($parsed_url['path'], PATHINFO_EXTENSION);
        if (empty($file_ext)) {
            $file_ext = 'jpg'; // Default to jpg
        }
        
        // Create file array
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp_file,
            'type' => mime_content_type($tmp_file),
            'error' => 0,
            'size' => filesize($tmp_file)
        ];
        
        // Upload
        $result = $this->upload($file_array);
        
        // Clean up temp file
        if (file_exists($tmp_file)) {
            wp_delete_file($tmp_file);
        }
        
        return $result;
    }
    
    /**
     * Batch upload multiple images
     *
     * @param array $files Array of files
     * @return array Results
     */
    public function batch_upload($files) {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($files as $file) {
            $upload = $this->upload($file);
            
            if (is_wp_error($upload)) {
                $results['failed'][] = [
                    'filename' => $file['name'],
                    'error' => $upload->get_error_message()
                ];
            } else {
                $results['success'][] = $upload;
            }
        }
        
        return $results;
    }
    
    /**
     * Upload with chunking for large files
     *
     * @param array $file File data
     * @param int $chunk_index Current chunk index
     * @param int $total_chunks Total number of chunks
     * @return array|WP_Error
     */
    public function chunked_upload($file, $chunk_index, $total_chunks) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/photovault/temp/';
        
        // Ensure temp directory exists
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification should be done by the caller before calling this method.
        $unique_id = isset($_POST['unique_id']) ? sanitize_file_name(wp_unslash($_POST['unique_id'])) : uniqid();
        $original_filename = isset($_POST['original_filename']) ? sanitize_file_name(wp_unslash($_POST['original_filename'])) : $file['name'];
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $chunk_file = $temp_dir . $unique_id . '_chunk_' . $chunk_index;
        
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Move uploaded file using WP_Filesystem
        if (!$wp_filesystem->move($file['tmp_name'], $chunk_file, true)) {
            return new \WP_Error('chunk_upload_failed', __('Failed to save chunk', 'photovault'));
        }
        
        // If this is the last chunk, combine all chunks
        if ($chunk_index == $total_chunks - 1) {
            return $this->combine_chunks($unique_id, $total_chunks, $original_filename);
        }
        
        return [
            'chunk' => $chunk_index,
            'total' => $total_chunks,
            'status' => 'chunk_received'
        ];
    }
    
    /**
     * Combine file chunks
     *
     * @param string $unique_id Unique upload ID
     * @param int $total_chunks Total chunks
     * @param string $filename Original filename
     * @return array|WP_Error
     */
    private function combine_chunks($unique_id, $total_chunks, $filename) {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/photovault/temp/';
        $final_file = $temp_dir . sanitize_file_name($filename);
        
        // Initialize WP_Filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Combine chunks
        $combined_content = '';
        
        for ($i = 0; $i < $total_chunks; $i++) {
            $chunk_file = $temp_dir . $unique_id . '_chunk_' . $i;
            
            if (!$wp_filesystem->exists($chunk_file)) {
                return new \WP_Error('chunk_missing', __('Chunk file missing', 'photovault'));
            }
            
            $chunk_content = $wp_filesystem->get_contents($chunk_file);
            if ($chunk_content === false) {
                return new \WP_Error('chunk_read_failed', __('Failed to read chunk', 'photovault'));
            }
            
            $combined_content .= $chunk_content;
            
            // Delete chunk
            wp_delete_file($chunk_file);
        }
        
        // Write combined content to final file
        if (!$wp_filesystem->put_contents($final_file, $combined_content, FS_CHMOD_FILE)) {
            return new \WP_Error('file_creation_failed', __('Failed to create file', 'photovault'));
        }
        
        // Upload the combined file
        $file_array = [
            'name' => $filename,
            'tmp_name' => $final_file,
            'type' => mime_content_type($final_file),
            'error' => 0,
            'size' => $wp_filesystem->size($final_file)
        ];
        
        $result = $this->upload($file_array);
        
        // Clean up
        wp_delete_file($final_file);
        
        return $result;
    }
    
    /**
     * Validate uploaded file
     *
     * @param array $file File data
     * @return true|WP_Error
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }

        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s is the maximum allowed file size (e.g., "10 MB"). */
                    __('File size exceeds maximum allowed size of %s', 'photovault'),
                    size_format($this->max_file_size)
                )
            );
        }

        // Check if file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new \WP_Error('file_not_found', __('Temporary file not found', 'photovault'));
        }

        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if (!in_array(strtolower($file_type['ext']), $this->allowed_types, true)) {
            return new \WP_Error(
                'invalid_file_type',
                sprintf(
                    /* translators: %s is a comma-separated list of allowed file types. */
                    __('File type not allowed. Allowed types: %s', 'photovault'),
                    implode(', ', $this->allowed_types)
                )
            );
        }

        // Validate image
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return new \WP_Error('invalid_image', __('File is not a valid image', 'photovault'));
        }

        // Check image dimensions (optional limit)
        $max_width = 10000; // 10k pixels
        $max_height = 10000;
        if ($image_info[0] > $max_width || $image_info[1] > $max_height) {
            return new \WP_Error(
                'image_too_large',
                sprintf(
                    /* translators: %1$d and %2$d are maximum image width and height in pixels. */
                    __('Image dimensions exceed maximum of %1$d×%2$d pixels', 'photovault'),
                    $max_width,
                    $max_height
                )
            );
        }

        return true;
    }

    
    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string
     */
    private function get_upload_error_message($error_code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => __('File exceeds upload_max_filesize directive in php.ini', 'photovault'),
            UPLOAD_ERR_FORM_SIZE => __('File exceeds MAX_FILE_SIZE directive', 'photovault'),
            UPLOAD_ERR_PARTIAL => __('File was only partially uploaded', 'photovault'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded', 'photovault'),
            UPLOAD_ERR_NO_TMP_DIR => __('Missing temporary folder', 'photovault'),
            UPLOAD_ERR_CANT_WRITE => __('Failed to write file to disk', 'photovault'),
            UPLOAD_ERR_EXTENSION => __('File upload stopped by extension', 'photovault'),
        ];
        
        return $errors[$error_code] ?? __('Unknown upload error', 'photovault');
    }
    
    /**
     * Get max upload size in bytes
     *
     * @return int
     */
    public function get_max_upload_size() {
        return $this->max_file_size;
    }
    
    /**
     * Get allowed file types
     *
     * @return array
     */
    public function get_allowed_types() {
        return $this->allowed_types;
    }
    
    /**
     * Format file size
     *
     * @param int $bytes File size in bytes
     * @return string Formatted size
     */
    public function format_file_size($bytes) {
        return size_format($bytes);
    }
}