<?php
/**
 * Admin Menu Manager - Updated
 *
 * @package PhotoVault
 */

namespace PhotoVault\Admin;

use PhotoVault\Controllers\TagController;

class TagManager {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on PhotoVault pages
        if (strpos($hook, 'photovault-tags') === false) {
            return;
        }
        
        // Enqueue WordPress media uploader
        wp_enqueue_media();
        
        // Admin CSS
        wp_enqueue_style(
            'photovault-admin-tag',
            PHOTOVAULT_PLUGIN_URL . 'assets/css/admin/tag.css',
            [],
            PHOTOVAULT_VERSION
        );

        // Admin JavaScript
        wp_enqueue_script(
            'photovault-admin-tag',
            PHOTOVAULT_PLUGIN_URL . 'assets/js/admin/tag.js',
            ['jquery', 'wp-util'],
            PHOTOVAULT_VERSION,
            true
        );      
        
    }
    
    /**
     * Register admin menus
     */
    public function register_menus() {

        // Tags submenu
        add_submenu_page(
            'photovault',
            __('Tags', 'photovault'),
            __('Tags', 'photovault'),
            'upload_files',
            'photovault-tags',
            [$this, 'render_tags_page']
        );
    }  
    
    /**
     * Render tags page
     */
    public function render_tags_page() {
        // Check if viewing a specific tag
        if (isset($_GET['tag_id']) && !empty($_GET['tag_id'])) {
            $this->render_tag_view_page();
        } else {
            $this->render_view('tags');
        }
    }
    
    /**
     * Render tag view page (images by tag)
     */
    private function render_tag_view_page() {
        $tag_controller = new TagController();
        $data = $tag_controller->get_tag_view_data();
        
        // Extract variables for the template
        $tag = $data['tag'];
        $images = $data['images'];
        $error = $data['error'];
        
        // Load the tag view template
        $view_file = PHOTOVAULT_PLUGIN_DIR . 'src/Admin/Views/tag-view.php';
        
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . __('Tag View', 'photovault') . '</h1>';
            if ($error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Template file not found.', 'photovault') . '</p></div>';
            }
            echo '<a href="' . esc_url(admin_url('admin.php?page=photovault-tags')) . '" class="button">';
            echo __('Back to Tags', 'photovault');
            echo '</a>';
            echo '</div>';
        }
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
                esc_html__('The view file %s does not exist.', 'photovault'),
                '<code>' . esc_html($view) . '.php</code>'
            ) . '</p>';
            echo '</div>';
        }
    }
    
}