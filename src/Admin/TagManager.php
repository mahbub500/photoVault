<?php
/**
 * Admin Menu Manager - Updated & Secure
 *
 * @package PhotoVault
 */

namespace PhotoVault\Admin;

use PhotoVault\Controllers\TagController;

class TagManager {

    private $tag_controller;
    
    public function __construct() {

        $this->tag_controller = new TagController();

        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        $this->render_tag_ajax();
    }

    private function render_tag_ajax() {
        // Tag operations
        add_action('wp_ajax_add_tag', [$this->tag_controller, 'add_tag']);
        add_action('wp_ajax_get_tags', [$this->tag_controller, 'get_tags']);
        add_action('wp_ajax_get_images_by_tag', [$this->tag_controller, 'get_images_by_tag']);
        add_action('wp_ajax_remove_tag', [$this->tag_controller, 'remove_tag']);
        add_action('wp_ajax_update_tag', [$this->tag_controller, 'update_tag']);
        add_action('wp_ajax_delete_tag', [$this->tag_controller, 'delete_tag']);
        add_action('wp_ajax_get_image_tags', [$this->tag_controller, 'get_image_tags']);

        add_action('wp_ajax_pv_get_user_images', [$this->tag_controller, 'get_user_images_for_assignment']);
        add_action('wp_ajax_pv_assign_images_to_tag', [$this->tag_controller, 'assign_images_to_tag']);
        add_action('wp_ajax_pv_get_all_images', [$this->tag_controller, 'get_all_user_images']);
        add_action('wp_ajax_pv_bulk_assign_tag', [$this->tag_controller, 'bulk_assign_tag']);
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
        
        // Localize script
        wp_localize_script(
            'photovault-admin-tag',
            'photoVaultTag',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'photovault_nonce' => wp_create_nonce('photovault_nonce'),
                'i18n' => [
                    'addNewTag' => esc_html__('Add New Tag', 'photovault'),
                    'editTag' => esc_html__('Edit Tag', 'photovault'),
                    'pleaseEnterTagName' => esc_html__('Please enter a tag name', 'photovault'),
                    'saving' => esc_html__('Saving...', 'photovault'),
                    'saveTag' => esc_html__('Save Tag', 'photovault'),
                    'errorSavingTag' => esc_html__('Error saving tag', 'photovault'),
                    'deleteTagConfirm' => esc_html__('Are you sure you want to delete this tag? This will remove it from all images.', 'photovault'),
                    'errorDeletingTag' => esc_html__('Error deleting tag', 'photovault'),
                    'removeTagFromImage' => esc_html__('Remove tag from this image', 'photovault'),
                    'errorLoadingImages' => esc_html__('Error loading images. Please try again.', 'photovault'),
                    'removeTagConfirm' => esc_html__('Remove this tag from the image?', 'photovault'),
                    'failedToRemoveTag' => esc_html__('Failed to remove tag', 'photovault'),
                    'errorRemovingTag' => esc_html__('Error removing tag', 'photovault'),
                    'images' => esc_html__('images', 'photovault'),
                    'pleaseSelectImage' => esc_html__('Please select at least one image', 'photovault'),
                    'assigning' => esc_html__('Assigning...', 'photovault'),
                    'assignSelectedImages' => esc_html__('Assign Selected Images', 'photovault'),
                    'errorAssigningImages' => esc_html__('Error assigning images', 'photovault')
                ]
            ]
        );
    }
    
    /**
     * Register admin menus
     */
    public function register_menus() {

        // Tags submenu
        add_submenu_page(
            'photovault',
            esc_html__('Tags', 'photovault'),
            esc_html__('Tags', 'photovault'),
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
            echo '<h1>' . esc_html__('Tag View', 'photovault') . '</h1>';

            if ($error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__('Template file not found.', 'photovault') . '</p></div>';
            }

            echo '<a href="' . esc_url(admin_url('admin.php?page=photovault-tags')) . '" class="button">';
            echo esc_html__('Back to Tags', 'photovault');
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
                // translators: %1$s is the missing view filename wrapped in code tags.
                esc_html__('The view file %1$s does not exist.', 'photovault'),
                '<code>' . esc_html($view) . '.php</code>'
            ) . '</p>';
            echo '</div>';
        }
    }
}
