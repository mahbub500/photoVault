<?php
/**
 * Settings Page Class
 *
 * @package PhotoVault
 */
namespace PhotoVault\Admin;
use PhotoVault\Controllers\SettingsController;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class SettingsManager {
    
    /**
     * Settings Controller
     *
     * @var SettingsController
     */
    private $controller;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->controller = new SettingsController();
        
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_post_photovault_export_settings', [$this, 'export_settings']);
        add_action('admin_post_photovault_import_settings', [$this, 'import_settings']);
        add_action('admin_post_photovault_reset_settings', [$this, 'reset_settings']);
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'photovault',
            __('Settings', 'photovault'),
            __('Settings', 'photovault'),
            'manage_options',
            'photovault-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'photovault_page_photovault-settings') {
            return;
        }
        
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue custom styles
        if (strpos($hook, 'photovault-settings') !== false) {
            wp_enqueue_style(
                'photovault-admin-settings',
                PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/settings.css',
                ['photovault-admin-main'],
                PHOTOVAULT_VERSION
            );
        }
        
        if (str_contains($hook, 'photovault-settings') ) {
            wp_enqueue_script(
                'photovault-settings',
                PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/settings.js',
                ['jquery', 'photovault-admin-main'],
                PHOTOVAULT_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('photovault-settings', 'photoVaultSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('photovault_settings_nonce'),
            'strings' => [
                'confirm_reset' => __('Are you sure you want to reset all settings to defaults? This cannot be undone.', 'photovault'),
                'confirm_import' => __('Importing settings will overwrite your current settings. Continue?', 'photovault'),
            ]
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('photovault_general', 'photovault_items_per_page', [
            'type' => 'integer',
            'default' => 20,
            'sanitize_callback' => 'absint'
        ]);
        
        register_setting('photovault_general', 'photovault_default_visibility', [
            'type' => 'string',
            'default' => 'private',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('photovault_general', 'photovault_enable_comments', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('photovault_general', 'photovault_enable_likes', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('photovault_general', 'photovault_enable_exif', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        // Upload Settings
        register_setting('photovault_upload', 'photovault_max_upload_size', [
            'type' => 'integer',
            'default' => 10485760,
            'sanitize_callback' => 'absint'
        ]);
        
        register_setting('photovault_upload', 'photovault_allowed_types', [
            'type' => 'array',
            'default' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'sanitize_callback' => [$this, 'sanitize_allowed_types']
        ]);
        
        // Image Processing Settings
        register_setting('photovault_processing', 'photovault_image_quality', [
            'type' => 'integer',
            'default' => 85,
            'sanitize_callback' => [$this, 'sanitize_quality']
        ]);
        
        register_setting('photovault_processing', 'photovault_auto_optimize', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('photovault_processing', 'photovault_thumbnail_width', [
            'type' => 'integer',
            'default' => 300,
            'sanitize_callback' => 'absint'
        ]);
        
        register_setting('photovault_processing', 'photovault_thumbnail_height', [
            'type' => 'integer',
            'default' => 300,
            'sanitize_callback' => 'absint'
        ]);
        
        register_setting('photovault_processing', 'photovault_thumbnail_quality', [
            'type' => 'integer',
            'default' => 85,
            'sanitize_callback' => [$this, 'sanitize_quality']
        ]);
        
        // Watermark Settings
        register_setting('photovault_watermark', 'photovault_enable_watermark', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('photovault_watermark', 'photovault_watermark_text', [
            'type' => 'string',
            'default' => get_bloginfo('name'),
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('photovault_watermark', 'photovault_watermark_position', [
            'type' => 'string',
            'default' => 'bottom-right',
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        
        register_setting('photovault_watermark', 'photovault_watermark_opacity', [
            'type' => 'integer',
            'default' => 50,
            'sanitize_callback' => [$this, 'sanitize_opacity']
        ]);
    }
    
    /**
     * Sanitize allowed file types
     */
    public function sanitize_allowed_types($value) {
        if (!is_array($value)) {
            return ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        }
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        return array_intersect($value, $allowed);
    }
    
    /**
     * Sanitize quality value (0-100)
     */
    public function sanitize_quality($value) {
        $value = absint($value);
        return max(1, min(100, $value));
    }
    
    /**
     * Sanitize opacity value (0-100)
     */
    public function sanitize_opacity($value) {
        $value = absint($value);
        return max(0, min(100, $value));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        // Get settings data from controller
        $data = $this->get_settings_data($active_tab);
        
        // Include template
        include PHOTOVAULT_PLUGIN_DIR . "src/Admin/Views/settings.php";
    }
    
    /**
     * Get settings data for template
     */
    private function get_settings_data($active_tab) {
        $data = [
            'active_tab' => $active_tab,
            'tabs' => [
                'general' => __('General', 'photovault'),
                'upload' => __('Upload', 'photovault'),
                'processing' => __('Image Processing', 'photovault'),
                'watermark' => __('Watermark', 'photovault'),
            ],
        ];
        
        // Get settings from controller
        $data['general'] = $this->controller->get_general_settings();
        $data['upload'] = $this->controller->get_upload_settings();
        $data['processing'] = $this->controller->get_processing_settings();
        $data['watermark'] = $this->controller->get_watermark_settings();
        
        // File types
        $data['file_types'] = [
            'jpg' => 'JPG',
            'jpeg' => 'JPEG',
            'png' => 'PNG',
            'gif' => 'GIF',
            'webp' => 'WebP',
            'bmp' => 'BMP',
            'svg' => 'SVG'
        ];
        
        // Visibility options
        $data['visibility_options'] = [
            'private' => __('Private', 'photovault'),
            'public' => __('Public', 'photovault'),
            'shared' => __('Shared', 'photovault'),
        ];
        
        // Position options
        $data['position_options'] = [
            'top-left' => __('Top Left', 'photovault'),
            'top-right' => __('Top Right', 'photovault'),
            'bottom-left' => __('Bottom Left', 'photovault'),
            'bottom-right' => __('Bottom Right', 'photovault'),
            'center' => __('Center', 'photovault'),
        ];
        
        return $data;
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'photovault'));
        }
        check_admin_referer('photovault_export_settings');
        
        $settings = $this->controller->export_settings();
        
        // Set headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="photovault-settings-' . gmdate('Y-m-d') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo json_encode($settings, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Import settings
     */
    public function import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'photovault'));
        }
        
        check_admin_referer('photovault_import_settings');
        
        // Validate that the file was uploaded
        if (!isset($_FILES['import_file']) || 
            !isset($_FILES['import_file']['error']) || 
            !isset($_FILES['import_file']['tmp_name'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'upload_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Check for upload errors
        if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'upload_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Sanitize the file path
        $tmp_file = sanitize_text_field(wp_unslash($_FILES['import_file']['tmp_name']));
        
        // Validate file exists
        if (!file_exists($tmp_file)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'upload_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Read and decode file content
        $file_content = file_get_contents($tmp_file);
        $settings = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'invalid_json'
            ], admin_url('admin.php')));
            exit;
        }
        
        if ($this->controller->import_settings($settings)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'settings-updated' => 'true',
                'imported' => 'true'
            ], admin_url('admin.php')));
        } else {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'import_failed'
            ], admin_url('admin.php')));
        }
        exit;
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'photovault'));
        }
        
        check_admin_referer('photovault_reset_settings');
        
        if ($this->controller->reset_all_settings()) {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'settings-updated' => 'true',
                'reset' => 'true'
            ], admin_url('admin.php')));
        } else {
            wp_safe_redirect(add_query_arg([
                'page' => 'photovault-settings',
                'tab' => 'general',
                'error' => 'reset_failed'
            ], admin_url('admin.php')));
        }
        exit;
    }
}