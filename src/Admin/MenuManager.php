<?php
/**
 * Admin Menu Manager - Updated
 *
 * @package PhotoVault
 */

namespace PhotoVault\Admin;

use PhotoVault\Controllers\TagController;

class MenuManager {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
    }
    
    /**
     * Register admin menus
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __('PhotoVault', 'photovault'),           // Page title
            __('PhotoVault', 'photovault'),           // Menu title
            'upload_files',                            // Capability
            'photovault',                              // Menu slug
            [$this, 'render_main_page'],              // Callback
            'dashicons-format-gallery',                // Icon
            30                                         // Position
        );
        
        // Gallery submenu (default page)
        add_submenu_page(
            'photovault',                              // Parent slug
            __('Gallery', 'photovault'),              // Page title
            __('Gallery', 'photovault'),              // Menu title
            'upload_files',                            // Capability
            'photovault',                              // Menu slug (same as parent)
            [$this, 'render_main_page']               // Callback
        );
        
        // Albums submenu
        add_submenu_page(
            'photovault',
            __('Albums', 'photovault'),
            __('Albums', 'photovault'),
            'upload_files',
            'photovault-albums',
            [$this, 'render_albums_page']
        );
        
        // Timeline submenu
        add_submenu_page(
            'photovault',
            __('Timeline', 'photovault'),
            __('Timeline', 'photovault'),
            'upload_files',
            'photovault-timeline',
            [$this, 'render_timeline_page']
        );
        
        // Shared with Me submenu
        add_submenu_page(
            'photovault',
            __('Shared with Me', 'photovault'),
            __('Shared with Me', 'photovault'),
            'upload_files',
            'photovault-shared',
            [$this, 'render_shared_page']
        );
        
    }
    
    /**
     * Add PhotoVault to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('upload_files')) {
            return;
        }
        
        // Main node
        $wp_admin_bar->add_node([
            'id' => 'photovault',
            'title' => '<span class="ab-icon dashicons-format-gallery"></span> ' . __('PhotoVault', 'photovault'),
            'href' => admin_url('admin.php?page=photovault'),
        ]);
        
        // Gallery
        $wp_admin_bar->add_node([
            'parent' => 'photovault',
            'id' => 'photovault-gallery',
            'title' => __('Gallery', 'photovault'),
            'href' => admin_url('admin.php?page=photovault'),
        ]);
        
        // Upload
        $wp_admin_bar->add_node([
            'parent' => 'photovault',
            'id' => 'photovault-upload',
            'title' => __('Upload Images', 'photovault'),
            'href' => admin_url('admin.php?page=photovault'),
            'meta' => [
                'onclick' => 'jQuery("#pv-upload-btn").click(); return false;'
            ]
        ]);
        
        // Albums
        $wp_admin_bar->add_node([
            'parent' => 'photovault',
            'id' => 'photovault-albums',
            'title' => __('Albums', 'photovault'),
            'href' => admin_url('admin.php?page=photovault-albums'),
        ]);
        
        // Timeline
        $wp_admin_bar->add_node([
            'parent' => 'photovault',
            'id' => 'photovault-timeline',
            'title' => __('Timeline', 'photovault'),
            'href' => admin_url('admin.php?page=photovault-timeline'),
        ]);
    }
    
    /**
     * Render main gallery page
     */
    public function render_main_page() {
        $this->render_view('main');
    }
    
    /**
     * Render albums page
     */
    public function render_albums_page() {
        $this->render_view('albums');
    }
    
    /**
     * Render timeline page
     */
    public function render_timeline_page() {
        $this->render_view('timeline');
    }  
    
    /**
     * Render shared with me page
     */
    public function render_shared_page() {
        $this->render_view('shared');
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'photovault'));
        }
        
        // Save settings if form submitted
        if (isset($_POST['photovault_settings_submit'])) {
            check_admin_referer('photovault_settings_nonce');
            $this->save_settings();
        }
        
        $this->render_view('settings');
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Nonce is already verified in render_settings_page() before calling this method
        
        $settings = [
            'photovault_max_upload_size' => isset($_POST['max_upload_size']) ? intval($_POST['max_upload_size']) : 10485760,
            'photovault_thumbnail_width' => isset($_POST['thumbnail_width']) ? intval($_POST['thumbnail_width']) : 300,
            'photovault_thumbnail_height' => isset($_POST['thumbnail_height']) ? intval($_POST['thumbnail_height']) : 300,
            'photovault_thumbnail_quality' => isset($_POST['thumbnail_quality']) ? intval($_POST['thumbnail_quality']) : 85,
            'photovault_enable_watermark' => isset($_POST['enable_watermark']),
            'photovault_watermark_text' => isset($_POST['watermark_text']) ? sanitize_text_field(wp_unslash($_POST['watermark_text'])) : '',
            'photovault_default_visibility' => isset($_POST['default_visibility']) ? sanitize_text_field(wp_unslash($_POST['default_visibility'])) : 'private',
            'photovault_enable_exif' => isset($_POST['enable_exif']),
            'photovault_items_per_page' => isset($_POST['items_per_page']) ? intval($_POST['items_per_page']) : 20,
            'photovault_image_quality' => isset($_POST['image_quality']) ? intval($_POST['image_quality']) : 85,
            'photovault_auto_optimize' => isset($_POST['auto_optimize']),
        ];
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        // Show success message
        add_settings_error(
            'photovault_messages',
            'photovault_message',
            __('Settings saved successfully.', 'photovault'),
            'updated'
        );
    }
    
    /**
     * Render view template
     *
     * @param string $view View name
     */
    private function render_view($view) {
        $file = PHOTOVAULT_PLUGIN_DIR . "src/Admin/Views/{$view}.php";
        
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('View not found', 'photovault') . '</h1>';
            echo '<p>' . sprintf(
                // translators: %1$s is the missing view filename wrapped in HTML <code> tags.
                esc_html__('The view file %1$s does not exist.', 'photovault'),
                '<code>' . esc_html($view) . '.php</code>'
            ) . '</p>';
            echo '</div>';
        }
    }

    
    /**
     * Get current menu page
     *
     * @return string Current page slug
     */
    public function get_current_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading GET parameter for page identification only, not processing form data
        return isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    }
    
    /**
     * Check if current page is PhotoVault page
     *
     * @return bool
     */
    public function is_photovault_page() {
        $current_page = $this->get_current_page();
        return strpos($current_page, 'photovault') === 0;
    }
}