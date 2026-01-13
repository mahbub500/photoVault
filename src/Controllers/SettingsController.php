<?php
/**
 * Settings Controller
 *
 * @package PhotoVault
 */

namespace PhotoVault\Controllers;

use PhotoVault\Models\Settings as SettingsModel;

class SettingsController {
    
    private $settings_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_model = new SettingsModel();
    }
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all_settings() {
        return $this->settings_model->get_all();
    }
    
    /**
     * Get setting by key
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_setting($key, $default = null) {
        return $this->settings_model->get($key, $default);
    }
    
    /**
     * Update setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function update_setting($key, $value) {
        return $this->settings_model->update($key, $value);
    }
    
    /**
     * Update multiple settings
     *
     * @param array $settings Array of key => value pairs
     * @return bool
     */
    public function update_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }
        
        $success = true;
        foreach ($settings as $key => $value) {
            if (!$this->settings_model->update($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete setting
     *
     * @param string $key Setting key
     * @return bool
     */
    public function delete_setting($key) {
        return $this->settings_model->delete($key);
    }
    
    /**
     * Reset setting to default
     *
     * @param string $key Setting key
     * @return bool
     */
    public function reset_setting($key) {
        return $this->settings_model->reset($key);
    }
    
    /**
     * Reset all settings to defaults
     *
     * @return bool
     */
    public function reset_all_settings() {
        return $this->settings_model->reset_all();
    }
    
    /**
     * Get general settings
     *
     * @return array
     */
    public function get_general_settings() {
        return [
            'items_per_page' => $this->get_setting('photovault_items_per_page', 20),
            'default_visibility' => $this->get_setting('photovault_default_visibility', 'private'),
            'enable_exif' => $this->get_setting('photovault_enable_exif', true),
            'enable_comments' => $this->get_setting('photovault_enable_comments', false),
            'enable_likes' => $this->get_setting('photovault_enable_likes', false),
        ];
    }
    
    /**
     * Update general settings
     *
     * @param array $data Settings data
     * @return bool
     */
    public function update_general_settings($data) {
        $settings = [];
        
        if (isset($data['photovault_items_per_page'])) {
            $settings['photovault_items_per_page'] = absint($data['photovault_items_per_page']);
        }
        
        if (isset($data['photovault_default_visibility'])) {
            $settings['photovault_default_visibility'] = sanitize_text_field($data['photovault_default_visibility']);
        }
        
        if (isset($data['photovault_enable_exif'])) {
            $settings['photovault_enable_exif'] = (bool) $data['photovault_enable_exif'];
        }
        
        if (isset($data['photovault_enable_comments'])) {
            $settings['photovault_enable_comments'] = (bool) $data['photovault_enable_comments'];
        }
        
        if (isset($data['photovault_enable_likes'])) {
            $settings['photovault_enable_likes'] = (bool) $data['photovault_enable_likes'];
        }
        
        return $this->update_settings($settings);
    }
    
    /**
     * Get upload settings
     *
     * @return array
     */
    public function get_upload_settings() {
        return [
            'max_upload_size' => $this->get_setting('photovault_max_upload_size', 10485760),
            'allowed_types' => $this->get_setting('photovault_allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']),
            'server_max' => wp_max_upload_size(),
        ];
    }
    
    /**
     * Update upload settings
     *
     * @param array $data Settings data
     * @return bool
     */
    public function update_upload_settings($data) {
        $settings = [];
        
        if (isset($data['photovault_max_upload_size'])) {
            $max_size = absint($data['photovault_max_upload_size']);
            $server_max = wp_max_upload_size();
            
            // Don't allow setting higher than server maximum
            if ($max_size <= $server_max) {
                $settings['photovault_max_upload_size'] = $max_size;
            }
        }
        
        if (isset($data['photovault_allowed_types'])) {
            $allowed_types = $this->sanitize_allowed_types($data['photovault_allowed_types']);
            $settings['photovault_allowed_types'] = $allowed_types;
        }
        
        return $this->update_settings($settings);
    }
    
    /**
     * Get processing settings
     *
     * @return array
     */
    public function get_processing_settings() {
        return [
            'image_quality' => $this->get_setting('photovault_image_quality', 85),
            'auto_optimize' => $this->get_setting('photovault_auto_optimize', true),
            'thumbnail_width' => $this->get_setting('photovault_thumbnail_width', 300),
            'thumbnail_height' => $this->get_setting('photovault_thumbnail_height', 300),
            'thumbnail_quality' => $this->get_setting('photovault_thumbnail_quality', 85),
        ];
    }
    
    /**
     * Update processing settings
     *
     * @param array $data Settings data
     * @return bool
     */
    public function update_processing_settings($data) {
        $settings = [];
        
        if (isset($data['photovault_image_quality'])) {
            $settings['photovault_image_quality'] = $this->sanitize_quality($data['photovault_image_quality']);
        }
        
        if (isset($data['photovault_auto_optimize'])) {
            $settings['photovault_auto_optimize'] = (bool) $data['photovault_auto_optimize'];
        }
        
        if (isset($data['photovault_thumbnail_width'])) {
            $settings['photovault_thumbnail_width'] = absint($data['photovault_thumbnail_width']);
        }
        
        if (isset($data['photovault_thumbnail_height'])) {
            $settings['photovault_thumbnail_height'] = absint($data['photovault_thumbnail_height']);
        }
        
        if (isset($data['photovault_thumbnail_quality'])) {
            $settings['photovault_thumbnail_quality'] = $this->sanitize_quality($data['photovault_thumbnail_quality']);
        }
        
        return $this->update_settings($settings);
    }
    
    /**
     * Get watermark settings
     *
     * @return array
     */
    public function get_watermark_settings() {
        return [
            'enable_watermark' => $this->get_setting('photovault_enable_watermark', false),
            'watermark_text' => $this->get_setting('photovault_watermark_text', get_bloginfo('name')),
            'watermark_position' => $this->get_setting('photovault_watermark_position', 'bottom-right'),
            'watermark_opacity' => $this->get_setting('photovault_watermark_opacity', 50),
        ];
    }
    
    /**
     * Update watermark settings
     *
     * @param array $data Settings data
     * @return bool
     */
    public function update_watermark_settings($data) {
        $settings = [];
        
        if (isset($data['photovault_enable_watermark'])) {
            $settings['photovault_enable_watermark'] = (bool) $data['photovault_enable_watermark'];
        }
        
        if (isset($data['photovault_watermark_text'])) {
            $settings['photovault_watermark_text'] = sanitize_text_field($data['photovault_watermark_text']);
        }
        
        if (isset($data['photovault_watermark_position'])) {
            $settings['photovault_watermark_position'] = sanitize_text_field($data['photovault_watermark_position']);
        }
        
        if (isset($data['photovault_watermark_opacity'])) {
            $settings['photovault_watermark_opacity'] = $this->sanitize_opacity($data['photovault_watermark_opacity']);
        }
        
        return $this->update_settings($settings);
    }
    
    /**
     * Validate and sanitize settings data
     *
     * @param array $data Raw settings data
     * @return array Sanitized settings data
     */
    public function validate_settings($data) {
        $validated = [];
        
        // General settings
        if (isset($data['photovault_items_per_page'])) {
            $validated['photovault_items_per_page'] = max(1, min(100, absint($data['photovault_items_per_page'])));
        }
        
        if (isset($data['photovault_default_visibility'])) {
            $allowed_visibility = ['private', 'public', 'shared'];
            $visibility = sanitize_text_field($data['photovault_default_visibility']);
            $validated['photovault_default_visibility'] = in_array($visibility, $allowed_visibility) ? $visibility : 'private';
        }
        
        // Upload settings
        if (isset($data['photovault_max_upload_size'])) {
            $max_size = absint($data['photovault_max_upload_size']);
            $server_max = wp_max_upload_size();
            $validated['photovault_max_upload_size'] = min($max_size, $server_max);
        }
        
        if (isset($data['photovault_allowed_types'])) {
            $validated['photovault_allowed_types'] = $this->sanitize_allowed_types($data['photovault_allowed_types']);
        }
        
        // Processing settings
        if (isset($data['photovault_image_quality'])) {
            $validated['photovault_image_quality'] = $this->sanitize_quality($data['photovault_image_quality']);
        }
        
        if (isset($data['photovault_thumbnail_width'])) {
            $validated['photovault_thumbnail_width'] = max(50, min(1000, absint($data['photovault_thumbnail_width'])));
        }
        
        if (isset($data['photovault_thumbnail_height'])) {
            $validated['photovault_thumbnail_height'] = max(50, min(1000, absint($data['photovault_thumbnail_height'])));
        }
        
        if (isset($data['photovault_thumbnail_quality'])) {
            $validated['photovault_thumbnail_quality'] = $this->sanitize_quality($data['photovault_thumbnail_quality']);
        }
        
        // Watermark settings
        if (isset($data['photovault_watermark_position'])) {
            $allowed_positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
            $position = sanitize_text_field($data['photovault_watermark_position']);
            $validated['photovault_watermark_position'] = in_array($position, $allowed_positions) ? $position : 'bottom-right';
        }
        
        if (isset($data['photovault_watermark_opacity'])) {
            $validated['photovault_watermark_opacity'] = $this->sanitize_opacity($data['photovault_watermark_opacity']);
        }
        
        // Boolean settings
        $boolean_settings = [
            'photovault_enable_exif',
            'photovault_enable_comments',
            'photovault_enable_likes',
            'photovault_auto_optimize',
            'photovault_enable_watermark'
        ];
        
        foreach ($boolean_settings as $key) {
            if (isset($data[$key])) {
                $validated[$key] = (bool) $data[$key];
            }
        }
        
        // Text settings
        if (isset($data['photovault_watermark_text'])) {
            $validated['photovault_watermark_text'] = sanitize_text_field($data['photovault_watermark_text']);
        }
        
        return $validated;
    }
    
    /**
     * Export settings
     *
     * @return array
     */
    public function export_settings() {
        return $this->settings_model->export();
    }
    
    /**
     * Import settings
     *
     * @param array $settings Settings to import
     * @return bool
     */
    public function import_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }
        
        // Validate settings before import
        $validated = $this->validate_settings($settings);
        
        return $this->settings_model->import($validated);
    }
    
    /**
     * Get default settings
     *
     * @return array
     */
    public function get_defaults() {
        return $this->settings_model->get_defaults();
    }
    
    /**
     * Check if setting exists
     *
     * @param string $key Setting key
     * @return bool
     */
    public function has_setting($key) {
        return $this->settings_model->exists($key);
    }
    
    /**
     * Get settings by group
     *
     * @param string $group Group name (general, upload, processing, watermark)
     * @return array
     */
    public function get_settings_by_group($group) {
        switch ($group) {
            case 'general':
                return $this->get_general_settings();
            case 'upload':
                return $this->get_upload_settings();
            case 'processing':
                return $this->get_processing_settings();
            case 'watermark':
                return $this->get_watermark_settings();
            default:
                return [];
        }
    }
    
    /**
     * Update settings by group
     *
     * @param string $group Group name
     * @param array $data Settings data
     * @return bool
     */
    public function update_settings_by_group($group, $data) {
        switch ($group) {
            case 'general':
                return $this->update_general_settings($data);
            case 'upload':
                return $this->update_upload_settings($data);
            case 'processing':
                return $this->update_processing_settings($data);
            case 'watermark':
                return $this->update_watermark_settings($data);
            default:
                return false;
        }
    }
    
    /**
     * Sanitize quality value (1-100)
     *
     * @param mixed $value Quality value
     * @return int
     */
    private function sanitize_quality($value) {
        $value = absint($value);
        return max(1, min(100, $value));
    }
    
    /**
     * Sanitize opacity value (0-100)
     *
     * @param mixed $value Opacity value
     * @return int
     */
    private function sanitize_opacity($value) {
        $value = absint($value);
        return max(0, min(100, $value));
    }
    
    /**
     * Sanitize allowed file types
     *
     * @param mixed $value File types
     * @return array
     */
    private function sanitize_allowed_types($value) {
        if (!is_array($value)) {
            return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        }
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return array_intersect($value, $allowed);
    }
}