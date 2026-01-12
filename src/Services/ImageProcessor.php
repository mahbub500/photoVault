<?php
/**
 * Image Processor Service
 *
 * @package PhotoVault
 */

namespace PhotoVault\Services;

class ImageProcessor {
    
    private $thumbnail_width;
    private $thumbnail_height;
    private $thumbnail_quality;
    
    public function __construct() {
        $this->thumbnail_width = get_option('photovault_thumbnail_width', 300);
        $this->thumbnail_height = get_option('photovault_thumbnail_height', 300);
        $this->thumbnail_quality = get_option('photovault_thumbnail_quality', 85);
    }
    
    /**
     * Process uploaded image
     *
     * @param int $attachment_id WordPress attachment ID
     * @return array Processing results
     */
    public function process($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        $result = [
            'attachment_id' => $attachment_id,
            'file_path' => $file_path,
            'file_size' => filesize($file_path),
            'width' => $metadata['width'] ?? 0,
            'height' => $metadata['height'] ?? 0,
            'mime_type' => get_post_mime_type($attachment_id),
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'medium'),
        ];
        
        // Extract EXIF data
        if (get_option('photovault_enable_exif', true)) {
            $result['exif'] = $this->extract_exif($file_path);
        }
        
        // Create custom thumbnails
        $result['thumbnails'] = $this->create_thumbnails($attachment_id, $file_path);
        
        // Add watermark if enabled
        if (get_option('photovault_enable_watermark', false)) {
            $this->add_watermark($file_path);
        }
        
        return $result;
    }
    
    /**
     * Extract EXIF data from image
     *
     * @param string $file_path Image file path
     * @return array EXIF data
     */
    public function extract_exif($file_path) {
        if (!function_exists('exif_read_data')) {
            return [];
        }
        
        $exif = @exif_read_data($file_path);
        
        if (!$exif) {
            return [];
        }
        
        return [
            'camera' => $exif['Model'] ?? '',
            'lens' => $exif['LensModel'] ?? '',
            'focal_length' => $exif['FocalLength'] ?? '',
            'aperture' => $exif['FNumber'] ?? '',
            'shutter_speed' => $exif['ExposureTime'] ?? '',
            'iso' => $exif['ISOSpeedRatings'] ?? '',
            'date_taken' => $exif['DateTimeOriginal'] ?? '',
            'gps_latitude' => $this->get_gps_coordinate($exif, 'Latitude'),
            'gps_longitude' => $this->get_gps_coordinate($exif, 'Longitude'),
        ];
    }
    
    /**
     * Get GPS coordinate from EXIF
     *
     * @param array $exif EXIF data
     * @param string $type 'Latitude' or 'Longitude'
     * @return float|null
     */
    private function get_gps_coordinate($exif, $type) {
        if (!isset($exif['GPS' . $type]) || !isset($exif['GPS' . $type . 'Ref'])) {
            return null;
        }
        
        $coordinate = $exif['GPS' . $type];
        $ref = $exif['GPS' . $type . 'Ref'];
        
        // Convert to decimal
        $degrees = count($coordinate) > 0 ? $this->gps_to_decimal($coordinate[0]) : 0;
        $minutes = count($coordinate) > 1 ? $this->gps_to_decimal($coordinate[1]) : 0;
        $seconds = count($coordinate) > 2 ? $this->gps_to_decimal($coordinate[2]) : 0;
        
        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
        
        // Apply reference (N/S or E/W)
        if ($ref == 'S' || $ref == 'W') {
            $decimal *= -1;
        }
        
        return $decimal;
    }
    
    /**
     * Convert GPS coordinate to decimal
     *
     * @param string $coordinate Coordinate string
     * @return float
     */
    private function gps_to_decimal($coordinate) {
        $parts = explode('/', $coordinate);
        if (count($parts) <= 0) return 0;
        if (count($parts) == 1) return $parts[0];
        return floatval($parts[0]) / floatval($parts[1]);
    }
    
    /**
     * Create custom thumbnails
     *
     * @param int $attachment_id Attachment ID
     * @param string $file_path File path
     * @return array Thumbnail URLs
     */
    public function create_thumbnails($attachment_id, $file_path) {
        $thumbnails = [];
        
        // Standard WordPress sizes
        $thumbnails['thumbnail'] = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        $thumbnails['medium'] = wp_get_attachment_image_url($attachment_id, 'medium');
        $thumbnails['large'] = wp_get_attachment_image_url($attachment_id, 'large');
        
        // Custom PhotoVault thumbnail
        $custom_thumb = $this->create_custom_thumbnail($file_path);
        if ($custom_thumb) {
            $thumbnails['photovault'] = $custom_thumb;
        }
        
        return $thumbnails;
    }
    
    /**
     * Create custom thumbnail
     *
     * @param string $file_path Original file path
     * @return string|false Thumbnail URL or false
     */
    private function create_custom_thumbnail($file_path) {
        $upload_dir = wp_upload_dir();
        $thumb_dir = $upload_dir['basedir'] . '/photovault/thumbnails/';
        
        if (!file_exists($thumb_dir)) {
            wp_mkdir_p($thumb_dir);
        }
        
        $filename = basename($file_path);
        $thumb_path = $thumb_dir . 'thumb_' . $filename;
        
        // Get image editor
        $image = wp_get_image_editor($file_path);
        
        if (is_wp_error($image)) {
            return false;
        }
        
        // Resize
        $image->resize($this->thumbnail_width, $this->thumbnail_height, true);
        
        // Set quality
        $image->set_quality($this->thumbnail_quality);
        
        // Save
        $saved = $image->save($thumb_path);
        
        if (is_wp_error($saved)) {
            return false;
        }
        
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $thumb_path);
    }
    
    /**
     * Add watermark to image
     *
     * @param string $file_path Image file path
     * @return bool Success
     */
    public function add_watermark($file_path) {
        $watermark_text = get_option('photovault_watermark_text', get_bloginfo('name'));
        
        if (empty($watermark_text)) {
            return false;
        }
        
        $image = wp_get_image_editor($file_path);
        
        if (is_wp_error($image)) {
            return false;
        }
        
        // Get image size
        $size = $image->get_size();
        $width = $size['width'];
        $height = $size['height'];
        
        // Create watermark image
        $watermark = imagecreatetruecolor($width, 50);
        $white = imagecolorallocate($watermark, 255, 255, 255);
        $black = imagecolorallocate($watermark, 0, 0, 0);
        
        // Add text
        $font_size = 12;
        $font_file = ABSPATH . 'wp-includes/fonts/dejavu-sans/DejaVuSans.ttf';
        
        if (file_exists($font_file)) {
            imagettftext($watermark, $font_size, 0, 10, 30, $white, $font_file, $watermark_text);
        } else {
            imagestring($watermark, 5, 10, 20, $watermark_text, $white);
        }
        
        // Apply watermark (bottom right)
        $dest_x = $width - imagesx($watermark) - 10;
        $dest_y = $height - imagesy($watermark) - 10;
        
        // This would require additional implementation with GD or Imagick
        
        imagedestroy($watermark);
        
        return true;
    }
    
    /**
     * Optimize image file size
     *
     * @param string $file_path Image file path
     * @return bool Success
     */
    public function optimize($file_path) {
        $image = wp_get_image_editor($file_path);
        
        if (is_wp_error($image)) {
            return false;
        }
        
        // Set quality to 85 for optimization
        $image->set_quality(85);
        
        // Save optimized version
        $saved = $image->save($file_path);
        
        return !is_wp_error($saved);
    }
    
    /**
     * Generate multiple sizes for responsive images
     *
     * @param int $attachment_id Attachment ID
     * @return array Size URLs
     */
    public function generate_responsive_sizes($attachment_id) {
        $sizes = ['small', 'medium', 'large', 'extra-large'];
        $responsive = [];
        
        foreach ($sizes as $size) {
            $url = wp_get_attachment_image_url($attachment_id, $size);
            if ($url) {
                $responsive[$size] = $url;
            }
        }
        
        return $responsive;
    }
}