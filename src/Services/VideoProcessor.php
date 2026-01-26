<?php
/**
 * Video Processor - Compression & Thumbnail Generation
 *
 * @package PhotoVault
 */

namespace PhotoVault\Services;

class VideoProcessor {
    
    /**
     * FFmpeg path
     */
    private $ffmpeg_path;
    
    /**
     * FFprobe path
     */
    private $ffprobe_path;
    
    /**
     * Upload directory
     */
    private $upload_dir;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ffmpeg_path = $this->find_ffmpeg();
        $this->ffprobe_path = $this->find_ffprobe();
        
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/photovault';
    }
    
    /**
     * Check if FFmpeg is available
     */
    public function is_ffmpeg_available() {
        return !empty($this->ffmpeg_path);
    }
    
    /**
     * Compress video
     *
     * @param string $input_path Input video path
     * @param array $options Compression options
     * @return array Result with success status and output path
     */
    public function compress_video($input_path, $options = []) {
        if (!$this->is_ffmpeg_available()) {
            return [
                'success' => false,
                'message' => 'FFmpeg is not available on this server'
            ];
        }
        
        if (!file_exists($input_path)) {
            return [
                'success' => false,
                'message' => 'Input video file not found'
            ];
        }
        
        // Default compression options
        $defaults = [
            'quality' => 'medium', // low, medium, high
            'max_width' => 1920,
            'max_height' => 1080,
            'video_bitrate' => '2M',
            'audio_bitrate' => '128k',
            'format' => 'mp4',
            'codec' => 'libx264',
            'preset' => 'medium', // ultrafast, fast, medium, slow, veryslow
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Set quality presets
        switch ($options['quality']) {
            case 'low':
                $options['video_bitrate'] = '1M';
                $options['preset'] = 'fast';
                break;
            case 'high':
                $options['video_bitrate'] = '4M';
                $options['preset'] = 'slow';
                break;
            default: // medium
                $options['video_bitrate'] = '2M';
                $options['preset'] = 'medium';
        }
        
        // Create output path
        $path_info = pathinfo($input_path);
        $output_filename = $path_info['filename'] . '_compressed.' . $options['format'];
        $output_path = $this->upload_dir . '/videos/' . $output_filename;
        
        // Ensure output directory exists
        wp_mkdir_p($this->upload_dir . '/videos');
        
        // Build FFmpeg command
        $command = sprintf(
            '%s -i %s -c:v %s -preset %s -b:v %s -vf "scale=\'min(%d,iw)\':\'min(%d,ih)\':force_original_aspect_ratio=decrease" -c:a aac -b:a %s -movflags +faststart %s 2>&1',
            escapeshellarg($this->ffmpeg_path),
            escapeshellarg($input_path),
            $options['codec'],
            $options['preset'],
            $options['video_bitrate'],
            $options['max_width'],
            $options['max_height'],
            $options['audio_bitrate'],
            escapeshellarg($output_path)
        );
        
        // Execute compression
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($output_path)) {
            return [
                'success' => true,
                'output_path' => $output_path,
                'output_url' => $this->get_file_url($output_path),
                'original_size' => filesize($input_path),
                'compressed_size' => filesize($output_path),
                'compression_ratio' => round((1 - (filesize($output_path) / filesize($input_path))) * 100, 2) . '%'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Video compression failed',
            'error' => implode("\n", $output)
        ];
    }
    
    /**
     * Generate thumbnails from video
     *
     * @param string $video_path Video file path
     * @param array $options Thumbnail options
     * @return array Array of generated thumbnail paths
     */
    public function generate_thumbnails($video_path, $options = []) {
        if (!$this->is_ffmpeg_available()) {
            return [
                'success' => false,
                'message' => 'FFmpeg is not available'
            ];
        }
        
        if (!file_exists($video_path)) {
            return [
                'success' => false,
                'message' => 'Video file not found'
            ];
        }
        
        // Default options
        $defaults = [
            'count' => 5, // Number of thumbnails to generate
            'width' => 640,
            'height' => 360,
            'quality' => 2, // 1-31 (lower is better)
            'format' => 'jpg',
            'timestamps' => [], // Specific timestamps (e.g., ['00:00:01', '00:00:05'])
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Get video duration
        $duration = $this->get_video_duration($video_path);
        if (!$duration) {
            return [
                'success' => false,
                'message' => 'Could not determine video duration'
            ];
        }
        
        // Create thumbnails directory
        $thumb_dir = $this->upload_dir . '/video-thumbnails';
        wp_mkdir_p($thumb_dir);
        
        $thumbnails = [];
        
        // Use specific timestamps if provided, otherwise calculate evenly spaced
        if (!empty($options['timestamps'])) {
            $timestamps = $options['timestamps'];
        } else {
            $timestamps = $this->calculate_thumbnail_timestamps($duration, $options['count']);
        }
        
        $path_info = pathinfo($video_path);
        $base_filename = $path_info['filename'];
        
        foreach ($timestamps as $index => $timestamp) {
            $thumb_filename = $base_filename . '_thumb_' . ($index + 1) . '.' . $options['format'];
            $thumb_path = $thumb_dir . '/' . $thumb_filename;
            
            // Generate thumbnail
            $command = sprintf(
                '%s -i %s -ss %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease" -q:v %d %s 2>&1',
                escapeshellarg($this->ffmpeg_path),
                escapeshellarg($video_path),
                $timestamp,
                $options['width'],
                $options['height'],
                $options['quality'],
                escapeshellarg($thumb_path)
            );
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($thumb_path)) {
                $thumbnails[] = [
                    'path' => $thumb_path,
                    'url' => $this->get_file_url($thumb_path),
                    'timestamp' => $timestamp,
                    'size' => filesize($thumb_path)
                ];
            }
        }
        
        if (empty($thumbnails)) {
            return [
                'success' => false,
                'message' => 'No thumbnails generated'
            ];
        }
        
        return [
            'success' => true,
            'thumbnails' => $thumbnails,
            'count' => count($thumbnails)
        ];
    }
    
    /**
     * Generate animated GIF preview from video
     *
     * @param string $video_path Video file path
     * @param array $options GIF options
     * @return array Result
     */
    public function generate_gif_preview($video_path, $options = []) {
        if (!$this->is_ffmpeg_available()) {
            return [
                'success' => false,
                'message' => 'FFmpeg is not available'
            ];
        }
        
        $defaults = [
            'start_time' => '00:00:01',
            'duration' => 3, // seconds
            'width' => 480,
            'fps' => 10,
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $path_info = pathinfo($video_path);
        $gif_filename = $path_info['filename'] . '_preview.gif';
        $gif_path = $this->upload_dir . '/video-thumbnails/' . $gif_filename;
        
        wp_mkdir_p($this->upload_dir . '/video-thumbnails');
        
        // Generate palette first for better quality
        $palette_path = $this->upload_dir . '/video-thumbnails/palette.png';
        
        $palette_command = sprintf(
            '%s -i %s -ss %s -t %d -vf "fps=%d,scale=%d:-1:flags=lanczos,palettegen" %s 2>&1',
            escapeshellarg($this->ffmpeg_path),
            escapeshellarg($video_path),
            $options['start_time'],
            $options['duration'],
            $options['fps'],
            $options['width'],
            escapeshellarg($palette_path)
        );
        
        exec($palette_command, $output, $return_var);
        
        if ($return_var !== 0 || !file_exists($palette_path)) {
            return [
                'success' => false,
                'message' => 'Failed to generate color palette'
            ];
        }
        
        // Generate GIF using palette
        $gif_command = sprintf(
            '%s -i %s -i %s -ss %s -t %d -lavfi "fps=%d,scale=%d:-1:flags=lanczos[x];[x][1:v]paletteuse" %s 2>&1',
            escapeshellarg($this->ffmpeg_path),
            escapeshellarg($video_path),
            escapeshellarg($palette_path),
            $options['start_time'],
            $options['duration'],
            $options['fps'],
            $options['width'],
            escapeshellarg($gif_path)
        );
        
        exec($gif_command, $output, $return_var);
        
        // Clean up palette
        if (file_exists($palette_path)) {
            unlink($palette_path);
        }
        
        if ($return_var === 0 && file_exists($gif_path)) {
            return [
                'success' => true,
                'path' => $gif_path,
                'url' => $this->get_file_url($gif_path),
                'size' => filesize($gif_path)
            ];
        }
        
        return [
            'success' => false,
            'message' => 'GIF generation failed'
        ];
    }
    
    /**
     * Get video metadata
     *
     * @param string $video_path Video file path
     * @return array|false Metadata or false
     */
    public function get_video_metadata($video_path) {
        if (!$this->ffprobe_path || !file_exists($video_path)) {
            return false;
        }
        
        $command = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s 2>&1',
            escapeshellarg($this->ffprobe_path),
            escapeshellarg($video_path)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            $json = implode('', $output);
            $data = json_decode($json, true);
            
            if ($data) {
                return $this->parse_metadata($data);
            }
        }
        
        return false;
    }
    
    /**
     * Get video duration
     *
     * @param string $video_path Video file path
     * @return float|false Duration in seconds or false
     */
    private function get_video_duration($video_path) {
        $metadata = $this->get_video_metadata($video_path);
        return $metadata ? $metadata['duration'] : false;
    }
    
    /**
     * Calculate evenly spaced thumbnail timestamps
     *
     * @param float $duration Video duration in seconds
     * @param int $count Number of thumbnails
     * @return array Array of timestamps
     */
    private function calculate_thumbnail_timestamps($duration, $count) {
        $timestamps = [];
        $interval = $duration / ($count + 1);
        
        for ($i = 1; $i <= $count; $i++) {
            $seconds = $interval * $i;
            $timestamps[] = gmdate('H:i:s', (int) $seconds);
        }
        
        return $timestamps;
    }
    
    /**
     * Parse metadata from FFprobe output
     *
     * @param array $data FFprobe data
     * @return array Parsed metadata
     */
    private function parse_metadata($data) {
        $metadata = [
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'bitrate' => 0,
            'codec' => '',
            'fps' => 0,
            'audio_codec' => '',
            'audio_channels' => 0,
        ];
        
        if (isset($data['format']['duration'])) {
            $metadata['duration'] = (float) $data['format']['duration'];
        }
        
        if (isset($data['format']['bit_rate'])) {
            $metadata['bitrate'] = (int) $data['format']['bit_rate'];
        }
        
        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                $metadata['width'] = $stream['width'] ?? 0;
                $metadata['height'] = $stream['height'] ?? 0;
                $metadata['codec'] = $stream['codec_name'] ?? '';
                
                if (isset($stream['r_frame_rate'])) {
                    $fps_parts = explode('/', $stream['r_frame_rate']);
                    if (count($fps_parts) === 2 && $fps_parts[1] > 0) {
                        $metadata['fps'] = round($fps_parts[0] / $fps_parts[1], 2);
                    }
                }
            } elseif ($stream['codec_type'] === 'audio') {
                $metadata['audio_codec'] = $stream['codec_name'] ?? '';
                $metadata['audio_channels'] = $stream['channels'] ?? 0;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Find FFmpeg binary
     *
     * @return string|false Path to FFmpeg or false
     */
    private function find_ffmpeg() {
        $possible_paths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows
            'ffmpeg' // System PATH
        ];
        
        foreach ($possible_paths as $path) {
            if ($this->test_binary($path, 'ffmpeg')) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Find FFprobe binary
     *
     * @return string|false Path to FFprobe or false
     */
    private function find_ffprobe() {
        $possible_paths = [
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            '/opt/local/bin/ffprobe',
            'C:\\ffmpeg\\bin\\ffprobe.exe', // Windows
            'ffprobe' // System PATH
        ];
        
        foreach ($possible_paths as $path) {
            if ($this->test_binary($path, 'ffprobe')) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Test if binary exists and works
     *
     * @param string $path Binary path
     * @param string $type Binary type
     * @return bool
     */
    private function test_binary($path, $type) {
        if (!function_exists('exec')) {
            return false;
        }
        
        exec(escapeshellarg($path) . ' -version 2>&1', $output, $return_var);
        
        return $return_var === 0 && stripos(implode('', $output), $type) !== false;
    }
    
    /**
     * Get file URL from path
     *
     * @param string $file_path File path
     * @return string File URL
     */
    private function get_file_url($file_path) {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
    }
}