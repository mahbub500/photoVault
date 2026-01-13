<?php
/**
 * Settings Model
 *
 * @package PhotoVault
 */

namespace PhotoVault\Models;

class Settings {
    
    /**
     * Settings prefix
     */
    const PREFIX = 'photovault_';
    
    /**
     * Default settings
     */
    private $defaults = [
        'photovault_max_upload_size' => 10485760, // 10MB
        'photovault_allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'photovault_thumbnail_width' => 300,
        'photovault_thumbnail_height' => 300,
        'photovault_thumbnail_quality' => 85,
        'photovault_enable_watermark' => false,
        'photovault_watermark_text' => '',
        'photovault_watermark_position' => 'bottom-right',
        'photovault_watermark_opacity' => 50,
        'photovault_default_visibility' => 'private',
        'photovault_enable_exif' => true,
        'photovault_items_per_page' => 20,
        'photovault_enable_comments' => false,
        'photovault_enable_likes' => false,
        'photovault_image_quality' => 85,
        'photovault_auto_optimize' => true,
    ];
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all() {
        $settings = [];
        
        foreach ($this->defaults as $key => $default) {
            $settings[$key] = get_option($key, $default);
        }
        
        return $settings;
    }
    
    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get($key, $default = null) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        // Use default from defaults array if no custom default provided
        if ($default === null && isset($this->defaults[$key])) {
            $default = $this->defaults[$key];
        }
        
        return get_option($key, $default);
    }
    
    /**
     * Update setting
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function update($key, $value) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        return update_option($key, $value);
    }
    
    /**
     * Delete setting
     *
     * @param string $key Setting key
     * @return bool
     */
    public function delete($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        return delete_option($key);
    }
    
    /**
     * Reset setting to default
     *
     * @param string $key Setting key
     * @return bool
     */
    public function reset($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        if (isset($this->defaults[$key])) {
            return update_option($key, $this->defaults[$key]);
        }
        
        return false;
    }
    
    /**
     * Reset all settings to defaults
     *
     * @return bool
     */
    public function reset_all() {
        $success = true;
        
        foreach ($this->defaults as $key => $value) {
            if (!update_option($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Check if setting exists
     *
     * @param string $key Setting key
     * @return bool
     */
    public function exists($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        return get_option($key) !== false;
    }
    
    /**
     * Get default settings
     *
     * @return array
     */
    public function get_defaults() {
        return $this->defaults;
    }
    
    /**
     * Get default value for a setting
     *
     * @param string $key Setting key
     * @return mixed|null
     */
    public function get_default($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        return $this->defaults[$key] ?? null;
    }
    
    /**
     * Export settings
     *
     * @return array
     */
    public function export() {
        $export = [
            'version' => PHOTOVAULT_VERSION,
            'export_date' => current_time('mysql'),
            'settings' => $this->get_all()
        ];
        
        return $export;
    }
    
    /**
     * Import settings
     *
     * @param array $data Settings data
     * @return bool
     */
    public function import($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // If export format, extract settings
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data = $data['settings'];
        }
        
        $success = true;
        
        foreach ($data as $key => $value) {
            // Only import known settings
            if (isset($this->defaults[$key])) {
                if (!update_option($key, $value)) {
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Get settings as JSON
     *
     * @param bool $pretty Pretty print JSON
     * @return string
     */
    public function to_json($pretty = false) {
        $flags = $pretty ? JSON_PRETTY_PRINT : 0;
        return json_encode($this->export(), $flags);
    }
    
    /**
     * Import settings from JSON
     *
     * @param string $json JSON string
     * @return bool
     */
    public function from_json($json) {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $this->import($data);
    }
    
    /**
     * Backup current settings
     *
     * @return bool
     */
    public function backup() {
        $backup = $this->export();
        $backup['backup_date'] = current_time('mysql');
        
        return update_option('photovault_settings_backup', $backup);
    }
    
    /**
     * Restore from backup
     *
     * @return bool
     */
    public function restore_backup() {
        $backup = get_option('photovault_settings_backup');
        
        if (!$backup || !isset($backup['settings'])) {
            return false;
        }
        
        return $this->import($backup['settings']);
    }
    
    /**
     * Get backup
     *
     * @return array|false
     */
    public function get_backup() {
        return get_option('photovault_settings_backup', false);
    }
    
    /**
     * Delete backup
     *
     * @return bool
     */
    public function delete_backup() {
        return delete_option('photovault_settings_backup');
    }
    
    /**
     * Get settings by prefix
     *
     * @param string $prefix Prefix to filter by (without photovault_)
     * @return array
     */
    public function get_by_prefix($prefix) {
        $settings = [];
        $full_prefix = self::PREFIX . $prefix;
        
        foreach ($this->defaults as $key => $default) {
            if (strpos($key, $full_prefix) === 0) {
                $settings[$key] = get_option($key, $default);
            }
        }
        
        return $settings;
    }
    
    /**
     * Validate setting value
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool
     */
    public function validate($key, $value) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        // Check if setting exists in defaults
        if (!isset($this->defaults[$key])) {
            return false;
        }
        
        $default = $this->defaults[$key];
        
        // Type validation
        if (is_bool($default) && !is_bool($value)) {
            return false;
        }
        
        if (is_int($default) && !is_numeric($value)) {
            return false;
        }
        
        if (is_array($default) && !is_array($value)) {
            return false;
        }
        
        // Specific validations
        switch ($key) {
            case 'photovault_image_quality':
            case 'photovault_thumbnail_quality':
                return is_numeric($value) && $value >= 1 && $value <= 100;
                
            case 'photovault_watermark_opacity':
                return is_numeric($value) && $value >= 0 && $value <= 100;
                
            case 'photovault_max_upload_size':
                return is_numeric($value) && $value > 0 && $value <= wp_max_upload_size();
                
            case 'photovault_thumbnail_width':
            case 'photovault_thumbnail_height':
                return is_numeric($value) && $value >= 50 && $value <= 1000;
                
            case 'photovault_items_per_page':
                return is_numeric($value) && $value >= 1 && $value <= 100;
                
            case 'photovault_default_visibility':
                return in_array($value, ['private', 'public', 'shared']);
                
            case 'photovault_watermark_position':
                return in_array($value, ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center']);
                
            case 'photovault_allowed_types':
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
                return is_array($value) && count(array_diff($value, $allowed)) === 0;
        }
        
        return true;
    }
    
    /**
     * Get setting type
     *
     * @param string $key Setting key
     * @return string|null
     */
    public function get_type($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        if (!isset($this->defaults[$key])) {
            return null;
        }
        
        $value = $this->defaults[$key];
        
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_array($value)) {
            return 'array';
        } elseif (is_string($value)) {
            return 'string';
        }
        
        return 'mixed';
    }
    
    /**
     * Get setting description
     *
     * @param string $key Setting key
     * @return string
     */
    public function get_description($key) {
        // Add prefix if not present
        if (strpos($key, self::PREFIX) !== 0) {
            $key = self::PREFIX . $key;
        }
        
        $descriptions = [
            'photovault_max_upload_size' => __('Maximum file size for uploads in bytes', 'photovault'),
            'photovault_allowed_types' => __('Allowed image file types', 'photovault'),
            'photovault_thumbnail_width' => __('Width of thumbnail images in pixels', 'photovault'),
            'photovault_thumbnail_height' => __('Height of thumbnail images in pixels', 'photovault'),
            'photovault_thumbnail_quality' => __('Quality of thumbnail images (1-100)', 'photovault'),
            'photovault_enable_watermark' => __('Enable watermark on images', 'photovault'),
            'photovault_watermark_text' => __('Text to use for watermark', 'photovault'),
            'photovault_watermark_position' => __('Position of watermark on images', 'photovault'),
            'photovault_watermark_opacity' => __('Opacity of watermark (0-100)', 'photovault'),
            'photovault_default_visibility' => __('Default visibility for new images', 'photovault'),
            'photovault_enable_exif' => __('Extract and display EXIF data from images', 'photovault'),
            'photovault_items_per_page' => __('Number of items to display per page', 'photovault'),
            'photovault_enable_comments' => __('Enable comments on images', 'photovault'),
            'photovault_enable_likes' => __('Enable likes/favorites on images', 'photovault'),
            'photovault_image_quality' => __('Quality of processed images (1-100)', 'photovault'),
            'photovault_auto_optimize' => __('Automatically optimize images on upload', 'photovault'),
        ];
        
        return $descriptions[$key] ?? '';
    }
}